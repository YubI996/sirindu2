<?php

namespace App\Repositories\Admin\Core\Epidemiologi;

interface SurveillanceRepositoryInterface
{
    public function storeCase($request);
    public function updateCase($request, $id);
    public function deleteCase($id);
    public function getDashboardStats();
    public function getCasesByGeography($level = 'kecamatan');
    public function getCasesTrend($months = 12);
    public function getCasesByDisease();
    public function getCasesByStatus();
}
