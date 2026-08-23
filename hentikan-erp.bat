@echo off
setlocal
title ERP Peak - Menghentikan

REM ===========================================================================
REM  Mematikan server aplikasi dan MySQL.
REM
REM  Server dimatikan lewat pemilik portnya, bukan dengan membunuh semua
REM  php.exe: proses PHP lain yang kebetulan berjalan tidak boleh ikut mati.
REM ===========================================================================

set "XAMPP=C:\xampp"
set "PORT=7001"

echo.
echo    Menghentikan ERP Peak...
echo.

set "KETEMU="
for /f "tokens=5" %%P in ('netstat -ano ^| findstr /R /C:"LISTENING" ^| findstr /C:":%PORT% "') do (
    taskkill /PID %%P /T /F >nul 2>&1
    set "KETEMU=1"
)
if defined KETEMU (echo    Server aplikasi dihentikan.) else (echo    Server aplikasi memang tidak berjalan.)

REM MySQL dimatikan baik-baik lebih dulu supaya tabelnya tidak ditutup paksa.
if exist "%XAMPP%\mysql\bin\mysqladmin.exe" (
    "%XAMPP%\mysql\bin\mysqladmin.exe" -u root shutdown >nul 2>&1
)

call :jeda 2
tasklist /FI "IMAGENAME eq mysqld.exe" | find /I "mysqld.exe" >nul
if errorlevel 1 (
    echo    MySQL dihentikan.
) else (
    echo    MySQL masih berjalan - dihentikan paksa.
    taskkill /IM mysqld.exe /F >nul 2>&1
)

echo.
call :jeda 3
exit /b 0

REM Jeda %1 detik. Memakai ping, bukan timeout: timeout menolak berjalan
REM ketika stdin dialihkan, sehingga jedanya menjadi nol tanpa terlihat.
:jeda
ping -n %~1 127.0.0.1 >nul 2>&1
exit /b 0
