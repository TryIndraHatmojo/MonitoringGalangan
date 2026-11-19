<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function loginPage(): Response
    {
        return Inertia::render('Auth', ['mode' => 'login']);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:255']);
        $email = Str::lower($data['email']);
        $key = 'web-login:'.hash('sha256', $email.'|'.$request->ip());

        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Terlalu banyak percobaan masuk.', [
            'Retry-After' => (string) RateLimiter::availableIn($key),
        ]);
        if (! Auth::attempt(['email' => $email, 'password' => $data['password'], 'active' => true])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak valid.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function forgotPage(): Response
    {
        return Inertia::render('Auth', ['mode' => 'forgot']);
    }

    public function forgot(Request $request): RedirectResponse
    {
        $email = Str::lower($request->validate(['email' => 'required|email|max:255'])['email']);
        Password::sendResetLink(['email' => $email, 'active' => true]);

        return back()->with('notice', 'Jika akun aktif terdaftar, tautan reset akan dikirim.');
    }

    public function resetPage(Request $request, string $token): Response
    {
        return Inertia::render('Auth', ['mode' => 'reset', 'token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|max:255',
            'password' => ['required', 'confirmed', 'max:72', PasswordRule::min(12)],
        ]);
        $data['email'] = Str::lower($data['email']);
        $status = Password::reset($data + ['active' => true], function (User $user, string $password): void {
            DB::transaction(function () use ($user, $password): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                Audit::record($user, 'account.password_reset', target: $user->id);
            });

            event(new PasswordReset($user));
        });

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => 'Tautan reset tidak valid atau sudah kedaluwarsa.']);
        }

        return redirect()->route('login')->with('notice', 'Kata sandi diperbarui.');
    }
}
