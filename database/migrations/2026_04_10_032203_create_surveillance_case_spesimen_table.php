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
        Schema::create('surveillance_case_spesimen', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_surveillance_case');
            $table->tinyInteger('urutan')->unsigned()->default(1);
            $table->string('jenis_spesimen', 100);
            $table->date('tanggal_ambil_spesimen')->nullable();
            $table->date('tanggal_kirim_sampel')->nullable();
            $table->date('tanggal_terima_lab')->nullable();
            $table->string('status_pemeriksaan', 100)->nullable();
            $table->unsignedBigInteger('id_jenis_kasus_terkonfirmasi')->nullable();
            $table->string('nama_variant_genotype', 255)->nullable();
            $table->timestamps();

            $table->foreign('id_surveillance_case')
                  ->references('id')->on('surveillance_cases')
                  ->onDelete('cascade');

            $table->foreign('id_jenis_kasus_terkonfirmasi')
                  ->references('id')->on('jenis_kasus_epidemiologi')
                  ->onDelete('set null');

            $table->index('id_surveillance_case', 'idx_sc_spesimen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surveillance_case_spesimen');
    }
};
