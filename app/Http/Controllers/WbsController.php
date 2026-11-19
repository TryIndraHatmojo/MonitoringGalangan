<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\Baselines;
use App\Services\ProjectContext;
use App\Services\WbsSnapshot;
use App\Services\WbsTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WbsController extends Controller
{
    public function __construct(
        private readonly Access $access,
        private readonly Baselines $baselines,
        private readonly ProjectContext $context,
    ) {}

    public function show(Request $request, string $organization, string $project): Response
    {
        [$org, $record] = $this->resolve($request, $organization, $project);
        $filters = $request->validate(['period' => ['nullable', 'regex:'.WbsSnapshot::MONTH], 'revision' => 'nullable|ulid']);
        $period = $filters['period'] ?? min(max($this->baselines->month(), substr($record->start_date, 0, 7)), substr($record->target_date, 0, 7));
        $published = $this->baselines->published($project, $period);
        $selected = isset($filters['revision'])
            ? $this->baselines->revisions($project)->where('status', 'published')->where('id', $filters['revision'])->first()
            : $published;
        abort_if(isset($filters['revision']) && ! $selected, 404);

        $user = $request->user();
        $active = $record->status === 'active';
        $manage = $active && $this->access->allows($user, $record, 'wbs.manage');
        $access = $active && $this->access->allows($user, $record, 'wbs.access');
        $submit = $active && $this->access->allows($user, $record, 'baselines.submit');
        $approve = $active && $this->access->allows($user, $record, 'baselines.approve');
        $draft = ($manage || $submit || $approve) ? $this->baselines->open($project) : null;
        $publishedSnapshot = $this->baselines->snapshot($selected);
        $working = $this->baselines->snapshot($draft ?: $published);
        $previous = $this->baselines->published($project, $draft?->effective_month ?? $period);
        $previousSnapshot = $this->baselines->snapshot($previous);
        $this->context->remember($request, $record->id);

        return Inertia::render('Wbs', [
            'organization' => $org,
            'project' => Arr::only((array) $record, ['id', 'number', 'name', 'start_date', 'target_date', 'wbs_restricted']),
            'period' => $period,
            'periods' => DB::table('project_periods')->where('project_id', $project)->orderBy('month')
                ->get(['month', 'cutoff_date', 'official_baseline_id']),
            'published' => $this->metadata($selected),
            'history' => $this->baselines->revisions($project)->where('status', 'published')->orderByDesc('revision')
                ->get(['id', 'revision', 'effective_month', 'approved_at', 'reason']),
            'publishedPlan' => $this->baselines->planned($publishedSnapshot, $period),
            'draft' => $this->metadata($draft),
            'working' => ($draft || $manage) ? $working : null,
            'workingPlan' => $draft ? $this->baselines->planned($working, $period) : null,
            'difference' => $draft ? $this->baselines->diff($previousSnapshot, $working) : null,
            'comparison' => $draft ? [
                'baseline' => $this->metadata($previous),
                'planned_pct' => $this->baselines->planned($previousSnapshot, $period, false)['planned_pct'],
            ] : null,
            'roles' => $this->access->roles(),
            'methods' => $manage ? WbsTemplates::methods() : [],
            'members' => ($manage || $access) ? $this->baselines->roster($record)->orderBy('users.name')->get(['users.id', 'users.name', 'project_members.is_pic']) : [],
            'readers' => $access ? DB::table('wbs_readers')->where('project_id', $project)->pluck('user_id') : [],
            'can' => compact('manage', 'access', 'submit', 'approve'),
        ]);
    }

    public function save(Request $request, string $organization, string $project): RedirectResponse
    {
        [, $record] = $this->resolve($request, $organization, $project, 'wbs.manage');
        $data = $request->validate($this->revisionRules() + ['snapshot' => 'required|array:mode,nodes,targets,legacy,lineage']);
        $this->baselines->save($request->user(), $record, $data);

        return back()->with('notice', 'Draf baseline disimpan.');
    }

    public function copyTemplate(Request $request, string $organization, string $project): RedirectResponse
    {
        [, $record] = $this->resolve($request, $organization, $project, 'wbs.manage');
        $this->access->require($request->user(), $record, 'wbs.access');
        $data = $request->validate($this->revisionRules() + $this->readerRules() + [
            'template' => 'required|in:ferry,tug',
            'reviewer_role' => ['nullable', Rule::in(array_keys($this->access->roles()))],
        ]);
        $data['restricted'] = (bool) $data['restricted'];
        $data['snapshot'] = WbsTemplates::project($data['template'], $record, $data['reviewer_role'] ?? null);
        $this->baselines->save($request->user(), $record, $data, copyTemplate: true);

        return $this->afterReaders($request, $organization, $project, $record->galangan_id, 'Template disalin ke draf.');
    }

    public function readers(Request $request, string $organization, string $project): RedirectResponse
    {
        [, $record] = $this->resolve($request, $organization, $project, 'wbs.access');
        $data = $request->validate($this->readerRules());
        $this->baselines->readers($request->user(), $record, (bool) $data['restricted'], $data['readers']);

        return $this->afterReaders($request, $organization, $project, $record->galangan_id, 'Akses pembaca Form Builder diperbarui.');
    }

    public function cutoff(Request $request, string $organization, string $project): RedirectResponse
    {
        [, $record] = $this->resolve($request, $organization, $project, 'wbs.manage');
        $data = $request->validate(['period' => ['required', 'regex:'.WbsSnapshot::MONTH], 'cutoff_date' => 'nullable|date_format:Y-m-d']);
        $this->baselines->setCutoff($request->user(), $record, $data['period'], $data['cutoff_date'] ?? null);

        return back()->with('notice', 'Cutoff diperbarui.');
    }

    public function transition(Request $request, string $organization, string $project, string $revision): RedirectResponse
    {
        [, $record] = $this->resolve($request, $organization, $project);
        $data = $request->validate([
            'operation' => 'required|in:submit,return,publish',
            'version' => 'required|integer|min:1',
            'reason' => 'required_if:operation,submit,return|nullable|string|max:2000',
        ]);
        $this->baselines->transition($request->user(), $record, $revision, $data['operation'], (int) $data['version'], $data['reason'] ?? null);

        return back()->with('notice', match ($data['operation']) {
            'submit' => 'Baseline diajukan.', 'return' => 'Baseline dikembalikan ke draf.', 'publish' => 'Baseline diterbitkan.',
        });
    }

    private function resolve(Request $request, string $slug, string $project, string $action = 'wbs.view'): array
    {
        $user = $request->user();
        $org = $this->access->organization($user, $slug);
        $record = $this->access->project($user, $org->id, $project);
        $this->access->require($user, $record, 'wbs.view');
        if ($action !== 'wbs.view') {
            $this->access->require($user, $record, $action);
        }

        return [$org, $record];
    }

    private function afterReaders(Request $request, string $slug, string $id, string $org, string $notice): RedirectResponse
    {
        $current = $this->access->project($request->user(), $org, $id);

        return ($this->access->allows($request->user(), $current, 'wbs.view')
            ? back()
            : redirect()->route('projects.show', [$slug, $id]))->with('notice', $notice);
    }

    private function metadata(?object $revision): ?array
    {
        return $revision ? Arr::except((array) $revision, ['snapshot']) : null;
    }

    private function revisionRules(): array
    {
        return ['draft_id' => 'nullable|ulid', 'version' => 'nullable|integer|min:1', 'effective_month' => ['required', 'regex:'.WbsSnapshot::MONTH]];
    }

    private function readerRules(): array
    {
        return ['restricted' => 'required|boolean', 'readers' => 'present|array|max:300', 'readers.*' => 'required|ulid|distinct'];
    }
}
