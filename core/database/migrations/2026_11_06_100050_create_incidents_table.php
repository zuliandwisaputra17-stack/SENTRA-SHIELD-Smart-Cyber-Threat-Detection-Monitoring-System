<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inti Attack Story. agent_id terisi untuk insiden Sensor Buta (R12) yang
 * dibentuk per agent karena tidak punya IP penyerang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->restrictOnDelete();
            $table->string('src_ip', 45)->nullable();
            $table->string('title');
            $table->string('stage_max', 32)->nullable();
            $table->unsignedInteger('score')->default(0);
            $table->json('score_breakdown')->nullable();
            $table->string('severity', 16)->default('rendah');
            $table->string('status', 16)->default('baru');
            $table->dateTime('first_seen_at', 3);
            $table->dateTime('last_seen_at', 3);
            $table->timestamps();

            $table->index(['unit_id', 'src_ip', 'status', 'last_seen_at']);
            $table->index(['src_ip', 'last_seen_at']);
            $table->index(['status', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
