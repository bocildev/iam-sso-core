<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IamPrune extends Command
{
    protected $signature = 'iam:prune';

    protected $description = 'Bersihkan kode otorisasi, refresh token, dan sesi SSO yang sudah kedaluwarsa';

    public function handle(): int
    {
        $codes = DB::table('oauth_authorization_codes')->where('expires_at', '<', now()->subHour())->delete();
        $refresh = DB::table('oauth_refresh_tokens')->where('expires_at', '<', now()->subDays(7))->delete();
        $sessions = DB::table('sso_sessions')->where('expires_at', '<', now()->subDays(7))->delete();

        $this->info("Pruned: {$codes} codes, {$refresh} refresh tokens, {$sessions} SSO sessions.");

        return self::SUCCESS;
    }
}
