<?php

namespace App\Enums;

/**
 * Status tindakan (FR-06). Kedaluwarsa = blokir yang durasinya habis dan sudah
 * dibuka otomatis. Usulan yang belum diputuskan tetap Diusulkan.
 */
enum ActionStatus: string
{
    case Diusulkan = 'diusulkan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Dieksekusi = 'dieksekusi';
    case Gagal = 'gagal';
    case Kedaluwarsa = 'kedaluwarsa';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Diusulkan => 'Diusulkan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Dieksekusi => 'Dieksekusi',
            self::Gagal => 'Gagal',
            self::Kedaluwarsa => 'Kedaluwarsa',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Diusulkan => 'bg-accent/10 text-accent ring-accent/40',
            self::Disetujui => 'bg-accent/10 text-accent-strong ring-accent/50',
            self::Dieksekusi => 'bg-aman/10 text-aman ring-aman/40',
            self::Gagal => 'bg-awas/15 text-awas ring-awas/50',
            self::Ditolak, self::Kedaluwarsa, self::Dibatalkan => 'bg-inset text-ink-2 ring-line-strong',
        };
    }
}
