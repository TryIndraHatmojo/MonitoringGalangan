<?php

namespace App\Services;

use Illuminate\Http\Request;

final class ProjectContext
{
    public function __construct(private readonly Access $access) {}

    public function props(Request $request, string $organization, string $page, ?object $project = null): array
    {
        $user = $request->user();
        $key = $this->sessionKey($organization);
        if ($project === null) {
            $projects = $this->access->projects($user, $organization);
            $last = $request->session()->get($key);
            $project = $last ? (clone $projects)->where('projects.id', $last)->first() : null;
            $project ??= $projects->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                ->orderBy('number')->orderBy('projects.id')->first();
        }
        if ($project) {
            $this->remember($request, $organization, $project->id);
        } else {
            $request->session()->forget($key);
        }

        return [
            'page' => $page,
            'active' => $project ? [
                'id' => $project->id,
                'ship_name' => $project->ship_name,
                'number' => $project->number,
                'canOpenBuilder' => $this->access->allows($user, $project, 'wbs.view'),
            ] : null,
            'canManageOrganization' => $this->access->admin($user, $organization),
        ];
    }

    public function remember(Request $request, string $organization, string $project): void
    {
        $request->session()->put($this->sessionKey($organization), $project);
    }

    private function sessionKey(string $organization): string
    {
        return 'active_projects.'.$organization;
    }
}
