<?php

namespace App\Traits;

use App\Exceptions\FormatTanggalAmbigu;
use App\Support\TanggalBerkas;

/**
 * Dipakai kelas Import yang membaca kolom tanggal dari berkas petugas.
 *
 * Pemakaian: job memanggil setFormatTanggal() dari import_logs, lalu collection()
 * memanggil kunciFormatTanggal() pada potongan pertama SEBELUM baris disimpan.
 * Setelah itu bacaTanggal() memakai kesimpulan yang sama untuk seluruh berkas.
 */
trait MembacaTanggalBerkas
{
    /** Pilihan petugas: 'auto' | 'dmy' | 'mdy'. */
    protected string $formatTanggalPilihan = TanggalBerkas::OTOMATIS;

    /** Kesimpulan yang dipakai; null selama potongan pertama belum dipindai. */
    protected ?string $formatTanggalTerpakai = null;

    public function setFormatTanggal(?string $format): static
    {
        $this->formatTanggalPilihan = in_array($format, [TanggalBerkas::DMY, TanggalBerkas::MDY], true)
            ? $format
            : TanggalBerkas::OTOMATIS;

        return $this;
    }

    public function formatTanggalTerpakai(): string
    {
        return $this->formatTanggalTerpakai ?? $this->formatTanggalPilihan;
    }

    /**
     * Kunci format dari potongan pertama berkas.
     *
     * Baris disimpan sambil berjalan, jadi keputusan yang datang di potongan ke-3
     * tak bisa menarik kembali baris yang sudah masuk — potongan pertama itulah
     * batas buktinya, dan itu disengaja.
     *
     * @throws FormatTanggalAmbigu kalau petugas memilih "Otomatis" tapi berkasnya tak menentukan
     */
    protected function kunciFormatTanggal(iterable $nilai): void
    {
        if ($this->formatTanggalTerpakai !== null) return;

        if ($this->formatTanggalPilihan !== TanggalBerkas::OTOMATIS) {
            $this->formatTanggalTerpakai = $this->formatTanggalPilihan;
            return;
        }

        $nilai = is_array($nilai) ? $nilai : iterator_to_array($nilai, false);
        $hasil = TanggalBerkas::deteksi($nilai);

        if ($hasil === TanggalBerkas::AMBIGU) {
            throw new FormatTanggalAmbigu(TanggalBerkas::contohAmbigu($nilai));
        }

        // KOSONG: berkas tak punya triplet sama sekali (sel tanggal Excel / 2020-01-15).
        $this->formatTanggalTerpakai = $hasil === TanggalBerkas::KOSONG ? TanggalBerkas::OTOMATIS : $hasil;
    }

    protected function bacaTanggal($nilai): ?string
    {
        return TanggalBerkas::parse($nilai, $this->formatTanggalTerpakai());
    }
}
