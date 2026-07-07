<?php

namespace App\Console\Commands;

use App\Services\PrioritasGiziService;
use Illuminate\Console\Command;

class RefreshPrioritasGizi extends Command
{
    protected $signature = 'prioritas:refresh';
    protected $description = 'Bangun ulang snapshot prioritas_gizi untuk seluruh anak';

    public function handle(PrioritasGiziService $service): int
    {
        $this->info('Membangun ulang snapshot prioritas gizi...');
        $jumlah = $service->refreshAll();
        $this->info("Selesai. {$jumlah} anak diproses.");
        return self::SUCCESS;
    }
}
