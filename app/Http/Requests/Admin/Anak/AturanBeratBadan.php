<?php

namespace App\Http\Requests\Admin\Anak;

use App\Support\SatuanBeratBadan as S;
use Closure;

/**
 * Validasi & konversi berat badan di form pengukuran (spec 2026-10-02 §5.5) — Tambah Data
 * Pengukuran (AdminController::storeDataAnak) dan form per kunjungan (updateDataAnak). Form
 * identitas Tambah/Edit Anak TIDAK memakai ini: di sana BB tetap kg (keputusan pemilik produk).
 *
 * Server yang memutuskan satuan dari tanggal lahir + tanggal kunjungan yang dikirim; label di form
 * hanya cermin. Yang disimpan selalu kg (data_anak.bb dibaca z-score dan prioritas gizi OT).
 */
final class AturanBeratBadan
{
    /**
     * Rule closure rentang wajar sesuai satuan. $kgTersimpan diisi di form edit: bila nilai tidak
     * berubah, rentang TIDAK dicek — baris placeholder import imunisasi (bb = 0) dan data lama ganjil
     * tetap bisa disimpan tanpa memaksa petugas memperbaikinya lebih dulu.
     */
    public static function rentang(?string $tglLahir, mixed $tglKunjungan, ?float $kgTersimpan = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($tglLahir, $tglKunjungan, $kgTersimpan): void {
            if (!is_numeric($value)) {
                return; // rule 'numeric' yang melaporkan
            }
            $satuan = S::untuk($tglLahir, is_string($tglKunjungan) ? $tglKunjungan : null);
            $nilai = (float) $value;
            if ($kgTersimpan !== null && S::sama(S::keKg($nilai, $satuan), $kgTersimpan)) {
                return;
            }
            if (!S::dalamRentang($nilai, $satuan)) {
                $fail(self::pesan($satuan));
            }
        };
    }

    public static function pesan(string $satuan): string
    {
        [$min, $maks] = S::RENTANG[$satuan];
        $angka = fn (int|float $n): string => number_format($n, 0, ',', '.');

        return $satuan === S::GRAM
            ? sprintf('Untuk umur di bawah 2 bulan, berat badan diisi dalam gram (%s–%s), mis. 3250.', $angka($min), $angka($maks))
            : sprintf('Berat badan diisi dalam kg (%s–%s), mis. 7.5.', $angka($min), $angka($maks));
    }

    /**
     * Nilai kg yang ditulis ke data_anak.bb. Tidak berubah dari yang tersimpan → nilai tersimpan
     * dikembalikan apa adanya (tanpa pembulatan float ulang).
     */
    public static function untukDisimpan(?string $tglLahir, string $tglKunjungan, mixed $input, mixed $bbTersimpan = null): mixed
    {
        $kg = S::keKg((float) $input, S::untuk($tglLahir, $tglKunjungan));
        if ($bbTersimpan !== null && S::sama($kg, (float) $bbTersimpan)) {
            return $bbTersimpan;
        }

        return $kg;
    }
}
