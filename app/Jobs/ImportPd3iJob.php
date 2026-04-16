<?php

namespace App\Jobs;

use App\Imports\Pd3iImport;
use App\Models\ImportLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportPd3iJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Jumlah percobaan ulang jika job gagal */
    public int $tries = 1;

    /** Timeout 10 menit untuk file besar */
    public int $timeout = 600;

    public function __construct(
        protected ImportLog $importLog
    ) {}

    public function handle(): void
    {
        $this->importLog->update([
            'status'     => 'processing',
            'started_at' => now(),
        ]);

        try {
            $path = Storage::path($this->importLog->file_path);

            if (!file_exists($path)) {
                throw new \RuntimeException("File tidak ditemukan: {$this->importLog->file_path}");
            }

            $import = new Pd3iImport($this->importLog->user_id);
            Excel::import($import, $path);

            $results = $import->getResults();

            $this->importLog->update([
                'status'        => 'done',
                'success_count' => $results['success'],
                'failure_count' => $results['error_count'],
                'failures'      => $results['failures'] ?: null,
                'completed_at'  => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error("ImportPd3iJob gagal [{$this->importLog->id}]: " . $e->getMessage());

            $this->importLog->update([
                'status'        => 'failed',
                'failures'      => ["Import gagal: " . $e->getMessage()],
                'completed_at'  => now(),
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
