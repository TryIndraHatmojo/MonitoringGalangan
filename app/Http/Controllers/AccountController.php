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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(private readonly Access $access) {}

    public function index(Request $request): Response
    {
        $this->access->requirePlatform($request->user(), 'accounts.manage');

        return Inertia::render('Master', [
            'section' => 'users',
            'organizations' => $this->access->organizations(),
            'roles' => $this->access->roleCatalog(),
            'accounts' => User::query()->orderBy('name')->orderBy('id')
                ->paginate(30, ['id', 'name', 'email', 'active', 'kode_role', 'galangan_id'])->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->access->requirePlatform($request->user(), 'accounts.manage');
        $data = $this->validateAccount($request);

        try {
            DB::transaction(function () use ($request, $data): void {
                $this->lockAdministrator($request->user());
                $organization = $data['galangan_id'] ?? null;
                if ($organization !== null) {
                    $organization = $this->access->lockOrganization($organization)->id;
                }
                $this->access->lockRoles([$data['role']], 'role');
                $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
                $user->forceFill(['kode_role' => $data['role'], 'galangan_id' => $organization, 'active' => $data['active']])->save();
                Audit::record($request->user(), 'account.created', $organization, target: $user->id, changes: $this->accountDetails($user));
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Email sudah digunakan.']);
        }

        return redirect()->route('master.users')->with('notice', 'User ditambahkan.');
    }

    public function update(Request $request, string $account): RedirectResponse
    {
        $actor = $request->user();
        $this->access->requirePlatform($actor, 'accounts.manage');
        $record = User::query()->findOrFail($account);
        $data = $this->validateAccount($request, $record);
        if ($record->is($actor) && ! $data['active']) {
            throw ValidationException::withMessages(['active' => 'Akun yang sedang digunakan harus tetap aktif.']);
        }

        try {
            $logout = DB::transaction(function () use ($actor, $account, $data): bool {
                $this->lockAdministrator($actor);
                $this->access->lockRoles([$data['role']], 'role');
                $user = User::query()->lockForUpdate()->findOrFail($account);
                $before = $this->accountDetails($user);
                $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'kode_role' => $data['role'], 'active' => $data['active']]);
                $passwordChanged = isset($data['password']);
                if ($passwordChanged) {
                    $user->password = Hash::make($data['password']);
                }
                $revoke = $user->isDirty(['email', 'kode_role', 'active']) || $passwordChanged;
                if ($revoke) {
                    $user->remember_token = Str::random(60);
                }
                $user->save();
                if ($revoke) {
                    $this->revokeAccess($user, array_unique([$before['email'], $user->email]));
                }
                Audit::record($actor, 'account.updated', $user->galangan_id, target: $user->id, changes: [
                    'before' => $before, 'after' => $this->accountDetails($user), 'password_changed' => $passwordChanged,
                ]);

                return $revoke && $user->is($actor);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Email sudah digunakan.']);
        }

        if ($logout) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('notice', 'User diperbarui. Masuk kembali dengan kredensial terbaru.');
        }

        return redirect()->route('master.users')->with('notice', 'User diperbarui.');
    }

    public function status(Request $request, string $account): RedirectResponse
    {
        $actor = $request->user();
        $this->access->requirePlatform($actor, 'accounts.manage');
        $active = (bool) $request->validate(['active' => 'required|boolean'])['active'];
        DB::transaction(function () use ($actor, $account, $active): void {
            $this->lockAdministrator($actor);
            $user = User::query()->lockForUpdate()->findOrFail($account);
            abort_if(! $active && $user->is($actor), 422, 'Akun yang sedang digunakan harus tetap aktif.');
            $before = $user->active;
            $user->forceFill(['active' => $active, 'remember_token' => Str::random(60)])->save();
            if (! $active) {
                $this->revokeAccess($user, [$user->email]);
            }
            Audit::record($actor, 'account.status_changed', $user->galangan_id, target: $user->id, changes: ['before' => $before, 'after' => $active]);
        }, 3);

        return back()->with('notice', 'Status akun diperbarui.');
    }

    public function destroy(Request $request, string $account): RedirectResponse
    {
        $actor = $request->user();
        $this->access->requirePlatform($actor, 'accounts.manage');
        DB::transaction(function () use ($actor, $account): void {
            $this->lockAdministrator($actor);
            $user = User::query()->lockForUpdate()->findOrFail($account);
            if ($user->is($actor)) {
                throw ValidationException::withMessages(['user' => 'Akun yang sedang digunakan tidak dapat dihapus.']);
            }
            $before = $this->accountDetails($user);
            $user->forceFill(['active' => false, 'remember_token' => Str::random(60)])->save();
            $user->delete();
            $this->revokeAccess($user, [$user->email]);
            Audit::record($actor, 'account.deleted', $user->galangan_id, target: $user->id, changes: ['before' => $before]);
        }, 3);

        return redirect()->route('master.users')->with('notice', 'User dihapus.');
    }

    private function validateAccount(Request $request, ?User $user = null): array
    {
        $uniqueEmail = Rule::unique('users', 'email');
        $roles = $this->access->roleCatalog();
        if ($user !== null) {
            $uniqueEmail->ignore($user->id);
            $roles = $user->platform_admin ? ['platform_admin' => $roles['platform_admin']] : $this->access->roles();
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', $uniqueEmail],
            'role' => ['required', 'string', Rule::in(array_keys($roles))],
            'galangan_id' => $user === null
                ? ['bail', 'nullable', 'required_unless:role,platform_admin', 'prohibited_if:role,platform_admin', 'ulid', Rule::exists('galangantbls', 'id')->whereNull('dihapus_pada')]
                : ['nullable', 'ulid', Rule::in([$user->galangan_id])],
            'active' => 'required|boolean',
            'password' => [
                'bail', $user === null ? 'required' : 'nullable', 'string', 'min:12', 'confirmed',
                function (string $attribute, string $value, \Closure $fail): void {
                    if (strlen($value) > 72 || str_contains($value, "\0")) {
                        $fail('Kata sandi boleh berisi hingga 72 byte tanpa karakter nol.');
                    }
                },
            ],
        ], ['email.unique' => 'Email sudah digunakan.']);
        $data['email'] = Str::lower($data['email']);
        $data['active'] = (bool) $data['active'];

        return $data;
    }

    private function lockAdministrator(User $actor): void
    {
        $administrators = User::query()->where('kode_role', 'platform_admin')->orderBy('id')->lockForUpdate()->get();
        abort_unless($administrators->firstWhere('id', $actor->id)?->active, 403);
    }

    private function accountDetails(User $user): array
    {
        return ['name' => $user->name, 'email' => $user->email, 'role' => $user->kode_role, 'active' => $user->active];
    }

    private function revokeAccess(User $user, array $emails): void
    {
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
        if ($user->galangan_id !== null) {
            DB::table('invitations')->where('galangan_id', $user->galangan_id)->whereIn('email', $emails)
                ->whereNull('accepted_at')->where('expires_at', '>', now())->update(['expires_at' => now()]);
        }
    }
}
