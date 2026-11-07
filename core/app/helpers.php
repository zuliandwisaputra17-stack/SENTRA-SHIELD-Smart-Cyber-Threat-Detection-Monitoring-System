<?php

use Illuminate\Support\Carbon;

if (! function_exists('waktu_wib')) {
    /**
     * Format waktu (disimpan UTC) untuk tampilan dalam WIB.
     */
    function waktu_wib(?DateTimeInterface $time, string $format = 'd/m/Y H:i:s'): string
    {
        if ($time === null) {
            return '—';
        }

        return Carbon::instance($time)->setTimezone(config('sentra.display_timezone'))->format($format).' WIB';
    }
}

if (! function_exists('dtg')) {
    /**
     * Date-time group gaya militer untuk tampilan: "071447 WIB NOV 26"
     * (tanggal, jam, menit, zona, bulan singkat Indonesia, tahun dua digit).
     */
    function dtg(?DateTimeInterface $time): string
    {
        if ($time === null) {
            return '—';
        }
        $t = Carbon::instance($time)->setTimezone(config('sentra.display_timezone'));
        $bulan = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGT', 'SEP', 'OKT', 'NOV', 'DES'][$t->month - 1];

        return $t->format('dHi').' WIB '.$bulan.' '.$t->format('y');
    }
}
