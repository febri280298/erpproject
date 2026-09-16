<?php

/**
 * Mengubah CEKLIST-SERAH-TERIMA.md menjadi PDF siap cetak.
 *
 * Ceklist dipakai dengan cara dicetak, dicentang pakai pena, lalu
 * ditandatangani dua pihak. Yang disunting tetap berkas .md-nya; PDF selalu
 * dibuat ulang dari sana dan tidak pernah disunting sendiri. Dua berkas yang
 * sama-sama bisa disunting akan berbeda isi tanpa ada yang menyadarinya, dan
 * yang berbeda itu justru yang dibawa ke lapangan.
 *
 * Pemakaian:
 *   php scripts/ceklist-pdf.php                        -> CEKLIST-SERAH-TERIMA.pdf
 *   php scripts/ceklist-pdf.php sumber.md tujuan.pdf   -> berkas tertentu
 *
 * Biasanya cukup lewat cetak-ceklist.bat.
 */

$akar = dirname(__DIR__);

$autoload = $akar.'/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "Folder vendor\\ belum ada. Jalankan: composer install\n");
    exit(1);
}
require $autoload;

$sumber = $argv[1] ?? $akar.'/CEKLIST-SERAH-TERIMA.md';
$tujuan = $argv[2] ?? $akar.'/CEKLIST-SERAH-TERIMA.pdf';

if (! is_file($sumber)) {
    fwrite(STDERR, "Berkas sumber tidak ada: {$sumber}\n");
    exit(1);
}

$markdown = file_get_contents($sumber);

// Tautan antar berkas .md tidak ada gunanya di atas kertas — yang dipegang
// pembacanya hanya lembar ini. Judul tautannya dipertahankan, alamatnya
// dibuang sebelum diubah menjadi HTML.
$markdown = preg_replace('/\[([^\]]+)\]\((?!https?:)[^)]+\)/', '$1', $markdown);

$html = (new League\CommonMark\GithubFlavoredMarkdownConverter())->convert($markdown)->getContent();

// Dompdf tidak menggambar <input type="checkbox"> sama sekali: kotaknya hilang
// dan yang tersisa hanya teksnya, persis pada berkas yang gunanya untuk
// dicentang. Kotaknya digambar sendiri lewat CSS.
$html = preg_replace('/<input[^>]*type="checkbox"[^>]*>/i', '<span class="kotak"></span>', $html);
$html = preg_replace('/<li>(\s*(?:<p>)?)<span class="kotak">/', '<li class="ceklis">$1<span class="kotak">', $html);

// Di dalam sel tabel, "[ ]" bukan daftar centang bagi Markdown dan lolos apa
// adanya. Di atas kertas keduanya tetap harus tampak sama.
$html = str_replace('[ ]', '<span class="kotak"></span>', $html);

// Tabel isian dua kolom tidak punya judul kolom — Markdown tetap membuatkan
// baris kepala kosong, yang tercetak sebagai pita abu-abu tanpa isi.
$html = preg_replace('#<thead>\s*<tr>(?:\s*<th[^>]*>\s*</th>)+\s*</tr>\s*</thead>#', '', $html);

// Deretan garis bawah untuk diisi tangan lebih panjang daripada kolomnya, dan
// tanpa titik patah satu deret memaksa tabelnya melebar keluar halaman. Yang
// disisipi hanya deret panjangnya — bukan setiap garis bawah, supaya APP_ENV
// dan PHP_CLI_SERVER_WORKERS tetap utuh terbaca.
$html = preg_replace_callback(
    '/_{3,}/',
    fn (array $cocok) => implode('<wbr>', str_split($cocok[0], 2)),
    $html
);

$gaya = <<<'CSS'
@page { margin: 16mm 15mm 18mm 15mm; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; line-height: 1.45; color: #1a1a1a; }
h1 { font-size: 16pt; margin: 0 0 4pt; }
h2 { font-size: 11.5pt; margin: 14pt 0 5pt; padding-bottom: 2pt; border-bottom: 1px solid #999; page-break-after: avoid; }
h3 { font-size: 10pt; margin: 10pt 0 4pt; page-break-after: avoid; }
p { margin: 0 0 6pt; }
strong { font-weight: bold; }
hr { border: 0; border-top: 1px solid #ccc; margin: 12pt 0; }
a { color: #1a1a1a; text-decoration: none; }
code { font-family: "DejaVu Sans Mono", monospace; font-size: 8pt; background: #f0f0f0; padding: 0 2px; }
blockquote { margin: 8pt 0; padding: 6pt 8pt; border-left: 3px solid #888; background: #f5f5f5; }
blockquote p { margin: 0; }
ul { margin: 0 0 8pt; padding-left: 14pt; }
li { margin-bottom: 3.5pt; }
li.ceklis { list-style: none; margin-left: -12pt; }
.kotak { display: inline-block; width: 9pt; height: 9pt; border: 1px solid #333; margin-right: 5pt; }
table { width: 100%; border-collapse: collapse; margin: 6pt 0 10pt; empty-cells: show; page-break-inside: avoid; }
th, td { border: 1px solid #999; padding: 4pt 5pt; text-align: left; vertical-align: top; word-wrap: break-word; }
th { background: #ececec; font-weight: bold; }
td { height: 20pt; }
CSS;

$halaman = '<html><head><meta charset="utf-8"><style>'.$gaya.'</style></head><body>'.$html.'</body></html>';

$opsi = new Dompdf\Options();
$opsi->set('isHtml5ParserEnabled', true);
$opsi->set('isRemoteEnabled', false);
$opsi->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf\Dompdf($opsi);
$dompdf->setPaper('A4');
$dompdf->loadHtml($halaman, 'UTF-8');
$dompdf->render();

// Nomor halaman ditulis setelah render karena baru di sini jumlah halamannya
// diketahui. Lembar yang tercecer di meja customer masih bisa disusun ulang.
$kanvas = $dompdf->getCanvas();
$kanvas->page_text(
    42, 800,
    'Ceklist Serah Terima ERP Peak — halaman {PAGE_NUM} dari {PAGE_COUNT}',
    $dompdf->getFontMetrics()->getFont('DejaVu Sans'),
    7.5,
    [0.45, 0.45, 0.45]
);

$folder = dirname($tujuan);
if (! is_dir($folder) && ! mkdir($folder, 0777, true) && ! is_dir($folder)) {
    fwrite(STDERR, "Folder tujuan tidak bisa dibuat: {$folder}\n");
    exit(1);
}

if (file_put_contents($tujuan, $dompdf->output()) === false) {
    fwrite(STDERR, "Gagal menulis: {$tujuan}\n");
    exit(1);
}

printf("%s  (%d KB)%s", $tujuan, (int) round(filesize($tujuan) / 1024), PHP_EOL);
exit(0);
