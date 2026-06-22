<?php

namespace App\Console\Commands;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remediasi data kasus surveilans yang wilayahnya salah akibat fuzzy-match longgar
 * di importer (lihat App\Traits\ResolvesWilayah, FUZZY_THRESHOLD 80 -> 85).
 *
 * Default DRY-RUN. Tambahkan --apply untuk benar-benar mengubah data.
 * Aman dijalankan di produksi: hanya menyentuh kasus yang wilayahnya tak konsisten,
 * dibungkus transaksi, dan kasus ambigu (kelurahan sampah) hanya dilaporkan.
 */
class CleanupWilayahKasus extends Command
{
    protected $signature = 'wilayah:cleanup-kasus {--apply : Terapkan perubahan (tanpa flag ini hanya dry-run)}';

    protected $description = 'Perbaiki kasus surveilans dengan id_kec/id_kel tak konsisten (mis. Gunung Elai nyangkut di Bontang Barat).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $mode  = $apply ? 'APPLY (mengubah data)' : 'DRY-RUN (tidak mengubah apa pun)';
        $this->warn("Mode: {$mode}");
        $this->newLine();

        // Kecamatan sah Bontang (master tetap). Kelurahan di luar ini dianggap "asing".
        $kecBontang = [1, 2, 3]; // Bontang Barat, Utara, Selatan

        DB::beginTransaction();
        try {
            $remapKel = $this->fixElaiTelihan();
            $fixKec   = $this->normalizeIdKec($kecBontang);
            $this->reportAmbiguous($kecBontang);

            if ($apply) {
                DB::commit();
                $this->newLine();
                $this->info("DITERAPKAN: {$remapKel} kasus di-remap kelurahan, {$fixKec} kasus diperbaiki kecamatan.");
            } else {
                DB::rollBack();
                $this->newLine();
                $this->line("Dry-run selesai. Jalankan ulang dengan --apply untuk menerapkan.");
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Dibatalkan (rollback): ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * (A) Kasus "Gunung Elai" yang id_kec-nya Bontang Barat tak mungkin benar
     *     (Elai milik Bontang Utara). Satu-satunya kelurahan "Gunung *" di Bontang
     *     Barat adalah Gunung Telihan -> remap id_kel ke Telihan. Ini akar gejala
     *     "Gunung Telihan diimport sebagai Gunung Elai".
     */
    private function fixElaiTelihan(): int
    {
        $elai    = Kelurahan::where('name', 'Gunung Elai')->first();
        $telihan = Kelurahan::where('name', 'Gunung Telihan')->first();
        $kecBarat = Kecamatan::where('name', 'Bontang Barat')->value('id');

        if (!$elai || !$telihan || !$kecBarat) {
            $this->line('(A) Elai/Telihan/Bontang Barat tidak lengkap di master — dilewati.');
            return 0;
        }

        $q = DB::table('surveillance_cases')
            ->where('id_kel', $elai->id)
            ->where('id_kec', $kecBarat);
        $n = (clone $q)->count();

        $this->info("(A) Remap Gunung Elai->Gunung Telihan untuk kasus di Bontang Barat: {$n} kasus");
        if ($n > 0 && $this->option('apply')) {
            $q->update(['id_kel' => $telihan->id]);
        }

        return $n;
    }

    /**
     * (B) Untuk kasus yang kelurahannya kelurahan Bontang yang sah, id_kec WAJIB
     *     sama dengan kecamatan asli kelurahan itu (relasi 1 kelurahan -> 1 kecamatan).
     *     Setel id_kec := kelurahan.id_kecamatan. Geografis selalu valid.
     *     Dijalankan SETELAH (A) agar kasus Elai/Telihan sudah konsisten.
     */
    private function normalizeIdKec(array $kecBontang): int
    {
        $rows = DB::table('surveillance_cases as s')
            ->join('kelurahan as k', 's.id_kel', '=', 'k.id')
            ->whereColumn('s.id_kec', '!=', 'k.id_kecamatan')
            ->whereIn('k.id_kecamatan', $kecBontang)         // kelurahan sah Bontang
            ->where('k.name', '!=', 'Tidak Diketahui')        // bukan kelurahan sampah
            ->select('s.id as case_id', 'k.id_kecamatan as benar')
            ->get();

        $this->info("(B) Samakan id_kec dengan kecamatan asli kelurahan: {$rows->count()} kasus");
        foreach ($rows as $r) {
            $this->line("    - kasus #{$r->case_id} -> id_kec={$r->benar}");
            if ($this->option('apply')) {
                DB::table('surveillance_cases')->where('id', $r->case_id)->update(['id_kec' => $r->benar]);
            }
        }

        return $rows->count();
    }

    /**
     * (C) Kasus dengan kelurahan sampah / luar Bontang (Tidak Diketahui, Telihan
     *     pendek, kelurahan Kutai Timur). Tak bisa ditebak otomatis -> hanya lapor
     *     agar operator membetulkan dari file sumber.
     */
    private function reportAmbiguous(array $kecBontang): void
    {
        $rows = DB::table('surveillance_cases as s')
            ->join('kelurahan as k', 's.id_kel', '=', 'k.id')
            ->where(function ($w) use ($kecBontang) {
                $w->whereNotIn('k.id_kecamatan', $kecBontang)   // kelurahan luar Bontang
                  ->orWhere('k.name', 'Tidak Diketahui');
            })
            ->select('k.name as kel', DB::raw('count(*) as n'))
            ->groupBy('k.name')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('(C) Tidak ada kasus pada kelurahan sampah/luar Bontang.');
            return;
        }

        $this->warn('(C) PERLU TINJAUAN MANUAL — kasus pada kelurahan sampah/luar Bontang:');
        foreach ($rows as $r) {
            $this->line("    - {$r->kel}: {$r->n} kasus (betulkan dari file sumber / petakan ulang manual)");
        }
    }
}
