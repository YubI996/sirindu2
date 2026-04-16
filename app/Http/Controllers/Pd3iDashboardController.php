<?php

namespace App\Http\Controllers;

use App\Models\JenisKasusEpidemiologi;
use App\Models\Kelurahan;
use App\Models\SurveillanceCase;
use App\Repositories\Admin\Epidemiologi\SurveillanceRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class Pd3iDashboardController extends Controller
{
    protected SurveillanceRepository $repo;

    public function __construct(SurveillanceRepository $repo)
    {
        $this->middleware('auth');
        $this->repo = $repo;
    }

    // ==================== VIEWS ====================

    public function index(): View
    {
        $diseases = JenisKasusEpidemiologi::active()->orderBy('nama_penyakit')->get();
        $wilkers = SurveillanceCase::whereNotNull('wilker_puskesmas')
            ->where('wilker_puskesmas', '!=', '')
            ->distinct()
            ->orderBy('wilker_puskesmas')
            ->pluck('wilker_puskesmas');

        // Kelurahan yang ada di data surveillance (untuk filter)
        $kelurahans = Kelurahan::whereIn('id',
            SurveillanceCase::whereNotNull('id_kel')->distinct()->pluck('id_kel')
        )->orderBy('name')->get();

        return view('admin.epidemiologi.pd3i-dashboard', compact('diseases', 'wilkers', 'kelurahans'));
    }

    // ==================== API ENDPOINTS ====================

    public function kinerja(Request $request): JsonResponse
    {
        $filters = $this->parsePd3iFilters($request);
        $data = $this->repo->getPd3iKinerja(
            $filters['tahun'],
            $filters['jenis_kasus_id'],
            $filters['wilker'],
            $filters['kelurahan_id']
        );
        return response()->json($data);
    }

    public function demografi(Request $request): JsonResponse
    {
        $filters = $this->parsePd3iFilters($request);
        $data = $this->repo->getPd3iDemografi(
            $filters['tahun'],
            $filters['jenis_kasus_id'],
            $filters['wilker'],
            $filters['kelurahan_id']
        );
        return response()->json($data);
    }

    public function tren(Request $request): JsonResponse
    {
        $filters = $this->parsePd3iFilters($request);
        $data = $this->repo->getPd3iTren(
            $filters['tahun'],
            $filters['jenis_kasus_id'],
            $filters['wilker'],
            $filters['kelurahan_id']
        );
        return response()->json($data);
    }

    public function wilayah(Request $request): JsonResponse
    {
        $filters = $this->parsePd3iFilters($request);
        $data = $this->repo->getPd3iWilayah(
            $filters['tahun'],
            $filters['jenis_kasus_id'],
            $filters['wilker'],
            $filters['kelurahan_id']
        );
        return response()->json($data);
    }

    // ==================== EXPORT ====================

    public function exportPdf(Request $request): Response
    {
        $filters = $this->parsePd3iFilters($request);

        $kinerja  = $this->repo->getPd3iKinerja($filters['tahun'], $filters['jenis_kasus_id'], $filters['wilker'], $filters['kelurahan_id']);
        $demografi = $this->repo->getPd3iDemografi($filters['tahun'], $filters['jenis_kasus_id'], $filters['wilker'], $filters['kelurahan_id']);
        $tren     = $this->repo->getPd3iTren($filters['tahun'], $filters['jenis_kasus_id'], $filters['wilker'], $filters['kelurahan_id']);
        $wilayah  = $this->repo->getPd3iWilayah($filters['tahun'], $filters['jenis_kasus_id'], $filters['wilker'], $filters['kelurahan_id']);

        $namaJenisKasus = $filters['jenis_kasus_id']
            ? JenisKasusEpidemiologi::find($filters['jenis_kasus_id'])?->nama_penyakit
            : null;

        $namaKelurahan = $filters['kelurahan_id']
            ? Kelurahan::find($filters['kelurahan_id'])?->name
            : null;

        $pdf = Pdf::loadView('admin.epidemiologi.pdf.pd3i-dashboard', [
            'tahun'          => $filters['tahun'],
            'wilker'         => $filters['wilker'],
            'namaKelurahan'  => $namaKelurahan,
            'namaJenisKasus' => $namaJenisKasus,
            'kinerja'        => $kinerja,
            'demografi'      => $demografi,
            'tren'           => $tren,
            'wilayah'        => $wilayah,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("pd3i-dashboard-{$filters['tahun']}.pdf");
    }

    // ==================== HELPERS ====================

    private function parsePd3iFilters(Request $request): array
    {
        return [
            'tahun'          => (int) $request->get('tahun', now()->year),
            'jenis_kasus_id' => $request->filled('jenis_kasus_id') ? (int) $request->jenis_kasus_id : null,
            'wilker'         => $request->filled('wilker') ? trim($request->wilker) : null,
            'kelurahan_id'   => $request->filled('kelurahan_id') ? (int) $request->kelurahan_id : null,
        ];
    }
}
