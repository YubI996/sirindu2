<?php

namespace App\Http\Controllers;

use App\Exports\KesmasExport;
use App\Models\Kecamatan;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Export Kesmas — Excel dua sheet (Per Anak, Per Kunjungan) sebagai bahan dasbor Kesmas.
 * Spec: docs/superpowers/specs/2026-09-15-data-kesmas-design.md §5.
 * Sama dengan Export Anak lama: tidak ada scoping per faskes selain menolak
 * pengguna modul surveilans (pola ExportImunisasiController).
 */
class ExportKesmasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (auth()->user()->isFaskesSurveilans()) {
                abort(403, 'Akses tidak diizinkan.');
            }

            return $next($request);
        });
    }

    public function index()
    {
        $kec = Kecamatan::orderBy('name')->get();

        return view('admin.export.kesmas', compact('kec'));
    }

    public function download(Request $request)
    {
        $filter = $request->validate([
            'id_kec'       => 'nullable|integer|exists:kecamatan,id',
            'id_kel'       => 'nullable|integer|exists:kelurahan,id',
            'id_puskesmas' => 'nullable|integer|exists:puskesmas,id',
            'id_posyandu'  => 'nullable|integer|exists:posyandu,id',
            'dari'         => 'nullable|date',
            'sampai'       => 'nullable|date|after_or_equal:dari',
        ]);

        $export = new KesmasExport($filter);

        return Excel::download($export, $export->filename());
    }
}
