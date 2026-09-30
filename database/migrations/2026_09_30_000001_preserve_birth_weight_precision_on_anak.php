<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            // Tambah skala tanpa mengurangi kapasitas lima digit sebelum desimal.
            $table->decimal('bbl', 7, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->decimal('bbl', 6, 1)->nullable()->change();
        });
    }
};
