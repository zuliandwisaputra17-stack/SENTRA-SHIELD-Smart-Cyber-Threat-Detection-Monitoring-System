<?php

namespace App\Reports;

use App\Models\Detection;
use App\Models\Incident;
use App\Response\ActionService;

/**
 * LAPSIT dari template (FR-12, cadangan bila Ollama tidak tersedia).
 * Setiap kalimat dibentuk dari data insiden; kalimat temuan mengutip event
 * buktinya [EV-n].
 */
final class TemplateWriter
{
    /** @return array{situasi: string, temuan: list<string>, penilaian: string, saran: list<string>} */
    public function write(Incident $incident, IncidentFacts $facts): array
    {
        $d = $facts->data;
        $i = $d['insiden'];

        $situasi = $i['agent_sensor_buta']
            ? sprintf('Agent %s di %s berhenti mengirim heartbeat sehingga satuan kehilangan pantauan (Sensor Buta). Insiden #%d berstatus %s dengan severity %s (skor %d).',
                $i['agent_sensor_buta'], $i['satuan'], $i['id'], $i['status'], $i['severity'], $i['skor'])
            : sprintf('%s menyerang %s antara %s dan %s. Insiden #%d berstatus %s dengan severity %s (skor %d), tahap tertinggi %s.',
                $i['ip_sumber'] ? 'IP '.$i['ip_sumber'] : 'Pelaku yang belum teridentifikasi', $i['satuan'],
                $i['pertama_terlihat'], $i['terakhir_terlihat'], $i['id'], $i['status'], $i['severity'], $i['skor'], $i['tahap_tertinggi'] ?? '-');
        if ($d['kampanye']) {
            $situasi .= sprintf(' IP ini termasuk Kampanye #%d yang menyerang %d satuan: %s.',
                $d['kampanye']['id'], count($d['kampanye']['satuan_terdampak']), implode(', ', $d['kampanye']['satuan_terdampak']));
        }

        $events = collect($d['event'])->keyBy('id');
        $temuan = [];
        foreach ($d['deteksi'] as $det) {
            $first = $det['event_bukti'][0] ?? null;
            $ev = $first ? $events[$first] : null;
            $sentence = $ev
                ? sprintf('%s: %s (%s, tahap %s) terdeteksi%s di %s: %s',
                    self::time($ev['waktu']), $det['aturan'], $det['teknik_attack'], $det['tahap'], $ev['ip'] ? ' dari '.$ev['ip'] : '', $ev['host'] ?? '-', $ev['ringkasan'])
                : sprintf('%s: %s (%s, tahap %s) terdeteksi Core dari heartbeat agent yang hilang',
                    self::time($det['pertama']), $det['aturan'], $det['teknik_attack'], $det['tahap']);
            if ($det['jumlah_deteksi'] > 1) {
                $sentence .= sprintf('; total %d deteksi', $det['jumlah_deteksi']);
            }
            if ($det['atribusi'] === 'waktu') {
                $sentence .= ' (atribusi berdasarkan waktu)';
            }
            $cite = collect($det['event_bukti'])->take(3)->map(fn ($id) => '['.$id.']')->implode('');
            $temuan[] = $sentence.'.'.($cite !== '' ? ' '.$cite : '');
        }

        $penilaian = sprintf('Serangan mencapai %d tahap kill chain (tertinggi: %s) dengan %d aturan deteksi terpicu. Skor risiko %s, severity %s. Tingkat Siaga %s saat ini %s.',
            count(collect($d['deteksi'])->pluck('tahap')->unique()), $i['tahap_tertinggi'] ?? '-', count($d['deteksi']),
            $d['rincian_skor']['rumus'] ?? $i['skor'], $i['severity'], $i['satuan'], $i['tingkat_siaga_satuan'] ?? '-');

        return [
            'situasi' => $situasi,
            'temuan' => $temuan,
            'penilaian' => $penilaian,
            'saran' => $this->advice($incident, $facts),
        ];
    }

    /** @return list<string> */
    private function advice(Incident $incident, IncidentFacts $facts): array
    {
        $codes = collect($facts->data['deteksi'])->map(fn ($d) => strtok($d['aturan'], ' '))->all();
        $payloads = Detection::query()->where('incident_id', $incident->id)->with(['rule', 'event'])->get()
            ->filter(fn ($det) => $det->event !== null)
            ->groupBy(fn ($det) => $det->rule->code)
            ->map(fn ($group) => $group->pluck('event.payload'));
        $saran = [];

        $ip = $incident->src_ip;
        if ($ip !== null) {
            $active = ActionService::activeBlocks()->where('params->ip', $ip)
                ->whereHas('targetAgent', fn ($q) => $q->where('unit_id', $incident->unit_id))->first();
            $saran[] = $active
                ? sprintf('Pertahankan blokir IP %s (berlaku sampai %s) dan pantau upaya dari IP lain.', $ip, IncidentFacts::wib($active->expires_at))
                : sprintf('Usulkan blokir IP %s di firewall host agent %s melalui SENTRA SHIELD; blokir berjalan setelah disetujui Komandan.', $ip, $facts->data['insiden']['satuan']);
        }
        if (array_intersect($codes, ['R07', 'R08'])) {
            $users = collect($payloads['R08'] ?? $payloads['R07'] ?? [])->pluck('user')->filter()->unique()->take(5)->implode(', ');
            $saran[] = in_array('R08', $codes, true)
                ? sprintf('Login SSH berhasil setelah brute force: segera ganti kata sandi akun %s, periksa sesi aktif, dan tinjau aktivitas akun tersebut.', $users ?: 'yang dipakai')
                : sprintf('Periksa kekuatan kata sandi akun SSH yang dicoba (%s) dan batasi login SSH hanya dari jaringan pengelola.', $users ?: '-');
        }
        if (in_array('R09', $codes, true)) {
            $paths = collect($payloads['R09'] ?? [])->pluck('path')->filter()->unique()->take(3)->implode(', ');
            $saran[] = sprintf('Amankan salinan lalu hapus file webshell %s; tutup celah unggah file pada aplikasi web.', $paths ?: '');
        }
        if (in_array('R10', $codes, true)) {
            $saran[] = 'Bandingkan halaman utama dengan salinan resmi dan pulihkan bila isinya berubah.';
        }
        if (array_intersect($codes, ['R02', 'R03', 'R04', 'R05', 'R06'])) {
            $saran[] = 'Perbarui aplikasi web dan perketat validasi input pada parameter yang diserang (lihat event bukti).';
        }
        if (in_array('R11', $codes, true)) {
            $addresses = collect($payloads['R11'] ?? [])->pluck('address')->filter(fn ($a) => $a !== null)->unique()->take(5)->implode(', ');
            $saran[] = sprintf('Periksa register PLC %s dan pastikan nilainya sesuai prosedur operasi; batasi akses Modbus hanya dari HMI resmi.', $addresses !== '' ? $addresses : 'yang ditulis');
        }
        if (in_array('R12', $codes, true)) {
            $saran[] = sprintf('Periksa host %s dan pastikan layanan agent berjalan kembali; selidiki siapa yang mematikannya.', $facts->data['insiden']['agent_sensor_buta'] ?? 'agent');
        }
        if ($facts->data['kampanye']) {
            $saran[] = sprintf('Koordinasikan dengan satuan lain yang terdampak Kampanye #%d (%s).', $facts->data['kampanye']['id'], implode(', ', $facts->data['kampanye']['satuan_terdampak']));
        }
        $saran[] = 'Lanjutkan pemantauan dan perbarui LAPSIT bila ada perkembangan.';

        return $saran;
    }

    /** "06/11/2026 20:40:38 WIB" -> "20:40:38 WIB" */
    private static function time(?string $wib): string
    {
        return $wib ? substr($wib, 11) : '-';
    }
}
