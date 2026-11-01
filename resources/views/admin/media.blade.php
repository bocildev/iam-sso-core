@extends('admin.layout')
@section('title', 'Media & Storage Terpusat')
@section('admin')
<div class="page-head">
  <div>
    <h1>Media &amp; Storage Engine Terpusat</h1>
    <p>Pusat penyimpanan aset gambar (auto-compress &le; 500 KB) dan dokumen (&le; 2 MB) untuk seluruh aplikasi ekosistem.</p>
  </div>
</div>

<div class="admin-layout">
  {{-- KOLOM UTAMA --}}
  <div class="admin-main">

    {{-- KARTU STATISTIK PENYIMPANAN --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:20px">
      <div class="card" style="padding:16px">
        <div class="muted" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em">Total Digunakan</div>
        <div style="font-size:24px;font-weight:700;font-family:var(--font-mono);color:var(--text);margin-top:4px">
          {{ $totalStorageBytes >= 1048576 ? round($totalStorageBytes / 1048576, 2) . ' MB' : round($totalStorageBytes / 1024, 1) . ' KB' }}
        </div>
        <div class="muted" style="font-size:12px;margin-top:2px">Kapasitas penyimpanan public storage</div>
      </div>
      <div class="card" style="padding:16px">
        <div class="muted" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em">Penghematan Kompresi</div>
        <div style="font-size:24px;font-weight:700;font-family:var(--font-mono);color:var(--success);margin-top:4px">
          {{ $savingsBytes >= 1048576 ? round($savingsBytes / 1048576, 2) . ' MB' : round($savingsBytes / 1024, 1) . ' KB' }}
        </div>
        <div class="muted" style="font-size:12px;margin-top:2px">Ukuran diefisiensi oleh engine GD</div>
      </div>
      <div class="card" style="padding:16px">
        <div class="muted" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em">Jumlah Aset File</div>
        <div style="font-size:24px;font-weight:700;font-family:var(--font-mono);color:var(--primary);margin-top:4px">
          {{ $assets->total() }}
        </div>
        <div class="muted" style="font-size:12px;margin-top:2px">Gambar &amp; dokumen aktif</div>
      </div>
    </div>

    {{-- FORM UPLOAD CEPAT LANGSUNG DARI IAM CONSOLE --}}
    <div class="card" style="margin-bottom:20px">
      <div class="card-header">
        <div>
          <h2 class="card-title">Unggah File Baru</h2>
          <p class="muted" style="margin:4px 0 0;font-size:13px">Unggah langsung gambar atau dokumen dari browser ke penyimpanan pusat.</p>
        </div>
      </div>
      <form action="/admin/media/upload" method="post" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
        @csrf
        <div style="flex:1;min-width:240px">
          <label class="form-label" for="upload_file">Pilih File (Gambar maks 15MB &rarr; auto &le;500KB, Dokumen &le;2MB)</label>
          <input class="form-input" type="file" id="upload_file" name="file" required accept="image/*,.pdf,.doc,.docx,.txt" style="padding:6px 10px">
        </div>
        <div style="width:180px">
          <label class="form-label" for="app_slug">Aplikasi Pemilik</label>
          <select class="form-input" id="app_slug" name="app_slug">
            <option value="general">Umum / Shared</option>
            @foreach($apps as $a)
              <option value="{{ $a->slug }}">{{ $a->name }}</option>
            @endforeach
          </select>
        </div>
        <div style="width:140px">
          <label class="form-label" for="folder">Folder / Kategori</label>
          <input class="form-input" type="text" id="folder" name="folder" placeholder="logos, cv, ..." value="general">
        </div>
        <div>
          <button type="submit" class="btn btn-primary" style="height:38px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <span>Unggah ke Storage</span>
          </button>
        </div>
      </form>
    </div>

    {{-- DAFTAR ASET MEDIA --}}
    <div class="card">
      <div class="card-header" style="flex-wrap:wrap;gap:12px">
        <div>
          <h2 class="card-title">Daftar Aset Tersimpan</h2>
          <p class="muted" style="margin:4px 0 0;font-size:13px">File yang tersimpan di disk publik dan siap digunakan di seluruh frontend.</p>
        </div>
        <form method="get" action="/admin/media" style="display:flex;gap:8px;align-items:center">
          <select name="app_id" class="form-input" style="font-size:12px;padding:6px 10px;width:auto" onchange="this.form.submit()">
            <option value="">Semua Aplikasi</option>
            @foreach($apps as $a)
              <option value="{{ $a->id }}" @selected($selectedAppId == $a->id)>{{ $a->name }}</option>
            @endforeach
          </select>
          @if($selectedAppId || $selectedFolder)
            <a href="/admin/media" class="btn btn-ghost btn-sm" style="font-size:12px">Reset Filter</a>
          @endif
        </form>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:70px">Pratinjau</th>
              <th>Nama File &amp; URL</th>
              <th>Aplikasi &amp; Folder</th>
              <th>Tipe &amp; Ukuran</th>
              <th>Status Kompresi</th>
              <th style="text-align:right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse($assets as $m)
              <tr>
                <td>
                  @if($m->isImage())
                    <a href="{{ $m->public_url }}" target="_blank" rel="noopener">
                      <img src="{{ $m->public_url }}" alt="{{ $m->original_name }}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--border);background:var(--bg)">
                    </a>
                  @else
                    <div style="width:48px;height:48px;border-radius:6px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;background:var(--card);font-family:var(--font-mono);font-size:11px;font-weight:700;color:var(--primary)">
                      DOC
                    </div>
                  @endif
                </td>
                <td>
                  <div style="font-weight:600;font-size:13px;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $m->original_name }}">
                    {{ $m->original_name }}
                  </div>
                  <div style="display:flex;align-items:center;gap:6px;margin-top:4px">
                    <input type="text" readonly value="{{ $m->public_url }}" id="url-{{ $m->id }}" style="font-family:var(--font-mono);font-size:11px;background:var(--bg);border:1px solid var(--border);border-radius:4px;padding:2px 6px;width:180px;color:var(--text-muted)">
                    <button type="button" class="btn btn-ghost btn-sm" style="padding:2px 8px;font-size:11px" onclick="copyMediaUrl('url-{{ $m->id }}')">
                      Salin Link
                    </button>
                  </div>
                </td>
                <td>
                  <div style="font-weight:500;font-size:13px">{{ $m->application?->name ?? 'General / Shared' }}</div>
                  <div style="margin-top:2px"><x-badge variant="neutral" :dot="false">{{ $m->folder }}</x-badge></div>
                </td>
                <td>
                  <div style="font-family:var(--font-mono);font-size:12px;font-weight:600">{{ $m->formatted_size }}</div>
                  <div class="muted" style="font-size:11px">{{ $m->mime_type }}</div>
                </td>
                <td>
                  @if($m->is_compressed)
                    <x-badge variant="success" :dot="true">Auto &le;500KB</x-badge>
                    @if($m->original_size)
                      <div class="muted" style="font-size:11px;margin-top:2px">Asal: {{ round($m->original_size / 1024, 1) }} KB</div>
                    @endif
                  @else
                    <x-badge variant="neutral" :dot="false">Original</x-badge>
                  @endif
                </td>
                <td style="text-align:right">
                  <div style="display:flex;gap:6px;justify-content:flex-end">
                    <a href="{{ $m->public_url }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" title="Buka di tab baru">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    </a>
                    <form method="post" action="/admin/media/{{ $m->id }}" onsubmit="return confirm('Hapus aset file ini secara permanen?')">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)" title="Hapus file">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="muted" style="text-align:center;padding:32px">
                  Belum ada aset media yang diunggah. Unggah file pertama Anda di atas atau kirim dari aplikasi klien!
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div style="margin-top:16px">
        {{ $assets->links() }}
      </div>
    </div>
  </div>

  {{-- KOLOM SAMPING (ASIDE / PANDUAN EDUKASI) --}}
  <div class="admin-aside">
    <x-help key="media-engine" title="Apa ini? Centralized Media Engine">
      <p><b>Centralized Media Storage Engine</b> adalah fasilitas gudang aset digital bersama di dalam ekosistem IAM.</p>
      <p>Daripada setiap aplikasi klien (seperti Portfolio Admin, Mochi Finance, dll.) mengurus folder upload sendiri-sendiri, semua file (foto logo, screenshot proyek, avatar profil, maupun CV PDF) diunggah dan disimpan terpusat di sini.</p>
    </x-help>

    <div class="card" style="margin-top:16px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:8px">Aturan Batasan Ukuran</h3>
      <ul style="font-size:13px;line-height:1.6;padding-left:18px;margin:0" class="muted">
        <li><b style="color:var(--text)">Gambar:</b> Maksimal <span style="color:var(--success);font-weight:600">&le; 500 KB</span>. Jika Anda mengunggah foto mentah (misal 5MB dari HP/kamera), engine otomatis melakukan <i>resampling &amp; multi-pass compression</i> agar ukurannya mengecil ke bawah 500 KB tanpa merusak kualitas tampilan web.</li>
        <li><b style="color:var(--text)">Dokumen:</b> Maksimal <span style="color:var(--warning);font-weight:600">&le; 2 MB</span> (PDF, DOC, DOCX, TXT). Cocok untuk CV, portofolio dossier, dan berkas sertifikat.</li>
        <li><b style="color:var(--danger)">Keamanan:</b> Seluruh file skrip berbahaya (<code>.php</code>, <code>.exe</code>, <code>.sh</code>) otomatis ditolak dan dicatat di Audit Log.</li>
      </ul>
    </div>

    <div class="card" style="margin-top:16px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:8px">Cara Panggil dari Aplikasi Klien (API)</h3>
      <p class="muted" style="font-size:12px;line-height:1.5">Kirim HTTP POST multipart form-data dengan header API Key atau Client ID:</p>
      <pre style="background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:10px;font-size:11px;font-family:var(--font-mono);overflow-x:auto;color:var(--text)">
POST /api/v1/storage/upload
Headers:
  X-API-Key: iam_live_...
  (atau X-Client-Id: app_portfolio_admin)
Body (FormData):
  file: &lt;binary_file&gt;
  folder: logos
      </pre>
    </div>
  </div>
</div>

<script>
function copyMediaUrl(elemId) {
  const input = document.getElementById(elemId);
  if (!input) return;
  input.select();
  input.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(input.value).then(() => {
    alert('URL Publik berhasil disalin ke clipboard!');
  }).catch(() => {
    document.execCommand('copy');
    alert('URL Publik berhasil disalin!');
  });
}
</script>
@endsection
