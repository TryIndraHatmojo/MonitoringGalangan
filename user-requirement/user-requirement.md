# USER REQUIREMENT
## Sistem Monitoring Progres Pekerjaan dan Biaya Pembangunan Kapal

**Status:** Draft Awal  
**Referensi:** Laporan Progres Ferry Ro-Ro 1500 GT – 28 Oktober 2026

### 1. Latar Belakang

Dibutuhkan sebuah aplikasi berbasis web untuk memantau pelaksanaan proyek pembangunan kapal secara terpusat.

Aplikasi diharapkan dapat menggantikan atau mengurangi proses pelaporan manual menggunakan Excel, serta membantu manajemen, tim proyek, dan pemilik kapal dalam memonitor progres pekerjaan, kesesuaian jadwal, dan realisasi biaya.

### 2. Tujuan Aplikasi

1. Memantau persentase progres pembangunan kapal secara keseluruhan dan per pekerjaan.
2. Membandingkan persentase rencana (planned progress) dan aktual (actual progress).
3. Menampilkan S-Curve untuk mengetahui perkembangan proyek terhadap jadwal.
4. Memantau anggaran dan realisasi biaya pembangunan kapal.
5. Mengetahui pekerjaan yang mengalami keterlambatan atau deviasi.
6. Mempermudah penyusunan laporan progres berkala.
7. Menyediakan data dan riwayat pekerjaan sebagai bahan evaluasi manajemen.

### 3. Master Data Proyek

Sistem menyediakan pengelolaan data proyek pembangunan kapal.

**Informasi proyek:**
- Nomor dan nama proyek
- Nama kapal, jenis kapal, dan GT
- Nama pemilik kapal (Owner)
- Nama galangan (Shipyard)
- Tanggal mulai dan target penyelesaian
- Nilai kontrak dan anggaran proyek
- Penanggung jawab / Project Manager
- Status proyek
- Dokumen kontrak dan lampiran pendukung

Sistem harus mendukung pengelolaan lebih dari satu proyek kapal.

### 4. Struktur Pekerjaan (Work Breakdown Structure / WBS)

Sistem mendukung pembagian pekerjaan secara hierarkis, mengikuti struktur referensi laporan.

| Kode | Kategori utama | Bobot contoh |
|---|---|---:|
| A | Pengadaan / Material | 73,845% |
| B | Jasa Pembangunan | 23,403% |
| C | Desain, Gambar Kerja & Final Drawing | 0,727% |
| D | Jasa Pihak Lain | 2,025% |
| | **Total** | **100%** |

Masing-masing kategori dapat memiliki subkategori, item pekerjaan, dan tahapan pekerjaan.

**Contoh detail Jasa Pembangunan:**
- Konstruksi lambung per block
- Perlengkapan lambung dan geladak
- Interior ruangan
- Pengecatan dan perlindungan karat
- Instalasi mesin induk, mesin bantu, dan mesin geladak
- Instalasi perpipaan
- Instalasi kabel dan panel listrik
- Pemeriksaan, pengujian, Dock Trial, dan Sea Trial

**Data pada setiap pekerjaan:**
- Kode dan nama pekerjaan
- Kategori dan subkategori
- Volume dan satuan
- Bobot terhadap kelompok dan keseluruhan proyek
- Tanggal rencana mulai dan selesai
- PIC / penanggung jawab
- Persentase rencana dan aktual
- Status pekerjaan
- Keterangan, kendala, dan dokumen pendukung

### 5. Modul Perencanaan Pekerjaan

Sistem memungkinkan Project Manager atau Planner menyusun jadwal dan target progres pekerjaan.

**Kebutuhan fungsional:**
- Input rencana pekerjaan berdasarkan periode.
- Input dan penyesuaian bobot setiap pekerjaan.
- Menentukan target progres tiap bulan.
- Membuat baseline rencana yang digunakan dalam perhitungan S-Curve.
- Membandingkan rencana awal dengan revisi jadwal.
- Menyimpan riwayat revisi baseline tanpa menghapus data sebelumnya.

Periode standar adalah **bulanan**, mengikuti laporan Excel. Monitoring mingguan dapat dikembangkan apabila diperlukan.

### 6. Modul Monitoring Progres Pekerjaan

Sistem menyediakan halaman untuk memperbarui realisasi pekerjaan.

**Fitur utama:**
- Input progres aktual setiap item pekerjaan.
- Input tanggal dan periode pelaporan.
- Memantau progres bulan sebelumnya, bulan berjalan, dan kumulatif.
- Upload foto pekerjaan, hasil inspeksi, dan dokumen.
- Mengisi keterangan dan kendala di lapangan.
- Melihat riwayat perubahan progres.
- Review dan approval progres sebelum ditampilkan sebagai data resmi.

**Metode perhitungan progres harus fleksibel berdasarkan jenis pekerjaan.**

Contoh berdasarkan laporan referensi:

| Jenis pekerjaan | Tahapan dan bobot |
|---|---|
| Material | Pembayaran/pelunasan 80%, on-site 10%, digunakan 10% |
| Konstruksi | Fabrication 35%, Assembly 45%, Erection 20% |
| Pengecatan | Surface Preparation 40%, Primer 20%, Intermediate 15%, Final 15%, Inspeksi 10% |
| Drawing | Selesai/diterima galangan 50%, Owner Approved 25%, Class Approved 12,5%, Government Approved 12,5% |

Bobot tahapan dapat dikonfigurasi sesuai kebutuhan masing-masing item atau proyek.

**Catatan:** Bobot pembayaran material dalam referensi digunakan sebagai bagian pengukuran progres. Data tersebut tidak boleh otomatis dianggap sebagai jumlah pengeluaran aktual.

### 7. Dashboard Monitoring Proyek

Dashboard menampilkan informasi utama proyek secara visual dan mudah dipahami.

**A. Ringkasan Proyek (KPI Cards)**

Menampilkan:
- Persentase rencana kumulatif
- Persentase aktual kumulatif
- Deviasi progres (+/-)
- Kenaikan progres periode berjalan
- Jumlah pekerjaan selesai, berjalan, dan terlambat
- Anggaran proyek
- Realisasi biaya dan sisa anggaran

**B. Grafik S-Curve**

Dashboard wajib menyediakan grafik garis dengan:
- Sumbu X: periode waktu (bulan/tanggal).
- Sumbu Y: persentase kumulatif (0–100%).
- Garis 1: Planned Progress (%).
- Garis 2: Actual Progress (%).
- Tooltip berisi nilai rencana, aktual, dan deviasi.
- Filter berdasarkan proyek, periode, dan kategori pekerjaan.
- Rencana masa depan tetap ditampilkan, sedangkan aktual hanya sampai periode laporan terakhir.

**C. Ringkasan Progres per Kategori**

Tabel atau grafik yang memperlihatkan progres Pengadaan Material, Jasa Pembangunan, Desain, dan Jasa Pihak Lain.

**D. Monitoring Biaya**

Grafik anggaran versus biaya aktual, beserta ringkasan biaya per kategori pekerjaan.

**E. Pekerjaan Bermasalah**

Daftar pekerjaan yang terlambat, memiliki deviasi negatif, membutuhkan approval, atau memiliki kendala yang belum diselesaikan.

### 8. Modul Monitoring Biaya

Modul biaya merupakan kebutuhan tambahan karena laporan progres referensi belum menyediakan rincian biaya nominal.

**A. Perencanaan Anggaran**
- Input anggaran total proyek.
- Pembagian anggaran berdasarkan kategori dan WBS.
- Input estimasi biaya material, jasa, tenaga kerja, subkontraktor, dan biaya lainnya.
- Revisi anggaran dengan histori persetujuan.

**B. Realisasi Biaya**
- Pencatatan biaya berdasarkan tanggal transaksi.
- Pengaitan transaksi dengan kategori dan item pekerjaan.
- Pencatatan supplier/vendor.
- Nomor Purchase Order, kontrak, atau invoice.
- Nominal biaya, status tagihan, dan pembayaran.
- Upload bukti transaksi.

**C. Monitoring dan Analisis**
- Total anggaran proyek.
- Nilai kontrak/PO yang sudah disetujui.
- Biaya aktual yang telah diakui.
- Total pembayaran.
- Biaya yang belum dibayarkan.
- Sisa anggaran dan selisih anggaran terhadap realisasi.
- Estimasi biaya sampai proyek selesai.

**D. Visualisasi Biaya**

Dashboard menyediakan grafik:
- Budget vs Actual Cost.
- Tren biaya bulanan.
- Distribusi biaya per kategori.
- Perbandingan progres fisik dengan penyerapan biaya.

Biaya aktual, nilai komitmen kontrak, dan uang yang telah dibayarkan harus dihitung terpisah agar tidak terjadi pencatatan ganda.

### 9. Aturan Perhitungan

**A. Kontribusi Progres Item**

Kontribusi item terhadap keseluruhan proyek:

`Bobot Item (%) × Progres Fisik Item (%) / 100`

**B. Aktual Kumulatif Proyek**

`SUM(Kontribusi seluruh item pekerjaan yang disetujui)`

**C. Rencana Kumulatif**

Akumulasi target progres berbobot sampai periode laporan.

**D. Deviasi Progres**

`Aktual Kumulatif − Rencana Kumulatif`

Hasil disajikan dalam poin persentase (pp).

**E. Kenaikan Progres Periode**

`Aktual Kumulatif Sekarang − Aktual Kumulatif Periode Sebelumnya`

**F. Sisa Anggaran**

`Anggaran Disetujui − Biaya Aktual`

Sistem perlu menampilkan pembulatan angka secara konsisten dan menggunakan angka presisi untuk perhitungan.

### 10. Modul Laporan

Aplikasi menyediakan pembuatan laporan yang dapat diekspor dalam format PDF dan Excel.

**Jenis laporan:**
- Resume Progres Proyek
- S-Curve Overall Project
- S-Curve Material / Procurement
- S-Curve Jasa Pembangunan
- S-Curve Drawing / Engineering
- S-Curve Jasa Pihak Lain
- Detail Progres Pekerjaan
- Laporan Biaya dan Anggaran
- Laporan Kendala dan Deviasi
- Rekap Periode Proyek

Laporan memuat nama proyek, nomor proyek, periode, ringkasan progres, penanggung jawab, serta bagian persetujuan apabila dibutuhkan.

### 11. Pengguna dan Hak Akses

| Role | Kewenangan utama |
|---|---|
| Administrator | Kelola pengguna, proyek, dan konfigurasi |
| Project Manager | Mengatur proyek, memantau dan menyetujui progres |
| Planner / Engineering | Mengelola WBS, bobot, dan baseline rencana |
| PIC Lapangan | Menginput progres, kendala, dan dokumentasi |
| Procurement | Mengelola pengadaan dan data kontrak pembelian |
| Finance | Mengelola anggaran, biaya, invoice, dan pembayaran |
| Owner / Surveyor | Melihat laporan dan melakukan verifikasi sesuai kewenangan |
| Management / Viewer | Monitoring dashboard dan laporan sesuai hak akses |

### 12. Alur Kerja

1. Admin membuat proyek kapal.
2. Planner memasukkan struktur pekerjaan, bobot, serta rencana progres.
3. Project Manager menyetujui baseline proyek.
4. PIC menginput realisasi pekerjaan dan bukti pendukung secara berkala.
5. Supervisor/Project Manager memverifikasi progres.
6. Sistem menghitung ulang progres kumulatif dan memperbarui dashboard S-Curve.
7. Finance memperbarui data anggaran dan realisasi biaya secara terpisah.
8. Management memonitor dashboard dan mengunduh laporan yang telah disetujui.

Status laporan: **Draft → Submitted → Reviewed → Approved**, dengan opsi dikembalikan untuk revisi.

### 13. Persyaratan Sistem

- Aplikasi berbasis web dan responsif.
- Mendukung multi-project.
- Hak akses berdasarkan peran.
- Perhitungan progres otomatis dari data detail.
- Riwayat perubahan dan audit log.
- Penyimpanan foto dan dokumen pendukung.
- Validasi total bobot proyek sebesar 100%.
- Data laporan lama tetap tersedia setelah periode berjalan berganti.
- Perubahan pada periode yang telah disetujui harus melalui proses revisi.
- Backup data secara berkala.
- Impor awal master pekerjaan dan data progres dari Excel.

### 14. Prioritas Pengembangan

**Fase 1 — MVP (Prioritas Utama)**
1. Login dan hak akses.
2. Master proyek dan WBS.
3. Pengelolaan bobot serta rencana pekerjaan.
4. Input dan approval progres aktual.
5. Dashboard S-Curve Planned vs Actual.
6. Resume progres per kategori dan pekerjaan.
7. Laporan PDF/Excel.

**Fase 2 — Monitoring Biaya**
1. Master anggaran dan biaya per item pekerjaan.
2. Pencatatan PO, invoice, dan realisasi biaya.
3. Dashboard biaya dan analisis selisih anggaran.
4. Estimasi biaya akhir proyek.

**Fase 3 — Pengembangan Lanjutan**
1. Monitoring mingguan dan notifikasi keterlambatan.
2. Milestone dan Gantt Chart.
3. Approval dokumen secara digital.
4. Dashboard lintas proyek.
5. Integrasi dengan sistem procurement atau accounting.

### 15. Kriteria Keberhasilan / Acceptance Criteria

- Pengguna dapat membuat proyek beserta struktur pekerjaan dan bobotnya.
- Sistem dapat menghitung progres proyek otomatis dari progres detail.
- Dashboard menampilkan dua garis S-Curve berdasarkan rencana dan aktual.
- Nilai deviasi sesuai perhitungan dari laporan sumber.
- Dashboard dapat difilter menurut kategori dan periode.
- Perubahan progres yang sudah disetujui memiliki histori dan alasan revisi.
- Transaksi biaya dapat ditelusuri sampai ke dokumen sumber dan WBS terkait.
- Data dashboard konsisten dengan hasil ekspor laporan.
- Untuk data uji referensi 28 Oktober 2026, sistem dapat menghasilkan rencana 30,693%, aktual 50,065%, dan deviasi +19,372 poin persentase.

### 16. Hal yang Perlu Dikonfirmasi dengan User

1. Apakah laporan akan diperbarui harian, mingguan, atau bulanan?
2. Apakah input progres dilakukan per tahapan pekerjaan atau cukup persentase per item?
3. Apakah bobot pekerjaan dan metode perhitungannya mengikuti Excel secara persis, atau bisa diatur ulang?
4. Apakah biaya yang dimonitor mencakup pembelian, invoice, pembayaran, atau seluruhnya?
5. Siapa yang berwenang menyetujui progres dan perubahan anggaran?
6. Apakah dashboard hanya digunakan pihak galangan, atau juga dapat diakses Owner/Surveyor?
7. Apakah terdapat sistem existing yang perlu diintegrasikan?

---

**Catatan:** Dokumen ini merupakan draft kebutuhan awal berdasarkan laporan progres yang dilampirkan. Modul monitoring biaya, pengelolaan pengguna, dan beberapa fitur lanjutan adalah usulan untuk dikonfirmasi dalam pembahasan kebutuhan pengguna.