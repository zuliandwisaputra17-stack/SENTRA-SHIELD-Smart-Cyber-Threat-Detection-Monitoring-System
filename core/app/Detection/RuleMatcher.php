<?php

namespace App\Detection;

use App\Models\DetectionRule;
use App\Models\SecurityEvent;
use Illuminate\Support\Carbon;

/**
 * Evaluasi aturan pola terhadap satu event (tanpa akses database).
 * Bentuk config menentukan cara evaluasi; lihat katalog di DetectionRuleSeeder.
 */
class RuleMatcher
{
    public function matchesPattern(DetectionRule $rule, SecurityEvent $event): bool
    {
        $cfg = $rule->config;
        $payload = $event->payload ?? [];

        if (isset($cfg['match']) && ! $this->matchesConditions($cfg['match'], $payload)) {
            return false;
        }

        if (isset($cfg['fields'], $cfg['patterns'])) {
            return $this->anyFieldMatches($cfg['fields'], $cfg['patterns'], $payload, (bool) ($cfg['url_decode'] ?? false));
        }

        if (isset($cfg['content_patterns'])) {
            return in_array($payload['change'] ?? null, $cfg['changes'] ?? ['created', 'modified'], true)
                && self::regex($cfg['path_pattern'] ?? '.', (string) ($payload['path'] ?? ''))
                && $this->anyPattern($cfg['content_patterns'], (string) ($payload['sample'] ?? ''));
        }

        if (isset($cfg['watch_paths'])) {
            // R10 versi Penjaga Situs: SiteWatch sudah memeriksa baseline dan jadwal pembaruan situs.
            if ($event->source->value === 'site') {
                return ($payload['temuan'] ?? null) === 'defacement';
            }

            return $event->source->value === 'fim'
                && in_array($payload['path'] ?? null, $cfg['watch_paths'], true)
                && in_array($payload['change'] ?? null, $cfg['changes'] ?? ['modified'], true)
                && ! self::insideUpdateWindow($cfg['update_windows'] ?? [], $event->occurred_at);
        }

        if (isset($cfg['function_codes'])) {
            return in_array((int) ($payload['function_code'] ?? -1), array_map('intval', $cfg['function_codes']), true)
                && ! in_array($event->src_ip, $cfg['hmi_allowlist'] ?? [], true);
        }

        if (isset($cfg['keywords'], $cfg['similarity_threshold'])) {
            // R13: versi Googlebot/referer berbeda dari versi browser dan memuat kata kunci judi.
            return $event->source->value === 'site'
                && ($payload['temuan'] ?? null) === 'cloaking'
                && (float) ($payload['kemiripan'] ?? 1) < (float) $cfg['similarity_threshold']
                && ! empty($payload['kata_kunci']);
        }

        // Aturan dengan requires_rule (R08) hanya butuh kondisi "match";
        // syarat aturan pendahulunya diperiksa DetectionEngine.
        return isset($cfg['requires_rule']);
    }

    /** @param array<string, mixed> $conditions */
    public function matchesConditions(array $conditions, array $payload): bool
    {
        foreach ($conditions as $key => $expected) {
            if (! array_key_exists($key, $payload) || (string) $payload[$key] !== (string) $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $fields
     * @param  list<string>  $patterns
     */
    private function anyFieldMatches(array $fields, array $patterns, array $payload, bool $decode): bool
    {
        foreach ($fields as $field) {
            $value = (string) ($payload[$field] ?? '');
            if ($value === '') {
                continue;
            }
            if ($decode) {
                // Dua kali decode untuk menangkap encoding ganda (%2527 -> %27 -> ').
                $value = urldecode(urldecode($value));
            }
            if ($this->anyPattern($patterns, $value)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $patterns */
    private function anyPattern(array $patterns, string $subject): bool
    {
        foreach ($patterns as $pattern) {
            if (self::regex($pattern, $subject)) {
                return true;
            }
        }

        return false;
    }

    public static function regex(string $pattern, string $subject): bool
    {
        return @preg_match('~'.str_replace('~', '\~', $pattern).'~i', $subject) === 1;
    }

    /**
     * Jadwal pembaruan resmi: [{"hari":[1..7],"mulai":"HH:MM","selesai":"HH:MM"}] dalam WIB.
     *
     * @param  list<array<string, mixed>>  $windows
     */
    public static function insideUpdateWindow(array $windows, ?Carbon $time): bool
    {
        if ($time === null || $windows === []) {
            return false;
        }
        $local = $time->copy()->setTimezone(config('sentra.display_timezone'));
        $clock = $local->format('H:i');
        foreach ($windows as $window) {
            $days = $window['hari'] ?? [1, 2, 3, 4, 5, 6, 7];
            if (in_array($local->dayOfWeekIso, $days, true)
                && $clock >= ($window['mulai'] ?? '00:00') && $clock <= ($window['selesai'] ?? '23:59')) {
                return true;
            }
        }

        return false;
    }
}
