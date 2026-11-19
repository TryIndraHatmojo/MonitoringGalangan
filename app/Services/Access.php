<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Access
{
    public const ROLES = [
        'platform_admin' => 'Administrator Pusat',
        'administrator' => 'Administrator organisasi',
        'manager' => 'Project Manager',
        'planner' => 'Planner / Engineering',
        'pic' => 'PIC Lapangan',
        'reviewer' => 'Supervisor / Reviewer',
        'procurement' => 'Procurement',
        'finance' => 'Finance',
        'surveyor' => 'Owner / Surveyor',
        'viewer' => 'Management / Viewer',
    ];

    public const ACTIONS = [
        'projects.view' => 'Lihat proyek',
        'projects.manage' => 'Kelola master proyek',
        'projects.members' => 'Kelola anggota dan penugasan proyek',
        'projects.contract' => 'Lihat nilai kontrak',
        'documents.view' => 'Unduh dokumen',
        'documents.manage' => 'Unggah dokumen',
        'wbs.view' => 'Lihat Form Builder dan baseline',
        'wbs.manage' => 'Kelola draf Form Builder dan baseline',
        'wbs.access' => 'Kelola pembaca Form Builder',
        'baselines.submit' => 'Ajukan baseline',
        'baselines.approve' => 'Setujui atau kembalikan baseline',
        'official.reopen' => 'Buka kembali data resmi',
        'audit.view' => 'Lihat audit proyek',
    ];

    public const PLATFORM_ACTIONS = [
        'roles.manage' => 'Kelola master role',
    ];

    private const DEFAULTS = [
        'manager' => ['projects.view', 'projects.manage', 'projects.members', 'projects.contract', 'documents.view', 'documents.manage', 'wbs.view', 'baselines.approve', 'audit.view'],
        'planner' => ['projects.view', 'documents.view', 'wbs.view', 'wbs.manage', 'wbs.access', 'baselines.submit'],
        'pic' => ['projects.view', 'wbs.view'],
        'reviewer' => ['projects.view', 'wbs.view'],
        'procurement' => ['projects.view'],
        'finance' => ['projects.view', 'projects.contract'],
        'surveyor' => ['projects.view'],
        'viewer' => ['projects.view'],
    ];

    public function roles(): array
    {
        return array_diff_key($this->roleCatalog(), ['platform_admin' => true]);
    }

    public function roleCatalog(): array
    {
        $roles = DB::table('roletbls')->pluck('nama', 'kode')->all();

        return array_replace(array_intersect_key(self::ROLES, $roles), $roles);
    }

    public function lockRoles(array $codes, string $field): void
    {
        $found = DB::table('roletbls')->whereIn('kode', $codes)->orderBy('kode')->sharedLock()->pluck('kode');
        if (array_diff($codes, $found->all()) !== []) {
            throw ValidationException::withMessages([$field => 'Role sudah tidak tersedia. Pilih role lain.']);
        }
    }

    public function organizations(): Collection
    {
        return DB::table('galangantbls')->orderBy('name')->orderBy('id')->get(['id', 'slug', 'name']);
    }

    public function assignedOrganization(User $user): ?object
    {
        if (! $user->active || ! $user->keanggotaan_aktif || $user->galangan_id === null) {
            return null;
        }

        return DB::table('galangantbls')->where('id', $user->galangan_id)->first(['id', 'slug', 'name']);
    }

    public function organization(User $user, string $key, string $field = 'slug'): object
    {
        $organization = DB::table('galangantbls')->where($field, $key)->first();
        abort_unless($organization && $this->canAccessOrganization($user, $organization->id), 404);

        return $organization;
    }

    public function canAccessOrganization(User $user, string $organization): bool
    {
        return $this->platformAdmin($user) || $this->role($user, $organization) !== null;
    }

    public function platformAdmin(User $user): bool
    {
        return $user->active && $user->platform_admin;
    }

    public function requirePlatform(User $user, string $action): void
    {
        abort_unless($this->platformAdmin($user) && isset(self::PLATFORM_ACTIONS[$action]), 403);
    }

    public function role(User $user, string $organization): ?string
    {
        return $user->active && $user->keanggotaan_aktif && $user->galangan_id === $organization
            ? $user->kode_role
            : null;
    }

    public function admin(User $user, string $organization): bool
    {
        return $this->platformAdmin($user) || $this->role($user, $organization) === 'administrator';
    }

    public function actions(string $organization, string $role): array
    {
        if ($role === 'administrator') {
            return array_keys(self::ACTIONS);
        }

        $override = DB::table('galangan_premissiontbls')->where('kode_role', $role)
            ->where('galangan_id', $organization)->value('actions');

        return $override === null ? (self::DEFAULTS[$role] ?? []) : json_decode($override, true, flags: JSON_THROW_ON_ERROR);
    }

    public function permissions(User $user, string $organization): array
    {
        if ($this->admin($user, $organization)) {
            return array_keys(self::ACTIONS);
        }

        $role = $this->role($user, $organization);

        return $role ? $this->actions($organization, $role) : [];
    }

    public function projects(User $user, string $organization): Builder
    {
        $projects = DB::table('projects')->where('galangan_id', $organization);
        if ($this->admin($user, $organization)) {
            return $projects;
        }

        if (! in_array('projects.view', $this->permissions($user, $organization), true)) {
            return $projects->whereRaw('1 = 0');
        }

        return $projects->whereIn('projects.id', DB::table('project_members')
            ->select('project_id')->where('user_id', $user->id));
    }

    public function project(User $user, string $organization, string $project): object
    {
        $result = $this->projects($user, $organization)->where('projects.id', $project)->first();
        abort_unless($result, 404);

        return $result;
    }

    public function selectableProjects(User $user): Builder
    {
        if ($this->platformAdmin($user)) {
            return DB::table('projects');
        }

        if ($user->active && $user->keanggotaan_aktif && $user->galangan_id !== null) {
            return $this->projects($user, $user->galangan_id);
        }

        return DB::table('projects')->whereRaw('1 = 0');
    }

    public function allows(User $user, object $project, string $action): bool
    {
        if ($this->admin($user, $project->galangan_id)) {
            return true;
        }

        if (! in_array($action, $this->permissions($user, $project->galangan_id), true)) {
            return false;
        }

        if (! DB::table('project_members')->where('project_id', $project->id)->where('user_id', $user->id)->exists()) {
            return false;
        }

        if ($action === 'baselines.approve' && $project->baseline_approver_id !== $user->id) {
            return false;
        }

        if ((str_starts_with($action, 'wbs.') || str_starts_with($action, 'baselines.')) && $project->wbs_restricted) {
            return DB::table('wbs_readers')->where('project_id', $project->id)->where('user_id', $user->id)->exists();
        }

        return true;
    }

    public function require(User $user, object $project, string $action): void
    {
        abort_unless($this->allows($user, $project, $action), 403);
    }

    public function members(string $organization): Builder
    {
        return DB::table('users')->where('users.galangan_id', $organization);
    }

    public function serializeProject(User $user, object $project): array
    {
        $fields = (array) $project;
        $fields['organization_id'] = $fields['galangan_id'];
        unset($fields['galangan_id']);
        if (! $this->allows($user, $project, 'projects.contract')) {
            unset($fields['contract_amount'], $fields['currency']);
        }

        return $fields;
    }
}
