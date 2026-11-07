<?php

namespace App\Detection;

use App\Models\Setting;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Daftar putih IP (FR-03). IP di daftar ini tidak memicu deteksi, tetapi
 * event-nya tetap disimpan. Mendukung IP tunggal dan CIDR (IPv4/IPv6).
 */
class IpWhitelist
{
    /** @param  list<string>  $entries */
    public function __construct(private array $entries) {}

    public static function fromSettings(): self
    {
        return new self(array_values(array_filter((array) Setting::get('ip_whitelist', []), 'is_string')));
    }

    public function contains(?string $ip): bool
    {
        return $ip !== null && $this->entries !== [] && IpUtils::checkIp($ip, $this->entries);
    }

    /** @return list<string> */
    public function entries(): array
    {
        return $this->entries;
    }

    public static function isValidEntry(string $entry): bool
    {
        [$address, $mask] = array_pad(explode('/', $entry, 2), 2, null);
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if ($mask === null) {
            return true;
        }
        $max = str_contains($address, ':') ? 128 : 32;

        return ctype_digit($mask) && (int) $mask <= $max;
    }
}
