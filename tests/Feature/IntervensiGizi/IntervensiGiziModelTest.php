<?php

namespace Tests\Feature\IntervensiGizi;

use App\Models\Anak;
use App\Models\IntervensiGizi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntervensiGiziModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_menyimpan_intervensi_dan_membaca_relasi_anak(): void
    {
        $anak = Anak::create([
            'nama' => 'Budi', 'nik' => '3201000000009201', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);

        $iv = IntervensiGizi::create([
            'id_anak' => $anak->id,
            'jenis' => 'PMT',
            'tanggal' => '2026-07-01',
            'pelaksana' => 'Dinkes',
            'status' => 'Selesai',
            'catatan' => 'PMT 30 hari',
            'created_by' => 1,
        ]);

        $this->assertDatabaseHas('intervensi_gizi', ['id_anak' => $anak->id, 'jenis' => 'PMT', 'status' => 'Selesai']);
        $this->assertSame('Budi', $iv->fresh()->anak->nama);
        $this->assertSame('2026-07-01', $iv->fresh()->tanggal->format('Y-m-d'));
    }
}
