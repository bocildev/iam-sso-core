@extends('layouts.app')
@section('content')
@php
$path = request()->path();
$navItems = [
    ['href' => '/admin', 'label' => 'Dashboard', 'active' => $path === 'admin', 'icon' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>'],
    ['href' => '/admin/sessions', 'label' => 'Sesi Aktif', 'active' => str_starts_with($path, 'admin/sessions'), 'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
    ['href' => '/admin/apps', 'label' => 'Aplikasi & API Key', 'active' => str_starts_with($path, 'admin/apps'), 'icon' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>'],
    ['href' => '/admin/users', 'label' => 'User & Organisasi', 'active' => str_starts_with($path, 'admin/users'), 'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['href' => '/admin/security', 'label' => 'Keamanan & MFA', 'active' => str_starts_with($path, 'admin/security'), 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
    ['href' => '/admin/media', 'label' => 'Media & Storage', 'active' => str_starts_with($path, 'admin/media'), 'icon' => '<path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m16 16-4-4-4 4"/>'],
    ['href' => '/admin/debugger', 'label' => 'Debugger & Console', 'active' => str_starts_with($path, 'admin/debugger'), 'icon' => '<polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>'],
    ['href' => '/admin/glossary', 'label' => 'Kamus Istilah', 'active' => str_starts_with($path, 'admin/glossary'), 'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
    ['href' => '/admin/audit', 'label' => 'Audit Log', 'active' => str_starts_with($path, 'admin/audit'), 'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
];
$me = auth()->user();
@endphp
<div class="console-shell">
  <aside class="console-sidebar" aria-label="Navigasi Admin">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
      <a href="/admin" class="brand-mark" style="text-decoration:none">
        <span class="brand-icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </span>
        <div>
          <div style="line-height:1.1">IAM-SSO-CORE</div>
          <span class="muted" style="font-size:11px;font-weight:500">OIDC &amp; RBAC Control Plane</span>
        </div>
      </a>
      <x-theme-toggle />
    </div>

    <nav class="nav-list">
      @foreach($navItems as $item)
        <a href="{{ $item['href'] }}" class="nav-link {{ $item['active'] ? 'active' : '' }}" @if($item['active']) aria-current="page" @endif>
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">{!! $item['icon'] !!}</svg>
          <span>{{ $item['label'] }}</span>
        </a>
      @endforeach
    </nav>

    <div style="margin-top:auto;padding-top:14px;border-top:1px solid var(--border)">
      @if($me)
        <div style="margin-bottom:10px">
          <div style="font-weight:600;font-size:13px">{{ $me->name }}</div>
          <div class="muted" style="font-size:12px;word-break:break-all">{{ $me->email }}</div>
          <div style="margin-top:6px">
            @if($me->hasMfaEnabled())
              <x-badge variant="success">MFA TOTP Aktif</x-badge>
            @else
              <x-badge variant="warning">MFA Belum Aktif</x-badge>
            @endif
          </div>
        </div>
      @endif
      <form method="post" action="/logout">
        @csrf
        <button class="btn-ghost btn btn-sm" type="submit" style="width:100%">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          <span>Keluar Sesi SSO</span>
        </button>
      </form>
    </div>
  </aside>

  <main id="main-content" class="console-main">
    @if(session('ok'))
      <div class="alert alert-ok" role="status">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <span class="ok" style="margin:0">{{ session('ok') }}</span>
      </div>
    @endif

    @if($errors->any())
      <div class="alert alert-err" role="alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span class="err" style="margin:0">{{ $errors->first() }}</span>
      </div>
    @endif

    @if(session('secret'))
      <x-secret-banner label="Client Secret (tampil sekali)" :value="session('secret')" />
    @endif
    @if(session('webhook_secret'))
      <x-secret-banner label="Webhook HMAC Secret (tampil sekali)" :value="session('webhook_secret')" />
    @endif
    @if(session('api_key'))
      <x-secret-banner label="API Key Mesin (tampil sekali)" :value="session('api_key')" />
    @endif
    @if(session('recovery_codes'))
      <x-secret-banner
        label="Recovery Codes MFA (simpan di tempat aman — tampil sekali)"
        :value="implode('   ', session('recovery_codes'))"
        hint="Setiap kode hanya dapat dipakai satu kali saat Anda tidak memiliki akses ke aplikasi authenticator."
      />
    @endif

    @yield('admin')
  </main>
</div>
@endsection
