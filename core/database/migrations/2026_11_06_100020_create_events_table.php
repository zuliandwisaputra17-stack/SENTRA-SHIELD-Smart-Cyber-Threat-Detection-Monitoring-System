<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel terbesar. Append-only: tidak ada updated_at dan tidak bisa diubah dari UI.
 * agent_id kosong untuk event yang dibuat Core sendiri (Penjaga Situs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('source', 16);
            $table->string('src_ip', 45)->nullable();
            $table->unsignedSmallInteger('dst_port')->nullable();
            $table->json('payload');
            $table->text('raw')->nullable();
            $table->boolean('is_test')->default(false);
            $table->dateTime('occurred_at', 3);
            $table->dateTime('created_at', 3);

            $table->index(['unit_id', 'src_ip', 'occurred_at']);
            $table->index(['agent_id', 'source', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
