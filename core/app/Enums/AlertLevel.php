<?php

namespace App\Enums;

/**
 * Tingkat Siaga satuan (PRD bagian 10). Urutan rank dipakai untuk mengambil
 * tingkat tertinggi dari insiden terbuka dan dari satuan anak.
 */
enum AlertLevel: string
{
    case Aman = 'aman';
    case Waspada = 'waspada';
    case Siaga = 'siaga';
    case Awas = 'awas';

    public function label(): string
    {
        return match ($this) {
            self::Aman => 'Aman',
            self::Waspada => 'Waspada',
            self::Siaga => 'Siaga',
            self::Awas => 'Awas',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Aman => 0,
            self::Waspada => 1,
            self::Siaga => 2,
            self::Awas => 3,
        };
    }

    public static function fromSeverity(?Severity $severity): self
    {
        return match ($severity) {
            null => self::Aman,
            Severity::Rendah => self::Aman,
            Severity::Sedang => self::Waspada,
            Severity::Tinggi => self::Siaga,
            Severity::Kritis => self::Awas,
        };
    }

    public static function highest(self ...$levels): self
    {
        $top = self::Aman;
        foreach ($levels as $level) {
            if ($level->rank() > $top->rank()) {
                $top = $level;
            }
        }

        return $top;
    }

    /** Kelas warna konsisten dengan Severity padanannya. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Aman => 'bg-aman/10 text-aman ring-aman/40',
            self::Waspada => 'bg-waspada/10 text-waspada ring-waspada/40',
            self::Siaga => 'bg-siaga/10 text-siaga ring-siaga/40',
            self::Awas => 'bg-awas/10 text-awas ring-awas/50',
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::Aman => 'bg-aman',
            self::Waspada => 'bg-waspada',
            self::Siaga => 'bg-siaga',
            self::Awas => 'bg-awas',
        };
    }
}
