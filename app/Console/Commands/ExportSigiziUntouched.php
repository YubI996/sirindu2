<?php

namespace App\Console\Commands;

use App\Services\CapilDedupService;
use Illuminate\Console\Command;

/**
 * Ekspor record "sigizi belum tersentuh" (alamat_ktp NULL & updated_at == created_at) —
 * himpunan ~1.459 baris yang menjadi sasaran dedup Capil — ke berkas CSV.
 *
 * Kerahasiaan: command hanya melaporkan JUMLAH baris & path berkas. Isi data tidak
 * ditampilkan ke layar. Berkas default ditulis ke storage/app/exports/.
 */
class ExportSigiziUntouched extends Command
{
    protected $signature = 'capil:export-untouched {--path= : Path berkas CSV tujuan (default: storage/app/exports/sigizi_untouched_<timestamp>.csv)}';

    protected $description = 'Ekspor 1.459 record sigizi belum tersentuh (sasaran dedup) ke CSV.';

    public function handle(CapilDedupService $svc): int
    {
        $path = $this->option('path');
        if (!$path) {
            $dir = storage_path('app/exports');
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $path = $dir . DIRECTORY_SEPARATOR . 'sigizi_untouched_' . date('Ymd_His') . '.csv';
        }

        $count = $svc->exportSigiziUntouched($path);

        $this->info("Tertulis {$count} baris ke:");
        $this->line($path);

        return self::SUCCESS;
    }
}
