@extends('admin.layout')
@section('title', 'User & Organisasi')
@section('admin')
<div class="page-head">
  <div>
    <h1>Direktori User &amp; Organisasi</h1>
    <p>Kelola identitas global (<code>sub</code> UUID), status MFA TOTP, dan struktur organisasi pelanggan.</p>
  </div>
</div>

<div class="admin-layout">
  <div class="admin-main">
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Daftar User ({{ $users->count() }})</h2>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nama &amp; Subjek OIDC</th>
              <th>Email</th>
              <th>Status</th>
              <th>MFA TOTP</th>
              <th>Hak Akses</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($users as $u)
              <tr>
                <td>
                  <a href="/admin/users/{{ $u->id }}" style="font-weight:600">{{ $u->name }}</a>
                  <div class="muted" style="font-size:11.5px;font-family:var(--font-mono)">{{ $u->sub }}</div>
                </td>
                <td>{{ $u->email }}</td>
                <td><x-badge :variant="$u->status">{{ $u->status }}</x-badge></td>
                <td>
                  @if($u->hasMfaEnabled())
                    <x-badge variant="success">Aktif</x-badge>
                  @else
                    <x-badge variant="neutral" :dot="false">Nonaktif</x-badge>
                  @endif
                </td>
                <td>
                  @if($u->is_super_admin)
                    <x-badge variant="info" :dot="false">Super Admin</x-badge>
                  @else
                    <span class="muted">Member</span>
                  @endif
                </td>
                <td style="text-align:right">
                  <div style="display:inline-flex;align-items:center;gap:6px">
                    <a href="/admin/users/{{ $u->id }}" class="btn btn-sm btn-ghost">Kelola</a>
                    @if($u->id !== auth()->id())
                      <form method="post" action="/admin/users/{{ $u->id }}" style="margin:0;display:inline" onsubmit="return confirm('⚠️ PERINGATAN:\n\nApakah Anda yakin ingin MENGHAPUS pengguna &quot;{{ $u->name }}&quot; ({{ $u->email }})?\n\nSeluruh sesi login, hak akses aplikasi, dan peran pengguna ini akan dicabut permanen.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" title="Hapus User" style="padding:4px 8px;display:inline-flex;align-items:center">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                        </button>
                      </form>
                    @endif
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Tambah User Baru</h3>
      </div>
      <form method="post" action="/admin/users">
        @csrf
        <div class="grid-2">
          <div>
            <label for="u-name">Nama Lengkap</label>
            <input id="u-name" name="name" required placeholder="Nama Lengkap">
          </div>
          <div>
            <label for="u-email">Alamat Email</label>
            <input id="u-email" type="email" name="email" required placeholder="user@organisasi.id">
          </div>
        </div>

        <label for="u-pass">Password Awal (minimal 8 karakter)</label>
        <input id="u-pass" type="password" name="password" required autocomplete="new-password">

        <label class="checkbox-row">
          <input type="checkbox" name="is_super_admin" value="1">
          <span>Berikan hak <strong>Super Admin IAM</strong> (akses penuh kontrol panel ini)</span>
        </label>

        <button type="submit">Buat Identitas User</button>
      </form>
    </div>
  </div>

  <aside class="admin-aside">
    <x-help key="users" title="Apa ini? Daftar user dan organisasi">
      <ul>
        <li><strong>Subjek OIDC (kode panjang di bawah nama)</strong>: nomor identitas unik user yang tidak berubah, seperti nomor KTP.</li>
        <li><strong>MFA TOTP</strong>: apakah user memakai kode tambahan dari ponsel saat login.</li>
        <li><strong>Super Admin</strong>: akses penuh ke konsol ini. Beri seperlunya.</li>
        <li><strong>Organisasi</strong>: kelompok pelanggan/perusahaan tempat user dikaitkan.</li>
      </ul>
    </x-help>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title" style="font-size:14.5px">Organisasi (Tenant Pelanggan)</h3>
      </div>
      <p class="muted" style="margin-top:0;font-size:12.5px">
        Organisasi memetakan klaim <code>tenant_id</code> pada token OIDC ketika user mengakses aplikasi tertentu.
      </p>
      <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:14px">
        @forelse($orgs as $o)
          <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 10px;border-radius:6px;background:var(--bg-subtle);border:1px solid var(--border)">
            <div>
              <span style="font-weight:600;font-size:13px">{{ $o->name }}</span>
              <span class="muted" style="font-size:11px;font-family:var(--font-mono);margin-left:4px">({{ $o->slug }})</span>
            </div>
            <form method="post" action="/admin/orgs/{{ $o->id }}" style="margin:0" onsubmit="return confirm('Hapus organisasi &quot;{{ $o->name }}&quot;?');">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--danger);padding:2px 6px" title="Hapus Organisasi">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              </button>
            </form>
          </div>
        @empty
          <span class="muted" style="font-size:12px">Belum ada organisasi terdaftar.</span>
        @endforelse
      </div>

      <form method="post" action="/admin/orgs" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
        @csrf
        <label for="o-name" style="font-size:12px">Nama Organisasi</label>
        <input id="o-name" name="name" required placeholder="VyrnForge Ecosystem" style="padding:8px 10px;font-size:13px">

        <label for="o-slug" style="font-size:12px">Slug Organisasi</label>
        <input id="o-slug" name="slug" required placeholder="vyrnforge" style="padding:8px 10px;font-size:13px">

        <button type="submit" class="btn btn-sm" style="width:100%;justify-content:center">Tambah Organisasi</button>
      </form>
    </div>

    <div class="card">
      <div class="card-header" style="margin-bottom:8px">
        <h3 class="card-title" style="font-size:14.5px">Tips Akses Global</h3>
      </div>
      <p class="muted" style="font-size:12px;margin:0">
        Akun yang dibuat di sini memiliki identitas tunggal lintas aplikasi. Setelah akun dibuat, buka detail user untuk memberikan izin aplikasi dan role spesifik.
      </p>
    </div>
  </aside>
</div>
@endsection
