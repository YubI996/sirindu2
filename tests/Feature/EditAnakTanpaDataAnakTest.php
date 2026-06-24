<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anak hasil import sering dibuat tanpa baris DataAnak (data kelahiran).
 * Halaman edit & proses update tidak boleh error untuk anak seperti ini.
 */
class EditAnakTanpaDataAnakTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['type' => 1]);
    }

    private function buatAnakTanpaDataAnak(): Anak
    {
        return Anak::create([
            'nama' => 'Anak Import Tanpa Timbang',
            'nik' => '6402990909090001',
            'jk' => 1,
            'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-15',
            'status' => 1,
        ]);
    }

    public function test_edit_anak_tanpa_data_anak_tidak_error(): void
    {
        $anak = $this->buatAnakTanpaDataAnak();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.editAnak', $anak->hashid));

        $response->assertStatus(200);
    }
}
