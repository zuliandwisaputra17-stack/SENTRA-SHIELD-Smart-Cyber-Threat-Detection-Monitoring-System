<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alur persetujuan tindakan. Satu usulan bisa menjadi beberapa baris (satu per
 * agent) yang berbagi group_uuid, sehingga cukup satu persetujuan Komandan.
 * approved_by/approved_at mencatat siapa yang memutuskan (setuju atau tolak);
 * decision_note menyimpan alasan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('group_uuid')->nullable()->index();
            $table->foreignId('incident_id')->nullable()->constrained('incidents')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->string('type', 16);
            $table->foreignId('target_agent_id')->constrained('agents')->restrictOnDelete();
            $table->json('params');
            $table->string('status', 16)->default('diusulkan');
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->dateTime('executed_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->index(['target_agent_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actions');
    }
};
