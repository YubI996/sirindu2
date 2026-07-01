<?php

namespace App\Console\Commands;

use App\Services\CapilDedupService;
use Illuminate\Console\Command;

/**
 * Ekspor pasangan duplikat terkonfirmasi (Capil-baru ↔ sigizi-untouched) ke CSV
 * untuk review manual sebelum menjalankan `capil:dedup --apply`.
 *
 * Kerahasiaan: command hanya melaporkan JUMLAH pasangan & path berkas; isi tak
 * ditampilkan. Berkas default ditulis ke storage/app/exports/.
 */
class ExportCapilPairs extends Command
{
    protected $signature = 'capil:export-pairs {--path= : Path berkas CSV tujuan (default: storage/app/exports/capil_pairs_<timestamp>.csv)}';

    protected $description = 'Ekspor 388 pasangan duplikat (capil↔sigizi) + skor kecocokan ke CSV untuk review.';

    public function handle(CapilDedupService $svc): int
    {
        $path = $this->option('path');
        if (!$path) {
            $dir = storage_path('app/exports');
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $path = $dir . DIRECTORY_SEPARATOR . 'capil_pairs_' . date('Ymd_His') . '.csv';
        }

        $count = $svc->exportPairs($path);

        $this->info("Tertulis {$count} pasangan ke:");
        $this->line($path);

        return self::SUCCESS;
    }
}
