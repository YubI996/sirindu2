<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Models\AnakTautan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SkemaTautanTest extends TestCase
{
    use RefreshDatabase;

    private function anak(string $nik): Anak
    {
        return Anak::create(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil']);
    }

    public function test_tabel_dan_kolom_ada(): void
    {
        $this->assertTrue(Schema::hasColumns('anak_kandidat', ['id_anak_a', 'id_anak_b', 'skor', 'via', 'child_sim', 'parent_sim', 'dipindai_at']));
        $this->assertTrue(Schema::hasColumns('anak_tautan', ['id_anak_a', 'id_anak_b', 'keputusan', 'skor', 'via', 'status',
            'diusulkan_oleh', 'diusulkan_at', 'ditinjau_oleh', 'ditinjau_at', 'catatan', 'catatan_reviu']));
    }

    public function test_urut_pasangan_dan_unik(): void
    {
        $this->assertSame([3, 9], AnakTautan::urut(9, 3));
        $a = $this->anak('3201000000011001');
        $b = $this->anak('3201000000011002');
        $u = User::factory()->create(['type' => 0]);

        AnakTautan::create(['id_anak_a' => $a->id, 'id_anak_b' => $b->id, 'keputusan' => 'sama',
            'diusulkan_oleh' => $u->id, 'diusulkan_at' => now()]);
        $this->assertSame('diusulkan', AnakTautan::first()->status);

        $this->expectException(QueryException::class);
        AnakTautan::create(['id_anak_a' => $a->id, 'id_anak_b' => $b->id, 'keputusan' => 'beda',
            'diusulkan_oleh' => $u->id, 'diusulkan_at' => now()]);
    }

    public function test_kandidat_ikut_terhapus_bila_anak_dihapus(): void
    {
        $a = $this->anak('3201000000011003');
        $b = $this->anak('3201000000011004');
        AnakKandidat::create(['id_anak_a' => $a->id, 'id_anak_b' => $b->id, 'skor' => 190, 'via' => 'ortu',
            'child_sim' => 100, 'parent_sim' => 90, 'dipindai_at' => now()]);

        $a->delete();

        $this->assertSame(0, AnakKandidat::count());
    }
}
