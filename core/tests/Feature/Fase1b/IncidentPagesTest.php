<?php

use App\Livewire\Admin\Rules;
use App\Livewire\Incidents\IncidentList;
use App\Livewire\Incidents\IncidentShow;
use App\Models\AuditLog;
use App\Models\DetectionRule;
use App\Models\Incident;
use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Models\User;
use Livewire\Livewire;

require_once __DIR__.'/helpers.php';

function pengguna(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

/** Skenario contoh PRD (240, Kritis) di Kodim A. */
function skenarioPrd($agent): Incident
{
    deteksi(...pindai($agent, 15, now()->subMinutes(5)));
    deteksi(...sshGagal($agent, 10, now()->subMinutes(4)));
    deteksi(eventLab($agent, 'ssh', ['outcome' => 'success', 'user' => 'latihan'], PENYERANG, 22, now()->subMinutes(3)));
    deteksi(eventLab($agent, 'fim', ['path' => '/var/www/html/uploads/x.php', 'change' => 'created', 'sample' => '<?php system($_GET["c"]); ?>'], null, null, now()->subMinutes(2)));

    return Incident::sole();
}

beforeEach(function () {
    $this->seed();
    $this->web = agentLab('kodim-a');
});

test('detail insiden menampilkan Attack Story, ATT&CK, rincian skor, dan bukti', function () {
    $incident = skenarioPrd($this->web);

    $this->actingAs(pengguna('analis@sentra.lab'))
        ->get(route('incidents.show', $incident))
        ->assertOk()
        ->assertSeeInOrder(['Pengintaian', 'Akses Awal', 'Eksekusi/Persistensi', 'Dampak'])
        ->assertSee('T1046')
        ->assertSee('T1505.003')
        ->assertSee('Atribusi berdasarkan waktu')
        ->assertSee('Login berhasil setelah brute force')
        ->assertSee('+50')
        ->assertSee('(160 + 40) x 1.2 x 1 = 240')
        ->assertSee('Kritis')
        ->assertSee('EV-');
});

test('daftar insiden: operator hanya melihat satuannya', function () {
    skenarioPrd($this->web);
    $kodimB = agentLab('kodim-b', 'KODIM-LAT-B');
    deteksi(eventLab($kodimB, 'web', ['user_agent' => 'nikto', 'path' => '/'], '172.30.0.99'));

    Livewire::actingAs(pengguna('operator.b@sentra.lab'))->test(IncidentList::class)
        ->assertSee('172.30.0.99')
        ->assertDontSee(PENYERANG);

    Livewire::actingAs(pengguna('analis@sentra.lab'))->test(IncidentList::class)
        ->assertSee('172.30.0.99')
        ->assertSee(PENYERANG)
        ->set('severity', 'kritis')
        ->assertSee(PENYERANG)
        ->assertDontSee('172.30.0.99');
});

test('operator satuan lain tidak bisa membuka detail insiden maupun event buktinya', function () {
    $incident = skenarioPrd($this->web);
    $event = SecurityEvent::first();

    $this->actingAs(pengguna('operator.b@sentra.lab'))->get(route('incidents.show', $incident))->assertForbidden();
    $this->actingAs(pengguna('operator.b@sentra.lab'))->get(route('events.show', $event))->assertForbidden();
    $this->actingAs(pengguna('operator.a@sentra.lab'))->get(route('events.show', $event))->assertOk()->assertSee('Koneksi ke port tertutup');
});

test('ubah status insiden tercatat di audit log, admin dan demo tidak bisa', function () {
    $incident = skenarioPrd($this->web);

    Livewire::actingAs(pengguna('operator.a@sentra.lab'))->test(IncidentShow::class, ['incident' => $incident])
        ->call('setStatus', 'diselidiki')
        ->assertHasNoErrors();
    expect($incident->fresh()->status->value)->toBe('diselidiki')
        ->and(AuditLog::where('action', 'status_insiden_diubah')->sole()->data)->toEqualCanonicalizing(['dari' => 'baru', 'ke' => 'diselidiki']);

    foreach (['admin@sentra.lab', 'demo@sentra.lab'] as $email) {
        Livewire::actingAs(pengguna($email))->test(IncidentShow::class, ['incident' => $incident])
            ->call('setStatus', 'selesai')
            ->assertForbidden();
    }
    expect($incident->fresh()->status->value)->toBe('diselidiki');
});

test('menyelesaikan insiden mengembalikan Tingkat Siaga satuan', function () {
    $incident = skenarioPrd($this->web);
    expect($incident->unit->fresh()->alert_level->value)->toBe('awas');

    Livewire::actingAs(pengguna('komandan@sentra.lab'))->test(IncidentShow::class, ['incident' => $incident])
        ->call('setStatus', 'selesai');

    expect($incident->unit->fresh()->alert_level->value)->toBe('aman');
});

test('Beranda menampilkan Tingkat Siaga dari insiden asli', function () {
    skenarioPrd($this->web);

    $this->actingAs(pengguna('analis@sentra.lab'))->get('/beranda')->assertSee('Awas');
});

test('admin mengubah ambang aturan dan tercatat di audit', function () {
    $rule = DetectionRule::where('code', 'R07')->first();

    Livewire::actingAs(pengguna('admin@sentra.lab'))->test(Rules::class)
        ->set("form.{$rule->id}.threshold", 5)
        ->set("form.{$rule->id}.window_s", 120)
        ->set("form.{$rule->id}.weight", 35)
        ->call('saveRule', $rule->id)
        ->assertHasNoErrors();

    $rule->refresh();
    expect($rule->config['threshold'])->toBe(5)
        ->and($rule->config['window_s'])->toBe(120)
        ->and($rule->weight)->toBe(35)
        ->and(AuditLog::where('action', 'aturan_diubah')->sole()->data['sesudah']['config'])->toEqualCanonicalizing(['threshold' => 5, 'window_s' => 120]);
});

test('admin menonaktifkan aturan', function () {
    $rule = DetectionRule::where('code', 'R02')->first();

    Livewire::actingAs(pengguna('admin@sentra.lab'))->test(Rules::class)
        ->set("form.{$rule->id}.enabled", false)
        ->call('saveRule', $rule->id);

    expect($rule->fresh()->enabled)->toBeFalse();
});

test('daftar putih IP divalidasi lalu disimpan', function () {
    Livewire::actingAs(pengguna('admin@sentra.lab'))->test(Rules::class)
        ->set('whitelist', "172.32.0.50\nbukan-ip")
        ->call('saveWhitelist')
        ->assertHasErrors('whitelist')
        ->set('whitelist', "172.32.0.50\n10.10.0.0/24")
        ->call('saveWhitelist')
        ->assertHasNoErrors();

    expect(Setting::get('ip_whitelist'))->toBe(['172.32.0.50', '10.10.0.0/24'])
        ->and(AuditLog::where('action', 'daftar_putih_diubah')->count())->toBe(1);
});

test('hanya admin yang membuka halaman Aturan Deteksi', function (string $email, int $status) {
    $this->actingAs(pengguna($email))->get('/aturan')->assertStatus($status);
})->with([
    ['admin@sentra.lab', 200],
    ['analis@sentra.lab', 403],
    ['komandan@sentra.lab', 403],
]);
