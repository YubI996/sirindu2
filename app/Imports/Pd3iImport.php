<?php

namespace App\Imports;

use App\Models\SurveillanceCase;
use App\Models\JenisKasusEpidemiologi;
use App\Services\NikDummyService;
use App\Traits\CleansImportData;
use App\Traits\ResolvesWilayah;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Import data PD3I dari file Excel resmi (pd3i.xlsx).
 *
 * Struktur kolom berdasarkan: docs/Modul Import/pd3i.xlsx
 * Data dimulai dari baris ke-3 (baris 1-2 adalah header ganda).
 * Upsert menggunakan no_registrasi (kolom [0]) sebagai kunci.
 */
class Pd3iImport implements ToCollection, WithStartRow, WithChunkReading
{
    use ResolvesWilayah, CleansImportData;

    protected int $userId;
    protected int $successCount = 0;
    protected int $errorCount   = 0;   // hanya baris yang benar-benar gagal disimpan
    protected array $failures = [];    // semua pesan: error + peringatan + info (untuk tampilan log)

    /** Offset baris absolut untuk pelaporan nomor baris yang akurat lintas chunk */
    protected int $rowOffset = 0;

    /** Cache jenis kasus epidemiologi */
    protected array $jenisKasusCache = [];

    protected NikDummyService $nikService;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->nikService = new NikDummyService();

        // Pra-muat cache wilayah (via trait ResolvesWilayah)
        $this->initWilayahCache();

        $this->jenisKasusCache = JenisKasusEpidemiologi::pluck('id', 'nama_penyakit')->mapWithKeys(function ($id, $nama) {
            return [strtoupper($nama) => $id];
        })->toArray();
    }

    /**
     * Dapatkan atau buat JenisKasusEpidemiologi dari nama. Update cache setelah dibuat.
     */
    protected function resolveJenisKasus(string $name): ?int
    {
        $key = strtoupper(trim($name));
        if (empty($key)) return null;

        if (!isset($this->jenisKasusCache[$key])) {
            $jenis = JenisKasusEpidemiologi::firstOrCreate(
                ['nama_penyakit' => trim($name)],
                ['is_active' => true]
            );
            $this->jenisKasusCache[$key] = $jenis->id;
            Log::info("Import PD3I: auto-create JenisKasus '{$name}' → id={$jenis->id}");
        }

        return $this->jenisKasusCache[$key];
    }

    /** Data dimulai dari baris ke-2 (baris 1 = header, baris 2 = data pertama) */
    public function startRow(): int
    {
        return 2;
    }

    /** Chunk 500 baris untuk efisiensi memori */
    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * Proses satu chunk baris dari Excel.
     */
    public function collection(Collection $rows)
    {
        // Cache lokal hanya untuk referensi cepat dalam closure — resolver ada di method class

        // --- T003: US3 — Deteksi file kosong ---
        $nonEmptyRows = $rows->filter(function ($row) {
            return !empty($row[0]) || !empty($row[9]);
        });

        if ($nonEmptyRows->isEmpty()) {
            $this->failures[] = 'File tidak mengandung data (semua baris kosong atau hanya header).';
            return;
        }

        // =====================================================================
        // T005: Helper calcKategoriUmur
        // Hitung dari tanggal_lahir dan tanggal_onset
        // Enum: ['bayi','balita','anak','remaja','dewasa','lansia']
        // =====================================================================
        $calcKategoriUmur = function ($tanggalLahir, $tanggalOnset): ?string {
            $lahirStr  = $this->parseDate($tanggalLahir);
            $onsetStr  = $this->parseDate($tanggalOnset);
            if (!$lahirStr) return null;

            try {
                $lahir  = Carbon::parse($lahirStr);
                $acuan  = $onsetStr ? Carbon::parse($onsetStr) : Carbon::now();
                $bulan  = $lahir->diffInMonths($acuan);

                return match (true) {
                    $bulan < 12  => 'bayi',
                    $bulan < 60  => 'balita',
                    $bulan < 144 => 'anak',
                    $bulan < 216 => 'remaja',
                    $bulan < 720 => 'dewasa',
                    default      => 'lansia',
                };
            } catch (\Exception $e) {
                return null;
            }
        };

        // =====================================================================
        // Proses baris demi baris
        // =====================================================================
        $chunkSize = count($rows);

        foreach ($rows as $index => $row) {
            // Skip baris yang no_registrasi DAN nama_lengkap kosong
            if (empty($row[0]) && empty($row[9])) {
                continue;
            }

            // rowNum akurat lintas chunk: offset absolut + posisi dalam chunk + startRow
            $rowNum = $this->rowOffset + $index + $this->startRow();

            // T039: Cek nama_lengkap kosong secara eksplisit sebelum DB
            if (empty($row[9])) {
                $noReg = $row[0] ?? '(kosong)';
                $this->failures[] = "[ERROR] Baris {$rowNum} (No. Reg: {$noReg}): Nama pasien wajib diisi.";
                $this->errorCount++;
                Log::warning("Import PD3I skip baris {$rowNum}: nama_lengkap kosong.");
                continue;
            }

            try {
                // Resolve wilayah — auto-create jika belum ada di DB
                $idKec        = $this->resolveKecamatan((string) ($row[18] ?? ''));
                $idKel        = $this->resolveKelurahan((string) ($row[19] ?? ''), $idKec);
                $idRt         = $this->resolveRt((string) ($row[20] ?? ''), $idKel);

                // Resolve jenis kasus — auto-create jika belum ada di DB
                $idJenisKasus = $this->resolveJenisKasus((string) ($row[23] ?? ''));

                // Kunci upsert
                $noReg = !empty($row[0]) ? (string) $row[0] : ('TEMP-' . uniqid());
                if (empty($row[0])) {
                    $this->failures[] = "[PERINGATAN] Baris {$rowNum}: No. registrasi kosong — data disimpan dengan ID sementara '{$noReg}', tidak bisa di-update ulang.";
                }

                // Bangun nilai untuk kolom tanggal yang wajib NOT NULL
                // Kolom [2] Timestamp sering kosong → fallback ke tanggal_terima_laporan [6]
                $tglLapor      = $this->parseDate($row[2]  ?? null)
                              ?? $this->parseDate($row[6]  ?? null)   // Tanggal Terima Laporan
                              ?? $this->parseDate($row[22] ?? null)   // Tanggal Onset
                              ?? Carbon::today()->format('Y-m-d');

                $tglOnset      = $this->parseDate($row[22] ?? null)
                              ?? $tglLapor;

                $tglKonsultasi = $this->parseDate($row[80] ?? null)   // Tanggal Kunjungan RS
                              ?? $this->parseDate($row[82] ?? null)   // Tanggal Kunjungan FKTP
                              ?? $tglLapor;

                // Estimasi tanggal_lahir dari kolom umur jika kosong
                $tglLahirIsEstimated = false;
                if (empty($row[11])) {
                    // Prioritas: Umur(Bulan) [187] → AGEYEAR [189] + AGEMONTH [190]
                    $totalBulan = is_numeric($row[187] ?? null) ? (int) $row[187] : null;
                    if ($totalBulan === null) {
                        $ageYear = is_numeric($row[189] ?? null) ? (int) $row[189] : null;
                        $ageMon  = is_numeric($row[190] ?? null) ? (int) $row[190] : null;
                        if ($ageYear !== null || $ageMon !== null) {
                            $totalBulan = (($ageYear ?? 0) * 12) + ($ageMon ?? 0);
                        }
                    }

                    $tglPenyidikan = $this->parseDate($row[7] ?? null);
                    if ($totalBulan !== null && $totalBulan > 0 && $tglPenyidikan) {
                        try {
                            $row[11] = Carbon::parse($tglPenyidikan)
                                ->subMonths($totalBulan)
                                ->startOfMonth()
                                ->format('Y-m-d');
                            $tglLahirIsEstimated = true;
                        } catch (\Exception $e) {
                            // proceed, row[11] stays empty
                        }
                    }
                }

                $noRegWarn = $row[0] ?? '(kosong)';
                if (empty($row[11])) {
                    $this->failures[] = "[PERINGATAN] Baris {$rowNum} (No. Reg: {$noRegWarn}): Tanggal lahir tidak diisi dan data umur tidak tersedia — kategori_umur tidak dapat dihitung (disimpan null).";
                } elseif ($tglLahirIsEstimated) {
                    $this->failures[] = "[INFO] Baris {$rowNum} (No. Reg: {$noRegWarn}): Tanggal lahir kosong — diestimasi dari umur ({$totalBulan} bulan) menjadi {$row[11]} (perkiraan awal bulan).";
                }

                $namaFaskes       = !empty($row[79]) ? (string) $row[79]  // nama_rs
                                 : (!empty($row[81]) ? (string) $row[81]  // nama_fktp
                                 : (!empty($row[4])  ? (string) $row[4]   // instansi_pelapor
                                 : 'Tidak Diketahui'));

                $statusRawat      = !empty($row[79]) ? 'rawat_inap' : 'rawat_jalan';

                SurveillanceCase::updateOrCreate(
                    // Kunci upsert
                    ['no_registrasi' => $noReg],

                    [
                        // =================================================
                        // GRUP A: Identitas Pasien
                        // =================================================
                        'nik'                    => (function() use ($row) {
                            $rawNik = $this->cleanNikRaw($row[8] ?? '');
                            if ($rawNik !== '' && strlen($rawNik) >= 15) {
                                return substr($rawNik, 0, 16);
                            }
                            // Generate atau temukan NIK dummy
                            $nama    = trim((string) ($row[9] ?? ''));
                            $tglLhir = $this->parseDate($row[11] ?? null) ?? date('Y-m-d');
                            $jk      = $this->parseGenderString($row[10] ?? null) ?? 'L';
                            $nik = $this->nikService->findExisting($nama, $tglLhir, $jk)
                                ?? $this->nikService->generate(NikDummyService::DEFAULT_KODE_WILAYAH, $tglLhir, $jk);
                            $this->failures[] = "[INFO] NIK kosong untuk {$nama} — NIK dummy {$nik} di-generate.";
                            return $nik;
                        })(),
                        'nama_lengkap'           => $row[9]  ?? null,
                        'jenis_kelamin'          => $this->parseGenderString($row[10] ?? null),
                        'tanggal_lahir'          => $this->parseDate($row[11] ?? null),
                        'kategori_umur'          => $calcKategoriUmur($row[11] ?? null, $row[22] ?? null),
                        'tempat_kerja_sekolah'   => $row[12] ?? null,
                        'nama_orang_tua'         => $row[13] ?? null,
                        'no_hp_orang_tua'        => $row[14] ?? null,
                        'alamat_lengkap'         => !empty($row[15]) ? (string) $row[15] : 'Tidak Diketahui',
                        'provinsi'               => $row[16] ?? null,
                        'kab_kota'               => $row[17] ?? null,
                        'id_kec'                 => $idKec,
                        'id_kel'                 => $idKel,
                        'id_rt'                  => $idRt,

                        // =================================================
                        // GRUP B: Identitas Pelapor
                        // =================================================
                        'tanggal_lapor'          => $tglLapor,
                        'nama_pelapor'           => !empty($row[3]) ? (string) $row[3] : 'Tidak Diketahui',
                        'instansi_pelapor'       => $row[4]  ?? null,
                        'wilker_puskesmas'       => $row[5]  ?? null,
                        'tanggal_terima_laporan' => $this->parseDate($row[6]  ?? null),
                        'tanggal_penyidikan'     => $this->parseDate($row[7]  ?? null),

                        // =================================================
                        // GRUP C: Data Kasus
                        // =================================================
                        'tanggal_demam'          => $this->parseDate($row[21] ?? null),
                        'tanggal_onset'          => $tglOnset,
                        'tanggal_konsultasi'     => $tglKonsultasi,               // wajib NOT NULL — fallback ke tanggal_lapor/onset
                        'id_jenis_kasus'         => $idJenisKasus,
                        'status_kasus'           => 'suspected',
                        'status_rawat'           => $statusRawat,                 // wajib NOT NULL — derived dari nama_rs
                        'nama_faskes_rawat'      => $namaFaskes,                  // wajib NOT NULL — chain fallback
                        'id_petugas_input'       => $this->userId,                // wajib NOT NULL — user yang upload

                        // =================================================
                        // GRUP D: Gejala Klinis
                        // =================================================
                        'gejala_demam'           => !empty($row[21]),             // T015: dari kehadiran tanggal_demam
                        // T016: gejala_ruam dihapus (tidak ada kolom di Excel)
                        'gejala_batuk'           => $this->parseBoolean($row[24] ?? '') ?? false, // T012: fix [26]→[24]
                        'gejala_pilek'           => $this->parseBoolean($row[25] ?? '') ?? false, // T013: fix [27]→[25]
                        'gejala_mata_merah'      => $this->parseBoolean($row[26] ?? '') ?? false, // T014: fix [28]→[26]
                        'gejala_adenopathy'      => $this->parseBoolean($row[27] ?? '') ?? false, // T023
                        'gejala_arthralgia'      => $this->parseBoolean($row[28] ?? '') ?? false, // T023
                        'gejala_kehamilan'       => $this->parseBoolean($row[29] ?? '') ?? false, // T023
                        'gejala_lainnya'         => $row[42] ?? null,             // T023

                        // Tanggal gejala lanjutan (T024)
                        'tanggal_leher_bengkak'  => $this->parseDate($row[43] ?? null),
                        'tanggal_sesak_nafas'    => $this->parseDate($row[44] ?? null),
                        'tanggal_pseudomembran'  => $this->parseDate($row[45] ?? null),
                        'tanggal_apnea'          => $this->parseDate($row[68] ?? null),

                        // =================================================
                        // GRUP D2: Komplikasi (T025)
                        // =================================================
                        'komplikasi_diare'              => $this->parseBoolean($row[30] ?? '') ?? false,
                        'komplikasi_kebutaan'           => $this->parseBoolean($row[31] ?? '') ?? false,
                        'komplikasi_pneumonia'          => $this->parseBoolean($row[32] ?? '') ?? false,
                        'komplikasi_malnutrisi'         => $this->parseBoolean($row[33] ?? '') ?? false,
                        'komplikasi_bronchopneumonia'   => $this->parseBoolean($row[34] ?? '') ?? false,
                        'komplikasi_otitis_media'       => $this->parseBoolean($row[35] ?? '') ?? false,
                        'komplikasi_encephalitis'       => $this->parseBoolean($row[36] ?? '') ?? false,
                        'komplikasi_ulkus_mukosa_mulut' => $this->parseBoolean($row[37] ?? '') ?? false,

                        // =================================================
                        // GRUP D3-D4: Gizi & Pengobatan (T026)
                        // =================================================
                        'vitamin_a'              => $this->parseVitaminA($row[38] ?? null),
                        'berat_badan'            => is_numeric($row[40] ?? null) ? (float) $row[40] : null,
                        'tinggi_badan'           => is_numeric($row[41] ?? null) ? (float) $row[41] : null,
                        'jenis_antibiotik'       => $row[46] ?? null,
                        'dosis_ads'              => $row[47] ?? null,
                        'obat_lainnya'           => $row[48] ?? null,

                        // =================================================
                        // GRUP D5: AFP/Polio (T027)
                        // Enum: ['ya', 'tidak']
                        // =================================================
                        'kelumpuhan_akut'        => $this->parseYaTidak($row[49] ?? null),
                        'kelumpuhan_flaccid'     => $this->parseYaTidak($row[50] ?? null),
                        'kelumpuhan_rudapaksa'   => $this->parseYaTidak($row[51] ?? null),

                        // =================================================
                        // GRUP D6: Diagnosis & Pemeriksaan Fisik (T017, T028)
                        // =================================================
                        'diagnosis'              => $row[52] ?? null,             // T017: fix [121]→[52]
                        'tanda_tungkai_kanan'    => $row[53] ?? null,
                        'tanda_tungkai_kiri'     => $row[54] ?? null,
                        'tanda_lengan_kanan'     => $row[55] ?? null,
                        'tanda_lengan_kiri'      => $row[56] ?? null,
                        'kekuatan_otot'          => $this->parseIntOrNull($row[57] ?? null),
                        'lokasi_kelemahan_lain'  => $row[58] ?? null,
                        'tanda_penyakit_observasi' => $row[96] ?? null,

                        // =================================================
                        // GRUP D7: Kontak Polio (T029)
                        // Enum: ['ya', 'tidak', 'tidak_tahu']
                        // =================================================
                        'kontak_polio_oral'      => $this->parseYaTidak($row[59] ?? null),

                        // =================================================
                        // GRUP D8: Sanitasi (T029)
                        // =================================================
                        'jamban_sendiri'         => $this->parseYaTidak($row[60] ?? null),
                        'jamban_saluran_kedap'   => $this->parseYaTidak($row[61] ?? null),
                        'jenis_jamban'           => $row[62] ?? null,
                        'selalu_gunakan_jamban'  => $this->parseYaTidak($row[63] ?? null),
                        'pembuangan_diapers'     => $row[64] ?? null,

                        // =================================================
                        // GRUP D9: Dokter (T030)
                        // =================================================
                        'nama_dokter'            => $row[65] ?? null,
                        'no_telp_dokter'         => $row[66] ?? null,
                        'diagnosis_dokter'       => $row[67] ?? null,

                        // =================================================
                        // GRUP E: Riwayat Imunisasi (T031)
                        // =================================================
                        'riwayat_imunisasi'              => $this->parseRiwayatImunisasi($row[39] ?? null),
                        'imunisasi_1'                    => $row[70] ?? null,
                        'imunisasi_2'                    => $row[71] ?? null,
                        'imunisasi_3'                    => $row[72] ?? null,
                        'imunisasi_4'                    => $row[73] ?? null,
                        'imunisasi_5'                    => $row[74] ?? null,
                        'sumber_informasi_imunisasi'     => $row[75] ?? null,
                        'alasan_imunisasi_tidak_lengkap' => $row[76] ?? null,

                        // =================================================
                        // GRUP G: Status Gizi & Tempat Berobat (T032)
                        // =================================================
                        'status_gizi'                    => $this->parseStatusGizi($row[77] ?? null),
                        'tempat_berobat'                 => $row[78] ?? null,
                        'nama_rs'                        => $row[79] ?? null,
                        'tanggal_kunjungan_rs'           => $this->parseDate($row[80] ?? null),
                        'nama_fktp'                      => $row[81] ?? null,
                        'tanggal_kunjungan_fktp'         => $this->parseDate($row[82] ?? null),
                        'nama_pengobatan_tradisional'    => $row[83] ?? null,
                        'tanggal_kunjungan_tradisional'  => $this->parseDate($row[84] ?? null),

                        // =================================================
                        // GRUP F: Laboratorium (T033)
                        // =================================================
                        'jenis_spesimen'              => $row[85] ?? null,
                        'tanggal_pengambilan_spesimen' => $this->parseDate($row[86] ?? null),
                        'jenis_spesimen_2'            => $row[87] ?? null,
                        'tanggal_spesimen_2'          => $this->parseDate($row[88] ?? null),
                        'jenis_spesimen_3'            => $row[89] ?? null,
                        'tanggal_spesimen_3'          => $this->parseDate($row[90] ?? null),

                        // =================================================
                        // GRUP I: Kontak & Perjalanan (T034)
                        // =================================================
                        'keluarga_sakit_sama'    => $this->parseYaTidak($row[91] ?? null),
                        'jumlah_keluarga_sakit'  => $this->parseIntOrNull($row[92] ?? null),
                        'riwayat_bepergian'      => $this->parseYaTidak($row[93] ?? null),
                        'lokasi_bepergian'       => $row[94] ?? null,
                        'tanggal_bepergian'      => $this->parseDate($row[95] ?? null),

                        // =================================================
                        // GRUP TN: Tetanus Neonatorum (T035)
                        // =================================================
                        'lama_tinggal_desa'      => $row[97]  ?? null,
                        'bayi_lahir_hidup'       => $this->parseYaTidak($row[98]  ?? null),
                        'umur_bayi_meninggal_hari' => $this->parseIntOrNull($row[99] ?? null),
                        'bayi_menangis_lahir'    => $this->parseYaTidak($row[100] ?? null),
                        'tanda_kelahiran_hidup'  => $this->parseYaTidak($row[102] ?? null),
                        'bayi_bisa_menyusu'      => $this->parseYaTidak($row[103] ?? null),
                        'bayi_mulut_mencucu'     => $this->parseYaTidak($row[104] ?? null),
                        'bayi_mudah_kejang'      => $this->parseYaTidak($row[105] ?? null),
                        'jumlah_kunjungan_anc'   => $this->parseIntOrNull($row[106] ?? null),
                        'tempat_pemeriksaan_hamil' => $row[107] ?? null,
                        'pemeriksa_kehamilan'    => $row[108] ?? null,
                        'tempat_persalinan'      => $row[109] ?? null,
                        'usia_kehamilan_bulan'   => $this->parseIntOrNull($row[110] ?? null),
                        'penolong_persalinan'    => $row[111] ?? null,
                        'alat_potong_tali_pusat' => $row[112] ?? null,
                        'perawatan_tali_pusat'   => $row[113] ?? null,
                        'keadaan_ibu_saat_ini'   => $row[114] ?? null,

                        // =================================================
                        // GRUP J: Audit (diisi sistem)
                        // =================================================
                        'created_by'             => $this->userId,
                        'updated_by'             => $this->userId,
                    ]
                );

                $this->successCount++;

            } catch (\Exception $e) {
                // T037: Pesan error yang informatif untuk petugas
                $noReg  = $row[0] ?? '(kosong)';
                $nama   = $row[9] ?? '(tidak ada nama)';
                $errMsg = $this->simplifyError($e->getMessage());
                $this->failures[] = "[ERROR] Baris {$rowNum} (No. Reg: {$noReg}, Nama: {$nama}): {$errMsg}";
                $this->errorCount++;
                Log::warning("Import PD3I skip baris {$rowNum} [{$noReg}]: " . $e->getMessage());
            }
        }

        // Naikkan offset agar chunk berikutnya melaporkan nomor baris yang benar
        $this->rowOffset += $chunkSize;
    }

    /**
     * Kembalikan ringkasan hasil import.
     *
     * @return array{success: int, failures: string[]}
     */
    public function getResults(): array
    {
        return [
            'success'      => $this->successCount,
            'error_count'  => $this->errorCount,
            'failures'     => $this->failures,
        ];
    }
}
