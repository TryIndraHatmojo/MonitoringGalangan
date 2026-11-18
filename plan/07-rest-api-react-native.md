# Rencana REST API untuk konsumen React Native

**Backend untuk konsumen React Native.** Rancang kontrak sejak awal dan implementasikan API baca bersama modul domain. Endpoint mengikuti izin aksi, proyek, dan WBS yang dipakai web. Rilis API baca dikoordinasikan dengan tim Android setelah kontrak dan staging siap.

## Pembagian rilis

| Paket | Operasi dari sisi konsumen | Dependensi |
|---|---|---|
| A · API baca | Token per pengguna dan perangkat, proyek, WBS, periode, progres resmi, dashboard progres, S-Curve, dan laporan progres | Fondasi, WBS, progres, dan dashboard sesuai data yang ditampilkan |
| B · API PIC, sesuai konfirmasi | Membuat/memperbarui draft, mengunggah bukti, dan mengirim laporan | Alur input progres, file privat, retry, dan kontrol versi |
| C · Perluasan, sesuai konfirmasi | Review/approval, ringkasan biaya mobile, akses antarsistem, atau sinkronisasi offline | Kebijakan izin, modul biaya, kontrak integrasi, atau protokol sinkronisasi yang disetujui |

Gunakan koneksi aktif pada rilis awal. Jadwalkan paket B dan C sesuai kebutuhan yang disetujui bersama tim Android dan pemilik modul.

## Inventaris endpoint usulan

Prefix `/api/v1`. Nama resource berikut ditetapkan pada review kontrak.

| Paket | Metode dan path | Tujuan |
|---|---|---|
| A | `POST /auth/tokens`, `DELETE /auth/tokens/current`, `GET /me` | Penerbitan token per pengguna lokal dan perangkat, pencabutan token saat logout, serta profil dan kemampuan |
| A | `GET /projects`, `GET /projects/{projectId}` | Daftar dan metadata proyek yang diizinkan |
| A | `GET /projects/{projectId}/wbs`, `GET /wbs-items/{itemId}` | Daftar item dengan parent/level, detail dan template progres sesuai hak pengguna |
| A | `GET /projects/{projectId}/periods`, `GET /projects/{projectId}/progress-reports`, `GET /progress-reports/{reportId}` | Periode dan hasil resmi terbaru per periode sesuai izin |
| A | `GET /projects/{projectId}/dashboard`, `GET /projects/{projectId}/s-curve` | KPI progres, kategori, status pekerjaan, dan seri planned/actual yang dapat difilter |
| A | `GET /projects/{projectId}/exports`, `GET /exports/{exportId}/download` | Daftar dan unduh ekspor progres yang tersedia, termasuk arsip PDF progres sesuai izin |
| B | `POST /projects/{projectId}/progress-reports`, `PATCH /progress-reports/{reportId}` | Buat dan perbarui draft secara atomik dengan versi |
| B | `POST /progress-reports/{reportId}/attachments`, `GET /attachments/{attachmentId}/download` | Unggah bukti multipart dan unduh file sesuai izin |
| B / C | `POST /progress-reports/{reportId}/transitions` | Catat transisi submit pada B. Tambahkan review, pengembalian, dan approval pada C jika disetujui. |
| C | `GET /projects/{projectId}/cost-summary` | Ringkasan biaya saat akses Android disetujui, dengan nominal per mata uang sesuai model biaya |

## Kontrak dan implementasi

1. Tulis OpenAPI 3.1 yang mendefinisikan operation ID, request/response, skema, enum, nullable, izin, satuan, contoh, batasan unggahan, pagination, dan status HTTP. Review dengan tim Android. Usulan JSON menggunakan `data` untuk hasil, `meta` untuk pagination, dan `error` dengan `code`, `message`, `fields` jika ada, serta `request_id`. Bedakan perubahan kompatibel dari perubahan kontrak yang memerlukan versi baru.
2. Gunakan HTTPS dan token Sanctum per pengguna lokal dan perangkat. Tetapkan masa berlaku, kemampuan token, pencabutan saat logout, dan penyimpanan aman di perangkat. Cek status pengguna, izin aksi, keanggotaan proyek, penugasan, serta akses WBS di setiap request yang relevan. Cek cakupan izin saat mengunduh file yang sudah terbit. [Dokumentasi Laravel Sanctum](https://laravel.com/framework/docs/sanctum)
3. Tetapkan ID sebagai string opak, periode sebagai `YYYY-MM`, cutoff sebagai tanggal, dan timestamp dalam format ISO 8601 dengan zona. Dokumentasikan skala field desimal. Contoh `planned_pct: "30.693"`, `actual_pct: "50.065"`, `variance_pp: "19.372"`, dan `actual_pct: null` untuk periode tanpa data aktual. Cantumkan kode mata uang pada nominal bila ringkasan biaya dirilis.
4. Definisikan filter `period`, `from_period`, `to_period`, dan `category_id` pada resource yang relevan. Gunakan pagination stabil dan batas ukuran halaman. Pada S-Curve, kembalikan periode, cutoff, skala kategori bila relevan, dan data terurut dari hasil resmi terbaru.
5. Gunakan `401` untuk autentikasi gagal/kedaluwarsa, `403` untuk aksi yang dilarang pada resource yang boleh dikenali, `404` untuk resource yang tidak dapat ditemukan atau tidak boleh diungkap, `409` untuk konflik versi/status/idempotensi, `413` untuk unggahan melebihi batas, `422` untuk validasi field, dan `429` dengan informasi kapan dapat mencoba lagi. Dokumentasikan error untuk baseline belum resmi, laporan sudah disetujui, akses ditarik, dan file belum tersedia.
6. Jika ada operasi tulis, gunakan `Idempotency-Key` untuk pembuatan, unggah, dan transisi yang dapat diulang. Simpan hasil atau referensi hasil yang dikaitkan dengan pengguna/proyek/operasi dan sidik payload selama jendela retry. Ulangan payload yang sama memakai hasil awal, payload berbeda dengan kunci yang sama mendapat konflik. Gunakan `version` untuk update draft dan approval. Tentukan strategi retry ketika hasil request pertama belum diketahui serta ketika unggahan terputus.
7. Jika offline penuh dikonfirmasi, desain perubahan sejak cursor, penanganan record dihapus atau akses dicabut, outbox perubahan, aturan konflik per resource, serta resinkronisasi. Perkirakan kebutuhan backend dan konfirmasikan tanggung jawab konsumen sebelum menjanjikan jadwal. Paket awal dapat diuji dengan jaringan terputus dan retry sesuai kontrak.
8. Turunkan Postman collection lokal dari OpenAPI yang disepakati dengan `postman spec generate collection`. Gunakan environment contoh tanpa kredensial, fixtures staging, dan assertions. Simpan OpenAPI sebagai definisi kontrak dan sinkronkan perubahan ke koleksi sambil mempertahankan skenario uji. Tambahkan lint, uji kontrak, dan pengujian HTTP untuk perubahan. [Spesifikasi OpenAPI 3.1](https://spec.openapis.org/oas/v3.1) · [Postman CLI spec commands](https://learning.postman.com/latest-v-12/docs/postman-cli/postman-cli-governance)

## Keluaran dan penerimaan

- OpenAPI, Postman collection, environment contoh, dan panduan integrasi menjelaskan autentikasi, sumber data resmi, filter, endpoint, error, unduhan, dan retry yang termasuk rilis.
- Pada fixture Ferry, API baca menghasilkan angka dan cutoff yang sama dengan dashboard, PDF, dan Excel untuk filter serta hasil resmi yang sama. Aktual periode berikutnya kosong sampai tersedia data resmi. Koreksi yang disetujui tercermin pada API baca periode terkait.
- Uji HTTP mencakup akun sah, token kedaluwarsa dan dicabut, PIC tanpa penugasan, proyek berbeda, akses WBS, pagination, akses file terbit, retry pembacaan, dan rate limit. Bila perluasan dirilis, uji izin biaya, unggahan terputus, submit ganda, dan konflik perubahan draft sesuai endpoint yang ditambahkan.
- Tim Android dapat meninjau kontrak lewat contoh request/response dan mencoba di staging dengan akun uji. Serah terima menetapkan base URL, prosedur mendapatkan akses, dan cara melacak masalah dari request ID.
