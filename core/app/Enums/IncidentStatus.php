<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Baru = 'baru';
    case Diselidiki = 'diselidiki';
    case Ditangani = 'ditangani';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::Diselidiki => 'Diselidiki',
            self::Ditangani => 'Ditangani',
            self::Selesai => 'Selesai',
        };
    }

    /** Insiden terbuka = semua status selain Selesai. */
    public function isOpen(): bool
    {
        return $this !== self::Selesai;
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::Baru->value, self::Diselidiki->value, self::Ditangani->value];
    }
}
