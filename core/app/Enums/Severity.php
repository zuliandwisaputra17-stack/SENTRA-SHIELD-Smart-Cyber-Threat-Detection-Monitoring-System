<?php

namespace App\Enums;

/**
 * Severity insiden ditentukan dari skor (PRD bagian 10):
 * 0–39 Rendah, 40–79 Sedang, 80–149 Tinggi, 150+ Kritis.
 */
enum Severity: string
{
    case Rendah = 'rendah';
    case Sedang = 'sedang';
    case Tinggi = 'tinggi';
    case Kritis = 'kritis';

    public static function fromScore(int|float $score): self
    {
        return match (true) {
            $score >= 150 => self::Kritis,
            $score >= 80 => self::Tinggi,
            $score >= 40 => self::Sedang,
            default => self::Rendah,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Rendah => 'Rendah',
            self::Sedang => 'Sedang',
            self::Tinggi => 'Tinggi',
            self::Kritis => 'Kritis',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Rendah => 0,
            self::Sedang => 1,
            self::Tinggi => 2,
            self::Kritis => 3,
        };
    }

    /**
     * Kelas warna lencana. Rendah memakai pasir (bukan hijau Aman); Sedang/Tinggi/Kritis
     * sama dengan Waspada/Siaga/Awas.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Rendah => 'bg-pasir/10 text-pasir ring-pasir/40',
            default => AlertLevel::fromSeverity($this)->badgeClass(),
        };
    }

    /** Nama nada warna untuk komponen (kartu, stempel): pasir|waspada|siaga|awas. */
    public function tone(): string
    {
        return match ($this) {
            self::Rendah => 'pasir',
            self::Sedang => 'waspada',
            self::Tinggi => 'siaga',
            self::Kritis => 'awas',
        };
    }

    /** Kelas warna teks (angka skor, kata status). */
    public function textClass(): string
    {
        return match ($this) {
            self::Rendah => 'text-pasir',
            self::Sedang => 'text-waspada',
            self::Tinggi => 'text-siaga',
            self::Kritis => 'text-awas',
        };
    }

    /** Kelas warna isian (meter, garis, batang). */
    public function fillClass(): string
    {
        return match ($this) {
            self::Rendah => 'bg-pasir',
            self::Sedang => 'bg-waspada',
            self::Tinggi => 'bg-siaga',
            self::Kritis => 'bg-awas',
        };
    }
}
