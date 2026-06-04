<?php

namespace App\Imports;

use App\Models\DataAnak;
use App\Traits\ResolvesAnakByTwoOfThree;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import data operasi timbang dari format contoh_format_ukur.csv.
 *
 * Kolom CSV: No, NIK, nama_anak, TANGGALUKUR, BERAT, TINGGI, LILA,
 *   lingkar_kepala, Pitting_edema, CARAUKUR, vita, asi_bulan_0..6,
 *   kelas_ibu_balita, mbg.
 *
 * Identifikasi anak: NIK (utama), fallback NIK+nama via ResolvesAnakByTwoOfThree.
 * Usia dalam bulan (bln) dihitung otomatis dari tgl_lahir anak + TANGGALUKUR.
 */
class UkurImport implements ToCollection, WithStartRow, WithChunkReading
{
    use ResolvesAnakByTwoOfThree;

    protected int $userId;
    protected int $successCount = 0;
    protected int $errorCount   = 0;
    protected array $failures   = [];
    protected int $rowOffset    = 0;

    protected ?array $columnMap    = null;
    protected int    $headerRowIdx = 0;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function startRow(): int { return 1; }
    public function chunkSize(): int { return 200; }

    // =========================================================================
    // Parse helpers
    // =========================================================================

    protected function parseDate($value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) {
            try { return Carbon::instance(Date::excelToDateTimeObject((float) $value))->format('Y-m-d'); }
            catch (\Exception $e) { return null; }
        }
        try { return Carbon::parse((string) $value)->format('Y-m-d'); }
        catch (\Exception $e) { return null; }
    }

    protected function parseBoolean($value): ?bool
    {
        if ($value === null || $value === '') return null;
        return in_array(strtolower(trim((string) $value)), ['ya', 'y', 'yes', 'true', '1']);
    }

    protected function parseBoolInt($value): ?int
    {
        $b = $this->parseBoolean($value);
        return $b === null ? null : ($b ? 1 : 0);
    }

    protected function parseDecimal($value): ?float
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (float) $value : null;
    }

    protected function parseTinyInt($value): ?int
    {
        if ($value === null || $value === '') return null;
        $v = trim((string) $value);
        // Accept numeric values (0-3 for pitting edema severity)
        if (is_numeric($v) && (int)$v >= 0) return (int)$v;
        // ya/tidak mapped to 1/0
        $b = $this->parseBoolean($value);
        return $b === null ? null : ($b ? 1 : 0);
    }

    // =========================================================================
    // Main processor
    // =========================================================================

    public function collection(Collection $rows)
    {
        $isFirstChunk = $this->rowOffset === 0;
        $originalSize = count($rows);

        if ($isFirstChunk) {
            $detected = $this->detectImportHeader($rows);
            if ($detected === null) {
                $this->failures[] = '[ERROR] Header tidak ditemukan. Pastikan file memiliki baris header.';
                $this->rowOffset += $originalSize;
                return;
            }
            [$this->headerRowIdx, $this->columnMap] = $detected;
            $rows = $rows->slice($this->headerRowIdx + 1)->values();
        }

        $map        = $this->columnMap ?? [];
        $baseOffset = $isFirstChunk ? ($this->headerRowIdx + 1) : 0;

        foreach ($rows as $index => $row) {
            $rowNum = $this->rowOffset + $index + 1 + ($isFirstChunk ? $baseOffset : 0);

            $nikRaw      = trim((string) ($this->colVal($row, $map, 'nik') ?? ''));
            $namaAnakRaw = trim((string) ($this->colVal($row, $map, 'nama_anak') ?? ''));

            // Skip baris tanpa identifier apapun
            if (empty($nikRaw) && empty($namaAnakRaw)) continue;

            $tglUkur = $this->parseDate($this->colVal($row, $map, 'tanggalukur'));
            if (!$tglUkur) {
                $this->failures[] = "[PERINGATAN] Baris {$rowNum} ({$namaAnakRaw}): TANGGALUKUR kosong atau tidak valid — dilewati.";
                continue;
            }

            try {
                $result = $this->resolveAnakByTwoOfThree($nikRaw, $namaAnakRaw, null);

                if ($result['warning']) {
                    $this->failures[] = "[INFO] Baris {$rowNum}: " . $result['warning'];
                }

                if ($result['anak'] === null) {
                    $label = $namaAnakRaw ?: $nikRaw;
                    $this->failures[] = match ($result['match']) {
                        'ambigu'          => "[ERROR] Baris {$rowNum} ({$label}): " . $result['warning'],
                        'tidak_ditemukan' => "[ERROR] Baris {$rowNum} ({$label}): Anak tidak ditemukan. Pastikan data anak sudah diimport terlebih dahulu.",
                        default           => "[ERROR] Baris {$rowNum} ({$label}): Anak tidak ditemukan.",
                    };
                    $this->errorCount++;
                    continue;
                }

                $anak = $result['anak'];

                // Hitung usia dalam bulan dari tgl_lahir anak
                $bln = 0;
                if ($anak->tgl_lahir) {
                    try {
                        $bln = (int) Carbon::parse($anak->tgl_lahir)->diffInMonths(Carbon::parse($tglUkur));
                    } catch (\Exception $e) {
                        $bln = 0;
                    }
                }

                DataAnak::updateOrCreate(
                    ['id_anak' => $anak->id, 'tgl_kunjungan' => $tglUkur],
                    [
                        'bln'             => $bln,
                        'posisi'          => trim((string) ($this->colVal($row, $map, 'caraukur') ?? 'terlentang')),
                        'bb'              => $this->parseDecimal($this->colVal($row, $map, 'berat')) ?? 0,
                        'tb'              => $this->parseDecimal($this->colVal($row, $map, 'tinggi')) ?? 0,
                        'lla'             => $this->parseDecimal($this->colVal($row, $map, 'lila')) ?? 0,
                        'lk'              => $this->parseDecimal($this->colVal($row, $map, 'lingkar_kepala')) ?? 0,
                        'pitting_edema'   => $this->parseTinyInt($this->colVal($row, $map, 'pitting_edema')),
                        'vit_a'           => $this->parseBoolInt($this->colVal($row, $map, 'vita')),
                        'asi'             => $this->parseBoolInt($this->colVal($row, $map, 'asi_bulan_0')),
                        'asi_bulan_0'     => $this->parseBoolean($this->colVal($row, $map, 'asi_bulan_0')),
                        'asi_bulan_1'     => $this->parseBoolean($this->colVal($row, $map, 'asi_bulan_1')),
                        'asi_bulan_2'     => $this->parseBoolean($this->colVal($row, $map, 'asi_bulan_2')),
                        'asi_bulan_3'     => $this->parseBoolean($this->colVal($row, $map, 'asi_bulan_3')),
                        'asi_bulan_4'     => $this->parseBoolean($this->colVal($row, $map, 'asi_bulan_4')),
                        'asi_bulan_5'     => $this->parseBoolean($this->colVal($row, $map, 'asi_bulan_5')),
                        'asi_bulan_6'     => $this->parseBoolean($this->colVal($row, $map, 'asi_bulan_6')),
                        'kelas_ibu_balita' => $this->parseBoolean($this->colVal($row, $map, 'kelas_ibu_balita')),
                        'mbg'             => $this->parseBoolean($this->colVal($row, $map, 'mbg')),
                        'id_user'         => $this->userId,
                    ]
                );

                $this->successCount++;

            } catch (\Exception $e) {
                $label = $namaAnakRaw ?: $nikRaw;
                $this->failures[] = "[ERROR] Baris {$rowNum} ({$label}): " . $this->simplifyError($e->getMessage());
                $this->errorCount++;
                Log::warning("UkurImport skip baris {$rowNum}: " . $e->getMessage());
            }
        }

        $this->rowOffset += $originalSize;
    }

    protected function simplifyError(string $message): string
    {
        return match (true) {
            str_contains($message, 'Data too long')        => 'Data terlalu panjang untuk salah satu kolom.',
            str_contains($message, 'Incorrect date value') => 'Format tanggal tidak valid.',
            str_contains($message, 'Incorrect integer')    => 'Format angka tidak valid.',
            str_contains($message, 'Integrity constraint') => 'Data referensi tidak ditemukan.',
            default => mb_substr($message, 0, 120),
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
