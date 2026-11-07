<?php

use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\Unit;
use Illuminate\Support\Facades\Schema;

test('15 tabel inti PRD bagian 8 tersedia', function () {
    $tables = [
        'units', 'users', 'agents', 'events', 'detection_rules', 'detections',
        'incidents', 'campaigns', 'actions', 'audit_logs', 'readiness_runs',
        'readiness_results', 'monitored_sites', 'site_checks', 'reports',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("tabel {$table}");
    }
    expect(Schema::hasTable('settings'))->toBeTrue()
        ->and(Schema::hasTable('personal_access_tokens'))->toBeTrue();
});

test('tabel events diberi indeks (unit_id, src_ip, occurred_at)', function () {
    $indexes = collect(Schema::getIndexes('events'))->pluck('columns')->all();

    expect($indexes)->toContain(['unit_id', 'src_ip', 'occurred_at']);
});

test('kolom tambahan yang disetujui tersedia', function () {
    expect(Schema::hasColumns('agents', ['asset_type', 'enrollment_code_hash', 'enrollment_expires_at', 'sources']))->toBeTrue()
        ->and(Schema::hasColumn('agents', 'token_hash'))->toBeFalse()
        ->and(Schema::hasColumn('monitored_sites', 'update_windows'))->toBeTrue()
        ->and(Schema::hasColumn('detections', 'attribution'))->toBeTrue();
});

test('event telemetri tidak bisa diubah atau dihapus lewat aplikasi', function () {
    $agent = Agent::factory()->create();
    $event = SecurityEvent::create([
        'agent_id' => $agent->id,
        'unit_id' => $agent->unit_id,
        'source' => 'web',
        'src_ip' => '172.30.0.66',
        'payload' => ['method' => 'GET', 'path' => '/'],
        'raw' => 'GET /',
        'is_test' => false,
        'occurred_at' => now(),
        'created_at' => now(),
    ]);

    expect(fn () => $event->update(['src_ip' => '10.0.0.1']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class)
        ->and($event->fresh()->src_ip)->toBe('172.30.0.66')
        ->and($event->reference())->toBe('EV-'.$event->id);
});

test('baris audit log tidak bisa diubah lewat aplikasi', function () {
    $log = AuditLog::create([
        'action' => 'uji', 'data' => [], 'prev_hash' => str_repeat('0', 64),
        'hash' => str_repeat('a', 64), 'created_at' => now(),
    ]);

    expect(fn () => $log->update(['action' => 'ubah']))->toThrow(LogicException::class);
});

test('agent memakai token Sanctum yang tersimpan ter-hash', function () {
    $agent = Agent::factory()->create(['unit_id' => Unit::factory()]);
    $plain = $agent->createToken('agent')->plainTextToken;

    [, $secret] = explode('|', $plain, 2);
    expect($agent->tokens()->first()->token)->toBe(hash('sha256', $secret))
        ->not->toBe($secret);
});
