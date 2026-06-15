<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Satu sumber kebenaran klasifikasi status gizi balita berbasis standar WHO/Kemenkes.
 *
 * Sebelumnya rumus z-score + koreksi posisi diduplikasi di banyak tempat
 * (helpers.php::z_score, AdminController show & peta, TimbangDashboardController::gizi)
 * dengan perbedaan halus (mis. batas var di 24 bln). Service ini menyatukannya.
 *
 * Empat indikator dihitung dari tabel referensi `z_score`:
 *   - IMT/U  (jenis_tbl=1, acuan=umur bln, var sesuai umur)
 *   - BB/U   (jenis_tbl=2, acuan=umur bln, var=1 selalu)
 *   - TB/U   (jenis_tbl=3, acuan=umur bln, var sesuai umur)
 *   - BB/TB  (jenis_tbl=4, acuan=tinggi terkoreksi, var sesuai umur)
 *
 * Koreksi posisi (±0.7 cm) menyelaraskan panjang (terlentang) & tinggi (berdiri):
 *   umur <24 & berdiri (H)   → +0.7 (basis panjang)
 *   umur >=24 & terlentang (L) → -0.7 (basis tinggi)
 * Batas var konsisten: umur <24 → var 1 (panjang), >=24 → var 2 (tinggi).
 */
class StatusGiziService
{
    /** Cache seluruh baris z_score, dikunci "jenis_tbl_jk_acuan_var". */
    private static ?array $refs = null;

    /**
     * Klasifikasi lengkap satu pengukuran.
     *
     * @return array{
     *   bln:int, tinggi:float, berat:float, bmi:float,
     *   imt:string, bb:string, tb:string, bt:string,
     *   enum:array{imt_u:?string,bb_u:?string,tb_u:?string,bb_tb:?string},
     *   tersedia:bool
     * }
     */
    public function klasifikasi($bb, $tb, $bln, $posisi, $jk): array
    {
        $bln = (int) $bln;
        $bb = (float) $bb;
        $jk = (int) $jk;

        $tinggi = round($this->koreksiTinggi($tb, $bln, $posisi));
        $var = $bln < 24 ? 1 : 2;
        $bmi = $tinggi > 0 ? round(10000 * $bb / pow($tinggi, 2), 2) : 0.0;

        $imtRef  = $this->ref(1, $jk, $bln, $var);
        $bbRef   = $this->ref(2, $jk, $bln, 1);
        $tbRef   = $this->ref(3, $jk, $bln, $var);
        $btRef   = $this->ref(4, $jk, (int) $tinggi, $var);

        // Tiap indikator dihitung independen; enum null bila baris referensinya
        // tak ada (mis. tinggi di luar jangkauan tabel BB/TB) agar konsumen
        // agregasi tetap menghitung indikator lain.
        $imtEnum = $imtRef ? $this->klasifikasiImt($bmi, $imtRef, $bln) : null;
        $bbEnum  = $bbRef ? $this->klasifikasiBb($bb, $bbRef) : null;
        $tbEnum  = $tbRef ? $this->klasifikasiTb($tinggi, $tbRef) : null;
        $btEnum  = $btRef ? $this->klasifikasiBbTb($bb, $btRef) : null;

        $tidakAda = 'Data Z-Score tidak tersedia';

        return [
            'bln' => $bln,
            'tinggi' => $tinggi,
            'berat' => $bb,
            'bmi' => $bmi,
            'imt' => $imtEnum ? self::labelImt($imtEnum, $bln) : $tidakAda,
            'bb'  => $bbEnum ? self::labelBb($bbEnum) : $tidakAda,
            'tb'  => $tbEnum ? self::labelTb($tbEnum) : $tidakAda,
            'bt'  => $btEnum ? self::labelBbTb($btEnum) : $tidakAda,
            'enum' => [
                'imt_u' => $imtEnum,
                'bb_u'  => $bbEnum,
                'tb_u'  => $tbEnum,
                'bb_tb' => $btEnum,
            ],
            // tersedia = keempat indikator punya referensi (dipakai tampilan show
            // yang menampilkan "tidak tersedia" untuk semua bila ada yg kurang).
            'tersedia' => $imtRef && $bbRef && $tbRef && $btRef,
        ];
    }

    /** Tinggi terkoreksi posisi (belum dibulatkan). */
    public function koreksiTinggi($tb, $bln, $posisi): float
    {
        $tb = (float) $tb;
        $bln = (int) $bln;
        $pos = strtoupper(trim((string) $posisi));
        if ($bln < 24 && $pos === 'H') {
            return $tb + 0.7;
        }
        if ($bln >= 24 && $pos === 'L') {
            return $tb - 0.7;
        }
        return $tb;
    }

    // --- Klasifikasi per indikator (enum kanonik) -------------------------------

    /**
     * IMT/U → severely_wasted|wasted|normal|risiko_lebih|overweight|obese.
     * >60 bln tanpa tier "risiko_lebih": (+1..+2SD)=overweight, >+2SD=obese.
     */
    private function klasifikasiImt(float $bmi, object $r, int $bln): string
    {
        if ($bmi < $r->m3sd)         return 'severely_wasted';
        if ($bmi < $r->m2sd)         return 'wasted';
        if ($bmi <= $r->{'1sd'})     return 'normal';
        if ($bln > 60) {
            return $bmi <= $r->{'2sd'} ? 'overweight' : 'obese';
        }
        if ($bmi <= $r->{'2sd'})     return 'risiko_lebih';
        if ($bmi <= $r->{'3sd'})     return 'overweight';
        return 'obese';
    }

    /** BB/U → severely_underweight|underweight|normal|lebih */
    private function klasifikasiBb(float $bb, object $r): string
    {
        if ($bb < $r->m3sd)          return 'severely_underweight';
        if ($bb < $r->m2sd)          return 'underweight';
        if ($bb <= $r->{'1sd'})      return 'normal';
        return 'lebih';
    }

    /** TB/U → severely_stunted|stunted|normal|tinggi */
    private function klasifikasiTb(float $tinggi, object $r): string
    {
        if ($tinggi < $r->m3sd)      return 'severely_stunted';
        if ($tinggi < $r->m2sd)      return 'stunted';
        if ($tinggi <= $r->{'3sd'})  return 'normal';
        return 'tinggi';
    }

    /** BB/TB → severely_wasted|wasted|normal|risiko_lebih|overweight|obese */
    private function klasifikasiBbTb(float $bb, object $r): string
    {
        if ($bb < $r->m3sd)          return 'severely_wasted';
        if ($bb < $r->m2sd)          return 'wasted';
        if ($bb <= $r->{'1sd'})      return 'normal';
        if ($bb <= $r->{'2sd'})      return 'risiko_lebih';
        if ($bb <= $r->{'3sd'})      return 'overweight';
        return 'obese';
    }

    // --- Label manusiawi (selaras tampilan show anak) ---------------------------

    public static function labelImt(?string $enum, int $bln): string
    {
        // >60 bln pakai istilah "thinness"; <=60 pakai "wasted". Enum sama.
        $tua = $bln > 60;
        return match ($enum) {
            'severely_wasted' => $tua ? 'Gizi buruk (severely thinness)' : 'Gizi buruk (severely wasted)',
            'wasted'          => $tua ? 'Gizi kurang (thinness)' : 'Gizi kurang (wasted)',
            'normal'          => 'Gizi baik (normal)',
            'risiko_lebih'    => $tua ? 'Gizi lebih (overweight)' : 'Berisiko gizi lebih (possible risk of overweight)',
            'overweight'      => 'Gizi lebih (overweight)',
            'obese'           => 'Obesitas (obese)',
            default           => 'Data Tidak Valid',
        };
    }

    public static function labelBb(?string $enum): string
    {
        return match ($enum) {
            'severely_underweight' => 'Berat badan sangat kurang (severely underweight)',
            'underweight'          => 'Berat badan kurang (underweight)',
            'normal'               => 'Berat badan normal',
            'lebih'                => 'Risiko Berat badan lebih',
            default                => 'Data Tidak Valid',
        };
    }

    public static function labelTb(?string $enum): string
    {
        return match ($enum) {
            'severely_stunted' => 'Sangat pendek (severely stunted)',
            'stunted'          => 'Pendek (stunted)',
            'normal'           => 'Normal',
            'tinggi'           => 'Tinggi',
            default            => 'Data Tidak Valid',
        };
    }

    public static function labelBbTb(?string $enum): string
    {
        return match ($enum) {
            'severely_wasted' => 'Gizi buruk (severely wasted)',
            'wasted'          => 'Gizi kurang (wasted)',
            'normal'          => 'Gizi baik (normal)',
            'risiko_lebih'    => 'Berisiko gizi lebih (possible risk of overweight)',
            'overweight'      => 'Gizi lebih (overweight)',
            'obese'           => 'Obesitas (obese)',
            default           => 'Data Tidak Valid',
        };
    }

    // --- Referensi z_score (in-memory) ------------------------------------------

    /** Satu baris referensi atau null bila tak ada. */
    private function ref(int $jenisTbl, int $jk, int $acuan, int $var): ?object
    {
        $refs = self::refs();
        return $refs["{$jenisTbl}_{$jk}_{$acuan}_{$var}"] ?? null;
    }

    /** Muat & cache seluruh baris z_score (1.190 baris — kecil). */
    private static function refs(): array
    {
        if (self::$refs === null) {
            self::$refs = [];
            foreach (DB::table('z_score')->get() as $r) {
                self::$refs["{$r->jenis_tbl}_{$r->jk}_{$r->acuan}_" . ($r->var ?? 0)] = $r;
            }
        }
        return self::$refs;
    }

    /** Reset cache (dipakai test). */
    public static function flushCache(): void
    {
        self::$refs = null;
    }

    /**
     * Seed referensi langsung (dipakai test agar tak menyentuh DB).
     * @param array<string,object> $map dikunci "jenis_tbl_jk_acuan_var"
     */
    public static function useRefs(array $map): void
    {
        self::$refs = $map;
    }
}
