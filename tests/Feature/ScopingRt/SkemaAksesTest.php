<?php

namespace Tests\Feature\ScopingRt;

use App\Models\Rt;
use App\Models\RtAksesTautan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SkemaAksesTest extends TestCase
{
    use RefreshDatabase;

    public function test_kolom_dan_tabel_ada(): void
    {
        $this->assertTrue(Schema::hasColumn('verifikasi_anak', 'pelaksana'));
        $this->assertTrue(Schema::hasColumn('anak_tautan', 'pelaksana'));
        $this->assertTrue(Schema::hasColumns('rt_akses_tautan', [
            'id_rt', 'token_hash', 'kedaluwarsa_at', 'dibuat_oleh', 'dicabut_at', 'terakhir_dipakai_at', 'jumlah_pakai',
        ]));

        // diusulkan_oleh boleh NULL — mode tautan tanpa akun
        foreach (['verifikasi_anak', 'anak_tautan'] as $tabel) {
            $n = DB::selectOne("SELECT IS_NULLABLE AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'diusulkan_oleh'", [$tabel])->n;
            $this->assertSame('YES', $n, "$tabel.diusulkan_oleh harus nullable");
        }
    }

    public function test_buat_cari_dan_cabut_tautan(): void
    {
        $rt = Rt::factory()->create();
        $u  = User::factory()->create(['type' => 0]);

        $pertama = RtAksesTautan::buat($rt, $u);
        $kedua   = RtAksesTautan::buat($rt, $u, 7);

        $this->assertSame(40, strlen($kedua['token']));
        $this->assertNull(RtAksesTautan::cariToken($pertama['token']), 'tautan lama dicabut saat yang baru dibuat');
        $this->assertSame($rt->id, RtAksesTautan::cariToken($kedua['token'])->id_rt);
        $this->assertNull(RtAksesTautan::cariToken('token-ngawur'));
        $this->assertSame(1, RtAksesTautan::aktif()->count());
        $this->assertNotSame($kedua['token'], $kedua['model']->token_hash, 'DB hanya menyimpan hash');

        $kedua['model']->catatPakai();
        $this->assertSame(1, (int) $kedua['model']->fresh()->jumlah_pakai);
        $this->assertNotNull($kedua['model']->fresh()->terakhir_dipakai_at);

        $this->travel(8)->days();
        $this->assertNull(RtAksesTautan::cariToken($kedua['token']), 'kedaluwarsa');
    }
}
