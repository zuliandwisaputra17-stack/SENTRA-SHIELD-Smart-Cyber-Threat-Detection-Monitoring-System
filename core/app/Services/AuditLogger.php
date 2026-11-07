<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Audit log berantai hash (FR-11, keputusan A6).
 *
 * hash = SHA-256(prev_hash + "|" + JSON kanonik isi baris). Baris pertama
 * memakai prev_hash 64 angka nol. Penulisan diserialkan dengan named lock
 * MySQL agar dua permintaan bersamaan tidak membuat rantai bercabang.
 */
class AuditLogger
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    private const LOCK = 'sentra_audit_chain';

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(string $action, ?Model $subject = null, array $data = [], ?User $user = null): AuditLog
    {
        $user ??= Auth::user() instanceof User ? Auth::user() : null;

        return $this->withLock(function () use ($action, $subject, $data, $user) {
            $prev = AuditLog::query()->orderByDesc('id')->value('hash') ?? self::GENESIS;
            $row = [
                'user_id' => $user?->id,
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'data' => $data,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s.u'),
            ];

            return AuditLog::query()->create($row + [
                'prev_hash' => $prev,
                'hash' => self::hashRow($prev, $row),
            ]);
        });
    }

    /**
     * @param  array{user_id: ?int, action: string, subject_type: ?string, subject_id: ?int, data: array<string, mixed>, created_at: string}  $row
     */
    public static function hashRow(string $prevHash, array $row): string
    {
        $canonical = [
            'action' => $row['action'],
            'created_at' => $row['created_at'],
            'data' => self::canonicalize($row['data']),
            'subject_id' => $row['subject_id'] === null ? null : (int) $row['subject_id'],
            'subject_type' => $row['subject_type'],
            'user_id' => $row['user_id'] === null ? null : (int) $row['user_id'],
        ];

        return hash('sha256', $prevHash.'|'.json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Periksa seluruh rantai. Mengembalikan baris pertama yang rusak, bila ada.
     *
     * @return array{ok: bool, checked: int, broken_id: ?int, reason: ?string}
     */
    public function verify(): array
    {
        $prev = self::GENESIS;
        $checked = 0;

        foreach (DB::table('audit_logs')->orderBy('id')->lazyById(500) as $log) {
            $row = [
                'user_id' => $log->user_id,
                'action' => $log->action,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'data' => $log->data === null ? [] : (array) json_decode($log->data, true),
                'created_at' => Carbon::parse($log->created_at)->format('Y-m-d H:i:s.u'),
            ];

            if ($log->prev_hash !== $prev) {
                return ['ok' => false, 'checked' => $checked, 'broken_id' => $log->id, 'reason' => 'prev_hash tidak sama dengan hash baris sebelumnya'];
            }
            if (! hash_equals($log->hash, self::hashRow($prev, $row))) {
                return ['ok' => false, 'checked' => $checked, 'broken_id' => $log->id, 'reason' => 'isi baris tidak cocok dengan hash-nya'];
            }

            $prev = $log->hash;
            $checked++;
        }

        return ['ok' => true, 'checked' => $checked, 'broken_id' => null, 'reason' => null];
    }

    /** Urutkan kunci secara rekursif agar JSON stabil walau MySQL mengubah urutan kunci. */
    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }
        ksort($value);

        return array_map(self::canonicalize(...), $value);
    }

    private function withLock(callable $callback): mixed
    {
        if (DB::getDriverName() !== 'mysql') {
            return DB::transaction($callback);
        }

        $got = DB::selectOne('SELECT GET_LOCK(?, 10) AS l', [self::LOCK])->l ?? 0;
        if ((int) $got !== 1) {
            throw new RuntimeException('Gagal mengunci rantai audit log.');
        }

        try {
            return DB::transaction($callback);
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS l', [self::LOCK]);
        }
    }
}
