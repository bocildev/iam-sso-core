<?php

namespace App\Domain\Tenancy;

use App\Domain\Audit\AuditLogger;
use App\Models\Application;
use App\Models\OutboxEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OutboxService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Tulis event sinkronisasi ke outbox untuk semua app yang memiliki akses user tersebut (atau app spesifik)
     * dan langsung coba kirim webhook HMAC.
     */
    public function publishForUser(User $user, string $eventType, array $extraPayload = [], ?Application $onlyApp = null): int
    {
        $apps = $onlyApp
            ? collect([$onlyApp])
            : Application::whereIn('id', function ($q) use ($user) {
                $q->select('application_id')->from('user_applications')->where('user_id', $user->id);
            })->where('status', 'active')->whereNotNull('webhook_url')->get();

        $count = 0;
        foreach ($apps as $app) {
            if (! $app->webhook_url) {
                continue;
            }
            $event = OutboxEvent::create([
                'application_id' => $app->id,
                'event_type' => $eventType,
                'payload' => array_merge([
                    'event' => $eventType,
                    'sub' => $user->sub,
                    'email' => $user->email,
                    'name' => $user->name,
                    'status' => $user->status,
                    'client_id' => $app->client_id,
                    'occurred_at' => now()->toIso8601String(),
                ], $extraPayload),
                'status' => 'pending',
            ]);
            $this->deliver($event);
            $count++;
        }

        return $count;
    }

    public function deliver(OutboxEvent $event): bool
    {
        $app = $event->application;
        if (! $app || ! $app->webhook_url) {
            $event->update(['status' => 'failed', 'last_error' => 'No webhook_url configured']);

            return false;
        }

        $body = json_encode($event->payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $secret = $app->webhookSecret() ?? '';
        $signature = self::sign($body, $timestamp, $secret);

        $event->increment('attempts');

        try {
            $res = Http::timeout(4)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-IAM-Event' => $event->event_type,
                    'X-IAM-Delivery' => $event->uuid,
                    'X-IAM-Timestamp' => $timestamp,
                    'X-IAM-Signature' => 'sha256='.$signature,
                ])
                ->withBody($body, 'application/json')
                ->post($app->webhook_url);

            if ($res->successful()) {
                $event->update([
                    'status' => 'delivered',
                    'last_http_status' => $res->status(),
                    'last_error' => null,
                    'delivered_at' => now(),
                ]);

                return true;
            }

            $event->update([
                'status' => $event->attempts >= 5 ? 'failed' : 'pending',
                'last_http_status' => $res->status(),
                'last_error' => mb_substr($res->body(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            $event->update([
                'status' => $event->attempts >= 5 ? 'failed' : 'pending',
                'last_error' => mb_substr($e->getMessage(), 0, 500),
            ]);
            $this->audit->log('WEBHOOK_DELIVERY_FAILED', 'system', null, $app->slug, 'failure', 'warning', [
                'event' => $event->event_type,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    public function retryPending(int $limit = 50): int
    {
        $ok = 0;
        $events = OutboxEvent::with('application')
            ->where('status', 'pending')
            ->where('attempts', '<', 5)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($events as $event) {
            if ($this->deliver($event)) {
                $ok++;
            }
        }

        return $ok;
    }

    public static function sign(string $rawBody, string $timestamp, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
    }

    public static function verifySignature(string $rawBody, string $timestamp, string $signatureHeader, string $secret, int $toleranceSeconds = 300): bool
    {
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > $toleranceSeconds) {
            return false;
        }
        $sig = str_starts_with($signatureHeader, 'sha256=') ? substr($signatureHeader, 7) : $signatureHeader;

        return hash_equals(self::sign($rawBody, $timestamp, $secret), $sig);
    }
}
