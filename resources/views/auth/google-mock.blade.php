@extends('layouts.app')
@section('title', 'Simulasi Login Google (Sandbox Lokal)')
@section('content')
<main id="main-content" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px">
  <div class="card" style="max-width:440px;width:100%;box-shadow:var(--shadow-elevated);border-color:var(--accent)">
    <div style="text-align:center;margin-bottom:20px">
      <div style="width:52px;height:52px;border-radius:50%;background:var(--bg-subtle);border:1px solid var(--border);display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px">
        <svg width="26" height="26" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
        </svg>
      </div>
      <h1 style="font-size:20px;margin:0 0 6px">Simulasi Login Google</h1>
      <x-badge variant="info" :dot="false">Sandbox Lingkungan Lokal</x-badge>
      <p class="muted" style="font-size:13px;margin:8px 0 0;line-height:1.5">
        Kredensial <code>GOOGLE_CLIENT_ID</code> belum diatur di <code>.env</code>. Halaman ini mensimulasikan persetujuan login Google tanpa perlu setup Google Cloud Console terlebih dahulu.
      </p>
    </div>

    <form method="get" action="/auth/google/callback">
      <input type="hidden" name="mock" value="1">

      <label for="mock-name">Nama Akun Google</label>
      <input id="mock-name" name="mock_name" value="Bocil Developer" required>

      <label for="mock-email">Email Akun Google</label>
      <input id="mock-email" name="mock_email" type="email" value="athallahrich@gmail.com" required>

      @if($intended)
        <div style="margin:12px 0;padding:10px;border-radius:6px;background:var(--bg-subtle);font-size:12px">
          <span class="muted">Tujuan kembali setelah login:</span>
          <div style="font-family:var(--font-mono);color:var(--text-highlight);word-break:break-all;margin-top:2px">
            {{ $intended }}
          </div>
        </div>
      @endif

      <div style="display:flex;flex-direction:column;gap:10px;margin-top:16px">
        <button type="submit" style="width:100%;justify-content:center">
          Lanjutkan Login sebagai Akun Ini &rarr;
        </button>
        <a href="/login" class="btn btn-ghost btn-sm" style="text-align:center;justify-content:center">
          Batal &amp; Kembali ke Form Login
        </a>
      </div>
    </form>
  </div>
</main>
@endsection
