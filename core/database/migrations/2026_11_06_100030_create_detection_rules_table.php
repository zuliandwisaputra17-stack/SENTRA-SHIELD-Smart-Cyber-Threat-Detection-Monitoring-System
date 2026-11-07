<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog aturan (PRD bagian 10). attack_name dan stage menyimpan kolom
 * "MITRE ATT&CK" dan "Tahap" dari katalog tersebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detection_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('name');
            $table->string('source', 32);
            $table->string('type', 16);
            $table->json('config');
            $table->string('attack_id', 32);
            $table->string('attack_name');
            $table->string('stage', 32);
            $table->unsignedSmallInteger('weight');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detection_rules');
    }
};
