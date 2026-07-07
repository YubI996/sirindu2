<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prioritas_gizi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_anak')->unique();
            $table->unsignedBigInteger('id_kec')->nullable();
            $table->unsignedBigInteger('id_kel')->nullable();
            $table->unsignedBigInteger('id_rt')->nullable();
            $table->unsignedBigInteger('id_posyandu')->nullable();
            $table->boolean('gizi_buruk')->default(false);
            $table->boolean('gizi_kurang')->default(false);
            $table->boolean('stunting')->default(false);
            $table->boolean('bb_tidak_naik')->default(false);
            $table->unsignedTinyInteger('prioritas')->nullable();
            $table->integer('usia_bln')->nullable();
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();

            $table->index('id_rt');
            $table->index('id_kel');
            $table->index('prioritas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prioritas_gizi');
    }
};
