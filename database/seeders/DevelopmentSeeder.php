<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Audit;
use App\Services\Baselines;
use App\Services\WbsTemplates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DevelopmentSeeder extends Seeder
{
    private const REASON = 'Fixture development untuk pemeriksaan struktur dan target.';

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Fixture tersedia di lingkungan pengembangan dan test.');
        }

        $password = config('monitoring.demo_password');
        if (! is_string($password) || strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new RuntimeException('Isi MONITORING_DEMO_PASSWORD dengan kata sandi 12–72 byte.');
        }

        DB::transaction(function () use ($password): void {
            $this->call(RoleSeeder::class);

            $admin = $this->account('admin', 'Administrator Pusat Demo', $password, 'platform_admin');
            if (! $admin->active || ! $admin->platform_admin) {
                throw new RuntimeException('Akun administrator pusat demo harus aktif dan memiliki hak administrator pusat.');
            }

            $this->demo($admin, 'ferry', 'Galangan Ferry Contoh', 'Ferry 1500 GT (demo)', $password, [
                'manager' => ['manager.ferry', 'Project Manager Ferry Demo'],
                'planner' => ['planner.ferry', 'Planner Ferry Demo'],
                'pic' => ['pic.ferry', 'PIC Ferry Demo'],
            ]);
            $this->demo($admin, 'tug', 'Galangan Tug Contoh', 'ASD Harbour Tug (demo)', $password, [
                'viewer' => ['viewer.tug', 'Viewer Tug Demo'],
            ]);
        }, 3);
    }

    private function account(string $alias, string $name, string $password, string $role, ?string $organization = null, ?User $admin = null): User
    {
        $email = $alias.'@demo.monitoring.test';
        $user = User::query()->where('email', $email)->lockForUpdate()->first();
        if ($user) {
            $seeded = DB::table('audit_events')->where('action', 'demo.account_created')
                ->where('target_id', $user->id)->where('changes->email', $email)->exists();
            if (! $seeded) {
                throw new RuntimeException("Email demo {$email} sudah dipakai akun lain.");
            }

            return $user;
        }

        $user = new User(['email' => $email, 'name' => $name, 'password' => Hash::make($password)]);
        $user->forceFill(['kode_role' => $role, 'galangan_id' => $organization])->save();
        Audit::record($admin, 'demo.account_created', $organization, target: $user->id, changes: [
            'email' => $email, 'name' => $name, 'platform_admin' => $user->platform_admin,
        ]);
        if ($organization !== null) {
            Audit::record($admin, 'organization.membership_changed', $organization, target: $user->id,
                changes: ['before' => null, 'after' => ['role' => $role, 'active' => true]], reason: self::REASON);
        }

        return $user;
    }

    /** @param array<string, array{string, string}> $accounts */
    private function demo(User $admin, string $template, string $organization, string $name, string $password, array $accounts): void
    {
        $slug = $template.'-demo';
        $org = DB::table('galangantbls')->where('slug', $slug)->lockForUpdate()->first();
        if ($org) {
            $project = DB::table('projects')->where('galangan_id', $org->id)
                ->where('number', 'DEMO-01')->lockForUpdate()->first();
            $audit = DB::table('audit_events')->where('galangan_id', $org->id);
            $initialized = $project && (clone $audit)->where('project_id', $project->id)
                ->where('action', 'project.assignments_changed')->where('reason', self::REASON)->exists();
            // The previous seeder audited the baseline, but inserted the organization directly.
            $legacy = $project && ! $initialized
                && ! (clone $audit)->where('action', 'organization.created')->exists()
                && (clone $audit)->where('project_id', $project->id)->where('action', 'baseline.submit')
                    ->where('reason', self::REASON)->where('changes->revision', 1)->exists();
            if (! $initialized && ! $legacy) {
                throw new RuntimeException("Slug demo {$slug} sudah dipakai organisasi yang tidak dikenali sebagai fixture.");
            }

            $members = $this->accounts($admin, $org->id, $password, $accounts);
            if ($initialized) {
                $members = array_filter($members, fn (User $user): bool => $user->wasRecentlyCreated);
            }

            if ($members !== []) {
                $this->assign($admin, $project, $members, initialize: ! $initialized);
            }

            return;
        }

        $organizationId = (string) Str::ulid();
        $projectId = (string) Str::ulid();
        $organizationData = ['slug' => $slug, 'name' => $organization];
        DB::table('galangantbls')->insert($organizationData + ['id' => $organizationId, 'created_at' => now(), 'updated_at' => now()]);
        Audit::record($admin, 'organization.created', $organizationId, target: $organizationId, changes: $organizationData);

        $projectData = [
            'number' => 'DEMO-01',
            'name' => $name, 'ship_name' => $name, 'ship_type' => $template === 'ferry' ? 'Ferry' : 'Harbour Tug',
            'tonnage_gt' => $template === 'ferry' ? '1500' : null, 'owner' => 'Pemilik contoh', 'shipyard' => $organization,
            'start_date' => '2026-09-01', 'target_date' => '2026-11-30',
        ];
        DB::table('projects')->insert($projectData + [
            'id' => $projectId, 'galangan_id' => $organizationId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Audit::record($admin, 'project.created', $organizationId, $projectId, $projectId, $projectData);

        $project = DB::table('projects')->where('id', $projectId)->first();
        $this->assign($admin, $project, $this->accounts($admin, $organizationId, $password, $accounts), initialize: true);
        $this->publish($admin, $project, $template);
    }

    /** @param array<string, array{string, string}> $accounts */
    private function accounts(User $admin, string $organization, string $password, array $accounts): array
    {
        $members = [];
        foreach ($accounts as $role => [$alias, $name]) {
            $members[$role] = $this->account($alias, $name, $password, $role, $organization, $admin);
        }

        return $members;
    }

    /** @param array<string, User> $members */
    private function assign(User $admin, object $project, array $members, bool $initialize): void
    {
        if ($project->status !== 'active') {
            throw new RuntimeException('Aktifkan proyek demo untuk menambah penugasan.');
        }

        $organizationId = $project->galangan_id;
        $projectId = $project->id;
        $assignments = DB::table('project_members')->where('project_id', $projectId);
        $leaders = ['manager_id' => $project->manager_id, 'baseline_approver_id' => $project->baseline_approver_id];
        $before = ['members' => $assignments->get(['user_id', 'is_pic'])] + $leaders;
        foreach ($members as $role => $user) {
            if (! $user->active || ! $user->keanggotaan_aktif || $user->galangan_id !== $organizationId || $user->kode_role !== $role) {
                continue;
            }

            if (! (clone $assignments)->where('user_id', $user->id)->exists()) {
                DB::table('project_members')->insert(['project_id' => $projectId, 'user_id' => $user->id, 'is_pic' => $role === 'pic']);
            }
            if ($initialize && $role === 'manager') {
                $leaders['manager_id'] ??= $user->id;
                $leaders['baseline_approver_id'] ??= $user->id;
                if ($leaders['manager_id'] !== $project->manager_id || $leaders['baseline_approver_id'] !== $project->baseline_approver_id) {
                    DB::table('projects')->where('id', $projectId)->update($leaders + ['updated_at' => now()]);
                }
            }
        }

        Audit::record($admin, 'project.assignments_changed', $organizationId, $projectId, $projectId, [
            'before' => $before, 'after' => ['members' => $assignments->get(['user_id', 'is_pic'])] + $leaders,
        ], self::REASON);
    }

    private function publish(User $admin, object $project, string $template): void
    {
        $snapshot = WbsTemplates::project($template, $project, 'reviewer');
        $month = '2026-10';
        if ($template === 'ferry') {
            $snapshot['mode'] = 'legacy';
            $weights = ['20.583656', '9.290695', '0.363500', '0.455176'];
            $index = 0;
            foreach ($snapshot['nodes'] as $node) {
                if ($node['type'] === 'category') {
                    $snapshot['legacy'][] = [
                        'category_id' => $node['id'], 'period' => $month,
                        'value' => $weights[$index], 'source' => 'Ferry · Kurva-S (All)!M'.(10 + $index),
                    ];
                    $index++;
                }
            }
            $snapshot['legacy'][] = ['category_id' => null, 'period' => $month, 'value' => '30.693028', 'source' => 'Ferry · Kurva-S (All)!M16'];
        } else {
            foreach ($snapshot['nodes'] as $node) {
                if ($node['type'] === 'item') {
                    $snapshot['targets'][] = ['item_id' => $node['id'], 'period' => $month, 'value' => '60'];
                    $snapshot['targets'][] = ['item_id' => $node['id'], 'period' => '2026-11', 'value' => '100'];
                }
            }
        }

        $service = app(Baselines::class);
        $id = $service->save($admin, $project, ['snapshot' => $snapshot, 'effective_month' => $month]);
        $service->transition($admin, $project, $id, 'submit', 1, self::REASON);
        $service->transition($admin, $project, $id, 'publish', 2, null);
        if ($template === 'ferry') {
            $service->setCutoff($admin, $project, $month, '2026-10-28');
        }
    }
}
