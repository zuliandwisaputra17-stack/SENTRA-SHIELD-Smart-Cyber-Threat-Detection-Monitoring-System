<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;

test('setiap baris menyimpan hash baris sebelumnya', function () {
    $audit = app(AuditLogger::class);
    $user = User::factory()->create();

    $a = $audit->record('login', $user, ['ip' => '10.0.0.1', 'b' => ['z' => 1, 'a' => 2]], $user);
    $b = $audit->record('agent_dibuat', null, ['hostname' => 'kodim-a']);

    expect($a->prev_hash)->toBe(AuditLogger::GENESIS)
        ->and($b->prev_hash)->toBe($a->hash)
        ->and(strlen($b->hash))->toBe(64);

    expect($audit->verify())->toMatchArray(['ok' => true, 'checked' => 2, 'broken_id' => null]);
});

test('perubahan langsung di database terdeteksi pada baris pertama yang rusak', function () {
    $audit = app(AuditLogger::class);
    foreach (range(1, 5) as $i) {
        $audit->record('aksi_'.$i, null, ['urutan' => $i]);
    }
    $target = AuditLog::orderBy('id')->skip(2)->first();

    DB::table('audit_logs')->where('id', $target->id)->update(['data' => json_encode(['urutan' => 99])]);

    $result = $audit->verify();
    expect($result['ok'])->toBeFalse()
        ->and($result['broken_id'])->toBe($target->id)
        ->and($result['checked'])->toBe(2);
});

test('baris yang dihapus memutus rantai', function () {
    $audit = app(AuditLogger::class);
    foreach (range(1, 4) as $i) {
        $audit->record('aksi_'.$i);
    }
    $ids = AuditLog::orderBy('id')->pluck('id');
    DB::table('audit_logs')->where('id', $ids[1])->delete();

    expect($audit->verify())->toMatchArray(['ok' => false, 'broken_id' => $ids[2]]);
});

test('login berhasil dan gagal tercatat di audit log', function () {
    $user = User::factory()->create();

    $this->post('/logout');
    Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'salah')->call('login');
    Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'password')->call('login');

    expect(AuditLog::pluck('action')->all())->toBe(['login_gagal', 'login'])
        ->and(app(AuditLogger::class)->verify()['ok'])->toBeTrue();
});
