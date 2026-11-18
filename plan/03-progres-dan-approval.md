# Input progres, persetujuan, dan histori

**Fase 1.** Mengacu ke requirement §6, §9, §12–13, dan §15 serta keputusan K04–K08. Dependensi 01–02.

## Langkah implementasi

1. Buat laporan progres bulanan dengan tanggal cutoff terpisah. Kaitkan data item, input progres, bobot, dan baseline yang dipakai. Simpan catatan, kendala, serta lampiran dengan cakupan proyek. Tetapkan satu hasil resmi per proyek dan periode. Simpan draft atau koreksi yang sedang diajukan terpisah dari hasil resmi.
2. Sediakan daftar pekerjaan PIC, form sesuai metode item, dan preview bulan lalu, periode ini, serta kumulatif. Bedakan capaian yang dibawa dari hasil resmi terakhir, nilai nol eksplisit, dan data yang belum diketahui. Catat alasan penurunan, foto, hasil inspeksi, dan dokumen sesuai izin.
3. Gunakan satu layanan hitung dengan persentase desimal. Progres fisik item adalah nilai langsung atau jumlah `bobot tahap × capaian tahap / 100`. Kontribusi proyek adalah `bobot proyek item × progres fisik / 100`. Agregasikan setiap item yang diukur sekali saja. Simpan dasar hitung bersama hasil resmi, termasuk nilai periode sebelumnya yang digunakan untuk menghitung kenaikan.
4. Implementasikan **Draft → Submitted → Reviewed → Approved**. Review dilakukan per item oleh pengguna bertipe reviewer yang ditetapkan pada template WBS dan berwenang pada proyek. Setelah seluruh review wajib selesai, satu approver proyek menyetujui laporan secara keseluruhan. Reviewer dapat mengembalikan laporan dengan alasan. Tampilkan perubahan, penurunan progres, dan bukti pendukung saat review.
5. Saat approval, kunci atau periksa versi draft untuk menangani konkurensi, hitung hasil, tulis audit, dan publikasikan secara atomik. Approval yang diulang menghasilkan satu publikasi. Dashboard dan ekspor baru memakai hasil resmi yang berlaku.
6. Izinkan pengguna dengan izin membuka kembali data resmi menyiapkan koreksi dengan alasan. Gunakan review item dan approval laporan yang sama. Setelah disetujui, gantikan hasil resmi periode terkait dan catat perubahan dalam audit. Jika koreksi memengaruhi carry-forward pada draft berikutnya, tampilkan dampaknya untuk ditinjau. Untuk mengubah hasil resmi periode lain, ajukan koreksi pada periode tersebut. PDF yang sudah terbit tersedia melalui arsip paket 04 dengan angka asli.
7. Sediakan daftar kendala dengan kaitan item dan laporan, PIC, status, serta catatan penyelesaian. Hubungkan indikator keterlambatan ke baseline resmi dan aturan yang disepakati Planner dan PM.

## Keluaran dan penerimaan

- PIC berwenang dapat mengisi item langsung dan bertahap, menyertakan bukti, lalu mengajukan laporan. Dua item dalam laporan dapat ditinjau oleh tipe reviewer berbeda. Satu approver proyek menerbitkan laporan setelah semua review wajib selesai.
- Contoh material dengan capaian 80%, 50%, dan 20% pada bobot 80/10/10 menghasilkan progres fisik 71%. Item berbobot proyek 10% memberi kontribusi 7,1 pp. Kontrol ini berlaku konsisten di UI, API, dan ekspor.
- Uji satu hasil resmi per periode, penolakan aksi tanpa izin, serta retry dan konflik approval. Tampilan resmi memakai hasil terakhir yang disetujui.
- Koreksi yang disetujui tercermin pada periode sasaran dan dapat ditelusuri melalui audit. Uji penurunan, nilai kosong atau nol, carry-forward, hasil resmi periode lain, dan akses arsip PDF terbit.
