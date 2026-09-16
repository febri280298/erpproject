@echo off
setlocal
title ERP Peak - Membuat shortcut

REM ===========================================================================
REM  Membuat shortcut ERP Peak di desktop.
REM
REM  Lokasi desktop dibaca dari Windows, bukan ditebak dari %USERPROFILE%,
REM  karena desktop yang dialihkan ke OneDrive berada di tempat lain.
REM ===========================================================================

set "PROYEK=%~dp0"
if "%PROYEK:~-1%"=="\" set "PROYEK=%PROYEK:~0,-1%"

echo.
echo    Membuat shortcut di desktop...
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$d = [Environment]::GetFolderPath('Desktop');" ^
  "$p = '%PROYEK%';" ^
  "$w = New-Object -ComObject WScript.Shell;" ^
  "$s = $w.CreateShortcut((Join-Path $d 'ERP Peak.lnk'));" ^
  "$s.TargetPath = Join-Path $p 'jalankan-erp.bat';" ^
  "$s.WorkingDirectory = $p;" ^
  "$s.IconLocation = (Join-Path $p 'public\desktop\erp.ico');" ^
  "$s.Description = 'Nyalakan MySQL, server aplikasi, lalu buka browser';" ^
  "$s.Save();" ^
  "$s2 = $w.CreateShortcut((Join-Path $d 'ERP Peak - Matikan.lnk'));" ^
  "$s2.TargetPath = Join-Path $p 'hentikan-erp.bat';" ^
  "$s2.WorkingDirectory = $p;" ^
  "$s2.IconLocation = (Join-Path $p 'public\desktop\erp.ico');" ^
  "$s2.Description = 'Hentikan server aplikasi dan MySQL';" ^
  "$s2.Save();" ^
  "Write-Host ('   Selesai. Shortcut ada di: ' + $d)"

echo.

REM Berhenti menunggu tombol HANYA bila skrip ini diklik sendiri.
REM
REM pasang-erp.bat memanggilnya dengan output dibungkam (>nul 2>&1), dan itu
REM ikut membungkam tulisan "Press any key to continue" milik pause. Layarnya
REM lalu diam tanpa sebab yang terlihat — tampak menggantung, padahal hanya
REM menunggu tombol yang tak seorang pun tahu harus ditekan. Pernah terjadi di
REM depan customer.
if /I not "%~1"=="/tanpajeda" pause
