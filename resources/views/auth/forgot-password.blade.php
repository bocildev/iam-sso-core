@extends('layouts.app')
@section('title', 'Lupa Password')
@section('content')
<main id="main-content" class="auth-shell">
  <div class="auth-topbar"><x-theme-toggle /></div>
  <div class="auth-card">
    <div style="margin-bottom:20px">
      <div class="brand-mark" style="margin-bottom:10px">
        <span class="brand-icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </span>
        <span>Pemulihan Akses</span>
      </div>
      <h1 style="margin:0 0 6px;font-size:20px">Reset Password SSO</h1>
      <p class="muted" style="margin:0;font-size:13.5px">Masukkan alamat email akun Anda untuk menerima tautan pengaturan ulang password.</p>
    </div>

    @if(session('ok'))
      <div class="alert alert-ok" role="status">{{ session('ok') }}</div>
    @endif
    @if($errors->any())
      <div class="alert alert-err" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="/forgot-password">
      @csrf
      <label for="reset-email">Alamat Email</label>
      <input id="reset-email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="nama@organisasi.id">
      <button type="submit" style="width:100%">Kirim Tautan Reset</button>
    </form>

    <div style="margin-top:16px;text-align:center">
      <a href="/login" style="font-size:13px">Kembali ke halaman masuk</a>
    </div>
  </div>
</main>
@endsection
