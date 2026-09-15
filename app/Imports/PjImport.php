<?php

namespace App\Imports;

use RuntimeException;

/**
 * Parser CSV nama Penanggung Jawab: kolom `kelurahan`, `posyandu` (opsional), `nama_pj`.
 * Header bebas urutan & huruf besar/kecil, BOM dibuang, pemisah koma atau titik koma.
 * Berkas kecil (puluhan baris) → fgetcsv biasa, tanpa Maatwebsite.
 */
class PjImport
{
    public const KOLOM_WAJIB = ['kelurahan', 'nama_pj'];

    /**
     * @return array{baris: array<int, array{kelurahan:string, posyandu:?string, nama_pj:string, baris:int}>, gagal: string[]}
     */
    public function baca(string $path): array
    {
        $fh = fopen($path, 'r');
        if (!$fh) {
            throw new RuntimeException("Berkas tidak bisa dibuka: {$path}");
        }

        $headerLine = fgets($fh);
        if ($headerLine === false) {
            throw new RuntimeException('Berkas kosong.');
        }
        $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine);
        $delim = substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), str_getcsv($headerLine, $delim));

        $idx = [];
        foreach (['kelurahan', 'posyandu', 'nama_pj'] as $k) {
            $i = array_search($k, $header, true);
            if ($i !== false) $idx[$k] = $i;
        }
        foreach (self::KOLOM_WAJIB as $k) {
            if (!isset($idx[$k])) {
                fclose($fh);
                throw new RuntimeException("Kolom wajib \"{$k}\" tidak ada di header. Header ditemukan: ".implode(', ', $header));
            }
        }

        $baris = [];
        $gagal = [];
        $no = 1;
        while (($row = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) { // escape eksplisit: PHP 8.4
            $no++;
            if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // baris kosong
            }
            $ambil = fn (string $k) => isset($idx[$k]) ? trim((string) ($row[$idx[$k]] ?? '')) : '';
            $kelurahan = $ambil('kelurahan');
            $namaPj    = $ambil('nama_pj');
            $posyandu  = $ambil('posyandu');

            if ($kelurahan === '' || $namaPj === '') {
                $gagal[] = "Baris {$no}: kelurahan dan nama_pj wajib diisi.";
                continue;
            }
            $baris[] = [
                'kelurahan' => $kelurahan,
                'posyandu'  => $posyandu !== '' ? $posyandu : null,
                'nama_pj'   => $namaPj,
                'baris'     => $no,
            ];
        }
        fclose($fh);

        return ['baris' => $baris, 'gagal' => $gagal];
    }
}
