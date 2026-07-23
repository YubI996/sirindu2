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
}
