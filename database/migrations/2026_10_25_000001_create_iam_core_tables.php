<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('sub')->nullable()->unique()->after('id');
            $table->string('status', 20)->default('active')->after('password'); // active|suspended
            $table->boolean('is_super_admin')->default(false)->after('status');
            $table->timestamp('last_login_at')->nullable();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['organization_id', 'user_id']);
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('client_id')->unique();
            $table->string('client_secret_hash')->nullable(); // null = public client (PKCE wajib)
            $table->json('redirect_uris');
            $table->json('post_logout_redirect_uris')->nullable();
            $table->string('backchannel_logout_uri')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('auth_mode', 10)->default('oidc'); // legacy|oidc|both
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('key'); // resource.action
            $table->string('description')->nullable();
            $table->unique(['application_id', 'key']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->unique(['application_id', 'key']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->unique(['user_id', 'role_id', 'organization_id']);
        });

        Schema::create('user_applications', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->primary(['user_id', 'application_id']);
        });

        Schema::create('sso_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('oauth_authorization_codes', function (Blueprint $table) {
            $table->string('code_hash', 64)->primary();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('sso_session_id');
            $table->text('redirect_uri');
            $table->string('code_challenge')->nullable();
            $table->string('nonce')->nullable();
            $table->string('scope')->default('openid');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
        });

        Schema::create('oauth_refresh_tokens', function (Blueprint $table) {
            $table->string('token_hash', 64)->primary();
            $table->uuid('family_id')->index();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('sso_session_id');
            $table->string('scope')->default('openid');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('signing_keys', function (Blueprint $table) {
            $table->id();
            $table->string('kid')->unique();
            $table->text('public_pem');
            $table->text('private_pem_encrypted');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('prefix', 16);
            $table->string('key_hash', 64)->unique(); // SHA-256, plaintext tidak disimpan
            $table->json('scopes');
            $table->string('status', 20)->default('active'); // active|revoked
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 64)->index();
            $table->string('actor_type', 20)->nullable();
            $table->string('actor_id')->nullable();
            $table->string('target')->nullable();
            $table->string('result', 16)->default('success');
            $table->string('severity', 16)->default('info');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach ([
            'audit_logs', 'api_keys', 'signing_keys', 'oauth_refresh_tokens', 'oauth_authorization_codes',
            'sso_sessions', 'user_applications', 'user_roles', 'role_permissions', 'roles', 'permissions',
            'applications', 'organization_user', 'organizations',
        ] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sub', 'status', 'is_super_admin', 'last_login_at']);
        });
    }
};
