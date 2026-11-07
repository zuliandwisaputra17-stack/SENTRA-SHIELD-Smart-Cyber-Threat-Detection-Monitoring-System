<?php

namespace App\Enums;

/**
 * Tahap Attack Story (FR-04). "Pertahanan dilumpuhkan" khusus R12 (Sensor Buta)
 * yang membentuk insidennya sendiri per agent.
 */
enum KillChainStage: string
{
    case Pengintaian = 'pengintaian';
    case AksesAwal = 'akses_awal';
    case Persistensi = 'persistensi';
    case Dampak = 'dampak';
    case PertahananDilumpuhkan = 'pertahanan_dilumpuhkan';

    public function label(): string
    {
        return match ($this) {
            self::Pengintaian => 'Pengintaian',
            self::AksesAwal => 'Akses Awal',
            self::Persistensi => 'Eksekusi/Persistensi',
            self::Dampak => 'Dampak',
            self::PertahananDilumpuhkan => 'Pertahanan dilumpuhkan',
        };
    }

    /** Urutan pada garis waktu Attack Story. */
    public function order(): int
    {
        return match ($this) {
            self::Pengintaian => 1,
            self::AksesAwal => 2,
            self::Persistensi => 3,
            self::Dampak => 4,
            self::PertahananDilumpuhkan => 5,
        };
    }
}
