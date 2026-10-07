<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tabel Data Anak (`admin.getAnak`, Yajra DataTables) harus meng-escape teks anak (audit Kesmas
 * 2026-10-06, SEC-002). `->escapeColumns([])` mematikan escape bawaan Yajra, dan kolom di
 * `admin/anak/index.blade.php` tak punya `render` — DataTables menyisipkan string sebagai HTML.
 * Nama/NIK/nama ibu dari form atau import (berkas Excel) lalu menjadi stored XSS di sesi siapa pun
 * yang membuka daftar. Hanya kolom `edit` (menu tombol) yang memang HTML.
 */
class DaftarAnakEscapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_teks_anak_di_json_datatables_di_escape_tetapi_menu_edit_tetap_html(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        Anak::create([
            'no_kk' => '6474010101010009', 'nik' => '6474010101230009',
            'nama' => '<img src=x onerror=alert(1)>', 'nama_ibu' => '<script>alert(2)</script>',
            'nik_ortu' => '6474010101900009', 'nama_ayah' => 'Ayah', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-05-05', 'status' => 1, 'sumber' => 'manual',
        ]);

        $baris = $this->actingAs($admin)->getJson(route('admin.getAnak'))->assertOk()->json('data.0');

        $this->assertStringNotContainsString('<img', $baris['nama']);
        $this->assertStringContainsString('&lt;img', $baris['nama']);
        $this->assertStringNotContainsString('<script', $baris['nama_ibu']);
        $this->assertStringContainsString('&lt;script', $baris['nama_ibu']);
        // kolom edit sengaja HTML (rawColumns) — jangan ikut ter-escape
        $this->assertStringContainsString('<div class="dropdown">', $baris['edit']);
    }
}
