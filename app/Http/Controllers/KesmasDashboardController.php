<?php
// app/Http/Controllers/KesmasDashboardController.php

namespace App\Http\Controllers;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Services\ImunisasiStatusService;
use App\Services\KesmasDashboardService;
use App\Support\BatasDataPribadiKesmas;
use App\Support\KohortImunisasi;
use App\Support\PeriodeKesmas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dasbor Kesmas — tumbuh kembang balita & posyandu.
 * Spec: docs/superpowers/specs/2026-09-21-dasbor-kesmas-design.md
 * Akses seperti Export Kesmas: super-admin & admin; pengguna modul surveilans ditolak.
 */
class KesmasDashboardController extends Controller
{
    private const PER_PAGE = 20;

    private const KUNCI_WILAYAH = ['id_kecamatan', 'id_kelurahan', 'id_rt', 'id_posyandu', 'id_puskesmas'];

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
        [$periode, $filters, $usia] = $this->parse($request);
        $svc  = app(KesmasDashboardService::class);
        $imun = app(ImunisasiStatusService::class);

        return view('admin.kesmas.dashboard', [
            'periode' => $periode,
            'filters' => $filters,
            'usia'    => $usia,
            'sasaran' => $svc->sasaran($periode, $filters),
            'penandaan' => $svc->penandaanSasaran($periode, $filters),
            'spm'     => $svc->spmKohort($periode, $filters),
            'tk'      => $svc->pemantauanTk($periode, $filters),
            'sdidtk'  => $svc->sdidtk($periode, $filters),
            'ckg'     => $svc->ckg($periode, $filters),
            'layanan' => $svc->layananLingkungan($periode, $filters),
            'idl'     => $imun->getIdlCoverage(KohortImunisasi::dari($periode->tahun()), $filters),
            'ibl'     => $imun->getIblCoverage(KohortImunisasi::dari($periode->tahun()), $filters),
            'kelompokKosong' => $imun->getKelompokKosong(),
            'alasan'  => array_slice($imun->getAlasanTidakImunisasi($filters), 0, 4, true),
            'kecamatanList' => Kecamatan::orderBy('name')->get(),
            'kelurahanList' => Kelurahan::orderBy('name')->get(),
            'posyanduList'  => Posyandu::orderBy('name')->get(),
            'puskesmasList' => Puskesmas::orderBy('name')->get(),
            'tahunList'     => range(now()->year + 1, 2020),
            // Registri memuat NIK & nama orang tua → hanya bila akun boleh (BatasDataPribadiKesmas).
            'registriBoleh' => BatasDataPribadiKesmas::untuk(auth()->user())['boleh'],
        ]);
    }

    public function registri(Request $request): JsonResponse
    {
        $batas = BatasDataPribadiKesmas::untuk(auth()->user());
        abort_unless($batas['boleh'], 403, 'Akses tidak diizinkan.');

        [$periode, $filters, $usia] = $this->parse($request);
        // Batas wilayah akun menang atas pilihan klien; filter lain tetap AND, jadi hanya bisa mempersempit.
        if ($batas['puskesmas']) {
            $filters['id_puskesmas'] = $batas['puskesmas'];
        }
        $v = $request->validate([
            'q'           => 'nullable|string|max:100',
            'status_gizi' => ['nullable', Rule::in(KesmasDashboardService::STATUS_GIZI)],
            'page'        => 'nullable|integer|min:1',
        ]);

        return response()->json(app(KesmasDashboardService::class)->registri(
            $periode, $filters, $usia,
            trim((string) ($v['q'] ?? '')),
            $v['status_gizi'] ?? 'semua',
            (int) ($v['page'] ?? 1),
            self::PER_PAGE,
        ));
    }

    /** @return array{0: PeriodeKesmas, 1: array<string,int>, 2: string} */
    private function parse(Request $request): array
    {
        $v = $request->validate([
            'tahun'        => ['nullable', 'integer', 'min:2020', 'max:' . (now()->year + 1)],
            'periode'      => ['nullable', Rule::in(PeriodeKesmas::KODE)],
            'id_kecamatan' => 'nullable|integer|exists:kecamatan,id',
            'id_kelurahan' => 'nullable|integer|exists:kelurahan,id',
            'id_rt'        => 'nullable|integer|exists:rt,id',
            'id_posyandu'  => 'nullable|integer|exists:posyandu,id',
            'id_puskesmas' => 'nullable|integer|exists:puskesmas,id',
            'usia'         => ['nullable', Rule::in(array_keys(KesmasDashboardService::USIA))],
        ]);

        $periode = PeriodeKesmas::dari((int) ($v['tahun'] ?? now()->year), $v['periode'] ?? 'tahun');
        $filters = [];
        foreach (self::KUNCI_WILAYAH as $k) {
            if (!empty($v[$k])) {
                $filters[$k] = (int) $v[$k];
            }
        }

        return [$periode, $filters, $v['usia'] ?? 'semua'];
    }
}
