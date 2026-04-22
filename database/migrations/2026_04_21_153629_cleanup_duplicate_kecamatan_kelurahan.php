<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Kelurahan duplikat → canonical
    // kel 16 "Berebas Tengah" → 5 "Berbas Tengah"   (kec 3 Bontang Selatan)
    // kel 18 "Api-api"        → 10 "Api Api"          (kec 2 Bontang Utara)
    // kel 19 "Lok Tuan"       → 15 "Loktuan"          (kec 2 Bontang Utara)
    //
    // RT sampah (kel=16) → RT canonical di kel=5 berdasarkan nomor
    // 500 (19SIDRAP) → 136 (19BT)
    // 501 (20SIDRAP) → 137 (20BT)
    // 502 (21SIDRAP) → 138 (21BT)
    // 503 (22SIDRAP) → 139 (22BT)
    // 504 (23SIDRAP) → 140 (23BT)
    // 505 (24SIDRAP) → 141 (24BT)

    private array $rtMap = [
        500 => 136,
        501 => 137,
        502 => 138,
        503 => 139,
        504 => 140,
        505 => 141,
    ];

    public function up(): void
    {
        DB::transaction(function () {

            // ── 1. Perbaiki RT + kel=16 → kel=5 ──────────────────────────
            foreach ($this->rtMap as $oldRt => $newRt) {
                DB::table('surveillance_cases')
                    ->where('id_kel', 16)
                    ->where('id_rt', $oldRt)
                    ->update(['id_kel' => 5, 'id_rt' => $newRt]);
            }
            // Sisa kel=16 tanpa RT canonical
            DB::table('surveillance_cases')
                ->where('id_kel', 16)
                ->update(['id_kel' => 5, 'id_rt' => null]);

            // ── 2. kel=18 (Api-api) → kel=10 (Api Api), kec wajib 2 ──────
            DB::table('surveillance_cases')
                ->where('id_kel', 18)
                ->update(['id_kel' => 10, 'id_kec' => 2]);

            // ── 3. kel=19 (Lok Tuan) → kel=15 (Loktuan), kec wajib 2 ────
            DB::table('surveillance_cases')
                ->where('id_kel', 19)
                ->update(['id_kel' => 15, 'id_kec' => 2]);

            // ── 4. Hapus RT sampah ────────────────────────────────────────
            DB::table('rt')->whereIn('id', array_keys($this->rtMap))->delete();

            // ── 5. Hapus kelurahan duplikat & sampah ─────────────────────
            DB::table('kelurahan')->whereIn('id', [16, 18, 19, 45, 46, 47])->delete();

            // ── 6. Hapus kecamatan sampah ─────────────────────────────────
            DB::table('kecamatan')->whereIn('id', [
                109, // Bontang (generik)
                110, // formula sampah
                111, // "18" (angka acak)
                112, // formula sampah
                113, // Bontang Lestari (sebenarnya kelurahan, bukan kecamatan)
                114, // formula sampah
                115, // Discarded
                116, // Campak
                117, // Rubella
                118, // formula sampah
            ])->delete();
        });
    }

    public function down(): void
    {
        throw new \RuntimeException('Rollback tidak didukung — restore dari backup.');
    }
};
