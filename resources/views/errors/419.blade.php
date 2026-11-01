@extends('layouts.app')
@section('title', '419 - Sesi Halaman Berakhir')
@section('content')
<main id="main-content" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px">
  <div class="card" style="max-width:460px;width:100%;text-align:center;box-shadow:var(--shadow-elevated);border-color:var(--warning)">
    <div style="width:54px;height:54px;border-radius:50%;background:rgba(245, 158, 11, 0.1);border:1px solid var(--warning);display:inline-flex;align-items:center;justify-content:center;margin-bottom:14px;color:var(--warning)">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    </div>

    <h1 style="font-size:20px;margin:0 0 6px">419 - Sesi Halaman Berakhir</h1>
    <p class="muted" style="font-size:13.5px;margin:0 0 18px;line-height:1.5">
      Halaman ini sudah terlalu lama terbuka tanpa aktivitas (CSRF Token Expired). Silakan muat ulang halaman untuk melanjutkan.
    </p>

    <div style="display:flex;flex-direction:column;gap:10px">
      <button onclick="window.location.reload()" class="btn btn-sm" style="width:100%;justify-content:center">
        🔄 Muat Ulang Halaman (Refresh)
      </button>
      <a href="/login" class="btn btn-sm btn-ghost" style="justify-content:center">
        Kembali ke Halaman Login
      </a>
    </div>
  </div>
</main>
@endsection
