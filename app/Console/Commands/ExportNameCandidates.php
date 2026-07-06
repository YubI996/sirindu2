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
    protected $signature = 'capil:export-name-candidates
        {--min=80 : Ambang minimum kemiripan nama anak (persen)}
        {--format=csv : Format keluaran: csv | xlsx (xlsx menjaga NIK 16-digit sebagai teks)}
        {--path= : Path tujuan (default: storage/app/exports/name_candidates.<ext>)}';

    protected $description = 'Ekspor calon dedup longgar (nama anak >=N%) untuk review manual (CSV/XLSX).';

    public function handle(CapilDedupService $svc): int
    {
        $min = (float) $this->option('min');
        $format = strtolower((string) $this->option('format'));
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            $this->error("Format tidak dikenal: '{$format}'. Pilih: csv | xlsx.");
            return self::INVALID;
        }

        $path = $this->option('path');
        if (!$path) {
            $dir = storage_path('app/exports');
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $path = $dir . DIRECTORY_SEPARATOR . 'name_candidates.' . $format;
        }

        $count = $format === 'xlsx'
            ? $svc->exportNameCandidatesXlsx($path, $min)
            : $svc->exportNameCandidates($path, $min);

        $this->info("Tertulis {$count} kandidat (nama >= {$min}%, {$format}) ke:");
        $this->line($path);

        return self::SUCCESS;
    }
}
