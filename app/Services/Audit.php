<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class Audit
{
    public static function record(
        ?User $actor, string $action, ?string $organization = null, ?string $project = null,
        ?string $target = null, array $changes = [], ?string $reason = null,
    ): void {
        DB::table('audit_events')->insert([
            'actor_id' => $actor?->id,
            'galangan_id' => $organization,
            'project_id' => $project,
            'action' => $action,
            'target_id' => $target,
            'changes' => json_encode($changes, JSON_THROW_ON_ERROR),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
