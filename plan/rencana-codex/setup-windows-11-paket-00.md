# Panduan setup Monitoring Galangan dari Windows 11 kosong

## Dokumen dan cakupan

Buat `docs/setup-windows.md` dalam Bahasa Indonesia untuk pengembang yang memakai **Windows 11 x64**. Tambahkan tautannya di [README rencana](../README.md). Panduan membawa pembaca dari pemasangan toolchain Paket 00 sampai menjalankan aplikasi lokal dari clone setelah Paket 01 tersedia.

Repositori saat ini berisi dokumen rencana dan workbook referensi. Langkah instalasi toolchain dapat diikuti secara mandiri. Langkah menjalankan aplikasi dimulai setelah checkout berisi `artisan`, `.env.example`, `composer.lock`, dan `package-lock.json`.

## 1. Pasang dan verifikasi toolchain

- Jelaskan installer mana yang membutuhkan hak Administrator dan kapan harus membuka ulang PowerShell agar perubahan `PATH` terbaca. Urutkan pemasangan **Visual C++ Redistributable x64**, **Git for Windows**, **PHP 8.4**, **Composer 2**, **Node.js 22 LTS beserta npm**, lalu **MySQL Community Server 8.4 LTS**. [Microsoft Visual C++ Redistributable](https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist/) · [Git for Windows](https://git-scm.com/install/windows)
- Gunakan PHP 8.4 **x64 Non Thread Safe** untuk CLI, ekstrak ke `C:\Tools\php84`, salin `php.ini-development` menjadi `php.ini`, dan atur `extension_dir`. Sertakan daftar ekstensi yang harus muncul di `php -m`, termasuk `pdo_mysql` untuk koneksi database dan `bcmath` untuk perhitungan bobot. Arahkan Composer Setup ke `C:\Tools\php84\php.exe`. Verifikasi dengan `where.exe php`, `php -v`, `php --ini`, `php -m`, dan `composer --version`. [Unduhan PHP untuk Windows](https://www.php.net/downloads.php?os=windows) · [Instalasi Composer di Windows](https://getcomposer.org/doc/00-intro.md#installation-windows)
- Gunakan installer Node.js **22.x x64** dengan versi minimal **22.12**. Verifikasi dengan `node -v` dan `npm.cmd -v`. Jelaskan bahwa Laravel, Inertia, React, TypeScript, Tailwind, dan Vite dipasang dari dependensi dan lockfile proyek. [Node.js 22](https://nodejs.org/en/download/archive/v22) · [Persyaratan Vite](https://vite.dev/guide/)

## 2. Siapkan MySQL lokal

- Gunakan MSI **MySQL Community Server 8.4** dan MySQL Configurator yang disertakan. Dokumentasikan pilihan wizard untuk service `MySQL84`, TCP `127.0.0.1:3306`, dan kata sandi `root`. Verifikasi service dengan `Get-Service MySQL84` dan port dengan `Test-NetConnection`. [Instalasi MySQL di Windows](https://dev.mysql.com/doc/refman/8.4/en/windows-installation.html)
- Sertakan SQL siap salin untuk membuat database `monitoring_galangan` dengan `utf8mb4`, akun `monitoring_app` untuk koneksi lokal, dan hak akses yang terbatas pada database tersebut. Gunakan placeholder kata sandi dan login dengan prompt `-p`. Verifikasi koneksi sebagai akun aplikasi dengan `SELECT VERSION(), CURRENT_USER(), DATABASE()`. Sertakan database terpisah untuk tes otomatis. [Manajemen akun MySQL](https://dev.mysql.com/doc/refman/8.4/en/creating-accounts.html)

## 3. Jalankan aplikasi dari clone

- Sertakan langkah clone repo, cara autentikasi GitHub bila diminta, dan pemeriksaan file yang menandakan Paket 01 sudah tersedia. Tunjukkan cara menyalin `.env.example` ke `.env` dan mengisi `APP_URL`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, serta `DB_PASSWORD`. Gunakan mailer lokal `log` untuk pengujian reset sandi. [Konfigurasi Laravel](https://laravel.com/framework/docs/13.x/configuration)
- Tulis urutan perintah `composer install`, `composer check-platform-reqs`, `php artisan key:generate`, `php artisan migrate`, dan `npm.cmd ci`. Dokumentasikan perintah pembuatan Administrator pertama dan fixture berdasarkan perintah yang benar-benar tersedia di checkout Paket 01.
- Tunjukkan dua terminal PowerShell untuk menjalankan `php artisan serve --host=127.0.0.1 --port=8000` dan `npm.cmd run dev -- --host 127.0.0.1`. Sertakan cara memeriksa halaman login di `http://127.0.0.1:8000`, masuk dengan akun Administrator, dan memverifikasi build dengan `npm.cmd run build`.

## Verifikasi panduan

Uji prosedur pada Windows 11 x64 dari profil atau VM bersih saat dokumentasi ditulis. Panduan dianggap lengkap jika pembaca dapat memverifikasi versi toolchain, terhubung ke MySQL sebagai akun aplikasi, menjalankan migrasi, membuka halaman login, dan menghasilkan build frontend. Sertakan pemecahan masalah untuk PHP lama yang terbaca dari `PATH`, `php.ini` atau `pdo_mysql` yang belum aktif, service MySQL berhenti, akses database ditolak, bentrok port, dan aset Vite yang belum tersedia. Catat langkah yang sudah diuji beserta versi yang digunakan.
