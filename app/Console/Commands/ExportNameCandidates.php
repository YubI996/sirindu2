<?php

namespace App\Console\Commands;

use App\Services\CapilDedupService;
use Illuminate\Console\Command;

/**
 * Ekspor KANDIDAT dedup longgar berbasis kemiripan nama anak saja (default >=80%),
 * dari sisa yang BELUM masuk pasangan terkonfirmasi — untuk perburuan manual calon lain.
 *
 * BUKAN auto-merge: hasil perlu ditinjau mata manusia (lihat kolom parent_sim,
 * selisih_hari, kk_sama). Kerahasiaan: hanya jumlah baris & path yang ditampilkan.
 */
class ExportNameCandidates extends Command
{
    protected $signature = 'capil:export-name-candidates {--min=80 : Ambang minimum kemiripan nama anak (persen)} {--path= : Path CSV tujuan (default: storage/app/exports/name_candidates.csv)}';

    protected $description = 'Ekspor calon dedup longgar (nama anak >=N%) untuk review manual.';

    public function handle(CapilDedupService $svc): int
    {
        $min = (float) $this->option('min');
        $path = $this->option('path');
        if (!$path) {
            $dir = storage_path('app/exports');
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $path = $dir . DIRECTORY_SEPARATOR . 'name_candidates.csv';
        }

        $count = $svc->exportNameCandidates($path, $min);

        $this->info("Tertulis {$count} kandidat (nama >= {$min}%) ke:");
        $this->line($path);

        return self::SUCCESS;
    }
}
