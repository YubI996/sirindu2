<?php

namespace Tests\Unit;

use App\Services\KesmasPresenter;
use Tests\TestCase;

class KesmasPresenterTest extends TestCase
{
    public function test_ya_tidak_membedakan_belum_diisi_dari_tidak(): void
    {
        $this->assertSame('', KesmasPresenter::yaTidak(null));
        $this->assertSame('—', KesmasPresenter::yaTidak('', '—'));
        $this->assertSame('Ya', KesmasPresenter::yaTidak(1));
        $this->assertSame('Ya', KesmasPresenter::yaTidak('1'));
        $this->assertSame('Tidak', KesmasPresenter::yaTidak(0));
        $this->assertSame('Tidak', KesmasPresenter::yaTidak('0'));
    }

    public function test_enum_label_dari_config(): void
    {
        $this->assertSame('Tidak normal', KesmasPresenter::enumLabel('skrining', 'tidak_normal'));
        $this->assertSame('Non reaktif', KesmasPresenter::enumLabel('hepatitis_b', 'non_reaktif'));
        $this->assertSame('', KesmasPresenter::enumLabel('skrining', null));
        $this->assertSame('—', KesmasPresenter::enumLabel('skrining', '', '—'));
        $this->assertSame('xyz', KesmasPresenter::enumLabel('skrining', 'xyz'));
    }

    public function test_teks_kosong_jadi_strip(): void
    {
        $this->assertSame('—', KesmasPresenter::teks(null));
        $this->assertSame('—', KesmasPresenter::teks('   '));
        $this->assertSame('Bidan', KesmasPresenter::teks('Bidan'));
    }

    public function test_layanan_kunjungan_hanya_yang_bernilai_satu(): void
    {
        $baris = (object) [
            'kn1' => 1, 'kn3' => 0, 'mtbm' => null, 'mtbs' => '1', 'pkat' => 0,
            'skrining_atresia_bilier' => 0, 'oralit_zinc' => 0, 'mbg' => 1, 'kelas_ibu_balita' => 0,
            'tgl_penanda_ckg' => '2026-03-05',
            'pemeriksaan_gigi' => 'Karies', 'rujukan' => null, 'mt_pangan_lokal' => '  ',
            'catatan_pengukuran' => null, 'pemeriksaan_lainnya' => null, 'pola_makan' => null,
            'pola_asuh' => null, 'intervensi' => 'PMT 30 hari',
        ];

        $r = KesmasPresenter::layananKunjungan($baris);

        $this->assertSame(['KN1', 'MTBS', 'MBG', 'CKG 05/03'], $r['badge']);
        $this->assertSame(['Gigi: Karies', 'Intervensi: PMT 30 hari'], $r['keterangan']);
    }

    public function test_layanan_kunjungan_kosong_bila_tak_ada_apa_pun(): void
    {
        $r = KesmasPresenter::layananKunjungan((object) []);

        $this->assertSame([], $r['badge']);
        $this->assertSame([], $r['keterangan']);
    }

    public function test_config_layanan_lengkap_dan_berurutan(): void
    {
        $this->assertSame(
            ['kn1', 'kn3', 'mtbm', 'mtbs', 'pkat', 'skrining_atresia_bilier', 'oralit_zinc', 'mbg', 'kelas_ibu_balita'],
            array_keys(config('kesmas.layanan'))
        );
        foreach (config('kesmas.layanan') as $kolom => $def) {
            $this->assertArrayHasKey('label', $def, $kolom);
            $this->assertArrayHasKey('badge', $def, $kolom);
            $this->assertArrayHasKey('kolom', $def, $kolom);
        }
    }
}
