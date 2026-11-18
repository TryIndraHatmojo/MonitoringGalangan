# Monitoring Galangan

Aplikasi web untuk mengelola proyek pembangunan kapal, akses organisasi, dokumen privat, WBS, dan baseline. Paket 01–02 menyediakan API baca proyek dan WBS dengan token perangkat.

## Mulai

- Ikuti [setup Windows 11 dari mesin kosong](docs/setup-windows.md) untuk memasang PHP 8.4, Composer 2, Node.js 22, dan MySQL 8.4 LTS.
- Gunakan [panduan toolchain portabel](docs/toolchain-windows.md) jika PHP dan MySQL sudah tersedia di folder `.toolchain`.
- Baca [urutan paket](plan/README.md) dan [keputusan kebutuhan](plan/00-konfirmasi-kebutuhan.md) untuk lingkup rilis.
- Gunakan [OpenAPI](postman/specs/monitoring-galangan.openapi.yaml) dan [panduan uji Postman](postman/README.md) untuk mencoba API lokal.

## Verifikasi pengembangan

Setelah mengikuti panduan setup, gunakan database tes khusus untuk menjalankan pemeriksaan secara manual.

~~~powershell
composer lint
npm.cmd run lint
npm.cmd run typecheck
npm.cmd run build
php artisan test
~~~
