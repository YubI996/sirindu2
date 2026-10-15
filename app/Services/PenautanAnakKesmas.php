<?php

namespace App\Services;

use App\Models\Anak;

/**
 * Penautan baris Import Data Kesmas ke anak yang SUDAH ADA (spec 2026-10-15-import-kesmas-design.md §4).
 * Hanya mencari: tidak membuat anak, tidak mengubah NIK.
 *
 * NIK dipercaya hanya bila tgl lahir sama ATAU nama mirip — 72% anak ganda di prod ber-NIK ibu atau
 * salah ketik, dan salah tautan di sini berarti data Kesmas menempel ke anak orang lain tanpa jejak.
 */
class PenautanAnakKesmas
{
    private const MIRIP = 87;

    /** Isian nama ibu yang bukan nama (selaras PenautanAnakImport::BUKAN_NAMA). */
    private const BUKAN_NAMA_IBU = ['ada', 'tidak', 'tdk', 'belum', 'nn', 'na', 'null', 'kosong'];

    private PenautanAnakImport $penautan;

    public function __construct(?PenautanAnakImport $penautan = null)
    {
        $this->penautan = $penautan ?? new PenautanAnakImport(new NikDummyService());
    }

    /** @return array{anak: ?Anak, pesan: list<array{0:string,1:string}>} */
    public function cari(string $nama, string $jk, string $tglLahir, string $namaOrtu, string $nik): array
    {
        $pesan = [];
        $nik = substr($nik, 0, 16);
        $nikSah = strlen($nik) >= 15 && ctype_digit($nik);

        // NIK yang ternyata milik anak lain bukan bukti apa pun — jangan diteruskan ke pencocok nama.
        $nikUntukTautan = $nikSah ? $nik : null;

        if ($nikSah) {
            // anak.nik UNIQUE di DB — paling banyak satu anak.
            if ($ada = Anak::where('nik', $nik)->first()) {
                $lahirAda = substr((string) $ada->tgl_lahir, 0, 10);
                if (self::huruf($nama) === self::huruf((string) $ada->nama)) {
                    return ['anak' => $ada, 'pesan' => []];
                }
                $mirip = self::mirip($nama, (string) $ada->nama);
                // Kembar lahir di hari yang sama dan namanya mirip, jadi "nama mirip" saja lolos untuk kembaran
                // yang salah. Saudara yang namanya lebih cocok = NIK di berkas milik kembarannya.
                if ($mirip && ! self::adaSaudaraLebihCocok($ada, $nama)) {
                    return ['anak' => $ada, 'pesan' => []];
                }
                // Nama tak mirip (nama panggilan, "Budi" vs "Muhammad Budi Santoso"): tanggal lahir saja tidak cukup
                // — NIK salah ketik bisa menunjuk anak lain yang lahir di hari yang sama (audit SEC-001, Okt 2026).
                // Diterima hanya bila jk sama DAN nama ibu di berkas & di data sama-sama terisi dan cocok.
                if (! $mirip && $lahirAda === $tglLahir && self::ibuDanJkCocok($ada, $jk, $namaOrtu, $nama)) {
                    return ['anak' => $ada, 'pesan' => [['INFO', "nama di berkas ({$nama}) berbeda dengan data ({$ada->nama}); "
                        .'dicocokkan lewat NIK + tgl lahir + jk + nama ibu.']]];
                }
                $cocok = $mirip || $lahirAda === $tglLahir;
                $pesan[] = ['PERINGATAN', $cocok
                    ? "NIK {$nik} milik {$ada->nama}, tetapi ada anak lain lahir {$lahirAda} yang namanya lebih cocok — dicocokkan lewat nama."
                    : "NIK {$nik} tercatat untuk anak lain ({$ada->nama}, lahir {$lahirAda}) — dicocokkan lewat nama."];
                $nikUntukTautan = null;
            }
        }

        $t = $this->penautan->tautkan($nama, $tglLahir, $jk, null, $nikUntukTautan, $namaOrtu);

        if ($t['hasil'] === PenautanAnakImport::SAMA) {
            $pesan[] = ['INFO', "dicocokkan lewat nama + tgl lahir + jk dengan anak ber-NIK {$t['anak']->nik}"
                .($nikSah ? "; NIK berkas {$nik} tidak dipakai dan NIK di data tidak diubah." : '.')];

            return ['anak' => $t['anak'], 'pesan' => $pesan];
        }

        $pesan[] = ['ERROR', $t['hasil'] === PenautanAnakImport::AMBIGU
            ? "ditemukan {$t['jumlah']} anak yang cocok (nama + tgl lahir + jk) — isi nik_anak supaya pasti."
            : 'anak tidak ditemukan (nama + tgl lahir + jk + nama ortu) — tambahkan dulu lewat Tambah Anak atau Import Anak.'];

        return ['anak' => null, 'pesan' => $pesan];
    }

    private static function mirip(string $a, string $b): bool
    {
        return self::skor($a, $b) >= self::MIRIP;
    }

    private static function skor(string $a, string $b): float
    {
        similar_text(mb_strtolower(trim($a)), mb_strtolower(trim($b)), $pct);

        return $pct;
    }

    /** Huruf saja, kecil semua — "MUHAMMAD ZAYYAN" sama dengan "Muhammad  Zayyan". */
    private static function huruf(string $teks): string
    {
        return preg_replace('/[^a-z]/', '', mb_strtolower($teks)) ?? '';
    }

    /** jk sama, dan nama ibu di berkas & data sama-sama terisi serta tidak "jelas berbeda" (aturan PenautanAnakImport). */
    private static function ibuDanJkCocok(Anak $ada, string $jk, string $namaOrtu, string $nama): bool
    {
        if ((int) $ada->jk !== (strtoupper($jk) === 'L' ? 1 : 2)) {
            return false;
        }
        // Ragu = tidak diterima: tanpa nama ibu tak ada bukti kedua selain tanggal lahir. bukanAnakYangSama()
        // menganggap isian kosong/asal ("ADA", "-") sebagai "ragu → bukan beda", jadi dicegat di sini.
        foreach ([(string) $ada->nama_ibu, $namaOrtu] as $ibu) {
            if (strlen(self::huruf($ibu)) < 3 || in_array(self::huruf($ibu), self::BUKAN_NAMA_IBU, true)) {
                return false;
            }
        }

        return ! PenautanAnakImport::bukanAnakYangSama($nama, null, $namaOrtu, $ada);
    }

    /** Ada anak lain lahir di hari yang sama yang namanya lebih mirip dengan nama di berkas? */
    private static function adaSaudaraLebihCocok(Anak $ada, string $nama): bool
    {
        $skorAda = self::skor($nama, (string) $ada->nama);

        return Anak::where('tgl_lahir', $ada->tgl_lahir)->where('id', '!=', $ada->id)->pluck('nama')
            ->contains(fn ($lain) => self::skor($nama, (string) $lain) > $skorAda);
    }
}
