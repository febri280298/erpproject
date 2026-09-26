#!/usr/bin/env bash
#
# Mengosongkan seluruh transaksi di basis data produksi, master tetap utuh.
#
# Dipakai setelah uji coba: dokumen percobaan dibuang, tetapi data yang sudah
# disetel — akun, mitra, produk, harga, pengguna, hak akses, profil perusahaan,
# penomoran — tidak boleh ikut hilang.
#
# Jalankan sebagai berkas, bukan tempel per baris:
#   bash kosongkan-transaksi.sh              -> hanya laporan, tidak mengubah apa pun
#   bash kosongkan-transaksi.sh --jalankan   -> cadangkan lalu kosongkan
#
# Sengaja berupa skrip: 'exit' di dalam perintah yang ditempel langsung akan
# menutup sesi SSH-nya, dan itu pernah membuat perintah berikutnya mendarat di
# server yang salah.

set -uo pipefail

SERVER_SAH="srv988090"
DB="erppeak"

# Daftar SERTAKAN, bukan daftar kecualikan.
#
# Tabel apa pun yang tidak disebut di sini akan dikosongkan. Arah itu disengaja:
# modul baru selalu membawa tabel transaksi baru, dan kalau daftarnya dibalik
# tabel baru itu diam-diam lolos dan menyisakan data uji coba. Kalau kelak ada
# tabel master baru, ia akan muncul di bagian "AKAN DIKOSONGKAN" pada laporan —
# jadi ketahuan sebelum dieksekusi, bukan sesudah.
MASTER="accounts bom_items boms departments employees leave_types migrations
model_has_permissions model_has_roles number_sequences partners payment_terms
permissions positions price_levels product_categories product_customer_prices
product_prices product_supplier_prices products role_has_permissions roles
settings taxes uoms users warehouses"

JALANKAN=0
[ "${1:-}" = "--jalankan" ] && JALANKAN=1

if [ "$(hostname)" != "$SERVER_SAH" ]; then
    echo ">>> SALAH SERVER <<< ini '$(hostname)', seharusnya '$SERVER_SAH'. Berhenti."
    exit 1
fi

if ! mysql -N -B -e "USE \`$DB\`" 2>/dev/null; then
    echo ">>> Tidak bisa membuka basis data '$DB'. Berhenti."
    exit 1
fi

adalah_master() {
    for m in $MASTER; do [ "$m" = "$1" ] && return 0; done
    return 1
}

# tr -d menghapus carriage return: klien mysql di sebagian sistem
# mengembalikannya, dan nama tabel yang kelebihan satu karakter tak terlihat
# membuat setiap tabel master gagal dikenali lalu ikut terhapus.
SEMUA=$(mysql -N -B -e "SELECT table_name FROM information_schema.tables
        WHERE table_schema='$DB' AND table_type='BASE TABLE' ORDER BY table_name" | tr -d '\r')

KOSONGKAN=""
PERTAHANKAN=""
for t in $SEMUA; do
    if adalah_master "$t"; then PERTAHANKAN="$PERTAHANKAN $t"; else KOSONGKAN="$KOSONGKAN $t"; fi
done

# COUNT(*) sungguhan, bukan taksiran information_schema — taksiran InnoDB bisa
# meleset jauh, dan laporan yang dipakai memutuskan penghapusan tidak boleh menebak.
jumlah() { mysql -N -B -e "SELECT COUNT(*) FROM \`$DB\`.\`$1\`" 2>/dev/null | tr -d '\r' || echo "?"; }

echo
echo "=============================================================="
echo " BASIS DATA : $DB   di $(hostname)   $(date '+%Y-%m-%d %H:%M')"
echo "=============================================================="
echo
echo "--- DIPERTAHANKAN (master, tidak disentuh) ---------------------"
total_master=0
for t in $PERTAHANKAN; do
    n=$(jumlah "$t"); printf '  %-34s %8s baris\n' "$t" "$n"
    [ "$n" != "?" ] && total_master=$((total_master + n))
done
echo "  ..................................... $total_master baris dipertahankan"
echo
echo "--- AKAN DIKOSONGKAN (transaksi + data sementara) --------------"
total_hapus=0
ada_isi=0
for t in $KOSONGKAN; do
    n=$(jumlah "$t")
    if [ "$n" != "?" ] && [ "$n" -gt 0 ]; then
        printf '  %-34s %8s baris  <-- ada isinya\n' "$t" "$n"
        ada_isi=$((ada_isi + 1))
    else
        printf '  %-34s %8s baris\n' "$t" "$n"
    fi
    [ "$n" != "?" ] && total_hapus=$((total_hapus + n))
done
echo "  ..................................... $total_hapus baris akan hilang, dari $ada_isi tabel"
echo
echo "  Pencacah nomor dokumen dikembalikan ke 1 (baris number_sequences"
echo "  tetap ada; awalan, pola, dan padding yang sudah disetel tidak berubah)."
echo

if [ "$JALANKAN" -ne 1 ]; then
    echo "Ini baru laporan. Tidak ada yang diubah."
    echo "Kalau daftar di atas sudah benar, jalankan ulang dengan: --jalankan"
    exit 0
fi

echo "Periksa sekali lagi daftar DIPERTAHANKAN di atas."
printf 'Ketik KOSONGKAN untuk lanjut: '
read -r jawab
if [ "$jawab" != "KOSONGKAN" ]; then
    echo "Dibatalkan. Tidak ada yang diubah."
    exit 0
fi

# Cadangan lebih dulu, dan hasilnya diperiksa. Truncate tidak boleh berjalan
# hanya karena perintah cadangan "sudah dipanggil".
CADANGAN="/var/backups/erppeak/sebelum-kosongkan-$(date '+%Y%m%d-%H%M').sql.gz"
mkdir -p /var/backups/erppeak
echo "Mencadangkan ke $CADANGAN ..."
if ! mysqldump --single-transaction --routines --triggers "$DB" | gzip > "$CADANGAN"; then
    echo ">>> Cadangan GAGAL. Tidak ada yang dikosongkan."
    rm -f "$CADANGAN"
    exit 1
fi
UKURAN=$(stat -c %s "$CADANGAN")
if [ "$UKURAN" -lt 20000 ]; then
    echo ">>> Cadangan hanya $UKURAN byte — terlalu kecil untuk masuk akal."
    echo ">>> Tidak ada yang dikosongkan. Periksa $CADANGAN."
    exit 1
fi
echo "Cadangan siap: $UKURAN byte."

# Kunci asing dimatikan sementara supaya urutan truncate tidak jadi soal.
# Semua tabel yang saling merujuk dikosongkan dalam satu jalan yang sama,
# jadi tidak ada baris yatim yang tertinggal saat kuncinya dinyalakan lagi.
{
    echo "SET FOREIGN_KEY_CHECKS=0;"
    for t in $KOSONGKAN; do echo "TRUNCATE TABLE \`$t\`;"; done
    echo "UPDATE number_sequences SET next_number = 1;"
    echo "SET FOREIGN_KEY_CHECKS=1;"
} | mysql -D "$DB"
HASIL=$?

if [ "$HASIL" -ne 0 ]; then
    echo ">>> Ada perintah yang gagal. Pulihkan dengan:"
    echo ">>>   zcat $CADANGAN | mysql $DB"
    exit 1
fi

echo
echo "--- SESUDAH ----------------------------------------------------"
sisa=0
for t in $KOSONGKAN; do
    n=$(jumlah "$t")
    [ "$n" != "?" ] && [ "$n" -gt 0 ] && { printf '  MASIH BERISI %-22s %8s baris\n' "$t" "$n"; sisa=$((sisa + 1)); }
done
[ "$sisa" -eq 0 ] && echo "  Semua tabel transaksi kosong."
echo
for t in $PERTAHANKAN; do printf '  %-34s %8s baris\n' "$t" "$(jumlah "$t")"; done
echo
echo "Selesai. Cadangan sebelum tindakan ini: $CADANGAN"
