<?php

namespace App\Http\Controllers;

use App\Models\Anak;
use App\Models\Kelurahan;
use App\Services\StatusGiziService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TimbangDashboardController extends Controller
{
    public function __construct(private StatusGiziService $statusGizi)
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

        // Latest visit per anak for per-anak stats.
        // Di-pluck sekali: dipakai berulang di whereIn dan untuk hitung jumlah anak
        // (count() langsung pada query ber-groupBy tidak menghitung jumlah grup).
        $latestIds = $this->latestVisitQuery($tahun, $kelId)->pluck('max_id');

        $vitATotal   = $latestIds->count();
        $vitACount   = DB::table('data_anak')->whereIn('id', $latestIds)->where('vit_a', 1)->count();
        $vitACoverage = $vitATotal > 0 ? round($vitACount / $vitATotal * 100, 1) : 0;

        $mbgTotal = DB::table('data_anak')->whereIn('id', $latestIds)->whereNotNull('mbg')->count();
        $mbgCount = DB::table('data_anak')->whereIn('id', $latestIds)->where('mbg', 1)->count();
        $mbgRate  = $mbgTotal > 0 ? round($mbgCount / $mbgTotal * 100, 1) : 0;

        $kelasTotal = DB::table('data_anak')->whereIn('id', $latestIds)->whereNotNull('kelas_ibu_balita')->count();
        $kelasCount = DB::table('data_anak')->whereIn('id', $latestIds)->where('kelas_ibu_balita', 1)->count();
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
            ->whereIn('da.id', $this->latestVisitQuery($tahun, $kelId))
            ->where('da.bln', '<=', 60)
            ->where('da.bb', '>', 0)
            ->where('da.tb', '>', 0);

        $measurements = $q->select('da.id_anak', 'da.bb', 'da.tb', 'da.bln', 'da.posisi', 'a.jk')->get();

        // Pemetaan enum kanonik StatusGiziService → bucket dashboard.
        $imtMap = [
            'severely_wasted' => 'buruk', 'wasted' => 'kurang', 'normal' => 'normal',
            'risiko_lebih' => 'lebih', 'overweight' => 'lebih', 'obese' => 'obesitas',
        ];
        $bbMap = [
            'severely_underweight' => 'sangat_kurang', 'underweight' => 'kurang',
            'normal' => 'normal', 'lebih' => 'lebih',
        ];
        $tbMap = [
            'severely_stunted' => 'sangat_pendek', 'stunted' => 'pendek',
            'normal' => 'normal', 'tinggi' => 'tinggi',
        ];

        foreach ($measurements as $m) {
            $g = $this->statusGizi->klasifikasi($m->bb, $m->tb, $m->bln, $m->posisi, $m->jk);
            if ($g['enum']['imt_u'] !== null) $results['imt_u'][$imtMap[$g['enum']['imt_u']]]++;
            if ($g['enum']['bb_u'] !== null)  $results['bb_u'][$bbMap[$g['enum']['bb_u']]]++;
            if ($g['enum']['tb_u'] !== null)  $results['tb_u'][$tbMap[$g['enum']['tb_u']]]++;
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

        // Kunjungan per bulan: tahun terpilih (Jan–Des) atau 12 bulan terakhir
        $kunjunganQ = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->selectRaw("DATE_FORMAT(da.tgl_kunjungan, '%Y-%m') as bulan, COUNT(*) as total")
            ->whereNotNull('da.tgl_kunjungan');
        if ($tahun) {
            $kunjunganQ->whereYear('da.tgl_kunjungan', $tahun);
        } else {
            $kunjunganQ->where('da.tgl_kunjungan', '>=', now()->subMonths(11)->startOfMonth());
        }
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
        $totalQ = DB::table('anak')
            ->join('kelurahan', 'anak.id_kel', '=', 'kelurahan.id')
            ->selectRaw('kelurahan.id, kelurahan.name as nama, COUNT(DISTINCT anak.id) as total');
        if ($kelId) $totalQ->where('anak.id_kel', $kelId);
        $totalPerKel = $totalQ->groupBy('kelurahan.id', 'kelurahan.name')->get()->keyBy('id');

        $timbangQ = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->join('kelurahan as k', 'a.id_kel', '=', 'k.id')
            ->selectRaw('k.id, k.name as nama, COUNT(DISTINCT da.id_anak) as ditimbang');
        if ($tahun) $timbangQ->whereYear('da.tgl_kunjungan', $tahun);
        if ($kelId) $timbangQ->where('a.id_kel', $kelId);
        $timbangPerKel = $timbangQ->groupBy('k.id', 'k.name')->get()->keyBy('id');

        // Vitamin A per kelurahan (kunjungan terakhir per anak)
        $vitaQ = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->join('kelurahan as k', 'a.id_kel', '=', 'k.id')
            ->whereIn('da.id', $this->latestVisitQuery($tahun, $kelId))
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

        $latestIds = $this->latestVisitQuery($tahun, $kelId)->pluck('max_id');

        $base = DB::table('data_anak')->whereIn('id', $latestIds);

        // Pitting edema — group by nilai ter-coalesce agar NULL & 0 tidak terpisah
        $pittingEdema = (clone $base)
            ->selectRaw('COALESCE(pitting_edema, 0) as level, COUNT(*) as total')
            ->groupBy(DB::raw('COALESCE(pitting_edema, 0)'))
            ->orderBy(DB::raw('COALESCE(pitting_edema, 0)'))
            ->get();

        // ASI per bulan (0-6)
        $asiCols = [];
        for ($i = 0; $i <= 6; $i++) {
            $col = "asi_bulan_{$i}";
            $row = (clone $base)->whereNotNull($col)->selectRaw("SUM(CASE WHEN {$col}=1 THEN 1 ELSE 0 END) as ya, COUNT(*) as total")->first();
            $pct = ($row && $row->total > 0) ? round($row->ya / $row->total * 100, 1) : null;
            $asiCols[] = ['bulan' => $i, 'pct' => $pct, 'ya' => $row->ya ?? 0, 'total' => $row->total ?? 0];
        }

        // Cara ukur — group by nilai ternormalisasi agar varian huruf/spasi menyatu
        $caraUkur = (clone $base)
            ->selectRaw('LOWER(TRIM(posisi)) as cara, COUNT(*) as total')
            ->groupBy(DB::raw('LOWER(TRIM(posisi))'))
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

    /**
     * Builder yang menghasilkan id kunjungan TERAKHIR per anak.
     *
     * "Terakhir" ditentukan oleh tgl_kunjungan terbesar (bukan MAX(id)), karena
     * data hasil import/backfill bisa punya id besar untuk tanggal lama. Tie-break
     * pada tanggal yang sama memakai MAX(id). Kolom hasil: max_id.
     */
    private function latestVisitQuery(?int $tahun, ?int $kelId)
    {
        $maxTgl = DB::table('data_anak as dm')
            ->join('anak as am', 'dm.id_anak', '=', 'am.id')
            ->selectRaw('dm.id_anak, MAX(dm.tgl_kunjungan) as max_tgl')
            ->whereNotNull('dm.tgl_kunjungan');
        if ($tahun) $maxTgl->whereYear('dm.tgl_kunjungan', $tahun);
        if ($kelId) $maxTgl->where('am.id_kel', $kelId);
        $maxTgl->groupBy('dm.id_anak');

        return DB::table('data_anak as da')
            ->joinSub($maxTgl, 'm', function ($join) {
                $join->on('m.id_anak', '=', 'da.id_anak')
                     ->on('m.max_tgl', '=', 'da.tgl_kunjungan');
            })
            ->selectRaw('MAX(da.id) as max_id')
            ->groupBy('da.id_anak');
    }
}
