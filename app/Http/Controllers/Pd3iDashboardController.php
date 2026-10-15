<?php

namespace App\Http\Controllers;

use App\Exports\SurveillanceExport;
use App\Models\JenisKasusEpidemiologi;
use App\Models\Kelurahan;
use App\Models\SurveillanceCase;
use App\Repositories\Admin\Epidemiologi\SurveillanceRepository;
use App\Support\FilterPd3i;
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
        return response()->json($this->repo->getPd3iKinerja(FilterPd3i::dariRequest($request)));
    }

    public function demografi(Request $request): JsonResponse
    {
        return response()->json($this->repo->getPd3iDemografi(FilterPd3i::dariRequest($request)));
    }

    public function tren(Request $request): JsonResponse
    {
        return response()->json($this->repo->getPd3iTren(FilterPd3i::dariRequest($request)));
    }

    public function wilayah(Request $request): JsonResponse
    {
        return response()->json($this->repo->getPd3iWilayah(FilterPd3i::dariRequest($request)));
    }

    // ==================== EXPORT ====================

    public function exportPdf(Request $request): Response
    {
        $f = FilterPd3i::dariRequest($request);

        $kinerja   = $this->repo->getPd3iKinerja($f);
        $demografi = $this->repo->getPd3iDemografi($f);
        $tren      = $this->repo->getPd3iTren($f);
        $wilayah   = $this->repo->getPd3iWilayah($f);

        $namaJenisKasus = $f->jenisKasusId
            ? JenisKasusEpidemiologi::find($f->jenisKasusId)?->nama_penyakit
            : null;

        $namaKabKota = $f->kabKota ? implode(', ', $f->kabKota) : null;
        $namaWilker  = $f->wilker ? implode(', ', $f->wilker) : null;
        $namaKelurahan = $f->kelurahanId
            ? Kelurahan::whereIn('id', $f->kelurahanId)->orderBy('name')->pluck('name')->implode(', ')
            : null;

        $pdf = Pdf::loadView('admin.epidemiologi.pdf.pd3i-dashboard', [
            'tahun'          => $f->tahun,
            'periode'        => $f->labelPeriode(),
            'wilker'         => $namaWilker,
            'namaKabKota'    => $namaKabKota,
            'namaKelurahan'  => $namaKelurahan,
            'namaFaskes'     => $f->faskesPelapor ? implode(', ', $f->faskesPelapor) : null,
            'namaJenisKasus' => $namaJenisKasus,
            'kinerja'        => $kinerja,
            'demografi'      => $demografi,
            'tren'           => $tren,
            'wilayah'        => $wilayah,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("pd3i-dashboard-{$f->tahun}.pdf");
    }

    public function exportExcel(Request $request)
    {
        $f = FilterPd3i::dariRequest($request);

        $namaFile = 'surveilans-pd3i-' . $f->tahun;
        if ($f->wilker) {
            $namaFile .= '-' . str_replace(' ', '_', implode('-', $f->wilker));
        }
        $namaFile .= '.xlsx';

        return Excel::download(SurveillanceExport::untukDasborPd3i($f), $namaFile);
    }
}
