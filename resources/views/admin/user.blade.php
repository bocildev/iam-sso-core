@extends('admin.layout')
@section('title', 'Profil User — ' . $u->name)
@section('admin')
<div class="page-head">
  <div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <h1>{{ $u->name }}</h1>
      <x-badge :variant="$u->status">{{ $u->status }}</x-badge>
      @if($u->hasMfaEnabled())
        <x-badge variant="success">MFA TOTP Aktif</x-badge>
      @endif
    </div>
    <p>{{ $u->email }} · Subjek OIDC: <code>{{ $u->sub }}</code></p>
  </div>
  <a href="/admin/users" class="btn btn-sm btn-ghost">&larr; Semua User</a>
</div>

<form method="post" action="/admin/users/{{ $u->id }}">
  @csrf
  <div class="grid-2">
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Profil &amp; Status Akun</h2>
      </div>
      <label for="u-name">Nama Lengkap</label>
      <input id="u-name" name="name" value="{{ $u->name }}" required>

      <label for="u-status">Status Akun</label>
      <select id="u-status" name="status">
        @foreach(['active','suspended'] as $s)
          <option value="{{ $s }}" @selected($u->status===$s)>{{ $s }}</option>
        @endforeach
      </select>

      <label class="checkbox-row">
        <input type="checkbox" name="is_super_admin" value="1" @checked($u->is_super_admin)>
        <span><strong>Super Admin IAM</strong> (akses penuh konsol administrasi)</span>
      </label>

      <label for="u-new-pass">Password Baru (opsional, minimal 8 karakter)</label>
      <div style="position:relative;margin-bottom:14px">
        <input id="u-new-pass" type="password" name="new_password" autocomplete="new-password" placeholder="Kosongkan jika tidak ingin mengubah password" style="margin-bottom:0;padding-right:92px">
        <button type="button" class="btn-ghost btn-sm" data-toggle-password="u-new-pass" aria-pressed="false" aria-label="Tampilkan password" style="position:absolute;right:6px;top:5px;min-height:30px;padding:3px 9px">
          Tampilkan
        </button>
      </div>

      <button type="submit">Simpan Perubahan User</button>
    </div>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Matriks Akses Aplikasi &amp; Role</h2>
      </div>
      <p class="muted" style="margin-top:0;font-size:13px">
        Pilih aplikasi yang diizinkan beserta organisasi (<code>tenant_id</code>) dan role spesifik aplikasi.
      </p>
      @foreach($apps as $a)
        <div style="background:var(--surface-input);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;margin-bottom:12px">
          <label class="checkbox-row" style="margin-bottom:8px">
            <input type="checkbox" name="apps[]" value="{{ $a->id }}" @checked(in_array($a->id, $appIds))>
            <strong>{{ $a->name }}</strong>
            <code>{{ $a->client_id }}</code>
          </label>

          <label for="org-{{ $a->id }}" style="font-size:12px">Organisasi (Tenant)</label>
          <select id="org-{{ $a->id }}" name="org[{{ $a->id }}]" style="margin-bottom:10px">
            <option value="">— Tanpa Organisasi —</option>
            @foreach($orgs as $o)
              <option value="{{ $o->id }}" @selected(($orgByApp[$a->id] ?? null) == $o->id)>{{ $o->name }} ({{ $o->slug }})</option>
            @endforeach
          </select>

          <div style="display:flex;gap:12px;flex-wrap:wrap">
            @foreach($a->roles as $r)
              <label class="checkbox-row" style="margin-bottom:4px">
                <input type="checkbox" name="roles[]" value="{{ $r->id }}" @checked(in_array($r->id, $roleIds))>
                <span>{{ $r->name }} (<code>{{ $r->key }}</code>)</span>
              </label>
            @endforeach
          </div>
        </div>
      @endforeach
    </div>
  </div>
</form>

@if($u->hasMfaEnabled())
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Darurat: Reset MFA TOTP User</h3>
    <x-badge variant="warning">Tindakan Sensitif</x-badge>
  </div>
  <p class="muted" style="margin-top:0;font-size:13px">
    Gunakan tindakan ini hanya apabila user kehilangan perangkat authenticator sekaligus recovery code. Tindakan ini dicatat di Audit Log.
  </p>
  <form method="post" action="/admin/users/{{ $u->id }}/reset-mfa">
    @csrf
    <button type="submit" class="btn btn-danger btn-sm">Reset MFA TOTP User Ini</button>
  </form>
</div>
@endif
@endsection
