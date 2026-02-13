<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('jenis_kasus_epidemiologi', function (Blueprint $table) {
            $table->id();
            $table->string('kode_penyakit', 20)->unique();
            $table->string('nama_penyakit', 255);
            $table->enum('kategori', ['PD3I', 'menular_langsung', 'vector_borne', 'zoonosis', 'lainnya']);
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('kategori', 'idx_kategori');
            $table->index('is_active', 'idx_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('jenis_kasus_epidemiologi');
    }
};
