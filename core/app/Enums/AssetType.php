<?php

namespace App\Enums;

/**
 * Jenis aset host agent, menentukan m_aset pada rumus skor (PRD bagian 10).
 */
enum AssetType: string
{
    case Ot = 'ot';
    case Web = 'web';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Ot => 'Aset OT (PLC/HMI)',
            self::Web => 'Server web',
            self::Lainnya => 'Lainnya',
        };
    }

    public function multiplier(): float
    {
        return match ($this) {
            self::Ot => 1.5,
            self::Web => 1.2,
            self::Lainnya => 1.0,
        };
    }
}
