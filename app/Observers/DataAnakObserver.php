<?php

namespace App\Observers;

use App\Models\DataAnak;
use App\Services\PrioritasGiziService;

class DataAnakObserver
{
    public function __construct(private PrioritasGiziService $service) {}

    public function saved(DataAnak $dataAnak): void
    {
        if (PrioritasGiziService::$muted) return;
        $this->service->refreshAnak((int) $dataAnak->id_anak);
    }

    public function deleted(DataAnak $dataAnak): void
    {
        if (PrioritasGiziService::$muted) return;
        $this->service->refreshAnak((int) $dataAnak->id_anak);
    }
}
