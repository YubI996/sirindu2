<?php

namespace App\Jobs;

use App\Imports\PengukuranImport;
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

class ImportPengukuranJob implements ShouldQueue
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

            $import  = new PengukuranImport($this->importLog->user_id);
            app(PrioritasGiziService::class)->duringMutedImport(fn () => Excel::import($import, $path));
            app(PrioritasGiziService::class)->refreshAll();
            $results = $import->getResults();

            $this->importLog->update([
                'status'        => 'done',
                'success_count' => $results['success'],
                'failure_count' => $results['error_count'],
                'failures'      => $results['failures'] ?: null,
                'completed_at'  => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error("ImportPengukuranJob gagal [{$this->importLog->id}]: " . $e->getMessage());
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
