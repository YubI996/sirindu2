<?php

namespace App\Traits;

use App\Models\Anak;
use Illuminate\Support\Collection;

/**
 * Trait untuk mencari record Anak menggunakan logika 2-dari-3 identifier:
 * NIK, nama, dan tanggal lahir.
 *
 * Prioritas: NIK exact → NIK+tgl_lahir → NIK+nama → nama+tgl_lahir
 */
trait ResolvesAnakByTwoOfThree
{
    /**
     * Temukan Anak menggunakan minimal 2 dari 3 identifier.
     *
     * @param  string|null  $nik
     * @param  string|null  $nama
     * @param  string|null  $tglLahir  Format YYYY-MM-DD
     * @return array{anak: ?Anak, match: string, warning: ?string}
     */
    protected function resolveAnakByTwoOfThree(?string $nik, ?string $nama, ?string $tglLahir): array
    {
        $nik      = $nik ? substr(trim($nik), 0, 16) : null;
        $nikValid = $nik && ctype_digit($nik) && strlen($nik) >= 15;
        $namaKey  = $nama ? trim($nama) : null;

        // Priority 1: NIK exact (unique key di DB)
        if ($nikValid) {
            $anak = Anak::where('nik', $nik)->first();
            if ($anak) {
                return ['anak' => $anak, 'match' => 'NIK', 'warning' => null];
            }
        }

        // Priority 2: NIK + tgl_lahir (2 of 3)
        if ($nikValid && $tglLahir) {
            $anak = Anak::where('nik', $nik)->whereDate('tgl_lahir', $tglLahir)->first();
            if ($anak) {
                return ['anak' => $anak, 'match' => 'NIK+tgl_lahir', 'warning' => "NIK '{$nik}' tidak cocok exact, ditemukan via NIK+tgl_lahir."];
            }
        }

        // Priority 3: NIK + nama (2 of 3)
        if ($nikValid && $namaKey) {
            $anak = Anak::where('nik', $nik)
                ->whereRaw('LOWER(nama) LIKE ?', ['%' . strtolower($namaKey) . '%'])
                ->first();
            if ($anak) {
                return ['anak' => $anak, 'match' => 'NIK+nama', 'warning' => null];
            }
        }

        // Priority 4: nama + tgl_lahir (2 of 3)
        if ($namaKey && $tglLahir) {
            $candidates = Anak::whereRaw('LOWER(nama) LIKE ?', ['%' . strtolower($namaKey) . '%'])
                ->whereDate('tgl_lahir', $tglLahir)
                ->get();

            if ($candidates->count() === 1) {
                $warn = $nikValid ? "NIK '{$nik}' tidak ditemukan di DB; cocok via nama+tgl_lahir." : null;
                return ['anak' => $candidates->first(), 'match' => 'nama+tgl_lahir', 'warning' => $warn];
            }

            if ($candidates->count() > 1) {
                return [
                    'anak'    => null,
                    'match'   => 'ambigu',
                    'warning' => "Ditemukan {$candidates->count()} anak bernama '{$namaKey}' lahir '{$tglLahir}'. Lengkapi kolom nik_anak.",
                ];
            }
        }

        return ['anak' => null, 'match' => 'tidak_ditemukan', 'warning' => null];
    }

    /**
     * Dari baris-baris di Collection, temukan baris header (baris pertama non-#).
     * Return [rowIndex, columnMap (nama_kolom_lowercase => indeks)] atau null.
     */
    protected function detectImportHeader(Collection $rows): ?array
    {
        foreach ($rows as $idx => $row) {
            $firstCell = trim((string) ($row[0] ?? ''));
            if (str_starts_with($firstCell, '#')) continue;

            $columnMap = [];
            foreach ($row as $colIdx => $colName) {
                $key = strtolower(trim((string) $colName));
                if ($key !== '') {
                    $columnMap[$key] = $colIdx;
                }
            }

            return [$idx, $columnMap];
        }

        return null;
    }

    /**
     * Ambil nilai dari baris berdasarkan nama kolom dalam columnMap.
     * Return null jika kolom tidak ada atau nilainya kosong/null.
     */
    protected function colVal($row, array $map, string $key): mixed
    {
        $idx = $map[$key] ?? null;
        if ($idx === null) return null;
        $val = $row[$idx] ?? null;
        if ($val === '' || $val === null) return null;
        return $val;
    }
}
