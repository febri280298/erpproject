@echo off
setlocal enabledelayedexpansion
title ERP Peak - Cetak Ceklist Serah Terima

REM ===========================================================================
REM  Membuat CEKLIST-SERAH-TERIMA.pdf dari CEKLIST-SERAH-TERIMA.md, lalu
REM  membukanya supaya tinggal dicetak.
REM
REM  Yang disunting selalu berkas .md-nya; PDF dibuat ulang dari sana setiap
REM  kali. Dua berkas yang sama-sama bisa disunting akan berbeda isi tanpa ada
REM  yang menyadarinya, dan yang dibawa ke lapangan justru yang tertinggal.
REM
REM  Lembar ini yang dicentang pakai pena di tempat customer dan ditandatangani
REM  dua pihak. Langkah lengkapnya ada di PANDUAN-SERAH-TERIMA.md.
REM ===========================================================================

cd /d "%~dp0"

set "XAMPP=C:\xampp"
set "PHP=%XAMPP%\php\php.exe"
if not exist "%PHP%" set "PHP=php"

set "HASIL=%~dp0CEKLIST-SERAH-TERIMA.pdf"

echo.
echo    Membuat ceklist serah terima...
echo.

if not exist "%~dp0CEKLIST-SERAH-TERIMA.md" goto :tanpa_sumber
if not exist "%~dp0vendor\autoload.php" goto :tanpa_vendor

"%PHP%" "%~dp0scripts\ceklist-pdf.php" >nul
if errorlevel 1 goto :gagal
if not exist "%HASIL%" goto :gagal

for %%S in ("%HASIL%") do set /a KB=%%~zS/1024

echo    ==========================================
echo    Selesai.
echo.
echo      Berkas : CEKLIST-SERAH-TERIMA.pdf  ^(!KB! KB^)
echo.
echo    Cetak dua rangkap: satu untuk customer, satu dibawa pulang.
echo.

start "" "%HASIL%"
timeout /t 3 >nul
exit /b 0


REM ============================================================== galat ======

:tanpa_sumber
echo    GAGAL: CEKLIST-SERAH-TERIMA.md tidak ada di folder ini.
goto :berhenti

:tanpa_vendor
echo    GAGAL: folder vendor\ belum ada.
echo    Jalankan lebih dulu:  composer install
goto :berhenti

:gagal
echo    GAGAL membuat PDF.
echo.
echo    Jalankan perintah berikut untuk melihat pesan galatnya:
echo      "%PHP%" scripts\ceklist-pdf.php
goto :berhenti

:berhenti
echo.
pause
exit /b 1
