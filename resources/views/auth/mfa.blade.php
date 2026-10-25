@extends('layouts.app')
@section('title', 'Verifikasi MFA TOTP')
@section('content')
<main id="main-content" class="auth-shell">
  <div class="auth-topbar"><x-theme-toggle /></div>
  <div class="auth-card">
    <div style="margin-bottom:20px">
      <div class="brand-mark" style="margin-bottom:10px">
        <span class="brand-icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </span>
        <span>Verifikasi Dua Langkah</span>
      </div>
      <h1 style="margin:0 0 6px;font-size:20px">Masukkan Kode Authenticator</h1>
      <p class="muted" style="margin:0;font-size:13.5px">
        Akun <code>{{ $email }}</code> dilindungi MFA TOTP. Masukkan kode 6 digit dari aplikasi authenticator atau salah satu recovery code Anda.
      </p>
    </div>

    @if($errors->any())
      <div class="alert alert-err" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="/login/mfa">
      @csrf
      <label for="mfa-code">Kode 6 Digit / Recovery Code</label>
      <input
        id="mfa-code"
        type="text"
        name="code"
        required
        autofocus
        autocomplete="one-time-code"
        placeholder="123456 atau XXXX-XXXX"
        style="font-family:var(--font-mono);font-size:16px;letter-spacing:0.08em"
      >
      <p class="field-hint">Anda dapat menempel (paste) kode langsung dari password manager atau aplikasi authenticator.</p>

      <button type="submit" style="width:100%">Verifikasi &amp; Lanjutkan</button>
    </form>

    <div style="margin-top:16px;text-align:center">
      <a href="/login" style="font-size:13px">Kembali ke halaman login</a>
    </div>
  </div>
</main>
@endsection
