<?php

namespace App\Services;

use App\Enums\AgentStatus;
use App\Models\Agent;
use Illuminate\Support\Str;

/**
 * Kode pendaftaran sekali pakai dan pencabutan token agent (FR-01).
 * Kode hanya ditampilkan sekali; yang disimpan hanya hash SHA-256-nya.
 */
class AgentEnrollment
{
    public const CODE_TTL_HOURS = 24;

    public function __construct(private AuditLogger $audit) {}

    /** Buat kode baru untuk agent berstatus Menunggu. Mengembalikan kode asli. */
    public function issueCode(Agent $agent, ?string $code = null): string
    {
        $code ??= self::generateCode();

        $agent->forceFill([
            'status' => AgentStatus::Menunggu,
            'enrollment_code_hash' => hash('sha256', $code),
            'enrollment_expires_at' => now()->addHours(self::CODE_TTL_HOURS),
        ])->save();

        $this->audit->record('kode_pendaftaran_dibuat', $agent, ['berlaku_jam' => self::CODE_TTL_HOURS]);

        return $code;
    }

    /** Cabut token: agent langsung ditolak API. */
    public function revoke(Agent $agent): void
    {
        $agent->tokens()->delete();
        $agent->forceFill([
            'status' => AgentStatus::Dicabut,
            'enrollment_code_hash' => null,
            'enrollment_expires_at' => null,
        ])->save();

        $this->audit->record('agent_dicabut', $agent, ['hostname' => $agent->hostname]);
    }

    public static function generateCode(): string
    {
        $raw = strtoupper(Str::random(16));

        return 'SNTR-'.implode('-', str_split(preg_replace('/[^A-Z0-9]/', 'X', $raw), 4));
    }
}
