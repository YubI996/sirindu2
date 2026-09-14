<?php

namespace App\Jobs;

use App\Services\TautanIdentitasService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Pindai ulang kandidat identitas di antrean — dipicu tombol "Pindai ulang" (superadmin). */
class PindaiIdentitasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function handle(TautanIdentitasService $svc): void
    {
        $r = $svc->pindai();
        Log::info("PindaiIdentitasJob selesai: {$r['pasangan']} pasangan.");
    }
}
