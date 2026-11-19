<?php

namespace App\Services;

use Illuminate\Http\Request;

final class ProjectContext
{
    private const SESSION_KEY = 'active_project';

    public function __construct(private readonly Access $access) {}

    public function active(Request $request): ?array
    {
        $user = $request->user();
        $selection = $request->session()->get(self::SESSION_KEY);
        if (! $user || ($selection['user_id'] ?? null) !== $user->id) {
            return null;
        }
        $project = $this->access->selectableProjects($user)->where('projects.id', $selection['project_id'])->first();
        if (! $project) {
            return null;
        }
        $organization = $this->access->organization($user, $project->galangan_id, 'id');

        return [
            'id' => $project->id,
            'ship_name' => $project->ship_name,
            'number' => $project->number,
            'organization' => ['id' => $organization->id, 'name' => $organization->name, 'slug' => $organization->slug],
            'canOpenBuilder' => $this->access->allows($user, $project, 'wbs.view'),
        ];
    }

    public function remember(Request $request, string $project): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $request->user()->id,
            'project_id' => $project,
        ]);
    }
}
