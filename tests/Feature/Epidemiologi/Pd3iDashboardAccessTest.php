<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Dasbor PD3I dibuka untuk admin/faskes (menggantikan submenu Peta Sebaran).
 * Data tetap city-wide untuk semua peran (keputusan klien).
 */
class Pd3iDashboardAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_faskes_user_can_open_pd3i_dashboard(): void
    {
        // User faskes surveilans: lolos is_admin, TAPI bukan superadmin.
        $user = User::factory()->create(['role' => 'surveilans_puskesmas', 'type' => 1]);

        $this->assertFalse($user->isSuperAdmin());

        $response = $this->actingAs($user)->get(route('admin.pd3i.dashboard'));

        $response->assertOk();
    }
}
