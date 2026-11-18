<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class Access
{
    public const ROLES = [
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
        'wbs.view' => 'Lihat WBS dan baseline',
        'wbs.manage' => 'Kelola draf WBS dan baseline',
        'wbs.access' => 'Kelola pembaca WBS',
        'baselines.submit' => 'Ajukan baseline',
        'baselines.approve' => 'Setujui atau kembalikan baseline',
        'official.reopen' => 'Buka kembali data resmi',
        'audit.view' => 'Lihat audit proyek',
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

    public function organizations(User $user): Collection
    {
        return DB::table('organizations')->when(! $user->platform_admin, fn ($query) => $query
            ->whereIn('id', DB::table('organization_members')->select('organization_id')
                ->where('user_id', $user->id)->where('active', true)))
            ->orderBy('name')->orderBy('id')->get(['id', 'slug', 'name']);
    }

    public function organization(User $user, string $key, string $field = 'slug'): object
    {
        $organization = DB::table('organizations')->where($field, $key)->first();
        abort_unless($organization && ($user->platform_admin || $this->role($user, $organization->id)), 404);

        return $organization;
    }

    public function role(User $user, string $organization): ?string
    {
        if (! $user->active) {
            return null;
        }

        return DB::table('organization_members')->where('organization_id', $organization)
            ->where('user_id', $user->id)->where('active', true)->value('role');
    }

    public function admin(User $user, string $organization): bool
    {
        return $user->active && ($user->platform_admin || $this->role($user, $organization) === 'administrator');
    }

    public function actions(string $organization, string $role): array
    {
        if ($role === 'administrator') {
            return array_keys(self::ACTIONS);
        }

        $override = DB::table('organization_permissions')->where(compact('role'))
            ->where('organization_id', $organization)->value('actions');

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
        $projects = DB::table('projects')->where('organization_id', $organization);
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

    public function allows(User $user, object $project, string $action): bool
    {
        if ($this->admin($user, $project->organization_id)) {
            return true;
        }

        if (! in_array($action, $this->permissions($user, $project->organization_id), true)) {
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
        return DB::table('organization_members as members')
            ->join('users', 'users.id', '=', 'members.user_id')
            ->where('members.organization_id', $organization);
    }

    public function serializeProject(User $user, object $project): array
    {
        $fields = (array) $project;
        if (! $this->allows($user, $project, 'projects.contract')) {
            unset($fields['contract_amount'], $fields['currency']);
        }

        return $fields;
    }
}
