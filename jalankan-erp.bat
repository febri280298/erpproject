@echo off
setlocal enabledelayedexpansion
title ERP Peak - Menjalankan

REM ===========================================================================
REM  Menyalakan ERP Peak untuk dipakai di komputer ini.
REM
REM  Urutannya penting: MySQL lebih dulu, baru server aplikasi. Session dan
REM  cache disimpan di database, jadi server yang dinyalakan sebelum MySQL siap
REM  akan menggantung di setiap permintaan tanpa pesan galat apa pun.
REM
REM  Skrip ini aman dijalankan berulang: yang sudah menyala tidak dinyalakan
REM  dua kali, hanya browsernya yang dibuka.
REM ===========================================================================

cd /d "%~dp0"

set "XAMPP=C:\xampp"
set "PORT=7001"
set "URL=http://127.0.0.1:%PORT%"

REM PHP milik XAMPP dipakai langsung, tidak bergantung pada PATH.
set "PHP=%XAMPP%\php\php.exe"
if not exist "%PHP%" set "PHP=php"

echo.
echo    ERP Peak
echo    ==========================================
echo.

REM ------------------------------------------------------------ 1. MySQL ----
call :cek_port 3306
if "!ADA!"=="1" (
    echo    [1/3] MySQL sudah berjalan.
) else (
    echo    [1/3] Menjalankan MySQL...
    if not exist "%XAMPP%\mysql\bin\mysqld.exe" goto :tidak_ada_xampp
    start "" /B "%XAMPP%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP%\mysql\bin\my.ini"
    call :tunggu_port 3306 30
    if errorlevel 1 goto :mysql_gagal
    echo          MySQL siap.
)

REM --------------------------------------------------- 2. Server aplikasi ----
call :cek_port %PORT%
if "!ADA!"=="1" (
    echo    [2/3] Server sudah berjalan di port %PORT%.
) else (
    echo    [2/3] Menjalankan server aplikasi...
    start "ERP Peak Server" /MIN cmd /c ""%PHP%" artisan serve --host=127.0.0.1 --port=%PORT%"
    call :tunggu_port %PORT% 30
    if errorlevel 1 goto :server_gagal
    echo          Server siap.
)

REM ------------------------------------------------------------ 3. Browser ---
echo    [3/3] Membuka browser...
start "" "%URL%"

echo.
echo    Siap dipakai:  %URL%
echo    Untuk mematikan, jalankan: hentikan-erp.bat
echo.
call :jeda 4
exit /b 0


REM =========================================================== subrutin ======

REM Mengisi ADA=1 bila ada yang mendengarkan di port %1.
:cek_port
set "ADA=0"
for /f %%L in ('netstat -ano ^| findstr /R /C:"LISTENING" ^| findstr /C:":%~1 "') do set "ADA=1"
exit /b 0

REM Menunggu port %1 terbuka, maksimal %2 detik. errorlevel 1 bila kehabisan waktu.
:tunggu_port
set /a _sisa=%~2
:tunggu_ulang
call :cek_port %~1
if "!ADA!"=="1" exit /b 0
set /a _sisa-=1
if !_sisa! leq 0 exit /b 1
call :jeda 1
goto :tunggu_ulang


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

:server_gagal
echo.
echo    GAGAL: server aplikasi tidak siap dalam 30 detik.
echo    Periksa jendela "ERP Peak Server" yang terminimalkan untuk pesan galatnya.
goto :berhenti

:berhenti
echo.
pause
exit /b 1

REM Jeda %1 detik. Memakai ping, bukan timeout: timeout menolak berjalan
REM ketika stdin dialihkan, sehingga jedanya menjadi nol tanpa terlihat.
:jeda
ping -n %~1 127.0.0.1 >nul 2>&1
exit /b 0
