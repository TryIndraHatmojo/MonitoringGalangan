# Pengujian, rilis, dan operasi

**Dimulai sejak fondasi dan diterapkan per rilis.** Mengacu ke requirement §13–15 dan hasil konfirmasi paket 00. MVP web mencakup progres, biaya dasar, dan ekspor PDF/Excel. Serah terima API baca mengikuti kesiapan kontrak dan staging bersama tim Android.

## Pengujian dan UAT

| Area | Bukti yang harus tersedia sebelum rilis terkait |
|---|---|
| Fondasi dan izin | Administrator punya akses penuh dan dapat mengubah izin per tipe user. Uji anggota proyek, Owner/Surveyor, Finance, akun nonaktif, akses di luar proyek, serta pilihan akun pembaca WBS melalui web dan API. Periksa izin di unduhan dan ekspor. |
| WBS dan baseline | Uji total bobot 100%, subtotal, variasi Ferry dan Tug, penugasan reviewer, publikasi baseline terbaru, serta koreksi pada periode yang sudah resmi. |
| Progres dan persetujuan | Uji persentase langsung dan tahapan, nilai kosong atau nol, carry-forward, cutoff, penurunan, dan pengembalian. Gunakan satu laporan dengan dua item yang memiliki tipe reviewer berbeda. Pastikan satu approver proyek menyetujui setelah review wajib selesai. Uji izin membuka kembali, audit koreksi, dan retry atau approval bersamaan. |
| Impor | Uji preview, pemetaan sumber, konflik, retry batch, rekonsiliasi sebelum commit, serta pengajuan koreksi saat file memperbarui periode resmi. |
| Dashboard dan ekspor | Rekonsiliasi Ferry dengan rencana 30,693%, aktual 50,065%, deviasi +19,372 pp, dan kenaikan +0,926 pp. Cocokkan dashboard, PDF, Excel, dan API yang dirilis pada filter yang sama. Uji koreksi yang memperbarui periode sasaran dan unduhan PDF terbit dengan angka asli. |
| Biaya MVP | Uji pemisahan persentase, kuantitas, dan nominal, serta anggaran, realisasi, dan sisa dalam dua mata uang. Cocokkan dashboard, PDF, dan Excel dengan sumber Finance. Uji cakupan izin sampai bukti. |
| Biaya Fase 2 | Uji PO, invoice, pembayaran parsial, alokasi, koreksi, konversi mata uang, rekonsiliasi, dan estimasi akhir. |
| API | Uji OpenAPI dan Postman di staging. Cakup status token per perangkat, izin proyek/WBS, file privat, retry pembacaan, dan kompatibilitas klien. Uji konflik dan retry mutasi saat API tulis dirilis. |
| Operasi | Deployment ke staging, backup dan restore database beserta lampiran, worker/scheduler, pengukuran dashboard/ekspor/impor sesuai volume target, serta prosedur rollback yang berhasil diuji. |

1. Pilih dataset tersanitasi atau data referensi yang disetujui. Planner memvalidasi cakupan impor dan konflik sumber sebelum pilot. Finance menyediakan data biaya resmi atau teranonimkan sebelum rekonsiliasi MVP. Siapkan akun uji per tipe user, fixture WBS multi-proyek, dan hasil hitung sumber.
2. Sebelum UAT, PM menetapkan penanggung jawab penerimaan tiap modul. PM dan IT menyepakati target respons, kapasitas, batas unggah, retensi, dan pemulihan berdasarkan pilot. Libatkan Planner, PIC, reviewer, approver proyek, Finance, serta Management/Owner sesuai kewenangan. Tim Android meninjau kontrak dan menguji API baca di staging sebelum serah terima.
3. Terima MVP web setelah alur end-to-end, rekonsiliasi Ferry dan Finance, izin, ekspor PDF/Excel, dan pemulihan di staging lolos. Simpan bukti pengujian, persetujuan modul, serta tindak lanjut untuk defek dan risiko yang masih terbuka.

## Deployment dan pemeliharaan

1. IT menetapkan hosting, domain, dan akses API dari luar galangan sebelum deployment. Siapkan lingkungan dev, staging, dan produksi dengan HTTPS, MySQL, disk lokal privat, SMTP untuk kebutuhan akun lokal, worker, scheduler, dan pengelolaan rahasia. Catat provisioning dan rotasi kredensial.
2. Tentukan proses deployment, migrasi skema, migrasi dan rekonsiliasi data awal, pemeriksaan kesehatan, serta strategi rollback. Uji kemampuan kembali ke versi aplikasi yang kompatibel dengan skema dan data. Tetapkan prosedur pemulihan sebelum migrasi yang mengubah format data produksi.
3. Jadwalkan backup terkoordinasi untuk database dan file. Tetapkan lokasi salinan, retensi, siapa yang dapat restore, target kehilangan data dan waktu pemulihan. Jalankan simulasi restore dan verifikasi pasangan snapshot database dengan lampiran serta laporan resmi.
4. Pantau kesehatan aplikasi, durasi request, error, job tertunda/gagal, kapasitas file, backup, dan peristiwa impor/ekspor. Gunakan request ID untuk menghubungkan error API dengan log. Buat panduan tindakan untuk laporan gagal, lampiran hilang, pergantian periode, dan perubahan akses pengguna.
5. Jalankan pilot satu proyek, verifikasi total dan persetujuan dengan pengguna, dokumentasikan langkah admin/Planner/PIC/approver, lalu perluas ke proyek lain sesuai persetujuan.

## Fase 3 usulan

| Pekerjaan | Prasyarat dan hasil yang diuji |
|---|---|
| Monitoring mingguan | Sepakati kalender mingguan, cutoff, dan agregasi ke bulan. Histori bulanan menampilkan hasil resmi sesuai periode. |
| Notifikasi keterlambatan dan approval | Tetapkan ambang, kanal, penerima, frekuensi, serta pencegahan duplikasi. Uji pengiriman dan perubahan status setelah pekerja menindaklanjuti. |
| Milestone dan Gantt Chart | Tambahkan milestone, tanggal, dan dependensi pekerjaan yang disetujui. Uji visual baseline dan pembacaan progres aktual pada jadwal. |
| Approval dokumen digital | Bedakan pencatatan persetujuan workflow dan kebutuhan tanda tangan elektronik saat memilih solusi. Tetapkan pihak yang berhak, kebutuhan hukum dan dokumen, audit, serta verifikasi hasil. |
| Dashboard lintas proyek | Definisikan data yang dapat diakses, periode yang sebanding, dan metode agregasi lintas proyek. Uji isolasi organisasi sesuai K08. |
| Integrasi procurement/accounting | Tetapkan sistem sumber, kontrak pertukaran, pemetaan ID, rekonsiliasi, retry, dan penanganan duplikasi. Selesaikan K15 sebelum memilih integrasi. |

Keluaran paket berupa matriks pengujian dan bukti UAT, catatan rilis, panduan deployment dan restore, serta backlog Fase 3 yang dapat diprioritaskan.
