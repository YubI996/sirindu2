<?php

namespace App\Console\Commands;

use App\Services\CapilDedupService;
use Illuminate\Console\Command;

/**
 * Ekspor record sigizi-belum-tersentuh yang TIDAK punya padanan di Capil
 * (~1.071 baris sisa: 1.459 sasaran − 388 berpadanan) ke CSV.
 *
 * Ini anak sigizi "yatim" — tak terjodoh Capil. Kemungkinan: NIK salah & data
 * Capil-nya memang tak ada, atau perlu verifikasi manual ke Capil.
 *
 * Kerahasiaan: command hanya melaporkan JUMLAH baris & path; isi tak ditampilkan.
 */
class ExportSigiziUnpaired extends Command
{
    protected $signature = 'capil:export-unpaired {--path= : Path berkas CSV tujuan (default: storage/app/exports/sigizi_unpaired_<timestamp>.csv)}';

    protected $description = 'Ekspor sigizi tanpa padanan Capil (sisa setelah dedup) ke CSV.';

    public function handle(CapilDedupService $svc): int
    {
        $path = $this->option('path');
        if (!$path) {
            $dir = storage_path('app/exports');
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $path = $dir . DIRECTORY_SEPARATOR . 'sigizi_unpaired_' . date('Ymd_His') . '.csv';
        }

        $count = $svc->exportSigiziUnpaired($path);

        $this->info("Tertulis {$count} baris ke:");
        $this->line($path);

        return self::SUCCESS;
    }
}
