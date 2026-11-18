<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProjectController extends Controller
{
    public function __construct(private readonly Access $access) {}

    public function index(Request $request, string $organization): Response
    {
        $user = $request->user();
        $org = $this->access->organization($user, $organization);
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:active,closed,archived']);
        $q = $filters['q'] ?? '';
        $status = $filters['status'] ?? '';

        return Inertia::render('Projects', [
            'organization' => $org,
            'isAdmin' => $this->access->admin($user, $org->id),
            'filters' => compact('q', 'status'),
            'projects' => $this->access->projects($user, $org->id)->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($q !== '', fn ($query) => $query->where(function ($query) use ($q): void {
                    $query->whereLike('name', '%'.$q.'%')->orWhereLike('number', '%'.$q.'%')->orWhereLike('ship_name', '%'.$q.'%');
                }))->orderBy('number')->orderBy('id')->paginate(25, [
                    'id', 'number', 'name', 'ship_name', 'ship_type', 'start_date', 'target_date', 'status',
                ])->withQueryString(),
        ]);
    }

    public function show(Request $request, string $organization, string $project): Response
    {
        [$org, $record] = $this->resolve($request, $organization, $project);
        $user = $request->user();
        $can = [];
        foreach (Access::ACTIONS as $action => $label) {
            $can[$action] = $this->access->allows($user, $record, $action);
        }

        return Inertia::render('Project', [
            'organization' => $org,
            'project' => $this->access->serializeProject($user, $record),
            'can' => $can,
            'members' => $can['projects.members']
                ? $this->access->members($org->id)->where('members.active', true)->where('users.active', true)
                    ->orderBy('users.name')->get(['users.id', 'users.name', 'members.role'])
                : [],
            'assignments' => $can['projects.members'] ? DB::table('project_members')->where('project_id', $project)
                ->get(['user_id', 'is_pic']) : [],
            'documents' => $can['documents.view'] ? DB::table('project_documents')
                ->where('project_id', $project)->orderByDesc('created_at')->get(['id', 'title', 'filename', 'size', 'created_at']) : [],
            'audit' => $can['audit.view'] ? DB::table('audit_events')
                ->leftJoin('users', 'users.id', '=', 'audit_events.actor_id')
                ->where('audit_events.project_id', $project)->orderByDesc('audit_events.id')->limit(50)
                ->get(['audit_events.id', 'action', 'target_id', 'reason', 'audit_events.created_at', 'users.name as actor']) : [],
        ]);
    }

    public function create(Request $request, string $organization): RedirectResponse
    {
        $org = $this->access->organization($request->user(), $organization);
        abort_unless($this->access->admin($request->user(), $org->id), 403);
        $data = $this->validateProject($request, $org->id);
        $id = (string) Str::ulid();

        DB::transaction(function () use ($request, $org, $data, $id): void {
            DB::table('organizations')->where('id', $org->id)->lockForUpdate()->first();
            if (DB::table('projects')->where('organization_id', $org->id)->where('number', $data['number'])->exists()) {
                throw ValidationException::withMessages(['number' => 'Nomor proyek sudah dipakai di organisasi ini.']);
            }
            DB::table('projects')->insert($data + ['id' => $id, 'organization_id' => $org->id, 'created_at' => now(), 'updated_at' => now()]);
            Audit::record($request->user(), 'project.created', $org->id, $id, $id, $data);
        });

        return redirect()->route('projects.show', [$organization, $id])->with('notice', 'Proyek dibuat.');
    }

    public function update(Request $request, string $organization, string $project): RedirectResponse
    {
        [$org, $record] = $this->resolve($request, $organization, $project);
        $this->access->require($request->user(), $record, 'projects.manage');
        $data = $this->validateProject($request, $org->id, $project);
        if (! $this->access->allows($request->user(), $record, 'projects.contract')) {
            unset($data['contract_amount'], $data['currency']);
        }

        DB::transaction(function () use ($request, $org, $project, $data): void {
            DB::table('organizations')->where('id', $org->id)->lockForUpdate()->first();
            $before = DB::table('projects')->where('id', $project)->lockForUpdate()->first();
            if (DB::table('projects')->where('organization_id', $org->id)->where('number', $data['number'])->where('id', '!=', $project)->exists()) {
                throw ValidationException::withMessages(['number' => 'Nomor proyek sudah dipakai di organisasi ini.']);
            }
            DB::table('projects')->where('id', $project)->update($data + ['updated_at' => now()]);
            Audit::record($request->user(), 'project.updated', $org->id, $project, $project, [
                'before' => Arr::only((array) $before, array_keys($data)), 'after' => $data,
            ]);
        });

        return back()->with('notice', 'Proyek diperbarui.');
    }

    public function members(Request $request, string $organization, string $project): RedirectResponse
    {
        [$org, $record] = $this->resolve($request, $organization, $project);
        $this->access->require($request->user(), $record, 'projects.members');
        $data = $request->validate([
            'members' => 'present|array|max:300', 'members.*.id' => 'required|ulid|distinct',
            'members.*.is_pic' => 'required|boolean',
            'manager_id' => 'nullable|ulid', 'baseline_approver_id' => 'nullable|ulid',
        ]);

        DB::transaction(function () use ($request, $data, $org, $project): void {
            $current = DB::table('projects')->where('id', $project)->lockForUpdate()->first();
            abort_if($current->status !== 'active', 409, 'Aktifkan proyek untuk mengubah penugasan.');
            $ids = array_column($data['members'], 'id');
            $count = $this->access->members($org->id)->whereIn('users.id', $ids)
                ->where('users.active', true)->where('members.active', true)->count();
            if ($count !== count($ids)) {
                throw ValidationException::withMessages(['members' => 'Pilih anggota aktif dari organisasi proyek.']);
            }

            $leaders = ['manager_id' => $data['manager_id'] ?? null, 'baseline_approver_id' => $data['baseline_approver_id'] ?? null];
            foreach ($leaders as $field => $id) {
                if ($id !== null && ! in_array($id, $ids, true)) {
                    throw ValidationException::withMessages([$field => 'Penanggung jawab harus tercantum sebagai anggota proyek.']);
                }
            }

            $before = DB::table('project_members')->where('project_id', $project)->get(['user_id', 'is_pic']);
            DB::table('project_members')->where('project_id', $project)->whereNotIn('user_id', $ids)->delete();
            DB::table('wbs_readers')->where('project_id', $project)->whereNotIn('user_id', $ids)->delete();
            foreach ($data['members'] as $member) {
                DB::table('project_members')->updateOrInsert(['project_id' => $project, 'user_id' => $member['id']], ['is_pic' => $member['is_pic']]);
            }
            DB::table('projects')->where('id', $project)->update($leaders + ['updated_at' => now()]);
            Audit::record($request->user(), 'project.assignments_changed', $org->id, $project, $project, [
                'before' => ['members' => $before, 'manager_id' => $current->manager_id, 'baseline_approver_id' => $current->baseline_approver_id],
                'after' => ['members' => $data['members']] + $leaders,
            ]);
        });

        if (! $this->access->projects($request->user(), $org->id)->where('projects.id', $project)->exists()) {
            return redirect()->route('projects.index', $organization)->with('notice', 'Penugasan proyek diperbarui.');
        }

        return back()->with('notice', 'Penugasan proyek diperbarui.');
    }

    public function upload(Request $request, string $organization, string $project): RedirectResponse
    {
        [$org, $record] = $this->resolve($request, $organization, $project);
        $this->access->require($request->user(), $record, 'documents.manage');
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'file' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx'])->max('10mb')],
        ]);
        $id = (string) Str::ulid();
        $file = $data['file'];
        $directory = "organizations/{$org->id}/projects/{$project}/documents";
        $path = "{$directory}/{$id}";
        $disk = Storage::disk('local');

        try {
            if ($disk->putFileAs($directory, $file, $id) === false) {
                throw ValidationException::withMessages(['file' => 'Penyimpanan dokumen gagal.']);
            }
            DB::transaction(function () use ($request, $org, $project, $id, $path, $file, $data): void {
                $current = DB::table('projects')->where('id', $project)->lockForUpdate()->first();
                abort_if($current->status !== 'active', 409, 'Aktifkan proyek untuk menambah dokumen.');
                $details = [
                    'title' => $data['title'], 'filename' => Str::limit($file->getClientOriginalName(), 240, ''),
                    'size' => $file->getSize(),
                ];
                DB::table('project_documents')->insert($details + [
                    'id' => $id, 'project_id' => $project, 'user_id' => $request->user()->id,
                    'path' => $path, 'created_at' => now(),
                ]);
                Audit::record($request->user(), 'project.document_uploaded', $org->id, $project, $id, $details);
            });
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }

        return back()->with('notice', 'Dokumen disimpan.');
    }

    public function download(Request $request, string $organization, string $project, string $document): StreamedResponse
    {
        [, $record] = $this->resolve($request, $organization, $project);
        $this->access->require($request->user(), $record, 'documents.view');
        $file = DB::table('project_documents')->where('project_id', $project)->where('id', $document)->first();
        $disk = Storage::disk('local');
        abort_unless($file && $disk->exists($file->path), 404);

        return $disk->download($file->path, $file->filename, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function resolve(Request $request, string $slug, string $project): array
    {
        $org = $this->access->organization($request->user(), $slug);

        return [$org, $this->access->project($request->user(), $org->id, $project)];
    }

    private function validateProject(Request $request, string $organization, ?string $project = null): array
    {
        $request->merge(['number' => Str::upper(trim((string) $request->input('number')))]);
        $number = Rule::unique('projects')->where('organization_id', $organization);
        if ($project !== null) {
            $number->ignore($project);
        }

        return $request->validate([
            'number' => ['required', 'string', 'max:80', $number],
            'name' => 'required|string|max:255',
            'ship_name' => 'required|string|max:255',
            'ship_type' => 'required|string|max:255',
            'tonnage_gt' => 'nullable|numeric|decimal:0,3|min:0|max:99999999999.999',
            'owner' => 'required|string|max:255',
            'shipyard' => 'required|string|max:255',
            'start_date' => 'required|date_format:Y-m-d',
            'target_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'contract_amount' => 'nullable|required_with:currency|numeric|decimal:0,2|min:0|max:9999999999999999.99',
            'currency' => ['nullable', 'required_with:contract_amount', 'regex:/^[A-Z]{3}$/D'],
            'status' => 'required|in:active,closed,archived',
        ]);
    }
}
