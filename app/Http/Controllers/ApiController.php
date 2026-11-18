<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Access;
use App\Services\Audit;
use App\Services\Baselines;
use App\Services\WbsSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends Controller
{
    public function __construct(private readonly Access $access, private readonly Baselines $baselines) {}

    public function issue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|max:255',
            'organization' => 'required|string|max:100',
            'device_id' => 'required|string|max:100',
            'device_name' => 'required|string|max:150',
        ]);

        $issued = DB::transaction(function () use ($data): array {
            $org = DB::table('organizations')->where('slug', $data['organization'])->lockForUpdate()->first();
            $user = User::query()->where('email', Str::lower($data['email']))->lockForUpdate()->first();
            abort_unless($org && $user?->active && Hash::check($data['password'], $user->password)
                && ($user->platform_admin || $this->access->role($user, $org->id)), 401, 'Kredensial atau organisasi tidak valid.');

            $user->tokens()->where('organization_id', $org->id)->where('device_id', $data['device_id'])->delete();
            $secret = $user->generateTokenString();
            $token = $user->tokens()->make([
                'name' => $data['device_name'],
                'token' => hash('sha256', $secret),
                'abilities' => ['projects:read', 'wbs:read'],
                'expires_at' => now()->addDays(30),
            ]);
            $token->forceFill(['organization_id' => $org->id, 'device_id' => $data['device_id']])->save();
            Audit::record($user, 'account.device_token_issued', $org->id, target: (string) $token->id, changes: ['device_name' => $token->name]);

            return ['token_type' => 'Bearer', 'token' => $token->id.'|'.$secret, 'expires_at' => $token->expires_at->toIso8601String()];
        }, 3);

        return response()->json(['data' => $issued], 201, ['Cache-Control' => 'no-store']);
    }

    public function revoke(Request $request): Response
    {
        $token = $this->token($request);
        DB::transaction(function () use ($request, $token): void {
            $token->delete();
            Audit::record($request->user(), 'account.device_token_revoked', $token->organization_id, target: (string) $token->id);
        });

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        $token = $this->token($request);
        $user = $request->user();
        $org = $this->access->organization($user, $token->organization_id, 'id');

        return response()->json(['data' => [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'organization' => ['id' => $org->id, 'slug' => $org->slug, 'name' => $org->name],
            'permissions' => $this->access->permissions($user, $org->id),
        ]]);
    }

    public function projects(Request $request): JsonResponse
    {
        $token = $this->token($request, 'projects:read');
        $page = $this->pagination($request);
        $user = $request->user();
        $result = $this->access->projects($user, $token->organization_id)->orderBy('number')->orderBy('id')
            ->paginate($page['per_page'], page: $page['page']);

        return response()->json([
            'data' => $result->getCollection()->map(fn (object $project): array => $this->projectData($user, $project)),
            'meta' => $page + ['total' => $result->total()],
        ]);
    }

    public function project(Request $request, string $project): JsonResponse
    {
        $token = $this->token($request, 'projects:read');
        $record = $this->access->project($request->user(), $token->organization_id, $project);

        return response()->json(['data' => $this->projectData($request->user(), $record)]);
    }

    public function wbs(Request $request, string $project): JsonResponse
    {
        $token = $this->token($request, 'wbs:read');
        $record = $this->access->project($request->user(), $token->organization_id, $project);
        $this->access->require($request->user(), $record, 'wbs.view');
        $period = $this->period($request);
        $page = $this->pagination($request);
        $baseline = $this->baselines->published($project, $period);
        $plan = $this->baselines->planned($this->baselines->snapshot($baseline), $period);
        $rows = array_slice($plan['rows'], ($page['page'] - 1) * $page['per_page'], $page['per_page']);

        return response()->json([
            'data' => $rows,
            'meta' => $page + [
                'total' => count($plan['rows']), 'period' => $period,
                'baseline_id' => $baseline?->id, 'baseline_status' => $baseline ? 'published' : 'not_published',
                'mode' => $baseline ? $plan['mode'] : null, 'planned_pct' => $plan['planned_pct'],
                'target_source' => $plan['target_source'],
            ],
        ]);
    }

    public function item(Request $request, string $item): JsonResponse
    {
        $token = $this->token($request, 'wbs:read');
        $identity = DB::table('wbs_item_ids')->where('id', $item)->first();
        abort_unless($identity, 404);
        $project = $this->access->project($request->user(), $token->organization_id, $identity->project_id);
        abort_unless($this->access->allows($request->user(), $project, 'wbs.view'), 404);
        $period = $this->period($request);
        $baseline = $this->baselines->published($project->id, $period);
        abort_unless($baseline, 404);
        $rows = $this->baselines->planned($this->baselines->snapshot($baseline), $period)['rows'];
        $row = collect($rows)->firstWhere('id', $item);
        abort_unless($row, 404);

        return response()->json(['data' => $row, 'meta' => ['period' => $period, 'project_id' => $project->id, 'baseline_id' => $baseline->id]]);
    }

    private function token(Request $request, ?string $ability = null): PersonalAccessToken
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        abort_unless($request->bearerToken() && $user?->active && $token instanceof PersonalAccessToken
            && ($user->platform_admin || $this->access->role($user, $token->organization_id)), 401);
        abort_if($ability && ! $token->can($ability), 403);

        return $token;
    }

    private function projectData(User $user, object $project): array
    {
        $data = $this->access->serializeProject($user, $project);
        $data['tonnage_gt'] = $data['tonnage_gt'] === null ? null : bcadd((string) $data['tonnage_gt'], '0', 3);
        if (isset($data['contract_amount'])) {
            $data['contract_amount'] = bcadd((string) $data['contract_amount'], '0', 2);
        }
        $data['wbs_restricted'] = (bool) $data['wbs_restricted'];
        foreach (['created_at', 'updated_at'] as $column) {
            $data[$column] = $data[$column] ? Carbon::parse($data[$column])->toIso8601String() : null;
        }

        return $data;
    }

    private function pagination(Request $request): array
    {
        $data = $request->validate(['page' => 'nullable|integer|min:1|max:1000000', 'per_page' => 'nullable|integer|min:1|max:100']);

        return ['page' => (int) ($data['page'] ?? 1), 'per_page' => (int) ($data['per_page'] ?? 25)];
    }

    private function period(Request $request): string
    {
        return $request->validate(['period' => ['nullable', 'regex:'.WbsSnapshot::MONTH]])['period'] ?? $this->baselines->month();
    }
}
