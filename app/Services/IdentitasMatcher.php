<?php

namespace App\Services;

/**
 * Aturan kemiripan identitas anak — satu-satunya sumber kebenaran, dipakai
 * CapilDedupService (dedup impor Capil) dan TautanIdentitasService (kandidat untuk RT).
 *
 * Kalibrasi asli (kontrol +/-, FP ~0,2%): lihat komentar CapilDedupService.
 *   1. tgl lahir TEPAT sama  → nama anak >= CHILD_MIN      dan (No KK sama atau ortu >= PARENT_MIN)
 *   2. tgl lahir meleset ±1  → nama anak >= CHILD_NEAR_MIN dan syarat yang sama
 *   3. nama >= CHILD_STRONG dan ortu >= PARENT_STRONG → tanggal diabaikan (typo tahun/bulan)
 */
class IdentitasMatcher
{
    public const CHILD_MIN = 70.0;
    public const CHILD_NEAR_MIN = 90.0;
    public const PARENT_MIN = 87.0;
    public const DATE_TOLERANCE_DAYS = 1;
    public const CHILD_STRONG = 95.0;
    public const PARENT_STRONG = 95.0;
    public const NAME_BLOCK_LEN = 3;

    /** Kemiripan dua string (persen, case-insensitive). */
    public function nameSim(?string $a, ?string $b): float
    {
        similar_text(trim(mb_strtolower((string) $a)), trim(mb_strtolower((string) $b)), $pct);
        return $pct;
    }

    /** Token nama ortu (nama_ibu + nama_ayah), dipecah pada '/' — sigizi sering "IBU / AYAH" di satu kolom. */
    private function parentTokens(object $a): array
    {
        $tokens = [];
        foreach (['nama_ibu', 'nama_ayah'] as $field) {
            foreach (explode('/', (string) ($a->$field ?? '')) as $seg) {
                $seg = trim(mb_strtolower($seg));
                if ($seg !== '') {
                    $tokens[] = $seg;
                }
            }
        }
        return $tokens;
    }

    /** Kemiripan terbaik antar-segmen nama ortu kedua record (persen). */
    public function parentMatch(object $a, object $b): float
    {
        $best = 0.0;
        foreach ($this->parentTokens($a) as $x) {
            foreach ($this->parentTokens($b) as $y) {
                similar_text($x, $y, $pct);
                if ($pct > $best) {
                    $best = $pct;
                }
            }
        }
        return $best;
    }

    public function kkSame(object $a, object $b): bool
    {
        $x = trim((string) ($a->no_kk ?? ''));
        $y = trim((string) ($b->no_kk ?? ''));
        return $x !== '' && $y !== '' && $x === $y;
    }

    public function dateKey($value): string
    {
        return substr((string) $value, 0, 10);
    }

    /** Kunci blocking nama: prefiks nama ternormalisasi. */
    public function nameKey(?string $nama): string
    {
        return mb_substr(trim(mb_strtolower((string) $nama)), 0, self::NAME_BLOCK_LEN);
    }

    /** Tgl tepat + tetangga dalam toleransi. */
    public function neighborDateKeys($value): array
    {
        $ts = strtotime($this->dateKey($value));
        if ($ts === false) {
            return [$this->dateKey($value)];
        }
        $keys = [];
        for ($d = -self::DATE_TOLERANCE_DAYS; $d <= self::DATE_TOLERANCE_DAYS; $d++) {
            $keys[] = date('Y-m-d', $ts + $d * 86400);
        }
        return $keys;
    }

    /** Selisih hari (mutlak); PHP_INT_MAX bila tak terbaca. */
    public function dayDiff($a, $b): int
    {
        $ta = strtotime($this->dateKey($a));
        $tb = strtotime($this->dateKey($b));
        if ($ta === false || $tb === false) {
            return PHP_INT_MAX;
        }
        return (int) round(abs($ta - $tb) / 86400);
    }

    /**
     * Nilai satu pasangan. null bila tak memenuhi aturan.
     *
     * @return array{via:string, score:float, child:float, parent:float}|null
     */
    public function evaluate(object $x, object $y): ?array
    {
        $child  = $this->nameSim($x->nama ?? null, $y->nama ?? null);
        $parent = $this->parentMatch($x, $y);
        $kkSame = $this->kkSame($x, $y);
        $diff   = $this->dayDiff($x->tgl_lahir ?? null, $y->tgl_lahir ?? null);
        $exactDate = $diff === 0;

        $strongName = $child >= self::CHILD_STRONG && $parent >= self::PARENT_STRONG;

        if (!$strongName) {
            if ($diff > self::DATE_TOLERANCE_DAYS) {
                return null;
            }
            if ($child < ($exactDate ? self::CHILD_MIN : self::CHILD_NEAR_MIN)) {
                return null;
            }
            if (!$kkSame && $parent < self::PARENT_MIN) {
                return null;
            }
        }

        $via = $kkSame ? 'kk' : 'ortu';
        if ($strongName && $diff > self::DATE_TOLERANCE_DAYS) {
            $via = 'nama_kuat'; // lolos hanya karena identitas nama sangat kuat
        }

        return [
            'via'    => $via,
            'score'  => ($kkSame ? 1000.0 : 0.0) + ($exactDate ? 100.0 : 0.0) + $parent + $child,
            'child'  => $child,
            'parent' => $parent,
        ];
    }

    /**
     * Semua pasangan kandidat di satu himpunan anak (lintas sumber), unik & terurut a<b.
     * Dikecualikan: NIK sama (mustahil di DB, jaga-jaga) dan OT×OT (spec verifikasi RT §5).
     *
     * @param  iterable<object> $anak  objek dengan id, nik, nama, tgl_lahir, no_kk, nama_ibu, nama_ayah, sumber
     * @return array<int, array{a:int, b:int, via:string, score:float, child:float, parent:float}>
     */
    public function pindaiSemua(iterable $anak): array
    {
        $rows = [];
        $byTgl = [];
        $byName = [];
        foreach ($anak as $r) {
            $rows[$r->id] = $r;
            $byTgl[$this->dateKey($r->tgl_lahir ?? null)][] = $r->id;
            $byName[$this->nameKey($r->nama ?? null)][] = $r->id;
        }

        $hasil = [];
        foreach ($rows as $id => $x) {
            $bucket = [];
            foreach ($this->neighborDateKeys($x->tgl_lahir ?? null) as $key) {
                foreach ($byTgl[$key] ?? [] as $other) {
                    $bucket[$other] = true;
                }
            }
            foreach ($byName[$this->nameKey($x->nama ?? null)] ?? [] as $other) {
                $bucket[$other] = true;
            }

            foreach (array_keys($bucket) as $otherId) {
                if ($otherId <= $id) {
                    continue; // tiap pasangan dinilai sekali, dari sisi id kecil
                }
                $y = $rows[$otherId];
                if ((string) ($x->nik ?? '') === (string) ($y->nik ?? '')) {
                    continue;
                }
                if (($x->sumber ?? null) === 'operasi_timbang' && ($y->sumber ?? null) === 'operasi_timbang') {
                    continue;
                }
                if ($info = $this->evaluate($x, $y)) {
                    $hasil[] = ['a' => (int) $id, 'b' => (int) $otherId] + $info;
                }
            }
        }

        return $hasil;
    }
}
