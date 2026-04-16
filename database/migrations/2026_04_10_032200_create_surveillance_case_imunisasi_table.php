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
        Schema::create('surveillance_case_imunisasi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_surveillance_case');
            $table->tinyInteger('imunisasi_ke')->unsigned(); // 1–5
            $table->string('nama_antigen', 150);
            $table->enum('diberikan', ['ya', 'tidak', 'tidak_tahu'])->default('tidak_tahu');
            $table->string('sumber_informasi', 100)->nullable();
            $table->date('tanggal_imunisasi')->nullable();
            $table->timestamps();

            $table->foreign('id_surveillance_case')
                  ->references('id')->on('surveillance_cases')
                  ->onDelete('cascade');

            $table->unique(['id_surveillance_case', 'imunisasi_ke'], 'uq_sc_imun');
            $table->index('id_surveillance_case', 'idx_sc_imun');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surveillance_case_imunisasi');
    }
};
