<?php

namespace Tests\Feature\PrioritasGizi;

use App\Models\PrioritasGizi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrioritasGiziModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_dapat_menyimpan_dan_membaca_baris_snapshot(): void
    {
        $row = PrioritasGizi::create([
            'id_anak' => 1,
            'id_kel' => 5,
            'id_rt' => 10,
            'gizi_buruk' => true,
            'gizi_kurang' => false,
            'stunting' => true,
            'bb_tidak_naik' => false,
            'prioritas' => 1,
            'usia_bln' => 24,
            'refreshed_at' => now(),
        ]);

        $this->assertDatabaseHas('prioritas_gizi', ['id_anak' => 1, 'prioritas' => 1]);
        $this->assertTrue($row->fresh()->gizi_buruk);
        $this->assertSame(1, $row->fresh()->prioritas);
    }
}
