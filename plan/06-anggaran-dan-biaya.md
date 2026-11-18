# Anggaran, komitmen, dan realisasi biaya

**MVP untuk anggaran dan realisasi per WBS. Fase 2 untuk transaksi pengadaan dan pembayaran.** Mengacu ke requirement §7–10 dan §15 serta keputusan K03, K07–K08, K11, dan K15. Dependensi fondasi proyek, WBS, dan keputusan Finance. Analisis yang menggabungkan progres fisik dan biaya memakai hasil progres resmi paket 03.

## MVP biaya per WBS

1. Sediakan form per WBS untuk tiga jenis nilai. Tampilkan tanda persen di samping input persentase, pemilih satuan di samping kuantitas seperti unit atau kg, dan pemilih mata uang di samping nominal. Simpan nilai, jenis, dan satuan atau kode mata uang sesuai jenisnya. Gunakan desimal presisi tetap. Hanya nilai uang yang masuk rekap biaya. Persentase milestone pembayaran material adalah bagian pengukuran progres.
2. Catat anggaran resmi dan realisasi nominal per WBS, dengan tanggal, mata uang, catatan, dan referensi bukti. Terapkan izin Finance dan kewenangan persetujuan anggaran yang disepakati pada K07. Simpan audit perubahan dan izin unduh bukti. Finance menetapkan sumber resmi, tanggal yang menentukan periode realisasi, dan dasar pencatatan sebelum rekonsiliasi MVP. Nilai kontrak penjualan proyek memiliki sumber tersendiri di master proyek.
3. Sajikan anggaran, realisasi, dan sisa di dashboard dan laporan PDF/Excel. Hitung `sisa = anggaran disetujui − realisasi` per WBS, cakupan periode, dan mata uang. Gulung total ke kategori dan proyek sekali per alokasi. Sajikan angka tiap mata uang pada baris atau seri tersendiri. Tautkan ringkasan ke WBS dan bukti sesuai izin. Pakai rekap yang sama di master proyek.

## Fase 2

1. Tambahkan supplier/vendor, PO atau kontrak pembelian, komitmen, alokasi WBS, dokumen, persetujuan, dan pembatalan. Hubungkan invoice ke pembelian, serta pembayaran ke invoice. Dukung beberapa alokasi WBS dan pembayaran parsial sesuai proses Finance.
2. Catat pengakuan biaya, tagihan, dan pembayaran secara terpisah. Definisikan tanggal per metrik serta perlakuan PPN, diskon, retensi, uang muka, refund, biaya tanpa PO, dan pembatalan. Validasi saldo, cegah referensi ganda sesuai vendor/proyek, dan simpan koreksi yang dapat diaudit. Telusuri persetujuan anggaran dan dasar hitung laporan berdasarkan cutoff. Tentukan sumber dan tanggal kurs untuk laporan yang memerlukan konversi.
3. Sajikan biaya yang diakui, komitmen, total pembayaran, tagihan tertunggak, tren bulanan, dan perbandingan progres fisik dengan penyerapan anggaran. Tambahkan estimasi akhir. Usulan awal `EAC = biaya aktual diakui + estimasi sisa pekerjaan (ETC)` dengan tanggal, sumber, dan asumsi dari Finance.
4. Jika EVM disetujui, dokumentasikan baseline anggaran, PV, EV dan AC per periode, rumus SPI, CPI, EAC, ETC, dan VAC, serta penanganan nilai kosong dan pembagi nol. Rekonsiliasi AC ke transaksi resmi. Gunakan workbook biaya untuk pemetaan dan simulasi. [Workbook biaya, EVM dan Catatan Revisi](<../referensi data/CostandProgressMonitoring_Chart_Asis5.xlsx>)

## Kontrol pengujian

| Rilis | Skenario ilustrasi | Hasil yang diharapkan |
|---|---|---|
| MVP | Anggaran IDR 10.000.000 dan USD 1.000, realisasi IDR 4.000.000 dan USD 250 | Sisa IDR 6.000.000 dan USD 750 tampil sebagai dua angka di dashboard, PDF, dan Excel. |
| MVP | Item memuat progres 40%, kuantitas 250 kg, dan realisasi IDR 4.000.000 | Rekap biaya mengambil nilai IDR 4.000.000. Progres dan kuantitas tampil dengan satuannya. |
| Fase 2 | Anggaran IDR 100 juta, PO disetujui IDR 70 juta, invoice dan biaya diakui IDR 45 juta, pembayaran IDR 30 juta | Komitmen IDR 70 juta, aktual IDR 45 juta, pembayaran IDR 30 juta, tagihan belum dibayar IDR 15 juta, sisa IDR 55 juta. |
| Fase 2 | Invoice dengan dua pembayaran dan dua alokasi WBS, lalu ada koreksi | Total invoice dan pembayaran dihitung satu kali. Koreksi dapat ditelusuri sesuai cutoff dan kebijakan Finance. |

## Keluaran dan penerimaan

- Finance dapat menelusuri angka MVP sampai WBS dan bukti sesuai izin. Uji pemisahan tipe nilai, pembulatan, cutoff, total per mata uang, audit, dan kecocokan ekspor. Finance merekonsiliasi data sumber resmi sebelum UAT MVP.
- Fase 2 menambah penelusuran ke PO, invoice, dan pembayaran sesuai izin. Uji pembayaran parsial, alokasi, pembatalan, konversi, dan rekonsiliasi transaksi.
