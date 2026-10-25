<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class IamCreateAdmin extends Command
{
    protected $signature = 'iam:create-admin {email} {name} {--password= : Min 12 karakter (kosongkan agar diminta interaktif)}';

    protected $description = 'Buat super admin IAM';

    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Password (min 12 karakter)');
        if (strlen((string) $password) < 12) {
            $this->error('Password minimal 12 karakter.');

            return self::FAILURE;
        }
        $user = User::updateOrCreate(
            ['email' => strtolower($this->argument('email'))],
            ['name' => $this->argument('name'), 'password' => $password, 'status' => 'active', 'is_super_admin' => true],
        );
        $this->info("Super admin siap: {$user->email} (sub {$user->sub})");

        return self::SUCCESS;
    }
}
