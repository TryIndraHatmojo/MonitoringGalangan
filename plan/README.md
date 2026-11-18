# Rencana implementasi Monitoring Galangan

Rencana ini menjadi acuan implementasi aplikasi web responsif dan backend REST API bagi konsumen React Native. MVP web mencakup progres dan monitoring biaya dasar. Keputusan hasil konfirmasi, perbedaan terhadap requirement awal, serta penanggung jawab tindak lanjut tercatat di [00-konfirmasi-kebutuhan.md](00-konfirmasi-kebutuhan.md).

Panduan pengembangan tersedia di [Setup Windows 11](../docs/setup-windows.md).

## Paket pekerjaan

| Urutan | Rencana | Keluaran saat dijalankan | Dependensi |
|---|---|---|---|
| 00 | [Konfirmasi kebutuhan](00-konfirmasi-kebutuhan.md) | Acuan keputusan, lingkup rilis, dan tindak lanjut | Jawaban awal dan hasil konfirmasi |
| 01 | [Fondasi web dan akses](01-fondasi-web-dan-akses.md) | Login lokal, halaman hak akses, master proyek, dokumen privat, dan audit | Stack K01, autentikasi K02, dan izin K08 |
| 02 | [WBS dan baseline](02-wbs-dan-baseline.md) | Hierarki, template dengan aturan akses dan review, dua metode progres, serta target per periode | 01 dan pemetaan awal 05 |
| 03 | [Progres dan approval](03-progres-dan-approval.md) | Input detail, bukti, review item, approval laporan, dan koreksi resmi dengan audit | 01–02 |
| 04 | [Dashboard dan laporan](04-dashboard-dan-laporan.md) | KPI progres dan biaya, S-Curve, ekspor PDF/Excel, dan arsip PDF terbit | 02–03 serta 06 untuk data biaya |
| 05 | [Impor dan rekonsiliasi Excel](05-impor-dan-rekonsiliasi-excel.md) | Mapping workbook, preview, data historis, dan fixture Ferry | Pemetaan sebelum 02, commit setelah 01–03 |
| 06 | [Anggaran dan biaya](06-anggaran-dan-biaya.md) | Anggaran, realisasi, dan sisa per WBS dan mata uang pada MVP. Transaksi pengadaan dan pembayaran pada Fase 2. | 01–02 dan keputusan Finance |
| 07 | [REST API untuk React Native](07-rest-api-react-native.md) | OpenAPI, Postman, dan API baca dengan token per pengguna dan perangkat | Desain sejak awal, implementasi baca mengikuti 01–04 |
| 08 | [Pengujian, rilis, dan operasi](08-pengujian-rilis-dan-operasi.md) | UAT, deployment, backup dan restore, serta backlog Fase 3 | Berjalan sejak 01 |

## Urutan rilis

1. **Pilot internal** menggunakan Ferry untuk menguji master proyek, WBS, baseline, impor awal, dan persetujuan progres dari input sampai publikasi. Tug menguji variasi WBS dan metode pengukuran.
2. **MVP web** mencakup proyek jamak, hak akses, WBS, impor Excel, approval, dashboard, S-Curve, resume, anggaran dan realisasi per WBS, serta laporan PDF dan Excel. Syarat rilis mencakup rekonsiliasi Ferry dan biaya, pengujian akses, konsistensi ekspor, dan simulasi pemulihan di staging.
3. **Fase 2** menambah PO dan komitmen, invoice, pembayaran, kurs, serta estimasi biaya akhir. Perluasan API mengikuti kebutuhan konsumen yang disetujui.
4. **Fase 3** menangani monitoring mingguan, notifikasi, milestone/Gantt, persetujuan dokumen digital, dashboard lintas proyek, dan integrasi. Pemecahan pekerjaan Fase 3 ada di rencana 08.

Rilis API baca dikoordinasikan dengan tim Android setelah kontrak dan staging siap. Fiturnya mencakup proyek, WBS, dashboard progres, dan laporan progres melalui token per pengguna dan perangkat.

Setiap paket menuliskan langkah, keluaran, dan kriteria penerimaan. Gunakan data serta keputusan proyek yang telah disetujui saat mengerjakan paket terkait. Pembagian jadwal kalender dan penanggung jawab implementasi ditentukan setelah lingkup rilis dan kapasitas tim disepakati.

## Sumber

- [User requirement, terutama §3–16](../user-requirement/user-requirement.md)
- [Laporan Progres Ferry 1500 GT, 28 Oktober 2026](<../referensi data/9. Laporan Progres Ferry 1500 GT_28-10-26.xlsx>)
- [ASD Harbour Tug Progress](<../referensi data/ASD HARBOUR TUG 2 X 1800_PROGRESS (1).xlsx>)
- [Cost and Progress Monitoring Chart](<../referensi data/CostandProgressMonitoring_Chart_Asis5.xlsx>)
