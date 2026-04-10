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
        Schema::create('surveillance_case_faskes_berobat', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_surveillance_case');
            $table->tinyInteger('urutan')->unsigned()->default(1);
            $table->enum('jenis_faskes', ['rs', 'puskesmas', 'klinik', 'pengobatan_tradisional', 'lainnya']);
            $table->string('nama_faskes', 255);
            $table->date('tanggal_berobat')->nullable();
            $table->enum('jenis_perawatan', ['inap', 'jalan'])->nullable();
            $table->date('tanggal_keluar')->nullable();
            $table->timestamps();

            $table->foreign('id_surveillance_case')
                  ->references('id')->on('surveillance_cases')
                  ->onDelete('cascade');

            $table->index('id_surveillance_case', 'idx_sc_fasbes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surveillance_case_faskes_berobat');
    }
};
