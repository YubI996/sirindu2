<?php

namespace App\Imports;

use App\Models\SurveillanceCase;
use App\Models\JenisKasusEpidemiologi;
use App\Services\NikDummyService;
use App\Traits\ResolvesWilayah;
use App\Traits\ResolvesRumahSakit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;
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
    use ResolvesWilayah, ResolvesRumahSakit;

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

        // Pra-muat cache master RS (via trait ResolvesRumahSakit) untuk atribusi faskes
        $this->initRumahSakitCache();

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
            $kode = trim(preg_replace('/[^A-Z0-9]+/', '_', $key), '_');

            try {
                $jenis = JenisKasusEpidemiologi::firstOrCreate(
                    ['nama_penyakit' => trim($name)],
                    [
                        'kode_penyakit' => $kode,
                        'is_active'     => true,
                    ]
                );
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                $jenis = JenisKasusEpidemiologi::where('kode_penyakit', $kode)->firstOrFail();
            }

            $this->jenisKasusCache[$key] = $jenis->id;
        }

        return $this->jenisKasusCache[$key];
    }

    /** Nama penyakit yang dikenali pada kolom "Klasifikasi Akhir". */
    protected const KLASIFIKASI_PENYAKIT = ['Campak', 'Rubella', 'Polio', 'Difteri', 'Pertusis'];

    /**
     * Petakan nilai kolom "Klasifikasi Akhir" (kolom terakhir file impor) menjadi
     * status_kasus + nama penyakit terkonfirmasi.
     *
     * - Nama penyakit (Campak/Rubella/dll) → confirmed + penyakit tsb
     * - "Discarded"/"Disingkirkan"/"Bukan Kasus" → discarded
     * - kosong, "#N/A", atau nilai tak dikenal → status null ("tidak ada info")
     *
     * status null = JANGAN ubah status_kasus yang sudah ada saat re-import;
     * pemanggil yang memutuskan default 'suspected' hanya untuk record baru.
     *
     * @return array{status: ?string, penyakit: ?string}
     */
    protected static function resolveKlasifikasi(?string $value): array
    {
        $val = trim((string) $value);
        if ($val === '' || strtoupper($val) === '#N/A') {
            return ['status' => null, 'penyakit' => null];
        }

        $lower = strtolower($val);

        if (in_array($lower, ['discarded', 'disingkirkan', 'bukan kasus'])) {
            return ['status' => 'discarded', 'penyakit' => null];
        }

        foreach (self::KLASIFIKASI_PENYAKIT as $penyakit) {
            if (str_contains($lower, strtolower($penyakit))) {
                return ['status' => 'confirmed', 'penyakit' => $penyakit];
            }
        }

        // Nilai tak dikenal → jangan asal confirmed / jangan timpa status lama.
        return ['status' => null, 'penyakit' => null];
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
        // T002: Helper parseDate
        // Menangani: numeric Excel date, string tanggal, atau kosong → null
        // =====================================================================
        $parseDate = function ($value) {
            if ($value === null || $value === '') return null;
            if (is_numeric($value)) {
                try {
                    return Carbon::instance(Date::excelToDateTimeObject((float) $value))->format('Y-m-d');
                } catch (\Exception $e) {
                    return null;
                }
            }
            try {
                return Carbon::parse((string) $value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        };

        // =====================================================================
        // T003: Helper parseBoolean
        // "Ya"/"y"/"yes"/"true"/"1" → true; selain itu → false
        // =====================================================================
        $parseBoolean = function ($value): bool {
            return in_array(strtolower(trim((string) $value)), ['ya', 'y', 'yes', 'true', '1']);
        };

        // =====================================================================
        // T004: Helper parseIntOrNull
        // intval, return null jika 0 atau kosong
        // =====================================================================
        $parseIntOrNull = function ($value): ?int {
            if ($value === null || $value === '') return null;
            $int = intval($value);
            return $int > 0 ? $int : null;
        };

        // =====================================================================
        // T005: Helper calcKategoriUmur
        // Hitung dari tanggal_lahir dan tanggal_onset
        // Enum: ['bayi','balita','anak','remaja','dewasa','lansia']
        // =====================================================================
        $calcKategoriUmur = function ($tanggalLahir, $tanggalOnset) use ($parseDate): ?string {
            $lahirStr  = $parseDate($tanggalLahir);
            $onsetStr  = $parseDate($tanggalOnset);
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
        // Helper parseYaTidak
        // Untuk field enum ['ya', 'tidak'] atau ['ya', 'tidak', 'tidak_tahu']
        // Mengonversi "Ya"/"Tidak"/"Tidak Tahu" → lowercase enum value atau null
        // =====================================================================
        $parseYaTidak = function ($value): ?string {
            $val = strtolower(trim((string) $value));
            return match (true) {
                in_array($val, ['ya', 'y', 'yes', '1'])                 => 'ya',
                in_array($val, ['tidak', 'no', 'n', '0'])               => 'tidak',
                in_array($val, ['tidak tahu', 'tidak_tahu', 'unknown']) => 'tidak_tahu',
                in_array($val, ['kadang', 'kadang-kadang', 'kadang_kadang']) => 'kadang_kadang',
                default => null,
            };
        };

        // =====================================================================
        // Helper parseRiwayatImunisasi
        // Enum: ['lengkap', 'tidak_lengkap', 'tidak_tahu', 'tidak_ada']
        // =====================================================================
        $parseRiwayatImunisasi = function ($value): ?string {
            if ($value === null || trim((string) $value) === '') return null;
            $val = strtolower(trim((string) $value));
            // Urutan: spesifik dulu (tidak_lengkap, tidak_ada) sebelum yang umum (lengkap)
            return match (true) {
                str_contains($val, 'tidak_lengkap') || str_contains($val, 'tidak lengkap') => 'tidak_lengkap',
                str_contains($val, 'tidak_ada')     || str_contains($val, 'tidak ada') || $val === 'tidak' => 'tidak_ada',
                str_contains($val, 'tidak_tahu')    || str_contains($val, 'tidak tahu') => 'tidak_tahu',
                str_contains($val, 'lengkap') => 'lengkap',
                default => null,
            };
        };

        // =====================================================================
        // Helper parseStatusGizi
        // Enum: ['baik', 'kurang', 'buruk', 'lebih']
        // =====================================================================
        $parseStatusGizi = function ($value): ?string {
            if (empty($value)) return null;
            $val = strtolower(trim((string) $value));
            return match (true) {
                in_array($val, ['baik', 'normal', 'gizi baik'])   => 'baik',
                str_contains($val, 'kurang')                       => 'kurang',
                str_contains($val, 'buruk')                        => 'buruk',
                in_array($val, ['lebih', 'obesitas', 'gemuk'])     => 'lebih',
                default => null,
            };
        };

        // =====================================================================
        // Helper parseVitaminA
        // Enum: ['ya', 'tidak', 'tidak_tahu']
        // =====================================================================
        $parseVitaminA = function ($value): ?string {
            $val = strtolower(trim((string) $value));
            return match (true) {
                in_array($val, ['ya', 'y', 'yes', '1'])                      => 'ya',
                in_array($val, ['tidak', 'no', 'n', '0'])                    => 'tidak',
                in_array($val, ['tidak tahu', 'tidak_tahu', 'unknown', ''])  => 'tidak_tahu',
                default => null, // nilai tidak dikenal → null, bukan 'tidak_tahu' diam-diam
            };
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
                $tglLapor      = $parseDate($row[2]  ?? null)
                              ?? $parseDate($row[6]  ?? null)   // Tanggal Terima Laporan
                              ?? $parseDate($row[22] ?? null)   // Tanggal Onset
                              ?? Carbon::today()->format('Y-m-d');

                $tglOnset      = $parseDate($row[22] ?? null)
                              ?? $tglLapor;

                $tglKonsultasi = $parseDate($row[80] ?? null)   // Tanggal Kunjungan RS
                              ?? $parseDate($row[82] ?? null)   // Tanggal Kunjungan FKTP
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

                    $tglPenyidikan = $parseDate($row[7] ?? null);
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

                // Kolom terakhir file impor (Google Form export)
                $klasifikasi      = self::resolveKlasifikasi($row[194] ?? null);  // Klasifikasi Akhir
                $caseExists       = SurveillanceCase::where('no_registrasi', $noReg)->exists();
                $denganKomplikasi = match (strtolower(trim((string) ($row[196] ?? '')))) {  // Dengan Komplikasi
                    'ya', 'y', 'yes', '1'  => true,
                    'tidak', 'no', 'n', '0' => false,
                    default                 => null,
                };

                $attrs = [
                        // =================================================
                        // GRUP A: Identitas Pasien
                        // =================================================
                        'nik'                    => (function() use ($row, $noReg, $parseDate) {
                            $rawNik = trim((string) ($row[8] ?? ''));
                            if ($rawNik !== '' && ctype_digit($rawNik) && strlen($rawNik) >= 15) {
                                return substr($rawNik, 0, 16);
                            }
                            // Generate atau temukan NIK dummy
                            $nama    = trim((string) ($row[9] ?? ''));
                            $tglLhir = $parseDate($row[11] ?? null) ?? date('Y-m-d');
                            $jk      = in_array($row[10] ?? '', ['L', 'Laki-laki', 'laki-laki', 'l']) ? 'L' : 'P';
                            $nik = $this->nikService->findExisting($nama, $tglLhir, $jk)
                                ?? $this->nikService->generate(NikDummyService::DEFAULT_KODE_WILAYAH, $tglLhir, $jk);
                            $this->failures[] = "[INFO] NIK kosong untuk {$nama} — NIK dummy {$nik} di-generate.";
                            return $nik;
                        })(),
                        'nama_lengkap'           => $row[9]  ?? null,
                        'jenis_kelamin'          => in_array($row[10] ?? '', ['L', 'Laki-laki', 'laki-laki', 'l']) ? 'L' : 'P',
                        'tanggal_lahir'          => $parseDate($row[11] ?? null),
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
                        'tanggal_terima_laporan' => $parseDate($row[6]  ?? null),
                        'tanggal_penyidikan'     => $parseDate($row[7]  ?? null),

                        // =================================================
                        // GRUP C: Data Kasus
                        // =================================================
                        'tanggal_demam'          => $parseDate($row[21] ?? null),
                        'tanggal_onset'          => $tglOnset,
                        'tanggal_konsultasi'     => $tglKonsultasi,               // wajib NOT NULL — fallback ke tanggal_lapor/onset
                        'id_jenis_kasus'         => $idJenisKasus,
                        // status_kasus & penyakit_terkonfirmasi diset kondisional setelah array ini
                        'status_rawat'           => $statusRawat,                 // wajib NOT NULL — derived dari nama_rs
                        'nama_faskes_rawat'      => $namaFaskes,                  // wajib NOT NULL — chain fallback
                        'tanggal_keluar_rawat'   => $parseDate($row[197] ?? null), // kolom "Tanggal KRS"
                        'id_petugas_input'       => $this->userId,                // wajib NOT NULL — user yang upload

                        // =================================================
                        // GRUP D: Gejala Klinis
                        // =================================================
                        'gejala_demam'           => !empty($row[21]),             // T015: dari kehadiran tanggal_demam
                        // T016: gejala_ruam dihapus (tidak ada kolom di Excel)
                        'gejala_batuk'           => $parseBoolean($row[24] ?? ''), // T012: fix [26]→[24]
                        'gejala_pilek'           => $parseBoolean($row[25] ?? ''), // T013: fix [27]→[25]
                        'gejala_mata_merah'      => $parseBoolean($row[26] ?? ''), // T014: fix [28]→[26]
                        'gejala_adenopathy'      => $parseBoolean($row[27] ?? ''), // T023
                        'gejala_arthralgia'      => $parseBoolean($row[28] ?? ''), // T023
                        'gejala_kehamilan'       => $parseBoolean($row[29] ?? ''), // T023
                        'gejala_lainnya'         => $row[42] ?? null,             // T023

                        // Tanggal gejala lanjutan (T024)
                        'tanggal_leher_bengkak'  => $parseDate($row[43] ?? null),
                        'tanggal_sesak_nafas'    => $parseDate($row[44] ?? null),
                        'tanggal_pseudomembran'  => $parseDate($row[45] ?? null),
                        'tanggal_apnea'          => $parseDate($row[68] ?? null),

                        // =================================================
                        // GRUP D2: Komplikasi (T025)
                        // =================================================
                        'komplikasi_diare'              => $parseBoolean($row[30] ?? ''),
                        'komplikasi_kebutaan'           => $parseBoolean($row[31] ?? ''),
                        'komplikasi_pneumonia'          => $parseBoolean($row[32] ?? ''),
                        'komplikasi_malnutrisi'         => $parseBoolean($row[33] ?? ''),
                        'komplikasi_bronchopneumonia'   => $parseBoolean($row[34] ?? ''),
                        'komplikasi_otitis_media'       => $parseBoolean($row[35] ?? ''),
                        'komplikasi_encephalitis'       => $parseBoolean($row[36] ?? ''),
                        'komplikasi_ulkus_mukosa_mulut' => $parseBoolean($row[37] ?? ''),
                        'dengan_komplikasi'             => $denganKomplikasi,      // kolom "Dengan Komplikasi"

                        // =================================================
                        // GRUP D3-D4: Gizi & Pengobatan (T026)
                        // =================================================
                        'vitamin_a'              => $parseVitaminA($row[38] ?? null),
                        'berat_badan'            => is_numeric($row[40] ?? null) ? (float) $row[40] : null,
                        'tinggi_badan'           => is_numeric($row[41] ?? null) ? (float) $row[41] : null,
                        'jenis_antibiotik'       => $row[46] ?? null,
                        'dosis_ads'              => $row[47] ?? null,
                        'obat_lainnya'           => $row[48] ?? null,

                        // =================================================
                        // GRUP D5: AFP/Polio (T027)
                        // Enum: ['ya', 'tidak']
                        // =================================================
                        'kelumpuhan_akut'        => $parseYaTidak($row[49] ?? null),
                        'kelumpuhan_flaccid'     => $parseYaTidak($row[50] ?? null),
                        'kelumpuhan_rudapaksa'   => $parseYaTidak($row[51] ?? null),

                        // =================================================
                        // GRUP D6: Diagnosis & Pemeriksaan Fisik (T017, T028)
                        // =================================================
                        'diagnosis'              => $row[52] ?? null,             // T017: fix [121]→[52]
                        'tanda_tungkai_kanan'    => $row[53] ?? null,
                        'tanda_tungkai_kiri'     => $row[54] ?? null,
                        'tanda_lengan_kanan'     => $row[55] ?? null,
                        'tanda_lengan_kiri'      => $row[56] ?? null,
                        'kekuatan_otot'          => $parseIntOrNull($row[57] ?? null),
                        'lokasi_kelemahan_lain'  => $row[58] ?? null,
                        'tanda_penyakit_observasi' => $row[96] ?? null,

                        // =================================================
                        // GRUP D7: Kontak Polio (T029)
                        // Enum: ['ya', 'tidak', 'tidak_tahu']
                        // =================================================
                        'kontak_polio_oral'      => $parseYaTidak($row[59] ?? null),

                        // =================================================
                        // GRUP D8: Sanitasi (T029)
                        // =================================================
                        'jamban_sendiri'         => $parseYaTidak($row[60] ?? null),
                        'jamban_saluran_kedap'   => $parseYaTidak($row[61] ?? null),
                        'jenis_jamban'           => $row[62] ?? null,
                        'selalu_gunakan_jamban'  => $parseYaTidak($row[63] ?? null),
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
                        // Prioritas kolom "Status Vaksin MR" (195) yang lebih bersih,
                        // fallback ke "Pengisian Riwayat Imunisasi" (39).
                        'riwayat_imunisasi'              => $parseRiwayatImunisasi($row[195] ?? null)
                                                            ?? $parseRiwayatImunisasi($row[39] ?? null),
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
                        'status_gizi'                    => $parseStatusGizi($row[77] ?? null),
                        'tempat_berobat'                 => $row[78] ?? null,
                        'nama_rs'                        => $row[79] ?? null,
                        'tanggal_kunjungan_rs'           => $parseDate($row[80] ?? null),
                        'nama_fktp'                      => $row[81] ?? null,
                        'tanggal_kunjungan_fktp'         => $parseDate($row[82] ?? null),
                        'nama_pengobatan_tradisional'    => $row[83] ?? null,
                        'tanggal_kunjungan_tradisional'  => $parseDate($row[84] ?? null),

                        // =================================================
                        // GRUP F: Laboratorium (T033)
                        // =================================================
                        'jenis_spesimen'              => $row[85] ?? null,
                        'tanggal_pengambilan_spesimen' => $parseDate($row[86] ?? null),
                        'jenis_spesimen_2'            => $row[87] ?? null,
                        'tanggal_spesimen_2'          => $parseDate($row[88] ?? null),
                        'jenis_spesimen_3'            => $row[89] ?? null,
                        'tanggal_spesimen_3'          => $parseDate($row[90] ?? null),

                        // =================================================
                        // GRUP I: Kontak & Perjalanan (T034)
                        // =================================================
                        'keluarga_sakit_sama'    => $parseYaTidak($row[91] ?? null),
                        'jumlah_keluarga_sakit'  => $parseIntOrNull($row[92] ?? null),
                        'riwayat_bepergian'      => $parseYaTidak($row[93] ?? null),
                        'lokasi_bepergian'       => $row[94] ?? null,
                        'tanggal_bepergian'      => $parseDate($row[95] ?? null),

                        // =================================================
                        // GRUP TN: Tetanus Neonatorum (T035)
                        // =================================================
                        'lama_tinggal_desa'      => $row[97]  ?? null,
                        'bayi_lahir_hidup'       => $parseYaTidak($row[98]  ?? null),
                        'umur_bayi_meninggal_hari' => $parseIntOrNull($row[99] ?? null),
                        'bayi_menangis_lahir'    => $parseYaTidak($row[100] ?? null),
                        'tanda_kelahiran_hidup'  => $parseYaTidak($row[102] ?? null),
                        'bayi_bisa_menyusu'      => $parseYaTidak($row[103] ?? null),
                        'bayi_mulut_mencucu'     => $parseYaTidak($row[104] ?? null),
                        'bayi_mudah_kejang'      => $parseYaTidak($row[105] ?? null),
                        'jumlah_kunjungan_anc'   => $parseIntOrNull($row[106] ?? null),
                        'tempat_pemeriksaan_hamil' => $row[107] ?? null,
                        'pemeriksa_kehamilan'    => $row[108] ?? null,
                        'tempat_persalinan'      => $row[109] ?? null,
                        'usia_kehamilan_bulan'   => $parseIntOrNull($row[110] ?? null),
                        'penolong_persalinan'    => $row[111] ?? null,
                        'alat_potong_tali_pusat' => $row[112] ?? null,
                        'perawatan_tali_pusat'   => $row[113] ?? null,
                        'keadaan_ibu_saat_ini'   => $row[114] ?? null,

                        // =================================================
                        // GRUP J: Audit (diisi sistem)
                        // =================================================
                        'created_by'             => $this->userId,
                        'updated_by'             => $this->userId,
                ];

                // Status kasus dari "Klasifikasi Akhir":
                // - ada nilai (confirmed/discarded) → set + penyakit terkonfirmasi
                // - kosong/#N/A pada record LAMA → JANGAN timpa status yang sudah ada
                //   (mis. confirmed dari import hasil lab terpisah)
                // - kosong pada record BARU → default aman 'suspected'
                if ($klasifikasi['status'] !== null) {
                    $attrs['status_kasus']           = $klasifikasi['status'];
                    $attrs['penyakit_terkonfirmasi'] = $klasifikasi['penyakit'];
                } elseif (!$caseExists) {
                    $attrs['status_kasus']           = 'suspected';
                    $attrs['penyakit_terkonfirmasi'] = null;
                }

                // Atribusi faskes RS — tanpa ini kasus hasil import punya
                // faskes_type/id_faskes NULL sehingga TIDAK PERNAH terlihat oleh user
                // surveilans_rs (lihat SurveillanceCase::scopeVisibleTo, yang menyaring
                // RS murni via faskes_type='rs' + id_faskes, tanpa fallback wilayah).
                // Hanya di-set bila instansi_pelapor cocok master RS; bila tidak (mis.
                // pelapor puskesmas) biarkan null — puskesmas tetap melihatnya via id_kel.
                $idRs = $this->resolveRumahSakit(isset($row[4]) ? (string) $row[4] : null);
                if ($idRs !== null) {
                    $attrs['faskes_type'] = 'rs';
                    $attrs['id_faskes']   = $idRs;
                }

                SurveillanceCase::updateOrCreate(['no_registrasi' => $noReg], $attrs);

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
     * T037: Sederhanakan pesan error teknis menjadi pesan yang ramah pengguna.
     */
    protected function simplifyError(string $message): string
    {
        return match (true) {
            str_contains($message, 'Data too long')         => 'Data terlalu panjang untuk salah satu kolom.',
            str_contains($message, 'Incorrect date value')  => 'Format tanggal tidak valid.',
            str_contains($message, 'Incorrect integer')     => 'Format angka tidak valid.',
            str_contains($message, 'Integrity constraint')  => 'Data referensi tidak ditemukan di sistem.',
            str_contains($message, 'ENUM')                  => 'Nilai pilihan tidak valid untuk salah satu kolom.',
            default => 'Gagal menyimpan data — periksa isian baris ini.',
        };
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
