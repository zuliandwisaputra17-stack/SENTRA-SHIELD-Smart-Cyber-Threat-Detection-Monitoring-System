<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uji Kesiapan (FR-08). coverage = rasio skenario terdeteksi (0..1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readiness_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->restrictOnDelete();
            $table->string('status', 16)->default('berjalan');
            $table->decimal('coverage', 5, 4)->nullable();
            $table->unsignedInteger('avg_ttd_ms')->nullable();
            $table->dateTime('started_at', 3);
            $table->dateTime('finished_at', 3)->nullable();
            $table->timestamps();
        });

        Schema::create('readiness_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('readiness_runs')->cascadeOnDelete();
            $table->string('scenario', 32);
            $table->foreignId('rule_id')->constrained('detection_rules')->restrictOnDelete();
            $table->boolean('detected')->default(false);
            $table->unsignedInteger('ttd_ms')->nullable();
            $table->dateTime('started_at', 3)->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'scenario']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readiness_results');
        Schema::dropIfExists('readiness_runs');
    }
};
