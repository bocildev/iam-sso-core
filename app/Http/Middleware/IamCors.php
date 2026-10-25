<?php

namespace App\Http\Middleware;

use App\Models\Application;
use Closure;
use Illuminate\Http\Request;

/**
 * CORS untuk endpoint OIDC & API mesin.
 * Discovery & JWKS bersifat publik (`*`); endpoint lain mengikuti allow-list origin aplikasi terdaftar
 * atau env `IAM_CORS_ORIGINS`.
 */
class IamCors
{
    public function handle(Request $request, Closure $next)
    {
        $origin = (string) $request->headers->get('Origin', '');
        $allowOrigin = null;

        if ($request->is('.well-known/*', 'oauth/jwks')) {
            $allowOrigin = '*';
        } elseif ($origin !== '' && $this->isAllowedOrigin($origin)) {
            $allowOrigin = $origin;
        }

        if ($request->isMethod('OPTIONS')) {
            $res = response('', 204);
            if ($allowOrigin) {
                $this->applyHeaders($res, $allowOrigin);
            }

            return $res;
        }

        $response = $next($request);
        if ($allowOrigin) {
            $this->applyHeaders($response, $allowOrigin);
        }

        return $response;
    }

    private function applyHeaders($response, string $allowOrigin): void
    {
        $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-API-Key, Accept');
        if ($allowOrigin !== '*') {
            $response->headers->set('Vary', 'Origin');
        }
    }

    private function isAllowedOrigin(string $origin): bool
    {
        $envOrigins = array_filter(array_map('trim', explode(',', (string) env('IAM_CORS_ORIGINS', 'http://localhost:3000,http://localhost:3001'))));
        if (in_array($origin, $envOrigins, true)) {
            return true;
        }

        foreach (Application::where('status', 'active')->get(['redirect_uris', 'allowed_origins']) as $app) {
            foreach ($app->allowed_origins ?? [] as $ao) {
                if (rtrim($ao, '/') === rtrim($origin, '/')) {
                    return true;
                }
            }
            foreach ($app->redirect_uris ?? [] as $uri) {
                $p = parse_url($uri);
                if (! empty($p['scheme']) && ! empty($p['host'])) {
                    $derived = $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
                    if ($derived === rtrim($origin, '/')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
