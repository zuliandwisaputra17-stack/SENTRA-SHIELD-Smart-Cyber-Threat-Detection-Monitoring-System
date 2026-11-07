<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu host = satu agent. Token API disimpan ter-hash oleh Sanctum di
 * personal_access_tokens (keputusan A4, kolom token_hash tidak dipakai).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('hostname');
            $table->string('ip', 45)->nullable();
            $table->string('version', 32)->nullable();
            $table->string('asset_type', 16)->default('lainnya');
            $table->string('status', 16)->default('menunggu');
            $table->char('enrollment_code_hash', 64)->nullable()->unique();
            $table->dateTime('enrollment_expires_at')->nullable();
            $table->json('sources')->nullable();
            $table->dateTime('last_heartbeat_at', 3)->nullable();
            $table->timestamps();

            $table->index(['status', 'last_heartbeat_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
