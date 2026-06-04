<?php

namespace App\Http\Controllers;

use App\Models\Anak;
use App\Models\Kelurahan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TimbangDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $user = auth()->user();

        $kelurahanList = $user->isSuperAdmin()
            ? Kelurahan::orderBy('name')->get()
            : Kelurahan::where('id', $user->id_kel)->get();

        $tahunList = DB::table('data_anak')
            ->selectRaw('YEAR(tgl_kunjungan) as tahun')
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun');

        return view('admin.dashboard.timbang', compact('kelurahanList', 'tahunList'));
    }

    // ==================== API ====================

    public function ringkasan(Request $request): JsonResponse
    {
        [$tahun, $kelId] = $this->parseFilters($request);

        $q = $this->baseQuery($tahun, $kelId);

        $totalKunjungan   = (clone $q)->count();
        $totalDitimbang   = (clone $q)->distinct('id_anak')->count('id_anak');
        $totalAnak        = $this->totalAnakQuery($kelId);
        $coverage         = $totalAnak > 0 ? round($totalDitimbang / $totalAnak * 100, 1) : 0;

        // Latest visit per anak for per-anak stats
        $latestIds = $this->latestIds($tahun, $kelId);

        $vitATotal   = (clone $latestIds)->count();
        $vitACount   = DB::table('data_anak')->whereIn('id', $latestIds->select('max_id'))->where('vit_a', 1)->count();
        $vitACoverage = $vitATotal > 0 ? round($vitACount / $vitATotal * 100, 1) : 0;

        $mbgTotal = DB::table('data_anak')->whereIn('id', $latestIds->select('max_id'))->whereNotNull('mbg')->count();
        $mbgCount = DB::table('data_anak')->whereIn('id', $latestIds->select('max_id'))->where('mbg', 1)->count();
        $mbgRate  = $mbgTotal > 0 ? round($mbgCount / $mbgTotal * 100, 1) : 0;

        $kelasTotal = DB::table('data_anak')->whereIn('id', $latestIds->select('max_id'))->whereNotNull('kelas_ibu_balita')->count();
        $kelasCount = DB::table('data_anak')->whereIn('id', $latestIds->select('max_id'))->where('kelas_ibu_balita', 1)->count();
        $kelasRate  = $kelasTotal > 0 ? round($kelasCount / $kelasTotal * 100, 1) : 0;

        return response()->json([
            'total_kunjungan'  => $totalKunjungan,
            'total_ditimbang'  => $totalDitimbang,
            'total_anak'       => $totalAnak,
            'coverage'         => $coverage,
            'vit_a_coverage'   => $vitACoverage,
            'mbg_rate'         => $mbgRate,
            'kelas_ibu_rate'   => $kelasRate,
        ]);
    }

    public function gizi(Request $request): JsonResponse
    {
        [$tahun, $kelId] = $this->parseFilters($request);

        $results = [
            'imt_u' => ['normal' => 0, 'kurang' => 0, 'buruk' => 0, 'lebih' => 0, 'obesitas' => 0],
            'bb_u'  => ['normal' => 0, 'kurang' => 0, 'sangat_kurang' => 0, 'lebih' => 0],
            'tb_u'  => ['normal' => 0, 'pendek' => 0, 'sangat_pendek' => 0, 'tinggi' => 0],
        ];

        $q = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->whereIn('da.id', function ($sub) use ($tahun, $kelId) {
                $sub->selectRaw('MAX(id)')->from('data_anak');
                if ($tahun) $sub->whereYear('tgl_kunjungan', $tahun);
                $sub->groupBy('id_anak');
            })
            ->where('da.bln', '<=', 60)
            ->where('da.bb', '>', 0)
            ->where('da.tb', '>', 0);

        if ($kelId) {
            $q->where('a.id_kel', $kelId);
        }

        $measurements = $q->select('da.id_anak', 'da.bb', 'da.tb', 'da.bln', 'da.posisi', 'a.jk')->get();

        $zScoreRefs = DB::table('z_score')
            ->whereIn('jenis_tbl', [1, 2, 3])
            ->get()
            ->groupBy(fn($r) => $r->jenis_tbl . '_' . $r->jk . '_' . $r->acuan . '_' . ($r->var ?? 0));

        foreach ($measurements as $m) {
            $tb  = $m->tb;
            if ($m->bln < 24 && strtoupper($m->posisi ?? '') === 'H') $tb += 0.7;
            elseif ($m->bln >= 24 && strtoupper($m->posisi ?? '') === 'L') $tb -= 0.7;
            $tb  = round($tb);
            $var = $m->bln <= 24 ? 1 : 2;
            $bmi = $tb > 0 ? round(10000 * $m->bb / pow($tb, 2), 2) : 0;

            $imtKey = "1_{$m->jk}_{$m->bln}_{$var}";
            if (isset($zScoreRefs[$imtKey]) && $zScoreRefs[$imtKey]->isNotEmpty()) {
                $ref = $zScoreRefs[$imtKey]->first();
                if ($bmi < $ref->m3sd)          $results['imt_u']['buruk']++;
                elseif ($bmi < $ref->m2sd)      $results['imt_u']['kurang']++;
                elseif ($bmi <= $ref->{'1sd'})  $results['imt_u']['normal']++;
                elseif ($bmi <= $ref->{'2sd'})  $results['imt_u']['lebih']++;
                else                            $results['imt_u']['obesitas']++;
            }

            $bbKey = "2_{$m->jk}_{$m->bln}_1";
            if (isset($zScoreRefs[$bbKey]) && $zScoreRefs[$bbKey]->isNotEmpty()) {
                $ref = $zScoreRefs[$bbKey]->first();
                if ($m->bb < $ref->m3sd)        $results['bb_u']['sangat_kurang']++;
                elseif ($m->bb < $ref->m2sd)    $results['bb_u']['kurang']++;
                elseif ($m->bb <= $ref->{'1sd'}) $results['bb_u']['normal']++;
                else                            $results['bb_u']['lebih']++;
            }

            $tbKey = "3_{$m->jk}_{$m->bln}_{$var}";
            if (isset($zScoreRefs[$tbKey]) && $zScoreRefs[$tbKey]->isNotEmpty()) {
                $ref = $zScoreRefs[$tbKey]->first();
                if ($tb < $ref->m3sd)           $results['tb_u']['sangat_pendek']++;
                elseif ($tb < $ref->m2sd)       $results['tb_u']['pendek']++;
                elseif ($tb <= $ref->{'3sd'})   $results['tb_u']['normal']++;
                else                            $results['tb_u']['tinggi']++;
            }
        }

        $total = count($measurements);

        // Stunting = sangat_pendek + pendek
        $stunting = $results['tb_u']['sangat_pendek'] + $results['tb_u']['pendek'];
        // Wasting = underweight by BB/U (kurang + sangat_kurang)
        $underweight = $results['bb_u']['kurang'] + $results['bb_u']['sangat_kurang'];

        return response()->json([
            'total'       => $total,
            'imt_u'       => $results['imt_u'],
            'bb_u'        => $results['bb_u'],
            'tb_u'        => $results['tb_u'],
            'stunting'    => $stunting,
            'stunting_pct'=> $total > 0 ? round($stunting / $total * 100, 1) : 0,
            'underweight' => $underweight,
            'underweight_pct' => $total > 0 ? round($underweight / $total * 100, 1) : 0,
        ]);
    }

    public function tren(Request $request): JsonResponse
    {
        [$tahun, $kelId] = $this->parseFilters($request);

        // Kunjungan per bulan (12 bulan terakhir)
        $kunjunganQ = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->selectRaw("DATE_FORMAT(da.tgl_kunjungan, '%Y-%m') as bulan, COUNT(*) as total")
            ->where('da.tgl_kunjungan', '>=', now()->subMonths(11)->startOfMonth());
        if ($kelId) $kunjunganQ->where('a.id_kel', $kelId);
        $kunjunganTren = $kunjunganQ->groupBy('bulan')->orderBy('bulan')->get();

        // Rata-rata BB dan TB per bulan usia (0-60)
        $growthQ = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->select('da.bln', DB::raw('ROUND(AVG(da.bb),2) as avg_bb'), DB::raw('ROUND(AVG(da.tb),2) as avg_tb'), DB::raw('COUNT(*) as n'))
            ->whereBetween('da.bln', [0, 60])
            ->where('da.bb', '>', 0)
            ->where('da.tb', '>', 0);
        if ($tahun) $growthQ->whereYear('da.tgl_kunjungan', $tahun);
        if ($kelId) $growthQ->where('a.id_kel', $kelId);
        $growthTren = $growthQ->groupBy('da.bln')->orderBy('da.bln')->get();

        return response()->json([
            'kunjungan' => $kunjunganTren,
            'growth'    => $growthTren,
        ]);
    }

    public function coverage(Request $request): JsonResponse
    {
        [$tahun, $kelId] = $this->parseFilters($request);

        // Coverage timbang per kelurahan
        $totalPerKel = DB::table('anak')
            ->join('kelurahan', 'anak.id_kel', '=', 'kelurahan.id')
            ->selectRaw('kelurahan.id, kelurahan.name as nama, COUNT(DISTINCT anak.id) as total')
            ->groupBy('kelurahan.id', 'kelurahan.name')
            ->get()
            ->keyBy('id');

        $timbangQ = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->join('kelurahan as k', 'a.id_kel', '=', 'k.id')
            ->selectRaw('k.id, k.name as nama, COUNT(DISTINCT da.id_anak) as ditimbang');
        if ($tahun) $timbangQ->whereYear('da.tgl_kunjungan', $tahun);
        $timbangPerKel = $timbangQ->groupBy('k.id', 'k.name')->get()->keyBy('id');

        // Vitamin A per kelurahan (latest visit)
        $vitaQ = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->join('kelurahan as k', 'a.id_kel', '=', 'k.id')
            ->whereIn('da.id', function ($sub) use ($tahun) {
                $sub->selectRaw('MAX(id)')->from('data_anak');
                if ($tahun) $sub->whereYear('tgl_kunjungan', $tahun);
                $sub->groupBy('id_anak');
            })
            ->selectRaw('k.id, k.name as nama, COUNT(*) as total, SUM(CASE WHEN da.vit_a=1 THEN 1 ELSE 0 END) as vit_a');
        $vitaPerKel = $vitaQ->groupBy('k.id', 'k.name')->get()->keyBy('id');

        $rows = [];
        foreach ($totalPerKel as $id => $kel) {
            $ditimbang = $timbangPerKel[$id]->ditimbang ?? 0;
            $vitaTot   = $vitaPerKel[$id]->total ?? 0;
            $vitaJml   = $vitaPerKel[$id]->vit_a ?? 0;
            $rows[]    = [
                'nama'              => $kel->nama,
                'total_anak'        => $kel->total,
                'ditimbang'         => $ditimbang,
                'coverage_pct'      => $kel->total > 0 ? round($ditimbang / $kel->total * 100, 1) : 0,
                'vit_a_pct'         => $vitaTot > 0 ? round($vitaJml / $vitaTot * 100, 1) : 0,
            ];
        }

        usort($rows, fn($a, $b) => $b['coverage_pct'] <=> $a['coverage_pct']);

        return response()->json($rows);
    }

    public function program(Request $request): JsonResponse
    {
        [$tahun, $kelId] = $this->parseFilters($request);

        $latestIdsSub = DB::table('data_anak as d2')
            ->join('anak as a2', 'd2.id_anak', '=', 'a2.id')
            ->selectRaw('MAX(d2.id) as max_id');
        if ($tahun) $latestIdsSub->whereYear('d2.tgl_kunjungan', $tahun);
        if ($kelId) $latestIdsSub->where('a2.id_kel', $kelId);
        $latestIdsSub->groupBy('d2.id_anak');
        $latestIds = $latestIdsSub->pluck('max_id');

        $base = DB::table('data_anak')->whereIn('id', $latestIds);

        // Pitting edema
        $pittingEdema = (clone $base)
            ->selectRaw('COALESCE(pitting_edema, 0) as level, COUNT(*) as total')
            ->groupBy('pitting_edema')
            ->orderBy('pitting_edema')
            ->get();

        // ASI per bulan (0-6)
        $asiCols = [];
        for ($i = 0; $i <= 6; $i++) {
            $col = "asi_bulan_{$i}";
            $row = (clone $base)->whereNotNull($col)->selectRaw("SUM(CASE WHEN {$col}=1 THEN 1 ELSE 0 END) as ya, COUNT(*) as total")->first();
            $pct = ($row && $row->total > 0) ? round($row->ya / $row->total * 100, 1) : null;
            $asiCols[] = ['bulan' => $i, 'pct' => $pct, 'ya' => $row->ya ?? 0, 'total' => $row->total ?? 0];
        }

        // Cara ukur
        $caraUkur = (clone $base)
            ->selectRaw('LOWER(TRIM(posisi)) as cara, COUNT(*) as total')
            ->groupBy('posisi')
            ->get();

        // MBG
        $mbgData = (clone $base)->whereNotNull('mbg')
            ->selectRaw('SUM(CASE WHEN mbg=1 THEN 1 ELSE 0 END) as ya, COUNT(*) as total')->first();

        // Kelas ibu balita
        $kelasData = (clone $base)->whereNotNull('kelas_ibu_balita')
            ->selectRaw('SUM(CASE WHEN kelas_ibu_balita=1 THEN 1 ELSE 0 END) as ya, COUNT(*) as total')->first();

        return response()->json([
            'pitting_edema' => $pittingEdema,
            'asi_per_bulan' => $asiCols,
            'cara_ukur'     => $caraUkur,
            'mbg'           => $mbgData,
            'kelas_ibu'     => $kelasData,
        ]);
    }

    // ==================== HELPERS ====================

    private function parseFilters(Request $request): array
    {
        $user  = auth()->user();
        $tahun = $request->query('tahun') ? (int) $request->query('tahun') : null;

        if (!$user->isSuperAdmin()) {
            // Faskes users are locked to their assigned kelurahan; deny if none assigned.
            abort_if(!$user->id_kel, 403);
            $kelId = (int) $user->id_kel;
        } else {
            $kelId = $request->query('kelurahan') ? (int) $request->query('kelurahan') : null;
        }

        return [$tahun, $kelId];
    }

    private function baseQuery(?int $tahun, ?int $kelId)
    {
        $q = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id');
        if ($tahun) $q->whereYear('da.tgl_kunjungan', $tahun);
        if ($kelId) $q->where('a.id_kel', $kelId);
        return $q;
    }

    private function totalAnakQuery(?int $kelId): int
    {
        $q = Anak::query();
        if ($kelId) $q->where('id_kel', $kelId);
        return $q->count();
    }

    private function latestIds(?int $tahun, ?int $kelId)
    {
        $q = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->selectRaw('MAX(da.id) as max_id');
        if ($tahun) $q->whereYear('da.tgl_kunjungan', $tahun);
        if ($kelId) $q->where('a.id_kel', $kelId);
        return $q->groupBy('da.id_anak');
    }
}
