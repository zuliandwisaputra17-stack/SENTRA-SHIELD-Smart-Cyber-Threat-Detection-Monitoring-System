<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Aktif = 'aktif';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Selesai => 'Selesai',
        };
    }
}
