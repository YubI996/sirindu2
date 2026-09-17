<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use RuntimeException;

/**
 * Parser daftar PJ: `nip_pj`, `nama_pj`.
 * Header bebas urutan & huruf besar/kecil, BOM dibuang, pemisah koma atau titik koma.
 * CSV dibaca fgetcsv biasa (berkas kecil, puluhan baris); .xlsx/.xls lewat Maatwebsite.
 */
class PjImport
{
    public const KOLOM_WAJIB = ['nip_pj', 'nama_pj'];

    /**
     * @return array{baris: array<int, array{nip_pj:string, nama_pj:string, baris:int}>, gagal: string[]}
     */
    public function baca(string $path): array
    {
        $ekstensi = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $rows = in_array($ekstensi, ['xlsx', 'xls'], true) ? $this->barisExcel($path, $ekstensi) : $this->barisCsv($path);

        $header = null;
        $idx = [];
        $baris = [];
        $gagal = [];
        $no = 0;
        foreach ($rows as $row) {
            $no++;
            if ($header === null) {
                $header = array_map(fn ($h) => strtolower(trim((string) $h)), $row);
                foreach (self::KOLOM_WAJIB as $k) {
                    $i = array_search($k, $header, true);
                    if ($i !== false) $idx[$k] = $i;
                }
                foreach (self::KOLOM_WAJIB as $k) {
                    if (!isset($idx[$k])) {
                        throw new RuntimeException("Kolom wajib \"{$k}\" tidak ada di header. Header ditemukan: ".implode(', ', $header));
                    }
                }
                continue;
            }
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // baris kosong
            }
            $ambil = fn (string $k) => isset($idx[$k]) ? trim((string) ($row[$idx[$k]] ?? '')) : '';
            $namaPj    = $ambil('nama_pj');
            $nipPj     = $ambil('nip_pj');

            $masalah = [];
            if ($nipPj === '') {
                $masalah[] = 'Kolom nip_pj wajib diisi.';
            } elseif (!preg_match('/^[0-9]{18}$/', $nipPj)) {
                $masalah[] = 'Kolom nip_pj harus 18 digit utuh. Simpan kolom NIP sebagai teks.';
            }
            if ($namaPj === '') {
                $masalah[] = 'Kolom nama_pj wajib diisi.';
            } elseif (mb_strlen($namaPj) > 100) {
                $masalah[] = 'Kolom nama_pj maksimal 100 karakter.';
            }
            if ($masalah !== []) {
                $gagal[] = "Baris {$no}: ".implode(' ', $masalah);
                continue;
            }
            $baris[] = [
                'nama_pj'   => $namaPj,
                'nip_pj'    => $nipPj,
                'baris'     => $no,
            ];
        }
        if ($header === null) {
            throw new RuntimeException('Berkas kosong.');
        }

        return ['baris' => $baris, 'gagal' => $gagal];
    }

    /** Baris-baris CSV (baris pertama = header), pemisah ditebak dari header. */
    private function barisCsv(string $path): \Generator
    {
        $fh = fopen($path, 'r');
        if (!$fh) {
            throw new RuntimeException("Berkas tidak bisa dibuka: {$path}");
        }

        $headerLine = fgets($fh);
        if ($headerLine === false) {
            fclose($fh);
            throw new RuntimeException('Berkas kosong.');
        }
        $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine);
        $delim = substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';
        yield str_getcsv($headerLine, $delim, '"', '\\');

        while (($row = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) { // escape eksplisit: PHP 8.4
            yield $row === [null] ? [] : $row;
        }
        fclose($fh);
    }

    /** Baris-baris sheet pertama; sel angka dibaca apa adanya (NIP angka sudah dibulatkan Excel → gagal validasi 18 digit). */
    private function barisExcel(string $path, string $ekstensi): array
    {
        $tipe = $ekstensi === 'xls' ? Excel::XLS : Excel::XLSX;
        $sheets = ExcelFacade::toArray(new class implements ToArray {
            public function array(array $array): void {}
        }, $path, null, $tipe);

        return $sheets[0] ?? [];
    }
}
