<?php

namespace App\Repositories\Admin\Core\Epidemiologi;

interface SurveillanceRepositoryInterface
{
    public function storeCase($request, ?string $fotoPath = null);
    public function updateCase($request, $id, ?string $fotoPath = null, bool $deleteFoto = false);
    public function deleteCase($id);
    public function getDashboardStats(?string $faskesType = null, ?int $faskesId = null, ?int $diseaseId = null);
    public function getCasesByGeography($level = 'kecamatan', ?array $faskesScope = null, ?int $diseaseId = null);
    public function getCasesTrend($months = 12, ?array $faskesScope = null, ?int $diseaseId = null);
    public function getCasesByDisease(?array $faskesScope = null, ?int $diseaseId = null);
    public function getCasesByStatus(?array $faskesScope = null, ?int $diseaseId = null);
}
