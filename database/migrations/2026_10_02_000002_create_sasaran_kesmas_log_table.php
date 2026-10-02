<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak setiap perubahan anak.sasaran_balita_kesmas (spec 2026-10-02 §4): dari form Tambah/Edit
 * Anak, perintah kesmas:tandai-sasaran, atau pembatalannya. Menjawab "kenapa anak ini (tidak)
 * dihitung di dasbor Kesmas?" dan menjadi dasar --batalkan. Tanpa FK: baris audit tetap ada walau
 * anaknya dihapus (sejalan dengan epid_renumber_log).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sasaran_kesmas_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_anak')->index();
            $table->tinyInteger('nilai_lama')->nullable();
            $table->tinyInteger('nilai_baru')->nullable();
            $table->enum('sumber', ['form_tambah', 'form_edit', 'perintah', 'batal']);
            $table->char('batch', 36)->nullable()->index();
            $table->string('alasan', 255)->nullable();
            $table->unsignedBigInteger('id_user')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sasaran_kesmas_log');
    }
};
