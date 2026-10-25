<?php

namespace App\Http\Controllers\Oidc;

use App\Domain\Audit\AuditLogger;
use App\Domain\Oidc\AuthorizationService;
use App\Domain\Oidc\KeyManager;
use App\Domain\Oidc\OidcException;
use App\Domain\Oidc\SessionService;
use App\Domain\Oidc\TokenService;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\SsoSession;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function __construct(
        private AuthorizationService $authz,
        private TokenService $tokens,
        private SessionService $sessions,
        private KeyManager $keys,
        private AuditLogger $audit,
    ) {}

    public function discovery(): JsonResponse
    {
        $i = config('iam.issuer');

        return response()->json([
            'issuer' => $i,
            'authorization_endpoint' => $i.'/oauth/authorize',
            'token_endpoint' => $i.'/oauth/token',
            'userinfo_endpoint' => $i.'/oauth/userinfo',
            'jwks_uri' => $i.'/oauth/jwks',
            'end_session_endpoint' => $i.'/oauth/logout',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => config('iam.scopes_supported'),
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'none'],
            'code_challenge_methods_supported' => ['S256'],
            'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'sid', 'nonce', 'name', 'email', 'email_verified', 'tenant_id', 'roles', 'permissions'],
            'backchannel_logout_supported' => true,
            'backchannel_logout_session_supported' => true,
        ])->header('Access-Control-Allow-Origin', '*');
    }

    public function jwks(): JsonResponse
    {
        return response()->json($this->keys->jwks())
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function authorize(Request $request)
    {
        try {
            ['app' => $app, 'params' => $params] = $this->authz->validate($request->query());
        } catch (OidcException $e) {
            return $this->authzError($e);
        }

        $session = $this->sessions->current($request);
        $prompt = (string) $request->query('prompt', '');

        if ($prompt === 'login' && $request->session()->pull('iam_force_login_done') !== true && $session) {
            $this->sessions->terminate($session, 'prompt_login');
            $session = null;
        }

        if (! $session) {
            if ($prompt === 'none') {
                return $this->redirectError($params['redirect_uri'], 'login_required', $params['state']);
            }
            $request->session()->put('iam_intended', $request->fullUrl());
            $request->session()->put('iam_force_login_done', true);

            return redirect('/login');
        }

        $user = User::findOrFail($session->user_id);
        if (! $this->authz->mayAccess($user, $app)) {
            $this->audit->log('ACCESS_DENIED', 'user', $user->id, $app->slug, 'failure', 'warning');

            return response()->view('oidc.denied', ['app' => $app], 403);
        }

        $code = $this->authz->issueCode($app, $user, $session, $params);
        $this->audit->log('AUTHORIZE_GRANTED', 'user', $user->id, $app->slug);

        return $this->redirectWith($params['redirect_uri'], array_filter(['code' => $code, 'state' => $params['state']]));
    }

    public function token(Request $request): JsonResponse
    {
        try {
            [$id, $secret] = $this->clientCredentials($request);
            $app = $this->tokens->authenticateClient($id, $secret);

            return $this->noStore(response()->json($this->tokens->handle($request->all(), $app)));
        } catch (OidcException $e) {
            return $this->noStore(response()->json($e->toArray(), $e->status === 302 ? 400 : $e->status));
        }
    }

    public function userinfo(Request $request): JsonResponse
    {
        $claims = $this->bearerClaims($request);
        if (! $claims) {
            return response()->json(['error' => 'invalid_token'], 401)->header('WWW-Authenticate', 'Bearer error="invalid_token"');
        }
        $user = User::where('sub', $claims['sub'])->first();
        if (! $user || ! $user->isActive()) {
            return response()->json(['error' => 'invalid_token'], 401);
        }
        $scopes = explode(' ', (string) ($claims['scope'] ?? ''));
        $out = ['sub' => $user->sub];
        if (in_array('profile', $scopes, true)) {
            $out['name'] = $user->name;
        }
        if (in_array('email', $scopes, true)) {
            $out['email'] = $user->email;
            $out['email_verified'] = $user->email_verified_at !== null;
        }

        return response()->json($out);
    }

    public function endSession(Request $request): RedirectResponse
    {
        $app = null;
        $sid = null;
        if ($hint = $request->input('id_token_hint')) {
            try {
                JWT::$leeway = 60 * 60 * 24 * 3650; // id_token_hint boleh sudah kedaluwarsa
                $c = $this->keys->verify($hint);
                $sid = $c['sid'] ?? null;
                $app = Application::where('client_id', is_array($c['aud'] ?? null) ? ($c['aud'][0] ?? '') : ($c['aud'] ?? ''))->first();
            } catch (\Throwable) {
                $app = null;
            } finally {
                JWT::$leeway = 0;
            }
        }

        $session = $this->sessions->current($request) ?? ($sid ? SsoSession::find($sid) : null);
        if ($session) {
            $this->sessions->terminate($session, 'end_session');
        }
        $request->session()->forget('iam_sid');
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $to = $request->input('post_logout_redirect_uri');
        if ($app && $to && in_array($to, $app->post_logout_redirect_uris ?? [], true)) {
            return $this->redirectWith($to, array_filter(['state' => $request->input('state')]));
        }

        return redirect('/login');
    }

    private function clientCredentials(Request $r): array
    {
        if ($r->getUser()) {
            return [urldecode($r->getUser()), urldecode((string) $r->getPassword())];
        }

        return [$r->input('client_id'), $r->input('client_secret')];
    }

    private function bearerClaims(Request $r): ?array
    {
        $t = $r->bearerToken();
        if (! $t) {
            return null;
        }
        try {
            $c = $this->keys->verify($t);
        } catch (\Throwable) {
            return null;
        }
        if (($c['iss'] ?? null) !== config('iam.issuer') || ($c['typ'] ?? null) !== 'access') {
            return null;
        }
        $s = SsoSession::find($c['sid'] ?? '');

        return $s && $s->isValid() ? $c : null;
    }

    private function authzError(OidcException $e)
    {
        if ($e->redirectTo) {
            return $this->redirectError($e->redirectTo, $e->error, $e->state, $e->description);
        }

        return response()->view('oidc.error', ['e' => $e], 400);
    }

    private function redirectError(string $uri, string $error, ?string $state, string $desc = ''): RedirectResponse
    {
        return $this->redirectWith($uri, array_filter(['error' => $error, 'error_description' => $desc, 'state' => $state]));
    }

    private function redirectWith(string $uri, array $params): RedirectResponse
    {
        $sep = str_contains($uri, '?') ? '&' : '?';

        return redirect()->away($uri.$sep.http_build_query($params));
    }

    private function noStore(JsonResponse $r): JsonResponse
    {
        return $r->header('Cache-Control', 'no-store')->header('Pragma', 'no-cache');
    }
}
