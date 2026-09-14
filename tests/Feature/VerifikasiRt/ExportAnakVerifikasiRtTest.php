<?php

namespace Tests\Feature\VerifikasiRt;

use App\Exports\AnakExport;
use App\Models\Anak;
use App\Models\DataAnak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExportAnakVerifikasiRtTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_alldata_punya_kolom_verifikasi(): void
    {
        $this->assertTrue(Schema::hasColumns('alldata', ['sumber', 'sumber_gabungan', 'verifRtStatus', 'verifRtReviu']));
    }

    public function test_export_anak_memuat_kolom_sumber_dan_verifikasi(): void
    {
        $a = Anak::create(['nama' => 'Anak Export', 'nik' => '3201000000022001', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'operasi_timbang', 'sumber_gabungan' => ['operasi_timbang', 'capil']]);
        DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => '2026-01-10', 'bln' => 24, 'posisi' => 'berdiri', 'tb' => 80, 'bb' => 10,
            'lla' => 0, 'lk' => 0, 'id_user' => 1, 'zscore_bb_u' => 0, 'zscore_pb_u' => 0, 'zscore_bb_pb' => 0, 'sumber' => 'operasi_timbang']);
        DB::table('anak')->where('id', $a->id)->update(['verif_rt_status' => 'pindah', 'verif_rt_reviu' => 'disetujui']);

        $export = new AnakExport(new Request());
        $headings = $export->headings();
        $row = $export->map($export->query()->first());

        $this->assertSame(['Sumber Data', 'Verifikasi RT', 'Reviu Verifikasi'], array_slice($headings, -3));
        $this->assertSame(['operasi_timbang (+capil)', 'Pindah', 'disetujui'], array_slice($row, -3));
        $this->assertCount(count($headings), $row);
    }
}
