<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penjaga Situs (FR-13). update_windows = jadwal pembaruan resmi (keputusan A4).
 * check_uuid mengelompokkan tiga varian dari satu kali pemeriksaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('url');
            $table->char('baseline_hash', 64)->nullable();
            $table->json('update_windows')->nullable();
            $table->timestamps();
        });

        Schema::create('site_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('monitored_sites')->cascadeOnDelete();
            $table->uuid('check_uuid')->index();
            $table->string('variant', 16);
            $table->char('hash', 64)->nullable();
            $table->decimal('similarity', 5, 4)->nullable();
            $table->unsignedSmallInteger('gambling_hits')->default(0);
            $table->boolean('flagged')->default(false);
            $table->dateTime('checked_at', 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_checks');
        Schema::dropIfExists('monitored_sites');
    }
};
