<?php

use App\Enums\SiteVariant;
use App\Livewire\Sites\SiteWatchPage;
use App\Models\AuditLog;
use App\Models\Detection;
use App\Models\Incident;
use App\Models\MonitoredSite;
use App\Models\SecurityEvent;
use App\Models\Unit;
use App\Models\User;
use App\Sites\SiteWatch;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

require_once __DIR__.'/../Fase1b/helpers.php';

const SITUS = 'http://172.31.0.21/';
const HALAMAN_NORMAL = '<html><head><style>.x{}</style><script>var a=1;</script></head><body><p>LINGKUNGAN LATIHAN</p><h1>Portal Berita Kodim Latihan A</h1><ul><li>Latihan menembak triwulan</li><li>Bakti sosial di desa binaan</li><li>Karya bakti perbaikan jalan</li></ul></body></html>';
const HALAMAN_JUDOL = '<html><body><h1>SLOT GACOR MAXWIN HARI INI</h1><p>Daftar situs judi slot online terpercaya: togel, scatter, jackpot.</p></body></html>';

/** Situs tiruan: $state['cloaking'] menyajikan halaman judi untuk Googlebot/referer Google. */
function situsTiruan(array &$state): void
{
    Http::fake(function (Request $request) use (&$state) {
        $googlebot = str_contains($request->header('User-Agent')[0] ?? '', 'Googlebot');
        $bot = $googlebot || str_contains($request->header('Referer')[0] ?? '', 'google.');
        if (($state['redirect_googlebot'] ?? false) && $googlebot) {
            return Http::response('', 302, ['Location' => 'https://situs-judi.example/']);
        }

        return Http::response(($state['cloaking'] ?? false) && $bot ? HALAMAN_JUDOL : ($state['browser'] ?? HALAMAN_NORMAL));
    });
}

beforeEach(function () {
    $this->seed();
    $this->unit = Unit::where('code', 'KODIM-LAT-A')->first();
    $this->site = MonitoredSite::create(['unit_id' => $this->unit->id, 'url' => SITUS]);
    $this->watch = app(SiteWatch::class);
    $this->state = [];
    situsTiruan($this->state);
});

test('pemeriksaan pertama: tiga varian diambil, baseline ditetapkan, tidak ada temuan', function () {
    $checks = $this->watch->check($this->site);

    expect($checks->pluck('variant')->all())->toBe([SiteVariant::Browser, SiteVariant::Googlebot, SiteVariant::Referer])
        ->and($checks->every(fn ($c) => $c->http_status === 200 && ! $c->flagged && $c->similarity === 1.0))->toBeTrue()
        ->and($checks->first()->excerpt)->toContain('portal berita kodim latihan a')
        ->and($checks->first()->excerpt)->not->toContain('var a=1')
        ->and($this->site->fresh()->baseline_hash)->toBe($checks->first()->hash)
        ->and(SecurityEvent::count())->toBe(0);

    Http::assertSent(fn (Request $r) => str_contains($r->header('User-Agent')[0], 'Googlebot/2.1'));
    Http::assertSent(fn (Request $r) => ($r->header('Referer')[0] ?? '') === SiteWatch::GOOGLE_REFERER.'172.31.0.21');
});

test('injeksi judi ber-cloaking: R13, insiden Dampak, tidak berulang tiap pemeriksaan', function () {
    $this->watch->check($this->site);
    $this->state['cloaking'] = true;

    $checks = $this->watch->check($this->site)->keyBy(fn ($c) => $c->variant->value);
    expect($checks['browser']->flagged)->toBeFalse()
        ->and($checks['googlebot']->flagged)->toBeTrue()
        ->and($checks['referer']->flagged)->toBeTrue()
        ->and($checks['googlebot']->similarity)->toBeLessThan(0.8)
        ->and($checks['googlebot']->keywords)->toMatchArray(['slot' => 2, 'gacor' => 1, 'judi' => 1, 'togel' => 1]);

    $event = SecurityEvent::sole();
    $detection = Detection::sole();
    $incident = Incident::sole();
    expect($event->source->value)->toBe('site')
        ->and($event->agent_id)->toBeNull()
        ->and($event->payload['varian'])->toBe(['googlebot', 'referer'])
        ->and($event->summary())->toContain('versi googlebot/referer memuat kata judi (')
        ->and($event->summary())->toContain('gacor')
        ->and($detection->rule->code)->toBe('R13')
        ->and($incident->title)->toBe('Injeksi judi online pada situs 172.31.0.21 (Kodim Latihan A)')
        ->and($incident->stage_max->value)->toBe('dampak')
        ->and($incident->score)->toBe(54)
        ->and($incident->score_breakdown['pengali_aset'])->toMatchArray(['label' => 'Server web', 'host' => 'situs dipantau']);

    // Pemeriksaan berikutnya dengan isi sama: tidak ada event/deteksi baru.
    $this->watch->check($this->site);
    expect(SecurityEvent::count())->toBe(1)->and(Detection::count())->toBe(1);

    // Pulih lalu disuntik lagi: temuan baru.
    $this->state['cloaking'] = false;
    $this->watch->check($this->site);
    $this->state['cloaking'] = true;
    $this->watch->check($this->site);
    expect(SecurityEvent::count())->toBe(2);
});

test('cloaking tertaut ke insiden penyerang di satuan yang sama (atribusi waktu)', function () {
    $web = agentLab('kodim-a');
    deteksi(...pindai($web, 20));
    $this->watch->check($this->site);
    $this->state['cloaking'] = true;
    $this->watch->check($this->site);

    $incident = Incident::sole();
    $r13 = Detection::whereHas('rule', fn ($q) => $q->where('code', 'R13'))->sole();
    expect($r13->incident_id)->toBe($incident->id)
        ->and($r13->attribution->value)->toBe('waktu')
        ->and($incident->src_ip)->toBe(PENYERANG)
        ->and($incident->stage_max->value)->toBe('dampak');
});

test('halaman utama berubah di luar jadwal: R10 versi situs, sekali; baseline bisa diterima', function () {
    $this->watch->check($this->site);
    $baseline = $this->site->fresh()->baseline_hash;
    $this->state['browser'] = '<html><body><h1>HACKED BY LATIHAN</h1></body></html>';

    $checks = $this->watch->check($this->site);
    expect($checks->first()->flagged)->toBeTrue()
        ->and(Detection::sole()->rule->code)->toBe('R10')
        ->and(SecurityEvent::sole()->payload)->toMatchArray(['temuan' => 'defacement', 'hash_lama' => $baseline])
        ->and(Incident::sole()->title)->toBe('Perubahan halaman utama situs 172.31.0.21 (Kodim Latihan A)');

    $this->watch->check($this->site);
    expect(SecurityEvent::count())->toBe(1);

    $analis = User::where('email', 'analis@sentra.lab')->first();
    Livewire::actingAs($analis)->test(SiteWatchPage::class)
        ->assertSee('Halaman utama berubah')
        ->call('acceptBaseline', $this->site->id)
        ->assertSee('Versi browser terakhir dijadikan baseline');
    expect($this->site->fresh()->baseline_hash)->not->toBe($baseline)
        ->and(AuditLog::where('action', 'baseline_situs_diubah')->sole()->user_id)->toBe($analis->id);

    $this->watch->check($this->site);
    expect(SecurityEvent::count())->toBe(1);
});

test('perubahan di dalam jadwal pembaruan menjadi baseline baru tanpa peringatan', function () {
    $this->site->update(['update_windows' => [['mulai' => '00:00', 'selesai' => '23:59']]]);
    $this->watch->check($this->site);
    $this->state['browser'] = '<html><body><h1>Portal Berita Kodim Latihan A</h1><p>Berita baru hari ini</p></body></html>';

    $checks = $this->watch->check($this->site);
    expect($checks->first()->flagged)->toBeFalse()
        ->and($this->site->fresh()->baseline_hash)->toBe($checks->first()->hash)
        ->and(SecurityEvent::count())->toBe(0)
        ->and(AuditLog::where('action', 'baseline_situs_diperbarui')->count())->toBe(1);
});

test('berita resmi tentang judi di semua versi bukan cloaking', function () {
    $this->state['browser'] = '<html><body><h1>Kodim Latihan A memerangi judi online</h1><p>Sosialisasi bahaya judi dan slot online kepada warga.</p></body></html>';
    $this->watch->check($this->site);
    $checks = $this->watch->check($this->site);

    expect($checks->every(fn ($c) => ! $c->flagged))->toBeTrue()
        ->and($checks->first()->gambling_hits)->toBe(3)
        ->and(SecurityEvent::count())->toBe(0);
});

test('hanya situs di jaringan privat; pengalihan tidak diikuti', function () {
    $public = MonitoredSite::create(['unit_id' => $this->unit->id, 'url' => 'http://8.8.8.8/']);
    Http::fake();
    $checks = $this->watch->check($public);
    expect($checks->every(fn ($c) => str_contains($c->error, 'bukan jaringan privat') && $c->hash === null))->toBeTrue();
    Http::assertNothingSent();

    expect(SiteWatch::urlRefusal('ftp://172.31.0.21/'))->toContain('http')
        ->and(SiteWatch::urlRefusal('http://10.1.2.3/'))->toBeNull();
    config(['sentra.sites.allow_public' => true]);
    expect(SiteWatch::urlRefusal('http://8.8.8.8/'))->toBeNull();
});

test('pengalihan ke situs lain tidak diikuti', function () {
    $this->state['redirect_googlebot'] = true;

    $checks = $this->watch->check($this->site)->keyBy(fn ($c) => $c->variant->value);
    expect($checks['googlebot']->http_status)->toBe(302)
        ->and($checks['googlebot']->error)->toBe('Dialihkan ke https://situs-judi.example/');
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'situs-judi.example'));
});

test('halaman Penjaga Situs: Analis mengelola, Demo melihat, peran lain ditolak', function () {
    $this->watch->check($this->site);
    $this->state['cloaking'] = true;
    $this->watch->check($this->site);
    $analis = User::where('email', 'analis@sentra.lab')->first();

    Livewire::actingAs($analis)->test(SiteWatchPage::class)
        ->assertSee(SITUS)
        ->assertSee('Cloaking judi')
        ->assertSee('Googlebot')
        ->assertSee('Referer Google')
        ->assertSeeHtml('<mark class="rounded bg-red-700/80 px-0.5 text-white">slot</mark>')
        ->assertSee('Insiden terkait')
        ->call('checkNow', $this->site->id)
        ->assertSee('Pemeriksaan selesai.')
        ->set('unitId', $this->unit->id)
        ->set('url', 'http://172.31.0.22/')
        ->set('windows', '22:00-23:00, 05:00-05:30')
        ->call('add')
        ->assertHasNoErrors()
        ->set('url', 'http://8.8.4.4/')
        ->call('add')
        ->assertHasErrors('url')
        ->set('url', 'http://172.31.0.23/')
        ->set('windows', 'malam hari')
        ->call('add')
        ->assertHasErrors('windows');

    $new = MonitoredSite::where('url', 'http://172.31.0.22/')->sole();
    expect($new->update_windows)->toBe([['mulai' => '22:00', 'selesai' => '23:00'], ['mulai' => '05:00', 'selesai' => '05:30']])
        ->and(AuditLog::where('action', 'situs_ditambah')->sole()->user_id)->toBe($analis->id);

    Livewire::actingAs($analis)->test(SiteWatchPage::class)->call('remove', $new->id);
    expect(MonitoredSite::count())->toBe(1)->and(AuditLog::where('action', 'situs_dihapus')->count())->toBe(1);

    $demo = User::where('email', 'demo@sentra.lab')->first();
    $this->actingAs($demo)->get(route('sites'))->assertOk()->assertSee('Cloaking judi')->assertDontSee('Tambah situs');
    Livewire::actingAs($demo)->test(SiteWatchPage::class)->call('checkNow', $this->site->id)->assertForbidden();

    foreach (['operator.a@sentra.lab', 'komandan@sentra.lab', 'admin@sentra.lab'] as $email) {
        $this->actingAs(User::where('email', $email)->first())->get(route('sites'))->assertForbidden();
    }
});

test('perintah sentra:penjaga-situs dan pendaftaran situs lab', function () {
    $this->state['cloaking'] = true;
    $this->watch->check($this->site);
    Artisan::call('sentra:penjaga-situs');
    expect(Artisan::output())->toContain(SITUS);

    config(['sentra.lab.sites' => 'KODIM-LAT-B=http://kodim-b/,TIDAK-ADA=http://x/', 'sentra.lab.agents' => '']);
    Artisan::call('sentra:setup', ['--no-optimize' => true]);
    expect(MonitoredSite::where('url', 'http://kodim-b/')->sole()->unit->code)->toBe('KODIM-LAT-B')
        ->and(MonitoredSite::count())->toBe(2);
});
