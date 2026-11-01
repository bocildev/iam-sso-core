<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('category', 30)->default('public_auth')->after('slug');
            $table->boolean('allow_google_login')->default(true)->after('category');
        });

        // Setel kategori aplikasi yang sudah ada sesuai arsitektur ekosistem
        DB::table('applications')->where('slug', 'motion-portfolio')->update([
            'category' => 'public_no_auth',
            'allow_google_login' => false,
        ]);

        DB::table('applications')->where('slug', 'mochi-finance')->update([
            'category' => 'public_auth',
            'allow_google_login' => true,
        ]);

        DB::table('applications')->where('slug', 'mochi-finance-admin')->update([
            'category' => 'internal_admin',
            'allow_google_login' => false,
        ]);

        DB::table('applications')->where('slug', 'portfolio-admin')->update([
            'category' => 'internal_admin',
            'allow_google_login' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['category', 'allow_google_login']);
        });
    }
};
