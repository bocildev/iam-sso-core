@extends('admin.layout')
@section('title', 'Kamus Istilah')
@php
$terms = [
  'dasar' => [
    'title' => 'Dasar & Konsep Inti',
    'items' => [
      ['IAM (Identity & Access Management)', 'Sistem yang mengatur "siapa kamu" dan "kamu boleh apa". IAM-SSO-CORE adalah sistem itu untuk semua aplikasimu.', 'Seperti resepsionis gedung: mengecek identitas, lalu memberi kartu akses sesuai lantai yang boleh dimasuki.'],
      ['SSO (Single Sign-On)', 'Login satu kali, lalu bisa masuk ke banyak aplikasi tanpa login ulang.', 'Satu kunci induk yang membuka semua pintu apartemenmu.'],
      ['OIDC / OpenID Connect', 'Aturan standar internasional supaya aplikasi bisa meminta IAM memeriksa login pengguna. Karena standar, aplikasi apa pun bisa terhubung.', 'Bahasa resmi yang dipahami semua aplikasi dan IAM.'],
      ['Aplikasi Klien', 'Aplikasi milikmu yang memakai IAM untuk login (misalnya Motion Portfolio, Mochi Finance). Disebut "klien" karena ia yang meminta layanan ke IAM.', 'Toko-toko di dalam mal; IAM adalah satpam mal-nya.'],
      ['Organisasi', 'Kelompok pelanggan/perusahaan. Satu user bisa dikaitkan ke organisasi tertentu untuk tiap aplikasi.', 'Nama perusahaan yang tertera di kartu karyawan.'],
      ['Super Admin', 'Pengguna dengan akses penuh ke konsol IAM ini. Beri hanya ke orang yang benar-benar dipercaya.', 'Pemegang kunci master gedung.'],
      ['sub (Subjek)', 'Nomor identitas unik (UUID) milik tiap user, tidak pernah berubah walau email/nama diganti. Aplikasi klien menyimpan ini untuk mengenali user.', 'Nomor KTP: nama bisa berubah, nomornya tetap.'],
    ],
  ],
  'pendaftaran-aplikasi' => [
    'title' => 'Pendaftaran Aplikasi',
    'items' => [
      ['Client ID', 'Nama pengenal publik sebuah aplikasi di IAM. Boleh diketahui orang; bukan rahasia.', 'Nama toko di papan depan.'],
      ['Client Secret', 'Kata sandi rahasia aplikasi untuk membuktikan dirinya asli. Hanya ditampilkan sekali saat dibuat; simpan di tempat aman dan jangan masukkan ke kode frontend.', 'PIN brankas toko.'],
      ['Public vs Confidential Client', 'Public = aplikasi yang kodenya terlihat pengguna (Nuxt/SPA/mobile), tidak punya secret, diamankan dengan PKCE. Confidential = aplikasi server yang bisa menyimpan secret dengan aman.', 'Public: booth di pameran terbuka. Confidential: kantor tertutup.'],
      ['PKCE', 'Lapisan pengaman tambahan untuk aplikasi tanpa secret: tiap login memakai kode sekali pakai sehingga tidak bisa dibajak di tengah jalan.', 'Stempel unik di setiap tiket sehingga tiket curian tidak berlaku.'],
      ['Redirect URI', 'Alamat halaman aplikasi tempat pengguna dikembalikan setelah login. Harus cocok persis dengan yang didaftarkan, supaya tidak dialihkan ke situs palsu.', 'Alamat pengiriman yang sudah didaftarkan; kurir tidak boleh mengirim ke alamat lain.'],
      ['Post-Logout Redirect URI', 'Alamat tujuan setelah pengguna logout dari aplikasi.', 'Pintu keluar yang ditentukan.'],
      ['CORS (Allowed Origins)', 'Daftar alamat situs web yang diizinkan memanggil IAM lewat browser. Selain daftar ini, browser akan memblokir.', 'Daftar tamu yang boleh masuk lewat gerbang depan.'],
      ['Mode Auth (legacy / oidc / both)', 'Cara login yang dipakai aplikasi: sistem lama, OIDC baru, atau keduanya selama masa migrasi.', 'Masa transisi: pintu lama dan pintu baru dibuka bersamaan.'],
    ],
  ],
  'token-kunci' => [
    'title' => 'Token & Kunci Kriptografi',
    'items' => [
      ['Token', 'Bukti digital bahwa seseorang sudah login. Aplikasi menunjukkan token ke IAM atau API untuk membuktikan identitas.', 'Gelang tanda masuk konser.'],
      ['Access Token', 'Token berumur pendek (15 menit) untuk mengakses data. Isinya identitas, role, dan permission pengguna.', 'Tiket harian.'],
      ['Refresh Token', 'Token berumur panjang untuk meminta access token baru tanpa login ulang. Dipakai sekali lalu diganti (rotasi); kalau dipakai ulang, sistem curiga dan mencabutnya.', 'Kartu langganan untuk menukar tiket harian baru.'],
      ['JWT', 'Format token berupa teks panjang yang bertanda tangan digital sehingga isinya tidak bisa dipalsukan.', 'Surat resmi bermaterai.'],
      ['Signing Key (RS256)', 'Sepasang kunci digital: kunci rahasia (disimpan IAM) untuk menandatangani token, dan kunci publik untuk memeriksa tanda tangan. RS256 adalah nama algoritmanya.', 'Stempel resmi kantor; hanya kantor yang memegang stempelnya, tapi semua orang bisa memeriksa cap-nya.'],
      ['Rotasi Key', 'Mengganti kunci penandatangan dengan yang baru, demi keamanan. Kunci lama tetap bisa dipakai untuk memeriksa token lama sampai kedaluwarsa.', 'Mengganti gembok secara berkala.'],
      ['JWKS', 'Daftar kunci publik yang bisa diambil aplikasi klien untuk memeriksa keaslian token.', 'Buku contoh stempel resmi.'],
      ['Discovery Document', 'Satu alamat yang berisi semua info endpoint IAM. Aplikasi klien cukup diberi alamat ini.', 'Papan petunjuk di lobi gedung.'],
      ['Sesi SSO', 'Catatan bahwa pengguna sedang login di IAM. Selama sesi aktif, pengguna tidak perlu login ulang ke aplikasi lain.', 'Gelang masuk yang berlaku sampai habis masanya.'],
    ],
  ],
  'hak-akses' => [
    'title' => 'Hak Akses & Otorisasi',
    'items' => [
      ['Role', 'Kumpulan hak akses dengan nama yang mudah dipahami, misalnya "Admin" atau "Viewer". Satu aplikasi punya role sendiri.', 'Jabatan: Manajer, Staf, Tamu.'],
      ['Permission', 'Izin spesifik, ditulis resource.aksi, misalnya portfolio.write. Role berisi beberapa permission.', 'Daftar izin di kartu: boleh membaca, boleh menulis, boleh menerbitkan.'],
      ['Scope (pada API Key)', 'Batas apa saja yang boleh dilakukan sebuah API key, misalnya iam:users.read. Tanda * berarti semua.', 'Izin terbatas pada kartu tamu.'],
    ],
  ],
  'integrasi-server' => [
    'title' => 'Integrasi Server & Webhook',
    'items' => [
      ['API Key', 'Kunci rahasia untuk komunikasi server ke server (tanpa pengguna). Hanya ditampilkan sekali dan bisa dicabut kapan saja.', 'Kartu akses khusus kurir.'],
      ['Webhook', 'IAM mengirim kabar otomatis ke aplikasimu saat ada perubahan (user diperbarui, dinonaktifkan, role berubah).', 'Notifikasi pesan otomatis ke ponsel.'],
      ['HMAC / X-IAM-Signature', 'Tanda tangan di setiap webhook supaya aplikasimu yakin pesannya benar-benar dari IAM, bukan palsu.', 'Segel lilin pada surat.'],
      ['Outbox', 'Antrean pesan webhook yang menunggu dikirim, lengkap dengan status berhasil/gagal dan percobaan ulang.', 'Kotak surat keluar.'],
      ['Back-channel Logout', 'Saat pengguna logout dari IAM, IAM memberi tahu aplikasi klien di belakang layar agar ikut menutup sesinya.', 'Satpam mengabari semua toko bahwa tamu sudah keluar.'],
    ],
  ],
  'keamanan-akun' => [
    'title' => 'Keamanan Akun & Perlindungan',
    'items' => [
      ['MFA / Verifikasi Dua Langkah', 'Selain password, login butuh kode tambahan dari ponsel sehingga password bocor pun akun tetap aman.', 'Pintu dengan kunci dan kode PIN.'],
      ['TOTP', 'Kode 6 digit yang berganti tiap 30 detik dari aplikasi authenticator (Google Authenticator, Authy, 1Password).', 'Angka yang selalu berganti di layar token bank.'],
      ['Recovery Code', 'Kode cadangan sekali pakai kalau ponselmu hilang. Simpan di tempat aman dan jangan di ponsel yang sama.', 'Kunci cadangan yang disimpan di laci.'],
      ['Audit Log', 'Catatan permanen semua kejadian penting (login, gagal login, perubahan akses). Hanya bisa dibaca, tidak bisa diubah.', 'Rekaman CCTV dan buku tamu.'],
      ['Brute Force', 'Serangan menebak password berkali-kali. IAM membatasi percobaan login untuk mencegahnya.', 'Maling mencoba semua kombinasi gembok.'],
      ['Social Login (Google OAuth)', 'Masuk ke aplikasi hanya dengan 1-klik memakai akun Google Anda tanpa perlu membuat password baru.', 'Masuk ke gedung menggunakan kartu identitas resmi mitra yang sudah terpercaya.'],
      ['Google Client ID & Secret', 'Nomor tanda pengenal dan kata sandi rahasia yang diterbitkan Google Cloud Console agar Google mengenali IAM Anda saat meminta verifikasi login.', 'Nomor kemitraan resmi antara toko Anda dan pihak Google.'],
    ],
  ],
  'media-storage' => [
    'title' => 'Penyimpanan Media & Dokumen',
    'items' => [
      ['Centralized Media Engine', 'Gudang terpusat untuk menyimpan semua gambar dan berkas dokumen dari berbagai aplikasi klien di satu tempat.', 'Gudang logistik bersama; toko cabang tidak perlu sewa ruko gudang sendiri-sendiri.'],
      ['Auto-Compression Gambar', 'Penyusutan otomatis ukuran file gambar yang melebihi batas 500 KB agar cepat dimuat di web tanpa merusak ketajaman visual.', 'Pengepakan vakum baju agar muat rapi di koper.'],
      ['MIME Type', 'Label pengenal standar internet yang menyatakan jenis isi file (contoh: image/jpeg untuk foto, application/pdf untuk dokumen).', 'Label stiker jenis paket: "Makanan Cepat Saji" atau "Surat Dokumen Rahasia".'],
      ['Multipart Form-Data', 'Metode pengiriman berkas digital dari formulir web atau aplikasi ke server secara bertahap dan terpotong-potong.', 'Mengirim paket berukuran besar lewat mobil box kurir khusus berkas.'],
    ],
  ],
  'server-debugger' => [
    'title' => 'Pemeliharaan Server & Debugger',
    'items' => [
      ['Web Debugger / Artisan Runner', 'Fitur di konsol admin untuk menjalankan tugas teknis server (seperti migrasi dan hapus cache) langsung dari browser web tanpa terminal SSH.', 'Remote kontrol pintar untuk menyalakan atau merawat mesin dari kejauhan tanpa harus membuka kap mesin mobil.'],
      ['Database Migration (Migrate)', 'Proses otomatis untuk membuat, mengubah, atau memperbarui struktur tabel dan kolom di database.', 'Mandor bangunan yang menata denah kamar dan partisi baru sesuai blueprint arsitek.'],
      ['Database Seeder', 'Perintah untuk mengisi tabel database dengan data permulaan wajib (seperti akun Super Admin dan pendaftaran aplikasi resmi).', 'Menata furnitur pertama dan memberikan kunci master saat gedung baru selesai dibangun.'],
      ['Storage Symlink', 'Jalan pintas (shortcut) yang menghubungkan folder penyimpanan berkas internal ke folder web publik agar gambar/dokumen bisa diakses browser.', 'Jembatan penyeberangan khusus dari gudang barang privat langsung ke etalase toko depan.'],
      ['Optimize Clear', 'Perintah untuk menghapus seluruh file cache konfigurasi, rute URL, dan tampilan Blade agar perubahan kode langsung terbaca segar.', 'Menyapu bersih papan tulis sekolah agar catatan pelajaran lama hilang dan catatan baru terlihat jelas.'],
    ],
  ],
];
@endphp
@section('admin')
<div class="page-head">
  <div>
    <h1>Kamus Istilah IAM &amp; SSO</h1>
    <p>Penjelasan bahasa sehari-hari untuk semua istilah teknis di IAM-SSO-CORE, lengkap dengan perumpamaan sederhana.</p>
  </div>
</div>

<div class="admin-layout">
  {{-- KOLOM UTAMA --}}
  <div class="admin-main">
    @foreach($terms as $id => $category)
      <div class="card" id="{{ $id }}">
        <div class="card-header">
          <h2 class="card-title">{{ $category['title'] }}</h2>
          <x-badge variant="neutral" :dot="false">{{ count($category['items']) }} istilah</x-badge>
        </div>
        @foreach($category['items'] as [$term, $desc, $analogy])
          <div class="glossary-term">
            <h3>{{ $term }}</h3>
            <p>{{ $desc }}</p>
            <p class="glossary-analogy">Perumpamaan: {{ $analogy }}</p>
          </div>
        @endforeach
      </div>
    @endforeach
  </div>

  {{-- KOLOM SAMPING (ASIDE / PANDUAN) --}}
  <div class="admin-aside">
    <x-help key="glossary_nav" title="Panduan Membaca Kamus">
      <p>Kamus ini dibuat khusus agar Anda tidak perlu bingung saat membaca istilah teknis di aplikasi ini.</p>
      <ul>
        <li>Setiap istilah memiliki <strong>definisi sederhana</strong> (apa fungsinya).</li>
        <li>Setiap istilah dilengkapi <strong>perumpamaan</strong> sehari-hari (seperti resepsionis, gembok, stempel, dll.) agar langsung terbayang di kepala.</li>
      </ul>
    </x-help>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title" style="font-size:14px">Daftar Kategori</h3>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:13px">
        @foreach($terms as $id => $category)
          <a href="#{{ $id }}" style="display:flex;justify-content:space-between;align-items:center;padding:6px 8px;border-radius:6px;background:var(--bg-subtle);text-decoration:none">
            <span>{{ $category['title'] }}</span>
            <span class="muted" style="font-size:11.5px">{{ count($category['items']) }}</span>
          </a>
        @endforeach
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title" style="font-size:14px">💡 Tips Cepat</h3>
      </div>
      <p class="muted" style="font-size:12.5px;margin:0;line-height:1.6">
        Jika menemukan istilah baru di halaman mana pun yang belum Anda pahami, Anda bisa mencarinya di kamus ini menggunakan <code>Ctrl + F</code> pada browser.
      </p>
    </div>
  </div>
</div>
@endsection
