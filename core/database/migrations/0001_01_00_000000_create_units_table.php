<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hierarki satuan (Kodam > Korem > Kodim). Dibuat sebelum users karena
 * users.unit_id merujuk ke tabel ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->string('level', 16);
            $table->string('alert_level', 16)->default('aman');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
