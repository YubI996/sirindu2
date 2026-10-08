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
 * Kandidat yang jelas ORANG LAIN disingkirkan lebih dulu (lihat bukanAnakYangSama): anak kembar
 * (NIK berurutan, nama beda) dan anak dengan nama ibu berbeda. Tanpa ini, kembar bernama mirip
 * (Zayyan/Rayyan) digabung jadi satu — 5 anak hilang dari satu berkas import Okt 2026.
 *
 * Import Operasi Timbang sengaja TIDAK memakai ini: jumlah anak OT harus sama dengan baris berkas.
 */
class PenautanAnakImport
{
    public const BARU   = 'baru';
    public const SAMA   = 'sama';
    public const AMBIGU = 'ambigu';

    /** Selisih nomor urut NIK (4 digit akhir) yang masih dianggap kembar — 3 menampung kembar tiga/empat. */
    private const SELISIH_URUT_KEMBAR = 3;

    public function __construct(private readonly NikDummyService $nikService)
    {
    }

    /**
     * @param  string|null  $nikBerkas  NIK valid dari berkas (15-16 digit), null bila kosong/tak valid
     * @param  string|null  $namaIbu    nama ibu dari berkas, null bila kolomnya tak ada/kosong
     * @return array{hasil:string, anak:?Anak, nik:?string, jumlah:int, tingkat:?string, pesan:?string}
     */
    public function tautkan(string $nama, ?string $tglLahir, string $jkChar, ?string $noKk, ?string $nikBerkas, ?string $namaIbu = null): array
    {
        $baru = ['hasil' => self::BARU, 'anak' => null, 'nik' => null, 'jumlah' => 0, 'tingkat' => null, 'pesan' => null];

        if ($tglLahir === null || trim($nama) === '') {
            return $baru;
        }

        $kandidat = $this->nikService->kandidat($nama, $tglLahir, $jkChar, $noKk, null)
            ->reject(fn (Anak $ada) => self::bukanAnakYangSama($nama, $nikBerkas, $namaIbu, $ada))
            ->values();
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

    /**
     * Kandidat yang mirip nama + tgl lahir + jk tetapi jelas orang lain:
     *
     *  - Kembar: kedua NIK 16 digit asli (bukan dummy) dengan 12 digit awal sama (wilayah + tgl lahir),
     *    nomor urut beda tapi berdekatan (≤ SELISIH_URUT_KEMBAR), dan namanya beda. Dukcapil memberi
     *    nomor urut berurutan untuk kembar. Nomor urut berjauhan (mis. 3723/6107, buatan e-PPGBM) bukan
     *    tanda kembar. Nama yang sama persis dengan nomor urut beda tetap dianggap salah ketik.
     *  - Nama ibu berbeda: keduanya terisi, utuhnya tak mirip (<80%), dan TAK ADA satu kata pun yang mirip
     *    (≥80%). Per kata karena berkas sering menulis "ayah / ibu" ("MUHERMI / AISYA" vs "AISYAH"); utuh
     *    karena nama kadang terpotong ("FEBRI / ANTI" vs "FEBRIYANTI"). Isian asal ("ADA", "-") dianggap
     *    kosong. Ragu -> tidak dipisahkan (perilaku lama).
     */
    private static function bukanAnakYangSama(string $nama, ?string $nikBerkas, ?string $namaIbu, Anak $ada): bool
    {
        $nikAda = (string) $ada->nik;

        if (self::nikAsli($nikBerkas) && self::nikAsli($nikAda)
            && substr($nikBerkas, 0, 12) === substr($nikAda, 0, 12)
            && $nikBerkas !== $nikAda
            && abs((int) substr($nikBerkas, 12) - (int) substr($nikAda, 12)) <= self::SELISIH_URUT_KEMBAR
            && self::huruf($nama) !== self::huruf((string) $ada->nama)) {
            return true;
        }

        $kataBerkas = self::kataNama((string) $namaIbu);
        $kataAda    = self::kataNama((string) $ada->nama_ibu);
        if ($kataBerkas === [] || $kataAda === []) {
            return false;
        }
        // Utuh tanpa spasi dulu: nama yang ditulis terpotong ("FEBRI / ANTI" = "FEBRIYANTI").
        similar_text(implode('', $kataBerkas), implode('', $kataAda), $pct);
        if ($pct >= 80) {
            return false;
        }
        foreach ($kataBerkas as $a) {
            foreach ($kataAda as $b) {
                similar_text($a, $b, $pct);
                if ($pct >= 80) {
                    return false;
                }
            }
        }

        return true;
    }

    /** Isian nama ibu yang bukan nama — dianggap kosong. */
    private const BUKAN_NAMA = ['ada', 'tidak', 'tdk', 'belum', 'nn', 'na', 'null', 'kosong'];

    /** Kata-kata nama (huruf kecil, ≥3 huruf), tanpa isian asal. */
    private static function kataNama(string $teks): array
    {
        $kata = preg_split('/[^a-z]+/', mb_strtolower($teks), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter($kata, fn ($k) => strlen($k) >= 3 && !in_array($k, self::BUKAN_NAMA, true)));
    }

    private static function nikAsli(?string $nik): bool
    {
        return $nik !== null && strlen($nik) === 16 && ctype_digit($nik) && !NikDummyService::isDummy($nik);
    }

    /** Huruf kecil saja — spasi, tanda baca, dan besar-kecil huruf tidak membedakan nama. */
    private static function huruf(string $teks): string
    {
        return preg_replace('/[^a-z]/', '', mb_strtolower($teks));
    }
}
