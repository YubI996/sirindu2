<?php

namespace App\Support;

/**
 * Pemetaan nilai status pemeriksaan lab (status_lab) ke bentuk biner
 * `diperiksa` / `tidak`. Dipakai migrasi & importer agar nilai lama
 * (positif/negatif/proses/…) selaras dengan skema baru.
 */
class LabStatus
{
    /** Nilai lama/baru yang berarti spesimen diperiksa. */
    private const DIPERIKSA = ['positif', 'negatif', 'proses', 'diperiksa_lab', 'diperiksa'];

    public static function toBinary(?string $value): string
    {
        $v = strtolower(trim((string) $value));

        return in_array($v, self::DIPERIKSA, true) ? 'diperiksa' : 'tidak';
    }
}
