<?php

namespace App\Services;

use App\Models\Anak;
use App\Support\KeaslianNik;

/**
 * Satu aturan "apakah baris berkas ini anak yang sudah ada?" untuk importer yang MEMBUAT anak
 * (Import Anak, Kohort). Dipakai saat NIK baris tidak ketemu di DB (atau kosong/tak valid).
 *
 * Kunci pencocokan: nama (>=87%, tanpa beda huruf besar-kecil) + tgl lahir + jk, No KK tak
 * bertentangan — bukan NIK, karena 72% anak ganda di prod ber-NIK berbeda total (NIK ibu di kolom
 * anak, salah ketik, sumber berbeda). NIK mana yang bertahan ditentukan KeaslianNik.
 *
 *   0 kandidat  -> BARU   (pemanggil membuat anak baru)
 *   1 kandidat  -> SAMA   (perbarui anak itu; 'nik' = NIK pengganti, null = pertahankan NIK di DB)
 *  >1 kandidat  -> AMBIGU (dilaporkan, tidak ditebak)
 *
 * Import Operasi Timbang sengaja TIDAK memakai ini: jumlah anak OT harus sama dengan baris berkas.
 */
class PenautanAnakImport
{
    public const BARU   = 'baru';
    public const SAMA   = 'sama';
    public const AMBIGU = 'ambigu';

    public function __construct(private readonly NikDummyService $nikService)
    {
    }

    /**
     * @param  string|null  $nikBerkas  NIK valid dari berkas (15-16 digit), null bila kosong/tak valid
     * @return array{hasil:string, anak:?Anak, nik:?string, jumlah:int, tingkat:?string, pesan:?string}
     */
    public function tautkan(string $nama, ?string $tglLahir, string $jkChar, ?string $noKk, ?string $nikBerkas): array
    {
        $baru = ['hasil' => self::BARU, 'anak' => null, 'nik' => null, 'jumlah' => 0, 'tingkat' => null, 'pesan' => null];

        if ($tglLahir === null || trim($nama) === '') {
            return $baru;
        }

        $kandidat = $this->nikService->kandidat($nama, $tglLahir, $jkChar, $noKk, null);
        if ($kandidat->isEmpty()) {
            return $baru;
        }
        if ($kandidat->count() > 1) {
            return ['hasil' => self::AMBIGU, 'jumlah' => $kandidat->count()] + $baru;
        }

        /** @var Anak $ada */
        $ada = $kandidat->first();
        $sama = ['hasil' => self::SAMA, 'anak' => $ada, 'nik' => null, 'jumlah' => 1, 'tingkat' => 'INFO', 'pesan' => null];

        if ($nikBerkas === null) {
            $sama['pesan'] = NikDummyService::isDummy((string) $ada->nik)
                ? "NIK dummy {$ada->nik} dipakai ulang (anak yang sama)."
                : "dicocokkan dengan anak ber-NIK {$ada->nik} (nama + tgl lahir + jk), tidak dibuat anak baru.";

            return $sama;
        }

        $jk       = strtoupper($jkChar) === 'L' ? 1 : 2;
        $skorBaru = KeaslianNik::skor($nikBerkas, $tglLahir, $jk);
        $skorAda  = KeaslianNik::skor((string) $ada->nik, $tglLahir, $jk);

        if ($skorBaru > $skorAda) {
            if ($ada->sumber === 'operasi_timbang') {
                // Import OT memakai NIK sebagai kunci; mengubahnya membuat OT ganda saat diimpor ulang.
                $sama['tingkat'] = 'PERINGATAN';
                $sama['pesan']   = "NIK berkas {$nikBerkas} lebih real daripada NIK {$ada->nik}, tetapi NIK baris Operasi Timbang tidak diubah "
                    . '(dipakai sebagai kunci import OT); data tetap ditautkan ke anak itu.';

                return $sama;
            }

            $sama['nik']   = $nikBerkas;
            $sama['pesan'] = "NIK {$ada->nik} diganti NIK {$nikBerkas} yang lebih real (anak yang sama).";

            return $sama;
        }

        $sama['tingkat'] = 'PERINGATAN';
        $sama['pesan']   = $skorBaru === $skorAda
            ? "NIK berkas {$nikBerkas} dan NIK di DB {$ada->nik} sama-sama tak lebih real satu dari yang lain; NIK di DB dipertahankan, periksa salah ketik."
            : "NIK berkas {$nikBerkas} kurang real daripada NIK di DB {$ada->nik}; NIK di DB dipertahankan.";

        return $sama;
    }
}
