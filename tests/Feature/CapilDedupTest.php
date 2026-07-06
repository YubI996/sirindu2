<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Services\CapilDedupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dedup duplikat hasil import Capil: anak "Capil-baru" (NIK asli, id_kel NULL,
 * tanpa data kesehatan) yang sebenarnya kembaran dari anak sigizi "belum tersentuh"
 * (NIK salah/dummy) — lolos pencocokan karena NIK beda + ejaan nama beda tipis.
 *
 * Aturan jodoh: nama anak >=70% DAN (No KK sama ATAU nama ortu [ibu/ayah, split '/'] >=87%).
 * Merge: identitas/kependudukan ikut Capil, domisili+kesehatan ikut sigizi, record Capil dihapus.
 */
class CapilDedupTest extends TestCase
{
    use RefreshDatabase;

    private CapilDedupService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new CapilDedupService();
    }

    /** Record bergaya Capil-baru: NIK asli, alamat_ktp terisi, id_kel NULL, tanpa kesehatan. */
    private function capil(array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nik'        => '3274010101200001',
            'nama'       => 'BUDI SANTOSO',
            'jk'         => 1,
            'tgl_lahir'  => '2020-01-15',
            'no'         => 'C',
            'status'     => 1,
            'no_kk'      => '3274000000000001',
            'nama_ibu'   => 'SITI AMINAH',
            'nama_ayah'  => 'JOKO WIDODO',
            'alamat_ktp' => 'JL MAWAR NO 5',
        ], $o));
    }

    /** Record bergaya sigizi-untouched: NIK salah/dummy, ortu tergabung di nama_ibu, punya domisili+kesehatan. */
    private function sigizi(array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nik'       => '9999990101200001',
            'nama'      => 'BUDI SANTOSO',
            'jk'        => 1,
            'tgl_lahir' => '2020-01-15',
            'no'        => 'S',
            'status'    => 1,
            'no_kk'     => '',
            'nama_ibu'  => 'SITI AMINAH / JOKO WIDODO',
            'nama_ayah' => '',
            'alamat'    => 'JL DOMISILI SIGIZI',
            'id_kec'    => 5,
            'id_kel'    => 7,
            'bbl'       => 3.2,
        ], $o));
    }

    public function test_parent_match_mengenali_nama_ayah_di_field_nama_ibu_sigizi(): void
    {
        // Ibu beda, tapi nama ayah Capil ada di segmen kedua nama_ibu sigizi.
        $c = $this->capil(['nama_ibu' => 'ZZZ', 'nama_ayah' => 'JOKO WIDODO']);
        $s = $this->sigizi(['nama_ibu' => 'XXX / JOKO WIDODO']);

        $this->assertGreaterThanOrEqual(87, $this->svc->parentMatch($c, $s));
    }

    public function test_pasangan_lewat_no_kk_sama(): void
    {
        $c = $this->capil(['no_kk' => '3274000000000099', 'nama_ibu' => 'BEDA', 'nama_ayah' => 'BEDA']);
        $s = $this->sigizi(['no_kk' => '3274000000000099', 'nama_ibu' => 'LAIN / LAIN']);

        $pairs = $this->svc->findPairs(collect([$c]), collect([$s]));

        $this->assertCount(1, $pairs);
        $this->assertEquals('kk', $pairs[0]['via']);
    }

    public function test_pasangan_lewat_nama_ortu_meski_kk_kosong(): void
    {
        $c = $this->capil(['no_kk' => '', 'nama_ibu' => 'SITI AMINAH', 'nama_ayah' => 'JOKO WIDODO']);
        $s = $this->sigizi(['no_kk' => '', 'nama_ibu' => 'SITI AMINAH / JOKO WIDODO']);

        $pairs = $this->svc->findPairs(collect([$c]), collect([$s]));

        $this->assertCount(1, $pairs);
        $this->assertEquals('ortu', $pairs[0]['via']);
    }

    public function test_bukan_pasangan_bila_hanya_tgl_lahir_sama(): void
    {
        $c = $this->capil(['nama' => 'BUDI SANTOSO', 'no_kk' => 'AAA', 'nama_ibu' => 'A', 'nama_ayah' => 'B']);
        $s = $this->sigizi(['nama' => 'RANI MELATI', 'no_kk' => 'CCC', 'nama_ibu' => 'X / Y']);

        $pairs = $this->svc->findPairs(collect([$c]), collect([$s]));

        $this->assertCount(0, $pairs);
    }

    public function test_kembar_tidak_digabung(): void
    {
        // Lahir tanggal sama, KK & ortu sama, TAPI nama anak beda jauh → anak kembar, bukan duplikat.
        $c = $this->capil(['nama' => 'BUDI SANTOSO', 'no_kk' => 'KK1', 'nama_ibu' => 'SITI', 'nama_ayah' => 'JOKO']);
        $s = $this->sigizi(['nama' => 'CITRA LESTARI', 'no_kk' => 'KK1', 'nama_ibu' => 'SITI / JOKO']);

        $pairs = $this->svc->findPairs(collect([$c]), collect([$s]));

        $this->assertCount(0, $pairs);
    }

    public function test_satu_capil_hanya_menggabung_satu_sigizi_terbaik(): void
    {
        $c  = $this->capil(['nama' => 'BUDI SANTOSO', 'no_kk' => 'KK1', 'nama_ibu' => 'SITI', 'nama_ayah' => 'JOKO']);
        $s1 = $this->sigizi(['nik' => '9999990101200001', 'nama' => 'BUDI SANTOSO', 'no_kk' => 'KK1', 'nama_ibu' => 'SITI / JOKO']); // KK sama → skor tertinggi
        $s2 = $this->sigizi(['nik' => '9999990101200002', 'nama' => 'BUDI SANTOS',  'no_kk' => '',    'nama_ibu' => 'SITI / JOKO']); // hanya via ortu

        $pairs = $this->svc->findPairs(collect([$c]), collect([$s1, $s2]));

        $this->assertCount(1, $pairs);
        $this->assertEquals($s1->id, $pairs[0]['sigizi']->id);
    }

    public function test_merge_pertahankan_kesehatan_sigizi_dan_identitas_capil(): void
    {
        $c = $this->capil(['nik' => '3274010101200001', 'no_kk' => 'KKCAPIL', 'nama_ibu' => 'SITI AMINAH', 'nama_ayah' => 'JOKO WIDODO']);
        $s = $this->sigizi(['nik' => '9999990101200001', 'no_kk' => '', 'alamat' => 'JL DOMISILI SIGIZI', 'id_kec' => 5, 'id_kel' => 7, 'bbl' => 3.2]);
        $sid = $s->id;
        $cid = $c->id;

        $this->svc->merge($s, $c);

        // Record Capil terserap & terhapus; record sigizi bertahan membawa kesehatan/domisili.
        $this->assertNull(Anak::find($cid));
        $merged = Anak::find($sid);
        $this->assertNotNull($merged);
        // Identitas/kependudukan ikut Capil
        $this->assertEquals('3274010101200001', $merged->nik);
        $this->assertEquals('KKCAPIL', $merged->no_kk);
        $this->assertEquals('SITI AMINAH', $merged->nama_ibu);
        $this->assertEquals('JOKO WIDODO', $merged->nama_ayah);
        // Domisili & kesehatan ikut sigizi
        $this->assertEquals('JL DOMISILI SIGIZI', $merged->alamat);
        $this->assertEquals(5, $merged->id_kec);
        $this->assertEquals(7, $merged->id_kel);
        $this->assertEquals(3.2, (float) $merged->bbl);
        $this->assertEquals(1, Anak::count());
    }

    public function test_scope_memisahkan_capil_baru_dan_sigizi_untouched(): void
    {
        $capilNew = $this->capil(['nik' => '3274010101200001', 'alamat_ktp' => 'JL X', 'id_kel' => null]);
        $sigizi   = $this->sigizi(['nik' => '9999990101200009']); // alamat_ktp NULL (default sigizi maker tak set)
        // Record Capil-updated: alamat_ktp terisi TAPI id_kel terisi & updated>created → bukan dua-duanya.
        $updated  = Anak::create([
            'nik' => '3274010101200055', 'nama' => 'X', 'jk' => 1, 'tgl_lahir' => '2020-01-15',
            'no' => 'U', 'status' => 1, 'alamat_ktp' => 'JL Y', 'id_kel' => 3,
        ]);
        Anak::whereKey($updated->id)->update(['updated_at' => now()->addMinutes(5)]);

        // Record Capil-updated yg sigizi-base-nya tak punya kelurahan: alamat_ktp terisi,
        // alamat (domisili sigizi) terisi, id_kel NULL → tetap BUKAN Capil-baru (sudah punya domisili).
        $updatedNoKel = Anak::create([
            'nik' => '3274010101200077', 'nama' => 'Y', 'jk' => 1, 'tgl_lahir' => '2020-01-15',
            'no' => 'U2', 'status' => 1, 'alamat_ktp' => 'JL Z', 'alamat' => 'DOMISILI ADA', 'id_kel' => null,
        ]);
        Anak::whereKey($updatedNoKel->id)->update(['updated_at' => now()->addMinutes(5)]);

        $cn = $this->svc->capilNew();
        $su = $this->svc->sigiziUntouched();

        $this->assertTrue($cn->contains('id', $capilNew->id));
        $this->assertFalse($cn->contains('id', $updated->id));
        $this->assertFalse($cn->contains('id', $updatedNoKel->id));
        $this->assertFalse($cn->contains('id', $sigizi->id));

        $this->assertTrue($su->contains('id', $sigizi->id));
        $this->assertFalse($su->contains('id', $updated->id));
        $this->assertFalse($su->contains('id', $capilNew->id));
    }

    public function test_export_sigizi_untouched_menulis_csv_hanya_baris_belum_tersentuh(): void
    {
        $a = $this->sigizi(['nik' => '9999990101200001', 'nama' => 'ANAK SATU']);
        $b = $this->sigizi(['nik' => '9999990101200002', 'nama' => 'ANAK DUA']);
        // Capil-baru & sigizi-updated TIDAK boleh ikut terekspor.
        $this->capil(['nik' => '3274010101200001', 'alamat_ktp' => 'JL X', 'id_kel' => null]);
        $updated = Anak::create([
            'nik' => '3274010101200055', 'nama' => 'X', 'jk' => 1, 'tgl_lahir' => '2020-01-15',
            'no' => 'U', 'status' => 1, 'alamat_ktp' => 'JL Y', 'id_kel' => 3,
        ]);
        Anak::whereKey($updated->id)->update(['updated_at' => now()->addMinutes(5)]);

        $path = tempnam(sys_get_temp_dir(), 'dedup_') . '.csv';
        $count = $this->svc->exportSigiziUntouched($path);

        $this->assertEquals(2, $count);
        $this->assertFileExists($path);

        $lines = array_values(array_filter(explode("\n", file_get_contents($path)), fn($l) => trim($l) !== ''));
        $this->assertCount(3, $lines); // header + 2 baris
        $this->assertStringContainsString('nik', $lines[0]); // ada header

        $body = implode("\n", array_slice($lines, 1));
        $this->assertStringContainsString('9999990101200001', $body);
        $this->assertStringContainsString('9999990101200002', $body);
        $this->assertStringNotContainsString('3274010101200001', $body); // capil-baru tak ikut
        $this->assertStringNotContainsString('3274010101200055', $body); // sigizi-updated tak ikut

        @unlink($path);
    }

    public function test_export_pairs_menulis_csv_pasangan_dengan_id_dan_skor(): void
    {
        $c = $this->capil(['nik' => '3274010101200001', 'no_kk' => 'KK1', 'nama_ibu' => 'SITI AMINAH', 'nama_ayah' => 'JOKO WIDODO']);
        $s = $this->sigizi(['nik' => '9999990101200001', 'no_kk' => 'KK1', 'nama_ibu' => 'SITI AMINAH / JOKO WIDODO']);
        // Pasangan kedua yang tak cocok (tgl beda) → tak boleh ikut.
        $this->capil(['nik' => '3274010101200099', 'tgl_lahir' => '2019-05-05', 'no_kk' => 'KKX']);

        $path = tempnam(sys_get_temp_dir(), 'pairs_') . '.csv';
        $count = $this->svc->exportPairs($path);

        $this->assertEquals(1, $count);
        $this->assertFileExists($path);

        $lines = array_values(array_filter(explode("\n", file_get_contents($path)), fn($l) => trim($l) !== ''));
        $this->assertCount(2, $lines); // header + 1 pasangan
        $this->assertStringContainsString('id_capil', $lines[0]);
        $this->assertStringContainsString('id_sigizi', $lines[0]);
        $this->assertStringContainsString('via', $lines[0]);

        $body = $lines[1];
        $this->assertStringContainsString((string) $c->id, $body);
        $this->assertStringContainsString((string) $s->id, $body);
        $this->assertStringContainsString('kk', $body);

        @unlink($path);
    }

    public function test_export_sigizi_unpaired_hanya_yang_tak_punya_padanan_capil(): void
    {
        // Sigizi yang BERPADANAN dengan Capil (via KK) → tak boleh ikut export ini.
        $cMatch = $this->capil(['nik' => '3274010101200001', 'no_kk' => 'KK1', 'nama_ibu' => 'SITI AMINAH', 'nama_ayah' => 'JOKO WIDODO']);
        $sPaired = $this->sigizi(['nik' => '9999990101200001', 'nama' => 'BUDI SANTOSO', 'no_kk' => 'KK1', 'nama_ibu' => 'SITI AMINAH / JOKO WIDODO']);
        // Sigizi tanpa padanan apa pun di Capil → HARUS ikut.
        $sOrphan = $this->sigizi(['nik' => '9999990101200002', 'nama' => 'YATIM SENDIRI', 'no_kk' => 'KKZZ', 'tgl_lahir' => '2018-03-03']);

        $path = tempnam(sys_get_temp_dir(), 'orphan_') . '.csv';
        $count = $this->svc->exportSigiziUnpaired($path);

        $this->assertEquals(1, $count);
        $this->assertFileExists($path);

        $lines = array_values(array_filter(explode("\n", file_get_contents($path)), fn($l) => trim($l) !== ''));
        $this->assertCount(2, $lines); // header + 1 orphan
        $this->assertStringContainsString('nik', $lines[0]);

        $body = $lines[1];
        $this->assertStringContainsString('9999990101200002', $body);   // orphan ikut
        $this->assertStringNotContainsString('9999990101200001', $body); // yg berpadanan tak ikut

        @unlink($path);
    }

    public function test_pasangan_meski_tgl_lahir_meleset_sehari_bila_nama_dan_ortu_persis(): void
    {
        // Typo tanggal: nama anak & ortu persis sama, tgl lahir beda 1 hari → tetap satu anak.
        $c = $this->capil(['tgl_lahir' => '2020-01-15', 'no_kk' => '', 'nama' => 'BUDI SANTOSO', 'nama_ibu' => 'SITI AMINAH', 'nama_ayah' => 'JOKO WIDODO']);
        $s = $this->sigizi(['tgl_lahir' => '2020-01-16', 'no_kk' => '', 'nama' => 'BUDI SANTOSO', 'nama_ibu' => 'SITI AMINAH / JOKO WIDODO']);

        $pairs = $this->svc->findPairs(collect([$c]), collect([$s]));

        $this->assertCount(1, $pairs);
        $this->assertEquals('ortu', $pairs[0]['via']);
    }

    public function test_tgl_lahir_beda_dua_hari_tidak_dijodohkan_untuk_pasangan_tak_strong(): void
    {
        // Pasangan TAK "strong" (nama anak ~92% — di bawah 95): toleransi tanggal tetap
        // hanya +-1 hari, jadi beda 2 hari ditolak. (Kalau nama+ortu >=95% tanggal diabaikan,
        // diuji terpisah di test_abaikan_tanggal_*.)
        $c = $this->capil(['tgl_lahir' => '2020-01-15', 'no_kk' => 'KK1', 'nama' => 'BUDI SANTOSO']);
        $s = $this->sigizi(['tgl_lahir' => '2020-01-17', 'no_kk' => 'KK1', 'nama' => 'BUDI SANTOSX']);

        $pairs = $this->svc->findPairs(collect([$c]), collect([$s]));

        $this->assertCount(0, $pairs);
    }

    public function test_tgl_beda_sehari_tetap_butuh_nama_kuat(): void
    {
        // Beda tgl 1 hari + No KK sama, TAPI nama anak beda jauh → jangan gabung (cegah kembar/sibling).
        $c = $this->capil(['tgl_lahir' => '2020-01-15', 'no_kk' => 'KK1', 'nama' => 'BUDI SANTOSO']);
        $s = $this->sigizi(['tgl_lahir' => '2020-01-16', 'no_kk' => 'KK1', 'nama' => 'RANI MELATI']);

        $pairs = $this->svc->findPairs(collect([$c]), collect([$s]));

        $this->assertCount(0, $pairs);
    }

    public function test_tgl_tepat_diprioritaskan_atas_tgl_meleset(): void
    {
        // Satu sigizi diperebutkan dua Capil: tgl tepat harus menang atas tgl meleset sehari.
        $s     = $this->sigizi(['nik' => '9999990101200001', 'tgl_lahir' => '2020-01-15', 'no_kk' => '', 'nama' => 'BUDI SANTOSO', 'nama_ibu' => 'SITI AMINAH / JOKO WIDODO']);
        $exact = $this->capil(['nik' => '3274010101200001', 'tgl_lahir' => '2020-01-15', 'no_kk' => '', 'nama' => 'BUDI SANTOSO', 'nama_ibu' => 'SITI AMINAH', 'nama_ayah' => 'JOKO WIDODO']);
        $near  = $this->capil(['nik' => '3274010101200002', 'tgl_lahir' => '2020-01-16', 'no_kk' => '', 'nama' => 'BUDI SANTOSO', 'nama_ibu' => 'SITI AMINAH', 'nama_ayah' => 'JOKO WIDODO']);

        $pairs = $this->svc->findPairs(collect([$exact, $near]), collect([$s]));

        $this->assertCount(1, $pairs);
        $this->assertEquals($exact->id, $pairs[0]['capil']->id);
    }

    public function test_abaikan_tanggal_bila_nama_dan_ortu_sangat_mirip(): void
    {
        // Nama anak & ortu persis sama, tapi tgl lahir jauh berbeda (mis. salah tahun) → tetap digabung.
        $c = $this->capil(['tgl_lahir' => '2019-01-15', 'no_kk' => '', 'nama' => 'BUDI SANTOSO', 'nama_ibu' => 'SITI AMINAH', 'nama_ayah' => 'JOKO WIDODO']);
        $s = $this->sigizi(['tgl_lahir' => '2020-06-20', 'no_kk' => '', 'nama' => 'BUDI SANTOSO', 'nama_ibu' => 'SITI AMINAH / JOKO WIDODO']);

        $pairs = $this->svc->findPairs(collect([$c]), collect([$s]));

        $this->assertCount(1, $pairs);
        $this->assertEquals('ortu', $pairs[0]['via']);
    }

    public function test_tgl_jauh_butuh_ortu_sangat_mirip_juga(): void
    {
        // Nama anak persis (100%) TAPI nama ortu beda jauh, tgl jauh → JANGAN gabung.
        $c = $this->capil(['tgl_lahir' => '2019-01-15', 'no_kk' => '', 'nama' => 'BUDI SANTOSO', 'nama_ibu' => 'SITI AMINAH', 'nama_ayah' => 'JOKO WIDODO']);
        $s = $this->sigizi(['tgl_lahir' => '2020-06-20', 'no_kk' => '', 'nama' => 'BUDI SANTOSO', 'nama_ibu' => 'RINI HARTATI / BAMBANG']);

        $pairs = $this->svc->findPairs(collect([$c]), collect([$s]));

        $this->assertCount(0, $pairs);
    }

    public function test_tgl_jauh_kk_sama_bukan_alasan_gabung_sibling(): void
    {
        // Tgl jauh + No KK SAMA + nama anak persis, TAPI nama ortu beda → kemungkinan sibling,
        // KK sama saja TIDAK boleh membuka jendela tanggal. Harus ditolak.
        $c = $this->capil(['tgl_lahir' => '2019-01-15', 'no_kk' => 'KK1', 'nama' => 'BUDI SANTOSO', 'nama_ibu' => 'AAA', 'nama_ayah' => 'BBB']);
        $s = $this->sigizi(['tgl_lahir' => '2021-09-09', 'no_kk' => 'KK1', 'nama' => 'BUDI SANTOSO', 'nama_ibu' => 'CCC / DDD']);

        $pairs = $this->svc->findPairs(collect([$c]), collect([$s]));

        $this->assertCount(0, $pairs);
    }

    public function test_duplicate_groups_by_name_dob_seluruh_tabel_anak(): void
    {
        // Duplikat dihitung atas SELURUH tabel anak (bukan hanya Capil-baru): sepasang
        // record nama+tgl identik → satu kelompok, walau salah satunya bergaya sigizi.
        $a = $this->capil(['nik' => '3274010101200001', 'nama' => 'RANI MELATI', 'tgl_lahir' => '2020-01-15']);
        $b = $this->sigizi(['nik' => '9999990101200002', 'nama' => 'RANI MELATI', 'tgl_lahir' => '2020-01-15', 'alamat' => 'ADA', 'id_kel' => 7]);
        // Nama sama tapi tgl beda → bukan kelompok.
        $this->capil(['nik' => '3274010101200003', 'nama' => 'RANI MELATI', 'tgl_lahir' => '2019-05-05']);
        // Tgl sama tapi nama beda → bukan kelompok.
        $this->capil(['nik' => '3274010101200004', 'nama' => 'DINI PUTRI', 'tgl_lahir' => '2020-01-15']);

        $groups = $this->svc->duplicateGroupsByNameDob();

        $this->assertCount(1, $groups);
        $this->assertCount(2, $groups[0]);
    }

    public function test_duplicate_groups_by_kk_name_kk_dan_nama_sama(): void
    {
        // No.KK + nama identik (tgl boleh beda) → satu kelompok.
        $this->capil(['nik' => '3274010101200001', 'no_kk' => 'KKX', 'nama' => 'ANDI WIJAYA', 'tgl_lahir' => '2020-01-01']);
        $this->capil(['nik' => '3274010101200002', 'no_kk' => 'KKX', 'nama' => 'ANDI WIJAYA', 'tgl_lahir' => '2019-02-02']);
        // KK sama, nama beda → bukan kelompok (mis. kakak-adik).
        $this->capil(['nik' => '3274010101200003', 'no_kk' => 'KKX', 'nama' => 'BENI SUSANTO']);
        // No.KK kosong → diabaikan meski nama sama.
        $this->capil(['nik' => '3274010101200004', 'no_kk' => '', 'nama' => 'CINDY LARA']);
        $this->capil(['nik' => '3274010101200005', 'no_kk' => '', 'nama' => 'CINDY LARA']);

        $groups = $this->svc->duplicateGroupsByKkName();

        $this->assertCount(1, $groups);
        $this->assertCount(2, $groups[0]);
    }

    public function test_export_internal_duplicates_gabung_sinyal_nama_tgl_dan_kk_nama(): void
    {
        // Sinyal B: nama + tgl identik.
        $this->capil(['nik' => '3274010101200001', 'no_kk' => 'K1', 'nama' => 'RANI MELATI', 'tgl_lahir' => '2020-01-15']);
        $this->capil(['nik' => '3274010101200002', 'no_kk' => 'K2', 'nama' => 'RANI MELATI', 'tgl_lahir' => '2020-01-15']);
        // Sinyal C: No.KK + nama identik, tgl beda (jadi bukan B).
        $this->capil(['nik' => '3274010101200003', 'no_kk' => 'KZ', 'nama' => 'ANDI WIJAYA', 'tgl_lahir' => '2018-03-03']);
        $this->capil(['nik' => '3274010101200004', 'no_kk' => 'KZ', 'nama' => 'ANDI WIJAYA', 'tgl_lahir' => '2017-05-05']);
        // Record sendiri tanpa kembaran → tak masuk.
        $this->capil(['nik' => '3274010101200005', 'no_kk' => 'KS', 'nama' => 'SENDIRIAN', 'tgl_lahir' => '2015-05-05']);

        $path = tempnam(sys_get_temp_dir(), 'dupint_') . '.csv';
        $res = $this->svc->exportInternalDuplicates($path);

        $this->assertEquals(1, $res['name_dob']['groups']);
        $this->assertEquals(2, $res['name_dob']['rows']);
        $this->assertEquals(1, $res['kk_name']['groups']);
        $this->assertEquals(2, $res['kk_name']['rows']);
        $this->assertEquals(2, $res['total_groups']);
        $this->assertEquals(4, $res['total_rows']);

        $lines = array_values(array_filter(explode("\n", file_get_contents($path)), fn($l) => trim($l) !== ''));
        $this->assertCount(5, $lines); // header + 4 baris
        $this->assertStringContainsString('signal', $lines[0]);

        $body = implode("\n", array_slice($lines, 1));
        $this->assertStringContainsString('RANI MELATI', $body);
        $this->assertStringContainsString('ANDI WIJAYA', $body);
        $this->assertStringNotContainsString('SENDIRIAN', $body);

        @unlink($path);
    }

    public function test_export_name_candidates_xlsx_pertahankan_nik_sebagai_teks(): void
    {
        // Orphan sigizi vs Capil mirip → satu kandidat. NIK 16-digit harus tetap utuh sebagai teks.
        $this->sigizi(['nik' => '9999990101200002', 'nama' => 'BUDI SANTOSO', 'no_kk' => '3201099988887777', 'tgl_lahir' => '2018-03-03', 'nama_ibu' => 'BEDA / BEDA']);
        $this->capil(['nik' => '3274010101200010', 'nama' => 'BUDI SANTOSX', 'no_kk' => '3274000011112222', 'tgl_lahir' => '2010-10-10', 'nama_ibu' => 'LAIN', 'nama_ayah' => 'LAIN']);

        $path = tempnam(sys_get_temp_dir(), 'candx_') . '.xlsx';
        $count = $this->svc->exportNameCandidatesXlsx($path, 80.0);

        $this->assertEquals(1, $count);
        $this->assertFileExists($path);

        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        // Baris 1 = header, baris 2 = data pertama.
        $header = [];
        foreach ($sheet->getRowIterator(1, 1)->current()->getCellIterator() as $cell) {
            $header[] = $cell->getValue();
        }
        $this->assertContains('nik_sigizi', $header);
        $this->assertContains('nik_capil', $header);

        $nikSigiziCol = chr(65 + array_search('nik_sigizi', $header));
        $nikCapilCol  = chr(65 + array_search('nik_capil', $header));

        // NIK utuh 16 digit (tak jadi 9.99999E+15) dan bertipe string.
        $this->assertSame('9999990101200002', (string) $sheet->getCell($nikSigiziCol . '2')->getValue());
        $this->assertSame('3274010101200010', (string) $sheet->getCell($nikCapilCol . '2')->getValue());
        $this->assertSame(
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,
            $sheet->getCell($nikSigiziCol . '2')->getDataType()
        );

        @unlink($path);
    }

    public function test_export_name_candidates_berbasis_nama_saja(): void
    {
        // Orphan sigizi (tak berpadanan): nama ~92% mirip capil, TAPI tgl & ortu beda → tetap kandidat
        // (filter hanya nama >=80%, abaikan tanggal/ortu/KK).
        $sOrphan  = $this->sigizi(['nik' => '9999990101200002', 'nama' => 'BUDI SANTOSO', 'no_kk' => 'KZZ', 'tgl_lahir' => '2018-03-03', 'nama_ibu' => 'BEDA / BEDA']);
        $cSimilar = $this->capil(['nik' => '3274010101200010', 'nama' => 'BUDI SANTOSX', 'no_kk' => 'KAA', 'tgl_lahir' => '2010-10-10', 'nama_ibu' => 'LAIN', 'nama_ayah' => 'LAIN']);
        // Nama jauh (<80%) → bukan kandidat.
        $cFar = $this->capil(['nik' => '3274010101200011', 'nama' => 'RANI MELATI', 'tgl_lahir' => '2010-10-10']);
        // Pasangan kuat (masuk findPairs) → DIKECUALIKAN dari kandidat sisa.
        $cPair = $this->capil(['nik' => '3274010101200012', 'nama' => 'CITRA DEWI', 'no_kk' => 'KKP', 'tgl_lahir' => '2020-01-15', 'nama_ibu' => 'WATI', 'nama_ayah' => 'TONO']);
        $sPair = $this->sigizi(['nik' => '9999990101200003', 'nama' => 'CITRA DEWI', 'no_kk' => 'KKP', 'tgl_lahir' => '2020-01-15', 'nama_ibu' => 'WATI / TONO']);

        $path = tempnam(sys_get_temp_dir(), 'cand_') . '.csv';
        $count = $this->svc->exportNameCandidates($path, 80.0);

        $this->assertEquals(1, $count);
        $lines = array_values(array_filter(explode("\n", file_get_contents($path)), fn($l) => trim($l) !== ''));
        $this->assertCount(2, $lines); // header + 1 kandidat
        $this->assertStringContainsString('child_sim', $lines[0]);

        $body = $lines[1];
        $this->assertStringContainsString('3274010101200010', $body); // cSimilar muncul
        $this->assertStringNotContainsString('RANI', $body);          // nama jauh tak muncul
        $this->assertStringNotContainsString('CITRA', $body);         // pasangan kuat dikecualikan

        @unlink($path);
    }
}
