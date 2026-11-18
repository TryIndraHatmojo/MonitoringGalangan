# Menjalankan toolchain portabel di Windows

Panduan ini memakai PHP 8.4 dan MySQL 8.4 yang sudah ada di folder `.toolchain` Monitoring Galangan. Composer dijalankan dengan PHP 8.4 proyek. File `composer.phar` berada di instalasi Composer Windows, sedangkan konfigurasi dan cache Composer disimpan di `.toolchain`.

Folder `.toolchain` diabaikan oleh Git. Untuk menyiapkan komputer lain dari awal, ikuti [panduan setup Windows 11](setup-windows.md).

## 1. Siapkan PowerShell

Jalankan blok ini setiap kali membuka terminal baru untuk proyek.

~~~powershell
Set-Location -LiteralPath 'C:\IndraWebProject\apps\MonitoringGalangan'

$projectDir = (Get-Location).Path
$php84 = Join-Path $projectDir '.toolchain\php84\php.exe'
$composerPhar = 'C:\ProgramData\ComposerSetup\bin\composer.phar'
$mysqlBase = Join-Path $projectDir '.toolchain\mysql84\mysql-8.4.13-winx64'
$mysqlBin = Join-Path $mysqlBase 'bin'
$mysqlData = Join-Path $projectDir '.toolchain\mysql-data'
~~~

| Komponen | Lokasi |
| --- | --- |
| PHP dan konfigurasinya | `.toolchain\php84\php.exe` dan `.toolchain\php84\php.ini` |
| Composer | `C:\ProgramData\ComposerSetup\bin\composer.phar` |
| Konfigurasi dan cache Composer | `.toolchain\composer` dan `.toolchain\composer-cache` |
| Server dan klien MySQL | `.toolchain\mysql84\mysql-8.4.13-winx64\bin\` |
| Data MySQL yang sudah ada | `.toolchain\mysql-data\` |

## 2. Jalankan PHP dan Composer

Gunakan `$php84` untuk memilih PHP proyek secara langsung. Pada komputer ini, perintah `php` biasa masih mengarah ke XAMPP.

~~~powershell
& $php84 -v
& $php84 --ini
& $php84 artisan --version
~~~

Tetapkan konfigurasi dan cache Composer untuk terminal ini, lalu jalankan `composer.phar` dengan PHP proyek.

~~~powershell
$env:COMPOSER_HOME = Join-Path $projectDir '.toolchain\composer'
$env:COMPOSER_CACHE_DIR = Join-Path $projectDir '.toolchain\composer-cache'

& $php84 $composerPhar --version
& $php84 $composerPhar install
& $php84 $composerPhar check-platform-reqs
~~~

Jalankan `install` pada checkout baru atau ketika `composer.lock` berubah. Jika file `composer.phar` berada di direktori lain, sesuaikan `$composerPhar` dengan lokasinya.

## 3. Nyalakan MySQL

Periksa port lokal. Jika hasilnya `True`, lanjut ke langkah koneksi database.

~~~powershell
Test-NetConnection -ComputerName 127.0.0.1 -Port 33068 -InformationLevel Quiet
~~~

Jika hasilnya `False`, jalankan perintah ini di terminal khusus MySQL. Panggilan ini memakai direktori data yang sudah ada. Tunggu pesan `ready for connections` dan biarkan terminal tetap terbuka.

~~~powershell
& (Join-Path $mysqlBin 'mysqld.exe') --no-defaults "--basedir=$mysqlBase" "--datadir=$mysqlData" --bind-address=127.0.0.1 --port=33068 --mysqlx=0 --console
~~~

MySQL portabel dijalankan sebagai proses lokal. Jalankan kembali langkah ini setelah komputer dimulai ulang.

## 4. Buka database

Buka terminal PowerShell lain, ulangi langkah 1, lalu sambungkan dengan akun aplikasi. Isi password dari `DB_PASSWORD` di `.env` saat diminta.

~~~powershell
& (Join-Path $mysqlBin 'mysql.exe') --no-defaults --protocol=TCP -h 127.0.0.1 -P 33068 -u monitoring_app -p monitoring_galangan
~~~

Di prompt MySQL, jalankan:

~~~sql
SELECT DATABASE(), VERSION();
SHOW TABLES;
SELECT number, name, status FROM projects;
EXIT;
~~~

Untuk klien GUI, gunakan koneksi MySQL dengan host `127.0.0.1`, port `33068`, database `monitoring_galangan`, username `monitoring_app`, dan password yang sama.

Jika perlu menghentikan server dengan bersih, jalankan perintah berikut dari terminal lain. Masukkan password root MySQL portabel dari `.toolchain\mysql-root-password` saat diminta. Gunakan akun `monitoring_app` untuk membuka database sehari-hari.

~~~powershell
& (Join-Path $mysqlBin 'mysqladmin.exe') --no-defaults --protocol=TCP -h 127.0.0.1 -P 33068 -u root -p shutdown
~~~

## 5. Jalankan Laravel

Pastikan MySQL sudah menerima koneksi dan `.env` mengarah ke `127.0.0.1:33068`. Dari terminal yang sudah menjalankan langkah 1, periksa migrasi dan jalankan server web.

~~~powershell
& $php84 artisan migrate:status
& $php84 artisan serve --host=127.0.0.1 --port=8000
~~~

Buka `http://127.0.0.1:8000/login`. Jika aset frontend belum dibangun, gunakan `npm.cmd run build` atau jalankan `npm.cmd run dev -- --host 127.0.0.1` di terminal lain.

| Gejala | Pemeriksaan |
| --- | --- |
| PHP atau Composer menampilkan versi PHP XAMPP | Gunakan `& $php84 $composerPhar --version` dan periksa file konfigurasi dengan `& $php84 --ini`. |
| MySQL menolak koneksi | Pastikan server sudah menampilkan `ready for connections` dan klien memakai port `33068`. Periksa pesan di terminal MySQL jika proses gagal dimulai. |
| MySQL mengembalikan `Access denied` | Periksa `DB_USERNAME` dan `DB_PASSWORD` di `.env`, lalu ulangi login dengan prompt `-p`. |
| Klien MySQL berhasil tersambung, tetapi Laravel gagal | Periksa `DB_HOST`, `DB_PORT`, dan `DB_DATABASE` di `.env`. Jika sebelumnya memakai cache konfigurasi, jalankan `& $php84 artisan config:clear`. |
