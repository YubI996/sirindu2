<?php

namespace App\Traits;

use App\Models\Anak;
use App\Services\PenautanAnakImport;

/**
 * Bagian bersama importer yang menautkan baris ke anak yang sudah ada (lihat PenautanAnakImport).
 * Mengandalkan properti $failures dan $errorCount milik kelas pemakai.
 */
trait MenautkanAnakImport
{
    /**
     * Kolom yang TIDAK ditimpa saat anak yang sudah ada dicocokkan lewat nama+tgl lahir.
     * Identitas (nama/tgl lahir/jk) sudah menjadi dasar pencocokan, dan `no`/`status`
     * di $data hanyalah default (nomor IMP- otomatis, status 1) — bukan isi berkas.
     */
    private const JANGAN_TIMPA = ['nama', 'tgl_lahir', 'jk', 'no', 'status'];

    private ?PenautanAnakImport $penautanAnakInstance = null;

    /**
     * Perbarui anak yang sudah ada dengan aturan "isi yang diberikan": hanya kolom
     * ber-nilai di berkas yang menimpa. Baris yang dicocokkan lewat nama+tgl lahir adalah sumber
     * yang lebih lemah dari baris ber-NIK, jadi sel kosong tak boleh mengosongkan data lama
     * (mis. NIK ibu & wilayah dari Capil/Operasi Timbang).
     *
     * @param  string|null  $nikBaru   bila diisi, NIK anak diganti (lebih real)
     * @param  string[]     $bolehTimpa kolom yang biasanya dijaga (JANGAN_TIMPA) tetapi di sini boleh ditimpa
     *                                 — mis. jalur NIK, tempat identitas boleh dikoreksi berkas
     */
    protected function perbaruiAnakAda(int $id, array $data, ?string $nikBaru = null, array $bolehTimpa = []): Anak
    {
        $isi = array_diff_key(
            array_filter($data, fn ($v) => $v !== null),
            array_flip(array_diff(self::JANGAN_TIMPA, $bolehTimpa))
        );

        if ($nikBaru !== null) {
            $isi['nik'] = $nikBaru;
        }

        $anak = Anak::findOrFail($id);
        $anak->update($isi);

        return $anak;
    }

    /** Lebih dari satu anak cocok -> laporkan, jangan menebak (pola ImunisasiImport). */
    protected function laporkanAmbigu(int $rowNum, string $nama, ?string $tglLahir, int $jumlah): void
    {
        $this->failures[] = "[ERROR] Baris {$rowNum} ({$nama}): Ditemukan {$jumlah} anak bernama '{$nama}' lahir '{$tglLahir}'. Lengkapi kolom nik.";
        $this->errorCount++;
    }

    /** Catat hasil penautan (SAMA) sebagai baris informasi/peringatan. */
    protected function catatTautan(int $rowNum, string $nama, array $tautan, string $awalan = ''): void
    {
        $this->failures[] = "[{$tautan['tingkat']}] Baris {$rowNum} ({$nama}): {$awalan}{$tautan['pesan']}";
    }

    /** Awalan pesan untuk NIK yang kosong atau tidak valid (kosong bila NIK valid). */
    protected function awalanNikTakValid(bool $nikValid, string $nikMentah): string
    {
        if ($nikValid) {
            return '';
        }

        return $nikMentah === '' ? 'NIK kosong — ' : "NIK '{$nikMentah}' tidak valid — ";
    }

    protected function penautanAnak(): PenautanAnakImport
    {
        return $this->penautanAnakInstance ??= new PenautanAnakImport($this->nikService);
    }
}
