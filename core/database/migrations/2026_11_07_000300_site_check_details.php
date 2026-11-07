<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom pelengkap Penjaga Situs (FR-13): halaman memperlihatkan "hasil
 * pemeriksaan per varian, perbandingan isi" sehingga cuplikan teks, status
 * HTTP, kata kunci yang ditemukan, dan galat per varian perlu disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_checks', function (Blueprint $table) {
            $table->unsignedSmallInteger('http_status')->nullable()->after('variant');
            $table->json('keywords')->nullable()->after('gambling_hits');
            $table->text('excerpt')->nullable()->after('flagged');
            $table->string('error')->nullable()->after('excerpt');
        });
    }

    public function down(): void
    {
        Schema::table('site_checks', function (Blueprint $table) {
            $table->dropColumn(['http_status', 'keywords', 'excerpt', 'error']);
        });
    }
};
