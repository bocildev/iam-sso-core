@extends('layouts.app')
@section('title', 'Masuk SSO')
@section('content')
<main id="main-content" class="auth-shell">
  <div class="auth-topbar"><x-theme-toggle /></div>
  <div class="auth-card">
    <div style="margin-bottom:22px">
      <div class="brand-mark" style="margin-bottom:10px">
        <span class="brand-icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </span>
        <span>IAM-SSO-CORE</span>
      </div>
      <h1 style="margin:0 0 4px;font-size:21px">Masuk ke Akun Anda</h1>
      <p class="muted" style="margin:0;font-size:13.5px">Satu identitas terpusat untuk seluruh ekosistem aplikasi.</p>
    </div>

    @if(session('ok'))
      <div class="alert alert-ok" role="status">{{ session('ok') }}</div>
    @endif
    @if($errors->any())
      <div class="alert alert-err" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="/login" novalidate>
      @csrf
      <label for="email">Alamat Email</label>
      <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="nama@organisasi.id">

      <div style="display:flex;justify-content:space-between;align-items:baseline">
        <label for="password">Password</label>
        <a href="/forgot-password" style="font-size:12.5px">Lupa password?</a>
      </div>
      <div style="position:relative;margin-bottom:16px">
        <input id="password" type="password" name="password" required autocomplete="current-password" style="margin-bottom:0;padding-right:92px">
        <button type="button" class="btn-ghost btn-sm" data-toggle-password="password" aria-pressed="false" aria-label="Tampilkan password" style="position:absolute;right:6px;top:5px;min-height:30px;padding:3px 9px">
          Tampilkan
        </button>
      </div>

      <button type="submit" style="width:100%">
        <span>Masuk Sekarang</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </button>
    </form>

    <div style="display:flex;align-items:center;gap:12px;margin:18px 0 14px">
      <div style="flex:1;height:1px;background:var(--border)"></div>
      <span class="muted" style="font-size:11.5px;text-transform:uppercase;letter-spacing:0.06em">atau</span>
      <div style="flex:1;height:1px;background:var(--border)"></div>
    </div>

    <a href="/auth/google{{ request()->has('client_id') ? '?client_id='.request('client_id') : '' }}" class="btn btn-ghost" style="width:100%;justify-content:center;gap:10px;padding:9px 16px;border:1px solid var(--border);border-radius:8px;font-weight:600;font-size:13.5px;text-decoration:none;display:flex;align-items:center">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
      </svg>
      <span>Masuk dengan Akun Google</span>
    </a>

    <div style="margin-top:16px;text-align:center;font-size:13px">
      <span class="muted">Belum punya akun klien?</span>
      <a href="/register" style="font-weight:600;margin-left:4px">Daftar Gratis Sekarang</a>
    </div>

    <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;font-size:12px" class="muted">
      <span>OpenID Connect 1.0 · PKCE S256</span>
      <span>RS256 Signed</span>
    </div>
  </div>
</main>
@endsection
