<?php

namespace App\Http\Controllers;

use App\Exports\SurveillanceExport;
use App\Models\JenisKasusEpidemiologi;
use App\Models\Kelurahan;
use App\Models\SurveillanceCase;
use App\Repositories\Admin\Epidemiologi\SurveillanceRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

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

        // Kabupaten/Kota yang ada di data surveillance (untuk filter — data bisa dari luar Bontang)
        $kabKotas = SurveillanceCase::whereNotNull('kab_kota')
            ->where('kab_kota', '!=', '')
            ->distinct()
            ->orderBy('kab_kota')
            ->pluck('kab_kota');

        // Kelurahan yang ada di data surveillance (untuk filter)
        $kelurahans = Kelurahan::whereIn('id',
            SurveillanceCase::whereNotNull('id_kel')->distinct()->pluck('id_kel')
        )->orderBy('name')->get();

        return view('admin.epidemiologi.pd3i-dashboard', compact('diseases', 'wilkers', 'kabKotas', 'kelurahans'));
    }

    // ==================== API ENDPOINTS ====================

    public function kinerja(Request $request): JsonResponse
    {
        $filters = $this->parsePd3iFilters($request);
        $data = $this->repo->getPd3iKinerja(
            $filters['tahun'],
            $filters['jenis_kasus_id'],
            $filters['wilker'],
            $filters['kelurahan_id'],
            $filters['kab_kota']
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
            $filters['kelurahan_id'],
            $filters['kab_kota']
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
            $filters['kelurahan_id'],
            $filters['kab_kota']
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
            $filters['kelurahan_id'],
            $filters['kab_kota']
        );
        return response()->json($data);
    }

    // ==================== EXPORT ====================

    public function exportPdf(Request $request): Response
    {
        $filters = $this->parsePd3iFilters($request);

        $kinerja  = $this->repo->getPd3iKinerja($filters['tahun'], $filters['jenis_kasus_id'], $filters['wilker'], $filters['kelurahan_id'], $filters['kab_kota']);
        $demografi = $this->repo->getPd3iDemografi($filters['tahun'], $filters['jenis_kasus_id'], $filters['wilker'], $filters['kelurahan_id'], $filters['kab_kota']);
        $tren     = $this->repo->getPd3iTren($filters['tahun'], $filters['jenis_kasus_id'], $filters['wilker'], $filters['kelurahan_id'], $filters['kab_kota']);
        $wilayah  = $this->repo->getPd3iWilayah($filters['tahun'], $filters['jenis_kasus_id'], $filters['wilker'], $filters['kelurahan_id'], $filters['kab_kota']);

        $namaJenisKasus = $filters['jenis_kasus_id']
            ? JenisKasusEpidemiologi::find($filters['jenis_kasus_id'])?->nama_penyakit
            : null;

        $namaKabKota = $filters['kab_kota'] ? implode(', ', $filters['kab_kota']) : null;
        $namaWilker  = $filters['wilker'] ? implode(', ', $filters['wilker']) : null;
        $namaKelurahan = $filters['kelurahan_id']
            ? Kelurahan::whereIn('id', $filters['kelurahan_id'])->orderBy('name')->pluck('name')->implode(', ')
            : null;

        $pdf = Pdf::loadView('admin.epidemiologi.pdf.pd3i-dashboard', [
            'tahun'          => $filters['tahun'],
            'wilker'         => $namaWilker,
            'namaKabKota'    => $namaKabKota,
            'namaKelurahan'  => $namaKelurahan,
            'namaJenisKasus' => $namaJenisKasus,
            'kinerja'        => $kinerja,
            'demografi'      => $demografi,
            'tren'           => $tren,
            'wilayah'        => $wilayah,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("pd3i-dashboard-{$filters['tahun']}.pdf");
    }

    public function exportExcel(Request $request)
    {
        $filters = $this->parsePd3iFilters($request);

        $namaFile = 'surveilans-pd3i-' . $filters['tahun'];
        if ($filters['wilker']) {
            $namaFile .= '-' . str_replace(' ', '_', implode('-', $filters['wilker']));
        }
        $namaFile .= '.xlsx';

        return Excel::download(
            new SurveillanceExport(
                $filters['tahun'],
                $filters['jenis_kasus_id'],
                $filters['wilker'],
                $filters['kelurahan_id'],
                $filters['kab_kota']
            ),
            $namaFile
        );
    }

    // ==================== HELPERS ====================

    private function parsePd3iFilters(Request $request): array
    {
        return [
            'tahun'          => (int) $request->get('tahun', now()->year),
            'jenis_kasus_id' => $request->filled('jenis_kasus_id') ? (int) $request->jenis_kasus_id : null,
            'wilker'         => $this->normalizeMulti($request->input('wilker')),
            'kelurahan_id'   => $this->normalizeMulti($request->input('kelurahan_id'), true),
            'kab_kota'       => $this->normalizeMulti($request->input('kab_kota')),
        ];
    }

    /**
     * Normalize a multiselect filter input into a clean array (or null when empty).
     * Accepts either an array (name[]=…) or a single scalar value.
     */
    private function normalizeMulti($value, bool $asInt = false): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $items = is_array($value) ? $value : [$value];
        $items = array_filter($items, fn ($v) => $v !== null && $v !== '');

        if (empty($items)) {
            return null;
        }

        $items = $asInt
            ? array_map('intval', $items)
            : array_map(fn ($v) => trim((string) $v), $items);

        return array_values(array_unique($items));
    }
}
