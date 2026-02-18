<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabel referensi jenis vaksin
        Schema::create('jenis_vaksin', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('kategori');
            $table->integer('usia_pemberian_min')->nullable();
            $table->integer('usia_pemberian_max')->nullable();
            $table->integer('interval_hari')->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        // Tabel imunisasi
        Schema::create('imunisasi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_anak');
            $table->bigInteger('id_jenis_vaksin')->unsigned();
            $table->integer('dosis')->default(1);
            $table->date('tanggal_pemberian')->nullable();
            $table->date('tanggal_selanjutnya')->nullable();
            $table->string('batch_number')->nullable();
            $table->string('lokasi_pemberian')->nullable();
            $table->integer('id_petugas')->nullable();
            $table->enum('status', ['belum', 'sudah', 'terlambat'])->default('belum');
            $table->text('reaksi_kipi')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('id_anak')->references('id')->on('anak')->onDelete('cascade');
            $table->foreign('id_jenis_vaksin')->references('id')->on('jenis_vaksin')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imunisasi');
        Schema::dropIfExists('jenis_vaksin');
    }
};
