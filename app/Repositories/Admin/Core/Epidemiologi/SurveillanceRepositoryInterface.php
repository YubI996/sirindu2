<?php

namespace App\Repositories\Admin\Core\Epidemiologi;

interface SurveillanceRepositoryInterface
{
    public function storeCase($request, ?string $fotoPath = null, ?string $fotoPath2 = null);
    public function updateCase($request, $id, ?string $fotoPath = null, bool $deleteFoto = false, ?string $fotoPath2 = null, bool $deleteFoto2 = false);
    public function deleteCase($id);
    public function getDashboardStats(?\App\Models\User $scopeUser = null, ?int $diseaseId = null);
    public function getCasesByGeography($level = 'kecamatan', ?\App\Models\User $scopeUser = null, ?int $diseaseId = null);
    public function getCasesTrend($months = 12, ?\App\Models\User $scopeUser = null, ?int $diseaseId = null);
    public function getCasesByDisease(?\App\Models\User $scopeUser = null, ?int $diseaseId = null);
    public function getCasesByStatus(?\App\Models\User $scopeUser = null, ?int $diseaseId = null);
}
