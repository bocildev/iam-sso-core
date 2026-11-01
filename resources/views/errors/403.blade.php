@extends('layouts.app')
@section('title', '403 - Akses Dibatasi')
@section('content')
<main id="main-content" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px">
  <div class="card" style="max-width:480px;width:100%;text-align:center;box-shadow:var(--shadow-elevated);border-color:var(--danger)">
    <div style="width:54px;height:54px;border-radius:50%;background:rgba(239, 68, 68, 0.1);border:1px solid var(--danger);display:inline-flex;align-items:center;justify-content:center;margin-bottom:14px;color:var(--danger)">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
    </div>

    <h1 style="font-size:20px;margin:0 0 6px">403 - Akses Dibatasi</h1>
    <p class="muted" style="font-size:13.5px;margin:0 0 16px;line-height:1.5">
      Halaman konsol <code>/admin</code> hanya dapat diakses oleh akun <strong>Super Admin</strong>.
    </p>

    @if(auth()->check())
      <div style="padding:10px 14px;border-radius:8px;background:var(--bg-subtle);border:1px solid var(--border);margin-bottom:18px;text-align:left;font-size:12.5px">
        <span class="muted" style="display:block;margin-bottom:2px">Saat ini Anda masuk sebagai:</span>
        <strong style="color:var(--text-main)">{{ auth()->user()->email }}</strong>
        <div class="muted" style="margin-top:2px;font-size:11.5px">Status: Pengguna Klien Biasa (Bukan Super Admin)</div>
      </div>
    @endif

    <div style="display:flex;flex-direction:column;gap:10px">
      <form method="post" action="/logout" style="margin:0">
        @csrf
        <button type="submit" class="btn btn-sm btn-danger" style="width:100%;justify-content:center">
          Keluar &amp; Masuk dengan Akun Super Admin
        </button>
      </form>
      <a href="/" class="btn btn-sm btn-ghost" style="justify-content:center">
        Kembali ke Beranda Portal
      </a>
    </div>
  </div>
</main>
@endsection
