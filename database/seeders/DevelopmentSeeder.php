<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Baselines;
use App\Services\WbsTemplates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Fixture tersedia di lingkungan pengembangan dan test.');
        }

        $admin = User::query()->where('platform_admin', true)->where('active', true)->orderBy('id')->first();
        if (! $admin) {
            throw new RuntimeException('Buat Administrator pusat dengan php artisan monitoring:admin.');
        }

        DB::transaction(function () use ($admin): void {
            $this->demo($admin, 'ferry', 'Galangan Ferry Contoh', 'Ferry 1500 GT (demo)');
            $this->demo($admin, 'tug', 'Galangan Tug Contoh', 'ASD Harbour Tug (demo)');
        });
    }

    private function demo(User $admin, string $template, string $organization, string $name): void
    {
        $slug = $template.'-demo';
        $org = DB::table('organizations')->where('slug', $slug)->first();
        if ($org) {
            return;
        }

        $organizationId = (string) Str::ulid();
        $projectId = (string) Str::ulid();
        DB::table('organizations')->insert([
            'id' => $organizationId, 'slug' => $slug, 'name' => $organization, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('projects')->insert([
            'id' => $projectId, 'organization_id' => $organizationId, 'number' => 'DEMO-01',
            'name' => $name, 'ship_name' => $name, 'ship_type' => $template === 'ferry' ? 'Ferry' : 'Harbour Tug',
            'tonnage_gt' => $template === 'ferry' ? '1500' : null, 'owner' => 'Pemilik contoh', 'shipyard' => $organization,
            'start_date' => '2026-09-01', 'target_date' => '2026-11-30', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $project = DB::table('projects')->where('id', $projectId)->first();
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
        $service->transition($admin, $project, $id, 'submit', 1, 'Fixture development untuk pemeriksaan struktur dan target.');
        $service->transition($admin, $project, $id, 'publish', 2, null);
        if ($template === 'ferry') {
            $service->setCutoff($admin, $project, $month, '2026-10-28');
        }
    }
}
