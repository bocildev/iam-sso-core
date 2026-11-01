@extends('layouts.app')
@section('title', 'Portal Pengguna SSO')
@section('content')
<main id="main-content" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px">
  <div class="card" style="max-width:560px;width:100%;box-shadow:var(--shadow-elevated)">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px">
      <div style="display:flex;align-items:center;gap:12px">
        @if($user->avatar_url)
          <img src="{{ $user->avatar_url }}" alt="Avatar" style="width:48px;height:48px;border-radius:50%;border:2px solid var(--accent);object-fit:cover">
        @else
          <div style="width:48px;height:48px;border-radius:50%;background:var(--accent);color:#001020;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px">
            {{ strtoupper(substr($user->name, 0, 1)) }}
          </div>
        @endif
        <div>
          <h1 style="font-size:18px;margin:0 0 2px">Halo, {{ $user->name }}! ✨</h1>
          <p class="muted" style="margin:0;font-size:13px">{{ $user->email }}</p>
        </div>
      </div>
      <x-badge variant="success">Sesi SSO Aktif</x-badge>
    </div>

    <div style="padding:14px;border-radius:8px;background:var(--bg-subtle);border:1px solid var(--border);margin-bottom:18px">
      <div style="font-weight:600;font-size:13px;margin-bottom:4px">Portal Akun Pengguna Terpusat</div>
      <p class="muted" style="margin:0;font-size:12.5px;line-height:1.5">
        Anda saat ini terhubung ke sistem Single Sign-On (SSO). Anda dapat menggunakan akun ini untuk masuk ke seluruh aplikasi dalam ekosistem.
      </p>
    </div>

    <div style="margin-bottom:18px">
      <div style="font-size:13px;font-weight:600;margin-bottom:8px">Aplikasi yang Tersedia:</div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <a href="http://localhost:3001" target="_blank" style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-radius:8px;border:1px solid var(--border);background:var(--bg-card);text-decoration:none">
          <div>
            <div style="font-weight:600;font-size:13.5px">Mochi Finance (Aplikasi Pengguna)</div>
            <span class="muted" style="font-size:12px">Buka aplikasi manajemen keuangan Anda</span>
          </div>
          <span style="font-size:14px">&rarr;</span>
        </a>
      </div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border);padding-top:14px">
      @if($user->is_super_admin)
        <a href="/admin" class="btn btn-sm">Buka Konsol Super Admin &rarr;</a>
      @else
        <span class="muted" style="font-size:12px">Akun Pengguna Biasa</span>
      @endif

      <form method="post" action="/logout" style="margin:0">
        @csrf
        <button type="submit" class="btn btn-sm btn-ghost">Keluar Sesi SSO</button>
      </form>
    </div>
  </div>
</main>
@endsection
