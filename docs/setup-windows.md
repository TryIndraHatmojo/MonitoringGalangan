# Setup Monitoring Galangan di Windows 11

Panduan ini untuk Windows 11 x64 yang baru dipasang. Siapkan koneksi internet, akun dengan hak pemasangan aplikasi, dan akses ke repositori GitHub. Gunakan PowerShell biasa untuk perintah pengembangan. Installer sistem, pengaturan service MySQL, dan perubahan PATH tingkat mesin dapat meminta hak Administrator.

## 1. Instal Git dan runtime

1. Instal **Microsoft Visual C++ Redistributable x64**, lalu **Git for Windows x64**. Pertahankan opsi Git yang menambahkan Git ke PATH dan menyediakan Git Credential Manager. Buka PowerShell baru dan jalankan `git --version`. [Microsoft Visual C++ Redistributable](https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist/) · [Git for Windows](https://git-scm.com/install/windows)
2. Unduh **PHP 8.4 terbaru** dalam ZIP **VS17 x64 Non Thread Safe**. Ekstrak sehingga `php.exe` berada di `C:\Tools\php84`. Tambahkan direktori itu di awal PATH melalui **System Properties → Advanced → Environment Variables**. Buka PowerShell baru. [PHP untuk Windows](https://www.php.net/downloads.php?os=windows)
3. Buat `php.ini` dari file bawaan.

   ~~~powershell
   Copy-Item -LiteralPath 'C:\Tools\php84\php.ini-development' -Destination 'C:\Tools\php84\php.ini'
   notepad.exe 'C:\Tools\php84\php.ini'
   ~~~

   Aktifkan baris ekstensi yang relevan dengan menghapus `;` di depannya. Isi `extension_dir` dengan direktori absolut:

   ~~~ini
   extension_dir = "C:\Tools\php84\ext"
   extension=curl
   extension=fileinfo
   extension=intl
   extension=mbstring
   extension=openssl
   extension=pdo_mysql
   extension=zip
   upload_max_filesize = 10M
   post_max_size = 12M
   ~~~

   Pastikan `php -m` juga menampilkan `bcmath`, `PDO`, `dom`, `xml`, `xmlwriter`, dan `tokenizer`. PHP Windows 8.4 yang diverifikasi menyediakan BCMath sebagai modul bawaan. [Ekstensi Laravel](https://laravel.com/framework/docs/13.x/deployment)
4. Instal **Composer 2** melalui Composer Setup. Saat diminta lokasi PHP, pilih `C:\Tools\php84\php.exe`. [Composer di Windows](https://getcomposer.org/doc/00-intro.md#installation-windows)
5. Dari arsip Node, pilih **Node.js 22 LTS x64** terbaru pada seri 22 dan unduh MSI dari halaman rilisnya. Biarkan npm dan opsi PATH ikut terpasang. Toolchain proyek memakai Node.js **22.13 atau lebih baru** pada seri 22. [Node.js 22](https://nodejs.org/en/download/archive/v22) · [Persyaratan Vite](https://vite.dev/guide/) · [Persyaratan ESLint](https://eslint.org/docs/latest/use/getting-started)

Jalankan dari PowerShell baru:

~~~powershell
git --version
where.exe php
php -v
php --ini
php -m
composer --version
node -v
npm.cmd -v
~~~

Hasil `where.exe php` harus menempatkan `C:\Tools\php84\php.exe` di urutan pertama. Laravel, Inertia, React, TypeScript, Tailwind, dan Vite ikut terpasang dari lockfile setelah repo di-clone.

## 2. Pasang MySQL 8.4 LTS

1. Di halaman MySQL Community Server, pilih seri **8.4 LTS**, **Microsoft Windows**, dan paket **Windows x86, 64-bit MSI**. Jalankan installer dan buka MySQL Configurator setelah selesai. [MySQL Community Server](https://dev.mysql.com/downloads/mysql/) · [MySQL Configurator](https://dev.mysql.com/doc/refman/8.4/en/mysql-configurator.html)
2. Pilih konfigurasi **Development Computer**. Aktifkan TCP/IP pada port `3306` dan kosongkan opsi pembukaan port Windows Firewall. Buat kata sandi kuat untuk akun `root`. Konfigurasikan Windows service bernama `MySQL84` dengan startup otomatis. Terapkan konfigurasi.
3. Untuk membatasi listener ke mesin ini, buka berkas `my.ini` service sebagai Administrator. Lokasi default ada di `C:\ProgramData\MySQL\MySQL Server 8.4\my.ini`. Jika lokasi berbeda, jalankan `sc.exe qc MySQL84` dan lihat nilai `--defaults-file`. Tetapkan `bind-address=127.0.0.1` pada bagian `[mysqld]`, lalu jalankan `Restart-Service MySQL84`. [Konfigurasi MySQL di Windows](https://dev.mysql.com/doc/refman/8.4/en/windows-installation.html)

~~~powershell
Get-Service MySQL84
Test-NetConnection 127.0.0.1 -Port 3306
$mysql = 'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe'
& $mysql --protocol=TCP -h 127.0.0.1 -P 3306 -u root -p
~~~

Pada prompt MySQL, ganti placeholder kata sandi dengan kata sandi lokal yang kuat, lalu jalankan:

~~~sql
CREATE DATABASE monitoring_galangan CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE monitoring_galangan_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER 'monitoring_app'@'127.0.0.1' IDENTIFIED BY '<password-aplikasi-lokal>';
GRANT ALL PRIVILEGES ON `monitoring\_galangan`.* TO 'monitoring_app'@'127.0.0.1';
GRANT ALL PRIVILEGES ON `monitoring\_galangan\_test`.* TO 'monitoring_app'@'127.0.0.1';
EXIT;
~~~

Verifikasi sebagai akun aplikasi. Hasilnya harus menunjukkan MySQL `8.4.x`, akun `monitoring_app@127.0.0.1`, dan database `monitoring_galangan`. Escape `\_` pada `GRANT` membatasi izin ke nama database yang ditentukan. [Akun dan hak akses MySQL](https://dev.mysql.com/doc/refman/8.4/en/creating-accounts.html) · [Aturan GRANT MySQL](https://dev.mysql.com/doc/refman/8.4/en/grant.html)

~~~powershell
& $mysql --protocol=TCP -h 127.0.0.1 -P 3306 -u monitoring_app -p monitoring_galangan -e 'SELECT VERSION(), CURRENT_USER(), DATABASE();'
~~~

## 3. Clone dan konfigurasi aplikasi

~~~powershell
Set-Location $env:USERPROFILE
git clone https://github.com/TryIndraHatmojo/MonitoringGalangan.git
Set-Location MonitoringGalangan
Get-Item artisan, .env.example, composer.lock, package-lock.json
Copy-Item -LiteralPath '.env.example' -Destination '.env'
notepad.exe '.env'
~~~

Jika repository meminta autentikasi, masuk melalui Git Credential Manager dengan akun yang punya akses. Jika empat file pemeriksaan belum muncul, gunakan branch atau checkout yang berisi implementasi Paket 01. Untuk checkout yang sudah punya `.env`, edit file yang ada.

~~~dotenv
APP_ENV=local
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=monitoring_galangan
DB_USERNAME=monitoring_app
DB_PASSWORD='<password-aplikasi-lokal>'
SESSION_DRIVER=database
FILESYSTEM_DISK=local
MAIL_MAILER=log
~~~

Gunakan akun dan port dari langkah MySQL. Tautan undangan dan reset kata sandi untuk pengembangan ditulis ke `storage/logs/laravel.log`. Jaga `.env` dan log lokal di luar commit. [Konfigurasi Laravel](https://laravel.com/framework/docs/13.x/configuration)

~~~powershell
composer install
composer check-platform-reqs
php artisan key:generate
php artisan migrate
npm.cmd ci
~~~

Untuk memuat demo pada environment `local`, tambahkan `MONITORING_DEMO_PASSWORD` ke `.env` dan isi dengan kata sandi pilihan Anda sepanjang 12–72 byte. Lalu jalankan perintah berikut.

~~~powershell
php artisan db:seed
~~~

Kelima akun memakai kata sandi tersebut saat pertama dibuat.

| Akun | Akses |
| --- | --- |
| admin@demo.monitoring.test | Administrator pusat untuk kedua organisasi |
| manager.ferry@demo.monitoring.test | Project Manager dan penyetuju baseline Ferry |
| planner.ferry@demo.monitoring.test | Planner Ferry |
| pic.ferry@demo.monitoring.test | PIC Lapangan Ferry |
| viewer.tug@demo.monitoring.test | Viewer proyek Tug |

Seeding ulang mempertahankan kata sandi akun yang sudah ada. Akun biasa langsung membuka Proyek Kapal di galangannya. Administrator Pusat memilih galangan dari halaman administrasi. Untuk menyiapkan administrator pusat bagi instalasi operasional, seed katalog dengan `php artisan db:seed --class=RoleSeeder`, lalu jalankan `php artisan monitoring:admin`.

Untuk membangun ulang database demo, pastikan koneksi mengarah ke database lokal `monitoring_galangan`, lalu jalankan `php artisan migrate:fresh --seed`. Perintah ini menghapus tabel dan isi database tujuan sebelum memuat fixture.

## 4. Buka dan verifikasi aplikasi

Gunakan dua terminal di direktori repo. Jalankan server PHP pada terminal pertama.

~~~powershell
php artisan serve --host=127.0.0.1 --port=8000
~~~

Jalankan Vite pada terminal kedua.

~~~powershell
npm.cmd run dev -- --host 127.0.0.1
~~~

Buka `http://127.0.0.1:8000/login`, masuk sebagai Administrator, dan pilih salah satu organisasi. Jika memakai demo, pilih periode `2026-10` di halaman WBS untuk melihat target Ferry **30,693%** dan target Tug **60%**. Target Tug untuk periode `2026-11` adalah **100%**. Periksa juga unduhan dokumen setelah mengunggah berkas dengan akun yang berwenang. Dokumen disimpan di `storage/app/private`.

Jalankan pemeriksaan berikut dari terminal lain. Tes memakai database `monitoring_galangan_test`.

~~~powershell
composer lint
npm.cmd run lint
npm.cmd run typecheck
npm.cmd run build
php artisan test --testsuite=Feature
~~~

Untuk menjalankan aplikasi dengan aset build, hentikan Vite setelah build selesai dan biarkan server PHP berjalan.

## Pemecahan masalah

| Gejala | Pemeriksaan |
| --- | --- |
| PHP versi lama muncul | Jalankan `where.exe php`, naikkan `C:\Tools\php84` di PATH, lalu buka PowerShell baru. |
| `could not find driver` | Periksa path konfigurasi dengan `php --ini`, aktifkan `extension=pdo_mysql`, lalu cek `php -m`. |
| `Connection refused` | Periksa `Get-Service MySQL84` dan `Test-NetConnection 127.0.0.1 -Port 3306`. |
| `Access denied` | Ulangi koneksi MySQL memakai akun, host, dan port dari `.env`. Cek kata sandi dan hak akses skema. Jalankan `php artisan config:clear` setelah memperbarui konfigurasi. |
| Port sudah dipakai | Jalankan `Get-NetTCPConnection -LocalPort 3306,8000,5173 -State Listen`. Pilih port bebas, lalu sesuaikan port service, `DB_PORT`, atau `APP_URL` sesuai layanan yang dipindah. |
| Aset Vite gagal dimuat | Jalankan `npm.cmd ci` dan pastikan server Vite aktif, atau jalankan `npm.cmd run build` untuk menghasilkan manifest aset. |
| Unggahan ditolak PHP | Periksa `upload_max_filesize` dan `post_max_size` pada `php.ini`, lalu mulai ulang server PHP. |

## Catatan verifikasi

Instalasi dependensi dari lockfile, migrasi pada skema baru, pembuatan Administrator pusat, fixture, server pengembangan, dan build diuji di Windows 11 x64 dengan PHP 8.4.27, Composer 2.9.2, Node.js 22.18.0, npm 10.9.3, Laravel 13.43.0, dan MySQL 8.4.13. Pengujian memakai runtime PHP dan MySQL portabel. Wizard MSI masih perlu diuji pada instalasi Windows baru. [Laravel 13](https://laravel.com/framework/docs/13.x/releases)
