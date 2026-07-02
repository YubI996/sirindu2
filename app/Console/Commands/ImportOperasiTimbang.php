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
        {--min-nama=88 : Ambang kemiripan nama (persen)}
        {--keputusan= : Path CSV keputusan ambigu (kolom: baris, keputusan_id)}';

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

        $keputusan = null;
        if ($kepPath = (string) $this->option('keputusan')) {
            if (!is_file($kepPath)) {
                $this->error("File keputusan tidak ditemukan: {$kepPath}");
                return self::FAILURE;
            }
            $keputusan = $this->bacaKeputusan($kepPath);
            if ($keputusan === null) {
                return self::FAILURE;
            }
            $this->info('Keputusan dimuat: ' . count($keputusan) . ' baris.');
        }

        $this->warn('Mode: ' . ($commit ? 'COMMIT (menulis data_anak)' : 'DRY-RUN (tidak menulis apa pun)'));

        // Guard sheet: hanya sheet terlihat pertama (cegah insiden multi-sheet).
        $sheets = CapilImport::inspectSheets($file);
        $target = CapilImport::firstVisibleSheet($sheets);
        if ($warn = CapilImport::sheetWarning($sheets, $target)) {
            $this->warn($warn);
        }

        $import = new OperasiTimbangImport($userId, $commit, $minNama, $target, $keputusan);

        if ($commit) {
            DB::transaction(fn () => Excel::import($import, $file));
        } else {
            Excel::import($import, $file);
        }

        $r = $import->getResults();

        $this->newLine();
        $this->info("COCOK      : {$r['matched']}" . ($commit ? ' (ditulis)' : ' (akan ditulis)'));
        if ($keputusan !== null) {
            $this->info("RESOLVED   : {$r['resolved']}" . ($commit ? ' (ditulis via keputusan)' : ' (akan ditulis via keputusan)'));
            $this->line("RES-SKIP   : {$r['resolved_skip']} (di-skip via keputusan)");
        }
        $this->line("AMBIGU     : " . count($r['ambiguous']) . ($keputusan !== null ? ' (belum diputus)' : ''));
        $this->line("TAK_COCOK  : " . count($r['unmatched']));
        $this->line("DILEWATI   : {$r['skipped']}");
        if (!empty($r['keputusan_error'])) {
            $this->newLine();
            $this->warn('⚠ keputusan_id bermasalah: ' . count($r['keputusan_error']));
            foreach (array_slice($r['keputusan_error'], 0, 15) as $e) {
                $this->line("    baris {$e['baris']} {$e['nama']}: {$e['alasan']}");
            }
        }
        $this->newLine();

        $base = pathinfo($file, PATHINFO_FILENAME);
        $this->tulisCsv("timbang/{$base}-ambigu.csv", $r['ambiguous']);
        $this->tulisCsv("timbang/{$base}-takcocok.csv", $r['unmatched']);

        if (!$commit) {
            $this->warn('[DRY-RUN] Tidak ada yang ditulis. Jalankan ulang dengan --commit untuk menyimpan.');
        }

        return self::SUCCESS;
    }

    /**
     * Baca CSV keputusan → peta baris(int) → keputusan_id (string 'skip'/id).
     * Return null bila header wajib tidak ada.
     *
     * @return array<int,string>|null
     */
    private function bacaKeputusan(string $path): ?array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            return [];
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv(array_shift($lines), ',', '"', '\\'));
        $bi = array_search('baris', $header, true);
        $ki = array_search('keputusan_id', $header, true);
        if ($bi === false || $ki === false) {
            $this->error('File keputusan wajib punya kolom "baris" dan "keputusan_id".');
            return null;
        }

        $map = [];
        foreach ($lines as $line) {
            $row   = str_getcsv($line, ',', '"', '\\');
            $baris = (int) ($row[$bi] ?? 0);
            $val   = trim((string) ($row[$ki] ?? ''));
            if ($baris > 0 && $val !== '') {
                $map[$baris] = $val;
            }
        }

        return $map;
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
