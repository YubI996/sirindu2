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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('id_kec')->nullable()->change();
            $table->integer('id_kel')->nullable()->change();
            $table->integer('id_puskesmas')->nullable()->change();
            $table->integer('id_posyandu')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('id_kec')->nullable(false)->default(0)->change();
            $table->integer('id_kel')->nullable(false)->default(0)->change();
            $table->integer('id_puskesmas')->nullable(false)->default(0)->change();
            $table->integer('id_posyandu')->nullable(false)->default(0)->change();
        });
    }
};
