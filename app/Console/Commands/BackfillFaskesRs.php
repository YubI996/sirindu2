<?php

namespace App\Console\Commands;

use App\Models\RumahSakit;
use App\Models\SurveillanceCase;
use App\Traits\ResolvesRumahSakit;
use Illuminate\Console\Command;

/**
 * Isi faskes_type='rs' + id_faskes pada kasus surveilans hasil import.
 *
 * Latar: Pd3iImport tidak pernah mengisi faskes_type/id_faskes (berbeda dengan
 * input manual lewat SurveillanceRepository::storeCase). Padahal
 * SurveillanceCase::scopeVisibleTo menyaring user surveilans_rs MURNI lewat
 * faskes_type='rs' + id_faskes — tanpa fallback wilayah seperti puskesmas.
 * Akibatnya seluruh kasus hasil import tak pernah terlihat oleh user RS.
 *
 * Importer sudah diperbaiki; command ini memperbaiki data yang terlanjur masuk.
 * Atribusi memakai instansi_pelapor (faskes pelapor), dicocokkan ketat ke master RS.
 */
class BackfillFaskesRs extends Command
{
    use ResolvesRumahSakit;

    protected $signature = 'surveilans:backfill-faskes-rs
        {--apply : Terapkan perubahan (tanpa flag ini hanya dry-run)}';

    protected $description = 'Isi faskes_type/id_faskes kasus surveilans dari instansi_pelapor agar user RS bisa melihat datanya.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $this->initRumahSakitCache();

        if (empty($this->rsCache)) {
            $this->error('Master rumah_sakits kosong — jalankan RumahSakitSeeder dulu.');
            return self::FAILURE;
        }

        $namaRs = RumahSakit::pluck('name', 'id');

        $this->line($apply ? 'Mode: APPLY (menulis ke database).' : 'Mode: DRY-RUN (tidak menulis apa pun).');
        $this->newLine();

        // Kelompokkan per instansi_pelapor — jumlah nilai distinct sedikit,
        // jadi update bisa dilakukan per-grup (bukan per-baris).
        $grup = SurveillanceCase::query()
            ->whereNull('faskes_type')
            ->selectRaw('instansi_pelapor, COUNT(*) as n')
            ->groupBy('instansi_pelapor')
            ->orderByDesc('n')
            ->get();

        if ($grup->isEmpty()) {
            $this->info('Tidak ada kasus dengan faskes_type kosong — tidak ada yang perlu diisi.');
            return self::SUCCESS;
        }

        $baris        = [];
        $totalCocok   = 0;
        $totalLewat   = 0;

        foreach ($grup as $g) {
            $idRs  = $this->resolveRumahSakit($g->instansi_pelapor);
            $cocok = $idRs !== null;

            $baris[] = [
                $g->instansi_pelapor ?? '(kosong)',
                $g->n,
                $cocok ? "RS #{$idRs} — {$namaRs[$idRs]}" : '— (bukan RS / tak ada di master)',
                $cocok ? ($apply ? 'diisi' : 'akan diisi') : 'dilewati',
            ];

            if (!$cocok) {
                $totalLewat += $g->n;
                continue;
            }

            $totalCocok += $g->n;

            if ($apply) {
                SurveillanceCase::query()
                    ->whereNull('faskes_type')
                    ->where('instansi_pelapor', $g->instansi_pelapor)
                    ->update(['faskes_type' => 'rs', 'id_faskes' => $idRs]);
            }
        }

        $this->table(['instansi_pelapor', 'Jumlah', 'Dipetakan ke', 'Aksi'], $baris);
        $this->newLine();
        $this->info("Cocok RS   : {$totalCocok} kasus" . ($apply ? ' (sudah diisi)' : ' (akan diisi)'));
        $this->line("Dilewati   : {$totalLewat} kasus — pelapor bukan RS (mis. puskesmas) atau tak ada di master RS.");
        $this->line('             Kasus dilewati tetap terlihat Dinkes, dan puskesmas via wilayah (id_kel).');

        if (!$apply) {
            $this->newLine();
            $this->comment('Dry-run. Jalankan ulang dengan --apply untuk menulis perubahan.');
        }

        return self::SUCCESS;
    }
}
