<?php

namespace Tests\Feature\Pj;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenameMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_dan_judul_memakai_nama_operasi_timbang(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super)->get(route('admin.home'))->assertOk()
            ->assertSee('Operasi Timbang')->assertDontSee('Gizi &amp; Timbang', false);
        $this->actingAs($super)->get(route('admin.timbang.dashboard'))->assertOk()
            ->assertSee('Dashboard Operasi Timbang')->assertDontSee('Gizi &amp; Timbang', false);

        $faskes = User::factory()->create(['type' => 1, 'role' => 'imunisasi_faskes', 'id_kel' => 1]);
        $this->actingAs($faskes)->get(route('admin.home'))->assertOk()->assertDontSee('Gizi &amp; Timbang', false);
    }
}
