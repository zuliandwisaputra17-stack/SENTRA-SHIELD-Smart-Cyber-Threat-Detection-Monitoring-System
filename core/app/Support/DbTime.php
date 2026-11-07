<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Batas waktu untuk query dengan presisi milidetik.
 *
 * Query builder Laravel memformat objek tanggal dengan format grammar
 * (Y-m-d H:i:s), sehingga milidetik hilang dan event yang terjadi di detik
 * yang sama bisa terlewat dari jendela waktu (mis. nmap memindai ratusan port
 * dalam < 1 detik). Semua batas waktu jendela deteksi memakai helper ini.
 */
final class DbTime
{
    public static function of(DateTimeInterface $time): string
    {
        return Carbon::instance($time)->utc()->format('Y-m-d H:i:s.v');
    }
}
