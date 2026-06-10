<?php

namespace App\Imports;

use App\Models\JenisKasusEpidemiologi;
use App\Models\SurveillanceCase;
use App\Models\SurveillanceCaseSpesimen;
use App\Services\NikDummyService;
use App\Traits\ResolvesWilayah;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Import data hasil laboratorium PD3I dari file CSV (hasil-lab*.csv).
 *
 * Struktur kolom (0-indexed, baris 1 = header, data mulai baris 2):
 *  [0]  Jenis Penyakit
 *  [1]  No Epid         → no_registrasi (kunci upsert)
 *  [2]  Nama Kasus      → nama_lengkap
 *  [3]  Faskes Pelapor  → instansi_pelapor / nama_faskes_rawat
 *  [4]  Wilker          → wilker_puskesmas / resolusi kecamatan
 *  [5]  Tanggal Terima Laporan
 *  [6]  Tgl Onset
 *  [7]  Tanggal Kirim   → tanggal_kirim_sampel (spesimen)
 *  [8]  Tanggal Lab Terima → tanggal_terima_lab (spesimen)
 *  [9]  Tanggal Hasil   → tanggal_hasil_lab
 *  [10] Kondisi Sampel
 *  [11] Sampel Adekuat
 *  [12] Campak          → hasil per-penyakit (Positif/Negatif)
 *  [13] Rubella
 *  [14] Polio
 *  [15] Difteri
 *  [16] Pertusis
 *  [17] Varian/Genotype/Serotype → nama_variant_genotype
 *  [18] Klasifikasi Akhir        → status_kasus
 *
 * Mode update (kasus sudah ada): hanya memperbarui kolom lab dan spesimen.
 * Mode create (kasus baru): menyimpan rekaman minimal dengan field wajib diisi default.
 */
class HasilLabImport implements ToCollection, WithStartRow, WithChunkReading
{
    use ResolvesWilayah;

    protected int $userId;
    protected int $successCount = 0;
    protected int $errorCount   = 0;
    protected array $failures   = [];
    protected int $rowOffset    = 0;

    protected array $jenisKasusCache = [];
    protected NikDummyService $nikService;

    /** Nama kolom hasil per penyakit dalam urutan kolom CSV [12]–[16]. */
    private const DISEASE_COLUMNS = ['Campak', 'Rubella', 'Polio', 'Difteri', 'Pertusis'];

    /** Jenis spesimen default berdasarkan kata kunci dalam Jenis Penyakit. */
    private const JENIS_SPESIMEN_MAP = [
        'campak'   => 'Darah (serum)',
        'rubella'  => 'Darah (serum)',
        'polio'    => 'Tinja',
        'afp'      => 'Tinja',
        'difteri'  => 'Swab Tenggorokan',
        'pertusis' => 'Swab Nasofaring',
    ];

    public function __construct(int $userId)
    {
        $this->userId     = $userId;
        $this->nikService = new NikDummyService();

        $this->initWilayahCache();

        $this->jenisKasusCache = JenisKasusEpidemiologi::pluck('id', 'nama_penyakit')
            ->mapWithKeys(fn ($id, $nama) => [strtoupper($nama) => $id])
            ->toArray();
    }

    public function startRow(): int  { return 2; }
    public function chunkSize(): int { return 500; }

    public function collection(Collection $rows): void
    {
        $parseDate = function ($value): ?string {
            if ($value === null || trim((string) $value) === '') return null;
            try {
                return Carbon::parse((string) $value)->format('Y-m-d');
            } catch (\Exception) {
                return null;
            }
        };

        $chunkSize = count($rows);

        foreach ($rows as $index => $row) {
            $rowNum = $this->rowOffset + $index + $this->startRow();

            // Lewati baris yang kolomnya semua kosong
            if ($row->filter(fn ($v) => trim((string) $v) !== '')->isEmpty()) {
                continue;
            }

            $jenisRaw = trim((string) ($row[0] ?? ''));
            $noEpid   = trim((string) ($row[1] ?? ''));
            $namaSisa = trim((string) ($row[2] ?? ''));

            // Lewati baris placeholder #N/A atau No Epid kosong
            if ($noEpid === '' || $noEpid === '#N/A' || $jenisRaw === '#N/A') {
                continue;
            }

            try {
                $tglTerima  = $parseDate($row[5] ?? null);
                $tglOnset   = $parseDate($row[6] ?? null);
                $tglKirim   = $parseDate($row[7] ?? null);
                $tglLabTrm  = $parseDate($row[8] ?? null);
                $tglHasil   = $parseDate($row[9] ?? null);
                $kondisi    = trim((string) ($row[10] ?? ''));
                $klasifikasi = trim((string) ($row[18] ?? ''));

                // ============================================================
                // Hitung hasil lab dari kolom per penyakit [12]–[16]
                // ============================================================
                $hasilPerPenyakit = [];
                $adaPositif       = false;
                $adaHasil         = false;
                $penyakitKonfirmasi = null;

                foreach (self::DISEASE_COLUMNS as $idx => $namaPenyakit) {
                    $hasil = trim((string) ($row[12 + $idx] ?? ''));
                    if ($hasil === '') continue;

                    $hasilPerPenyakit[] = "{$namaPenyakit}: {$hasil}";
                    $adaHasil           = true;

                    if (strtolower($hasil) === 'positif') {
                        $adaPositif         = true;
                        $penyakitKonfirmasi = $penyakitKonfirmasi ?? $namaPenyakit;
                    }
                }

                $hasilLabText = $adaHasil ? implode(' | ', $hasilPerPenyakit) : null;

                // Status lab berdasarkan ketersediaan data
                if ($adaHasil) {
                    $statusLab = $adaPositif ? 'positif' : 'negatif';
                } elseif ($tglKirim) {
                    $statusLab = 'proses';
                } else {
                    $statusLab = 'belum_diperiksa';
                }

                // Status kasus dari Klasifikasi Akhir
                $statusKasus = $this->resolveStatusKasus($klasifikasi);

                // ============================================================
                // Cari kasus yang sudah ada di DB
                // ============================================================
                $case = SurveillanceCase::where('no_registrasi', $noEpid)->first();

                if ($case) {
                    // Mode update: hanya perbarui kolom lab dan metadata
                    $updateData = ['updated_by' => $this->userId];

                    if ($hasilLabText !== null) {
                        $updateData['hasil_lab']       = $hasilLabText;
                        $updateData['status_lab']      = $statusLab;
                        $updateData['tanggal_hasil_lab'] = $tglHasil;
                    }

                    if ($statusKasus !== null) {
                        $updateData['status_kasus'] = $statusKasus;
                    }

                    if ($tglTerima && !$case->tanggal_terima_laporan) {
                        $updateData['tanggal_terima_laporan'] = $tglTerima;
                    }

                    $case->update($updateData);

                } else {
                    // Mode create: buat rekaman minimal
                    $faskes     = trim((string) ($row[3] ?? '')) ?: 'Tidak Diketahui';
                    $wilker     = trim((string) ($row[4] ?? ''));
                    $tglLapor   = $tglTerima ?? Carbon::today()->format('Y-m-d');
                    $tglOnsetFinal    = $tglOnset  ?? $tglLapor;
                    $tglKonsultasi    = $tglOnset  ?? $tglLapor;

                    // Resolusi wilayah dari nama wilker (auto-create jika belum ada)
                    // Wilker biasanya nama puskesmas seperti "Bontang Selatan 1" –
                    // strip angka trailing untuk mendapatkan nama kecamatan perkiraan
                    $namaKec = preg_replace('/\s*\d+$/', '', $wilker) ?: $wilker;
                    $idKec   = $this->resolveKecamatan($namaKec ?: 'Tidak Diketahui');
                    $idKel   = $this->resolveKelurahan('Tidak Diketahui', $idKec);

                    // Jenis kasus dari kolom Jenis Penyakit
                    $idJenisKasus = $this->resolveJenisKasus($jenisRaw);

                    // NIK dummy karena tidak tersedia di file lab
                    $tglLhStr = Carbon::today()->format('Y-m-d');
                    $nik = $this->nikService->generate(NikDummyService::DEFAULT_KODE_WILAYAH, $tglLhStr, 'L');
                    $this->failures[] = "[INFO] Baris {$rowNum} (No. Epid: {$noEpid}): Kasus baru dibuat dengan data minimal — NIK dummy {$nik}.";

                    if ($namaSisa === '') {
                        $this->failures[] = "[PERINGATAN] Baris {$rowNum} (No. Epid: {$noEpid}): Nama pasien kosong.";
                    }

                    $case = SurveillanceCase::create([
                        'no_registrasi'         => $noEpid,
                        'nik'                   => $nik,
                        'nama_lengkap'          => $namaSisa ?: 'Tidak Diketahui',
                        'jenis_kelamin'         => 'L',
                        'alamat_lengkap'        => 'Tidak Diketahui',
                        'id_kec'                => $idKec,
                        'id_kel'                => $idKel,
                        'nama_pelapor'          => $faskes,
                        'instansi_pelapor'      => $faskes,
                        'wilker_puskesmas'      => $wilker ?: null,
                        'tanggal_lapor'         => $tglLapor,
                        'tanggal_terima_laporan' => $tglTerima,
                        'tanggal_onset'         => $tglOnsetFinal,
                        'tanggal_konsultasi'    => $tglKonsultasi,
                        'id_jenis_kasus'        => $idJenisKasus,
                        'status_kasus'          => $statusKasus ?? 'suspected',
                        'status_rawat'          => 'rawat_jalan',
                        'nama_faskes_rawat'     => $faskes,
                        'status_lab'            => $statusLab,
                        'hasil_lab'             => $hasilLabText,
                        'tanggal_hasil_lab'     => $tglHasil,
                        'id_petugas_input'      => $this->userId,
                        'created_by'            => $this->userId,
                        'updated_by'            => $this->userId,
                    ]);
                }

                // ============================================================
                // Upsert SurveillanceCaseSpesimen (hanya jika ada data spesimen)
                // ============================================================
                $adaDataSpesimen = $tglKirim || $tglLabTrm || $adaHasil;
                if ($adaDataSpesimen) {
                    $this->upsertSpesimen($case, [
                        'tgl_kirim'          => $tglKirim,
                        'tgl_lab_terima'     => $tglLabTrm,
                        'kondisi'            => $kondisi,
                        'ada_hasil'          => $adaHasil,
                        'penyakit_konfirmasi' => $penyakitKonfirmasi,
                        'varian'             => trim((string) ($row[17] ?? '')),
                        'jenis_penyakit_raw' => $jenisRaw,
                    ]);
                }

                $this->successCount++;

            } catch (\Exception $e) {
                $errMsg = $this->simplifyError($e->getMessage());
                $this->failures[] = "[ERROR] Baris {$rowNum} (No. Epid: {$noEpid}): {$errMsg}";
                $this->errorCount++;
                Log::warning("HasilLabImport skip baris {$rowNum} [{$noEpid}]: " . $e->getMessage());
            }
        }

        $this->rowOffset += $chunkSize;
    }

    // =========================================================================

    protected function upsertSpesimen(SurveillanceCase $case, array $data): void
    {
        $statusPemeriksaan = $data['ada_hasil']
            ? 'selesai'
            : ($data['tgl_kirim'] ? 'dikirim' : 'pending');

        $idTerkonfirmasi = null;
        if ($data['penyakit_konfirmasi']) {
            $idTerkonfirmasi = $this->resolveJenisKasus($data['penyakit_konfirmasi']);
        }

        SurveillanceCaseSpesimen::updateOrCreate(
            [
                'id_surveillance_case' => $case->id,
                'urutan'               => 1,
            ],
            [
                'jenis_spesimen'            => $this->deriveJenisSpesimen($data['jenis_penyakit_raw']),
                'tanggal_kirim_sampel'      => $data['tgl_kirim'],
                'tanggal_terima_lab'        => $data['tgl_lab_terima'],
                'status_pemeriksaan'        => $statusPemeriksaan,
                'id_jenis_kasus_terkonfirmasi' => $idTerkonfirmasi,
                'nama_variant_genotype'     => $data['varian'] ?: null,
            ]
        );
    }

    protected function deriveJenisSpesimen(string $jenisPenyakit): string
    {
        $lower = strtolower($jenisPenyakit);
        foreach (self::JENIS_SPESIMEN_MAP as $keyword => $jenis) {
            if (str_contains($lower, $keyword)) return $jenis;
        }
        return 'Tidak Diketahui';
    }

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
                // kode_penyakit sudah dipakai entri lain — reuse entri tersebut
                $jenis = JenisKasusEpidemiologi::where('kode_penyakit', $kode)->firstOrFail();
            }

            $this->jenisKasusCache[$key] = $jenis->id;
        }

        return $this->jenisKasusCache[$key];
    }

    /**
     * Konversi nilai Klasifikasi Akhir → status_kasus ENUM.
     * Kembalikan null jika tidak ada perubahan (klasifikasi kosong).
     */
    protected function resolveStatusKasus(string $klasifikasi): ?string
    {
        if ($klasifikasi === '') return null;

        $lower = strtolower($klasifikasi);

        if (in_array($lower, ['discarded', 'disingkirkan', 'bukan kasus'])) return 'discarded';

        // Nama penyakit yang terkonfirmasi
        foreach (self::DISEASE_COLUMNS as $disease) {
            if (str_contains($lower, strtolower($disease))) return 'confirmed';
        }

        return null;
    }

    protected function simplifyError(string $message): string
    {
        return match (true) {
            str_contains($message, 'Data too long')               => 'Data terlalu panjang untuk salah satu kolom.',
            str_contains($message, 'Incorrect date value')        => 'Format tanggal tidak valid.',
            str_contains($message, 'Integrity constraint')        => 'Data referensi tidak ditemukan di sistem.',
            str_contains($message, 'ENUM')                        => 'Nilai pilihan tidak valid untuk salah satu kolom.',
            str_contains($message, "doesn't have a default value") => 'Kolom wajib tidak tersedia — hubungi pengembang.',
            default => 'Gagal menyimpan data — periksa isian baris ini.',
        };
    }

    public function getResults(): array
    {
        return [
            'success'     => $this->successCount,
            'error_count' => $this->errorCount,
            'failures'    => $this->failures,
        ];
    }
}
