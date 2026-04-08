<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            // New columns from kohort Excel (all nullable)
            $table->string('nik_ayah', 16)->nullable()->after('nik_ortu');
            $table->string('nik_ibu', 16)->nullable()->after('nik_ayah');
            $table->date('tgl_lahir_ibu')->nullable()->after('nik_ibu');
            $table->string('no_hp', 20)->nullable()->after('tgl_lahir_ibu');
            $table->text('alamat')->nullable()->after('no_hp');
            $table->decimal('bbl', 6, 1)->nullable()->after('alamat');
            $table->decimal('pbl', 5, 1)->nullable()->after('bbl');
            $table->decimal('lk_lahir', 4, 1)->nullable()->after('pbl');
            $table->boolean('imd')->nullable()->after('lk_lahir');
            $table->tinyInteger('usia_kehamilan_lahir')->nullable()->after('imd');
            $table->string('penolong_lahir', 100)->nullable()->after('usia_kehamilan_lahir');
            $table->string('komplikasi_persalinan', 255)->nullable()->after('penolong_lahir');

            // Make existing NOT NULL columns nullable
            $table->string('no_kk')->nullable()->change();
            $table->string('nik_ortu')->nullable()->change();
            $table->string('tempat_lahir')->nullable()->change();
            $table->string('golda')->nullable()->change();
            $table->integer('anak')->nullable()->change();
            $table->unsignedBigInteger('id_posyandu')->nullable()->change();
            $table->unsignedBigInteger('id_puskesmas')->nullable()->change();
            $table->text('catatan')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->dropColumn([
                'nik_ayah', 'nik_ibu', 'tgl_lahir_ibu', 'no_hp', 'alamat',
                'bbl', 'pbl', 'lk_lahir', 'imd', 'usia_kehamilan_lahir',
                'penolong_lahir', 'komplikasi_persalinan',
            ]);

            $table->string('no_kk')->nullable(false)->change();
            $table->string('nik_ortu')->nullable(false)->change();
            $table->string('tempat_lahir')->nullable(false)->change();
            $table->string('golda')->nullable(false)->change();
            $table->integer('anak')->nullable(false)->change();
            $table->unsignedBigInteger('id_posyandu')->nullable(false)->change();
            $table->unsignedBigInteger('id_puskesmas')->nullable(false)->change();
            $table->text('catatan')->nullable(false)->change();
        });
    }
};
