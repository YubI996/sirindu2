<?php

namespace Tests\Feature\Spm;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menguji ITEM SIDEBAR, bukan sekadar keberadaan URL di halaman — form filter
 * dan empty state dasbor juga memuat URL yang sama, jadi assertSee(route(...))
 * saja akan lulus walau menunya tidak pernah ditambahkan.
 */
class MenuSpmTest extends TestCase
{
    use RefreshDatabase;

    private function itemSidebar(string $url): string
    {
        return '/<li><a href="' . preg_quote($url, '/') . '"[^>]*>\s*SPM\s*<\/a><\/li>/';
    }

    private function halaman(User $user): string
    {
        return $this->actingAs($user)
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200)
            ->getContent();
    }

    public function test_superadmin_melihat_menu_dasbor_dan_master_data(): void
    {
        $html = $this->halaman(User::factory()->create(['type' => 0]));

        $this->assertMatchesRegularExpression($this->itemSidebar(route('admin.spm.dashboard')), $html);
        $this->assertMatchesRegularExpression($this->itemSidebar(route('admin.masterdata.spm.index')), $html);
    }

    public function test_admin_biasa_melihat_menu_dasbor_tanpa_master_data(): void
    {
        $html = $this->halaman(User::factory()->create(['type' => 1]));

        $this->assertMatchesRegularExpression($this->itemSidebar(route('admin.spm.dashboard')), $html);
        $this->assertDoesNotMatchRegularExpression($this->itemSidebar(route('admin.masterdata.spm.index')), $html);
    }

    public function test_faskes_surveilans_melihat_menu_dasbor(): void
    {
        $faskes = User::factory()->create(['type' => 1, 'role' => 'surveilans_puskesmas']);

        $this->assertMatchesRegularExpression(
            $this->itemSidebar(route('admin.spm.dashboard')),
            $this->halaman($faskes),
        );
    }

    public function test_item_sidebar_spm_aktif_saat_dasbornya_dibuka(): void
    {
        $html = $this->halaman(User::factory()->create(['type' => 0]));

        $this->assertMatchesRegularExpression(
            '/<a href="' . preg_quote(route('admin.spm.dashboard'), '/') . '" class="active">/',
            $html,
            'item SPM harus bertanda active saat rutenya sedang dibuka',
        );
    }
}
