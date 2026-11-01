<?php

use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Api\StorageController;
use App\Http\Controllers\Oidc\ProviderController;
use Illuminate\Support\Facades\Route;

// Endpoint mesin: tanpa session/CSRF. Rate limit per IP + CORS allow-list.
Route::middleware(['iam.cors', 'throttle:60,1'])->group(function () {
    Route::options('/{any}', fn () => response('', 204))->where('any', '^(oauth|api|\.well-known)/.*');

    Route::get('/.well-known/openid-configuration', [ProviderController::class, 'discovery']);
    Route::get('/oauth/jwks', [ProviderController::class, 'jwks']);
    Route::post('/oauth/token', [ProviderController::class, 'token']);
    Route::post('/oauth/direct-login', [ProviderController::class, 'directLogin']);
    Route::post('/oauth/direct-register', [ProviderController::class, 'directRegister']);
    Route::post('/oauth/revoke', [ProviderController::class, 'revoke']);
    Route::match(['get', 'post'], '/oauth/userinfo', [ProviderController::class, 'userinfo']);

    // Engine Penyimpanan Terpusat (Centralized Storage & Media Engine)
    Route::post('/api/v1/storage/upload', [StorageController::class, 'upload']);
    Route::get('/api/v1/storage/assets', [StorageController::class, 'index']);
    Route::delete('/api/v1/storage/assets/{id}', [StorageController::class, 'destroy']);
});

// API mesin ber-API key (scope, hash-only, fail-closed). Tanpa query-string token.
Route::prefix('api/v1')->middleware(['iam.cors', 'iam.apikey'])->group(function () {
    Route::get('/me', [MachineController::class, 'me']);
    Route::get('/users/{sub}/access', [MachineController::class, 'userAccess'])->middleware('iam.apikey:iam:users.read');
});
