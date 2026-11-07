<?php

namespace App\Livewire\Sites;

use App\Enums\EventSource;
use App\Enums\SiteVariant;
use App\Models\Detection;
use App\Models\MonitoredSite;
use App\Models\SiteCheck;
use App\Models\Unit;
use App\Services\AuditLogger;
use App\Sites\SiteWatch;
use App\Support\LiveUpdate;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Penjaga Situs (FR-13, PRD bagian 11): daftar situs, hasil pemeriksaan per
 * varian, perbandingan isi. Analis mengelola; Demo hanya melihat.
 */
#[Layout('layouts.app')]
#[Title('Penjaga Situs')]
class SiteWatchPage extends Component
{
    #[Url(as: 'situs')]
    public ?int $siteId = null;

    public ?int $unitId = null;

    public string $url = '';

    public string $windows = '';

    public ?string $flash = null;

    public function mount(): void
    {
        $this->authorize('lihat-penjaga-situs');
        $this->siteId ??= MonitoredSite::query()->orderBy('id')->value('id');
    }

    #[On('sentra-refresh')]
    public function refreshLive(): void {}

    public function select(int $id): void
    {
        $this->siteId = $id;
        $this->flash = null;
    }

    public function add(AuditLogger $audit): void
    {
        $this->authorize('kelola-penjaga-situs');
        $this->url = trim($this->url);
        $this->validate([
            'unitId' => ['required', 'exists:units,id'],
            'url' => ['required', 'url:http,https', 'max:255', 'unique:monitored_sites,url'],
            'windows' => ['nullable', 'string', 'max:200', 'regex:/^\s*(\d{2}:\d{2}\s*-\s*\d{2}:\d{2}\s*(,\s*|$))*$/'],
        ], ['windows.regex' => 'Format jadwal: 22:00-23:00, pisahkan beberapa jadwal dengan koma.'], [
            'unitId' => 'satuan', 'url' => 'URL', 'windows' => 'jadwal pembaruan',
        ]);
        if ($refusal = SiteWatch::urlRefusal($this->url)) {
            $this->addError('url', $refusal);

            return;
        }

        $site = MonitoredSite::create(['unit_id' => $this->unitId, 'url' => $this->url, 'update_windows' => self::parseWindows($this->windows) ?: null]);
        $audit->record('situs_ditambah', $site, ['url' => $site->url, 'jadwal_pembaruan' => $this->windows ?: null]);
        $this->reset('url', 'windows');
        $this->siteId = $site->id;
        $this->flash = 'Situs ditambahkan. Pemeriksaan pertama menjadikan halaman saat ini sebagai baseline.';
        LiveUpdate::notify('situs', [$site->unit_id]);
    }

    public function remove(int $id, AuditLogger $audit): void
    {
        $this->authorize('kelola-penjaga-situs');
        $site = MonitoredSite::query()->findOrFail($id);
        $audit->record('situs_dihapus', $site, ['url' => $site->url]);
        $site->delete();
        $this->siteId = MonitoredSite::query()->orderBy('id')->value('id');
        $this->flash = 'Situs dihapus dari Penjaga Situs.';
    }

    public function checkNow(int $id, SiteWatch $watch): void
    {
        $this->authorize('kelola-penjaga-situs');
        $watch->check(MonitoredSite::query()->findOrFail($id));
        $this->siteId = $id;
        $this->flash = 'Pemeriksaan selesai.';
    }

    public function acceptBaseline(int $id, SiteWatch $watch): void
    {
        $this->authorize('kelola-penjaga-situs');
        $this->flash = $watch->acceptBaseline(MonitoredSite::query()->findOrFail($id))
            ? 'Versi browser terakhir dijadikan baseline halaman utama.'
            : 'Belum ada pemeriksaan yang bisa dijadikan baseline.';
    }

    public function render()
    {
        $sites = MonitoredSite::query()->with('unit')->orderBy('id')->get();
        $latest = $this->latestChecks($sites->pluck('id')->all());
        $site = $sites->firstWhere('id', $this->siteId);

        $history = collect();
        $incidents = collect();
        if ($site) {
            $history = SiteCheck::query()->where('site_id', $site->id)
                ->orderByDesc('checked_at')->orderByDesc('id')->limit(60)->get()
                ->groupBy('check_uuid')->take(15);
            $incidents = Detection::query()->with('incident')
                ->whereNotNull('incident_id')
                ->whereHas('event', fn ($q) => $q->where('source', EventSource::Site)->where('payload->url', $site->url))
                ->latest('id')->get()->pluck('incident')->unique('id')->take(5);
        }

        return view('livewire.sites.site-watch-page', [
            'sites' => $sites,
            'latest' => $latest,
            'site' => $site,
            'checks' => $site ? ($latest[$site->id] ?? collect()) : collect(),
            'history' => $history,
            'incidents' => $incidents,
            'units' => Unit::query()->orderBy('name')->get(['id', 'name']),
            'canManage' => auth()->user()->can('kelola-penjaga-situs'),
        ]);
    }

    /**
     * Status ringkas dari satu pemeriksaan (tiga varian).
     *
     * @return array{0: string, 1: string} label dan kelas warna
     */
    public static function status(?Collection $checks): array
    {
        if ($checks === null || $checks->isEmpty()) {
            return ['Belum diperiksa', 'bg-inset text-ink-2 ring-line-strong'];
        }
        $browser = $checks->firstWhere('variant', SiteVariant::Browser);
        if ($checks->contains(fn ($c) => $c->variant !== SiteVariant::Browser && $c->flagged)) {
            return ['Cloaking judi', 'bg-awas/15 text-awas ring-awas/50'];
        }
        if ($browser?->flagged) {
            return ['Halaman utama berubah', 'bg-awas/15 text-awas ring-awas/50'];
        }
        if ($browser === null || $browser->hash === null || ($browser->error !== null)) {
            return ['Tidak dapat diakses', 'bg-waspada/10 text-waspada ring-waspada/40'];
        }

        return ['Aman', 'bg-aman/10 text-aman ring-aman/40'];
    }

    /** Teks cuplikan dengan kata kunci judi disorot (aman: di-escape dulu). */
    public static function highlight(?string $text, ?array $keywords): string
    {
        $html = e((string) $text);
        foreach (array_keys($keywords ?? []) as $keyword) {
            $html = preg_replace('/(?<![\p{L}\p{N}])('.preg_quote(e($keyword), '/').')(?![\p{L}\p{N}])/iu', '<mark class="rounded bg-red-700/80 px-0.5 text-white">$1</mark>', $html) ?? $html;
        }

        return $html;
    }

    /** @return list<array{mulai: string, selesai: string}> */
    public static function parseWindows(string $text): array
    {
        preg_match_all('/(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})/', $text, $m, PREG_SET_ORDER);

        return array_map(fn ($w) => ['mulai' => $w[1], 'selesai' => $w[2]], $m);
    }

    /** @return array<int, Collection<int, SiteCheck>> pemeriksaan terakhir per situs */
    private function latestChecks(array $siteIds): array
    {
        $latest = [];
        foreach ($siteIds as $id) {
            $uuid = SiteCheck::query()->where('site_id', $id)->orderByDesc('checked_at')->orderByDesc('id')->value('check_uuid');
            $latest[$id] = $uuid ? SiteCheck::query()->where('check_uuid', $uuid)->get()->sortBy(fn ($c) => array_search($c->variant, SiteVariant::cases(), true))->values() : collect();
        }

        return $latest;
    }
}
