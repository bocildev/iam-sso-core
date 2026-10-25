<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('webhook_url')->nullable()->after('backchannel_logout_uri');
            $table->text('webhook_secret_encrypted')->nullable()->after('webhook_url');
            $table->json('allowed_origins')->nullable()->after('webhook_secret_encrypted');
        });

        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('application_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event_type', 64)->index(); // user.updated, user.disabled, role.changed
            $table->json('payload');
            $table->string('status', 20)->default('pending')->index(); // pending|delivered|failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['webhook_url', 'webhook_secret_encrypted', 'allowed_origins']);
        });
    }
};
