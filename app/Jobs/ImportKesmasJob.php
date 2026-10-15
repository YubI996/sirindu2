<?php

namespace App\Jobs;

use App\Imports\KesmasImport;
use App\Models\ImportLog;
use App\Services\PrioritasGiziService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportKesmasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600;

    public function __construct(protected ImportLog $importLog) {}

    public function handle(): void
    {
        $this->importLog->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $path = Storage::path($this->importLog->file_path);

            if (!file_exists($path)) {
                throw new \RuntimeException("File tidak ditemukan: {$this->importLog->file_path}");
            }

            $import  = (new KesmasImport($this->importLog->user_id))->setFormatTanggal($this->importLog->format_tanggal);
            $prioritas = app(PrioritasGiziService::class);
            $prioritas->duringMutedImport(fn () => Excel::import($import, $path));
            // Hanya anak yang mendapat kunjungan baru: field Kesmas tak memengaruhi prioritas gizi, dan
            // refreshAll() se-kota makan 40–54 dtk walau 0 baris tersimpan — di prod (queue sync) itu
            // ditanggung request unggah petugas (audit DYN-UX-001, 15 Okt 2026).
            if ($ids = $import->anakBerkunjunganBaru()) {
                $prioritas->refreshBatch($ids);
            }
            $results = $import->getResults();

            $this->importLog->update([
                // Header tanpa kolom identitas wajib = tak satu baris pun diproses: gagal, bukan "Selesai 0/0".
                'status'        => $import->dibatalkan() ? 'failed' : 'done',
                'success_count' => $results['success'],
                'failure_count' => $results['error_count'],
                'failures'      => $results['failures'] ?: null,
                'completed_at'  => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error("ImportKesmasJob gagal [{$this->importLog->id}]: " . $e->getMessage());
            $this->importLog->update([
                'status'       => 'failed',
                'failures'     => ["Import gagal: " . $e->getMessage()],
                'completed_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->importLog->update([
            'status'       => 'failed',
            'failures'     => ["Job gagal dieksekusi: " . $exception->getMessage()],
            'completed_at' => now(),
        ]);
    }
}
