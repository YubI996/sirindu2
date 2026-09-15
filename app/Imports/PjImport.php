<?php

namespace App\Imports;

use RuntimeException;

/**
 * Parser CSV PJ: `kelurahan`, `posyandu` (opsional), `nip_pj`, `nama_pj`.
 * Header bebas urutan & huruf besar/kecil, BOM dibuang, pemisah koma atau titik koma.
 * Berkas kecil (puluhan baris) → fgetcsv biasa, tanpa Maatwebsite.
 */
class PjImport
{
    public const KOLOM_WAJIB = ['kelurahan', 'nip_pj', 'nama_pj'];

    /**
     * @return array{baris: array<int, array{kelurahan:string, posyandu:?string, nip_pj:string, nama_pj:string, baris:int}>, gagal: string[]}
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
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), str_getcsv($headerLine, $delim, '"', '\\'));

        $idx = [];
        foreach (['kelurahan', 'posyandu', 'nip_pj', 'nama_pj'] as $k) {
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
            $nipPj     = $ambil('nip_pj');
            $posyandu  = $ambil('posyandu');

            if ($kelurahan === '' || $namaPj === '' || $nipPj === '') {
                $gagal[] = "Baris {$no}: kelurahan, nip_pj, dan nama_pj wajib diisi.";
                continue;
            }
            if (!preg_match('/^[0-9]{18}$/', $nipPj) || mb_strlen($namaPj) > 100) {
                $gagal[] = "Baris {$no}: NIP harus 18 digit utuh dan nama PJ maksimal 100 karakter. Simpan kolom NIP sebagai teks.";
                continue;
            }
            $baris[] = [
                'kelurahan' => $kelurahan,
                'posyandu'  => $posyandu !== '' ? $posyandu : null,
                'nama_pj'   => $namaPj,
                'nip_pj'    => $nipPj,
                'baris'     => $no,
            ];
        }
        fclose($fh);

        return ['baris' => $baris, 'gagal' => $gagal];
    }
}
