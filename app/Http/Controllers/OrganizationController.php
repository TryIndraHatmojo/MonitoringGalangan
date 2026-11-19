<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Access;
use App\Services\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
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

    public function masterUsers(Request $request): Response
    {
        abort_unless($this->access->platformAdmin($request->user()), 403);

        return Inertia::render('Master', [
            'section' => 'users',
            'organizations' => $this->access->organizations(),
            'accounts' => User::query()->orderBy('name')->orderBy('id')
                ->paginate(30, ['id', 'name', 'email', 'active', 'kode_role'])->withQueryString(),
        ]);
    }

    public function masterShipyards(Request $request): Response
    {
        abort_unless($this->access->platformAdmin($request->user()), 403);

        return Inertia::render('Master', [
            'section' => 'shipyards',
            'organizations' => $this->access->organizations(),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        abort_unless($this->access->platformAdmin($request->user()), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', 'unique:galangantbls,slug'],
        ]);
        $id = (string) Str::ulid();
        DB::transaction(function () use ($request, $data, $id): void {
            DB::table('galangantbls')->insert($data + ['id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            Audit::record($request->user(), 'organization.created', $id, target: $id, changes: $data);
        });

        return redirect()->route('master.shipyards')->with('notice', 'Galangan dibuat.');
    }

    public function directory(Request $request, string $organization): Response
    {
        $org = $this->managedOrganization($request, $organization);
        $roles = $this->access->roles();
        $permissions = [];
        foreach (array_keys($roles) as $role) {
            $permissions[$role] = $this->access->actions($org->id, $role);
        }

        return Inertia::render('Organization', [
            'section' => 'roles',
            'organization' => $org,
            'roles' => $roles,
            'catalog' => Access::ACTIONS,
            'permissions' => $permissions,
            'audit' => DB::table('audit_events')->leftJoin('users', 'users.id', '=', 'audit_events.actor_id')
                ->where('audit_events.galangan_id', $org->id)->orderByDesc('audit_events.id')->limit(100)
                ->get(['audit_events.id', 'audit_events.action', 'audit_events.target_id', 'audit_events.changes', 'audit_events.reason', 'audit_events.created_at', 'users.name as actor']),
        ]);
    }

    public function directoryUsers(Request $request, string $organization): Response
    {
        $org = $this->managedOrganization($request, $organization);

        return Inertia::render('Organization', [
            'section' => 'users',
            'organization' => $org,
            'roles' => $this->access->roles(),
            'members' => $this->access->members($org->id)->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.email', 'users.active as account_active', 'users.kode_role as role', 'users.keanggotaan_aktif as active']),
            'invitations' => DB::table('invitations')->where('galangan_id', $org->id)->whereNull('accepted_at')
                ->where('expires_at', '>', now())->orderBy('expires_at')->get(['email', 'kode_role as role', 'expires_at']),
        ]);
    }

    public function permissions(Request $request, string $organization, string $role): RedirectResponse
    {
        $org = $this->managedOrganization($request, $organization);
        abort_unless(isset($this->access->roles()[$role]) && $role !== 'administrator', 404);
        $actions = $request->validate(['actions' => 'present|array', 'actions.*' => ['distinct', Rule::in(array_keys(Access::ACTIONS))]])['actions'];

        DB::transaction(function () use ($request, $org, $role, $actions): void {
            DB::table('galangantbls')->where('id', $org->id)->lockForUpdate()->first();
            $this->access->lockRoles([$role], 'role');
            $before = $this->access->actions($org->id, $role);
            DB::table('galangan_premissiontbls')->updateOrInsert(
                ['galangan_id' => $org->id, 'kode_role' => $role],
                ['actions' => json_encode(array_values($actions), JSON_THROW_ON_ERROR)],
            );
            Audit::record($request->user(), 'organization.permissions_changed', $org->id, target: $role, changes: ['before' => $before, 'after' => $actions]);
        });

        return back()->with('notice', 'Hak akses diperbarui.');
    }

    public function invite(Request $request, string $organization): RedirectResponse
    {
        $org = $this->managedOrganization($request, $organization);
        $data = $request->validate(['email' => 'required|email|max:255', 'role' => ['required', Rule::in(array_keys($this->access->roles()))]]);
        $email = Str::lower($data['email']);
        $id = (string) Str::ulid();
        $expires = now()->addHours(48);

        DB::transaction(function () use ($request, $org, $data, $email, $id, $expires): void {
            DB::table('galangantbls')->where('id', $org->id)->lockForUpdate()->first();
            $this->access->lockRoles([$data['role']], 'role');
            $this->invitedUser($email, $org->id, lock: true);
            DB::table('invitations')->where('galangan_id', $org->id)->where('email', $email)
                ->whereNull('accepted_at')->update(['expires_at' => now()]);
            DB::table('invitations')->insert([
                'id' => $id, 'galangan_id' => $org->id, 'email' => $email, 'kode_role' => $data['role'], 'expires_at' => $expires,
            ]);
            Audit::record($request->user(), 'organization.invited', $org->id, target: $id, changes: ['email' => $email, 'role' => $data['role']]);
        }, 3);

        $url = URL::temporarySignedRoute('invitation.show', $expires, ['invitation' => $id]);
        Mail::raw("Anda diundang bergabung ke {$org->name}.\nBuka tautan ini dalam 48 jam:\n{$url}", function ($message) use ($email): void {
            $message->to($email)->subject('Undangan Monitoring Galangan');
        });

        return back()->with('notice', 'Undangan dibuat dan dikirim.');
    }

    public function invitationPage(Request $request, string $invitation): Response
    {
        $record = $this->pendingInvitation($invitation);
        $org = DB::table('galangantbls')->where('id', $record->galangan_id)->first();
        $user = User::query()->where('email', $record->email)->first();
        abort_if($user && $user->galangan_id !== $record->galangan_id, 403, 'Akun email ini sudah terdaftar di luar galangan ini.');

        return Inertia::render('Auth', [
            'mode' => 'invite',
            'email' => $record->email,
            'invitedOrganization' => $org->name,
            'action' => $request->fullUrl(),
            'newAccount' => $user === null,
            'signedIn' => $request->user()?->email === $record->email,
        ]);
    }

    public function accept(Request $request, string $invitation): RedirectResponse
    {
        $record = $this->pendingInvitation($invitation);
        try {
            $user = DB::transaction(function () use ($request, $invitation, $record): User {
                DB::table('galangantbls')->where('id', $record->galangan_id)->lockForUpdate()->first();
                $record = $this->pendingInvitation($invitation, true);
                $user = $this->invitedUser($record->email, $record->galangan_id, lock: true);
                if ($user) {
                    if (! $user->active || ($request->user()?->id !== $user->id && ! Hash::check((string) $request->input('password'), $user->password))) {
                        throw ValidationException::withMessages(['password' => 'Kata sandi atau akun tidak valid.']);
                    }
                } else {
                    $data = $request->validate(['name' => 'required|string|max:255', 'password' => 'required|string|confirmed|min:12|max:72']);
                    $user = new User($data + ['email' => $record->email]);
                }
                if (! $user->exists || ! $user->keanggotaan_aktif) {
                    $user->forceFill([
                        'galangan_id' => $record->galangan_id,
                        'kode_role' => $record->kode_role,
                        'keanggotaan_aktif' => true,
                    ])->save();
                }
                DB::table('invitations')->where('id', $invitation)->update(['accepted_at' => now()]);
                Audit::record($user, 'organization.invitation_accepted', $record->galangan_id, target: $invitation);

                return $user;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if (! $this->invitedUser($record->email, $record->galangan_id)) {
                throw $exception;
            }

            throw ValidationException::withMessages(['email' => 'Akun sudah dibuat. Masuk lalu buka kembali undangan.']);
        }
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('notice', 'Undangan diterima.');
    }

    public function membership(Request $request, string $organization, string $member): RedirectResponse
    {
        $org = $this->managedOrganization($request, $organization);
        $data = $request->validate(['role' => ['required', Rule::in(array_keys($this->access->roles()))], 'active' => 'required|boolean']);
        $data['active'] = (bool) $data['active'];
        DB::transaction(function () use ($request, $org, $member, $data): void {
            DB::table('galangantbls')->where('id', $org->id)->lockForUpdate()->first();
            $this->access->lockRoles([$data['role']], 'role');
            $user = User::query()->where('galangan_id', $org->id)->whereKey($member)->lockForUpdate()->firstOrFail();
            $before = ['role' => $user->kode_role, 'active' => $user->keanggotaan_aktif];

            if (! $data['active']) {
                DB::table('personal_access_tokens')->where('galangan_id', $org->id)->where('tokenable_type', User::class)
                    ->where('tokenable_id', $member)->delete();
                DB::table('invitations')->where('galangan_id', $org->id)->where('email', $user->email)
                    ->whereNull('accepted_at')->where('expires_at', '>', now())
                    ->update(['expires_at' => now()]);
            }
            $user->forceFill(['kode_role' => $data['role'], 'keanggotaan_aktif' => $data['active']])->save();
            Audit::record($request->user(), 'organization.membership_changed', $org->id, target: $member, changes: [
                'before' => $before, 'after' => $data,
            ]);
            if ($request->user()->id === $member) {
                $request->user()->refresh();
            }
        });

        return ($this->access->admin($request->user(), $org->id) ? back() : redirect()->route('home'))
            ->with('notice', 'Keanggotaan diperbarui.');
    }

    public function account(Request $request, string $account): RedirectResponse
    {
        abort_unless($this->access->platformAdmin($request->user()), 403);
        $data = $request->validate(['active' => 'required|boolean']);
        $active = (bool) $data['active'];
        abort_if(! $active && $account === $request->user()->id, 422, 'Akun yang sedang digunakan harus tetap aktif.');

        DB::transaction(function () use ($request, $account, $active): void {
            User::query()->where('kode_role', 'platform_admin')->orderBy('id')->lockForUpdate()->get();
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

    private function invitedUser(string $email, string $organization, bool $lock = false): ?User
    {
        $user = User::query()->where('email', $email)->when($lock, fn ($query) => $query->lockForUpdate())->first();
        if ($user && $user->galangan_id !== $organization) {
            throw ValidationException::withMessages(['email' => 'Akun email ini sudah terdaftar di luar galangan ini.']);
        }

        return $user;
    }
}
