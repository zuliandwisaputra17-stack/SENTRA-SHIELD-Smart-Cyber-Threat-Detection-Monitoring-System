<?php

namespace App\Sites;

use App\Detection\DetectionEngine;
use App\Detection\RuleMatcher;
use App\Enums\EventSource;
use App\Enums\SiteVariant;
use App\Models\DetectionRule;
use App\Models\MonitoredSite;
use App\Models\SecurityEvent;
use App\Models\SiteCheck;
use App\Services\AuditLogger;
use App\Support\LiveUpdate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Penjaga Situs (FR-13). Core memeriksa situs satuan yang terdaftar:
 *
 * - Halaman diambil 3 kali: browser biasa, Googlebot, dan referer Google.
 *   Bila versi Googlebot/referer berbeda dari versi browser di bawah ambang
 *   kemiripan dan memuat kata kunci judi yang tidak ada di versi browser,
 *   itu injeksi judi online ber-cloaking (R13).
 * - Bila hash teks halaman utama (versi browser) berubah dari baseline di
 *   luar jadwal pembaruan yang terdaftar, itu defacement (R10). Perubahan di
 *   dalam jadwal menjadi baseline baru.
 *
 * Event "site" hanya dibuat saat temuan baru muncul, bukan setiap pemeriksaan.
 * Hanya situs terdaftar yang diperiksa, dan secara bawaan hanya alamat di
 * jaringan privat (situs dummy milik sendiri di lab).
 */
class SiteWatch
{
    public const USER_AGENTS = [
        'browser' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
        'googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    ];

    public const GOOGLE_REFERER = 'https://www.google.com/search?q=';

    private const MAX_BODY = 2_000_000;

    public function __construct(
        private DetectionEngine $engine,
        private AuditLogger $audit,
    ) {}

    /** @return int jumlah situs yang diperiksa */
    public function checkAll(): int
    {
        $sites = MonitoredSite::query()->orderBy('id')->get();
        foreach ($sites as $site) {
            $this->check($site);
        }

        return $sites->count();
    }

    /** @return Collection<int, SiteCheck> hasil tiga varian */
    public function check(MonitoredSite $site): Collection
    {
        $uuid = (string) Str::uuid();
        $now = now();
        [$keywords, $threshold] = $this->cloakingConfig();
        $blocked = self::urlRefusal($site->url);

        $pages = [];
        foreach (SiteVariant::cases() as $variant) {
            $pages[$variant->value] = $blocked !== null ? ['status' => null, 'error' => $blocked] : $this->fetch($site->url, $variant);
        }

        $browser = isset($pages['browser']['text']) ? $pages['browser']['text'] : null;
        $browserHits = $browser?->keywordHits($keywords) ?? [];
        $previous = $this->previous($site);

        // Baseline dan defacement (versi browser).
        $defacement = false;
        $browserHash = $browser?->hash();
        $baselineBefore = $site->baseline_hash;
        if ($browserHash !== null) {
            if ($site->baseline_hash === null) {
                $site->update(['baseline_hash' => $browserHash]);
            } elseif ($browserHash !== $site->baseline_hash) {
                if (RuleMatcher::insideUpdateWindow($site->update_windows ?? [], $now)) {
                    $site->update(['baseline_hash' => $browserHash]);
                    $this->audit->record('baseline_situs_diperbarui', $site, ['url' => $site->url, 'alasan' => 'perubahan di dalam jadwal pembaruan'], null);
                } else {
                    $defacement = true;
                }
            }
        }

        $checks = collect();
        foreach ($pages as $variant => $page) {
            $text = $page['text'] ?? null;
            $hits = $text?->keywordHits($keywords) ?? [];
            $similarity = $text === null ? null : ($variant === 'browser' ? 1.0 : ($browser ? $text->similarity($browser) : null));
            $flagged = $variant === 'browser'
                ? $defacement
                : ($similarity !== null && $similarity < $threshold && array_sum($hits) > array_sum($browserHits)
                    && array_diff_key($hits, $browserHits) !== []);

            $checks->push(SiteCheck::create([
                'site_id' => $site->id,
                'check_uuid' => $uuid,
                'variant' => $variant,
                'http_status' => $page['status'],
                'hash' => $text?->hash(),
                'similarity' => $similarity,
                'gambling_hits' => array_sum($hits),
                'keywords' => $hits ?: null,
                'flagged' => $flagged,
                'excerpt' => $text?->excerpt(),
                'error' => $page['error'] ?? null,
                'checked_at' => $now,
            ]));
        }

        $events = [];
        $cloaked = $checks->filter(fn (SiteCheck $c) => $c->variant !== SiteVariant::Browser && $c->flagged);
        if ($cloaked->isNotEmpty() && $this->isNewCloaking($cloaked, $previous)) {
            $worst = $cloaked->sortBy('similarity')->first();
            $events[] = $this->event($site, $now, [
                'temuan' => 'cloaking',
                'url' => $site->url,
                'varian' => $cloaked->map(fn ($c) => $c->variant->value)->values()->all(),
                'kemiripan' => $worst->similarity,
                'kata_kunci' => $cloaked->flatMap(fn ($c) => $c->keywords ?? [])->all(),
                'hash_browser' => $browserHash,
                'hash_varian' => $worst->hash,
                'cuplikan' => mb_strimwidth((string) $worst->excerpt, 0, 400, '…'),
                'pemeriksaan' => $uuid,
            ], sprintf('Versi %s situs %s berbeda dari versi browser (kemiripan %d%%) dan memuat kata judi: %s',
                $cloaked->map(fn ($c) => $c->variant->label())->implode(' dan '), $site->url,
                (int) round($worst->similarity * 100), implode(', ', array_keys($cloaked->flatMap(fn ($c) => $c->keywords ?? [])->all()))));
        }
        $previousBrowser = $previous?->firstWhere('variant', SiteVariant::Browser);
        if ($defacement && $previousBrowser?->hash !== $browserHash) {
            $events[] = $this->event($site, $now, [
                'temuan' => 'defacement',
                'url' => $site->url,
                'hash_lama' => $baselineBefore,
                'hash_baru' => $browserHash,
                'cuplikan' => mb_strimwidth($browser->excerpt(), 0, 400, '…'),
                'pemeriksaan' => $uuid,
            ], sprintf('Halaman utama %s berubah di luar jadwal pembaruan (hash %s… menjadi %s…)', $site->url, substr((string) $baselineBefore, 0, 12), substr($browserHash, 0, 12)));
        }

        if ($events !== []) {
            $this->engine->processIds(array_map(fn (SecurityEvent $e) => $e->id, $events));
        }
        LiveUpdate::notify('situs', [$site->unit_id]);

        return $checks;
    }

    /** Jadikan versi browser terakhir sebagai baseline (perubahan resmi di luar jadwal). */
    public function acceptBaseline(MonitoredSite $site): bool
    {
        $latest = $this->previous($site)?->firstWhere('variant', SiteVariant::Browser);
        if ($latest?->hash === null) {
            return false;
        }
        $old = $site->baseline_hash;
        $site->update(['baseline_hash' => $latest->hash]);
        SiteCheck::query()->where('check_uuid', $latest->check_uuid)->where('variant', SiteVariant::Browser)->update(['flagged' => false]);
        $this->audit->record('baseline_situs_diubah', $site, ['url' => $site->url, 'hash_lama' => $old, 'hash_baru' => $latest->hash]);

        return true;
    }

    /**
     * Alasan URL ditolak, atau null bila boleh diperiksa. Hanya http(s), dan
     * secara bawaan hanya host yang mengarah ke alamat privat (lab).
     */
    public static function urlRefusal(string $url): ?string
    {
        $parts = parse_url($url);
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            return 'URL harus http:// atau https://';
        }
        if (config('sentra.sites.allow_public')) {
            return null;
        }
        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            return "Nama host {$host} tidak dikenal";
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                return "Alamat {$ip} bukan jaringan privat; Penjaga Situs lab hanya memeriksa situs dummy milik sendiri";
            }
        }

        return null;
    }

    /** @return array{status: ?int, text?: PageText, error?: string} */
    private function fetch(string $url, SiteVariant $variant): array
    {
        $headers = ['User-Agent' => self::USER_AGENTS[$variant === SiteVariant::Googlebot ? 'googlebot' : 'browser']];
        if ($variant === SiteVariant::Referer) {
            $host = parse_url($url, PHP_URL_HOST);
            $headers['Referer'] = self::GOOGLE_REFERER.urlencode((string) $host);
        }

        try {
            // Tidak mengikuti pengalihan: pemeriksaan tidak boleh keluar dari situs terdaftar.
            $response = Http::withHeaders($headers)->withoutRedirecting()->connectTimeout(3)->timeout(10)->get($url);
        } catch (ConnectionException $e) {
            return ['status' => null, 'error' => 'Tidak dapat diakses: '.mb_strimwidth($e->getMessage(), 0, 150, '…')];
        }

        $result = ['status' => $response->status(), 'text' => new PageText(substr($response->body(), 0, self::MAX_BODY))];
        if ($response->redirect()) {
            $result['error'] = 'Dialihkan ke '.mb_strimwidth((string) $response->header('Location'), 0, 150, '…');
        } elseif (! $response->successful()) {
            $result['error'] = 'HTTP '.$response->status();
        }

        return $result;
    }

    /** @return array{0: list<string>, 1: float} kata kunci dan ambang kemiripan dari aturan R13 */
    private function cloakingConfig(): array
    {
        $config = DetectionRule::query()->where('code', 'R13')->value('config');
        $config = is_string($config) ? json_decode($config, true) : ($config ?? []);

        return [
            array_values(array_filter((array) ($config['keywords'] ?? ['slot', 'gacor', 'togel', 'judi', 'maxwin', 'casino', 'scatter', 'toto', 'jackpot']), 'is_string')),
            (float) ($config['similarity_threshold'] ?? 0.8),
        ];
    }

    /** @return Collection<int, SiteCheck>|null pemeriksaan terakhir sebelumnya (tiga varian) */
    private function previous(MonitoredSite $site): ?Collection
    {
        $uuid = SiteCheck::query()->where('site_id', $site->id)->orderByDesc('checked_at')->orderByDesc('id')->value('check_uuid');

        return $uuid ? SiteCheck::query()->where('check_uuid', $uuid)->get() : null;
    }

    /** Cloaking baru bila pemeriksaan sebelumnya belum ditandai, atau isi versi tersamar berganti. */
    private function isNewCloaking(Collection $cloaked, ?Collection $previous): bool
    {
        $before = $previous?->filter(fn (SiteCheck $c) => $c->variant !== SiteVariant::Browser && $c->flagged)
            ->mapWithKeys(fn (SiteCheck $c) => [$c->variant->value => $c->hash]) ?? collect();

        return $cloaked->contains(fn (SiteCheck $c) => ($before[$c->variant->value] ?? null) !== $c->hash);
    }

    private function event(MonitoredSite $site, $time, array $payload, string $raw): SecurityEvent
    {
        return SecurityEvent::create([
            'agent_id' => null,
            'unit_id' => $site->unit_id,
            'source' => EventSource::Site,
            'src_ip' => null,
            'dst_port' => null,
            'payload' => $payload,
            'raw' => $raw,
            'is_test' => false,
            'occurred_at' => $time,
            'created_at' => now(),
        ]);
    }
}
