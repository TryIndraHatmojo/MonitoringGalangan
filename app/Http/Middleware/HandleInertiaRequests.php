<?php

namespace App\Http\Middleware;

use App\Services\Access;
use Illuminate\Http\Request;
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

                return [
                    'user' => $user?->only(['id', 'name', 'email', 'platform_admin']),
                    'organization' => $user ? app(Access::class)->assignedOrganization($user) : null,
                ];
            },
            'notice' => fn () => $request->session()->get('notice'),
        ];
    }
}
