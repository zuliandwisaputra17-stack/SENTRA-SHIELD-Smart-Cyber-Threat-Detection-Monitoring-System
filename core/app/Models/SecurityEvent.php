<?php

namespace App\Models;

use App\Enums\EventSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Event telemetri (tabel `events`). Append-only: aplikasi tidak pernah
 * mengubah atau menghapus event setelah disimpan (PRD bagian 6, Integritas).
 */
#[Fillable([
    'agent_id', 'unit_id', 'source', 'src_ip', 'dst_port', 'payload', 'raw',
    'is_test', 'occurred_at', 'created_at',
])]
class SecurityEvent extends Model
{
    /** Simpan pecahan detik (kolom DATETIME(3)/(6)). */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    use HasFactory;

    protected $table = 'events';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'source' => EventSource::class,
            'payload' => 'array',
            'is_test' => 'boolean',
            'dst_port' => 'integer',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Event telemetri tidak bisa diubah.');
        });
        static::deleting(function () {
            throw new LogicException('Event telemetri tidak bisa dihapus.');
        });
    }

    /** ID tampilan untuk sitasi bukti, contoh EV-1042. */
    public function reference(): string
    {
        return 'EV-'.$this->id;
    }

    /** Ringkasan satu baris untuk tabel bukti. */
    public function summary(): string
    {
        $p = $this->payload ?? [];

        return match ($this->source) {
            EventSource::Web => trim(sprintf('%s %s%s -> %s', $p['method'] ?? '', $p['path'] ?? '', ($p['query'] ?? '') !== '' ? '?'.mb_strimwidth(urldecode((string) $p['query']), 0, 120, '...') : '', $p['status'] ?? '?'))
                .(($p['user_agent'] ?? '') !== '' ? ' ('.mb_strimwidth($p['user_agent'], 0, 40, '...').')' : ''),
            EventSource::Ssh => sprintf('Login %s, user %s%s', ($p['outcome'] ?? '') === 'success' ? 'BERHASIL' : 'gagal', $p['user'] ?? '?', ! empty($p['invalid_user']) ? ' (user tidak ada)' : ''),
            EventSource::Firewall => sprintf('Koneksi ke port tertutup %s/%s', $this->dst_port ?? '?', $p['protocol'] ?? 'tcp'),
            EventSource::Fim => sprintf('File %s: %s', match ($p['change'] ?? '') {
                'created' => 'dibuat', 'modified' => 'diubah', 'deleted' => 'dihapus', default => $p['change'] ?? '?',
            }, $p['path'] ?? '?'),
            EventSource::Modbus => sprintf('FC%s %s, alamat %s, nilai %s', $p['function_code'] ?? '?', $p['function_name'] ?? '', $p['address'] ?? '?', $p['value'] ?? ($p['quantity'] ?? '?')),
            EventSource::Site => match ($p['temuan'] ?? null) {
                'cloaking' => sprintf('Situs %s: versi %s memuat kata judi (%s), kemiripan dengan versi browser %d%%',
                    $p['url'] ?? '?', implode('/', (array) ($p['varian'] ?? [])), implode(', ', array_keys((array) ($p['kata_kunci'] ?? []))), (int) round(((float) ($p['kemiripan'] ?? 0)) * 100)),
                'defacement' => sprintf('Situs %s: halaman utama berubah di luar jadwal pembaruan', $p['url'] ?? '?'),
                default => (string) ($p['url'] ?? 'pemeriksaan situs'),
            },
        };
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function detections(): HasMany
    {
        return $this->hasMany(Detection::class, 'event_id');
    }
}
