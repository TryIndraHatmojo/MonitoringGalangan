<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Baselines
{
    public function revisions(string $project): Builder
    {
        return DB::table('baseline_revisions')->where('project_id', $project);
    }

    public function published(string $project, string $month): ?object
    {
        $official = DB::table('project_periods')->where('project_id', $project)->where('month', $month)->value('official_baseline_id');
        $query = $this->revisions($project)->where('status', 'published');

        return $official ? $query->where('id', $official)->first()
            : $query->where('effective_month', '<=', $month)->orderByDesc('effective_month')->orderByDesc('revision')->first();
    }

    public function open(string $project): ?object
    {
        return $this->revisions($project)->whereIn('status', ['draft', 'submitted'])->first();
    }

    public function snapshot(?object $revision): array
    {
        return $revision ? json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR) : WbsSnapshot::EMPTY;
    }

    public function month(): string
    {
        return now('Asia/Jakarta')->format('Y-m');
    }

    public function save(User $user, object $project, array $data, bool $copyTemplate = false): string
    {
        return DB::transaction(function () use ($user, $project, $data, $copyTemplate): string {
            $project = $this->lockActiveProject($project->id);
            $draft = $this->open($project->id);
            $this->assertDraft($draft, $data);
            $this->withinProject($data['effective_month'], $project);
            $snapshot = WbsSnapshot::validate($data['snapshot'], $project);
            $this->registerItemIds($project, $snapshot);
            $previous = $this->snapshot($draft);
            if ($copyTemplate) {
                abort_if($previous['nodes'] !== [], 409, 'Gunakan salin template pada draf kosong.');
                $this->setReaders($user, $project, $data['restricted'], $data['readers']);
            }

            $changes = [
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'effective_month' => $data['effective_month'],
                'updated_at' => now(),
            ];
            if ($draft) {
                $this->revisions($project->id)->where('id', $draft->id)->update($changes + ['version' => $draft->version + 1]);
                $id = $draft->id;
            } else {
                $id = (string) Str::ulid();
                $revision = 1 + (int) $this->revisions($project->id)->max('revision');
                DB::table('baseline_revisions')->insert($changes + [
                    'id' => $id, 'project_id' => $project->id, 'revision' => $revision,
                    'created_by' => $user->id, 'created_at' => now(),
                ]);
            }

            Audit::record($user, $copyTemplate ? 'baseline.template_copied' : 'baseline.draft_saved', $project->organization_id, $project->id, $id, [
                'effective_month' => $data['effective_month'],
                'changes' => $this->diff($previous, $snapshot),
            ]);

            return $id;
        }, 3);
    }

    public function transition(User $user, object $project, string $id, string $operation, int $version, ?string $reason): void
    {
        DB::transaction(function () use ($user, $project, $id, $operation, $version, $reason): void {
            $project = $this->lockActiveProject($project->id);
            $revision = $this->revisions($project->id)->where('id', $id)->lockForUpdate()->first();
            abort_unless($revision, 404);

            $access = app(Access::class);
            $access->require($user, $project, 'wbs.view');
            $access->require($user, $project, $operation === 'submit' ? 'baselines.submit' : 'baselines.approve');

            if ($operation === 'publish' && $revision->status === 'published') {
                return;
            }
            abort_if((int) $revision->version !== $version, 409, 'Versi sudah berubah. Muat ulang sebelum melanjutkan.');
            $snapshot = $this->snapshot($revision);
            $changes = match ($operation) {
                'submit' => ['status' => 'submitted', 'submitted_by' => $user->id, 'reason' => $reason],
                'return' => ['status' => 'draft'],
                'publish' => ['status' => 'published', 'approved_by' => $user->id, 'approved_at' => now()],
            };
            abort_unless($revision->status === ($operation === 'submit' ? 'draft' : 'submitted'), 409, 'Status baseline telah berubah.');

            if ($operation !== 'return') {
                $this->withinProject($revision->effective_month, $project);
                WbsSnapshot::validate($snapshot, $project, submit: true);
                $this->validatePics($project, $snapshot);
            }

            $this->revisions($project->id)->where('id', $id)->update($changes + ['version' => $version + 1, 'updated_at' => now()]);
            Audit::record($user, 'baseline.'.$operation, $project->organization_id, $project->id, $id, [
                'before' => $revision->status, 'after' => $changes['status'], 'effective_month' => $revision->effective_month, 'revision' => $revision->revision,
            ], $reason ?? $revision->reason);
        }, 3);
    }

    public function readers(User $user, object $project, bool $restricted, array $readers): void
    {
        DB::transaction(function () use ($user, $project, $restricted, $readers): void {
            $project = $this->lockActiveProject($project->id);
            app(Access::class)->require($user, $project, 'wbs.access');
            $this->setReaders($user, $project, $restricted, $readers);
        });
    }

    public function setCutoff(User $user, object $project, string $month, ?string $cutoff): void
    {
        DB::transaction(function () use ($user, $project, $month, $cutoff): void {
            $project = $this->lockActiveProject($project->id);
            app(Access::class)->require($user, $project, 'wbs.manage');
            $this->withinProject($month, $project);
            WbsSnapshot::ensure($cutoff === null || str_starts_with($cutoff, $month.'-'), 'cutoff_date', 'Tanggal cutoff harus berada dalam bulan yang dipilih.');
            $key = ['project_id' => $project->id, 'month' => $month];
            $before = DB::table('project_periods')->where($key)->first();
            abort_if($before?->official_baseline_id, 409, 'Periode resmi harus diperbarui melalui koreksi.');
            DB::table('project_periods')->updateOrInsert($key, ['cutoff_date' => $cutoff]);
            Audit::record($user, 'baseline.cutoff_changed', $project->organization_id, $project->id, $month, [
                'before' => $before?->cutoff_date, 'after' => $cutoff,
            ]);
        });
    }

    public function roster(object $project): Builder
    {
        return app(Access::class)->members($project->organization_id)
            ->join('project_members', 'project_members.user_id', '=', 'users.id')->where('project_members.project_id', $project->id)
            ->where('users.active', true)->where('members.active', true);
    }

    private function lockActiveProject(string $id): object
    {
        $project = DB::table('projects')->where('id', $id)->lockForUpdate()->first();
        abort_unless($project, 404);
        abort_unless($project->status === 'active', 409, 'Aktifkan proyek untuk mengubah baseline.');

        return $project;
    }

    private function assertDraft(?object $draft, array $data): void
    {
        abort_if(($data['draft_id'] ?? null) !== $draft?->id || ($draft && (int) $draft->version !== (int) ($data['version'] ?? 0)),
            409, 'Versi draf sudah berubah. Muat ulang sebelum menyimpan.');
        abort_if($draft && $draft->status !== 'draft', 409, 'Baseline sedang menunggu persetujuan.');
    }

    private function withinProject(string $month, object $project): void
    {
        WbsSnapshot::ensure($month >= substr($project->start_date, 0, 7) && $month <= substr($project->target_date, 0, 7),
            'effective_month', 'Periode berada di luar jadwal proyek.');
    }

    private function registerItemIds(object $project, array $snapshot): void
    {
        $this->validatePics($project, $snapshot);
        $nodes = array_column($snapshot['nodes'], 'id');
        $old = array_unique(array_column($snapshot['lineage'], 'from_id'));
        if ($old !== []) {
            WbsSnapshot::ensure(DB::table('wbs_item_ids')->where('project_id', $project->id)->whereIn('id', $old)->count() === count($old),
                'lineage', 'Item asal harus berasal dari histori proyek ini.');
        }

        $existing = DB::table('wbs_item_ids')->whereIn('id', $nodes)->pluck('project_id', 'id');
        foreach ($existing as $organization) {
            WbsSnapshot::ensure($organization === $project->id, 'nodes', 'ID WBS berasal dari proyek lain.');
        }
        foreach (array_chunk(array_diff($nodes, $existing->keys()->all()), 400) as $ids) {
            DB::table('wbs_item_ids')->insertOrIgnore(array_map(fn (string $id): array => ['id' => $id, 'project_id' => $project->id], $ids));
        }
        WbsSnapshot::ensure(DB::table('wbs_item_ids')->where('project_id', $project->id)->whereIn('id', $nodes)->count() === count($nodes),
            'nodes', 'Ada ID WBS yang telah dipakai di proyek lain.');
    }

    private function validatePics(object $project, array $snapshot): void
    {
        $pics = array_values(array_unique(array_filter(array_column($snapshot['nodes'], 'pic_id'))));
        WbsSnapshot::ensure($this->roster($project)->where('project_members.is_pic', true)->whereIn('users.id', $pics)->count() === count($pics),
            'nodes', 'PIC harus aktif dan ditugaskan pada proyek ini.');
    }

    private function setReaders(User $user, object $project, bool $restricted, array $readers): void
    {
        $readers = $restricted ? array_values($readers) : [];
        WbsSnapshot::ensure(! $restricted || $readers !== [], 'readers', 'Pilih paling sedikit satu pembaca.');
        WbsSnapshot::ensure($this->roster($project)->whereIn('users.id', $readers)->count() === count($readers),
            'readers', 'Pilih pembaca dari anggota aktif proyek.');
        $before = DB::table('wbs_readers')->where('project_id', $project->id)->pluck('user_id')->all();
        DB::table('wbs_readers')->where('project_id', $project->id)->delete();
        if ($readers !== []) {
            DB::table('wbs_readers')->insert(array_map(fn (string $id): array => ['project_id' => $project->id, 'user_id' => $id], $readers));
        }
        DB::table('projects')->where('id', $project->id)->update(['wbs_restricted' => $restricted, 'updated_at' => now()]);
        Audit::record($user, 'wbs.readers_changed', $project->organization_id, $project->id, $project->id,
            ['before' => ['restricted' => (bool) $project->wbs_restricted, 'readers' => $before], 'after' => compact('restricted', 'readers')]);
    }

    public function diff(array $before, array $after): array
    {
        $old = array_column($before['nodes'], null, 'id');
        $new = array_column($after['nodes'], null, 'id');
        $added = array_column(array_diff_key($new, $old), 'code');
        $removed = array_column(array_diff_key($old, $new), 'code');
        $changed = [];
        foreach (array_intersect_key($new, $old) as $id => $item) {
            $fields = array_keys(array_filter($item, fn ($value, $key) => ($old[$id][$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));
            if ($fields !== []) {
                $changed[] = ['code' => $item['code'], 'fields' => $fields];
            }
        }
        $targetsChanged = $before['targets'] !== $after['targets'] || $before['legacy'] !== $after['legacy'];

        return compact('added', 'removed', 'changed', 'targetsChanged');
    }

    public function planned(array $snapshot, string $month, bool $withRows = true): array
    {
        $nodes = array_column($snapshot['nodes'], null, 'id');
        $weights = WbsSnapshot::projectWeights($nodes);
        $targets = array_column(array_filter($snapshot['targets'], fn ($target) => $target['period'] === $month), 'value', 'item_id');
        $legacy = [];
        $legacyProject = null;
        $source = null;
        foreach ($snapshot['legacy'] as $target) {
            if ($target['period'] === $month) {
                if ($target['category_id'] === null) {
                    $legacyProject = $target['value'];
                    $source = $target['source'];
                } else {
                    $legacy[$target['category_id']] = $target;
                }
            }
        }

        $rollup = [];
        $incomplete = [];
        $physical = [];
        foreach ($nodes as $id => $node) {
            if ($node['type'] !== 'item' || $snapshot['mode'] === 'legacy') {
                continue;
            }
            $physical[$id] = $this->itemTarget($node, $targets[$id] ?? null, $month);
            $contribution = $physical[$id] === null ? null : bcdiv(bcmul($weights[$id], $physical[$id], 20), '100', 18);
            $cursor = $id;
            while ($cursor !== null) {
                if ($contribution === null) {
                    $incomplete[$cursor] = true;
                } else {
                    $rollup[$cursor] = bcadd($rollup[$cursor] ?? '0', $contribution, 18);
                }
                $cursor = $nodes[$cursor]['parent_id'];
            }
        }

        $categories = array_filter($nodes, fn ($node) => $node['type'] === 'category');
        if ($snapshot['mode'] === 'legacy') {
            $projectTotal = $legacyProject
                ?? (count($legacy) === count($categories) && $categories !== [] ? $this->sum(array_column($legacy, 'value')) : null);
        } else {
            $parts = array_intersect_key($rollup, $categories);
            $projectTotal = count($parts) === count($categories) && $categories !== [] && array_intersect_key($incomplete, $categories) === []
                ? $this->sum($parts) : null;
        }

        $rows = [];
        if ($withRows) {
            $children = [];
            foreach ($nodes as $node) {
                $children[$node['parent_id'] ?? 'root'][] = $node;
            }
            foreach ($children as &$group) {
                usort($group, fn ($a, $b) => [$a['position'], $a['code']] <=> [$b['position'], $b['code']]);
            }
            unset($group);
            $visit = function (string $parent, int $level) use (&$visit, &$rows, $children, $weights, $physical, $legacy, $rollup, $incomplete): void {
                foreach ($children[$parent] ?? [] as $item) {
                    $id = $item['id'];
                    $contribution = $legacy[$id]['value'] ?? (isset($incomplete[$id]) ? null : ($rollup[$id] ?? null));
                    $rows[] = $item + [
                        'level' => $level, 'project_weight' => WbsSnapshot::format($weights[$id]),
                        'planned_pct' => isset($physical[$id]) ? WbsSnapshot::format($physical[$id]) : null,
                        'planned_contribution_pp' => $contribution === null ? null : WbsSnapshot::format($contribution),
                        'target_source' => $legacy[$id]['source'] ?? null,
                    ];
                    $visit($id, $level + 1);
                }
            };
            $visit('root', 0);
        }

        return [
            'mode' => $snapshot['mode'], 'rows' => $rows,
            'planned_pct' => $projectTotal === null ? null : WbsSnapshot::format($projectTotal),
            'target_source' => $source,
        ];
    }

    private function itemTarget(array $node, ?string $value, string $month): ?string
    {
        if ($node['start_date'] && $month < substr($node['start_date'], 0, 7)) {
            return '0';
        }
        if ($node['end_date'] && $month > substr($node['end_date'], 0, 7)) {
            return '100';
        }

        return $value;
    }

    private function sum(array $values): string
    {
        return array_reduce($values, fn ($sum, $value) => bcadd($sum, $value, 18), '0');
    }
}
