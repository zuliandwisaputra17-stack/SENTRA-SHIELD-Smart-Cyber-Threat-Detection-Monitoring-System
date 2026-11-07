<?php

namespace App\Enums;

enum UnitLevel: string
{
    case Kodam = 'kodam';
    case Korem = 'korem';
    case Kodim = 'kodim';

    public function label(): string
    {
        return match ($this) {
            self::Kodam => 'Kodam',
            self::Korem => 'Korem',
            self::Kodim => 'Kodim',
        };
    }
}
