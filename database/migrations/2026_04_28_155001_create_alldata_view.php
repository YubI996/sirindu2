<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS `alldata`');

        DB::statement("
            CREATE VIEW `alldata` AS
            SELECT
                da.id,
                a.no_kk,
                a.nik,
                a.nama,
                a.nik_ortu,
                a.nama_ibu,
                a.nama_ayah,
                a.jk,
                a.tempat_lahir,
                a.tgl_lahir,
                a.golda,
                a.anak,
                a.catatan,
                a.id_kec        AS idKec,
                a.id_kel        AS idKel,
                a.id_puskesmas  AS idPuskes,
                a.id_posyandu   AS idPos,
                a.id_rt         AS idRt,
                kec.name        AS nameKec,
                kel.name        AS nameKel,
                pus.name        AS namePuskes,
                pos.name        AS namePos,
                rt.name         AS nameRt,
                da.tgl_kunjungan,
                da.bln,
                da.posisi,
                da.tb,
                da.bb,
                da.lla,
                da.lk,
                da.ntob,
                da.asi,
                da.vit_a,
                u.name          AS namaPetugas
            FROM data_anak da
            INNER JOIN anak a       ON a.id   = da.id_anak
            LEFT  JOIN kecamatan kec ON kec.id = a.id_kec
            LEFT  JOIN kelurahan kel ON kel.id = a.id_kel
            LEFT  JOIN puskesmas pus ON pus.id = a.id_puskesmas
            LEFT  JOIN posyandu  pos ON pos.id = a.id_posyandu
            LEFT  JOIN rt            ON rt.id  = a.id_rt
            LEFT  JOIN users u       ON u.id   = da.id_user
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS `alldata`');
    }
};
