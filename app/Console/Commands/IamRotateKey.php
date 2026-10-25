<?php

namespace App\Console\Commands;

use App\Domain\Oidc\KeyManager;
use Illuminate\Console\Command;

class IamRotateKey extends Command
{
    protected $signature = 'iam:rotate-key';

    protected $description = 'Rotasi signing key RS256 (key lama tetap di JWKS selama masa tenggang)';

    public function handle(KeyManager $keys): int
    {
        $this->info('Key aktif baru: '.$keys->rotate()->kid);

        return self::SUCCESS;
    }
}
