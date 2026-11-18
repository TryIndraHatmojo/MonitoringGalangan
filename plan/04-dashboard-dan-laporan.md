# Dashboard, S-Curve, dan laporan

**MVP untuk progres dan biaya dasar.** Mengacu ke requirement §7, §9–10, dan §15 serta keputusan K03, K06, K08, K10, dan K11. Dependensi 02–03 serta 06 untuk data biaya.

## Dashboard progres

1. Buat layanan ringkasan dengan input proyek, periode atau rentang, dan kategori. Gunakan hasil resmi terbaru untuk tiap periode dan dasar hitung yang terkait. Keluarkan tanggal cutoff, rencana dan aktual kumulatif, deviasi dalam poin persentase, kenaikan berdasarkan pembanding yang dipakai saat approval, status pekerjaan, serta data masalah. Gunakan sumber dan satuan yang sama di web, API, PDF, dan Excel.
2. Sajikan S-Curve garis planned dan actual, sumbu bulan, skala 0–100%, serta tooltip berisi periode, rencana, aktual, dan deviasi. Tampilkan rencana terbaru yang sudah disetujui untuk sisa horizon setelah cutoff aktual. Untuk periode tanpa data aktual, gunakan nilai kosong dan biarkan grafik terputus pada titik itu. Tampilkan carry-forward sesuai hasil resmi periode terkait.
3. Sajikan ringkasan kategori dengan bobot proyek, progres fisik kategori, dan kontribusi proyek. Progres kategori menggunakan kontribusi kategori dibagi bobot kategori, dikali 100, saat bobot positif. Contoh Ferry Oktober memperlihatkan Material sekitar 55,811% terhadap kategori dan 41,214 pp terhadap proyek. Tentukan data yang ditampilkan saat bobot nol atau baseline detail belum tersedia. [Ferry, sheet Resume baris 13–18](<../referensi data/9. Laporan Progres Ferry 1500 GT_28-10-26.xlsx>)
4. Tampilkan KPI rencana kumulatif, aktual kumulatif, deviasi, kenaikan, dan jumlah pekerjaan selesai, berjalan, serta terlambat. Daftar pekerjaan bermasalah menautkan keterlambatan, deviasi negatif, approval tertunda, dan kendala terbuka ke detail sesuai akses. Tambahkan kondisi loading, kosong, gagal, dan layout responsif.
5. Pada MVP, tampilkan anggaran, realisasi, dan sisa per WBS dan mata uang berdasarkan paket 06. Terapkan izin biaya, proyek, dan cakupan WBS. Fase 2 menambah ringkasan komitmen, invoice, pembayaran, dan estimasi akhir serta analisis tren dan penyerapan.

## PDF dan Excel

1. Implementasikan katalog laporan MVP untuk resume progres, S-Curve proyek dan kategori, detail progres, kendala/deviasi, rekap periode, serta ringkasan anggaran dan realisasi. Gunakan kategori proyek agar Ferry dan Tug dapat memakai kelompoknya masing-masing. Fase 2 menambah rincian transaksi biaya.
2. Pakai Bahasa Indonesia dan peta kolom Ferry. Sertakan cover atau ringkasan, S-Curve, tabel detail, identitas proyek, cutoff, penanggung jawab, dan blok persetujuan sesuai template yang disahkan. Gunakan sel numerik di Excel untuk persentase dan nominal serta cantumkan mata uang. Samakan pembulatan kedua format dengan dashboard.
3. Buat ekspor baru dari hasil resmi terbaru dengan filter dan izin yang sama dengan dashboard. Untuk ekspor besar, gunakan job dengan status dan tautan unduh privat. Simpan PDF yang sudah terbit sebagai file arsip privat, dengan metadata proyek, periode, cutoff, cakupan isi, dan waktu terbit. Sediakan unduhan file asli setelah memastikan izin saat ini mencakup isinya.

## Keluaran dan penerimaan

- Filter proyek, periode, dan kategori berfungsi pada S-Curve dan tabel. Data rencana setelah 28 Oktober tampil saat tersedia, sementara aktual setelah cutoff kosong sampai ada snapshot resmi.
- Untuk Ferry Oktober, dashboard, PDF, Excel, dan API baca yang dirilis menampilkan rencana 30,693%, aktual 50,065%, deviasi +19,372 pp, dan kenaikan +0,926 pp pada filter dan hasil resmi yang sama. Uji kecocokan nilai sebelum format dan setelah pembulatan tiga desimal.
- Setelah koreksi disetujui, dashboard dan ekspor baru menyajikan hasil resmi terbaru. Unduhan arsip menyajikan PDF terbit dengan angka asli. Uji penolakan akses di luar izin proyek, WBS, atau biaya.
- Laporan diuji dengan judul panjang dan banyak baris. PDF memiliki page break yang dapat dibaca dan Excel memuat angka, label, periode, serta unit yang konsisten.
