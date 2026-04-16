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
        Schema::create('surveillance_case_kontak_erat', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_surveillance_case');
            $table->tinyInteger('urutan')->unsigned()->default(1);
            $table->string('nama', 255);
            $table->string('hubungan', 100)->nullable();
            $table->string('no_telepon', 20)->nullable();
            $table->text('alamat')->nullable();
            $table->date('tanggal_kontak_terakhir')->nullable();
            $table->boolean('ada_gejala')->default(false);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('id_surveillance_case')
                  ->references('id')->on('surveillance_cases')
                  ->onDelete('cascade');

            $table->index('id_surveillance_case', 'idx_sc_kontak');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surveillance_case_kontak_erat');
    }
};
