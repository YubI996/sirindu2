<?php

namespace App\Imports;

use App\Services\NikDummyService;
use App\Traits\ResolvesAnakByTwoOfThree;
use App\Traits\ResolvesWilayah;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Bangun registri Operasi Timbang dari berkas final e-PPGBM (47 kolom).
 *
 * Seluruh logika diturunkan dari kolom NIK, BUKAN dari pewarnaan sel:
 *  - NIK kosong   → NIK dummy (setara baris cream di berkas)
 *  - NIK duplikat → satu Anak, banyak pengukuran (setara baris biru)
 *
 * Pengukuran di-upsert by (id_anak, tgl_kunjungan) sehingga dua baris ber-NIK
 * sama dan bertanggal ukur sama melebur jadi satu (keputusan klien).
 * Lihat specs/2026-07-23-ot-final-registri-import-design.md.
 */
class OtFinalRegistriImport implements ToCollection, WithStartRow, WithChunkReading, WithMultipleSheets
{
    use ResolvesAnakByTwoOfThree, ResolvesWilayah;

    /** Kolom yang wajib ada; tanpa ini pemetaan tak bermakna. */
    protected const KOLOM_WAJIB = [
        'nama anak', 'jenis kelamin', 'tanggal lahir', 'nik',
        'kecamatan', 'kelurahan',
        'tanggal pengukuran', 'berat (kg)', 'tinggi (cm)',
    ];

    protected NikDummyService $nikService;

    protected int $anakDibuat = 0;
    protected int $ukurDitulis = 0;
    protected int $dummy = 0;
    protected int $lebur = 0;
    protected int $dilewati = 0;
    protected array $peringatan = [];
    protected array $error = [];
    protected array $failures = []; // diisi ResolvesWilayah::flagUnresolvedWilayah

    protected ?array $columnMap = null;
    protected int $headerRowIdx = 0;
    protected int $rowOffset = 0;
    protected bool $headerRusak = false;

    /** Kunci anak yang sudah ditemui pada run ini ("NIK:xxx" / "BARIS:n"). */
    protected array $kunciAnak = [];

    /** Kunci "<kunciAnak>|tgl" yang sudah ditemui — untuk menghitung peleburan. */
    protected array $kunciUkur = [];

    public function __construct(
        protected int $userId,
        protected bool $commit = false,
        protected int|string $sheet = 0,
    ) {
        $this->nikService = new NikDummyService();
        $this->initWilayahCache();
    }

    public function sheets(): array
    {
        return [$this->sheet => $this];
    }

    public function startRow(): int
    {
        return 1;
    }

    public function chunkSize(): int
    {
        return 300;
    }

    public function collection(Collection $rows): void
    {
        $isFirstChunk = $this->rowOffset === 0;
        $originalSize = count($rows);

        if ($isFirstChunk) {
            $detected = $this->detectImportHeader($rows);
            if ($detected === null) {
                $this->error[] = 'Header tidak ditemukan pada berkas.';
                $this->headerRusak = true;
                $this->rowOffset += $originalSize;

                return;
            }
            [$this->headerRowIdx, $this->columnMap] = $detected;

            $hilang = array_values(array_diff(self::KOLOM_WAJIB, array_keys($this->columnMap)));
            if (!empty($hilang)) {
                $this->error[] = 'Kolom wajib tidak ditemukan: ' . implode(', ', $hilang);
                $this->headerRusak = true;
                $this->rowOffset += $originalSize;

                return;
            }

            $rows = $rows->slice($this->headerRowIdx + 1)->values();
        }

        if ($this->headerRusak) {
            $this->rowOffset += $originalSize;

            return;
        }

        $this->prosesBaris($rows, $isFirstChunk);
        $this->rowOffset += $originalSize;
    }

    /** Diisi pada Task 3–6. */
    protected function prosesBaris(Collection $rows, bool $isFirstChunk): void
    {
        // sengaja kosong pada tahap ini
    }

    public function getResults(): array
    {
        return [
            'anak_dibuat' => $this->anakDibuat,
            'ukur_ditulis' => $this->ukurDitulis,
            'dummy' => $this->dummy,
            'lebur' => $this->lebur,
            'dilewati' => $this->dilewati,
            'peringatan' => array_merge($this->peringatan, $this->failures),
            'error' => $this->error,
        ];
    }
}
