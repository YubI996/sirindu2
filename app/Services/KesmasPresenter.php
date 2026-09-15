<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Pembentuk label data Kesmas untuk detail anak & Export Kesmas — supaya
 * "Ya / Tidak / belum diisi" dan label enum ditulis satu kali (spec §2.3, §4, §5).
 * Semua method statis dan tanpa efek samping.
 */
final class KesmasPresenter
{
    /** null/'' → $kosong; 1 → "Ya"; selain itu → "Tidak". */
    public static function yaTidak($nilai, string $kosong = ''): string
    {
        if ($nilai === null || $nilai === '') {
            return $kosong;
        }

        return ((int) $nilai) === 1 ? 'Ya' : 'Tidak';
    }

    /** Label enum dari config('kesmas.<grup>'); kode yang tak dikenal dikembalikan apa adanya. */
    public static function enumLabel(string $grup, ?string $kode, string $kosong = ''): string
    {
        if ($kode === null || $kode === '') {
            return $kosong;
        }

        return config("kesmas.$grup")[$kode] ?? $kode;
    }

    /** Teks bebas: null/'' (setelah trim) → $kosong. */
    public static function teks($nilai, string $kosong = '—'): string
    {
        return ($nilai === null || trim((string) $nilai) === '') ? $kosong : (string) $nilai;
    }

    /**
     * Ringkasan layanan satu kunjungan untuk kolom "Layanan Kesmas" di detail anak.
     * Badge hanya untuk nilai 1 (0 dan NULL sama-sama tidak tampil); CKG ikut bila tanggalnya ada.
     *
     * @return array{badge: string[], keterangan: string[]}
     */
    public static function layananKunjungan(object $baris): array
    {
        $badge = [];
        foreach (config('kesmas.layanan') as $kolom => $def) {
            if (!empty($baris->$kolom)) {
                $badge[] = $def['badge'];
            }
        }
        if (!empty($baris->tgl_penanda_ckg)) {
            $badge[] = 'CKG ' . Carbon::parse($baris->tgl_penanda_ckg)->format('d/m');
        }

        $keterangan = [];
        foreach (config('kesmas.keterangan_kunjungan') as $kolom => $label) {
            $v = $baris->$kolom ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                $keterangan[] = $label . ': ' . $v;
            }
        }

        return ['badge' => $badge, 'keterangan' => $keterangan];
    }
}
