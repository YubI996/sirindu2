<?php

namespace App\Console\Commands;

use App\Imports\CapilImport;
use App\Imports\OtFinalRegistriImport;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Services\PrioritasGiziService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Bangun registri Operasi Timbang (anak + data_anak) dari berkas final e-PPGBM.
 *
 * Default DRY-RUN. Menulis hanya dengan --commit, dan --commit WAJIB disertai
 * --connection agar TRUNCATE tidak pernah mengenai DB dev.
 * Lihat specs/2026-07-23-ot-final-registri-import-design.md.
 */
class ImportOtFinal extends Command
{
    protected $signature = 'import:ot-final
        {file : Path berkas .xlsx OT final}
        {--commit : Tulis ke DB (tanpa flag ini hanya dry-run)}
        {--user=1 : id_user pemilik record data_anak}
        {--connection= : Nama koneksi DB target (wajib saat --commit)}';

    protected $description = 'Bangun registri OT (anak + data_anak) dari berkas final e-PPGBM.';

    public function handle(): int
    {
        $file   = (string) $this->argument('file');
        $commit = (bool) $this->option('commit');
        $conn   = (string) ($this->option('connection') ?? '');

        if ($commit && $conn === '') {
            $this->error('--connection wajib diisi saat --commit (mis. --connection=staging).');
            $this->line('Ini mencegah TRUNCATE mengenai database dev.');

            return self::FAILURE;
        }

        if ($conn !== '') {
            if (!config("database.connections.{$conn}")) {
                $this->error("Koneksi '{$conn}' tidak terdaftar di config/database.php.");

                return self::FAILURE;
            }
            DB::setDefaultConnection($conn);
        }

        $this->info('Database target : ' . DB::connection()->getDatabaseName()
            . ' (koneksi: ' . DB::getDefaultConnection() . ')');

        if (!is_file($file)) {
            $this->error("Berkas tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $this->warn('Mode: ' . ($commit ? 'COMMIT (menulis anak & data_anak)' : 'DRY-RUN (tidak menulis apa pun)'));

        // Guard sheet: hanya sheet terlihat pertama (cegah insiden multi-sheet).
        $sheets = CapilImport::inspectSheets($file);
        $target = CapilImport::firstVisibleSheet($sheets);
        if ($warn = CapilImport::sheetWarning($sheets, $target)) {
            $this->warn($warn);
        }

        if ($commit) {
            $this->warn('Mengosongkan data_anak & anak pada ' . DB::connection()->getDatabaseName() . ' ...');
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DataAnak::truncate();
            Anak::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $import = new OtFinalRegistriImport((int) $this->option('user'), $commit, $target);

        app(PrioritasGiziService::class)->duringMutedImport(function () use ($import, $file) {
            Excel::import($import, $file);
        });

        $r = $import->getResults();

        if (!empty($r['error'])) {
            $this->newLine();
            foreach ($r['error'] as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        if ($commit) {
            app(PrioritasGiziService::class)->refreshAll();
        }

        $this->newLine();
        $this->info("ANAK DIBUAT  : {$r['anak_dibuat']}");
        $this->info("UKUR DITULIS : {$r['ukur_ditulis']}");
        $this->line("NIK DUMMY    : {$r['dummy']}");
        $this->line("LEBUR        : {$r['lebur']} (NIK sama, tanggal ukur sama)");
        $this->line("DILEWATI     : {$r['dilewati']}");

        if (!empty($r['peringatan'])) {
            $this->newLine();
            $this->warn('⚠ Peringatan: ' . count($r['peringatan']));
            foreach (array_slice($r['peringatan'], 0, 20) as $p) {
                $this->line('    ' . $p);
            }
        }

        $this->newLine();
        if (!$commit) {
            $this->warn('[DRY-RUN] Tidak ada yang ditulis. Tambahkan --commit --connection=staging untuk menyimpan.');
        }

        return self::SUCCESS;
    }
}
