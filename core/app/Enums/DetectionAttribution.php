<?php

namespace App\Enums;

/**
 * Cara deteksi dikaitkan ke insiden (keputusan A2):
 * - ip: berdasarkan src_ip event.
 * - waktu: event tanpa IP (FIM) ditempelkan ke insiden terbuka paling baru aktif.
 * - agent: deteksi milik agent (Sensor Buta).
 */
enum DetectionAttribution: string
{
    case Ip = 'ip';
    case Waktu = 'waktu';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Ip => 'Berdasarkan IP sumber',
            self::Waktu => 'Atribusi berdasarkan waktu',
            self::Agent => 'Berdasarkan agent',
        };
    }
}
