# Impor, pemetaan, dan rekonsiliasi Excel

**Pemetaan awal sebelum paket WBS, commit setelah fondasi, WBS, dan progres tersedia.** Mengacu ke requirement §4–6, §13, dan §15 serta keputusan K04–K06 dan K09. Sebelum pilot, Planner memvalidasi cakupan, cutoff, konflik sumber, dan tingkat detail historis.

## Pemetaan awal workbook

| Sumber dan lokasi | Yang dipakai dalam rencana | Pemeriksaan saat implementasi |
|---|---|---|
| [Ferry 1500 GT](<../referensi data/9. Laporan Progres Ferry 1500 GT_28-10-26.xlsx>) · `Cover Weekly`, `Resume`, `Kurva-S (All)` dan kurva kategori | Identitas proyek dan cutoff, bobot empat kategori, target/aktual kumulatif, serta format laporan | Tegaskan cutoff 28 Oktober dan periode bulanan. Sel `Resume!E18:G18` dan kurva Oktober `M16:M19` menjadi angka pembanding. Periksa ketersediaan baseline per item sebelum commit. |
| Ferry · `A.Material `, `B.Jasa `, `konstruksi`, `pengecatan`, `C.Drawing`, `Det.Drawing`, `D.Jasa Pihak Lain`, serta sheet detail terkait | Struktur pekerjaan, satuan, volume, hierarki bobot, tahapan, kontribusi proyek dan kategori | Petakan subtotal dan baris detail. Gunakan versi sumber yang disetujui, termasuk ketika rumus merujuk sheet lain atau workbook eksternal. Sheet material memiliki dimensi yang tercatat sampai baris 1.048.576, sehingga pembacaan perlu batas baris bermakna. |
| [ASD Harbour Tug Progress](<../referensi data/ASD HARBOUR TUG 2 X 1800_PROGRESS (1).xlsx>) · `laporan_1`, `hull construction_4`, detail kelompok, `s-curve` | Variasi sembilan kelompok WBS, sublangkah konstruksi, dan representasi persen yang berbeda | Konflik 1.800 pada nama file dan 1.600 HP pada isi perlu keputusan. Contoh `laporan_1!C24` berisi 100, sementara beberapa bobot tahapan berupa fraksi. Ada formula ber-link eksternal. Kontrol historis `C24:G24` dipakai jika profile impor Tug disetujui. |
| [Cost and Progress Monitoring Chart](<../referensi data/CostandProgressMonitoring_Chart_Asis5.xlsx>) · `EE`, `Summary EE`, `Summary Monitoring`, `EVM`, `Catatan Revisi` | Contoh engineering estimate, pemetaan cost ke work group, angka lokal/impor, kurs, dan EVM | `Catatan Revisi!A19:A24` menyatakan data aktual dan sebagian realisasi tahapan masih dummy. Gunakan workbook untuk pemetaan dan simulasi. Finance menetapkan sumber resmi untuk rekonsiliasi biaya MVP. |

## Langkah implementasi

1. Definisikan format staging untuk metadata proyek, relasi WBS, bobot, metode, target, histori aktual, dan ringkasan sumber. Simpan file/fingerprint, versi pemetaan, sheet, sel/baris, jenis persen/satuan, dan relasi dari data sumber ke record domain.
2. Siapkan template Excel standar dan profil Ferry sesuai lingkup K09. Gunakan Tug untuk menguji variasi WBS. Profil impor tambahan mengikuti persetujuan pemilik data. Petakan kedua metode progres, header bertingkat, merged cells, kolom periode, label/ID berulang, subtotal, sel kosong, formula, dan satuan. Petakan fraksi 0,73845 menjadi 73,845% dan angka 25 menjadi 25% untuk kolom berskala 0–100. Tetapkan konversi per kolom.
3. Baca file secara terbatas dengan parser yang sesuai stack. Gunakan input literal atau nilai tersimpan dari rumus yang telah disetujui. Deteksi nilai formula kosong, error, external link, dan anomali tanpa membuka sumber link atau menjalankan formula dari file. Untuk nilai turunan, hitung ulang logika bisnis dari data yang dipetakan dan bandingkan hasilnya ke angka workbook. Untuk histori tanpa detail, tandai granularity pada data legacy dan laporan.
4. Buat alur unggah → validasi → preview → persetujuan commit. Preview menampilkan jumlah record yang akan dibuat/diperbarui, konflik kode, unit/format tak valid, distribusi bobot, data yang akan dilewati, dan selisih rekonsiliasi. Tawarkan unduhan hasil validasi untuk pembetulan sumber.
5. Gunakan layanan domain proyek/WBS/progres untuk commit. Terapkan izin, transaksi per proyek atau paket, penguncian terhadap edit bersamaan, dan fingerprint atau kunci idempotensi batch. Untuk perubahan pada periode yang sudah resmi, buat pengajuan koreksi beralasan oleh pengguna dengan izin membuka kembali data resmi. Gunakan review item dan approval akhir paket 03 sebelum mengganti hasil resmi. Simpan hasil batch, rujukan sel sumber, dan audit.
6. Setelah impor pilot, bandingkan total per kategori/periode dan pengukuran contoh ke workbook. Lengkapi mapping serta dokumentasi penerapan untuk operator.

## Keluaran dan penerimaan

| Kontrol Ferry 28 Oktober 2026 | Lokasi sumber | Hasil tampilan |
|---|---|---|
| Rencana kumulatif | `Resume!E18`, `Kurva-S (All)!M16` | 30,693% |
| Aktual kumulatif | `Resume!F18`, `Kurva-S (All)!M18` | 50,065% |
| Deviasi | `Resume!G18`, `Kurva-S (All)!M19` | +19,372 pp |
| Aktual bulan lalu dan kenaikan | `Resume!D18`, `Resume!I18`, `Kurva-S (All)!M17` | 49,139% dan +0,926 pp |
| Bobot kategori | `Resume!C13:C16` | 73,845%, 23,403%, 0,727%, 2,025% |

- Target pembulatan diverifikasi dari nilai tersimpan di dua sheet. Saat implementasi, hasil mesin dihitung dari detail yang dapat dipetakan dan ditelusuri. Selisih atau keterbatasan data detail diselesaikan dengan pemilik sumber.
- Preview menampilkan kasus konflik identitas Tug, workbook dengan formula tanpa nilai tersimpan, sel kosong, dan dimensi sheet besar. File yang gagal validasi tidak menghasilkan data resmi.
- Impor file yang sama dua kali menghasilkan catatan batch yang dapat ditelusuri tanpa menggandakan hasil bisnis. Persetujuan penggunaan kembali data legacy mengikuti K09.
- Impor ulang dengan perubahan pada periode resmi menghasilkan pengajuan koreksi. Setelah approval, hasil resmi periode itu diperbarui dan PDF yang sudah terbit dapat diunduh dengan angka aslinya.
- Keluaran mencakup data mapping, format standar, alur impor, dan fixture uji resmi dengan data yang diizinkan.
