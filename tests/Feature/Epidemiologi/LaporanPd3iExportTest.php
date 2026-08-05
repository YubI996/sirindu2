<?php

namespace Tests\Feature\Epidemiologi;

use App\Exports\LaporanKasusIndividuExport;
use App\Models\JenisKasusEpidemiologi;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class LaporanPd3iExportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_halaman_laporan_tampil(): void
    {
        $user = User::factory()->create(['type' => 0]);

        $this->actingAs($user)->get(route('admin.export.pd3i.index'))->assertOk();
    }

    public function test_download_memicu_export_dengan_nama_file_benar(): void
    {
        Excel::fake();
        $user = User::factory()->create(['type' => 0]);
        $disease = JenisKasusEpidemiologi::factory()->create(['kode_penyakit' => 'AFP', 'nama_penyakit' => 'AFP']);

        $response = $this->actingAs($user)->get(route('admin.export.pd3i.download', [
            'jenis_kasus_id' => $disease->id,
            'tahun' => 2026,
        ]));

        $response->assertOk();
        Excel::assertDownloaded('list-individu-afp-2026.xlsx', function (LaporanKasusIndividuExport $export) {
            // Blok judul + baris heading kolom harus ada di headings().
            return in_array('Nomor EPID', $export->headings(), true)
                && in_array('No', $export->headings(), true);
        });
    }

    public function test_download_wajib_jenis_kasus_dan_tahun(): void
    {
        $user = User::factory()->create(['type' => 0]);

        $this->actingAs($user)->get(route('admin.export.pd3i.download'))
            ->assertSessionHasErrors(['jenis_kasus_id', 'tahun']);
    }
}
