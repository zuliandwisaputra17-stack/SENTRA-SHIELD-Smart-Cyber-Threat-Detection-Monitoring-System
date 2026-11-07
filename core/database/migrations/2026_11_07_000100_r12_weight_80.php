<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Keputusan pemilik proyek (Fase 2): bobot R12 Sensor Buta 80 agar insiden
 * Sensor Buta selalu Tinggi sesuai FR-09 (bobot 40 hanya menghasilkan Sedang).
 * Hanya mengubah nilai bawaan lama; bobot yang sudah diubah Admin tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('detection_rules')->where('code', 'R12')->where('weight', 40)->update(['weight' => 80]);
    }

    public function down(): void
    {
        DB::table('detection_rules')->where('code', 'R12')->where('weight', 80)->update(['weight' => 40]);
    }
};
