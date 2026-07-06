<?php

namespace App\Console\Commands;

use App\Services\CapilDedupService;
use Illuminate\Console\Command;

/**
 * Ekspor dugaan duplikat INTERNAL di tabel `anak` (registri gabungan sigizi+Capil):
 *   B. Nama + tgl lahir identik (meski NIK beda/kosong)
 *   C. No. KK + nama identik (tgl boleh beda)
 * Sinyal A (NIK asli identik) selalu 0 — kolom `anak.nik` UNIQUE, DB mencegahnya.
 *
 * Kerahasiaan: command hanya melaporkan JUMLAH kelompok & baris + path berkas; isi tak
 * ditampilkan. Berkas default ditulis ke storage/app/exports/.
 */
class ExportCapilDuplicates extends Command
{
    protected $signature = 'capil:export-duplicates {--dir= : Direktori tujuan (default: storage/app/exports)}';

    protected $description = 'Ekspor dugaan duplikat internal tabel anak (sinyal nama+tgl & No.KK+nama) ke CSV.';

    public function handle(CapilDedupService $svc): int
    {
        $dir = $this->option('dir') ?: storage_path('app/exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'anak_dup_internal_' . date('Ymd_His') . '.csv';
        $res = $svc->exportInternalDuplicates($path);

        $this->info('Dugaan duplikat internal tabel anak:');
        $this->line("  A. NIK asli identik   : 0 grup / 0 record  (mustahil — nik UNIQUE)");
        $this->line("  B. Nama + tgl identik : {$res['name_dob']['groups']} grup / {$res['name_dob']['rows']} record");
        $this->line("  C. No.KK + nama sama  : {$res['kk_name']['groups']} grup / {$res['kk_name']['rows']} record");
        $this->line("  TOTAL (B + C)         : {$res['total_groups']} grup / {$res['total_rows']} record");
        $this->line($path);

        return self::SUCCESS;
    }
}
