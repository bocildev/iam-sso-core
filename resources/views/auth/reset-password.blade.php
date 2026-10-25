@extends('layouts.app')
@section('title', 'Buat Password Baru')
@section('content')
<main id="main-content" class="auth-shell">
  <div class="auth-topbar"><x-theme-toggle /></div>
  <div class="auth-card">
    <div style="margin-bottom:20px">
      <div class="brand-mark" style="margin-bottom:10px">
        <span class="brand-icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </span>
        <span>Atur Password Baru</span>
      </div>
      <h1 style="margin:0 0 6px;font-size:20px">Perbarui Kredensial</h1>
      <p class="muted" style="margin:0;font-size:13.5px">Seluruh sesi SSO dan refresh token lama akan otomatis dicabut setelah password disimpan.</p>
    </div>

    @if($errors->any())
      <div class="alert alert-err" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="/reset-password">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">

      <label for="email">Alamat Email</label>
      <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username">

      <label for="new-password">Password Baru (minimal 12 karakter)</label>
      <input id="new-password" type="password" name="password" required autofocus autocomplete="new-password">

      <label for="password-confirmation">Konfirmasi Password Baru</label>
      <input id="password-confirmation" type="password" name="password_confirmation" required autocomplete="new-password">

      <button type="submit" style="width:100%">Simpan Password Baru</button>
    </form>
  </div>
</main>
@endsection
