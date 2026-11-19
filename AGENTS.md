# Monitoring Galangan

- Sebelum mulai mengerjakan prompt terkait proyek, jalankan `graphify update .` dari root repositori. Jika CLI belum tersedia, pasang dengan `uv tool install graphifyy` (atau `python -m pip install graphifyy`). Gunakan graf di `graphify-out/` untuk menelusuri kode.
- Gunakan PHP 8.4, Laravel 13, MySQL 8.4, Inertia, React/TypeScript, dan Node.js 22.
- Utamakan istilah bahasa Indonesia saat menamai tabel dan kolom database.
- Saat menambah fitur atau mengubah aksi, perbarui katalog dan izin awal di App\Services\Access. Halaman hak akses memakai katalog tersebut. Sertakan pengujian otorisasi untuk peran yang diizinkan, peran yang ditolak, dan akses lintas organisasi.
- Pakai kebijakan yang sama untuk web, API, dan unduhan. Ikat mutasi ke organisasi serta proyek yang berwenang, dan catat perubahan bisnis melalui audit dalam transaksi.
- Lihat plan/README.md untuk urutan paket dan docs/setup-windows.md untuk setup lokal.

<!-- postman-cli:installation:start -->
Postman CLI is installed on this device, and can be used for API Engineering work.
<!-- postman-cli:installation:end -->
