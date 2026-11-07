<?php

namespace Database\Seeders;

use App\Enums\KillChainStage;
use App\Enums\RuleType;
use App\Models\DetectionRule;
use Illuminate\Database\Seeder;

/**
 * Katalog 13 aturan awal (PRD bagian 10). Ambang adalah nilai awal lab dan bisa
 * diubah Admin dari dashboard; seeder tidak menimpa aturan yang sudah ada.
 * Pola adalah regex PCRE tanpa delimiter, dievaluasi tanpa membedakan huruf besar/kecil.
 */
class DetectionRuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::catalog() as $rule) {
            DetectionRule::query()->firstOrCreate(['code' => $rule['code']], $rule);
        }
    }

    /** @return list<array<string, mixed>> */
    public static function catalog(): array
    {
        $t1190 = ['attack_id' => 'T1190', 'attack_name' => 'Exploit Public-Facing Application'];

        return [
            [
                'code' => 'R01', 'name' => 'Pemindaian port', 'source' => 'firewall',
                'type' => RuleType::Threshold, 'stage' => KillChainStage::Pengintaian, 'weight' => 20,
                'attack_id' => 'T1046', 'attack_name' => 'Network Service Discovery',
                'config' => ['window_s' => 60, 'threshold' => 15, 'distinct_field' => 'dst_port'],
            ],
            [
                'code' => 'R02', 'name' => 'Alat pemindai web', 'source' => 'web',
                'type' => RuleType::Pattern, 'stage' => KillChainStage::Pengintaian, 'weight' => 15,
                'attack_id' => 'T1595.002', 'attack_name' => 'Vulnerability Scanning',
                'config' => ['fields' => ['user_agent'], 'patterns' => ['nikto', 'sqlmap', 'nmap', 'gobuster', 'wpscan']],
            ],
            [
                'code' => 'R03', 'name' => 'Brute force direktori', 'source' => 'web',
                'type' => RuleType::Threshold, 'stage' => KillChainStage::Pengintaian, 'weight' => 15,
                'attack_id' => 'T1595.003', 'attack_name' => 'Wordlist Scanning',
                'config' => ['window_s' => 60, 'threshold' => 30, 'match' => ['status' => 404]],
            ],
            [
                'code' => 'R04', 'name' => 'SQL injection', 'source' => 'web',
                'type' => RuleType::Pattern, 'stage' => KillChainStage::AksesAwal, 'weight' => 35,
                ...$t1190,
                'config' => ['fields' => ['path', 'query'], 'url_decode' => true, 'patterns' => [
                    'union\s+(all\s+)?select', "'\\s*or\\s*'1'\\s*=\\s*'1", 'sleep\s*\(', '--',
                ]],
            ],
            [
                'code' => 'R05', 'name' => 'XSS', 'source' => 'web',
                'type' => RuleType::Pattern, 'stage' => KillChainStage::AksesAwal, 'weight' => 25,
                ...$t1190,
                'config' => ['fields' => ['path', 'query'], 'url_decode' => true, 'patterns' => [
                    '<script', 'onerror\s*=', 'javascript:',
                ]],
            ],
            [
                'code' => 'R06', 'name' => 'Path traversal / LFI', 'source' => 'web',
                'type' => RuleType::Pattern, 'stage' => KillChainStage::AksesAwal, 'weight' => 35,
                ...$t1190,
                'config' => ['fields' => ['path', 'query'], 'url_decode' => true, 'patterns' => [
                    '\.\./', '/etc/passwd', 'php://filter',
                ]],
            ],
            [
                'code' => 'R07', 'name' => 'Brute force SSH', 'source' => 'ssh',
                'type' => RuleType::Threshold, 'stage' => KillChainStage::AksesAwal, 'weight' => 30,
                'attack_id' => 'T1110.001', 'attack_name' => 'Password Guessing',
                'config' => ['window_s' => 60, 'threshold' => 10, 'match' => ['outcome' => 'failed']],
            ],
            [
                'code' => 'R08', 'name' => 'Login berhasil setelah brute force', 'source' => 'ssh',
                'type' => RuleType::Pattern, 'stage' => KillChainStage::AksesAwal, 'weight' => 50,
                'attack_id' => 'T1078', 'attack_name' => 'Valid Accounts',
                'config' => ['match' => ['outcome' => 'success'], 'requires_rule' => 'R07', 'within_s' => 1800],
            ],
            [
                'code' => 'R09', 'name' => 'Webshell', 'source' => 'fim',
                'type' => RuleType::Pattern, 'stage' => KillChainStage::Persistensi, 'weight' => 60,
                'attack_id' => 'T1505.003', 'attack_name' => 'Web Shell',
                'config' => [
                    'path_pattern' => '\.php$',
                    'changes' => ['created', 'modified'],
                    'content_patterns' => ['eval\s*\(', 'base64_decode\s*\(', 'system\s*\(\s*\$_'],
                ],
            ],
            [
                'code' => 'R10', 'name' => 'Defacement', 'source' => 'fim/site',
                'type' => RuleType::Pattern, 'stage' => KillChainStage::Dampak, 'weight' => 50,
                'attack_id' => 'T1491.001', 'attack_name' => 'Internal Defacement',
                'config' => [
                    'watch_paths' => ['/var/www/html/index.php', '/var/www/html/index.html'],
                    'changes' => ['modified', 'deleted'],
                    'respect_update_windows' => true,
                ],
            ],
            [
                'code' => 'R11', 'name' => 'Perintah tulis Modbus tanpa izin', 'source' => 'modbus',
                'type' => RuleType::Pattern, 'stage' => KillChainStage::Dampak, 'weight' => 60,
                'attack_id' => 'T0855', 'attack_name' => 'Unauthorized Command Message (ATT&CK for ICS)',
                'config' => ['function_codes' => [5, 6, 15, 16], 'hmi_allowlist' => []],
            ],
            [
                'code' => 'R12', 'name' => 'Sensor Buta', 'source' => 'heartbeat',
                'type' => RuleType::Threshold, 'stage' => KillChainStage::PertahananDilumpuhkan, 'weight' => 80,
                'attack_id' => 'T1562.001', 'attack_name' => 'Disable or Modify Tools',
                'config' => ['heartbeat_timeout_s' => 30, 'source_silence_s' => 300],
            ],
            [
                'code' => 'R13', 'name' => 'Injeksi judi online ber-cloaking', 'source' => 'site',
                'type' => RuleType::Pattern, 'stage' => KillChainStage::Dampak, 'weight' => 45,
                'attack_id' => 'T1491.002', 'attack_name' => 'External Defacement (pemetaan terdekat)',
                'config' => [
                    'similarity_threshold' => 0.8,
                    'keywords' => ['slot', 'gacor', 'togel', 'judi', 'maxwin', 'casino', 'scatter', 'toto', 'jackpot'],
                ],
            ],
        ];
    }
}
