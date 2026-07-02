<?php

namespace App\Console\Commands;

use App\Imports\CapilImport;
use App\Imports\OperasiTimbangImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import hasil Operasi Timbang (ekspor e-PPGBM) ke data_anak.
 *
 * Default DRY-RUN (hanya laporan). Tulis sungguhan hanya dengan --commit.
 * Baris tak cocok/ambigu diekspor ke storage/app/timbang/ untuk tinjau manual.
 */
class ImportOperasiTimbang extends Command
{
    protected $signature = 'import:operasi-timbang
        {file : Path file .xlsx ekspor e-PPGBM}
        {--commit : Tulis ke DB (tanpa flag ini hanya dry-run)}
        {--user=1 : id_user pemilik record data_anak}
        {--min-nama=88 : Ambang kemiripan nama (persen)}';

    protected $description = 'Cocokkan & impor hasil operasi timbang e-PPGBM ke data_anak (default dry-run).';

    public function handle(): int
    {
        $file    = (string) $this->argument('file');
        $commit  = (bool) $this->option('commit');
        $userId  = (int) $this->option('user');
        $minNama = (int) $this->option('min-nama');

        if (!is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");
            return self::FAILURE;
        }

        $this->warn('Mode: ' . ($commit ? 'COMMIT (menulis data_anak)' : 'DRY-RUN (tidak menulis apa pun)'));

        // Guard sheet: hanya sheet terlihat pertama (cegah insiden multi-sheet).
        $sheets = CapilImport::inspectSheets($file);
        $target = CapilImport::firstVisibleSheet($sheets);
        if ($warn = CapilImport::sheetWarning($sheets, $target)) {
            $this->warn($warn);
        }

        $import = new OperasiTimbangImport($userId, $commit, $minNama, $target);

        if ($commit) {
            DB::transaction(fn () => Excel::import($import, $file));
        } else {
            Excel::import($import, $file);
        }

        $r = $import->getResults();

        $this->newLine();
        $this->info("COCOK      : {$r['matched']}" . ($commit ? ' (ditulis)' : ' (akan ditulis)'));
        $this->line("AMBIGU     : " . count($r['ambiguous']));
        $this->line("TAK_COCOK  : " . count($r['unmatched']));
        $this->line("DILEWATI   : {$r['skipped']}");
        $this->newLine();

        $base = pathinfo($file, PATHINFO_FILENAME);
        $this->tulisCsv("timbang/{$base}-ambigu.csv", $r['ambiguous']);
        $this->tulisCsv("timbang/{$base}-takcocok.csv", $r['unmatched']);

        if (!$commit) {
            $this->warn('[DRY-RUN] Tidak ada yang ditulis. Jalankan ulang dengan --commit untuk menyimpan.');
        }

        return self::SUCCESS;
    }

    private function tulisCsv(string $path, array $rows): void
    {
        if (empty($rows)) return;

        $header = ['baris', 'nama', 'tgl_lahir', 'alasan', 'kandidat'];
        $lines  = [implode(',', $header)];
        foreach ($rows as $r) {
            $lines[] = implode(',', array_map(
                fn ($v) => '"' . str_replace('"', '""', (string) ($v ?? '')) . '"',
                [$r['baris'], $r['nama'], $r['tgl_lahir'], $r['alasan'], $r['kandidat']]
            ));
        }
        Storage::disk('local')->put($path, implode("\n", $lines));
        $this->line("  → tinjau manual: storage/app/{$path} (" . count($rows) . " baris)");
    }
}
