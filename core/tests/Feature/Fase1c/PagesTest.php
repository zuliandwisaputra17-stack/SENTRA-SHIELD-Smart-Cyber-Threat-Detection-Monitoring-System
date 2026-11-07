<?php

use App\Enums\ActionStatus;
use App\Enums\AgentStatus;
use App\Livewire\Actions\ActionHistory;
use App\Livewire\Actions\ApprovalQueue;
use App\Livewire\Dashboard\CommandDashboard;
use App\Livewire\Incidents\IncidentShow;
use App\Models\Incident;
use App\Models\ResponseAction;
use App\Models\User;
use App\Response\ActionService;
use Livewire\Livewire;

require_once __DIR__.'/../Fase1b/helpers.php';

function orang(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

beforeEach(function () {
    $this->seed();
    $this->web = agentLab('kodim-a');
    $this->kodimB = agentLab('kodim-b', 'KODIM-LAT-B');
    deteksi(...pindai($this->web, 15, now()->subMinutes(3)));
    deteksi(...sshGagal($this->web, 10, now()->subMinutes(2)));
    $this->incident = Incident::sole();
});

test('Beranda menampilkan metrik dari data asli', function () {
    $this->kodimB->update(['status' => AgentStatus::Buta]);
    $group = app(ActionService::class)->proposeBlock($this->incident, orang('operator.a@sentra.lab'), 3600);
    app(ActionService::class)->decide($group, orang('komandan@sentra.lab'), true);
    ResponseAction::where('group_uuid', $group)->update(['status' => ActionStatus::Dieksekusi, 'executed_at' => now(), 'expires_at' => now()->addHour()]);

    Livewire::actingAs(orang('analis@sentra.lab'))->test(CommandDashboard::class)
        ->assertSeeInOrder(['Insiden terbuka', '1'])
        ->assertSeeInOrder(['Agent online', '1', '/ 2'])
        ->assertSee('1 agent BUTA')
        ->assertSeeInOrder(['Blokir aktif', '1'])
        ->assertSee('1 IP diblokir')
        ->assertSee('Event per menit')
        ->assertSee('EV-')
        ->assertSee('R07');
});

test('Beranda operator hanya menghitung satuannya', function () {
    Livewire::actingAs(orang('operator.b@sentra.lab'))->test(CommandDashboard::class)
        ->assertSee('Tidak ada insiden terbuka')
        ->assertDontSee('EV-');
});

test('grafik menghitung event nyata per menit dan mengabaikan event uji', function () {
    eventLab($this->web, 'web', ['path' => '/'], '10.1.1.1', null, now(), true);

    $series = Livewire::actingAs(orang('analis@sentra.lab'))->test(CommandDashboard::class)->get('series');

    expect($series['labels'])->toHaveCount(CommandDashboard::CHART_MINUTES)
        ->and(array_sum($series['counts']))->toBe(25);
});

test('operator mengusulkan blokir dari Detail Insiden', function () {
    Livewire::actingAs(orang('operator.a@sentra.lab'))->test(IncidentShow::class, ['incident' => $this->incident])
        ->assertSee('Usulkan blokir IP')
        ->set('duration', 900)
        ->call('proposeBlock')
        ->assertHasNoErrors()
        ->assertSee('Usulan blokir dikirim');

    expect(ResponseAction::where('incident_id', $this->incident->id)->sole()->params['duration_s'])->toBe(900);
});

test('komandan tidak melihat tombol usul; usulan ganda menampilkan pesan', function () {
    Livewire::actingAs(orang('komandan@sentra.lab'))->test(IncidentShow::class, ['incident' => $this->incident])
        ->assertDontSee('Usulkan blokir IP');

    app(ActionService::class)->proposeBlock($this->incident, orang('operator.a@sentra.lab'), 3600);
    Livewire::actingAs(orang('analis@sentra.lab'))->test(IncidentShow::class, ['incident' => $this->incident])
        ->call('proposeBlock')
        ->assertSee('Sudah ada usulan atau blokir aktif');
});

test('antrean persetujuan: komandan menyetujui dari halaman', function () {
    $group = app(ActionService::class)->proposeBlock($this->incident, orang('operator.a@sentra.lab'), 3600);

    Livewire::actingAs(orang('komandan@sentra.lab'))->test(ApprovalQueue::class)
        ->assertSee(PENYERANG)
        ->assertSee('Operator Kodim A')
        ->call('approve', $group)
        ->assertSee('Disetujui. Perintah dikirim ke 1 agent');

    expect(ResponseAction::where('group_uuid', $group)->sole()->status)->toBe(ActionStatus::Disetujui);
});

test('antrean persetujuan: tolak tanpa alasan ditolak, dengan alasan berhasil', function () {
    $group = app(ActionService::class)->proposeBlock($this->incident, orang('operator.a@sentra.lab'), 3600);

    Livewire::actingAs(orang('komandan@sentra.lab'))->test(ApprovalQueue::class)
        ->call('reject', $group)
        ->assertHasErrors("keputusan.{$group}")
        ->set("note.{$group}", 'IP latihan internal')
        ->call('reject', $group)
        ->assertSee('Usulan ditolak.');

    expect(ResponseAction::where('group_uuid', $group)->sole()->decision_note)->toBe('IP latihan internal');
});

test('hanya komandan yang membuka Antrean Persetujuan', function (string $email, int $status) {
    $this->actingAs(orang($email))->get('/persetujuan')->assertStatus($status);
})->with([
    ['komandan@sentra.lab', 200],
    ['operator.a@sentra.lab', 403],
    ['analis@sentra.lab', 403],
    ['admin@sentra.lab', 403],
]);

test('navigasi komandan menampilkan jumlah usulan menunggu', function () {
    app(ActionService::class)->proposeBlock($this->incident, orang('operator.a@sentra.lab'), 3600);

    $this->actingAs(orang('komandan@sentra.lab'))->get('/beranda')
        ->assertSee('Antrean Persetujuan')
        ->assertSee('1 menunggu', false);
});

test('rollback dari Detail Insiden oleh komandan', function () {
    $group = app(ActionService::class)->proposeBlock($this->incident, orang('operator.a@sentra.lab'), 3600);
    app(ActionService::class)->decide($group, orang('komandan@sentra.lab'), true);
    $block = ResponseAction::where('group_uuid', $group)->sole();
    $block->update(['status' => ActionStatus::Dieksekusi, 'executed_at' => now(), 'expires_at' => now()->addHour()]);

    Livewire::actingAs(orang('komandan@sentra.lab'))->test(IncidentShow::class, ['incident' => $this->incident])
        ->assertSee('Buka blokir')
        ->call('rollback', $block->id)
        ->assertSee('Perintah buka blokir dikirim');

    expect(ResponseAction::where('type', 'unblock_ip')->sole()->params['rollback_of'])->toBe($block->id);
});

test('riwayat tindakan: filter blokir aktif dan dibatasi per satuan', function () {
    $group = app(ActionService::class)->proposeBlock($this->incident, orang('operator.a@sentra.lab'), 3600);
    app(ActionService::class)->decide($group, orang('komandan@sentra.lab'), true);
    ResponseAction::where('group_uuid', $group)->update(['status' => ActionStatus::Dieksekusi, 'executed_at' => now(), 'expires_at' => now()->addHour()]);

    Livewire::actingAs(orang('analis@sentra.lab'))->test(ActionHistory::class, ['status' => 'aktif'])
        ->assertSee(PENYERANG)
        ->assertSee('Dieksekusi');
    Livewire::actingAs(orang('operator.b@sentra.lab'))->test(ActionHistory::class)
        ->assertDontSee(PENYERANG);
});
