#!/usr/bin/env bash
#
# Memasang paket pembaruan ERP Peak di VPS.
#
#   bash /root/pasang-pembaruan.sh /root/erppeak-update-20261006.tar.gz
#
# Berupa skrip, bukan perintah tempel: 'exit' pada perintah yang ditempel
# langsung akan menutup sesi SSH-nya.

set -uo pipefail

SERVER_SAH="srv988090"
APP="/var/www/erppeak"
PAKET="${1:-}"

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

# Pemilik diambil dari berkas yang sudah ada, tidak diasumsikan www-data.
# Berkas hasil ekstrak sebagai root akan dimiliki root dan tidak terbaca
# oleh proses web — kesalahan yang hanya muncul sebagai 500 tanpa petunjuk.
PEMILIK=$(stat -c '%U:%G' "$APP/artisan")
echo "Pemilik berkas aplikasi: $PEMILIK"

BERKAS=$(tar -tzf "$PAKET")
echo "Isi paket:"; echo "$BERKAS" | sed 's/^/  /'

# Cadangan berkas yang akan ditimpa, supaya bisa dikembalikan tanpa
# mengandalkan cadangan basis data yang sama sekali tidak memuat kode.
CADANGAN="/root/cadangan-berkas-$(date '+%Y%m%d-%H%M')"
mkdir -p "$CADANGAN"
for f in $BERKAS; do
    if [ -f "$APP/$f" ]; then
        mkdir -p "$CADANGAN/$(dirname "$f")"
        cp -p "$APP/$f" "$CADANGAN/$f"
    else
        echo "  (baru) $f"
    fi
done
echo "Berkas lama dicadangkan ke $CADANGAN"

if ! tar -xzf "$PAKET" -C "$APP"; then
    echo ">>> Ekstrak GAGAL. Kembalikan dengan: cp -rp $CADANGAN/* $APP/"
    exit 1
fi

for f in $BERKAS; do chown "$PEMILIK" "$APP/$f"; done

# dompdf menulis cache font ke storage/fonts begitu subset dinyalakan.
mkdir -p "$APP/storage/fonts"
chown -R "$PEMILIK" "$APP/storage/fonts"

cd "$APP" || exit 1
php artisan config:clear >/dev/null && echo "config:clear"
php artisan view:clear   >/dev/null && echo "view:clear"
php artisan route:clear  >/dev/null && echo "route:clear"
[ -f bootstrap/cache/config.php ] && { php artisan config:cache >/dev/null && echo "config:cache (dipasang ulang)"; }

# OPcache bisa menahan berkas PHP lama bila validate_timestamps dimatikan.
# Hanya FPM 8.3 yang dipakai aplikasi ini; tetangga memakai mod_php 8.1,
# jadi muat ulang ini tidak menyentuh mereka.
if systemctl is-active --quiet php8.3-fpm; then
    systemctl reload php8.3-fpm && echo "php8.3-fpm dimuat ulang"
fi

echo
echo "--- VERIFIKASI ----------------------------------------------"
grep -q "dejavu sans" config/dompdf.php           && echo "  config dompdf   : font dejavu sans OK" || echo "  config dompdf   : GAGAL"
grep -q "enable_font_subsetting' => true" config/dompdf.php && echo "  config dompdf   : subset font OK" || echo "  config dompdf   : subset GAGAL"
grep -q "font-weight: 700" resources/views/partials/print-css.blade.php && echo "  gaya cetak      : bobot 700 OK" || echo "  gaya cetak      : GAGAL"
grep -q "sudahTerpakai" app/Services/DocumentNumberService.php && echo "  penomoran       : lewati nomor terpakai OK" || echo "  penomoran       : GAGAL"
echo -n "  situs           : "
curl -s -o /dev/null -w "%{http_code}\n" https://erp.pum.lokastudio.shop/login
echo
echo "Selesai. Kembalikan bila perlu: cp -rp $CADANGAN/* $APP/ && cd $APP && php artisan config:clear && php artisan view:clear"
