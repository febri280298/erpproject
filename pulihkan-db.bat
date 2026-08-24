@echo off
setlocal enabledelayedexpansion
title ERP Peak - Pulihkan Data

REM ===========================================================================
REM  Memulihkan basis data dari berkas cadangan .sql.
REM
REM  MENIMPA SELURUH ISI BASIS DATA. Karena itu isi basis data yang sekarang
REM  dicadangkan lebih dulu secara otomatis sebelum ditimpa, dan penggunanya
REM  harus mengetik YA untuk melanjutkan.
REM
REM  Pemakaian:
REM    pulihkan-db.bat                  -> memakai cadangan terbaru di backup\
REM    pulihkan-db.bat berkas.sql       -> memakai berkas tertentu
REM    (atau seret berkas .sql ke atas ikon skrip ini)
REM ===========================================================================

cd /d "%~dp0"

set "XAMPP=C:\xampp"
set "DB=erppeak"
set "DBUSER=root"
set "TUJUAN=%~dp0backup"

set "SUMBER=%~1"
if "%SUMBER%"=="" (
    REM Tanpa argumen: ambil cadangan paling baru.
    for /f "delims=" %%F in ('dir /b /o-d "%TUJUAN%\*.sql" 2^>nul') do (
        if not defined SUMBER set "SUMBER=%TUJUAN%\%%F"
    )
)

if "%SUMBER%"=="" goto :tanpa_berkas
if not exist "%SUMBER%" goto :tanpa_berkas

for %%S in ("%SUMBER%") do set "UKURAN=%%~zS"

echo.
echo    PULIHKAN BASIS DATA
echo    ==========================================
echo.
echo      Dari   : %SUMBER%
echo      Ukuran : %UKURAN% byte
echo      Ke     : basis data "%DB%"
echo.
echo    SELURUH isi basis data "%DB%" yang sekarang akan DITIMPA.
echo    Isi yang sekarang dicadangkan dulu secara otomatis.
echo.

set "JAWAB="
set /p "JAWAB=   Ketik YA lalu Enter untuk melanjutkan: "
if /I not "%JAWAB%"=="YA" goto :dibatalkan

echo.
call :cek_port 3306
if not "!ADA!"=="1" (
    echo    Menyalakan MySQL...
    start "" /B "%XAMPP%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP%\mysql\bin\my.ini"
    call :tunggu_port 3306 30
    if errorlevel 1 goto :mysql_gagal
)

REM Cadangan pengaman: dibuat lebih dulu supaya kesalahan memilih berkas masih
REM bisa dibatalkan. Nilainya baru terasa justru ketika sudah terlambat.
if not exist "%TUJUAN%" mkdir "%TUJUAN%"
for /f "tokens=1-6 delims=/:. " %%a in ("%date% %time%") do set "CAP=%%c%%b%%a-%%d%%e"
set "CAP=%CAP: =0%"
set "AMAN=%TUJUAN%\sebelum-pulih-%CAP%.sql"

echo    Mencadangkan isi yang sekarang...
"%XAMPP%\mysql\bin\mysqldump.exe" -u %DBUSER% --databases %DB% ^
    --routines --events --single-transaction --default-character-set=utf8mb4 > "%AMAN%" 2>nul
echo          %AMAN%

echo    Memulihkan...
"%XAMPP%\mysql\bin\mysql.exe" -u %DBUSER% --default-character-set=utf8mb4 < "%SUMBER%"
if errorlevel 1 goto :pulih_gagal

REM Migrasi dijalankan setelahnya: cadangan bisa berasal dari versi yang lebih
REM lama daripada kode di komputer ini.
set "PHP=%XAMPP%\php\php.exe"
if not exist "%PHP%" set "PHP=php"
echo    Menyesuaikan struktur tabel dengan versi aplikasi...
"%PHP%" artisan migrate --force

echo.
echo    Selesai. Data dari cadangan sudah aktif.
echo    Cadangan isi sebelumnya tersimpan di:
echo      %AMAN%
echo.
pause
exit /b 0


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

:jeda
ping -n %~1 127.0.0.1 >nul 2>&1
exit /b 0

:tanpa_berkas
echo.
echo    Tidak ada berkas cadangan yang bisa dipulihkan.
echo    Taruh berkas .sql di folder backup\, atau seret berkasnya
echo    ke atas ikon skrip ini.
goto :berhenti

:dibatalkan
echo.
echo    Dibatalkan. Tidak ada yang diubah.
goto :berhenti

:mysql_gagal
echo.
echo    GAGAL: MySQL tidak siap dalam 30 detik.
goto :berhenti

:pulih_gagal
echo.
echo    GAGAL memulihkan. Basis data mungkin dalam keadaan setengah jadi.
echo    Pulihkan dari cadangan pengaman:
echo      %AMAN%
goto :berhenti

:berhenti
echo.
pause
exit /b 1
