<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jenis_kasus_epidemiologi') && !Schema::hasColumn('jenis_kasus_epidemiologi', 'keterangan')) {
            Schema::table('jenis_kasus_epidemiologi', function (Blueprint $table) {
                $table->text('keterangan')->nullable()->after('kategori');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('jenis_kasus_epidemiologi') && Schema::hasColumn('jenis_kasus_epidemiologi', 'keterangan')) {
            Schema::table('jenis_kasus_epidemiologi', function (Blueprint $table) {
                $table->dropColumn('keterangan');
            });
        }
    }
};
