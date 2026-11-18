# Fondasi web, master proyek, dan akses

**Fase 1.** Mengacu ke requirement §3, §11, dan §13. Keputusan awal K01, K02, K08, K14.

## Langkah implementasi

1. Gunakan Laravel, Inertia, React/TypeScript, Tailwind, dan MySQL. Tetapkan versi runtime yang kompatibel saat bootstrap dan catat topologi hosting setelah IT mengonfirmasinya. Bentuk satu aplikasi modular dengan domain proyek/akses, WBS, progres, laporan, dan biaya. Sajikan UI web dan API dari domain yang sama. Siapkan migrasi, konfigurasi dev/staging/produksi, fixtures development, dan pipeline dasar lint/build/test.
2. Implementasikan login lokal dan pengelolaan akun. Sediakan reset sandi, pembatasan percobaan login, pencabutan sesi dan token perangkat, serta administrasi status akun. Tentukan akun Administrator awal dan proses serah terima.
3. Buat master proyek dengan nomor/nama proyek, nama/jenis/GT kapal, owner, galangan, tanggal mulai/target, nilai kontrak, Project Manager, status, dan dokumen. Tampilkan ringkasan anggaran dari modul biaya pada paket 06. Definisikan keunikan nomor proyek sesuai batas organisasi. Gunakan tanggal bisnis dan nominal presisi tetap. Sediakan arsip atau penutupan proyek dengan akses histori.
4. Buat keanggotaan proyek, penugasan PIC, dan halaman hak akses untuk mengatur izin aksi per tipe user. Administrator memiliki akses penuh dan tindakannya tetap tercatat dalam audit. Bagi pengguna lain, tindakan memerlukan izin aksi dan cakupan proyek. Akses detail WBS juga mengikuti pilihan akun pada paket 02. Terapkan kebijakan yang sama pada daftar, detail, tindakan, unduhan, dashboard, ekspor, dan API. Jika ada beberapa galangan independen, cakup keunikan, query, dan file dalam konteks organisasi.
5. Simpan lampiran kontrak, dokumen proyek, bukti progres, serta bukti biaya di disk lokal privat. Validasi jenis dan ukuran, kaitkan dengan entitas dan proyek, serta cek izin saat unduh. Catat kebijakan retensi dan bersihkan file sementara bila unggahan atau transaksi gagal.
6. Buat audit event untuk perubahan master, izin, publikasi, koreksi, impor, dan aksi biaya. Simpan aktor, aksi, waktu, proyek, ID target, serta alasan dan perubahan penting. Tulis audit aksi bisnis bersama transaksi perubahan status. Batasi isi audit pada metadata dan perubahan bisnis yang diperlukan.
7. Implementasikan navigasi responsif dengan pemilih proyek, akses berdasarkan peran, halaman daftar/detail, formulir yang menunjukkan validasi, dan kondisi kosong/memuat/gagal. Siapkan satu pengaturan format jumlah/tanggal untuk dipakai oleh formulir dan laporan.
8. Tambahkan `AGENTS.md` di akar proyek saat bootstrap. Instruksikan agar setiap fitur baru atau perubahan aksi memperbarui daftar izin yang digunakan halaman hak akses dan pengujian otorisasinya.

## Matriks izin awal

| Peran | Kewenangan untuk dikonfirmasi |
|---|---|
| Administrator | Akses penuh untuk seluruh proyek, data, dan aksi administratif maupun bisnis |
| Project Manager | Pengaturan proyek yang ditugaskan dan persetujuan baseline atau laporan progres sesuai penugasan |
| Planner / Engineering | WBS, bobot, metode pengukuran, target, dan pengajuan baseline |
| PIC Lapangan | Draft progres dan bukti untuk pekerjaan yang ditugaskan, pengajuan sesuai alur |
| Supervisor / Reviewer | Review item yang ditugaskan sesuai pengaturan template WBS dan pengembalian progres dengan alasan |
| Procurement | Vendor, pengadaan, dan komitmen pada Fase 2 sesuai izin |
| Finance | Anggaran, realisasi, dan bukti pada MVP. Invoice dan pembayaran pada Fase 2 sesuai izin. |
| Owner / Surveyor | Laporan proyek yang ditugaskan, dengan izin terpisah untuk verifikasi dan biaya |
| Management / Viewer | Dashboard dan laporan proyek yang diizinkan, dengan akses biaya sesuai izin |

## Keluaran dan penerimaan

- Halaman hak akses mengubah izin per tipe user, termasuk izin membuka kembali data resmi. Daftar izin dapat ditelusuri ke aksi dan pengujian sesuai ketentuan `AGENTS.md`.
- Administrator dapat mengakses dua proyek dengan keanggotaan berbeda dan melihat audit perubahan izin. Untuk pengguna biasa, uji kombinasi tipe user, keanggotaan proyek, penugasan, dan izin WBS.
- Pengujian mencakup URL langsung, dashboard, unduhan, ekspor, dan API. Akun nonaktif kehilangan akses sesi web dan token perangkat.
- UI proyek dan formulir pokok dapat dipakai pada viewport desktop dan ponsel sebelum modul domain berikutnya dipasang.
