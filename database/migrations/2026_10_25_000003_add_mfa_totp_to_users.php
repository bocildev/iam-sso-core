<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('totp_secret_encrypted')->nullable()->after('is_super_admin');
            $table->timestamp('totp_confirmed_at')->nullable()->after('totp_secret_encrypted');
            $table->json('recovery_code_hashes')->nullable()->after('totp_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['totp_secret_encrypted', 'totp_confirmed_at', 'recovery_code_hashes']);
        });
    }
};
