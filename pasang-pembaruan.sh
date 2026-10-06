#!/usr/bin/env bash
#
# Memasang paket pembaruan ERP Peak di VPS.
#
#   bash /root/pasang-pembaruan.sh /root/erppeak-update-<tanggal>.tar.gz
#
# Berupa skrip, bukan perintah tempel: 'exit' pada perintah yang ditempel
# langsung akan menutup sesi SSH-nya.

set -uo pipefail

SERVER_SAH="srv988090"
APP="/var/www/erppeak"
PAKET="${1:-}"

# Tidak boleh 'php' polos. CLI bawaan server ini PHP 8.1 — sengaja dibiarkan
# begitu karena aplikasi tetangga bergantung padanya — sedangkan ERP butuh
# 8.2 ke atas dan berjalan di FPM 8.3. 'php artisan' di sini langsung mati di
# pemeriksaan platform Composer.
PHP="php8.3"

if [ "$(hostname)" != "$SERVER_SAH" ]; then
    echo ">>> SALAH SERVER <<< ini '$(hostname)', seharusnya '$SERVER_SAH'. Berhenti."
    exit 1
fi
if [ ! -f "$PAKET" ]; then
    echo ">>> Paket tidak ditemukan: '$PAKET'. Berhenti."
    exit 1
fi
if [ ! -f "$APP/artisan" ]; then
    echo ">>> $APP bukan folder aplikasi Laravel. Berhenti."
    exit 1
fi

# Diperiksa sebelum apa pun diubah: kalau artisan tidak bisa dijalankan,
# pembersihan cache sesudah ekstrak juga gagal, dan lebih baik berhenti
# selagi berkas lama masih utuh.
if ! (cd "$APP" && "$PHP" artisan --version >/dev/null 2>&1); then
    echo ">>> '$PHP artisan' tidak bisa dijalankan di $APP. Berhenti, tidak ada yang diubah."
    exit 1
fi

# Angka, bukan nama. Berkas aplikasi di server ini dimiliki UID yang tidak
# punya nama (terbawa dari tarball pertama yang dibuat di Windows); stat %U
# mencetak UNKNOWN untuknya dan chown menolak nama itu.
PEMILIK=$(stat -c '%u:%g' "$APP/artisan")
# storage harus bisa ditulis proses web, jadi pemiliknya sendiri yang diikuti.
PEMILIK_STORAGE=$(stat -c '%u:%g' "$APP/storage")
echo "Pemilik kode: $PEMILIK   pemilik storage: $PEMILIK_STORAGE"

# Hanya berkas. Entri direktori di daftar ikut di-chown dan dicadangkan
# kalau tidak disaring.
BERKAS=$(tar -tzf "$PAKET" | grep -v '/$')
echo "Isi paket:"
CADANGAN="/root/cadangan-berkas-$(date '+%Y%m%d-%H%M%S')"
mkdir -p "$CADANGAN"
for f in $BERKAS; do
    if [ -f "$APP/$f" ]; then
        mkdir -p "$CADANGAN/$(dirname "$f")"
        cp -p "$APP/$f" "$CADANGAN/$f"
        echo "  $f"
    else
        echo "  $f   (baru)"
    fi
done
echo "Berkas lama dicadangkan ke $CADANGAN"

# --no-same-owner      : jangan ambil pemilik dari arsip (git archive = root)
# --no-same-permissions: mode mengikuti umask, bukan 664 bawaan arsip
# --no-overwrite-dir   : direktori yang sudah ada tidak disentuh metadatanya;
#                        tanpa ini tar sebagai root mengganti pemilik dan mode
#                        app/, config/, dan seterusnya.
if ! tar -xzf "$PAKET" -C "$APP" --no-same-owner --no-same-permissions --no-overwrite-dir; then
    echo ">>> Ekstrak GAGAL. Kembalikan dengan: cp -rp $CADANGAN/* $APP/"
    exit 1
fi

for f in $BERKAS; do chown "$PEMILIK" "$APP/$f"; done

# dompdf menyimpan cache font di storage/fonts.
mkdir -p "$APP/storage/fonts"
chown -R "$PEMILIK_STORAGE" "$APP/storage/fonts"

cd "$APP" || exit 1

# Dicatat SEBELUM dibersihkan. Kalau diperiksa sesudahnya, berkasnya sudah
# terhapus oleh :clear dan cache tidak akan pernah dipasang ulang.
ADA_CACHE_CONFIG=0; [ -f bootstrap/cache/config.php ]    && ADA_CACHE_CONFIG=1
ADA_CACHE_ROUTE=0;  [ -f bootstrap/cache/routes-v7.php ] && ADA_CACHE_ROUTE=1

GAGAL=0
artisan() {
    if "$PHP" artisan "$@" >/dev/null 2>&1; then
        echo "  $PHP artisan $*"
    else
        echo "  >>> GAGAL: $PHP artisan $*"
        GAGAL=1
    fi
}

artisan config:clear
artisan route:clear
artisan view:clear
[ "$ADA_CACHE_CONFIG" -eq 1 ] && artisan config:cache
[ "$ADA_CACHE_ROUTE" -eq 1 ]  && artisan route:cache

# OPcache bisa menahan berkas PHP lama bila validate_timestamps dimatikan.
# Hanya FPM 8.3 yang dipakai aplikasi ini; tetangga memakai mod_php 8.1,
# jadi muat ulang ini tidak menyentuh mereka.
if systemctl is-active --quiet php8.3-fpm; then
    systemctl reload php8.3-fpm && echo "  php8.3-fpm dimuat ulang"
fi

echo
echo "--- VERIFIKASI ----------------------------------------------"
grep -q "dejavu sans" config/dompdf.php           && echo "  config dompdf   : font dejavu sans OK" || echo "  config dompdf   : GAGAL"
grep -q "enable_font_subsetting' => true" config/dompdf.php && echo "  config dompdf   : subset font OK" || echo "  config dompdf   : subset GAGAL"
grep -q "font-weight: 700" resources/views/partials/print-css.blade.php && echo "  gaya cetak      : bobot 700 OK" || echo "  gaya cetak      : GAGAL"
grep -q "sudahTerpakai" app/Services/DocumentNumberService.php && echo "  penomoran       : lewati nomor terpakai OK" || echo "  penomoran       : GAGAL"
grep -q "function lunas" app/Models/Concerns/CalculatesTotals.php && echo "  status faktur   : sisa sen dihitung lunas OK" || echo "  status faktur   : GAGAL"
"$PHP" artisan list 2>/dev/null | grep -q "faktur:sinkron-status" && echo "  perintah        : faktur:sinkron-status OK" || echo "  perintah        : GAGAL"
echo -n "  situs           : "
curl -s -o /dev/null -w "%{http_code}\n" https://erp.pum.lokastudio.shop/login

echo
[ "$GAGAL" -ne 0 ] && echo ">>> Ada langkah artisan yang GAGAL — lihat di atas sebelum memakai aplikasi."
echo "Kembalikan bila perlu: cp -rp $CADANGAN/* $APP/ && cd $APP && $PHP artisan config:clear && $PHP artisan view:clear"
