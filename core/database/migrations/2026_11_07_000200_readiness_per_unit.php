<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uji Kesiapan per satuan: skenario dijalankan oleh agent yang punya sumber
 * telemetri yang sesuai (mis. R11 oleh agent sensor OT). Kolom pelengkap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('readiness_runs', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('started_by')->constrained('units')->restrictOnDelete();
            $table->foreignId('agent_id')->nullable()->change();
        });
        Schema::table('readiness_results', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('rule_id')->constrained('agents')->restrictOnDelete();
            $table->string('note')->nullable()->after('ttd_ms');
        });
    }

    public function down(): void
    {
        Schema::table('readiness_results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
            $table->dropColumn('note');
        });
        Schema::table('readiness_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
        });
    }
};
