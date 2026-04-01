<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lokasi_penularan_master', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 255);
            $table->enum('kategori', ['Sekolah', 'Tempat Kerja', 'Gym', 'Tempat Ibadah', 'Lainnya']);
            $table->boolean('is_custom')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lokasi_penularan_master');
    }
};
