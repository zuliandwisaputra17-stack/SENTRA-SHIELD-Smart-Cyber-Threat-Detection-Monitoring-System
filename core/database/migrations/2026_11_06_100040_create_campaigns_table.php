<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('src_ip', 45);
            $table->unsignedSmallInteger('unit_count')->default(0);
            $table->dateTime('first_seen_at', 3);
            $table->dateTime('last_seen_at', 3);
            $table->string('status', 16)->default('aktif');
            $table->timestamps();

            $table->index(['src_ip', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
