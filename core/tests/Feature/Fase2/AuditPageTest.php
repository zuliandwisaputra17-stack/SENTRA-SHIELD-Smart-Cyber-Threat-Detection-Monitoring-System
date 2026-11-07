<?php

use App\Livewire\Audit\AuditLogPage;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->audit = app(AuditLogger::class);
    $this->analis = User::where('email', 'analis@sentra.lab')->first();
    $this->komandan = User::where('email', 'komandan@sentra.lab')->first();
});

function isiAudit(): void
{
    $audit = app(AuditLogger::class);
    $analis = User::where('email', 'analis@sentra.lab')->first();
    $komandan = User::where('email', 'komandan@sentra.lab')->first();
    $incident = Incident::create([
        'unit_id' => Unit::where('code', 'KODIM-LAT-A')->value('id'), 'src_ip' => '172.30.0.66', 'title' => 'Serangan uji',
        'severity' => 'tinggi', 'status' => 'baru', 'first_seen_at' => now(), 'last_seen_at' => now(),
    ]);

    $audit->record('login', $analis, ['ip' => '10.0.0.5'], $analis);
    $audit->record('status_insiden_diubah', $incident, ['dari' => 'baru', 'ke' => 'ditangani'], $analis);
    $audit->record('tindakan_disetujui', $incident, ['ip' => '172.30.0.66'], $komandan);
    $audit->record('agent_buta', $incident, ['agent' => 'kodim-b'], null);
}

test('Analis, Komandan, dan Admin membuka Audit Log dan memverifikasi rantai', function (string $email) {
    isiAudit();
    $user = User::where('email', $email)->first();

    $this->actingAs($user)->get(route('audit'))
        ->assertOk()
        ->assertSee('Audit Log')
        ->assertSee('Verifikasi Rantai')
        ->assertSee('Status insiden diubah')
        ->assertSee('dari: baru')
        ->assertSee('ke: ditangani')
        ->assertSee('Insiden #')
        ->assertSee('Sistem');

    Livewire::actingAs($user)->test(AuditLogPage::class)
        ->call('verify')
        ->assertSee('Rantai utuh.')
        ->assertSee('4 baris diperiksa');

    $log = AuditLog::query()->latest('id')->first();
    expect($log->action)->toBe('audit_diverifikasi')
        ->and($log->user_id)->toBe($user->id)
        ->and($log->data)->toMatchArray(['utuh' => true, 'baris_diperiksa' => 4, 'baris_rusak' => null]);
})->with(['analis@sentra.lab', 'komandan@sentra.lab', 'admin@sentra.lab']);

test('baris yang diubah langsung di database: verifikasi menunjukkan baris pertama yang rusak', function () {
    isiAudit();
    $target = AuditLog::where('action', 'status_insiden_diubah')->sole();
    DB::table('audit_logs')->where('id', $target->id)->update(['data' => json_encode(['dari' => 'baru', 'ke' => 'selesai'])]);

    Livewire::actingAs($this->analis)->test(AuditLogPage::class)
        ->call('verify')
        ->assertSee("Rantai RUSAK mulai baris #{$target->id}")
        ->assertSee('isi baris tidak cocok dengan hash-nya')
        ->assertSee('1 baris sebelumnya utuh')
        ->assertSeeHtml('data-testid="baris-rusak"')
        ->call('showRow', $target->id)
        ->assertSet('baris', $target->id)
        ->assertSeeHtml('data-testid="baris-rusak"');

    expect(AuditLog::latest('id')->first()->data)->toMatchArray(['utuh' => false, 'baris_rusak' => $target->id]);
});

test('baris yang dihapus langsung di database terdeteksi pada baris sesudahnya', function () {
    isiAudit();
    $ids = AuditLog::orderBy('id')->pluck('id');
    DB::table('audit_logs')->where('id', $ids[1])->delete();

    Livewire::actingAs($this->komandan)->test(AuditLogPage::class)
        ->call('verify')
        ->assertSee("Rantai RUSAK mulai baris #{$ids[2]}")
        ->assertSee('prev_hash tidak sama dengan hash baris sebelumnya');
});

/** Aksi baris yang tampil di tabel (bukan di pilihan filter). */
function aksiTampil($page): array
{
    return $page->viewData('logs')->pluck('action')->all();
}

test('filter aksi, pengguna, sistem, dan tanggal', function () {
    isiAudit();
    $page = Livewire::actingAs($this->analis)->test(AuditLogPage::class);
    expect(aksiTampil($page))->toBe(['agent_buta', 'tindakan_disetujui', 'status_insiden_diubah', 'login']);

    $page->set('aksi', 'tindakan_disetujui');
    expect(aksiTampil($page))->toBe(['tindakan_disetujui']);

    $page->set('aksi', '')->set('pengguna', (string) $this->analis->id);
    expect(aksiTampil($page))->toBe(['status_insiden_diubah', 'login']);

    $page->set('pengguna', AuditLogPage::SYSTEM_USER);
    expect(aksiTampil($page))->toBe(['agent_buta']);

    $kemarin = Carbon::now(config('sentra.display_timezone'))->subDay()->format('Y-m-d');
    $hariIni = Carbon::now(config('sentra.display_timezone'))->format('Y-m-d');
    $page->set('pengguna', '')->set('sampai', $kemarin)->assertSee('Tidak ada baris audit.');
    $page->set('sampai', '')->set('dari', $hariIni);
    expect(aksiTampil($page))->toHaveCount(4);

    $page->set('dari', 'bukan-tanggal');
    expect(aksiTampil($page))->toHaveCount(4);

    $page->call('resetFilters')->assertSet('dari', '');
    expect(aksiTampil($page))->toHaveCount(4);
});

test('Demo hanya melihat tanpa tombol verifikasi; Operator tidak punya akses', function () {
    isiAudit();
    $demo = User::where('email', 'demo@sentra.lab')->first();

    $this->actingAs($demo)->get(route('audit'))
        ->assertOk()
        ->assertSee('Status insiden diubah')
        ->assertDontSee('wire:click="verify"', false);
    Livewire::actingAs($demo)->test(AuditLogPage::class)->call('verify')->assertForbidden();

    $this->actingAs(User::where('email', 'operator.a@sentra.lab')->first())->get(route('audit'))->assertForbidden();
    expect(AuditLog::where('action', 'audit_diverifikasi')->count())->toBe(0);
});

test('menu Sistem untuk Admin, menu Audit Log sesuai peran', function () {
    $admin = User::where('email', 'admin@sentra.lab')->first();
    $this->actingAs($admin)->get(route('dashboard'))
        ->assertSee('Aturan Deteksi')->assertSee('Pengguna')->assertSee('Audit Log');

    $this->actingAs(User::where('email', 'operator.a@sentra.lab')->first())->get(route('dashboard'))
        ->assertDontSee('Audit Log')->assertDontSee('Aturan Deteksi');
});
