<?php

namespace App\Repositories\Admin\Core\Epidemiologi;

interface SurveillanceRepositoryInterface
{
    public function storeCase($request);
    public function updateCase($request, $id);
    public function deleteCase($id);
    public function getDashboardStats(?string $faskesType = null, ?int $faskesId = null, ?int $diseaseId = null);
    public function getCasesByGeography($level = 'kecamatan', ?array $faskesScope = null, ?int $diseaseId = null);
    public function getCasesTrend($months = 12, ?array $faskesScope = null, ?int $diseaseId = null);
    public function getCasesByDisease(?array $faskesScope = null, ?int $diseaseId = null);
    public function getCasesByStatus(?array $faskesScope = null, ?int $diseaseId = null);
}
