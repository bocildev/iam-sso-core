<?php

namespace App\Console\Commands;

use App\Domain\Tenancy\OutboxService;
use Illuminate\Console\Command;

class IamOutboxWork extends Command
{
    protected $signature = 'iam:outbox-work {--limit=50}';

    protected $description = 'Kirim ulang outbox webhook event yang masih pending';

    public function handle(OutboxService $outbox): int
    {
        $sent = $outbox->retryPending((int) $this->option('limit'));
        $this->info("Delivered {$sent} pending outbox event(s).");

        return self::SUCCESS;
    }
}
