<?php

namespace Tests\Feature;

use App\Imports\OperasiTimbangImport;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Services\NikDummyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperasiTimbangImportTest extends TestCase
{
    use RefreshDatabase;

    /** Header e-PPGBM (subset yang dipakai importer). */
    private function header(): array
    {
        return [
            'No', 'NIK', 'Nama', 'JK', 'Tgl Lahir', 'Nama Ortu',
            'Tanggal Pengukuran', 'Berat', 'Tinggi', 'Cara Ukur', 'LiLA',
            'ZS BB/U', 'ZS TB/U', 'ZS BB/TB', 'Naik Berat Badan',
            'Jml Vit A', 'Kelas Ibu Balita', 'MBG',
        ];
    }

    private function baris(array $o = []): array
    {
        return array_values(array_merge([
            'no' => '1', 'nik' => '02022**********', 'nama' => 'MUHAMMAD ABIZAR',
            'jk' => 'L', 'tgl_lahir' => '2024-02-02', 'nama_ortu' => 'AGIL ANASTASYA F',
            'tgl_ukur' => '2026-06-09', 'berat' => '10.6', 'tinggi' => '81.5',
            'cara_ukur' => 'Terlentang', 'lila' => '14', 'zs_bbu' => '-1.73',
            'zs_tbu' => '-2.95', 'zs_bbtb' => '-0.15', 'naik_bb' => 'N',
            'vit_a' => '-', 'kelas_ibu' => 'Tidak', 'mbg' => 'Tidak',
        ], $o));
    }

    private function buatAnak(array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nik' => (string) random_int(1000000000000000, 9999999999999999),
            'nama' => 'MUHAMMAD ABIZAR', 'jk' => 1, 'tgl_lahir' => '2024-02-02',
            'nama_ibu' => 'AGIL ANASTASYA F', 'nama_ayah' => 'BUDI',
            'no' => 'REG-' . random_int(1, 999999), 'status' => 1,
        ], $o));
    }

    public function test_commit_menulis_data_anak_dengan_field_benar(): void
    {
        $anak = $this->buatAnak();

        $import = new OperasiTimbangImport(userId: 1, commit: true, minNama: 88);
        $import->collection(collect([$this->header(), $this->baris()]));

        $d = DataAnak::where('id_anak', $anak->id)->where('tgl_kunjungan', '2026-06-09')->first();
        $this->assertNotNull($d);
        $this->assertEquals(10.6, (float) $d->bb);
        $this->assertEquals(81.5, (float) $d->tb);
        $this->assertEquals(14.0, (float) $d->lla);
        $this->assertEquals('L', $d->posisi); // "Terlentang" → 'L'
        $this->assertEquals(28, (int) $d->bln); // 2024-02-02 → 2026-06-09
        $this->assertEquals(-1.73, (float) $d->zscore_bb_u);
        $this->assertEquals(-2.95, (float) $d->zscore_pb_u);
        $this->assertEquals(-0.15, (float) $d->zscore_bb_pb);
        $this->assertEquals(1, $import->getResults()['matched']);
    }

    public function test_dry_run_tidak_menulis_apa_pun(): void
    {
        $this->buatAnak();

        $import = new OperasiTimbangImport(userId: 1, commit: false, minNama: 88);
        $import->collection(collect([$this->header(), $this->baris()]));

        $this->assertEquals(0, DataAnak::count());
        $this->assertEquals(1, $import->getResults()['matched']); // "would match"
    }

    public function test_commit_idempoten_run_kedua_tidak_menggandakan(): void
    {
        $this->buatAnak();

        foreach (range(1, 2) as $_) {
            $import = new OperasiTimbangImport(userId: 1, commit: true, minNama: 88);
            $import->collection(collect([$this->header(), $this->baris()]));
        }

        $this->assertEquals(1, DataAnak::count());
    }

    public function test_baris_tak_cocok_dilaporkan_tanpa_menulis(): void
    {
        // Tidak ada anak sama sekali
        $import = new OperasiTimbangImport(userId: 1, commit: true, minNama: 88);
        $import->collection(collect([$this->header(), $this->baris()]));

        $this->assertEquals(0, DataAnak::count());
        $r = $import->getResults();
        $this->assertEquals(0, $r['matched']);
        $this->assertCount(1, $r['unmatched']);
        $this->assertEquals('MUHAMMAD ABIZAR', $r['unmatched'][0]['nama']);
    }

    public function test_tanggal_pengukuran_kosong_dilewati(): void
    {
        $this->buatAnak();

        $import = new OperasiTimbangImport(userId: 1, commit: true, minNama: 88);
        $import->collection(collect([$this->header(), $this->baris(['tgl_ukur' => ''])]));

        $this->assertEquals(0, DataAnak::count());
        $this->assertEquals(1, $import->getResults()['skipped']);
    }

    // ── Jalur --keputusan: petakan baris ambigu ke record pilihan ──

    /** Dua anak identik (nama+ibu+tgl+jk) → matcher AMBIGU. */
    private function buatAmbigu(): array
    {
        return [
            $this->buatAnak(['nama' => 'MUHAMMAD ABIZAR', 'nama_ibu' => 'AGIL ANASTASYA F']),
            $this->buatAnak(['nama' => 'MUHAMMAD ABIZAR', 'nama_ibu' => 'AGIL ANASTASYA F']),
        ];
    }

    public function test_keputusan_menulis_ke_record_pilihan(): void
    {
        [$a1, $a2] = $this->buatAmbigu();

        // Baris data = rowNum 2 (header di baris 1)
        $import = new OperasiTimbangImport(userId: 1, commit: true, minNama: 88, sheet: 0, keputusan: [2 => (string) $a2->id]);
        $import->collection(collect([$this->header(), $this->baris()]));

        $this->assertEquals(1, DataAnak::where('id_anak', $a2->id)->count());
        $this->assertEquals(0, DataAnak::where('id_anak', $a1->id)->count());
        $r = $import->getResults();
        $this->assertEquals(1, $r['resolved']);
        $this->assertCount(0, $r['ambiguous']);
    }

    public function test_keputusan_skip_tidak_menulis(): void
    {
        $this->buatAmbigu();

        $import = new OperasiTimbangImport(userId: 1, commit: true, minNama: 88, sheet: 0, keputusan: [2 => 'skip']);
        $import->collection(collect([$this->header(), $this->baris()]));

        $this->assertEquals(0, DataAnak::count());
        $this->assertEquals(1, $import->getResults()['resolved_skip']);
    }

    public function test_keputusan_id_tak_valid_dilaporkan_tetap_ambigu(): void
    {
        $this->buatAmbigu();

        $import = new OperasiTimbangImport(userId: 1, commit: true, minNama: 88, sheet: 0, keputusan: [2 => '99999999']);
        $import->collection(collect([$this->header(), $this->baris()]));

        $this->assertEquals(0, DataAnak::count());
        $r = $import->getResults();
        $this->assertNotEmpty($r['keputusan_error']);
        $this->assertCount(1, $r['ambiguous']);
    }

    public function test_dry_run_keputusan_menghitung_tanpa_menulis(): void
    {
        [$a1, $a2] = $this->buatAmbigu();

        $import = new OperasiTimbangImport(userId: 1, commit: false, minNama: 88, sheet: 0, keputusan: [2 => (string) $a2->id]);
        $import->collection(collect([$this->header(), $this->baris()]));

        $this->assertEquals(0, DataAnak::count());
        $this->assertEquals(1, $import->getResults()['resolved']);
    }

    public function test_tanpa_keputusan_baris_ambigu_tetap_ambigu(): void
    {
        $this->buatAmbigu();

        $import = new OperasiTimbangImport(userId: 1, commit: true, minNama: 88);
        $import->collection(collect([$this->header(), $this->baris()]));

        $this->assertEquals(0, DataAnak::count());
        $this->assertCount(1, $import->getResults()['ambiguous']);
    }

    // ── Jalur --buat-tak-cocok: baris TAK_COCOK dibuatkan anak NIK dummy ──

    /** Header lengkap dengan kolom wilayah (meniru file e-PPGBM asli). */
    private function headerLengkap(): array
    {
        return [
            'No', 'NIK', 'Nama', 'JK', 'Tgl Lahir', 'Nama Ortu',
            'Kec', 'Desa/Kel', 'RT', 'Alamat',
            'Tanggal Pengukuran', 'Berat', 'Tinggi', 'Cara Ukur', 'LiLA',
            'ZS BB/U', 'ZS TB/U', 'ZS BB/TB', 'Naik Berat Badan',
            'Jml Vit A', 'Kelas Ibu Balita', 'MBG',
        ];
    }

    private function barisLengkap(array $o = []): array
    {
        return array_values(array_merge([
            'no' => '1', 'nik' => '02022**********', 'nama' => 'MUHAMMAD ABIZAR',
            'jk' => 'L', 'tgl_lahir' => '2024-02-02',
            'nama_ortu' => 'BUDI SANTOSO / AGIL ANASTASYA F',
            'kec' => 'BONTANG UTARA', 'kel' => 'LOK TUAN', 'rt' => '12',
            'alamat' => 'JL. RE. MARTADINATA',
            'tgl_ukur' => '2026-06-09', 'berat' => '10.6', 'tinggi' => '81.5',
            'cara_ukur' => 'Terlentang', 'lila' => '14', 'zs_bbu' => '-1.73',
            'zs_tbu' => '-2.95', 'zs_bbtb' => '-0.15', 'naik_bb' => 'N',
            'vit_a' => '-', 'kelas_ibu' => 'Tidak', 'mbg' => 'Tidak',
        ], $o));
    }

    private function importBuat(bool $commit = true): OperasiTimbangImport
    {
        return new OperasiTimbangImport(
            userId: 1, commit: $commit, minNama: 88, sheet: 0,
            keputusan: null, buatTakCocok: true,
        );
    }

    public function test_buat_tak_cocok_membuat_anak_dummy_dengan_measurement(): void
    {
        $kec = Kecamatan::create(['name' => 'BONTANG UTARA']);
        $kel = Kelurahan::create(['id_kecamatan' => $kec->id, 'name' => 'LOK TUAN']);

        $import = $this->importBuat();
        $import->collection(collect([$this->headerLengkap(), $this->barisLengkap()]));

        $anak = Anak::first();
        $this->assertNotNull($anak);
        $this->assertTrue(NikDummyService::isDummy($anak->nik));
        $this->assertEquals('MUHAMMAD ABIZAR', $anak->nama);
        $this->assertEquals(1, (int) $anak->jk);
        $this->assertEquals('2024-02-02', Carbon::parse($anak->tgl_lahir)->format('Y-m-d'));
        $this->assertEquals('BUDI SANTOSO', $anak->nama_ayah);
        $this->assertEquals('AGIL ANASTASYA F', $anak->nama_ibu);
        $this->assertEquals($kec->id, (int) $anak->id_kec);
        $this->assertEquals($kel->id, (int) $anak->id_kel);
        $this->assertEquals(1, DataAnak::where('id_anak', $anak->id)->where('tgl_kunjungan', '2026-06-09')->count());

        $r = $import->getResults();
        $this->assertEquals(1, $r['dibuat']);
        $this->assertCount(0, $r['unmatched']);
        $this->assertEquals($anak->nik, $r['dibuat_list'][0]['nik']);
    }

    public function test_buat_tak_cocok_dry_run_tidak_menulis(): void
    {
        $import = $this->importBuat(commit: false);
        $import->collection(collect([$this->headerLengkap(), $this->barisLengkap()]));

        $this->assertEquals(0, Anak::count());
        $this->assertEquals(0, DataAnak::count());
        $r = $import->getResults();
        $this->assertEquals(1, $r['dibuat']); // dihitung sbg "akan dibuat"
        $this->assertEquals('(dry-run)', $r['dibuat_list'][0]['nik']);
    }

    public function test_buat_tak_cocok_idempoten_run_kedua_tidak_menggandakan(): void
    {
        foreach (range(1, 2) as $_) {
            $import = $this->importBuat();
            $import->collection(collect([$this->headerLengkap(), $this->barisLengkap()]));
        }

        $this->assertEquals(1, Anak::count());
        $this->assertEquals(1, DataAnak::count());
    }

    public function test_buat_tak_cocok_wilayah_tak_dikenal_tetap_dibuat_dengan_id_null(): void
    {
        // Tidak ada master wilayah sama sekali
        $import = $this->importBuat();
        $import->collection(collect([$this->headerLengkap(), $this->barisLengkap()]));

        $anak = Anak::first();
        $this->assertNotNull($anak);
        $this->assertNull($anak->id_kec);
        $this->assertNull($anak->id_kel);
        $r = $import->getResults();
        $this->assertNotEmpty(array_filter($r['failures'], fn ($f) => str_contains($f, 'Kelurahan')));
    }

    public function test_buat_tak_cocok_perempuan_nik_encode_dd_plus_40(): void
    {
        $import = $this->importBuat();
        $import->collection(collect([
            $this->headerLengkap(),
            $this->barisLengkap(['nama' => 'SITI AMINAH', 'jk' => 'P', 'nama_ortu' => 'AHMAD / RINA']),
        ]));

        $anak = Anak::first();
        $this->assertEquals(2, (int) $anak->jk);
        // tgl lahir 2024-02-02, P → DD+40 = 42 → digit 7-8 NIK = '42'
        $this->assertEquals('42', substr($anak->nik, 6, 2));
    }

    public function test_tanpa_flag_buat_tak_cocok_tidak_membuat_anak(): void
    {
        $import = new OperasiTimbangImport(userId: 1, commit: true, minNama: 88);
        $import->collection(collect([$this->headerLengkap(), $this->barisLengkap()]));

        $this->assertEquals(0, Anak::count());
        $this->assertCount(1, $import->getResults()['unmatched']);
    }
}
