@echo off
setlocal enabledelayedexpansion
title ERP Peak - Cadangkan Data

REM ===========================================================================
REM  Menyimpan seluruh isi basis data ke satu berkas .sql.
REM
REM  Berkas inilah yang dibawa ke komputer lain. Berkas proyek saja tidak cukup:
REM  produk, mitra, dokumen, dan pengaturan semuanya ada di basis data, bukan
REM  di dalam folder proyek.
REM ===========================================================================

cd /d "%~dp0"

set "XAMPP=C:\xampp"
set "DB=erppeak"
set "DBUSER=root"
set "TUJUAN=%~dp0backup"

if not exist "%TUJUAN%" mkdir "%TUJUAN%"

REM Nama berkas memuat tanggal dan jam agar cadangan lama tidak tertimpa.
for /f "tokens=1-6 delims=/:. " %%a in ("%date% %time%") do set "CAP=%%c%%b%%a-%%d%%e"
set "CAP=%CAP: =0%"
set "BERKAS=%TUJUAN%\erppeak-%CAP%.sql"

echo.
echo    Mencadangkan basis data...
echo.

call :cek_port 3306
if not "!ADA!"=="1" (
    echo    MySQL belum berjalan. Menyalakan...
    start "" /B "%XAMPP%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP%\mysql\bin\my.ini"
    call :tunggu_port 3306 30
    if errorlevel 1 goto :mysql_gagal
)

REM --routines dan --events ikut disertakan supaya tidak ada bagian basis data
REM yang tertinggal diam-diam saat dipulihkan di tempat lain.
"%XAMPP%\mysql\bin\mysqldump.exe" -u %DBUSER% --databases %DB% ^
    --routines --events --single-transaction --default-character-set=utf8mb4 > "%BERKAS%" 2>nul

if errorlevel 1 goto :dump_gagal
for %%S in ("%BERKAS%") do set "UKURAN=%%~zS"
if "!UKURAN!"=="0" goto :dump_gagal

echo    Selesai.
echo.
echo      Berkas : %BERKAS%
echo      Ukuran : !UKURAN! byte
echo.
echo    Bawa berkas ini bersama folder proyek ke komputer tujuan,
echo    lalu jalankan pulihkan-db.bat di sana.
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

:mysql_gagal
echo.
echo    GAGAL: MySQL tidak siap dalam 30 detik.
goto :berhenti

:dump_gagal
echo.
echo    GAGAL membuat cadangan.
echo    Periksa nama basis data dan pengguna MySQL pada berkas ini.
if exist "%BERKAS%" del "%BERKAS%"
goto :berhenti

:berhenti
echo.
pause
exit /b 1
