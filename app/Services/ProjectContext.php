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
        if (! $user) {
            return null;
        }

        $selection = $request->session()->get(self::SESSION_KEY);
        $projects = $this->access->selectableProjects($user);
        $project = ($selection['user_id'] ?? null) === $user->id
            ? (clone $projects)->where('projects.id', $selection['project_id'])->first()
            : null;

        if (! $project) {
            $project = $projects->orderByRaw('CASE WHEN projects.status = ? THEN 0 ELSE 1 END', ['active'])
                ->orderBy('projects.number')->orderBy('projects.id')->first();
            if (! $project) {
                $request->session()->forget(self::SESSION_KEY);

                return null;
            }
            $this->remember($request, $project->id);
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
