<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill kolom data_anak.posisi ke nilai kanonik H/L.
 *
 * z-score hanya mengenal posisi 'H' (Berdiri/Tinggi Badan) dan 'L'
 * (Terlentang/Panjang Badan) untuk koreksi tinggi ±0.7. Importer lama menyimpan
 * nilai mentah ("Bb", "berdiri", "terlentang", dst) yang tak dikenali sehingga
 * koreksi diam-diam tak berjalan. Helper normalisasi_posisi() (Paket B) kini
 * dipakai di seluruh jalur input; migrasi ini menyelaraskan data historis.
 *
 * Nilai dinormalkan per-varian distinct (jumlahnya sedikit) agar hemat query.
 */
return new class extends Migration
{
    public function up(): void
    {
        $varian = DB::table('data_anak')
            ->select('posisi', DB::raw('count(*) as n'))
            ->groupBy('posisi')
            ->get();

        foreach ($varian as $row) {
            $kanonik = normalisasi_posisi($row->posisi);

            // Hanya UPDATE bila nilai tersimpan berbeda dari bentuk kanonik
            // (case-sensitive: 'h' / 'berdiri' / null → 'H' atau 'L').
            if ((string) $row->posisi === $kanonik) {
                continue;
            }

            $q = DB::table('data_anak');
            if ($row->posisi === null) {
                $q->whereNull('posisi');
            } else {
                $q->where('posisi', $row->posisi);
            }
            $q->update(['posisi' => $kanonik]);
        }
    }

    public function down(): void
    {
        // Koreksi data — nilai mentah lama tidak perlu (dan tidak bisa) dipulihkan.
    }
};
