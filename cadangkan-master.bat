@echo off
setlocal enabledelayedexpansion
title ERP Peak - Cadangkan Data Master

REM ===========================================================================
REM  Membuat cadangan untuk DISERAHKAN KE CUSTOMER: seluruh data master ikut,
REM  seluruh transaksi kosong.
REM
REM  Bedanya dengan cadangkan-db.bat: yang itu menyalin apa adanya, termasuk PO,
REM  faktur, surat jalan, dan jurnal milik kita. Berkas seperti itu tidak boleh
REM  masuk ke pembukuan customer.
REM
REM  Caranya dua lintasan:
REM    1. struktur SELURUH tabel, tanpa satu baris pun data;
REM    2. data tabel master saja, ditambahkan di belakangnya.
REM  Tabel transaksi jadi ikut terbentuk lengkap, hanya isinya kosong.
REM
REM  Daftar MASTER di bawah sengaja berupa daftar-yang-diikutkan, bukan
REM  daftar-yang-dibuang. Bila kelak ada tabel transaksi baru, tabel itu
REM  otomatis kosong karena tidak terdaftar. Kebalikannya jauh lebih berbahaya:
REM  satu tabel transaksi yang lupa dibuang akan menyelinap ke pembukuan
REM  customer tanpa terlihat.
REM ===========================================================================

cd /d "%~dp0"

set "XAMPP=C:\xampp"
set "DB=erppeak"
set "DBUSER=root"
set "TUJUAN=%~dp0backup"

set "MYSQL=%XAMPP%\mysql\bin\mysql.exe"
set "DUMP=%XAMPP%\mysql\bin\mysqldump.exe"

REM --------------------------------------------------------------------------
REM  Tabel yang datanya IKUT dibawa.
REM
REM  migrations ikut karena tanpanya "artisan migrate" di komputer customer akan
REM  mengira belum ada satu pun migrasi yang pernah jalan, lalu mencoba membuat
REM  ulang tabel yang sudah ada dan berhenti dengan galat.
REM
REM  number_sequences ikut supaya prefix dan pola penomoran yang sudah disetel
REM  tidak hilang; nomor terakhirnya dikembalikan ke 1 di bagian bawah skrip.
REM --------------------------------------------------------------------------
set "MASTER=accounts bom_items boms departments employees leave_types migrations"
set "MASTER=%MASTER% model_has_permissions model_has_roles number_sequences"
set "MASTER=%MASTER% partners payment_terms permissions positions price_levels"
set "MASTER=%MASTER% product_categories product_customer_prices product_prices"
set "MASTER=%MASTER% product_supplier_prices products role_has_permissions roles"
set "MASTER=%MASTER% settings taxes uoms users warehouses"

if not exist "%TUJUAN%" mkdir "%TUJUAN%"

for /f "tokens=1-6 delims=/:. " %%a in ("%date% %time%") do set "CAP=%%c%%b%%a-%%d%%e"
set "CAP=%CAP: =0%"
set "BERKAS=%TUJUAN%\erppeak-master-%CAP%.sql"

echo.
echo    ERP Peak - Cadangan Data Master
echo    ==========================================
echo.
echo    Data master ikut. Transaksi dikosongkan.
echo.

REM ------------------------------------------------------------ MySQL ------
call :cek_port 3306
if not "!ADA!"=="1" (
    echo    Menyalakan MySQL...
    if not exist "%XAMPP%\mysql\bin\mysqld.exe" goto :tidak_ada_xampp
    start "" /B "%XAMPP%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP%\mysql\bin\my.ini"
    call :tunggu_port 3306 30
    if errorlevel 1 goto :mysql_gagal
)

REM ------------------------------------------- Periksa daftar MASTER -------
REM  Nama tabel yang berubah karena migrasi baru harus ketahuan SEKARANG. Bila
REM  dibiarkan, dumpnya tetap jadi dan tampak wajar, tetapi data master yang
REM  namanya berganti hilang diam-diam dan baru terasa di tempat customer.
echo    [1/4] Memeriksa daftar tabel master...
set "CEK=%TEMP%\erp-cek-tabel.txt"
set "HILANG="
for %%T in (%MASTER%) do (
    "%MYSQL%" -u %DBUSER% -D %DB% -N -B -e "SHOW TABLES LIKE '%%T';" > "%CEK%" 2>nul
    for %%S in ("%CEK%") do if "%%~zS"=="0" (
        echo          - tabel "%%T" tidak ada di basis data
        set "HILANG=1"
    )
)
del "%CEK%" >nul 2>&1
if defined HILANG goto :daftar_usang
echo          Semua tabel master ditemukan.

REM ------------------------------------------------- 2. Struktur -----------
echo    [2/4] Menyalin struktur seluruh tabel...
"%DUMP%" -u %DBUSER% --databases %DB% --no-data --routines --events ^
    --default-character-set=utf8mb4 > "%BERKAS%" 2>nul
if errorlevel 1 goto :dump_gagal
for %%S in ("%BERKAS%") do if "%%~zS"=="0" goto :dump_gagal

REM ------------------------------------------------- 3. Data master --------
echo    [3/4] Menyalin data master...
"%DUMP%" -u %DBUSER% --no-create-info --complete-insert --single-transaction ^
    --default-character-set=utf8mb4 %DB% %MASTER% >> "%BERKAS%" 2>nul
if errorlevel 1 goto :dump_gagal

REM ------------------------------------------ 4. Penomoran ke awal ---------
REM  Nomor dokumen terakhir milik kita tidak boleh diteruskan. Customer harus
REM  mulai dari PO-0001, bukan melanjutkan urutan pembukuan orang lain.
echo    [4/4] Mengembalikan penomoran dokumen ke awal...
>>"%BERKAS%" echo.
>>"%BERKAS%" echo -- Penomoran dikembalikan ke awal: nomor terakhir milik komputer asal
>>"%BERKAS%" echo -- tidak boleh diteruskan di pembukuan customer.
>>"%BERKAS%" echo UPDATE number_sequences SET next_number = 1, period_year = NULL, period_month = NULL;

for %%S in ("%BERKAS%") do set "UKURAN=%%~zS"

echo.
echo    ==========================================
echo    Selesai.
echo.
echo      Berkas : %BERKAS%
echo      Ukuran : !UKURAN! byte
echo.
echo    Bawa berkas ini ke komputer customer, taruh di folder backup\,
echo    lalu jalankan pulihkan-db.bat di sana.
echo.
echo    Sesudah dipulihkan, periksa dengan langkah 7 pada
echo    PANDUAN-SERAH-TERIMA.md: seluruh hitungan transaksi harus 0.
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

:tidak_ada_xampp
echo.
echo    GAGAL: MySQL tidak ditemukan di %XAMPP%\mysql\bin\mysqld.exe
echo    Ubah baris "set XAMPP=" di berkas ini bila XAMPP dipasang di tempat lain.
goto :berhenti

:mysql_gagal
echo.
echo    GAGAL: MySQL tidak siap dalam 30 detik.
echo    Buka XAMPP Control Panel dan periksa pesan galatnya.
goto :berhenti

:daftar_usang
echo.
echo    GAGAL: daftar tabel master di skrip ini sudah tidak cocok dengan
echo    basis data. Ada tabel master yang berganti nama atau dihapus.
echo.
echo    Perbaiki baris "set MASTER=" di bagian atas berkas ini lebih dulu.
echo    Cadangan TIDAK dibuat, supaya tidak ada data master yang hilang
echo    diam-diam di komputer customer.
goto :berhenti

:dump_gagal
echo.
echo    GAGAL membuat cadangan.
echo    Periksa nama basis data dan pengguna MySQL pada bagian atas berkas ini.
if exist "%BERKAS%" del "%BERKAS%"
goto :berhenti

:berhenti
echo.
pause
exit /b 1
