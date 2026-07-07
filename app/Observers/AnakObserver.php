<?php

namespace App\Observers;

use App\Models\Anak;
use App\Models\PrioritasGizi;
use App\Services\PrioritasGiziService;

class AnakObserver
{
    public function __construct(private PrioritasGiziService $service) {}

    public function saved(Anak $anak): void
    {
        if (PrioritasGiziService::$muted) return;
        $this->service->refreshAnak((int) $anak->id);
    }

    public function deleted(Anak $anak): void
    {
        if (PrioritasGiziService::$muted) return;
        PrioritasGizi::where('id_anak', $anak->id)->delete();
    }
}
