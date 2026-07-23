<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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

        return self::SUCCESS;
    }
}
