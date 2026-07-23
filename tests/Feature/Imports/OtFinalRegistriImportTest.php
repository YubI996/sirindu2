<?php

namespace Tests\Feature\Imports;

use App\Imports\OtFinalRegistriImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtFinalRegistriImportTest extends TestCase
{
    use RefreshDatabase;

    /** Header berkas OT final (subset yang dipakai importer). */
    protected function header(): array
    {
        return [
            'Nama Anak', 'Jenis Kelamin', 'Tanggal Lahir', 'NIK', 'Nomor KK', 'Anak Ke',
            'Nama Orang Tua (Ibu/Ayah)', 'NIK Orang Tua', 'No HP Orang Tua', 'Alamat',
            'RT', 'Kecamatan', 'Puskesmas', 'Kelurahan', 'Posyandu',
            'Usia Kehamilan (minggu)', 'Berat Lahir - Sasaran (kg)',
            'Panjang Lahir - Sasaran (cm)', 'Lingkar Kepala Lahir (cm)', 'IMD',
            'Tanggal Pengukuran', 'Berat (kg)', 'Tinggi (cm)', 'Cara Ukur', 'LiLA (cm)',
            'ZS BB/U', 'ZS TB/U', 'ZS BB/TB', 'Naik Berat Badan', 'Jml Vit A',
            'Kelas Ibu Balita', 'MBG',
        ];
    }

    protected function baris(array $o = []): array
    {
        return array_values(array_merge([
            'nama' => 'FARAH NUR SEPTIANA PUTRI', 'jk' => 'P', 'tgl_lahir' => '2025-09-12',
            'nik' => '6474025209250001', 'no_kk' => '6474010101010001', 'anak_ke' => '2',
            'nama_ortu' => 'BUDI SANTOSO / SITI AMINAH', 'nik_ortu' => '6474011234560002',
            'no_hp' => '081234567890', 'alamat' => 'JL MELATI NO 3',
            'rt' => '01', 'kecamatan' => 'Bontang Barat', 'puskesmas' => 'Bontang Barat',
            'kelurahan' => 'Gunung Telihan', 'posyandu' => 'Melati 1',
            'usia_kehamilan' => '38', 'bbl' => '3.1', 'pbl' => '49', 'lk_lahir' => '34',
            'imd' => 'Ya',
            'tgl_ukur' => '2026-06-11', 'berat' => '7.02', 'tinggi' => '68',
            'cara_ukur' => 'Terlentang', 'lila' => '13.5',
            'zs_bbu' => '-1.20', 'zs_tbu' => '-1.80', 'zs_bbtb' => '-0.40',
            'naik_bb' => 'N', 'vit_a' => '1', 'kelas_ibu' => 'Tidak', 'mbg' => 'Tidak',
        ], $o));
    }

    public function test_header_wajib_yang_hilang_dilaporkan_sebagai_error(): void
    {
        $header = $this->header();
        $header[3] = 'Kolom Ngawur'; // rusak kolom NIK

        $import = new OtFinalRegistriImport(userId: 1, commit: true);
        $import->collection(collect([$header, $this->baris()]));

        $r = $import->getResults();
        $this->assertNotEmpty($r['error']);
        $this->assertStringContainsString('nik', strtolower($r['error'][0]));
        $this->assertSame(0, $r['anak_dibuat']);
    }

    public function test_baris_normal_membuat_anak_dengan_nik_asli_dan_field_lengkap(): void
    {
        $import = new OtFinalRegistriImport(userId: 1, commit: true);
        $import->collection(collect([$this->header(), $this->baris()]));

        $anak = \App\Models\Anak::where('nik', '6474025209250001')->first();
        $this->assertNotNull($anak);
        $this->assertSame('FARAH NUR SEPTIANA PUTRI', $anak->nama);
        $this->assertSame(2, (int) $anak->jk);                 // P → 2
        // Anak tidak punya $casts → tgl_lahir bernilai string, bukan Carbon.
        $this->assertSame('2025-09-12', substr((string) $anak->tgl_lahir, 0, 10));
        $this->assertSame('6474010101010001', $anak->no_kk);
        $this->assertSame(2, (int) $anak->anak);
        $this->assertSame('BUDI SANTOSO', $anak->nama_ayah);
        $this->assertSame('SITI AMINAH', $anak->nama_ibu);
        $this->assertSame('081234567890', $anak->no_hp);
        $this->assertSame('JL MELATI NO 3', $anak->alamat);
        $this->assertSame(38, (int) $anak->usia_kehamilan_lahir);
        $this->assertSame(3.1, (float) $anak->bbl);
        $this->assertSame(49.0, (float) $anak->pbl);
        $this->assertSame(34.0, (float) $anak->lk_lahir);
        $this->assertSame(1, (int) $anak->status);
        $this->assertSame(1, $import->getResults()['anak_dibuat']);
    }

    public function test_dua_baris_nik_sama_hanya_membuat_satu_anak(): void
    {
        $import = new OtFinalRegistriImport(userId: 1, commit: true);
        $import->collection(collect([
            $this->header(),
            $this->baris(),
            $this->baris(['tgl_ukur' => '2026-06-26', 'berat' => '7.5']),
        ]));

        $this->assertSame(1, \App\Models\Anak::where('nik', '6474025209250001')->count());
        $this->assertSame(1, $import->getResults()['anak_dibuat']);
    }

    public function test_nik_kosong_mendapat_nik_dummy(): void
    {
        $import = new OtFinalRegistriImport(userId: 1, commit: true);
        $import->collection(collect([$this->header(), $this->baris(['nik' => ''])]));

        $anak = \App\Models\Anak::where('nama', 'FARAH NUR SEPTIANA PUTRI')->first();
        $this->assertNotNull($anak);
        $this->assertTrue(\App\Services\NikDummyService::isDummy($anak->nik));
        $this->assertSame(1, $import->getResults()['dummy']);
    }

    public function test_dua_baris_nik_kosong_identik_tetap_jadi_dua_anak(): void
    {
        $import = new OtFinalRegistriImport(userId: 1, commit: true);
        $import->collection(collect([
            $this->header(),
            $this->baris(['nik' => '']),
            $this->baris(['nik' => '', 'tgl_ukur' => '2026-06-26']),
        ]));

        // Nama + tgl lahir + jk identik, tetapi keduanya anak berbeda:
        // findExisting SENGAJA tidak dipakai (lihat spec §6).
        $this->assertSame(2, \App\Models\Anak::where('nama', 'FARAH NUR SEPTIANA PUTRI')->count());
        $this->assertSame(2, $import->getResults()['dummy']);
        $this->assertSame(2, $import->getResults()['anak_dibuat']);
    }

    public function test_wilayah_dan_faskes_diresolusi_ke_id(): void
    {
        $kec = \App\Models\Kecamatan::create(['name' => 'Bontang Barat']);
        $kel = \App\Models\Kelurahan::create(['name' => 'Gunung Telihan', 'id_kecamatan' => $kec->id]);
        $pus = \App\Models\Puskesmas::create(['name' => 'Bontang Barat', 'id_kecamatan' => $kec->id]);
        $pos = \App\Models\Posyandu::create(['name' => 'Melati 1', 'id_puskesmas' => $pus->id]);

        $import = new OtFinalRegistriImport(userId: 1, commit: true);
        $import->collection(collect([$this->header(), $this->baris()]));

        $anak = \App\Models\Anak::where('nik', '6474025209250001')->first();
        $this->assertSame($kec->id, (int) $anak->id_kec);
        $this->assertSame($kel->id, (int) $anak->id_kel);
        $this->assertSame($pus->id, (int) $anak->id_puskesmas);
        $this->assertSame($pos->id, (int) $anak->id_posyandu);
    }

    public function test_wilayah_tak_dikenal_jadi_null_dan_tidak_membuat_master_baru(): void
    {
        $import = new OtFinalRegistriImport(userId: 1, commit: true);
        $import->collection(collect([
            $this->header(),
            $this->baris(['kecamatan' => 'Antah Berantah', 'kelurahan' => 'Negeri Dongeng']),
        ]));

        $anak = \App\Models\Anak::where('nik', '6474025209250001')->first();
        $this->assertNull($anak->id_kec);
        $this->assertNull($anak->id_kel);
        $this->assertSame(0, \App\Models\Kecamatan::where('name', 'Antah Berantah')->count());
        $this->assertSame(0, \App\Models\Kelurahan::where('name', 'Negeri Dongeng')->count());
        $this->assertNotEmpty($import->getResults()['peringatan']);
    }

    public function test_pengukuran_ditulis_dengan_field_benar(): void
    {
        $import = new OtFinalRegistriImport(userId: 1, commit: true);
        $import->collection(collect([$this->header(), $this->baris()]));

        $anak = \App\Models\Anak::where('nik', '6474025209250001')->first();
        $d = \App\Models\DataAnak::where('id_anak', $anak->id)->first();

        $this->assertNotNull($d);
        $this->assertSame(7.02, (float) $d->bb);
        $this->assertSame(68.0, (float) $d->tb);
        $this->assertSame(13.5, (float) $d->lla);
        $this->assertSame(-1.20, (float) $d->zscore_bb_u);
        $this->assertSame(-1.80, (float) $d->zscore_pb_u);
        $this->assertSame(-0.40, (float) $d->zscore_bb_pb);
        $this->assertSame(1, (int) $d->id_user);
        $this->assertSame(1, $import->getResults()['ukur_ditulis']);
    }

    public function test_nik_sama_beda_tanggal_menghasilkan_dua_pengukuran(): void
    {
        $import = new OtFinalRegistriImport(userId: 1, commit: true);
        $import->collection(collect([
            $this->header(),
            $this->baris(['tgl_ukur' => '2026-06-13', 'berat' => '13.5']),
            $this->baris(['tgl_ukur' => '2026-06-10', 'berat' => '13.4']),
        ]));

        $anak = \App\Models\Anak::where('nik', '6474025209250001')->first();
        $this->assertSame(2, \App\Models\DataAnak::where('id_anak', $anak->id)->count());
        $this->assertSame(2, $import->getResults()['ukur_ditulis']);
        $this->assertSame(0, $import->getResults()['lebur']);
    }

    public function test_nik_sama_tanggal_sama_melebur_jadi_satu_dan_dihitung(): void
    {
        $import = new OtFinalRegistriImport(userId: 1, commit: true);
        $import->collection(collect([
            $this->header(),
            $this->baris(['tgl_ukur' => '2026-06-11', 'berat' => '7.02']),
            $this->baris(['tgl_ukur' => '2026-06-11', 'berat' => '7.0']),
        ]));

        $anak = \App\Models\Anak::where('nik', '6474025209250001')->first();
        $rows = \App\Models\DataAnak::where('id_anak', $anak->id)->get();

        $this->assertCount(1, $rows);
        $this->assertSame(7.0, (float) $rows->first()->bb); // baris terakhir menang
        $this->assertSame(1, $import->getResults()['ukur_ditulis']);
        $this->assertSame(1, $import->getResults()['lebur']);
    }

    public function test_no_hp_ganda_diambil_nomor_pertama_dan_dipotong(): void
    {
        $import = new OtFinalRegistriImport(userId: 1, commit: true);
        $import->collection(collect([
            $this->header(),
            $this->baris(['no_hp' => '081254693567 / 08125304445']),
        ]));

        $anak = \App\Models\Anak::where('nik', '6474025209250001')->first();
        $this->assertNotNull($anak, 'Anak harus tetap dibuat meski no_hp bermasalah.');
        $this->assertSame('081254693567', $anak->no_hp);
        $this->assertSame(0, $import->getResults()['dilewati']);
    }

    /**
     * Nilai di luar rentang fisiologis adalah sampah (serial tanggal Excel,
     * nomor HP nyasar, sentinel 8888) — disimpan NULL, anaknya dipertahankan.
     *
     * @dataProvider nilaiSampahProvider
     */
    public function test_nilai_di_luar_rentang_jadi_null_dan_anak_tetap_dibuat(string $kolom, string $field, string $nilai): void
    {
        $import = new OtFinalRegistriImport(userId: 1, commit: true);
        $import->collection(collect([
            $this->header(),
            $this->baris([$kolom => $nilai]),
        ]));

        $anak = \App\Models\Anak::where('nik', '6474025209250001')->first();
        $this->assertNotNull($anak, "Anak harus tetap dibuat meski {$field} bermasalah.");
        $this->assertNull($anak->{$field}, "{$field} bernilai sampah harus jadi NULL.");
        $this->assertSame(0, $import->getResults()['dilewati']);
        $this->assertNotEmpty($import->getResults()['abaikan'], 'Nilai sampah harus tercatat di ringkasan abaikan.');
    }

    public function test_nilai_nol_dianggap_tak_dicatat_bukan_sampah(): void
    {
        $import = new OtFinalRegistriImport(userId: 1, commit: true);
        $import->collection(collect([
            $this->header(),
            $this->baris(['lk_lahir' => '0', 'usia_kehamilan' => '0']),
        ]));

        $anak = \App\Models\Anak::where('nik', '6474025209250001')->first();
        $this->assertNull($anak->lk_lahir);
        $this->assertNull($anak->usia_kehamilan_lahir);
        // 0 berarti "tidak dicatat", bukan nilai rusak → jangan bising.
        $this->assertSame([], $import->getResults()['abaikan']);
    }

    public static function nilaiSampahProvider(): array
    {
        return [
            'usia kehamilan sentinel'  => ['usia_kehamilan', 'usia_kehamilan_lahir', '8888'],
            'usia kehamilan NIK'       => ['usia_kehamilan', 'usia_kehamilan_lahir', '6408090000000000'],
            'panjang lahir serial tgl' => ['pbl', 'pbl', '46238'],
            'lingkar kepala serial tgl' => ['lk_lahir', 'lk_lahir', '46173'],
            'lingkar kepala nomor HP'  => ['lk_lahir', 'lk_lahir', '85245175103'],
            'berat lahir tak masuk akal' => ['bbl', 'bbl', '46200'],
        ];
    }

    public function test_nilai_wajar_tetap_tersimpan(): void
    {
        $import = new OtFinalRegistriImport(userId: 1, commit: true);
        $import->collection(collect([$this->header(), $this->baris()]));

        $anak = \App\Models\Anak::where('nik', '6474025209250001')->first();
        $this->assertSame(38, (int) $anak->usia_kehamilan_lahir);
        $this->assertSame(3.1, (float) $anak->bbl);
        $this->assertSame(49.0, (float) $anak->pbl);
        $this->assertSame(34.0, (float) $anak->lk_lahir);
    }
}
