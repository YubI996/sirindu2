<?php

namespace App\Http\Controllers;

use App\Exports\KesmasExport;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use Illuminate\Http\Request;

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

    public function index(Request $request)
    {
        $filter = $this->filter($request);
        $kec = Kecamatan::orderBy('name')->get();
        $kel = Kelurahan::orderBy('name')->get();
        $puskesmas = Puskesmas::orderBy('name')->get();
        $posyandu = Posyandu::orderBy('name')->get();

        // Muat opsi di server agar tautan dengan filter anak saja juga tetap terpilih.
        return view('admin.export.kesmas', compact('kec', 'kel', 'puskesmas', 'posyandu', 'filter'));
    }

    public function download(Request $request)
    {
        // Streaming: baris dibaca & ditulis saat respons dikirim (lihat KesmasExport).
        return (new KesmasExport($this->filter($request)))->unduh();
    }

    private function filter(Request $request): array
    {
        return $request->validate([
            'id_kec'       => 'nullable|integer|exists:kecamatan,id',
            'id_kel'       => 'nullable|integer|exists:kelurahan,id',
            'id_puskesmas' => 'nullable|integer|exists:puskesmas,id',
            'id_posyandu'  => 'nullable|integer|exists:posyandu,id',
            'dari'         => 'nullable|date',
            'sampai'       => 'nullable|date|after_or_equal:dari',
        ]);
    }
}
