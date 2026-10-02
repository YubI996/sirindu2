<?php

namespace Tests\Feature\Kesmas;

use App\Exports\KesmasExport;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\Rt;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Support\WilkerPuskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/** Independent swarm checks for form-to-database-to-XLSX behavior. */
class KesmasSwarmDataExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private array $wilayah;
    private int $urutan = 0;

    protected function setUp(): void
    {
        parent::setUp();
        WilkerPuskesmas::flushCache();
        ImunisasiStatusService::flushCache();
        $this->admin = User::factory()->create(['type' => 1, 'role' => null]);
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'Bontang Baru', 'id_kecamatan' => $kec->id]);
        $pkm = Puskesmas::create(['name' => 'Bontang Utara 1', 'id_kecamatan' => $kec->id]);
        $pos = Posyandu::create(['name' => 'Swarm Melati', 'id_puskesmas' => $pkm->id]);
        $rt = Rt::create(['name' => '01', 'id_kelurahan' => $kel->id, 'id_posyandu' => $pos->id]);
        $this->wilayah = ['id_kec' => $kec->id, 'id_kel' => $kel->id, 'id_puskesmas' => $pkm->id, 'id_posyandu' => $pos->id, 'id_rt' => $rt->id];
    }

    private function dasar(array $extra = []): array
    {
        return array_merge([
            'no_kk' => '6474010101900010', 'nik' => '6474010101250010', 'nama' => 'Swarm Anak',
            'nik_ortu' => '6474010101900011', 'nama_ibu' => 'Swarm Ibu', 'nama_ayah' => 'Swarm Ayah',
            'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2025-01-10', 'golda' => 'O',
            'anak' => 1, 'no' => '1', 'status' => 1, 'sumber' => 'manual',
            'posisi' => 'L', 'bb' => 4.5, 'tb' => 55, 'lla' => 11, 'lk' => 38,
            'asi' => '1', 'vit_a' => '0', 'obat_cacing' => '0', 'ddtka' => '',
            'pitting_edema' => '0', 'mbg' => '0', 'kelas_ibu_balita' => '0',
            'tgl_kunjungan' => '2025-02-10',
        ], $this->wilayah, $extra);
    }

    private function anak(array $extra = []): Anak
    {
        $nik = '647401010125' . str_pad((string) ++$this->urutan, 4, '0', STR_PAD_LEFT);
        return Anak::create(array_merge([
            'nik' => $nik, 'nama' => 'Swarm Anak ' . $this->urutan, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2025-01-10', 'status' => 1, 'sumber' => 'manual', 'no' => '1',
        ], $this->wilayah, $extra));
    }

    private function kesmas(): array
    {
        return [
            'no_id_epus' => 'EP-SWARM', 'fktp_bpjs' => 'Puskesmas Utara', 'air_bersih' => '1',
            'jamban_sehat' => '0', 'merokok_keluarga' => '1', 'status_tk_paud' => 'TK B',
            'penyakit_penyerta' => 'Asma', 'pjb' => 'Tidak Ada', 'bbl' => '3.2', 'pbl' => '49.5',
            'lk_lahir' => '34.2', 'usia_kehamilan_lahir' => '39', 'tempat_bersalin' => 'RSUD',
            'jenis_persalinan' => 'SC', 'penolong_lahir' => 'Dokter Spesialis', 'imd' => '0',
            'riwayat_kek_ibu' => '1', 'komplikasi_persalinan' => 'Observasi ibu',
            'skrining_shk' => 'normal', 'skrining_shak' => 'tidak_normal', 'skrining_g6pd' => 'belum',
            'pemeriksaan_hepatitis_b' => 'reaktif', 'komplikasi_neonatal' => "Observasi bayi\nKontrol ulang",
        ];
    }

    private function layanan(): array
    {
        return [
            'kn1' => '1', 'kn3' => '0', 'mtbm' => '1', 'mtbs' => '0', 'pkat' => '1',
            'skrining_atresia_bilier' => '0', 'oralit_zinc' => '1', 'mbg' => '0', 'kelas_ibu_balita' => '1',
            'tgl_penanda_ckg' => '2025-02-10', 'pemeriksaan_gigi' => 'Masalah lain', 'rujukan' => 'Dokter gigi',
            'mt_pangan_lokal' => 'Bubur ikan', 'catatan_pengukuran' => 'Catatan swarm',
            'pemeriksaan_lainnya' => 'Pemeriksaan swarm', 'pola_makan' => 'Pola makan swarm',
            'pola_asuh' => 'Pola asuh swarm', 'intervensi' => 'Intervensi swarm',
        ];
    }

    private function kunjungan(Anak $anak, string $tanggal, array $extra = []): DataAnak
    {
        return DataAnak::create(array_merge([
            'id_anak' => $anak->id, 'tgl_kunjungan' => $tanggal, 'bln' => 1, 'posisi' => 'L',
            'bb' => 4.5, 'tb' => 55, 'lla' => 11, 'lk' => 38, 'id_user' => $this->admin->id,
        ], $extra));
    }

    private function workbook(array $filter = []): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'kesmas-swarm-');
        try {
            file_put_contents($path, Excel::raw(new KesmasExport($filter), \Maatwebsite\Excel\Excel::XLSX));
            // Binder export bersifat global; jangan terapkan aturan sheet terakhir saat membaca ulang XLSX.
            Cell::setValueBinder(new DefaultValueBinder);
            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }

    public function test_semua_field_anak_dapat_disimpan_dipertahankan_dan_dikosongkan_di_kedua_cabang_edit(): void
    {
        $this->actingAs($this->admin)->post(route('admin.storeAnak'), $this->dasar($this->kesmas()))
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();
        $anak = Anak::where('nik', '6474010101250010')->firstOrFail();
        $this->assertDatabaseHas('anak', array_merge(['id' => $anak->id], $this->kesmas()));

        // Both the unchanged-location and changed-location repository branches must honor omission.
        foreach ([false, true] as $kirimWilayah) {
            $payload = $this->dasar();
            if (!$kirimWilayah) {
                foreach (array_keys($this->wilayah) as $field) {
                    unset($payload[$field]);
                }
            }
            $this->put(route('admin.updateAnak', $anak->hashid), $payload)
                ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();
            $this->assertDatabaseHas('anak', array_merge(['id' => $anak->id], $this->kesmas()));

            $kosong = array_fill_keys(array_keys($this->kesmas()), '');
            $this->put(route('admin.updateAnak', $anak->hashid), array_merge($payload, $kosong))
                ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();
            $anak->refresh();
            foreach (array_keys($kosong) as $field) {
                $this->assertNull($anak->$field, "$field harus NULL sesudah pilihan dikosongkan");
            }
            $this->put(route('admin.updateAnak', $anak->hashid), array_merge($payload, $this->kesmas()))
                ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();
        }
    }

    public function test_berat_lahir_dua_desimal_yang_diterima_form_tetap_utuh_setelah_disimpan(): void
    {
        // The UI permits kg with step=0.01; the database must retain the submitted precision.
        $this->actingAs($this->admin)->post(route('admin.storeAnak'), $this->dasar(['bbl' => '2.49']))
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();
        $this->assertSame(2.49, (float) Anak::where('nik', '6474010101250010')->value('bbl'));
    }

    public function test_seluruh_checkbox_dan_keterangan_kunjungan_round_trip_tanpa_menimpa_kunjungan_lain(): void
    {
        $anak = $this->anak();
        $lain = $this->kunjungan($anak, '2025-03-10', ['kn1' => 1, 'catatan_pengukuran' => 'Jangan disentuh']);
        // Kunjungan 10 Feb 2025 pada anak lahir 10 Jan 2025 → BB dalam gram (spec 2026-10-02 §5.5).
        $payload = array_merge($this->dasar(), ['id_anak_hash' => $anak->hashid, 'bb' => '4500'], $this->layanan());
        $this->actingAs($this->admin)->post(route('admin.storeDataAnak'), $payload)
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();
        $data = DataAnak::where('id_anak', $anak->id)->whereDate('tgl_kunjungan', '2025-02-10')->firstOrFail();
        $this->assertDatabaseHas('data_anak', array_merge(['id' => $data->id], $this->layanan()));

        $tanpaKesmas = $payload;
        foreach (array_keys($this->layanan()) as $field) {
            unset($tanpaKesmas[$field]);
        }
        $this->put(route('admin.updateDataAnak', $data->id), $tanpaKesmas)
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('data_anak', array_merge(['id' => $data->id], $this->layanan()));

        $kosong = array_fill_keys(array_keys($this->layanan()), '');
        foreach (array_keys(config('kesmas.layanan')) as $field) {
            $kosong[$field] = '0'; // Real unchecked-checkbox payload, including MBG and KIB.
        }
        $this->put(route('admin.updateDataAnak', $data->id), array_merge($tanpaKesmas, $kosong))
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();
        $data->refresh();
        foreach ($kosong as $field => $value) {
            if ($value === '0') {
                $this->assertNotNull($data->$field, $field);
                $this->assertSame(0, (int) $data->$field, $field);
            } else {
                $this->assertNull($data->$field, $field);
            }
        }
        $this->assertDatabaseHas('data_anak', ['id' => $lain->id, 'kn1' => 1, 'catatan_pengukuran' => 'Jangan disentuh']);
    }

    public function test_invalid_kesmas_ditolak_sebelum_identitas_atau_kunjungan_diubah(): void
    {
        $anak = $this->anak($this->kesmas());
        $data = $this->kunjungan($anak, '2025-02-10', $this->layanan());
        $invalidAnak = [
            'no_id_epus' => str_repeat('x', 51), 'fktp_bpjs' => str_repeat('x', 101),
            'penyakit_penyerta' => str_repeat('x', 256), 'pjb' => str_repeat('x', 101),
            'tempat_bersalin' => str_repeat('x', 151), 'komplikasi_persalinan' => str_repeat('x', 256),
            'usia_kehamilan_lahir' => '19', 'bbl' => '-1', 'pbl' => 'abc', 'lk_lahir' => '-1',
            'air_bersih' => 'ya', 'jamban_sehat' => 'tidak', 'merokok_keluarga' => '2', 'imd' => 'yes', 'riwayat_kek_ibu' => 'no',
            'jenis_persalinan' => 'Tidak valid', 'status_tk_paud' => 'SMP',
            'skrining_shk' => 'positif', 'skrining_shak' => 'negatif', 'skrining_g6pd' => 'negatif',
            'pemeriksaan_hepatitis_b' => 'normal', 'penolong_lahir' => 'Tetangga',
        ];
        $this->actingAs($this->admin)->putJson(route('admin.updateAnak', $anak->hashid), $this->dasar(array_merge($invalidAnak, ['nama' => 'Tidak boleh tersimpan'])))
            ->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalidAnak));
        $this->assertSame('Swarm Anak 1', $anak->fresh()->nama);
        $this->assertDatabaseHas('anak', array_merge(['id' => $anak->id], $this->kesmas()));

        $invalidLayanan = array_fill_keys(array_keys(config('kesmas.layanan')), 'yes');
        $invalidLayanan += ['tgl_penanda_ckg' => '2025-02-30', 'pemeriksaan_gigi' => 'Sehat sekali', 'rujukan' => 'Tetangga', 'mt_pangan_lokal' => str_repeat('x', 101)];
        // 5000 g = perubahan BB yang SAH; tetap tak boleh tersimpan karena Kesmas tidak sah.
        $this->putJson(route('admin.updateDataAnak', $data->id), $this->dasar(array_merge($invalidLayanan, ['bb' => '5000'])))
            ->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalidLayanan));
        $this->assertSame(4.5, (float) $data->fresh()->bb);
        $this->assertDatabaseHas('data_anak', array_merge(['id' => $data->id], $this->layanan()));
    }

    public function test_nilai_penolong_legacy_hanya_diizinkan_untuk_anak_yang_memilikinya(): void
    {
        $lama = $this->anak(['penolong_lahir' => 'Dukun terlatih']);
        $lain = $this->anak();
        $payload = $this->dasar(['penolong_lahir' => 'Dukun terlatih']);
        $this->actingAs($this->admin)->put(route('admin.updateAnak', $lama->hashid), $payload)
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();
        $this->putJson(route('admin.updateAnak', $lain->hashid), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('penolong_lahir');
        $this->assertNull($lain->fresh()->penolong_lahir);
    }

    public function test_export_memadukan_semua_filter_wilayah_dan_tanggal_inklusif_hanya_pada_sheet_kunjungan(): void
    {
        $cocok = $this->anak(['nama' => 'A Cocok']);
        $tanpaKunjungan = $this->anak(['nama' => 'B Tanpa kunjungan']);
        foreach (['2025-01-31', '2025-02-01', '2025-02-28', '2025-03-01'] as $tanggal) {
            $this->kunjungan($cocok, $tanggal);
        }
        // A mismatch in any one dimension must exclude the child from both sheets.
        foreach (['id_kec', 'id_kel', 'id_puskesmas', 'id_posyandu'] as $field) {
            $lain = $this->anak([$field => null]);
            $this->kunjungan($lain, '2025-02-10');
        }
        $book = $this->workbook(array_merge($this->wilayah, ['dari' => '2025-02-01', 'sampai' => '2025-02-28']));
        try {
            $this->assertSame(['Per Anak', 'Per Kunjungan'], $book->getSheetNames());
            $anakSheet = $book->getSheetByName('Per Anak');
            $kunjSheet = $book->getSheetByName('Per Kunjungan');
            $this->assertSame(3, $anakSheet->getHighestDataRow());
            $this->assertSame($cocok->nik, $anakSheet->getCell('A2')->getValue());
            $this->assertSame($tanpaKunjungan->nik, $anakSheet->getCell('A3')->getValue());
            $this->assertSame(3, $kunjSheet->getHighestDataRow());
            $this->assertSame('2025-02-01', $kunjSheet->getCell('C2')->getValue());
            $this->assertSame('2025-02-28', $kunjSheet->getCell('C3')->getValue());
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_export_semua_layanan_dan_label_skrining_sesuai_nilai_aslinya(): void
    {
        $anak = $this->anak(array_merge($this->kesmas(), ['merokok_keluarga' => null]));
        $this->kunjungan($anak, '2025-02-10', array_merge($this->layanan(), ['mtbs' => null]));
        $book = $this->workbook();
        try {
            $a = $book->getSheetByName('Per Anak');
            foreach (['L2' => 'Ya', 'M2' => 'Tidak', 'N2' => '', 'Y2' => 'Tidak', 'Z2' => 'Ya', 'AA2' => 'Normal', 'AB2' => 'Tidak normal', 'AC2' => 'Belum', 'AD2' => 'Reaktif'] as $cell => $value) {
                $this->assertSame($value, (string) $a->getCell($cell)->getValue(), $cell);
            }
            $k = $book->getSheetByName('Per Kunjungan');
            foreach (['H2' => 'Ya', 'I2' => 'Tidak', 'J2' => 'Ya', 'K2' => '', 'L2' => 'Ya', 'M2' => 'Tidak', 'N2' => 'Ya', 'O2' => 'Tidak', 'P2' => 'Ya', 'Q2' => 'Masalah lain', 'R2' => 'Dokter gigi', 'S2' => 'Bubur ikan', 'T2' => 'Catatan swarm', 'U2' => 'Pemeriksaan swarm', 'V2' => 'Pola makan swarm', 'W2' => 'Pola asuh swarm', 'X2' => 'Intervensi swarm'] as $cell => $value) {
                $this->assertSame($value, (string) $k->getCell($cell)->getValue(), $cell);
            }
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_export_usia_nol_bulan_neonatus_tetap_angka_nol_bukan_sel_kosong(): void
    {
        $this->kunjungan($this->anak(), '2025-01-12', ['bln' => 0]);
        $book = $this->workbook();
        try {
            $usia = $book->getSheetByName('Per Kunjungan')->getCell('D2');
            $this->assertSame(0, $usia->getValue(), 'Usia neonatus 0 bulan tidak boleh dianggap NULL');
            $this->assertSame(DataType::TYPE_NUMERIC, $usia->getDataType());
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_export_teks_per_anak_yang_mirip_formula_tetap_teks(): void
    {
        $this->anak(['no_id_epus' => '=1+1']);
        $book = $this->workbook();
        try {
            $cell = $book->getSheetByName('Per Anak')->getCell('J2');
            $this->assertSame('=1+1', $cell->getValue());
            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType(), 'Teks ePus tidak boleh dieksekusi sebagai formula');
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_export_catatan_kunjungan_yang_mirip_formula_tetap_teks(): void
    {
        $this->kunjungan($this->anak(), '2025-02-10', ['catatan_pengukuran' => '=1+1']);
        $book = $this->workbook();
        try {
            $cell = $book->getSheetByName('Per Kunjungan')->getCell('T2');
            $this->assertSame('=1+1', $cell->getValue());
            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType(), 'Catatan tidak boleh dieksekusi sebagai formula');
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_export_mempertahankan_identitas_angka_sebagai_teks_dan_pengukuran_sebagai_angka(): void
    {
        $anak = $this->anak(['no_id_epus' => '000123', 'bbl' => 2.49, 'pbl' => 0]);
        $this->kunjungan($anak, '2025-02-10', ['bln' => 0, 'bb' => 4.5, 'tb' => 55]);
        $book = $this->workbook();
        try {
            $perAnak = $book->getSheetByName('Per Anak');
            $this->assertSame('000123', $perAnak->getCell('J2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $perAnak->getCell('J2')->getDataType());
            foreach (['R2' => 2.49, 'S2' => 0] as $alamat => $angka) {
                $this->assertEquals($angka, $perAnak->getCell($alamat)->getValue());
                $this->assertSame(DataType::TYPE_NUMERIC, $perAnak->getCell($alamat)->getDataType());
            }
            $perKunjungan = $book->getSheetByName('Per Kunjungan');
            foreach (['D2' => 0, 'E2' => 4.5, 'F2' => 55] as $alamat => $angka) {
                $this->assertEquals($angka, $perKunjungan->getCell($alamat)->getValue());
                $this->assertSame(DataType::TYPE_NUMERIC, $perKunjungan->getCell($alamat)->getDataType());
            }
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_form_export_memulihkan_query_termasuk_filter_tanpa_induk_dan_tanggal(): void
    {
        $semua = array_diff_key($this->wilayah, ['id_rt' => true]) + ['dari' => '2025-01-01', 'sampai' => '2025-02-10'];
        foreach ([$semua, ['id_kel' => $this->wilayah['id_kel']], ['id_posyandu' => $this->wilayah['id_posyandu']]] as $filter) {
            $response = $this->actingAs($this->admin)->get(route('admin.export.kesmas.index', $filter))->assertOk();
            $dom = new \DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);
            foreach ($filter as $key => $value) {
                $path = in_array($key, ['dari', 'sampai'], true)
                    ? '//input[@name="' . $key . '"]/@value'
                    : '//select[@name="' . $key . '"]/option[@selected]/@value';
                $this->assertSame((string) $value, $xpath->evaluate('string(' . $path . ')'), $key);
            }
        }
    }

    public function test_form_export_memvalidasi_query_seperti_endpoint_unduhan(): void
    {
        $this->actingAs($this->admin);
        foreach (['admin.export.kesmas.index', 'admin.export.kesmas.download'] as $route) {
            $this->getJson(route($route, ['id_kel' => ['invalid'], 'id_puskesmas' => 999999]))
                ->assertUnprocessable()->assertJsonValidationErrors(['id_kel', 'id_puskesmas']);
        }
    }

    public function test_download_menerima_batas_tanggal_tunggal_dan_hari_yang_sama(): void
    {
        Excel::fake();
        foreach ([['dari' => '', 'sampai' => '2025-02-10'], ['dari' => '2025-02-10', 'sampai' => ''], ['dari' => '2025-02-10', 'sampai' => '2025-02-10']] as $filter) {
            $this->actingAs($this->admin)->get(route('admin.export.kesmas.download', $filter))
                ->assertOk()->assertSessionDoesntHaveErrors();
        }
    }

    public function test_akses_export_tamu_surveilans_dan_petugas_imunisasi_sesuai_spec(): void
    {
        foreach (['admin.export.kesmas.index', 'admin.export.kesmas.download'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        foreach (['surveilans_puskesmas', 'surveilans_rs'] as $role) {
            $user = User::factory()->create(['type' => 1, 'role' => $role]);
            foreach (['admin.export.kesmas.index', 'admin.export.kesmas.download'] as $route) {
                $this->actingAs($user)->get(route($route))->assertForbidden();
            }
        }
        // City-wide access for imunisasi_faskes is an explicit spec decision.
        Excel::fake();
        $user = User::factory()->create(['type' => 1, 'role' => 'imunisasi_faskes', 'id_puskesmas' => $this->wilayah['id_puskesmas']]);
        $this->actingAs($user)->get(route('admin.export.kesmas.index'))->assertOk();
        $this->get(route('admin.export.kesmas.download'))->assertOk();
    }

    public function test_detail_mengescape_teks_kesmas_dan_atribut_catatan_kunjungan(): void
    {
        $teks = '<img src=x onerror="alert(1)">';
        $anak = $this->anak(['penyakit_penyerta' => $teks, 'komplikasi_neonatal' => $teks]);
        $this->kunjungan($anak, '2025-02-10', ['catatan_pengukuran' => $teks, 'kn1' => 1]);
        $response = $this->actingAs($this->admin)->get(route('admin.showAnak', $anak->hashid))->assertOk();
        $response->assertDontSee($teks, false)->assertSee(e($teks), false);
        $this->assertStringContainsString('title="Catatan: ' . e($teks) . '"', $response->getContent());
    }
}
