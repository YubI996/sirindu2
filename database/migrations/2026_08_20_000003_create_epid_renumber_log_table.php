<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak penggeseran nomor EPID.
 *
 * Menghapus kasus di tengah deret kini merapatkan nomor di atasnya, sehingga
 * nomor resmi kasus LAIN ikut berubah tanpa disentuh petugasnya. Tabel ini
 * satu-satunya cara menjawab "kenapa nomor kasus saya berubah?" — termasuk
 * bila hasil lab terlanjur dikirim memakai nomor lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epid_renumber_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_surveillance_case')->nullable();
            $table->string('no_lama', 50);
            $table->string('no_baru', 50);
            $table->string('dipicu_hapus', 50)->comment('no_registrasi kasus yang dihapus');
            $table->unsignedBigInteger('id_user')->nullable()->comment('yang menghapus');
            $table->timestamp('created_at')->nullable();

            $table->index('id_surveillance_case', 'idx_renumber_case');
            $table->index('no_lama', 'idx_renumber_no_lama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epid_renumber_log');
    }
};
