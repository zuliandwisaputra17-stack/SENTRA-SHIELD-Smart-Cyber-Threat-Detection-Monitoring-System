<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Baris audit berantai hash (FR-11). Aplikasi hanya menambah baris; perubahan
 * langsung di database akan terdeteksi oleh verifikasi rantai.
 */
#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'data', 'prev_hash', 'hash', 'created_at'])]
class AuditLog extends Model
{
    /** Simpan pecahan detik (kolom DATETIME(3)/(6)). */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Audit log tidak bisa diubah.');
        });
        static::deleting(function () {
            throw new LogicException('Audit log tidak bisa dihapus.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
