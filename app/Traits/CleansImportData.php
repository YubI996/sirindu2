<?php

namespace App\Traits;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;

trait CleansImportData
{
    protected function isExcelError(mixed $val): bool
    {
        return (bool) preg_match('/^#(VALUE|NAME|NULL|DIV\/0|REF|NUM|N\/A)!?$/i', trim((string) $val));
    }

    protected function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if ($this->isExcelError($value)) return null;
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
    }

    protected function parseBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') return null;
        return in_array(strtolower(trim((string) $value)), ['ya', 'y', 'yes', 'true', '1']);
    }

    protected function parseDecimalOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        if ($this->isExcelError($value)) return null;
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }
        return is_numeric($value) ? (float) $value : null;
    }

    protected function parseIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (int) $value : null;
    }

    protected function parseYaTidak(mixed $value): ?string
    {
        $val = strtolower(trim((string) $value));
        return match (true) {
            in_array($val, ['ya', 'y', 'yes', '1'])                           => 'ya',
            in_array($val, ['tidak', 'no', 'n', '0'])                         => 'tidak',
            in_array($val, ['tidak tahu', 'tidak_tahu', 'unknown'])            => 'tidak_tahu',
            in_array($val, ['kadang', 'kadang-kadang', 'kadang_kadang'])       => 'kadang_kadang',
            default => null,
        };
    }

    protected function parseRiwayatImunisasi(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') return null;
        $val = strtolower(trim((string) $value));
        return match (true) {
            str_contains($val, 'tidak_lengkap') || str_contains($val, 'tidak lengkap') => 'tidak_lengkap',
            str_contains($val, 'tidak_ada')     || str_contains($val, 'tidak ada') || $val === 'tidak' => 'tidak_ada',
            str_contains($val, 'tidak_tahu')    || str_contains($val, 'tidak tahu') => 'tidak_tahu',
            str_contains($val, 'lengkap') => 'lengkap',
            default => null,
        };
    }

    protected function parseStatusGizi(mixed $value): ?string
    {
        if (empty($value)) return null;
        $val = strtolower(trim((string) $value));
        return match (true) {
            in_array($val, ['baik', 'normal', 'gizi baik']) => 'baik',
            str_contains($val, 'kurang')                    => 'kurang',
            str_contains($val, 'buruk')                     => 'buruk',
            in_array($val, ['lebih', 'obesitas', 'gemuk'])  => 'lebih',
            default => null,
        };
    }

    protected function parseVitaminA(mixed $value): ?string
    {
        $val = strtolower(trim((string) $value));
        return match (true) {
            in_array($val, ['ya', 'y', 'yes', '1'])                     => 'ya',
            in_array($val, ['tidak', 'no', 'n', '0'])                   => 'tidak',
            in_array($val, ['tidak tahu', 'tidak_tahu', 'unknown', '']) => 'tidak_tahu',
            default => null,
        };
    }

    // Returns 'L'|'P'|null — null for truly empty/unrecognized (no silent default).
    protected function parseGenderString(mixed $val): ?string
    {
        $v = strtolower(trim((string) $val));
        if ($v === '') return null;
        if (in_array($v, ['l', 'laki', 'laki-laki', 'male', 'm', '1'])) return 'L';
        if (in_array($v, ['p', 'perempuan', 'female', 'f', '2']))        return 'P';
        return null;
    }

    // Returns 1|2|null — null for unrecognized, caller decides fallback.
    protected function parseGenderInt(mixed $val): ?int
    {
        $v = strtolower(trim((string) $val));
        if (in_array($v, ['l', 'laki', 'laki-laki', 'male', 'm', '1'])) return 1;
        if (in_array($v, ['p', 'perempuan', 'female', 'f', '2']))        return 2;
        return null;
    }

    protected function cleanText(mixed $val): ?string
    {
        $s = preg_replace('/\s+/', ' ', trim((string) $val));
        if ($s === '' || in_array(strtoupper($s), ['NULL', 'N/A', '-', '0'])) return null;
        return $s;
    }

    protected function cleanNama(mixed $val): ?string
    {
        $s = $this->cleanText($val);
        return $s !== null ? ucwords(strtolower($s)) : null;
    }

    // Strips all non-digit chars so dashed NIKs like "647272-010100-0001" become digits only.
    protected function cleanNikRaw(mixed $val): string
    {
        return preg_replace('/\D/', '', (string) $val);
    }

    // Soft anthropometry range check for children 0–60 months. Appends to $this->failures[].
    protected function validateAntropometri(?float $bb, ?float $tb, ?float $lk, ?float $lla, int $rowNum): void
    {
        if ($bb !== null && $bb > 0 && ($bb < 1.0 || $bb > 30.0)) {
            $this->failures[] = "[PERINGATAN] Baris {$rowNum}: BB {$bb} kg di luar batas wajar (1–30 kg)";
        }
        if ($tb !== null && $tb > 0 && ($tb < 30 || $tb > 120)) {
            $this->failures[] = "[PERINGATAN] Baris {$rowNum}: TB {$tb} cm di luar batas wajar (30–120 cm)";
        }
        if ($lk !== null && $lk > 0 && ($lk < 20 || $lk > 65)) {
            $this->failures[] = "[PERINGATAN] Baris {$rowNum}: LK {$lk} cm di luar batas wajar (20–65 cm)";
        }
        if ($lla !== null && $lla > 0 && ($lla < 4 || $lla > 25)) {
            $this->failures[] = "[PERINGATAN] Baris {$rowNum}: LLA {$lla} cm di luar batas wajar (4–25 cm)";
        }
    }

    // KohortImport version as canonical: extracts column name and value from MySQL errors.
    protected function simplifyError(string $message): string
    {
        $col = preg_match("/for column '([^']+)'/i", $message, $m) ? " (kolom: {$m[1]})" : '';
        $val = preg_match("/value:\s*'([^']*)'/i", $message, $m) ? " — nilai: '" . mb_substr($m[1], 0, 30) . "'" : '';

        return match (true) {
            str_contains($message, 'Data too long')           => "Data terlalu panjang{$col}.",
            str_contains($message, 'Incorrect date value')    => "Format tanggal tidak valid{$col}{$val}.",
            str_contains($message, 'Incorrect integer') || str_contains($message, 'Incorrect decimal')
                                                               => "Format angka tidak valid{$col}{$val}.",
            str_contains($message, 'Integrity constraint')    => "Data referensi tidak ditemukan{$col}.",
            str_contains($message, 'ENUM')                    => "Nilai pilihan tidak valid{$col}{$val}.",
            default => "Gagal menyimpan{$col} — " . mb_substr($message, 0, 120),
        };
    }
}
