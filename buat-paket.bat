@echo off
setlocal enabledelayedexpansion
title ERP Peak - Buat Paket Serah Terima

REM ===========================================================================
REM  Menyusun SATU folder yang tinggal disalin ke flashdisk lalu dibawa ke
REM  tempat customer.
REM
REM  Isinya sengaja dibuat lengkap sampai tidak perlu internet sama sekali di
REM  sana: vendor\ dan public\build\ ikut, sehingga pasang-erp.bat melewati
REM  composer install dan npm. Yang perlu dipasang di komputer customer tinggal
REM  XAMPP. Ini bukan soal cepat, tapi soal tidak berdiri di depan customer
REM  dengan pemasangan yang macet menunggu jaringan mereka.
REM
REM  node_modules\ TIDAK ikut - 352 MB, dan hanya alat untuk membangun
REM  public\build\ yang hasilnya sudah ikut.
REM
REM  Pemakaian:
REM    buat-paket.bat                 -> folder PAKET-ERP-PEAK di sebelah proyek
REM    buat-paket.bat D:\Paket        -> folder tertentu
REM ===========================================================================

cd /d "%~dp0"

set "SUMBER=%~dp0"

set "PAKET=%~1"
if "%PAKET%"=="" set "PAKET=%~dp0..\PAKET-ERP-PEAK"
for %%P in ("%PAKET%") do set "PAKET=%%~fP"

set "APP=%PAKET%\2-APLIKASI\Erppeak"

echo.
echo    ERP Peak - Paket Serah Terima
echo    ==========================================
echo.
echo    Tujuan : %PAKET%
echo.

REM ------------------------------------------- 1. Kelengkapan sumber -------
REM  Semua diperiksa lebih dulu, sebelum satu berkas pun disalin. Paket yang
REM  setengah jadi jauh lebih berbahaya daripada paket yang gagal dibuat:
REM  yang setengah jadi baru ketahuan di tempat customer.
echo    [1/6] Memeriksa kelengkapan...
set "KURANG="

if not exist "%SUMBER%vendor\autoload.php" (
    echo          - folder vendor\ belum ada. Jalankan: composer install
    set "KURANG=1"
)
if not exist "%SUMBER%public\build\manifest.json" (
    echo          - folder public\build\ belum ada. Jalankan: npm install ^&^& npm run build
    set "KURANG=1"
)
if not exist "%SUMBER%.env.customer" (
    echo          - berkas .env.customer tidak ada di folder proyek
    set "KURANG=1"
)

REM Cadangan master terbaru. Yang dipakai selalu yang paling baru, dan namanya
REM ditampilkan supaya ketahuan bila ternyata cadangan lama yang terbawa.
set "MASTERSQL="
set "MASTERNAMA="
for /f "delims=" %%F in ('dir /b /o-d "%SUMBER%backup\erppeak-master-*.sql" 2^>nul') do (
    if not defined MASTERSQL (
        set "MASTERSQL=%SUMBER%backup\%%F"
        set "MASTERNAMA=%%F"
    )
)
if defined KURANG goto :belum_lengkap

REM  Paket tanpa data master itu sah - customer yang memang mulai dari nol
REM  memakainya. Yang tidak boleh adalah terjadi tanpa disadari, karena
REM  akibatnya baru terasa di tempat customer ketika datanya ternyata kosong.
REM  Karena itu bukan ditolak, melainkan harus dijawab.
if not defined MASTERSQL (
    echo.
    echo          Belum ada berkas backup\erppeak-master-*.sql.
    echo          Paket akan dibuat TANPA data master: customer mulai dari
    echo          data awal bawaan, bukan dari data yang sudah kita rapikan.
    echo.
    echo          Bila itu tidak disengaja, batalkan lalu jalankan dulu
    echo          cadangkan-master.bat.
    echo.
    set "JAWAB="
    set /p "JAWAB=         Ketik YA lalu Enter untuk lanjut tanpa data master: "
    if /I not "!JAWAB!"=="YA" goto :dibatalkan
) else (
    echo          Lengkap. Data master: !MASTERNAMA!
)

REM ------------------------------------------- 2. Folder tujuan ------------
echo    [2/6] Menyiapkan folder tujuan...
if exist "%PAKET%\" (
    REM Hanya folder yang memang pernah dibuat skrip ini yang boleh dihapus.
    REM Tanpa penjaga ini, satu salah ketik pada argumen bisa menghapus folder
    REM yang sama sekali tidak ada hubungannya.
    if not exist "%PAKET%\BACA-DULU.txt" goto :tujuan_asing

    echo.
    echo          Folder paket sudah ada dan akan DITIMPA.
    set "JAWAB="
    set /p "JAWAB=         Ketik YA lalu Enter untuk melanjutkan: "
    if /I not "!JAWAB!"=="YA" goto :dibatalkan
    rmdir /s /q "%PAKET%"
)

mkdir "%APP%" 2>nul
mkdir "%PAKET%\1-PEMASANG" 2>nul
mkdir "%PAKET%\3-DATA" 2>nul
if not exist "%APP%" goto :tujuan_gagal

REM ------------------------------------------- 3. Salin aplikasi -----------
REM  /XJ wajib: public\storage adalah tautan ke folder lain di komputer ini.
REM  Tanpa /XJ tautannya ikut tersalin dan menunjuk ke jalur yang tidak ada di
REM  komputer customer - storage:link membuatnya ulang di sana.
echo    [3/6] Menyalin aplikasi ^(vendor ikut, node_modules tidak^)...
robocopy "%SUMBER%." "%APP%" /E /XJ /NFL /NDL /NJH /NJS /NP ^
    /XD "%SUMBER%node_modules" "%SUMBER%.git" "%SUMBER%backup" ^
        "%SUMBER%public\storage" "%SUMBER%.vscode" "%SUMBER%.idea" ^
        "%SUMBER%tests\e2e\hasil" ^
    /XF ".env" ".env.customer" "*.log" ".phpunit.result.cache" >nul

REM robocopy memakai 0-7 untuk berhasil dan 8 ke atas untuk gagal; 1 berarti
REM "ada berkas tersalin", bukan galat. Karena itu yang diperiksa hanya 8.
if errorlevel 8 goto :salin_gagal
ver >nul

REM Sisa pemakaian di komputer ini yang tidak ada gunanya dibawa.
del /q /s "%APP%\storage\framework\views\*.php" >nul 2>&1

REM ------------------------------------------- 4. Berkas .env --------------
REM  .env sengaja sudah disiapkan di dalam paket, bukan dibuat di tempat
REM  customer. APP_ENV=production di dalamnya yang mencegah transaksi contoh
REM  ikut terpasang - langkah yang paling mudah terlupa justru sudah selesai
REM  sebelum berangkat.
echo    [4/6] Memasang .env siap produksi...
copy /Y "%SUMBER%.env.customer" "%APP%\.env" >nul
if not exist "%APP%\.env" goto :env_gagal

REM ------------------------------------------- 5. Data master --------------
if defined MASTERSQL (
    echo    [5/6] Menyalin data master...
    copy /Y "%MASTERSQL%" "%PAKET%\3-DATA\" >nul
    if not exist "%PAKET%\3-DATA\%MASTERNAMA%" goto :data_gagal
) else (
    echo    [5/6] Tanpa data master - customer mulai dari data awal bawaan.
    >"%PAKET%\3-DATA\TIDAK-ADA-DATA-MASTER.txt" echo    Paket ini sengaja dibuat tanpa data master.
    >>"%PAKET%\3-DATA\TIDAK-ADA-DATA-MASTER.txt" echo.
    >>"%PAKET%\3-DATA\TIDAK-ADA-DATA-MASTER.txt" echo    Customer mulai dari data awal bawaan: bagan akun, satuan,
    >>"%PAKET%\3-DATA\TIDAK-ADA-DATA-MASTER.txt" echo    peran, pengguna, dan contoh produk toko bangunan.
    >>"%PAKET%\3-DATA\TIDAK-ADA-DATA-MASTER.txt" echo.
    >>"%PAKET%\3-DATA\TIDAK-ADA-DATA-MASTER.txt" echo    Langkah 5b pada PANDUAN-SERAH-TERIMA.md dilewati.
)

copy /Y "%SUMBER%PANDUAN-SERAH-TERIMA.md" "%PAKET%\" >nul
copy /Y "%SUMBER%PANDUAN-INSTALASI.md" "%PAKET%\" >nul

REM ------------------------------------------- 6. Catatan -----------------
echo    [6/6] Menulis catatan...

set "NOTA=%PAKET%\1-PEMASANG\TARUH-PEMASANG-XAMPP-DI-SINI.txt"
>"%NOTA%" echo    Taruh berkas pemasang XAMPP di folder ini.
>>"%NOTA%" echo.
>>"%NOTA%" echo    Unduh dari  https://www.apachefriends.org
>>"%NOTA%" echo    Pilih versi dengan PHP 8.2 atau lebih baru.
>>"%NOTA%" echo.
>>"%NOTA%" echo    Composer dan Node.js TIDAK perlu diunduh. Pustaka dan
>>"%NOTA%" echo    tampilan sudah ikut di dalam folder 2-APLIKASI, jadi
>>"%NOTA%" echo    pemasangan di komputer customer tidak menyentuh internet.
>>"%NOTA%" echo.
>>"%NOTA%" echo    Unduh sekarang, di kantor. Jangan diandalkan bisa mengunduh
>>"%NOTA%" echo    di tempat customer.

call :tulis_baca_dulu

REM Hasilnya dibaca lewat berkas sementara, bukan lewat for /f. Tanda kutip dan
REM tanda pipa di dalam perintah PowerShell bertabrakan dengan tanda kutip
REM pembungkus for /f: perintahnya gagal diam-diam dan ukurannya jadi 0 MB
REM padahal paketnya baik-baik saja. Jebakan yang sama ada di pasang-erp.bat.
set "UK=%TEMP%\erp-ukuran-paket.txt"
set "MB="
powershell -NoProfile -Command "[math]::Round(((Get-ChildItem -LiteralPath '%PAKET%' -Recurse -File -ErrorAction SilentlyContinue | Measure-Object Length -Sum).Sum)/1MB)" > "%UK%" 2>nul
if exist "%UK%" set /p MB=<"%UK%"
del "%UK%" >nul 2>&1

echo.
echo    ==========================================
echo    Paket selesai.
echo.
echo      Folder : %PAKET%
if defined MB echo      Ukuran : !MB! MB  ^(belum termasuk pemasang XAMPP^)
echo.
echo    Sisa yang harus dikerjakan sekarang, di kantor:
echo      1. Unduh pemasang XAMPP, taruh di folder 1-PEMASANG.
echo      2. Salin seluruh folder ini ke flashdisk.
echo.
echo    Di tempat customer, buka BACA-DULU.txt lebih dulu.
echo.
pause
exit /b 0


REM =========================================================== subrutin ======

:tulis_baca_dulu
set "B=%PAKET%\BACA-DULU.txt"
>"%B%" echo    ERP PEAK - PAKET SERAH TERIMA
>>"%B%" echo    ==========================================
>>"%B%" echo.
>>"%B%" echo    Isi folder ini:
>>"%B%" echo.
>>"%B%" echo      1-PEMASANG\   pemasang XAMPP
>>"%B%" echo      2-APLIKASI\   folder aplikasi, sudah lengkap
>>"%B%" echo      3-DATA\       data master dari kantor, transaksi kosong
>>"%B%" echo      PANDUAN-SERAH-TERIMA.md   langkah lengkap beserta checklist
>>"%B%" echo.
>>"%B%" echo.
>>"%B%" echo    URUTAN DI KOMPUTER CUSTOMER
>>"%B%" echo    ------------------------------------------
>>"%B%" echo.
>>"%B%" echo    1. Pasang XAMPP dari folder 1-PEMASANG. Biarkan di C:\xampp.
>>"%B%" echo       Composer dan Node.js TIDAK perlu dipasang.
>>"%B%" echo.
>>"%B%" echo    2. Salin folder 2-APLIKASI\Erppeak ke D:\ERP\Erppeak.
>>"%B%" echo       Jangan ditaruh di Desktop, Documents, atau OneDrive.
>>"%B%" echo.
>>"%B%" echo    3. Buka D:\ERP\Erppeak\.env dengan Notepad.
>>"%B%" echo       Ubah baris APP_NAME menjadi nama perusahaan customer, simpan.
>>"%B%" echo       Baris lainnya sudah benar - jangan diubah.
>>"%B%" echo.
>>"%B%" echo    4. Klik dua kali pasang-erp.bat. Tunggu sampai selesai.
>>"%B%" echo       Tidak perlu internet.
>>"%B%" echo.
>>"%B%" echo    5. Salin berkas .sql dari 3-DATA ke D:\ERP\Erppeak\backup\
>>"%B%" echo       lalu klik dua kali pulihkan-db.bat. Ketik YA.
>>"%B%" echo.
>>"%B%" echo    6. Buka PANDUAN-SERAH-TERIMA.md, kerjakan Langkah 7 sampai 15:
>>"%B%" echo       buktikan transaksi nol, isi profil perusahaan, lalu ganti
>>"%B%" echo       semua kata sandi.
>>"%B%" echo.
>>"%B%" echo.
>>"%B%" echo    Alamat aplikasi setelah terpasang:  http://127.0.0.1:7001
>>"%B%" echo    Masuk pertama kali:  admin@bonecomtricom.com  /  password
>>"%B%" echo.
>>"%B%" echo    JANGAN diserahkan sebelum semua kata sandi diganti.
exit /b 0


REM ============================================================== galat ======

:belum_lengkap
echo.
echo    Paket TIDAK dibuat: ada bagian yang belum siap.
echo    Perbaiki yang bertanda "-" di atas, lalu jalankan lagi.
goto :berhenti

:tujuan_asing
echo.
echo    GAGAL: folder tujuan sudah ada dan bukan buatan skrip ini.
echo      %PAKET%
echo.
echo    Tidak ada yang dihapus. Kosongkan sendiri folder itu, atau
echo    sebutkan folder lain:  buat-paket.bat D:\Paket-Lain
goto :berhenti

:tujuan_gagal
echo.
echo    GAGAL membuat folder tujuan:
echo      %PAKET%
echo    Periksa apakah drive-nya ada dan tidak berstatus hanya-baca.
goto :berhenti

:salin_gagal
echo.
echo    GAGAL menyalin aplikasi.
echo    Periksa ruang kosong di drive tujuan.
goto :berhenti

:env_gagal
echo.
echo    GAGAL menyalin .env.customer menjadi .env.
goto :berhenti

:data_gagal
echo.
echo    GAGAL menyalin berkas data master.
goto :berhenti

:dibatalkan
echo.
echo    Dibatalkan. Tidak ada yang diubah.
goto :berhenti

:berhenti
echo.
pause
exit /b 1
