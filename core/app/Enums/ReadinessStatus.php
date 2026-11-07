<?php

namespace App\Enums;

enum ReadinessStatus: string
{
    case Berjalan = 'berjalan';
    case Selesai = 'selesai';
    case Gagal = 'gagal';

    public function label(): string
    {
        return match ($this) {
            self::Berjalan => 'Berjalan',
            self::Selesai => 'Selesai',
            self::Gagal => 'Gagal',
        };
    }
}
