<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill kolom data_anak.bln (usia dalam bulan) untuk semua kunjungan yang
 * tersimpan dengan nilai salah — paling sering 0 karena import (kohort /
 * pengukuran) mengambil 'bln' dari kolom spreadsheet yang kosong, atau import
 * timbang berjalan ketika tgl_lahir anak masih NULL lalu diisi belakangan.
 *
 * Usia dihitung ulang dari tgl_lahir → tgl_kunjungan via helper usia_bulan().
 * Baris tanpa tgl_lahir/tgl_kunjungan valid, atau tgl_kunjungan < tgl_lahir,
 * dibiarkan apa adanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('data_anak as d')
            ->join('anak as a', 'a.id', '=', 'd.id_anak')
            ->whereNotNull('a.tgl_lahir')
            ->whereNotNull('d.tgl_kunjungan')
            ->where('a.tgl_lahir', '!=', '0000-00-00')
            ->where('d.tgl_kunjungan', '!=', '0000-00-00')
            ->select('d.id', 'd.bln', 'd.tgl_kunjungan', 'a.tgl_lahir')
            ->orderBy('d.id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    $bln = usia_bulan($r->tgl_lahir, $r->tgl_kunjungan);
                    if ($bln !== null && (int) $r->bln !== $bln) {
                        DB::table('data_anak')->where('id', $r->id)->update(['bln' => $bln]);
                    }
                }
            }, 'd.id', 'id');
    }

    public function down(): void
    {
        // Koreksi data — nilai lama yang salah tidak perlu (dan tidak bisa) dipulihkan.
    }
};
