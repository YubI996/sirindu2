<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervensi_gizi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_anak')->index();
            $table->string('jenis');
            $table->date('tanggal')->nullable();
            $table->string('pelaksana')->nullable();
            $table->string('status')->default('Direncanakan');
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervensi_gizi');
    }
};
