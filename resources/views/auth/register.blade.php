@extends('layouts.app')
@section('title', 'Daftar Akun SSO')
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
      <h1 style="margin:0 0 4px;font-size:21px">Daftar Akun Baru</h1>
      <p class="muted" style="margin:0;font-size:13.5px">Mulai gratis di ekosistem VyrnForge (otomatis mendapatkan paket Free Starter).</p>
    </div>

    @if($errors->any())
      <div class="alert alert-err" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="/register" novalidate>
      @csrf
      <label for="name">Nama Lengkap</label>
      <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Nama lengkap Anda">

      <label for="email">Alamat Email</label>
      <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="nama@email.com">

      <label for="password">Password (Min. 8 Karakter)</label>
      <input id="password" type="password" name="password" required autocomplete="new-password">

      <label for="password_confirmation">Konfirmasi Password</label>
      <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">

      <button type="submit" style="width:100%">
        <span>Daftar & Masuk Sekarang</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </button>
    </form>

    <div style="margin-top:16px;text-align:center;font-size:13px">
      <span class="muted">Sudah punya akun?</span>
      <a href="/login" style="font-weight:600;margin-left:4px">Masuk di sini</a>
    </div>

    <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;font-size:12px" class="muted">
      <span>OpenID Connect 1.0 · PKCE S256</span>
      <span>Default: Free Starter</span>
    </div>
  </div>
</main>
@endsection
