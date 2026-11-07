<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu event bisa memicu beberapa aturan. event_id kosong untuk deteksi tanpa
 * event (R12 heartbeat hilang). attribution menandai cara deteksi dikaitkan ke
 * insiden (keputusan A2: label "atribusi berdasarkan waktu" untuk FIM).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('detection_rules')->restrictOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->restrictOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained('incidents')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->restrictOnDelete();
            $table->string('src_ip', 45)->nullable();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('attack_id', 32);
            $table->unsignedSmallInteger('weight');
            $table->string('attribution', 8)->default('ip');
            $table->dateTime('detected_at', 3);

            $table->index(['rule_id', 'unit_id', 'src_ip', 'detected_at']);
            $table->index('detected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detections');
    }
};
