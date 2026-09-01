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
    /**
     * @param array{bb_u?:float|null,tb_u?:float|null,bb_tb?:float|null}|null $zscore
     *        Z-score e-PPGBM tersimpan. Bila nilai suatu indikator diberikan (non-null),
     *        klasifikasi indikator itu memakai z-score (selaras ekspor resmi Sigizi),
     *        menggantikan recompute WHO lokal. Indikator yang null → tetap recompute.
     *        IMT/U tak punya z-score tersimpan → selalu recompute.
     */
    public function klasifikasi($bb, $tb, $bln, $posisi, $jk, ?array $zscore = null): array
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

        // Z-score e-PPGBM tersimpan menggantikan recompute per-indikator (bila ada).
        // IMT/U tak punya z-score tersimpan → tak pernah dioverride.
        if ($zscore !== null) {
            if (($z = $zscore['bb_u'] ?? null) !== null)  $bbEnum = $this->enumBbDariZ((float) $z);
            if (($z = $zscore['tb_u'] ?? null) !== null)  $tbEnum = $this->enumTbDariZ((float) $z);
            if (($z = $zscore['bb_tb'] ?? null) !== null) $btEnum = $this->enumBbTbDariZ((float) $z);
        }

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

    // --- Klasifikasi dari z-score e-PPGBM tersimpan -----------------------------
    // Ambang WHO: severe pada z < -3, moderat pada z < -2 (batas -2,00 tepat = normal,
    // selaras hitungan ekspor Sigizi). Pita atas mengikuti klasifikasi berbasis nilai.

    /** BB/U z → severely_underweight|underweight|normal|lebih */
    private function enumBbDariZ(float $z): string
    {
        if ($z < -3)  return 'severely_underweight';
        if ($z < -2)  return 'underweight';
        if ($z <= 1)  return 'normal';
        return 'lebih';
    }

    /** TB/U z → severely_stunted|stunted|normal|tinggi */
    private function enumTbDariZ(float $z): string
    {
        if ($z < -3)  return 'severely_stunted';
        if ($z < -2)  return 'stunted';
        if ($z <= 3)  return 'normal';
        return 'tinggi';
    }

    /** BB/TB z → severely_wasted|wasted|normal|risiko_lebih|overweight|obese */
    private function enumBbTbDariZ(float $z): string
    {
        if ($z < -3)  return 'severely_wasted';
        if ($z < -2)  return 'wasted';
        if ($z <= 1)  return 'normal';
        if ($z <= 2)  return 'risiko_lebih';
        if ($z <= 3)  return 'overweight';
        return 'obese';
    }

    // --- Klasifikasi PERSIS rumus e-PPGBM/Dinkes --------------------------------
    //
    // Dashboard Operasi Timbang harus mereproduksi COUNTIFS ekspor Dinkes, MURNI
    // dari z-score tersimpan (TANPA recompute WHO lokal). Ambang mengikuti rumus
    // resmi (batas "<= -2.01"/"<= -3.01" setara "< -2"/"< -3" untuk data 2 desimal):
    //   - TB/U stunting     : -6.01 < z <= -2.01   (ADA batas bawah plausibilitas)
    //   - BB/U underweight   :         z <= -2.01   (tanpa batas bawah)
    //   - BB/TB wasting      :         z <= -2.01   (tanpa batas bawah)
    // z null → null: sel z-score kosong tidak dihitung (persis COUNTIFS).

    /**
     * SENGAJA independen per kolom, TANPA saling membatalkan lintas indikator.
     * Rumus resmi Dinkes (COUNTIFS per kolom AA/BB-U/BB-TB di sheet ekspor)
     * juga menghitung tiap indikator dari kolomnya sendiri saja — z-score
     * BB/TB yang tak masuk akal (mis. sentinel 999.99 saat bb/tb implausibel)
     * TIDAK membatalkan TB/U atau BB/U pada baris yang sama. Sempat dicoba
     * saling-membatalkan lintas indikator (kasus MUHAMMAD AZHAM, Bontang
     * Lestari) tapi itu membuat dashboard SELISIH dari sheet resmi — sheet
     * memang tetap menghitung TB/U & BB/U-nya walau BB/TB-nya sentinel.
     *
     * @return array{bb_u:?string,tb_u:?string,bb_tb:?string}
     */
    public function enumEppgbm(?float $zBbU, ?float $zTbU, ?float $zBbTb): array
    {
        return [
            'bb_u'  => $this->eppgbmBb($zBbU),
            'tb_u'  => $this->eppgbmTb($zTbU),
            'bb_tb' => $this->eppgbmBbTb($zBbTb),
        ];
    }

    private function eppgbmTb(?float $z): ?string
    {
        if ($z === null || $z <= -6.01) return null; // kosong / outlier implausibel
        if ($z <= -3.01) return 'severely_stunted';
        if ($z <= -2.01) return 'stunted';
        if ($z <= 3.00)  return 'normal';
        return 'tinggi';
    }

    private function eppgbmBb(?float $z): ?string
    {
        if ($z === null) return null;
        if ($z <= -3.01) return 'severely_underweight';
        if ($z <= -2.01) return 'underweight';
        if ($z <= 1.00)  return 'normal';
        return 'lebih';
    }

    private function eppgbmBbTb(?float $z): ?string
    {
        if ($z === null) return null;
        if ($z <= -3.01) return 'severely_wasted';
        if ($z <= -2.01) return 'wasted';
        if ($z <= 1.00)  return 'normal';
        if ($z <= 2.00)  return 'risiko_lebih';
        if ($z <= 3.00)  return 'overweight';
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
