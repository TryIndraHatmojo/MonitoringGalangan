<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private readonly Access $access) {}

    public function index(Request $request): Response
    {
        $this->access->requirePlatform($request->user(), 'roles.manage');

        return Inertia::render('Master', [
            'section' => 'roles',
            'organizations' => $this->access->organizations(),
            'roles' => $this->access->roleCatalog(),
            'builtInRoles' => array_keys(Access::ROLES),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->access->requirePlatform($request->user(), 'roles.manage');
        $data = $request->validate([
            'kode' => [
                'required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/D',
                Rule::notIn(array_keys(Access::ROLES)), Rule::unique('roletbls', 'kode'),
            ],
            'nama' => 'required|string|max:255',
        ], [
            'kode.regex' => 'Awali kode dengan huruf kecil. Gunakan huruf kecil, angka, dan garis bawah.',
            'kode.not_in' => 'Kode role tersebut sudah digunakan oleh sistem.',
            'kode.unique' => 'Kode role sudah digunakan.',
        ]);

        try {
            DB::transaction(function () use ($request, $data): void {
                DB::table('roletbls')->insert($data);
                Audit::record($request->user(), 'role.created', target: $data['kode'], changes: $data);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['kode' => 'Kode role sudah digunakan.']);
        }

        return redirect()->route('master.roles')->with('notice', 'Role ditambahkan.');
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        $this->access->requirePlatform($request->user(), 'roles.manage');
        $data = $request->validate(['nama' => 'required|string|max:255']);

        DB::transaction(function () use ($request, $role, $data): void {
            $record = $this->lockedRole($role);
            if ($record->nama !== $data['nama']) {
                DB::table('roletbls')->where('kode', $record->kode)->update($data);
                Audit::record($request->user(), 'role.updated', target: $record->kode, changes: [
                    'before' => ['nama' => $record->nama], 'after' => $data,
                ]);
            }
        }, 3);

        return redirect()->route('master.roles')->with('notice', 'Nama role disimpan.');
    }

    public function destroy(Request $request, string $role): RedirectResponse
    {
        $this->access->requirePlatform($request->user(), 'roles.manage');

        try {
            DB::transaction(function () use ($request, $role): void {
                $record = $this->lockedRole($role);
                $code = $record->kode;
                if (isset(Access::ROLES[$code])) {
                    throw ValidationException::withMessages(['role' => 'Role bawaan tidak dapat dihapus.']);
                }
                if (DB::table('baseline_revisions')->whereJsonContains('snapshot->nodes', [['reviewer_role' => $code]])->exists()) {
                    throw ValidationException::withMessages(['role' => 'Role masih dipakai sebagai reviewer di baseline Form Builder.']);
                }

                $permissions = DB::table('galangan_premissiontbls')->where('kode_role', $code);
                $previous = $permissions->pluck('actions', 'galangan_id')
                    ->map(fn (string $actions) => json_decode($actions, true, flags: JSON_THROW_ON_ERROR))->all();
                $permissions->delete();
                DB::table('roletbls')->where('kode', $code)->delete();
                Audit::record($request->user(), 'role.deleted', target: $code, changes: [
                    'before' => (array) $record, 'permissions' => $previous,
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1451) {
                throw $exception;
            }
            throw ValidationException::withMessages(['role' => 'Role masih dipakai oleh akun atau undangan.']);
        }

        return redirect()->route('master.roles')->with('notice', 'Role dihapus.');
    }

    private function lockedRole(string $code): object
    {
        $record = DB::table('roletbls')->where('kode', $code)->lockForUpdate()->first();
        abort_unless($record, 404);

        return $record;
    }
}
