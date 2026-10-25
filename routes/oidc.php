<?php

use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Oidc\ProviderController;
use Illuminate\Support\Facades\Route;

// Endpoint mesin: tanpa session/CSRF. Rate limit per IP + CORS allow-list.
Route::middleware(['iam.cors', 'throttle:60,1'])->group(function () {
    Route::options('/{any}', fn () => response('', 204))->where('any', '^(oauth|api|\.well-known)/.*');

    Route::get('/.well-known/openid-configuration', [ProviderController::class, 'discovery']);
    Route::get('/oauth/jwks', [ProviderController::class, 'jwks']);
    Route::post('/oauth/token', [ProviderController::class, 'token']);
    Route::match(['get', 'post'], '/oauth/userinfo', [ProviderController::class, 'userinfo']);
});

// API mesin ber-API key (scope, hash-only, fail-closed). Tanpa query-string token.
Route::prefix('api/v1')->middleware(['iam.cors', 'iam.apikey'])->group(function () {
    Route::get('/me', [MachineController::class, 'me']);
    Route::get('/users/{sub}/access', [MachineController::class, 'userAccess'])->middleware('iam.apikey:iam:users.read');
});
