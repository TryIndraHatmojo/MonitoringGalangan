<?php

namespace App\Http\Middleware;

use App\Services\Access;
use App\Services\ProjectContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => function () use ($request): array {
                $user = $request->user();
                $access = app(Access::class);
                $organization = $user ? $access->assignedOrganization($user) : null;

                return [
                    'user' => $user?->only(['id', 'name', 'email', 'platform_admin']),
                    'organization' => $organization,
                    'canManageOrganization' => $organization && $access->admin($user, $organization->id),
                ];
            },
            'activeProject' => Inertia::always(fn () => app(ProjectContext::class)->active($request)),
            'notice' => fn () => $request->session()->get('notice'),
        ];
    }
}
