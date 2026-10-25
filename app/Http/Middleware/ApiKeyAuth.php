<?php

namespace App\Http\Middleware;

use App\Domain\ApiKeys\ApiKeyService;
use App\Domain\Audit\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/** Gateway API key mesin. Header saja (bukan query string), fail-closed. */
class ApiKeyAuth
{
    public function __construct(private ApiKeyService $keys, private AuditLogger $audit) {}

    public function handle(Request $request, Closure $next, string $scope = '')
    {
        $plain = $request->bearerToken() ?: (string) $request->header('X-API-Key', '');
        if ($plain === '') {
            return $this->deny(401, 'unauthorized', 'API key diperlukan.');
        }

        try {
            $key = $this->keys->authenticate($plain);
        } catch (\Throwable $e) {
            return $this->deny(503, 'service_unavailable', 'Layanan sementara tidak tersedia.');
        }

        if (! $key) {
            $this->audit->log('API_KEY_REJECTED', 'api_key', substr($plain, 0, 13), null, 'failure', 'warning');

            return $this->deny(401, 'unauthorized', 'API key tidak valid.');
        }

        $limitKey = 'apikey:'.$key->id;
        if (RateLimiter::tooManyAttempts($limitKey, (int) config('iam.api_key_rate_per_minute'))) {
            return $this->deny(429, 'too_many_requests', 'Batas permintaan terlampaui.');
        }
        RateLimiter::hit($limitKey, 60);

        if ($scope !== '' && ! $this->keys->allows($key, $scope)) {
            $this->audit->log('API_KEY_SCOPE_DENIED', 'api_key', $key->id, $key->application->slug, 'failure', 'warning', ['scope' => $scope]);

            return $this->deny(403, 'forbidden', 'Scope tidak mencukupi.');
        }

        if ($key->last_used_at === null || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->save();
        }
        $request->attributes->set('api_key', $key);

        return $next($request);
    }

    private function deny(int $status, string $error, string $message)
    {
        return response()->json(['error' => $error, 'message' => $message], $status)
            ->header('WWW-Authenticate', 'Bearer');
    }
}
