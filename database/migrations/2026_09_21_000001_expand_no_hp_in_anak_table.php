<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->string('no_hp', 40)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->string('no_hp', 20)->nullable()->change();
        });
    }
};
