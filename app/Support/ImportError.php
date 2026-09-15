<?php

namespace App\Support;

/** Pesan kegagalan per baris tanpa membuang nama kolom dari error database. */
class ImportError
{
    public static function message(string $message): string
    {
        // QueryException menambahkan SQL beserta semua nilai baris. Baca hanya pesan driver.
        $detail = preg_split('/\s*\(Connection:|\s*\(SQL:/', $message, 2)[0];
        $column = null;
        if (preg_match('/\b(?:column|field)\s+[\'"`]([a-zA-Z0-9_]+)[\'"`]/i', $detail, $match)) {
            $column = $match[1];
        } elseif (preg_match('/FOREIGN KEY\s*\(([^)]+)\)/i', $detail, $match)) {
            $column = str_replace(['`', '"', "'"], '', $match[1]);
        }
        $location = $column !== null ? ' (kolom: '.$column.')' : '';
        $lower = strtolower($detail);

        $reason = match (true) {
            str_contains($lower, 'data too long') => 'Data terlalu panjang',
            str_contains($lower, 'incorrect date'), str_contains($lower, 'incorrect datetime'),
            str_contains($lower, 'invalid datetime') => 'Format tanggal tidak valid',
            str_contains($lower, 'incorrect integer'), str_contains($lower, 'incorrect decimal'),
            str_contains($lower, 'incorrect double') => 'Format angka tidak valid',
            str_contains($lower, 'out of range') => 'Angka di luar rentang yang diizinkan',
            str_contains($lower, 'cannot be null'), str_contains($lower, "doesn't have a default value") => 'Data wajib belum diisi',
            str_contains($lower, 'duplicate entry') => 'Data sudah digunakan oleh baris lain',
            str_contains($lower, 'foreign key constraint') => 'Data referensi tidak ditemukan atau masih digunakan',
            str_contains($lower, 'data truncated'), str_contains($lower, 'enum') => 'Nilai tidak sesuai pilihan atau format yang diizinkan',
            str_contains($lower, 'integrity constraint') => 'Data wajib tidak lengkap atau bentrok',
            default => 'Gagal menyimpan data; periksa isian baris ini',
        };

        if ($column === null && preg_match('/for key [\'"`]([a-zA-Z0-9_.]+)[\'"`]/i', $detail, $match)) {
            $location = ' (kunci: '.$match[1].')';
        }

        return $reason.$location.'.';
    }
}
