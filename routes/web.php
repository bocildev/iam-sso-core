<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\DebuggerController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\Oidc\ProviderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect('/login');
    }
    if (auth()->user()->is_super_admin) {
        return redirect('/admin');
    }

    return view('portal.home', [
        'user' => auth()->user(),
    ]);
});

// Alur browser OIDC (butuh session)
Route::get('/oauth/authorize', [ProviderController::class, 'authorize']);
Route::match(['get', 'post'], '/oauth/logout', [ProviderController::class, 'endSession']);

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:20,1');
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::match(['get', 'post'], '/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::get('/register', [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:10,1');
Route::get('/login/mfa', [LoginController::class, 'showMfa'])->name('login.mfa');
Route::post('/login/mfa', [LoginController::class, 'verifyMfa'])->middleware('throttle:20,1');
Route::post('/logout', [LoginController::class, 'logout']);

Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:10,1')->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');

Route::middleware(['auth', 'iam.admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard']);
    Route::get('/sessions', [AdminController::class, 'sessions']);
    Route::post('/app-sessions/revoke', [AdminController::class, 'revokeClientAppSession']);
    Route::post('/app-sessions/revoke-app', [AdminController::class, 'revokeAppSessionsByApp']);
    Route::post('/app-sessions/revoke-all', [AdminController::class, 'revokeAllClientAppSessions']);
    Route::get('/apps', [AdminController::class, 'apps']);
    Route::post('/apps', [AdminController::class, 'appStore']);
    Route::get('/apps/{app}', [AdminController::class, 'appShow']);
    Route::post('/apps/{app}', [AdminController::class, 'appUpdate']);
    Route::delete('/apps/{app}', [AdminController::class, 'appDestroy']);
    Route::post('/apps/{app}/rotate-secret', [AdminController::class, 'appRotateSecret']);
    Route::post('/apps/{app}/rotate-webhook-secret', [AdminController::class, 'appRotateWebhookSecret']);
    Route::post('/apps/{app}/roles', [AdminController::class, 'roleStore']);
    Route::post('/apps/{app}/api-keys', [AdminController::class, 'apiKeyStore']);
    Route::post('/api-keys/{key}/revoke', [AdminController::class, 'apiKeyRevoke']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::post('/users', [AdminController::class, 'userStore']);
    Route::get('/users/{user}', [AdminController::class, 'userShow']);
    Route::post('/users/{user}', [AdminController::class, 'userUpdate']);
    Route::delete('/users/{user}', [AdminController::class, 'userDestroy']);
    Route::post('/users/{user}/reset-mfa', [AdminController::class, 'userResetMfa']);
    Route::post('/orgs', [AdminController::class, 'orgStore']);
    Route::delete('/orgs/{org}', [AdminController::class, 'orgDestroy']);
    Route::get('/security', [AdminController::class, 'security']);
    Route::post('/security/master-password', [AdminController::class, 'masterPasswordSave']);
    Route::post('/security/master-password/toggle', [AdminController::class, 'masterPasswordToggle']);
    Route::post('/security/mfa/prepare', [AdminController::class, 'mfaPrepare']);
    Route::post('/security/mfa/confirm', [AdminController::class, 'mfaConfirm']);
    Route::post('/security/mfa/disable', [AdminController::class, 'mfaDisable']);
    Route::get('/media', [AdminController::class, 'media']);
    Route::delete('/media/{id}', [AdminController::class, 'mediaDestroy']);
    Route::post('/media/upload', [\App\Http\Controllers\Api\StorageController::class, 'upload']);
    Route::get('/audit', [AdminController::class, 'audit']);
    Route::view('/glossary', 'admin.glossary');
    Route::get('/debugger', [DebuggerController::class, 'index']);
    Route::post('/debugger/run', [DebuggerController::class, 'run']);
    Route::post('/keys/rotate', [AdminController::class, 'rotateKey']);
});
