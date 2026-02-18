<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('jenis_kasus_epidemiologi', function (Blueprint $table) {
            $table->id();
            $table->string('kode_penyakit', 20)->unique();
            $table->string('nama_penyakit', 100);
            $table->enum('kategori', ['PD3I', 'menular_langsung', 'vector_borne', 'zoonosis', 'lainnya'])->default('lainnya');
            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jenis_kasus_epidemiologi');
    }
};
