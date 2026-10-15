<?php

namespace App\Http\Controllers;

use App\Exports\KesmasExport;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Support\AksesKesmas;
use Illuminate\Http\Request;

/**
 * Export Kesmas — Excel dua sheet (Per Anak, Per Kunjungan) sebagai bahan dasbor Kesmas.
 * Spec: docs/superpowers/specs/2026-09-15-data-kesmas-design.md §5.
 * Pengguna modul surveilans ditolak. Akun imunisasi_faskes hanya mengunduh anak di catchment
 * puskesmas-nya, dan ditolak bila tak punya puskesmas (AksesKesmas) — berkas ini memuat
 * NIK, nama orang tua, penyakit penyerta, dan riwayat kunjungan.
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
        $this->akses();
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
        $akses = $this->akses();
        $filter = $this->filter($request);
        // Bukan input klien: ditambahkan SETELAH validasi supaya tak bisa dilewati lewat query string.
        if (($batas = $akses->batasKelurahanExport()) !== null) {
            $filter['batas_kelurahan'] = $batas;
        }

        // Streaming: baris dibaca & ditulis saat respons dikirim (lihat KesmasExport).
        return (new KesmasExport($filter))->unduh();
    }

    private function akses(): AksesKesmas
    {
        $akses = AksesKesmas::untuk(auth()->user());
        abort_unless($akses->bolehExport(), 403, 'Akses tidak diizinkan.');

        return $akses;
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
