<?php

namespace App\Http\Controllers;

use App\Exports\LaporanKasusIndividuExport;
use App\Models\JenisKasusEpidemiologi;
use App\Models\SurveillanceCase;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Laporan Kasus PD3I — "List Kasus Individu" per penyakit (Export Data).
 * User memilih penyakit + tahun, lalu mengunduh .xlsx dengan format list individu.
 */
class LaporanPd3iController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $diseases = JenisKasusEpidemiologi::active()->orderBy('nama_penyakit')->get();

        $tahunTersedia = SurveillanceCase::query()
            ->selectRaw('DISTINCT YEAR(tanggal_lapor) as th')
            ->whereNotNull('tanggal_lapor')
            ->orderByDesc('th')
            ->pluck('th')
            ->filter()
            ->values();

        if ($tahunTersedia->isEmpty()) {
            $tahunTersedia = collect([now()->year]);
        }

        return view('admin.epidemiologi.laporan-individu', compact('diseases', 'tahunTersedia'));
    }

    public function download(Request $request)
    {
        $data = $request->validate([
            'jenis_kasus_id' => 'required|integer|exists:jenis_kasus_epidemiologi,id',
            'tahun'          => 'required|integer|min:2000|max:2100',
        ]);

        $disease = JenisKasusEpidemiologi::findOrFail($data['jenis_kasus_id']);

        $namaFile = 'list-individu-'
            . str_replace(' ', '_', strtolower($disease->nama_penyakit))
            . '-' . $data['tahun'] . '.xlsx';

        return Excel::download(
            new LaporanKasusIndividuExport((int) $data['tahun'], (int) $data['jenis_kasus_id']),
            $namaFile
        );
    }
}
