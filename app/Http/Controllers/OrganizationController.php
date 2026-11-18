<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Access;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function __construct(private readonly Access $access) {}

    public function home(Request $request): Response
    {
        return Inertia::render('Home', [
            'accounts' => $request->user()->platform_admin
                ? User::query()->orderBy('name')->orderBy('id')->paginate(30, ['id', 'name', 'email', 'active', 'platform_admin'])->withQueryString()
                : null,
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        abort_unless($request->user()->platform_admin, 403);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', 'unique:organizations,slug'],
        ]);
        $id = (string) Str::ulid();
        DB::transaction(function () use ($request, $data, $id): void {
            DB::table('organizations')->insert($data + ['id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            Audit::record($request->user(), 'organization.created', $id, target: $id, changes: $data);
        });

        return redirect()->route('projects.index', $data['slug']);
    }

    public function directory(Request $request, string $organization): Response
    {
        $org = $this->managedOrganization($request, $organization);
        $permissions = [];
        foreach (Access::ROLES as $role => $label) {
            $permissions[$role] = $this->access->actions($org->id, $role);
        }

        return Inertia::render('Organization', [
            'organization' => $org,
            'members' => $this->access->members($org->id)->orderBy('users.name')
                ->get(['members.user_id as id', 'users.name', 'users.email', 'users.active as account_active', 'members.role', 'members.active']),
            'invitations' => DB::table('invitations')->where('organization_id', $org->id)->whereNull('accepted_at')
                ->where('expires_at', '>', now())->orderBy('expires_at')->get(['email', 'role', 'expires_at']),
            'roles' => Access::ROLES,
            'catalog' => Access::ACTIONS,
            'permissions' => $permissions,
            'audit' => DB::table('audit_events')->leftJoin('users', 'users.id', '=', 'audit_events.actor_id')
                ->where('audit_events.organization_id', $org->id)->orderByDesc('audit_events.id')->limit(100)
                ->get(['audit_events.id', 'audit_events.action', 'audit_events.target_id', 'audit_events.changes', 'audit_events.reason', 'audit_events.created_at', 'users.name as actor']),
        ]);
    }

    public function permissions(Request $request, string $organization, string $role): RedirectResponse
    {
        $org = $this->managedOrganization($request, $organization);
        abort_unless(isset(Access::ROLES[$role]) && $role !== 'administrator', 404);
        $actions = $request->validate(['actions' => 'present|array', 'actions.*' => ['distinct', Rule::in(array_keys(Access::ACTIONS))]])['actions'];

        DB::transaction(function () use ($request, $org, $role, $actions): void {
            DB::table('organizations')->where('id', $org->id)->lockForUpdate()->first();
            $before = $this->access->actions($org->id, $role);
            DB::table('organization_permissions')->updateOrInsert(
                ['organization_id' => $org->id, 'role' => $role],
                ['actions' => json_encode(array_values($actions), JSON_THROW_ON_ERROR)],
            );
            Audit::record($request->user(), 'organization.permissions_changed', $org->id, target: $role, changes: ['before' => $before, 'after' => $actions]);
        });

        return back()->with('notice', 'Hak akses diperbarui.');
    }

    public function invite(Request $request, string $organization): RedirectResponse
    {
        $org = $this->managedOrganization($request, $organization);
        $data = $request->validate(['email' => 'required|email|max:255', 'role' => ['required', Rule::in(array_keys(Access::ROLES))]]);
        $email = Str::lower($data['email']);
        $id = (string) Str::ulid();
        $expires = now()->addHours(48);

        DB::transaction(function () use ($request, $org, $data, $email, $id, $expires): void {
            DB::table('organizations')->where('id', $org->id)->lockForUpdate()->first();
            DB::table('invitations')->where('organization_id', $org->id)->where('email', $email)
                ->whereNull('accepted_at')->update(['expires_at' => now()]);
            DB::table('invitations')->insert([
                'id' => $id, 'organization_id' => $org->id, 'email' => $email, 'role' => $data['role'], 'expires_at' => $expires,
            ]);
            Audit::record($request->user(), 'organization.invited', $org->id, target: $id, changes: ['email' => $email, 'role' => $data['role']]);
        });

        $url = URL::temporarySignedRoute('invitation.show', $expires, ['invitation' => $id]);
        Mail::raw("Anda diundang bergabung ke {$org->name}.\nBuka tautan ini dalam 48 jam:\n{$url}", function ($message) use ($email): void {
            $message->to($email)->subject('Undangan Monitoring Galangan');
        });

        return back()->with('notice', 'Undangan dibuat dan dikirim.');
    }

    public function invitationPage(Request $request, string $invitation): Response
    {
        $record = $this->pendingInvitation($invitation);
        $org = DB::table('organizations')->where('id', $record->organization_id)->first();

        return Inertia::render('Auth', [
            'mode' => 'invite',
            'email' => $record->email,
            'invitedOrganization' => $org->name,
            'action' => $request->fullUrl(),
            'newAccount' => ! User::query()->where('email', $record->email)->exists(),
            'signedIn' => $request->user()?->email === $record->email,
        ]);
    }

    public function accept(Request $request, string $invitation): RedirectResponse
    {
        $record = $this->pendingInvitation($invitation);
        $user = DB::transaction(function () use ($request, $invitation, $record): User {
            DB::table('organizations')->where('id', $record->organization_id)->lockForUpdate()->first();
            $record = $this->pendingInvitation($invitation, true);
            $user = User::query()->where('email', $record->email)->lockForUpdate()->first();
            if ($user) {
                if (! $user->active || ($request->user()?->id !== $user->id && ! Hash::check((string) $request->input('password'), $user->password))) {
                    throw ValidationException::withMessages(['password' => 'Kata sandi atau akun tidak valid.']);
                }
            } else {
                $data = $request->validate(['name' => 'required|string|max:255', 'password' => 'required|string|confirmed|min:12|max:72']);
                $user = User::create($data + ['email' => $record->email]);
            }

            $key = ['organization_id' => $record->organization_id, 'user_id' => $user->id];
            $membership = DB::table('organization_members')->where($key)->first();
            if (! $membership || ! $membership->active) {
                DB::table('organization_members')->updateOrInsert($key, ['role' => $record->role, 'active' => true]);
            }
            DB::table('invitations')->where('id', $invitation)->update(['accepted_at' => now()]);
            Audit::record($user, 'organization.invitation_accepted', $record->organization_id, target: $invitation);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();
        $slug = DB::table('organizations')->where('id', $record->organization_id)->value('slug');

        return redirect()->route('projects.index', $slug)->with('notice', 'Undangan diterima.');
    }

    public function membership(Request $request, string $organization, string $member): RedirectResponse
    {
        $org = $this->managedOrganization($request, $organization);
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(Access::ROLES))], 'active' => 'required|boolean']);
        $data['active'] = (bool) $data['active'];
        DB::transaction(function () use ($request, $org, $member, $data): void {
            DB::table('organizations')->where('id', $org->id)->lockForUpdate()->first();
            $key = ['organization_id' => $org->id, 'user_id' => $member];
            $before = DB::table('organization_members')->where($key)->first();
            abort_unless($before, 404);

            if (! $data['active']) {
                DB::table('personal_access_tokens')->where('organization_id', $org->id)->where('tokenable_type', User::class)
                    ->where('tokenable_id', $member)->delete();
                DB::table('invitations')->where('organization_id', $org->id)
                    ->where('email', User::query()->whereKey($member)->value('email'))
                    ->whereNull('accepted_at')->where('expires_at', '>', now())
                    ->update(['expires_at' => now()]);
            }
            DB::table('organization_members')->where($key)->update($data);
            Audit::record($request->user(), 'organization.membership_changed', $org->id, target: $member, changes: [
                'before' => ['role' => $before->role, 'active' => (bool) $before->active], 'after' => $data,
            ]);
        });

        return ($this->access->admin($request->user(), $org->id) ? back() : redirect()->route('home'))
            ->with('notice', 'Keanggotaan diperbarui.');
    }

    public function account(Request $request, string $account): RedirectResponse
    {
        abort_unless($request->user()->platform_admin, 403);
        $data = $request->validate(['active' => 'required|boolean']);
        $active = (bool) $data['active'];
        abort_if(! $active && $account === $request->user()->id, 422, 'Akun yang sedang digunakan harus tetap aktif.');

        DB::transaction(function () use ($request, $account, $active): void {
            User::query()->where('platform_admin', true)->orderBy('id')->lockForUpdate()->get();
            abort_unless($request->user()->fresh()?->active, 403);
            $user = User::query()->whereKey($account)->lockForUpdate()->firstOrFail();
            $before = $user->active;
            $user->forceFill(['active' => $active, 'remember_token' => Str::random(60)])->save();
            if (! $active) {
                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            Audit::record($request->user(), 'account.status_changed', target: $account, changes: ['before' => $before, 'after' => $active]);
        });

        return back()->with('notice', 'Status akun diperbarui.');
    }

    private function managedOrganization(Request $request, string $slug): object
    {
        $organization = $this->access->organization($request->user(), $slug);
        abort_unless($this->access->admin($request->user(), $organization->id), 403);

        return $organization;
    }

    private function pendingInvitation(string $id, bool $lock = false): object
    {
        $invitation = DB::table('invitations')->where('id', $id)->whereNull('accepted_at')
            ->where('expires_at', '>', now())->when($lock, fn ($query) => $query->lockForUpdate())->first();
        abort_unless($invitation, 404, 'Undangan sudah digunakan atau kedaluwarsa.');

        return $invitation;
    }
}
