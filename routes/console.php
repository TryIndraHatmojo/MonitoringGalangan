<?php

use App\Models\User;
use App\Services\Audit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

Artisan::command('monitoring:admin {--email=}', function (): int {
    $email = Str::lower(trim($this->option('email') ?: $this->ask('Email administrator pusat')));
    $name = $this->ask('Nama administrator pusat');
    $password = $this->secret('Kata sandi (minimal 12 karakter)');
    $confirmation = $this->secret('Ulangi kata sandi');
    $validator = Validator::make(compact('email', 'name', 'password'), [
        'email' => 'required|email|max:255|unique:users,email',
        'name' => 'required|string|max:255',
        'password' => 'required|string|min:12|max:72',
    ]);
    if ($validator->fails() || $password !== $confirmation) {
        $this->error($validator->errors()->first() ?: 'Konfirmasi kata sandi berbeda.');

        return 1;
    }
    if (! DB::table('roletbls')->where('kode', 'platform_admin')->exists()) {
        $this->error('Isi katalog peran dengan php artisan db:seed --class=RoleSeeder.');

        return 1;
    }

    DB::transaction(function () use ($email, $name, $password): void {
        $user = new User(compact('email', 'name', 'password'));
        $user->forceFill(['kode_role' => 'platform_admin'])->save();
        Audit::record(null, 'account.platform_admin_created', target: $user->id);
    });
    $this->info('Administrator pusat berhasil dibuat.');

    return 0;
})->purpose('Buat Administrator pusat secara interaktif');
