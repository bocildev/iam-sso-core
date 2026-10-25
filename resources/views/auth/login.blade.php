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

    <div style="margin-top:20px;padding-top:14px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;font-size:12px" class="muted">
      <span>OpenID Connect 1.0 · PKCE S256</span>
      <span>RS256 Signed</span>
    </div>
  </div>
</main>
@endsection
