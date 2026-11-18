# WBS, bobot, dan baseline rencana

**Fase 1.** Mengacu ke requirement §4–5 dan §9 serta keputusan K04–K09. Dibangun setelah fondasi proyek dengan masukan pemetaan awal Ferry dan Tug dari paket 05.

## Langkah implementasi

1. Buat pohon WBS per proyek dengan kategori, subkategori, dan item. Simpan kode, nama, volume/satuan, urutan, tanggal rencana, PIC, dan referensi sumber. Gunakan ID stabil dan kode unik dalam proyek. Cegah siklus dan parent beda proyek. Tetapkan simpul mana yang merupakan unit pengukuran agar subtotal parent dapat dihitung dari anak.
2. Tambahkan template WBS yang dapat disalin. Ferry memiliki Pengadaan/Material 73,845%, Jasa Pembangunan 23,403%, Desain 0,727%, dan Jasa Pihak Lain 2,025%. Tug memiliki sembilan kelompok dan metode pengukuran berbeda. Di pengaturan template, pilih tipe user untuk review item dan akun yang boleh membuka halaman WBS. Saat template diterapkan, validasi akun terhadap proyek tujuan dan simpan aturan pada WBS proyek. Pengguna biasa harus punya izin aksi dan keanggotaan proyek sesuai paket 01. Jika daftar akun WBS diisi, pengguna biasa juga harus tercantum di daftar itu.
3. Definisikan satu sumber bobot per tingkat. Usulan menyimpan bobot lokal relatif terhadap parent dan menurunkan bobot proyek sepanjang hierarki. Saat impor, jika sumber memakai bobot proyek langsung, konversi ke representasi internal dan rekonsiliasi hasilnya terhadap agregat sumber. Validasi total 100% dalam proyek dan kelompok berbobot, serta nol, nilai negatif, dan penempatan subtotal. Tentukan toleransi impor dan tampilkan selisih sebelum publikasi.
4. Sediakan metode persentase langsung dan metode tahapan berbobot sesuai K05. Konfigurasikan metode pada item, dengan kategori dan bobot yang bisa berbeda per proyek. Sediakan template awal yang dapat disalin. Konstruksi mendukung subtahapan sesuai mapping, atau bobot efektif setiap tahap setelah pembobotan berlapis.

| Template | Tahapan contoh |
|---|---|
| Material | Pembayaran/pelunasan 80%, on-site 10%, digunakan 10% |
| Konstruksi | Fabrication 35%, Assembly 45%, Erection 20%. Contoh subtahap Fabrication berupa marking, cutting, dan inspection. |
| Pengecatan | Surface Preparation 40%, Primer 20%, Intermediate 15%, Final 15%, Inspeksi 10% |
| Drawing | Diterima/selesai 50%, Owner Approved 25%, Class Approved 12,5%, Government Approved 12,5% |

5. Buat kalender bulanan dan editor target per item dan periode. Usulan menyimpan persentase fisik kumulatif dan menurunkan kenaikan per periode. Periksa 0–100%, urutan periode, target yang menurun, dan target penutupan pekerjaan. Tambahkan baseline legacy pada level agregasi yang disetujui Planner dengan jenis data dan sumber yang eksplisit. Definisikan tampilan untuk level yang belum memiliki target.
6. Buat baseline draft, preview perubahan terhadap baseline aktif, pengajuan, dan persetujuan. Catat bobot, target, alasan, aktor, waktu, dan periode mulai berlaku. Publikasikan baseline terbaru secara atomik untuk periode yang belum punya laporan resmi. Untuk periode dengan laporan resmi, terapkan perubahan melalui alur koreksi paket 03 agar dashboard dan ekspor mengacu pada dasar hitung yang sama.
7. Pertahankan ID untuk item yang sama saat susunan berubah. Tentukan cara memetakan carry-forward bila item digabung, dipecah, atau diarsipkan. Uji dampak perubahan susunan pada hasil resmi periode terkait dan arsip PDF terbit.

## Keluaran dan penerimaan

- Planner dapat menyalin template, memilih metode, tipe reviewer, dan akun pembaca WBS, lalu menetapkan target dan mengajukan baseline. Approver baseline yang ditunjuk dapat menerbitkan pembaruan dengan periode mulai berlaku dan audit.
- WBS dengan bobot ganda pada subtotal, siklus, jumlah bobot salah, tahapan yang tidak memenuhi total, atau jadwal melampaui batas bisnis yang ditetapkan ditandai saat validasi.
- WBS Ferry dan variasi Tug dapat dimodelkan. Baseline berbasis item memberi agregat sesuai bobot. Baseline legacy memberi angka pada granularity yang memang tersedia.
- Keluaran berupa skema WBS, aturan template, kalender dan target, formulir kerja Planner, serta pengujian bobot, akses WBS, dan publikasi baseline.
