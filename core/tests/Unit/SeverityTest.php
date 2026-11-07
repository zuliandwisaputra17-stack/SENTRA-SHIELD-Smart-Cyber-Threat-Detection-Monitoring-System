<?php

use App\Enums\AlertLevel;
use App\Enums\Severity;

test('severity ditentukan dari skor sesuai batas PRD', function (int $score, Severity $expected) {
    expect(Severity::fromScore($score))->toBe($expected);
})->with([
    [0, Severity::Rendah], [39, Severity::Rendah],
    [40, Severity::Sedang], [79, Severity::Sedang],
    [80, Severity::Tinggi], [149, Severity::Tinggi],
    [150, Severity::Kritis], [240, Severity::Kritis],
]);

test('severity berpadanan dengan Tingkat Siaga', function () {
    expect(AlertLevel::fromSeverity(Severity::Rendah))->toBe(AlertLevel::Aman)
        ->and(AlertLevel::fromSeverity(Severity::Sedang))->toBe(AlertLevel::Waspada)
        ->and(AlertLevel::fromSeverity(Severity::Tinggi))->toBe(AlertLevel::Siaga)
        ->and(AlertLevel::fromSeverity(Severity::Kritis))->toBe(AlertLevel::Awas)
        ->and(AlertLevel::fromSeverity(null))->toBe(AlertLevel::Aman);
});

test('tingkat siaga tertinggi dipilih', function () {
    expect(AlertLevel::highest(AlertLevel::Aman, AlertLevel::Siaga, AlertLevel::Waspada))->toBe(AlertLevel::Siaga)
        ->and(AlertLevel::highest())->toBe(AlertLevel::Aman);
});
