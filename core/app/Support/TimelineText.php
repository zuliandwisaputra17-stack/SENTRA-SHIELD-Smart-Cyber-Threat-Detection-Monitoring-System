<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Response\ActionService;
use Illuminate\Support\Carbon;

/**
 * Kalimat garis waktu insiden dari baris audit log (FR-09: agent yang kembali
 * hidup tercatat di garis waktu insiden; juga status, usulan, keputusan).
 */
final class TimelineText
{
    public static function describe(AuditLog $log): string
    {
        $d = $log->data ?? [];
        $who = $log->user?->name ?? 'Sistem';

        return match ($log->action) {
            'agent_buta' => sprintf('Agent %s dinyatakan BUTA (heartbeat terakhir %s).', $d['agent'] ?? '?',
                isset($d['heartbeat_terakhir']) ? waktu_wib(Carbon::parse($d['heartbeat_terakhir']), 'H:i:s') : '—'),
            'agent_kembali_hidup' => sprintf('Agent %s kembali hidup%s.', $d['agent'] ?? '?',
                isset($d['mati_selama_detik']) ? ' setelah '.$d['mati_selama_detik'].' detik tanpa heartbeat' : ''),
            'status_insiden_diubah' => sprintf('Status diubah dari %s ke %s oleh %s.', ucfirst($d['dari'] ?? '?'), ucfirst($d['ke'] ?? '?'), $who),
            'tindakan_diusulkan' => sprintf('%s mengusulkan blokir %s selama %s di %s.', $who, $d['ip'] ?? '?',
                ActionService::DURATIONS[$d['durasi_s'] ?? 0] ?? (($d['durasi_s'] ?? '?').' detik'), implode(', ', (array) ($d['agent'] ?? []))),
            'tindakan_disetujui' => sprintf('%s menyetujui blokir %s.', $who, $d['ip'] ?? '?'),
            'tindakan_ditolak' => sprintf('%s menolak blokir %s: "%s".', $who, $d['ip'] ?? '?', $d['alasan'] ?? ''),
            'usulan_dibatalkan' => sprintf('%s membatalkan usulan tindakan.', $who),
            'lapsit_dibuat' => sprintf('LAPSIT %s terbit (%s), disusun untuk %s.', $d['nomor'] ?? '', ($d['penyusun'] ?? '') === 'llm' ? 'LLM lokal '.($d['model'] ?? '') : 'template', $who),
            'baseline_situs_diperbarui' => sprintf('Baseline situs %s diperbarui (perubahan di dalam jadwal pembaruan).', $d['url'] ?? ''),
            'lapsit_diekspor' => sprintf('%s mengekspor LAPSIT %s ke PDF.', $who, $d['nomor'] ?? ''),
            default => str_replace('_', ' ', ucfirst($log->action)).' oleh '.$who.'.',
        };
    }
}
