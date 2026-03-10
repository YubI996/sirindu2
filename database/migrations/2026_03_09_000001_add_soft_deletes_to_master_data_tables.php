<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_vaksin', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('jenis_kasus_epidemiologi', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('jenis_vaksin', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('jenis_kasus_epidemiologi', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
