<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galangan_premissiontbls', function (Blueprint $table): void {
            $table->foreignUlid('galangan_id')->constrained('galangantbls')->cascadeOnDelete();
            $table->string('kode_role', 40);
            $table->foreign('kode_role')->references('kode')->on('roletbls');
            $table->json('actions');
            $table->primary(['galangan_id', 'kode_role']);
        });

        Schema::create('invitations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('galangan_id')->constrained('galangantbls')->cascadeOnDelete();
            $table->string('email');
            $table->string('kode_role', 40);
            $table->foreign('kode_role')->references('kode')->on('roletbls');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('galangan_id')->constrained('galangantbls')->cascadeOnDelete();
            $table->string('number', 80);
            $table->string('name');
            $table->string('ship_name');
            $table->string('ship_type');
            $table->decimal('tonnage_gt', 14, 3)->nullable();
            $table->string('owner');
            $table->string('shipyard');
            $table->date('start_date');
            $table->date('target_date');
            $table->decimal('contract_amount', 18, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->foreignUlid('manager_id')->nullable()->constrained('users');
            $table->foreignUlid('baseline_approver_id')->nullable()->constrained('users');
            $table->string('status', 20)->default('active');
            $table->boolean('wbs_restricted')->default(false);
            $table->timestamps();
            $table->unique(['galangan_id', 'number']);
        });

        Schema::create('project_members', function (Blueprint $table): void {
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_pic')->default(false);
            $table->primary(['project_id', 'user_id']);
        });

        Schema::create('wbs_readers', function (Blueprint $table): void {
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'user_id']);
        });

        Schema::create('project_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained();
            $table->string('title');
            $table->string('filename');
            $table->string('path')->unique();
            $table->unsignedBigInteger('size');
            $table->timestamp('created_at');
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('galangan_id')->nullable()->constrained('galangantbls');
            $table->foreignUlid('project_id')->nullable()->constrained();
            $table->foreignUlid('actor_id')->nullable()->constrained('users');
            $table->string('action', 80);
            $table->string('target_id', 80)->nullable();
            $table->text('reason')->nullable();
            $table->json('changes');
            $table->timestamp('created_at');
            $table->index(['galangan_id', 'project_id', 'id']);
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->ulidMorphs('tokenable');
            $table->foreignUlid('galangan_id')->constrained('galangantbls')->cascadeOnDelete();
            $table->string('device_id', 100);
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['tokenable_type', 'tokenable_id', 'galangan_id', 'device_id'], 'one_token_per_device');
        });

        Schema::create('wbs_item_ids', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('baseline_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('draft');
            $table->char('effective_month', 7);
            $table->text('reason')->nullable();
            $table->json('snapshot');
            $table->foreignUlid('created_by')->constrained('users');
            $table->foreignUlid('submitted_by')->nullable()->constrained('users');
            $table->foreignUlid('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'revision']);
            $table->index(['project_id', 'status', 'effective_month']);
        });

        Schema::create('project_periods', function (Blueprint $table): void {
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->char('month', 7);
            $table->date('cutoff_date')->nullable();
            $table->foreignUlid('official_baseline_id')->nullable()->constrained('baseline_revisions');
            $table->primary(['project_id', 'month']);
        });
    }

    public function down(): void
    {
        foreach ([
            'project_periods', 'baseline_revisions', 'wbs_item_ids', 'personal_access_tokens',
            'audit_events', 'project_documents', 'wbs_readers', 'project_members', 'projects',
            'invitations', 'galangan_premissiontbls',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
