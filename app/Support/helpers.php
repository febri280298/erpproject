<?php

use App\Services\SettingService;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    /** Read an application setting (or the whole bag when no key is given). */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        $service = app(SettingService::class);

        return $key === null ? $service->all() : $service->get($key, $default);
    }
}

if (! function_exists('rupiah')) {
    /** Format money for display: `Rp 1.250.000` (or with decimals when asked). */
    function rupiah(mixed $value, int $decimals = 0, bool $withSymbol = true): string
    {
        $formatted = number_format((float) $value, $decimals, ',', '.');

        return $withSymbol ? 'Rp '.$formatted : $formatted;
    }
}

if (! function_exists('fnum')) {
    /** Format a quantity, dropping trailing zeros: `12,5` not `12,5000`. */
    function fnum(mixed $value, int $decimals = 2): string
    {
        $formatted = number_format((float) $value, $decimals, ',', '.');

        if (! str_contains($formatted, ',')) {
            return $formatted;
        }

        return rtrim(rtrim($formatted, '0'), ',');
    }
}

if (! function_exists('fdate')) {
    function fdate(mixed $value, string $format = 'd M Y'): string
    {
        return $value ? Carbon::parse($value)->translatedFormat($format) : '—';
    }
}

if (! function_exists('fdatetime')) {
    function fdatetime(mixed $value, string $format = 'd M Y H:i'): string
    {
        return $value ? Carbon::parse($value)->translatedFormat($format) : '—';
    }
}

if (! function_exists('terbilang')) {
    /** Spell out a number in Indonesian — used on printed invoices. */
    function terbilang(float $number): string
    {
        $number = (int) round(abs($number));
        $words = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        $spell = function (int $n) use (&$spell, $words): string {
            if ($n < 12) {
                return $words[$n];
            }
            if ($n < 20) {
                return $spell($n - 10).' belas';
            }
            if ($n < 100) {
                return $spell(intdiv($n, 10)).' puluh '.$spell($n % 10);
            }
            if ($n < 200) {
                return 'seratus '.$spell($n - 100);
            }
            if ($n < 1000) {
                return $spell(intdiv($n, 100)).' ratus '.$spell($n % 100);
            }
            if ($n < 2000) {
                return 'seribu '.$spell($n - 1000);
            }
            if ($n < 1000000) {
                return $spell(intdiv($n, 1000)).' ribu '.$spell($n % 1000);
            }
            if ($n < 1000000000) {
                return $spell(intdiv($n, 1000000)).' juta '.$spell($n % 1000000);
            }

            return $spell(intdiv($n, 1000000000)).' miliar '.$spell($n % 1000000000);
        };

        $result = trim(preg_replace('/\s+/', ' ', $spell($number)));

        return $result === '' ? 'nol rupiah' : ucfirst($result).' rupiah';
    }
}

if (! function_exists('percent')) {
    function percent(mixed $part, mixed $whole, int $decimals = 1): float
    {
        $whole = (float) $whole;

        return $whole == 0.0 ? 0.0 : round((float) $part / $whole * 100, $decimals);
    }
}
