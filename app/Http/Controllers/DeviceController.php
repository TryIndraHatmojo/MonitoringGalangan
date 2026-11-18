<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Devices', [
            'devices' => $request->user()->tokens()->join('organizations', 'organizations.id', '=', 'personal_access_tokens.organization_id')
                ->orderByDesc('personal_access_tokens.created_at')->get([
                    'personal_access_tokens.id', 'personal_access_tokens.name', 'device_id', 'last_used_at', 'expires_at',
                    'organizations.name as organization',
                ]),
        ]);
    }

    public function revoke(Request $request, string $device): RedirectResponse
    {
        DB::transaction(function () use ($request, $device): void {
            $token = $request->user()->tokens()->whereKey($device)->firstOrFail();
            $token->delete();
            Audit::record($request->user(), 'account.device_token_revoked', $token->organization_id, target: (string) $token->id);
        });

        return back()->with('notice', 'Token perangkat dicabut.');
    }
}
