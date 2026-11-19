<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roletbls', function (Blueprint $table): void {
            $table->string('kode', 40)->primary();
            $table->string('nama');
        });

        Schema::create('galangantbls', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->foreignUlid('galangan_id')->nullable()->constrained('galangantbls');
            $table->string('kode_role', 40);
            $table->foreign('kode_role')->references('kode')->on('roletbls');
            $table->boolean('keanggotaan_aktif')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_galangan_required CHECK (
            (kode_role = 'platform_admin' AND galangan_id IS NULL)
            OR (kode_role <> 'platform_admin' AND galangan_id IS NOT NULL)
        )");

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->ulid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('galangantbls');
        Schema::dropIfExists('roletbls');
    }
};
