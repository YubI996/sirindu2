<?php

namespace App\Services;

use App\Models\Anak;

/**
 * Cocokkan satu baris operasi timbang (e-PPGBM) ke record Anak yang sudah ada.
 *
 * NIK e-PPGBM disensor & tidak punya daya pembeda, jadi diabaikan. Gerbang
 * kandidat: tgl_lahir + jk exact.
 *
 * - Anak sudah bernama: lolos bila nama mirip (similar_text >= ambang) ATAU
 *   salah satu nama merupakan subset/awalan yang lain (mis. "MAUREEN BEA" vs
 *   "MAUREEN BEA KHALIQA").
 * - Bayi belum bernama (placeholder "BY NY ...", "BAYI ...", "BY IBU ..."):
 *   perbandingan nama dilewati; pencocokan bertumpu pada Nama Ortu (dari kolom
 *   Nama Ortu + sisa nama setelah prefix "BY NY", yang biasanya nama ibu).
 *
 * Bila kandidat >1, dibedakan (tie-break) pakai Nama Ortu (toleran urutan
 * ibu/ayah tertukar & versi digabung) dan Kelurahan.
 */
class OperasiTimbangMatcher
{
    public function __construct(private int $minNama = 88) {}

    /**
     * @return array{status:string, anak:?Anak, kandidat:array, alasan:?string}
     */
    public function match(
        string $nama,
        ?string $tglLahir,
        ?string $jk,
        ?string $namaOrtu = null,
        ?string $kelurahan = null,
    ): array {
        if (empty($tglLahir)) {
            return ['status' => 'TAK_COCOK', 'anak' => null, 'kandidat' => [], 'alasan' => 'Tanggal lahir kosong/tidak valid — tidak dapat dicocokkan.'];
        }

        $jkValue = strtoupper(trim((string) $jk)) === 'L' ? 1 : 2;

        // Gerbang kandidat: tgl_lahir + jk exact. Sertakan nama kelurahan (via id_kel)
        // untuk pembeda; left join agar anak tanpa domisili tetap ikut.
        $kandidat = Anak::query()
            ->leftJoin('kelurahan', 'anak.id_kel', '=', 'kelurahan.id')
            ->whereDate('anak.tgl_lahir', $tglLahir)
            ->where('anak.jk', $jkValue)
            ->get(['anak.id', 'anak.nama', 'anak.tgl_lahir', 'anak.nama_ibu', 'anak.nama_ayah', 'kelurahan.name as kel_name']);

        // Bayi belum bernama → nama tak bisa dipakai; ambil nama ortu dari kolom
        // + sisa placeholder ("BY NY MEIRINA" → "MEIRINA").
        $belumBernama = $this->belumBernama($nama);
        $ortuEfektif  = $this->ortuEfektif($namaOrtu, $belumBernama ? $this->buangPrefixBayi($nama) : null);

        $lolos = $kandidat->filter(function ($a) use ($belumBernama, $nama, $ortuEfektif) {
            return $belumBernama
                ? ($ortuEfektif !== '' && $this->cocokOrtu($ortuEfektif, $a))
                : $this->namaCocok($nama, $a->nama);
        })->values();

        if ($lolos->isEmpty()) {
            $alasan = $belumBernama
                ? 'Bayi belum bernama & nama orang tua tidak cocok pada tgl lahir & jenis kelamin yang sama.'
                : 'Tidak ada anak dengan nama mirip pada tgl lahir & jenis kelamin yang sama.';
            return ['status' => 'TAK_COCOK', 'anak' => null, 'kandidat' => [], 'alasan' => $alasan];
        }

        if ($lolos->count() === 1) {
            return ['status' => 'COCOK', 'anak' => $lolos->first(), 'kandidat' => $lolos->all(), 'alasan' => null];
        }

        // >1 kandidat → tie-break: skor per kandidat dari kecocokan ortu + kelurahan.
        $scored = $lolos->map(fn ($a) => [
            'anak' => $a,
            'skor' => ($ortuEfektif !== '' && $this->cocokOrtu($ortuEfektif, $a) ? 1 : 0)
                    + (!empty($kelurahan) && !empty($a->kel_name) && $this->mirip($kelurahan, $a->kel_name) ? 1 : 0),
        ]);

        $maxSkor = (int) $scored->max('skor');
        if ($maxSkor >= 1) {
            $teratas = $scored->where('skor', $maxSkor)->values();
            if ($teratas->count() === 1) {
                return ['status' => 'COCOK', 'anak' => $teratas->first()['anak'], 'kandidat' => $lolos->all(), 'alasan' => null];
            }
        }

        return ['status' => 'AMBIGU', 'anak' => null, 'kandidat' => $lolos->all(), 'alasan' => "Ditemukan {$lolos->count()} anak mirip; tidak dapat dibedakan otomatis."];
    }

    /** Nama cocok bila fuzzy >= ambang ATAU salah satu subset/awalan yang lain. */
    private function namaCocok(string $fileNama, string $dbNama): bool
    {
        if ($this->mirip($fileNama, $dbNama)) return true;
        return $this->subset($this->norm($fileNama), $this->norm($dbNama));
    }

    /** True bila string pendek (>=5 char) adalah awalan/kata utuh di string panjang. */
    private function subset(string $a, string $b): bool
    {
        [$pendek, $panjang] = strlen($a) <= strlen($b) ? [$a, $b] : [$b, $a];
        if (strlen($pendek) < 5) return false;

        return str_starts_with($panjang, $pendek)
            || str_ends_with($panjang, ' ' . $pendek)
            || str_contains($panjang, ' ' . $pendek . ' ');
    }

    private function mirip(string $a, string $b): bool
    {
        similar_text($this->norm($a), $this->norm($b), $pct);
        return $pct >= $this->minNama;
    }

    private function norm(string $s): string
    {
        return strtoupper(trim($s));
    }

    /** Deteksi placeholder bayi belum bernama: "BY", "BY NY", "BY IBU", "BAYI". */
    private function belumBernama(string $nama): bool
    {
        return (bool) preg_match('#^(BY|BAYI)([\s./,]|$)#', $this->norm($nama));
    }

    /** Buang prefix "BY NY / BAYI / IBU / NYONYA / TN" di awal → sisa (nama ortu). */
    private function buangPrefixBayi(string $nama): string
    {
        $rem = preg_replace('#^((BY|BAYI|NY|NYONYA|IBU|TN|TUAN|AN)[\s./,]+)+#', '', $this->norm($nama));
        return trim((string) $rem);
    }

    /** Gabungkan sumber nama ortu (kolom + sisa placeholder) jadi satu string. */
    private function ortuEfektif(?string $namaOrtu, ?string $extra): string
    {
        $bagian = array_filter([trim((string) $namaOrtu), trim((string) $extra)], fn ($s) => $s !== '');
        return implode(' / ', $bagian);
    }

    /**
     * Cocokkan Nama Ortu file dengan nama_ibu/nama_ayah kandidat.
     * Toleran urutan tertukar (bandingkan tiap bagian ke kedua field) dan
     * versi digabung (bandingkan string utuh ke gabungan ibu+ayah).
     */
    private function cocokOrtu(string $namaOrtu, Anak $a): bool
    {
        $bagian = array_filter(array_map('trim', preg_split('#[/,;]+#', $namaOrtu)));
        $target = array_filter([$a->nama_ibu, $a->nama_ayah]);

        // Per-bagian × per-field (toleran ibu/ayah tertukar).
        foreach ($bagian as $o) {
            foreach ($target as $t) {
                if ($this->mirip($o, (string) $t)) return true;
            }
        }

        // Digabung: string ortu utuh vs gabungan ibu+ayah (dua urutan).
        if (!empty($target)) {
            $combo1 = trim(($a->nama_ibu ?? '') . ' ' . ($a->nama_ayah ?? ''));
            $combo2 = trim(($a->nama_ayah ?? '') . ' ' . ($a->nama_ibu ?? ''));
            foreach ([$combo1, $combo2] as $c) {
                if ($c !== '' && $this->mirip($namaOrtu, $c)) return true;
            }
        }

        return false;
    }
}
