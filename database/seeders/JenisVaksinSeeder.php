<?php

namespace Database\Seeders;

use App\Models\JenisVaksin;
use Illuminate\Database\Seeder;

class JenisVaksinSeeder extends Seeder
{
    public function run(): void
    {
        // Rename CAMPAK → MR1 (only if MR1 doesn't exist yet)
        if (!JenisVaksin::withTrashed()->where('kode', 'MR1')->exists()) {
            JenisVaksin::where('kode', 'CAMPAK')->update(['kode' => 'MR1', 'nama' => 'MR 1 (Campak-Rubella)']);
        }

        // Rename IPV → IPV1 (only if IPV1 doesn't exist yet)
        if (!JenisVaksin::withTrashed()->where('kode', 'IPV1')->exists()) {
            JenisVaksin::where('kode', 'IPV')->update(['kode' => 'IPV1', 'nama' => 'IPV 1 (Polio Suntik)']);
        }

        // Rename MR → MR2 (only if MR2 doesn't exist yet; otherwise deactivate old MR)
        if (!JenisVaksin::withTrashed()->where('kode', 'MR2')->exists()) {
            JenisVaksin::where('kode', 'MR')->update(['kode' => 'MR2', 'nama' => 'MR 2 (Campak-Rubella Booster)']);
        } else {
            JenisVaksin::where('kode', 'MR')->update(['aktif' => false]);
        }

        // Rename DPT-HB-HIB-LANJUTAN → DPT-HB-HIB4 (only if target doesn't exist yet)
        if (!JenisVaksin::withTrashed()->where('kode', 'DPT-HB-HIB4')->exists()) {
            JenisVaksin::where('kode', 'DPT-HB-HIB-LANJUTAN')->update(['kode' => 'DPT-HB-HIB4', 'nama' => 'DPT-HB-Hib 4 (Booster)']);
        } else {
            JenisVaksin::where('kode', 'DPT-HB-HIB-LANJUTAN')->update(['aktif' => false]);
        }

        $vaksin = [
            // ── Imunisasi Dasar Lengkap (IDL) ──────────────────────────────────
            [
                'kode' => 'HB0',
                'nama' => 'Hepatitis B 0',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 0,
                'usia_pemberian_max' => 7,
                'interval_hari' => null,
                'catchup_max_hari' => null,
                'bisa_dikejar' => false,
                'keterangan' => 'Diberikan dalam 24 jam pertama setelah lahir. TIDAK bisa dikejar.',
            ],
            [
                'kode' => 'BCG',
                'nama' => 'BCG',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 0,
                'usia_pemberian_max' => 30,
                'interval_hari' => null,
                'catchup_max_hari' => 365,
                'bisa_dikejar' => true,
                'keterangan' => 'Diberikan usia 0–1 bulan. Kejar s/d usia 1 tahun.',
            ],
            [
                'kode' => 'POLIO1',
                'nama' => 'OPV 1',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 0,
                'usia_pemberian_max' => 30,
                'interval_hari' => 28,
                'catchup_max_hari' => null,
                'bisa_dikejar' => true,
                'keterangan' => 'Polio tetes dosis 1, usia 0–1 bulan.',
            ],
            [
                'kode' => 'POLIO2',
                'nama' => 'OPV 2',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 60,
                'usia_pemberian_max' => 90,
                'interval_hari' => 28,
                'catchup_max_hari' => null,
                'bisa_dikejar' => true,
                'keterangan' => 'Polio tetes dosis 2, usia 2–3 bulan.',
            ],
            [
                'kode' => 'POLIO3',
                'nama' => 'OPV 3',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 90,
                'usia_pemberian_max' => 120,
                'interval_hari' => 28,
                'catchup_max_hari' => null,
                'bisa_dikejar' => true,
                'keterangan' => 'Polio tetes dosis 3, usia 3–4 bulan.',
            ],
            [
                'kode' => 'POLIO4',
                'nama' => 'OPV 4',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 120,
                'usia_pemberian_max' => 150,
                'interval_hari' => 28,
                'catchup_max_hari' => null,
                'bisa_dikejar' => true,
                'keterangan' => 'Polio tetes dosis 4, usia 4–5 bulan.',
            ],
            [
                'kode' => 'IPV1',
                'nama' => 'IPV 1 (Polio Suntik)',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 120,
                'usia_pemberian_max' => 150,
                'interval_hari' => null,
                'catchup_max_hari' => null,
                'bisa_dikejar' => true,
                'keterangan' => 'Polio suntik dosis 1, usia 4–5 bulan.',
            ],
            [
                'kode' => 'IPV2',
                'nama' => 'IPV 2 (Polio Suntik)',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 270,
                'usia_pemberian_max' => 300,
                'interval_hari' => null,
                'catchup_max_hari' => null,
                'bisa_dikejar' => true,
                'keterangan' => 'Polio suntik dosis 2, usia 9–10 bulan (bersamaan MR1).',
            ],
            [
                'kode' => 'DPT-HB-HIB1',
                'nama' => 'DPT-HB-Hib 1',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 60,
                'usia_pemberian_max' => 90,
                'interval_hari' => 28,
                'catchup_max_hari' => null,
                'bisa_dikejar' => true,
                'keterangan' => 'Pentavalen dosis 1, usia 2–3 bulan.',
            ],
            [
                'kode' => 'DPT-HB-HIB2',
                'nama' => 'DPT-HB-Hib 2',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 90,
                'usia_pemberian_max' => 120,
                'interval_hari' => 28,
                'catchup_max_hari' => null,
                'bisa_dikejar' => true,
                'keterangan' => 'Pentavalen dosis 2, usia 3–4 bulan.',
            ],
            [
                'kode' => 'DPT-HB-HIB3',
                'nama' => 'DPT-HB-Hib 3',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 120,
                'usia_pemberian_max' => 150,
                'interval_hari' => 28,
                'catchup_max_hari' => null,
                'bisa_dikejar' => true,
                'keterangan' => 'Pentavalen dosis 3, usia 4–5 bulan.',
            ],
            [
                'kode' => 'PCV1',
                'nama' => 'PCV 1',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 60,
                'usia_pemberian_max' => 90,
                'interval_hari' => 28,
                'catchup_max_hari' => 730,
                'bisa_dikejar' => true,
                'keterangan' => 'Pneumococcal dosis 1, usia 2–3 bulan. Kejar s/d usia 24 bulan.',
            ],
            [
                'kode' => 'PCV2',
                'nama' => 'PCV 2',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 90,
                'usia_pemberian_max' => 120,
                'interval_hari' => 28,
                'catchup_max_hari' => 730,
                'bisa_dikejar' => true,
                'keterangan' => 'Pneumococcal dosis 2, usia 3–4 bulan. Kejar s/d usia 24 bulan.',
            ],
            [
                'kode' => 'RV1',
                'nama' => 'Rotavirus 1',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 42,
                'usia_pemberian_max' => 70,
                'interval_hari' => 28,
                'catchup_max_hari' => 168,
                'bisa_dikejar' => true,
                'keterangan' => 'Rotavirus dosis 1, usia 6–10 minggu. Tidak bisa diberikan jika >24 minggu (168 hari).',
            ],
            [
                'kode' => 'RV2',
                'nama' => 'Rotavirus 2',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 70,
                'usia_pemberian_max' => 105,
                'interval_hari' => 28,
                'catchup_max_hari' => 224,
                'bisa_dikejar' => true,
                'keterangan' => 'Rotavirus dosis 2, usia 10–15 minggu. Tidak bisa diberikan jika >32 minggu (224 hari).',
            ],
            [
                'kode' => 'MR1',
                'nama' => 'MR 1 (Campak-Rubella)',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 270,
                'usia_pemberian_max' => 300,
                'interval_hari' => null,
                'catchup_max_hari' => null,
                'bisa_dikejar' => true,
                'keterangan' => 'Campak-Rubella dosis 1, usia 9–10 bulan (IDL). Kejar s/d 18 tahun.',
            ],

            // ── Imunisasi Lanjutan / Booster (IBL) ─────────────────────────────
            [
                'kode' => 'PCV3',
                'nama' => 'PCV 3 (Booster)',
                'kategori' => 'Booster',
                'usia_pemberian_min' => 365,
                'usia_pemberian_max' => 395,
                'interval_hari' => null,
                'catchup_max_hari' => 730,
                'bisa_dikejar' => true,
                'keterangan' => 'Pneumococcal booster, usia 12–13 bulan. Kejar s/d 24 bulan.',
            ],
            [
                'kode' => 'MR2',
                'nama' => 'MR 2 (Campak-Rubella Booster)',
                'kategori' => 'Booster',
                'usia_pemberian_min' => 450,
                'usia_pemberian_max' => 540,
                'interval_hari' => null,
                'catchup_max_hari' => null,
                'bisa_dikejar' => true,
                'keterangan' => 'Campak-Rubella dosis 2, usia 15–18 bulan. Kejar s/d 18 tahun.',
            ],
            [
                'kode' => 'DPT-HB-HIB4',
                'nama' => 'DPT-HB-Hib 4 (Booster)',
                'kategori' => 'Booster',
                'usia_pemberian_min' => 540,
                'usia_pemberian_max' => 720,
                'interval_hari' => null,
                'catchup_max_hari' => null,
                'bisa_dikejar' => true,
                'keterangan' => 'Pentavalen booster, usia 18–24 bulan.',
            ],

            // ── Imunisasi Anak Sekolah BIAS (ISL) ──────────────────────────────
            [
                'kode' => 'DT',
                'nama' => 'DT (Difteri Tetanus)',
                'kategori' => 'Tambahan',
                'usia_pemberian_min' => 2190,
                'usia_pemberian_max' => 2555,
                'interval_hari' => null,
                'catchup_max_hari' => null,
                'bisa_dikejar' => true,
                'keterangan' => 'BIAS kelas 1 SD (usia 6–7 tahun), bulan November.',
            ],
            [
                'kode' => 'TD',
                'nama' => 'Td (Tetanus Difteri)',
                'kategori' => 'Tambahan',
                'usia_pemberian_min' => 2555,
                'usia_pemberian_max' => 3285,
                'interval_hari' => null,
                'catchup_max_hari' => null,
                'bisa_dikejar' => true,
                'keterangan' => 'BIAS kelas 2 & 5 SD (usia 7–9 tahun), bulan November.',
            ],
            [
                'kode' => 'MR-SEKOLAH',
                'nama' => 'MR Anak Sekolah',
                'kategori' => 'Tambahan',
                'usia_pemberian_min' => 2190,
                'usia_pemberian_max' => 2555,
                'interval_hari' => null,
                'catchup_max_hari' => null,
                'bisa_dikejar' => true,
                'keterangan' => 'BIAS kelas 1 SD, bulan Agustus.',
            ],
            [
                'kode' => 'HPV1',
                'nama' => 'HPV 1',
                'kategori' => 'Tambahan',
                'usia_pemberian_min' => 3650,
                'usia_pemberian_max' => 4015,
                'interval_hari' => null,
                'catchup_max_hari' => 9490,
                'bisa_dikejar' => true,
                'keterangan' => 'HPV dosis 1, khusus perempuan, kelas 5 SD. Kejar s/d usia 26 tahun.',
            ],
            [
                'kode' => 'HPV2',
                'nama' => 'HPV 2',
                'kategori' => 'Tambahan',
                'usia_pemberian_min' => 4015,
                'usia_pemberian_max' => 4380,
                'interval_hari' => 182,
                'catchup_max_hari' => 9490,
                'bisa_dikejar' => true,
                'keterangan' => 'HPV dosis 2, khusus perempuan, kelas 6 SD (6 bulan setelah dosis 1). Kejar s/d 26 tahun.',
            ],
        ];

        foreach ($vaksin as $v) {
            JenisVaksin::updateOrCreate(
                ['kode' => $v['kode']],
                $v
            );
        }
    }
}
