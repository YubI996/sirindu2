<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Membuat tabel rumah_sakits untuk 5 RS di Kota Bontang.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('rumah_sakits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_kecamatan')->nullable();
            $table->string('name');
            $table->string('kode_rs', 20)->nullable()->unique();
            $table->string('alamat')->nullable();
            $table->string('telepon', 20)->nullable();
            $table->enum('jenis_rs', ['RSUD', 'RS Swasta', 'RS TNI/Polri', 'Klinik Utama'])->default('RS Swasta');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('id_kecamatan')->references('id')->on('kecamatan')->nullOnDelete();
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('rumah_sakits');
    }
};
