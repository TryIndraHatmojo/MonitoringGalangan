# API lokal Monitoring Galangan

[OpenAPI 3.1](specs/monitoring-galangan.openapi.yaml) mendefinisikan API baca Paket 01–02. Koleksi Postman memakai operasi yang diturunkan dari spesifikasi dan menambahkan assertion untuk fixture Ferry.

1. Jalankan aplikasi dan muat fixture dengan `php artisan db:seed --class=DevelopmentSeeder`.
2. Salin environment contoh ke `Lokal.private.environment.yaml` di luar Git, lalu isi email dan kata sandi Administrator lokal. Pertahankan organisasi `ferry-demo`, periode `2026-10`, dan target `30.693` untuk fixture standar. Untuk menguji penentuan galangan otomatis, gunakan akun Planner Ferry dan kosongkan variabel `organization`.
3. Jalankan koleksi terhadap aplikasi lokal:

~~~powershell
postman collection run "postman/collections/Monitoring Galangan API" -e "Lokal.private.environment.yaml" --no-report-events
~~~

Koleksi menerbitkan token, mengambil profil, membaca proyek dan WBS, lalu mencabut token. Pemeriksaan terakhir memastikan token lama ditolak. Jangan gunakan akun atau token produksi untuk collection run.

Jika kontrak berubah, gunakan `postman spec sync collection postman/specs/monitoring-galangan.openapi.yaml`, lalu periksa diff script, urutan request, variabel, dan folder Penutupan sebelum menjalankan ulang. [Postman CLI](https://learning.postman.com/latest-v-12/docs/postman-cli/postman-cli-governance)
