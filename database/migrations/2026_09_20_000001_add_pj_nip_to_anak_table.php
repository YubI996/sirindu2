<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            // Identifier disimpan sebagai teks agar digit NIP tetap utuh.
            $table->string('pj_nip', 18)->nullable()->after('pj_nama');
        });
    }

    public function down(): void
    {
        Schema::table('anak', fn (Blueprint $table) => $table->dropColumn('pj_nip'));
    }
};
