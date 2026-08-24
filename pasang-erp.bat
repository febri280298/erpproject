@echo off
setlocal enabledelayedexpansion
title ERP Peak - Pemasangan

REM ===========================================================================
REM  Memasang ERP Peak di komputer baru.
REM
REM  Aman dijalankan ulang di komputer yang sudah terpasang: migrasi hanya
REM  menjalankan yang belum pernah dijalankan, dan data awal hanya diisi bila
REM  basis datanya memang masih kosong. Tidak ada langkah yang menimpa data.
REM
REM  Subrutin :cek_port dan :jeda sengaja diulang di tiap skrip, tidak dipakai
REM  bersama. Skrip pemasangan harus bisa berdiri sendiri di komputer yang belum
REM  punya apa-apa, termasuk bila berkas lain belum ikut tersalin.
REM ===========================================================================

cd /d "%~dp0"

set "XAMPP=C:\xampp"
set "PORT=7001"
set "DB=erppeak"
set "DBUSER=root"

set "PHP=%XAMPP%\php\php.exe"
if not exist "%PHP%" set "PHP=php"
set "MYSQL=%XAMPP%\mysql\bin\mysql.exe"

echo.
echo    ERP Peak - Pemasangan
echo    ==========================================
echo.

REM -------------------------------------------------- 0. Prasyarat ----------
echo    [0/7] Memeriksa prasyarat...
set "KURANG="

"%PHP%" -v >nul 2>&1 || (echo          - PHP tidak ditemukan & set "KURANG=1")
if not exist "%XAMPP%\mysql\bin\mysqld.exe" (echo          - MySQL/XAMPP tidak ditemukan di %XAMPP% & set "KURANG=1")
where composer >nul 2>&1 || (echo          - Composer tidak ditemukan & set "KURANG=1")
where npm >nul 2>&1 || (echo          - Node.js/npm tidak ditemukan & set "KURANG=1")

if defined KURANG goto :prasyarat_kurang
echo          PHP, MySQL, Composer, dan Node.js siap.

REM -------------------------------------------------- 1. Berkas .env --------
echo    [1/7] Menyiapkan berkas .env...
if exist ".env" (
    echo          .env sudah ada, dibiarkan apa adanya.
) else (
    if not exist ".env.example" goto :tanpa_env_example
    copy /Y ".env.example" ".env" >nul
    echo          .env dibuat dari .env.example.
)

REM -------------------------------------------------- 2. MySQL --------------
echo    [2/7] Memastikan MySQL berjalan...
call :cek_port 3306
if "!ADA!"=="1" (
    echo          MySQL sudah berjalan.
) else (
    start "" /B "%XAMPP%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP%\mysql\bin\my.ini"
    call :tunggu_port 3306 30
    if errorlevel 1 goto :mysql_gagal
    echo          MySQL siap.
)

REM -------------------------------------------------- 3. Basis data ---------
echo    [3/7] Membuat basis data "%DB%" bila belum ada...
"%MYSQL%" -u %DBUSER% -e "CREATE DATABASE IF NOT EXISTS `%DB%` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>nul
if errorlevel 1 goto :db_gagal
echo          Basis data siap.

REM -------------------------------------------------- 4. Pustaka PHP --------
echo    [4/7] Memasang pustaka PHP (composer install)...
echo          Ini bisa memakan beberapa menit pada pemasangan pertama.
call composer install --no-interaction --prefer-dist
if errorlevel 1 goto :composer_gagal

REM -------------------------------------------------- 5. Kunci aplikasi -----
echo    [5/7] Menyiapkan kunci aplikasi...
findstr /B /C:"APP_KEY=base64:" ".env" >nul 2>&1
if errorlevel 1 (
    "%PHP%" artisan key:generate --force
    echo          Kunci aplikasi dibuat.
) else (
    echo          Kunci aplikasi sudah ada.
)

REM -------------------------------------------------- 6. Migrasi & data -----
echo    [6/7] Menyiapkan tabel...
"%PHP%" artisan migrate --force
if errorlevel 1 goto :migrasi_gagal

REM Data awal hanya diisi bila tabel pengguna memang masih kosong. Tanpa
REM pemeriksaan ini, menjalankan ulang pemasangan di komputer yang sudah
REM berisi data akan menumpuk baris bawaan di atas data sungguhan.
REM
REM Hasilnya dibaca lewat berkas sementara, bukan lewat for /f. Tanda kutip
REM di dalam -e "SELECT ..." bertabrakan dengan tanda kutip pembungkus for /f,
REM dan akibatnya tidak terlihat: perintahnya gagal diam-diam, hasilnya kosong,
REM lalu dianggap basis data masih kosong sehingga data contoh ikut terisi.
set "HITUNG=%TEMP%\erp-hitung-pengguna.txt"
set "ADA_DATA="
"%MYSQL%" -u %DBUSER% -D %DB% -N -B -e "SELECT COUNT(*) FROM users;" > "%HITUNG%" 2>nul
if exist "%HITUNG%" set /p ADA_DATA=<"%HITUNG%"
del "%HITUNG%" >nul 2>&1

REM Angka yang tidak terbaca TIDAK dianggap nol. Menebak "kosong" lalu mengisi
REM data contoh merusak data sungguhan; berhenti dengan pesan jelas jauh lebih
REM murah daripada memulihkan dari cadangan.
echo !ADA_DATA!| findstr /R "^[0-9][0-9]*$" >nul || goto :hitung_gagal

if "!ADA_DATA!"=="0" (
    echo          Mengisi data awal ^(bagan akun, satuan, peran, pengguna^)...
    "%PHP%" artisan db:seed --force
    if errorlevel 1 goto :seed_gagal
) else (
    echo          Basis data sudah berisi !ADA_DATA! pengguna - data awal dilewati.
)

REM -------------------------------------------------- 7. Aset & pintasan ----
echo    [7/7] Membangun tampilan dan membuat pintasan...
call npm install
if errorlevel 1 goto :npm_gagal
call npm run build
if errorlevel 1 goto :npm_gagal

"%PHP%" artisan storage:link >nul 2>&1
call "%~dp0buat-shortcut.bat" >nul 2>&1

echo.
echo    ==========================================
echo    Pemasangan selesai.
echo.
echo    Klik pintasan "ERP Peak" di desktop untuk memulai,
echo    atau jalankan jalankan-erp.bat dari folder ini.
echo.
if "!ADA_DATA!"=="0" (
    echo    Masuk dengan:  admin@bonecomtricom.com  /  password
    echo    Segera ganti kata sandinya lewat menu Profil.
)
echo.
pause
exit /b 0


REM =========================================================== subrutin ======

:cek_port
set "ADA=0"
for /f %%L in ('netstat -ano ^| findstr /R /C:"LISTENING" ^| findstr /C:":%~1 "') do set "ADA=1"
exit /b 0

:tunggu_port
set /a _sisa=%~2
:tunggu_ulang
call :cek_port %~1
if "!ADA!"=="1" exit /b 0
set /a _sisa-=1
if !_sisa! leq 0 exit /b 1
call :jeda 1
goto :tunggu_ulang

REM Jeda %1 detik. Memakai ping, bukan timeout: timeout menolak berjalan
REM ketika stdin dialihkan, sehingga jedanya menjadi nol tanpa terlihat.
:jeda
ping -n %~1 127.0.0.1 >nul 2>&1
exit /b 0


REM ============================================================== galat ======

:prasyarat_kurang
echo.
echo    Pemasangan dihentikan: ada prasyarat yang belum terpasang.
echo.
echo    Pasang dulu yang bertanda "-" di atas:
echo      XAMPP ^(PHP 8.2 + MySQL^)  https://www.apachefriends.org
echo      Composer                   https://getcomposer.org/download
echo      Node.js LTS                https://nodejs.org
echo.
echo    Setelah terpasang, TUTUP jendela ini lalu jalankan lagi
echo    supaya PATH yang baru terbaca.
goto :berhenti

:tanpa_env_example
echo.
echo    GAGAL: .env.example tidak ada di folder ini.
echo    Pastikan seluruh isi folder proyek tersalin, bukan sebagian.
goto :berhenti

:mysql_gagal
echo.
echo    GAGAL: MySQL tidak siap dalam 30 detik.
echo    Buka XAMPP Control Panel dan periksa pesan galat MySQL.
goto :berhenti

:db_gagal
echo.
echo    GAGAL: basis data tidak bisa dibuat.
echo    Periksa pengguna dan kata sandi MySQL di berkas .env.
goto :berhenti

:composer_gagal
echo.
echo    GAGAL saat composer install.
echo    Periksa koneksi internet, lalu jalankan ulang skrip ini.
goto :berhenti

:migrasi_gagal
echo.
echo    GAGAL saat membuat tabel.
echo    Periksa pengaturan DB_ pada berkas .env.
goto :berhenti

:seed_gagal
echo.
echo    GAGAL saat mengisi data awal.
goto :berhenti

:hitung_gagal
echo.
echo    GAGAL: tidak bisa membaca jumlah pengguna dari basis data.
echo    Pemasangan dihentikan supaya data yang sudah ada tidak tertimpa
echo    data contoh. Periksa pengguna dan kata sandi MySQL pada berkas .env.
goto :berhenti

:npm_gagal
echo.
echo    GAGAL saat membangun tampilan.
echo    Periksa koneksi internet, lalu jalankan ulang skrip ini.
goto :berhenti

:berhenti
echo.
pause
exit /b 1
