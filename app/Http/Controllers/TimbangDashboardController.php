<?php

namespace App\Http\Controllers;

use App\Exports\TimbangDaftarExport;
use App\Models\Anak;
use App\Models\AppSetting;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Services\StatusGiziService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class TimbangDashboardController extends Controller
{
    public function __construct(private StatusGiziService $statusGizi)
    {
        // Endpoint agregat + landing publik boleh diakses tamu (tanpa login).
        // daftar/daftarExport TIDAK dikecualikan → tetap auth-only (privasi nama/NIK).
        $this->middleware('auth')->except([
            'landing', 'ringkasan', 'gizi', 'tren', 'coverage', 'program',
        ]);
    }

    /**
     * Nyalakan/matikan publikasi ringkasan Operasi Timbang di landing publik.
     *
     * Hanya Dinkes (superadmin) — di aplikasi ini seluruh helper isDinkes*
     * memang mengembalikan isSuperAdmin().
     */
    public function setPublikasi(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403, 'Hanya Dinkes yang dapat mengubah publikasi.');

        $aktif = $request->boolean('aktif');
        AppSetting::setBool(AppSetting::KEY_TIMBANG_PUBLIK, $aktif);

        return response()->json([
            'success' => true,
            'aktif'   => $aktif,
            'message' => $aktif
                ? 'Ringkasan Operasi Timbang kini tampil di halaman publik.'
                : 'Ringkasan Operasi Timbang disembunyikan dari halaman publik.',
        ]);
    }

    public function index(): View
    {
        $user = auth()->user();

        $kecamatanList = $user->isSuperAdmin() ? Kecamatan::orderBy('name')->get() : collect();
        $kelurahanList = $user->isSuperAdmin()
            ? Kelurahan::orderBy('name')->get()
            : Kelurahan::where('id', $user->id_kel)->get();

        $tahunList = DB::table('data_anak')
            ->selectRaw('YEAR(tgl_kunjungan) as tahun')
            ->whereNotNull('tgl_kunjungan')
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun');

        $publikasiAktif = AppSetting::timbangPublikAktif();

        return view('admin.dashboard.timbang', compact('kecamatanList', 'kelurahanList', 'tahunList', 'publikasiAktif'));
    }

    /** Landing publik — wajah aplikasi SIRINDU (tanpa login). */
    public function landing(): View
    {
        $kecamatanList = Kecamatan::orderBy('name')->get();
        $kelurahanList = Kelurahan::orderBy('name')->get();

        $tahunList = DB::table('data_anak')
            ->selectRaw('YEAR(tgl_kunjungan) as tahun')
            ->whereNotNull('tgl_kunjungan')
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun');

        $publikasiAktif = AppSetting::timbangPublikAktif();

        return view('public.timbang', compact('kecamatanList', 'kelurahanList', 'tahunList', 'publikasiAktif'));
    }

    // ==================== API ====================

    public function ringkasan(Request $request): JsonResponse
    {
        $f = $this->parseFilters($request);

        $q = $this->baseQuery($f);

        $totalKunjungan = (clone $q)->count();
        $totalDitimbang = (clone $q)->distinct('da.id_anak')->count('da.id_anak');
        $totalAnak      = $this->totalAnakQuery($f);
        $coverage       = $totalAnak > 0 ? round($totalDitimbang / $totalAnak * 100, 1) : 0;

        $latestIds = $this->latestVisitQuery($f)->pluck('max_id');

        $vitATotal    = $latestIds->count();
        $vitACount    = DB::table('data_anak')->whereIn('id', $latestIds)->where('vit_a', 1)->count();
        $vitACoverage = $vitATotal > 0 ? round($vitACount / $vitATotal * 100, 1) : 0;

        $kelasTotal = DB::table('data_anak')->whereIn('id', $latestIds)->whereNotNull('kelas_ibu_balita')->count();
        $kelasCount = DB::table('data_anak')->whereIn('id', $latestIds)->where('kelas_ibu_balita', 1)->count();
        $kelasRate  = $kelasTotal > 0 ? round($kelasCount / $kelasTotal * 100, 1) : 0;

        // BB tidak naik (perbandingan 2 kunjungan terakhir + fallback ntob).
        $bbTidakNaik = count($this->bbTidakNaikIds($f));

        return response()->json([
            'total_kunjungan' => $totalKunjungan,
            'total_ditimbang' => $totalDitimbang,
            'total_anak'      => $totalAnak,
            'coverage'        => $coverage,
            'vit_a_coverage'  => $vitACoverage,
            'kelas_ibu_rate'  => $kelasRate,
            'bb_tidak_naik'   => $bbTidakNaik,
        ]);
    }

    public function gizi(Request $request): JsonResponse
    {
        $f = $this->parseFilters($request);

        // Dashboard ini khusus balita (<=60 bln). Status wasting balita memakai
        // indikator BB/TB (Berat Badan menurut Tinggi/Panjang Badan), bukan IMT/U.
        $results = [
            'bb_tb' => ['normal' => 0, 'kurang' => 0, 'buruk' => 0, 'lebih' => 0, 'obesitas' => 0],
            'bb_u'  => ['normal' => 0, 'kurang' => 0, 'sangat_kurang' => 0, 'lebih' => 0],
            'tb_u'  => ['normal' => 0, 'pendek' => 0, 'sangat_pendek' => 0, 'tinggi' => 0],
        ];

        $measurements = $this->latestMeasurements($f);

        $bbTbMap = [
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
            // Klasifikasi PERSIS rumus Dinkes, murni dari z-score tersimpan.
            $g = $this->statusGizi->enumEppgbm(
                $this->zval($m->zscore_bb_u),
                $this->zval($m->zscore_pb_u),
                $this->zval($m->zscore_bb_pb),
            );
            if ($g['bb_tb'] !== null) $results['bb_tb'][$bbTbMap[$g['bb_tb']]]++;
            if ($g['bb_u'] !== null)  $results['bb_u'][$bbMap[$g['bb_u']]]++;
            if ($g['tb_u'] !== null)  $results['tb_u'][$tbMap[$g['tb_u']]]++;
        }

        $total = count($measurements);
        $stunting = $results['tb_u']['sangat_pendek'] + $results['tb_u']['pendek'];
        $underweight = $results['bb_u']['kurang'] + $results['bb_u']['sangat_kurang'];
        // Wasting Dinkes = SELURUH BB/TB <= -2SD (moderat + buruk).
        $wasting = $results['bb_tb']['kurang'] + $results['bb_tb']['buruk'];

        return response()->json([
            'total'           => $total,
            'bb_tb'           => $results['bb_tb'],
            'bb_u'            => $results['bb_u'],
            'tb_u'            => $results['tb_u'],
            'stunting'        => $stunting,
            'stunting_pct'    => $total > 0 ? round($stunting / $total * 100, 1) : 0,
            'underweight'     => $underweight,
            'underweight_pct' => $total > 0 ? round($underweight / $total * 100, 1) : 0,
            'wasting'         => $wasting,
            'wasting_pct'     => $total > 0 ? round($wasting / $total * 100, 1) : 0,
            'gizi_kurang'     => $results['bb_tb']['kurang'],
            'gizi_buruk'      => $results['bb_tb']['buruk'],
        ]);
    }

    public function tren(Request $request): JsonResponse
    {
        $f = $this->parseFilters($request);

        $kunjunganQ = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->selectRaw("DATE_FORMAT(da.tgl_kunjungan, '%Y-%m') as bulan, COUNT(*) as total")
            ->whereNotNull('da.tgl_kunjungan')
            ->where('da.bln', '<=', 60); // hanya kunjungan balita
        if ($f['tahun']) {
            $kunjunganQ->whereYear('da.tgl_kunjungan', $f['tahun']);
        } else {
            $kunjunganQ->where('da.tgl_kunjungan', '>=', now()->subMonths(11)->startOfMonth());
        }
        $this->applyWilayah($kunjunganQ, $f);
        $kunjunganTren = $kunjunganQ->groupBy('bulan')->orderBy('bulan')->get();

        $growthQ = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->select('da.bln', DB::raw('ROUND(AVG(da.bb),2) as avg_bb'), DB::raw('ROUND(AVG(da.tb),2) as avg_tb'), DB::raw('COUNT(*) as n'))
            ->whereBetween('da.bln', [0, 60])
            ->where('da.bb', '>', 0)
            ->where('da.tb', '>', 0);
        if ($f['tahun']) $growthQ->whereYear('da.tgl_kunjungan', $f['tahun']);
        $this->applyWilayah($growthQ, $f);
        $growthTren = $growthQ->groupBy('da.bln')->orderBy('da.bln')->get();

        return response()->json([
            'kunjungan' => $kunjunganTren,
            'growth'    => $growthTren,
        ]);
    }

    public function coverage(Request $request): JsonResponse
    {
        $f = $this->parseFilters($request);

        $totalQ = DB::table('anak as a')
            ->join('kelurahan', 'a.id_kel', '=', 'kelurahan.id')
            ->selectRaw('kelurahan.id, kelurahan.name as nama, COUNT(DISTINCT a.id) as total');
        $this->applyBalita($totalQ, 'a'); // denominator = balita saja
        $this->applyWilayah($totalQ, $f);
        $totalPerKel = $totalQ->groupBy('kelurahan.id', 'kelurahan.name')->get()->keyBy('id');

        $timbangQ = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->join('kelurahan as k', 'a.id_kel', '=', 'k.id')
            ->where('da.bln', '<=', 60) // hanya kunjungan balita
            ->selectRaw('k.id, k.name as nama, COUNT(DISTINCT da.id_anak) as ditimbang');
        if ($f['tahun']) $timbangQ->whereYear('da.tgl_kunjungan', $f['tahun']);
        $this->applyWilayah($timbangQ, $f);
        $timbangPerKel = $timbangQ->groupBy('k.id', 'k.name')->get()->keyBy('id');

        $vitaQ = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->join('kelurahan as k', 'a.id_kel', '=', 'k.id')
            ->whereIn('da.id', $this->latestVisitQuery($f)->pluck('max_id'))
            ->selectRaw('k.id, k.name as nama, COUNT(*) as total, SUM(CASE WHEN da.vit_a=1 THEN 1 ELSE 0 END) as vit_a');
        $vitaPerKel = $vitaQ->groupBy('k.id', 'k.name')->get()->keyBy('id');

        $rows = [];
        foreach ($totalPerKel as $id => $kel) {
            $ditimbang = $timbangPerKel[$id]->ditimbang ?? 0;
            $vitaTot   = $vitaPerKel[$id]->total ?? 0;
            $vitaJml   = $vitaPerKel[$id]->vit_a ?? 0;
            $rows[]    = [
                'nama'         => $kel->nama,
                'total_anak'   => $kel->total,
                'ditimbang'    => $ditimbang,
                'coverage_pct' => $kel->total > 0 ? round($ditimbang / $kel->total * 100, 1) : 0,
                'vit_a_pct'    => $vitaTot > 0 ? round($vitaJml / $vitaTot * 100, 1) : 0,
            ];
        }

        usort($rows, fn($a, $b) => $b['coverage_pct'] <=> $a['coverage_pct']);

        return response()->json($rows);
    }

    public function program(Request $request): JsonResponse
    {
        $f = $this->parseFilters($request);

        // Basis = pengukuran kunjungan terakhir per anak. joinSub (bukan pluck+whereIn
        // dgn ribuan id) supaya query cepat — lihat catatan performa ASI di bawah.
        $base = DB::table('data_anak')
            ->joinSub($this->latestVisitQuery($f), 'lv', 'data_anak.id', '=', 'lv.max_id');

        $pittingEdema = (clone $base)
            ->selectRaw('COALESCE(pitting_edema, 0) as level, COUNT(*) as total')
            ->groupBy(DB::raw('COALESCE(pitting_edema, 0)'))
            ->orderBy(DB::raw('COALESCE(pitting_edema, 0)'))
            ->get();

        // ASI eksklusif bulan 0–6 dalam SATU query agregat. Sebelumnya 7 query
        // terpisah (tiap bulan) meng-scan ulang basis → ~15 dtk; kini ~0,3 dtk.
        $asiParts = [];
        for ($i = 0; $i <= 6; $i++) {
            $asiParts[] = "SUM(CASE WHEN asi_bulan_{$i}=1 THEN 1 ELSE 0 END) as ya_{$i}";
            $asiParts[] = "SUM(CASE WHEN asi_bulan_{$i} IS NOT NULL THEN 1 ELSE 0 END) as tot_{$i}";
        }
        $asiRow = (clone $base)->selectRaw(implode(', ', $asiParts))->first();
        $asiCols = [];
        for ($i = 0; $i <= 6; $i++) {
            $ya    = (int) ($asiRow->{"ya_{$i}"} ?? 0);
            $total = (int) ($asiRow->{"tot_{$i}"} ?? 0);
            $pct   = $total > 0 ? round($ya / $total * 100, 1) : null;
            $asiCols[] = ['bulan' => $i, 'pct' => $pct, 'ya' => $ya, 'total' => $total];
        }

        $caraUkur = (clone $base)
            ->selectRaw('LOWER(TRIM(posisi)) as cara, COUNT(*) as total')
            ->groupBy(DB::raw('LOWER(TRIM(posisi))'))
            ->get();

        $kelasData = (clone $base)->whereNotNull('kelas_ibu_balita')
            ->selectRaw('SUM(CASE WHEN kelas_ibu_balita=1 THEN 1 ELSE 0 END) as ya, COUNT(*) as total')->first();

        return response()->json([
            'pitting_edema' => $pittingEdema,
            'asi_per_bulan' => $asiCols,
            'cara_ukur'     => $caraUkur,
            'kelas_ibu'     => $kelasData,
        ]);
    }

    /**
     * Daftar nama anak yang dapat ditindak untuk satu kategori kartu.
     * kategori: sasaran|hadir|stunting|underweight|gizi_kurang|gizi_buruk|bb_tidak_naik
     */
    public function daftar(Request $request): JsonResponse
    {
        $f = $this->parseFilters($request);
        $kategori = $request->query('kategori', 'sasaran');

        return response()->json([
            'kategori' => $kategori,
            'rows'     => $this->daftarRows($f, $kategori),
        ]);
    }

    public function daftarExport(Request $request)
    {
        $f = $this->parseFilters($request);
        $kategori = $request->query('kategori', 'sasaran');
        $rows = $this->daftarRows($f, $kategori);

        $namaKategori = $this->labelKategori($kategori);
        $file = 'daftar-' . $kategori . '-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new TimbangDaftarExport($rows, $namaKategori), $file);
    }

    // ==================== HELPERS ====================

    private function parseFilters(Request $request): array
    {
        $user  = auth()->user();
        $tahun = $request->query('tahun') ? (int) $request->query('tahun') : null;

        if ($user && !$user->isSuperAdmin()) {
            abort_if(!$user->id_kel, 403);
            return [
                'tahun'    => $tahun,
                'kec'      => null,
                'kel'      => (int) $user->id_kel,
                'rt'       => $request->query('rt') ? (int) $request->query('rt') : null,
                'posyandu' => $request->query('posyandu') ? (int) $request->query('posyandu') : null,
            ];
        }

        return [
            'tahun'    => $tahun,
            'kec'      => $request->query('kecamatan') ? (int) $request->query('kecamatan') : null,
            'kel'      => $request->query('kelurahan') ? (int) $request->query('kelurahan') : null,
            'rt'       => $request->query('rt') ? (int) $request->query('rt') : null,
            'posyandu' => $request->query('posyandu') ? (int) $request->query('posyandu') : null,
        ];
    }

    /** Terapkan filter wilayah pada query yg punya join/alias tabel anak. */
    private function applyWilayah($q, array $f, string $a = 'a'): void
    {
        if ($f['kec'])      $q->where("$a.id_kec", $f['kec']);
        if ($f['kel'])      $q->where("$a.id_kel", $f['kel']);
        if ($f['rt'])       $q->where("$a.id_rt", $f['rt']);
        if ($f['posyandu']) $q->where("$a.id_posyandu", $f['posyandu']);
    }

    /**
     * Batasi ke balita ≤60 bln memakai umur SAAT INI dari tgl_lahir — untuk
     * hitungan yang bersumber tabel anak (sasaran/cakupan), yang tak punya
     * kolom umur-saat-ditimbang. Mengeluarkan anak SD (>60 bln).
     *
     * Anak tanpa tgl_lahir tetap disertakan: umurnya tak bisa dibuktikan >60
     * bln, jadi jangan sampai keliru membuang balita yg tgl lahirnya kosong.
     * Untuk kartu gizi & pengukuran, batas 60 bln memakai da.bln (umur saat
     * ditimbang) supaya tetap persis cocok ekspor Dinkes — lihat latestVisitQuery.
     */
    private function applyBalita($q, string $a = 'a'): void
    {
        $q->where(function ($w) use ($a) {
            $w->whereNull("$a.tgl_lahir")
              ->orWhereRaw("TIMESTAMPDIFF(MONTH, $a.tgl_lahir, CURDATE()) <= 60");
        });
    }

    private function baseQuery(array $f)
    {
        $q = DB::table('data_anak as da')->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->where('da.bln', '<=', 60); // hanya kunjungan balita; kunjungan anak SD dikecualikan
        if ($f['tahun']) $q->whereYear('da.tgl_kunjungan', $f['tahun']);
        $this->applyWilayah($q, $f);
        return $q;
    }

    private function totalAnakQuery(array $f): int
    {
        $q = Anak::query();
        $this->applyBalita($q, 'anak'); // sasaran balita saja (≤60 bln), tanpa anak SD
        if ($f['kec'])      $q->where('id_kec', $f['kec']);
        if ($f['kel'])      $q->where('id_kel', $f['kel']);
        if ($f['rt'])       $q->where('id_rt', $f['rt']);
        if ($f['posyandu']) $q->where('id_posyandu', $f['posyandu']);
        return $q->count();
    }

    /**
     * Builder id kunjungan TERAKHIR per anak (tgl_kunjungan terbesar, tie-break MAX(id)).
     */
    private function latestVisitQuery(array $f)
    {
        // Kunjungan TERAKHIR dihitung di antara kunjungan balita saja (da.bln<=60):
        // bila kelak anak yg dulu balita punya kunjungan usia SD, dashboard tetap
        // memakai kunjungan balita terakhirnya — kartu gizi tak diam-diam bergeser.
        $maxTgl = DB::table('data_anak as dm')
            ->join('anak as am', 'dm.id_anak', '=', 'am.id')
            ->selectRaw('dm.id_anak, MAX(dm.tgl_kunjungan) as max_tgl')
            ->whereNotNull('dm.tgl_kunjungan')
            ->where('dm.bln', '<=', 60);
        if ($f['tahun']) $maxTgl->whereYear('dm.tgl_kunjungan', $f['tahun']);
        $this->applyWilayah($maxTgl, $f, 'am');
        $maxTgl->groupBy('dm.id_anak');

        return DB::table('data_anak as da')
            ->joinSub($maxTgl, 'm', function ($join) {
                $join->on('m.id_anak', '=', 'da.id_anak')
                     ->on('m.max_tgl', '=', 'da.tgl_kunjungan');
            })
            ->where('da.bln', '<=', 60)
            ->selectRaw('MAX(da.id) as max_id')
            ->groupBy('da.id_anak');
    }

    /** Pengukuran kunjungan terakhir per anak (>0 bb/tb, <=60 bln). */
    private function latestMeasurements(array $f)
    {
        return DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->whereIn('da.id', $this->latestVisitQuery($f))
            ->where('da.bln', '<=', 60)
            ->where('da.bb', '>', 0)
            ->where('da.tb', '>', 0)
            ->select('da.id_anak', 'da.bb', 'da.tb', 'da.bln', 'da.posisi', 'da.zscore_bb_u', 'da.zscore_pb_u', 'da.zscore_bb_pb', 'a.jk')
            ->get();
    }

    /**
     * id_anak dgn BB tidak naik: BB kunjungan terakhir <= BB sebelumnya
     * (2 kunjungan terakhir). Anak 1 kunjungan → fallback ntob='T'.
     *
     * @return array<int,object> dikunci id_anak; tiap nilai punya bb_terakhir,
     *                            bb_sebelumnya, tgl_terakhir, alasan
     */
    private function bbTidakNaikIds(array $f): array
    {
        $q = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->whereNotNull('da.tgl_kunjungan')
            ->where('da.bb', '>', 0)
            ->where('da.bln', '<=', 60); // hanya kunjungan balita
        if ($f['tahun']) $q->whereYear('da.tgl_kunjungan', $f['tahun']);
        $this->applyWilayah($q, $f);

        $rows = $q->select('da.id_anak', 'da.tgl_kunjungan', 'da.bb', 'da.ntob', 'da.id')
            ->orderBy('da.id_anak')
            ->orderByDesc('da.tgl_kunjungan')
            ->orderByDesc('da.id')
            ->get()
            ->groupBy('id_anak');

        $flagged = [];
        foreach ($rows as $idAnak => $visits) {
            $visits = $visits->values();
            $last = $visits[0];
            if ($visits->count() >= 2) {
                $prev = $visits[1];
                if ((float) $last->bb <= (float) $prev->bb) {
                    $flagged[$idAnak] = (object) [
                        'bb_terakhir'   => (float) $last->bb,
                        'bb_sebelumnya' => (float) $prev->bb,
                        'tgl_terakhir'  => $last->tgl_kunjungan,
                        'alasan'        => 'BB terakhir ≤ kunjungan sebelumnya',
                    ];
                }
            } elseif (strtoupper(trim((string) $last->ntob)) === 'T') {
                $flagged[$idAnak] = (object) [
                    'bb_terakhir'   => (float) $last->bb,
                    'bb_sebelumnya' => null,
                    'tgl_terakhir'  => $last->tgl_kunjungan,
                    'alasan'        => 'Ditandai tidak naik (NTOB)',
                ];
            }
        }

        return $flagged;
    }

    /** Bangun baris daftar nama+alamat untuk satu kategori. */
    private function daftarRows(array $f, string $kategori): array
    {
        // BB tidak naik punya jalur sendiri (butuh perbandingan 2 kunjungan).
        if ($kategori === 'bb_tidak_naik') {
            $flagged = $this->bbTidakNaikIds($f);
            if (empty($flagged)) return [];
            $info = $this->anakInfo(array_keys($flagged));
            $rows = [];
            foreach ($flagged as $idAnak => $bb) {
                $a = $info[$idAnak] ?? null;
                if (!$a) continue;
                $rows[] = $this->baseRow($a) + [
                    'indikator' => $bb->bb_sebelumnya !== null
                        ? "BB {$bb->bb_sebelumnya} → {$bb->bb_terakhir} kg"
                        : $bb->alasan,
                    'tgl_kunjungan' => $bb->tgl_terakhir,
                ];
            }
            return $rows;
        }

        // Sasaran = semua anak terfilter (tanpa syarat kunjungan).
        if ($kategori === 'sasaran') {
            $ids = (function () use ($f) {
                $q = Anak::query();
                $this->applyBalita($q, 'anak'); // sasaran balita saja (≤60 bln)
                if ($f['kec'])      $q->where('id_kec', $f['kec']);
                if ($f['kel'])      $q->where('id_kel', $f['kel']);
                if ($f['rt'])       $q->where('id_rt', $f['rt']);
                if ($f['posyandu']) $q->where('id_posyandu', $f['posyandu']);
                return $q->pluck('id')->all();
            })();
            $info = $this->anakInfo($ids);
            return array_map(fn($a) => $this->baseRow($a) + ['indikator' => null, 'tgl_kunjungan' => null], array_values($info));
        }

        // Sisanya berbasis kunjungan terakhir per anak.
        $measurements = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->whereIn('da.id', $this->latestVisitQuery($f))
            ->select('da.id_anak', 'da.bb', 'da.tb', 'da.bln', 'da.posisi', 'da.tgl_kunjungan', 'da.zscore_bb_u', 'da.zscore_pb_u', 'da.zscore_bb_pb', 'a.jk')
            ->get();

        $matchIds = [];
        $detail = [];
        foreach ($measurements as $m) {
            if ($kategori === 'hadir') {
                $matchIds[] = $m->id_anak;
                $detail[$m->id_anak] = ['indikator' => 'Kunjungan terakhir', 'tgl' => $m->tgl_kunjungan];
                continue;
            }
            if ($m->bln > 60 || $m->bb <= 0 || $m->tb <= 0) continue;
            // Klasifikasi PERSIS rumus Dinkes, murni dari z-score tersimpan.
            $g = $this->statusGizi->enumEppgbm(
                $this->zval($m->zscore_bb_u),
                $this->zval($m->zscore_pb_u),
                $this->zval($m->zscore_bb_pb),
            );
            $hit = match ($kategori) {
                'stunting'    => in_array($g['tb_u'], ['severely_stunted', 'stunted'], true),
                'underweight' => in_array($g['bb_u'], ['severely_underweight', 'underweight'], true),
                'wasting'     => in_array($g['bb_tb'], ['wasted', 'severely_wasted'], true),
                'gizi_kurang' => $g['bb_tb'] === 'wasted',
                'gizi_buruk'  => $g['bb_tb'] === 'severely_wasted',
                default       => false,
            };
            if ($hit) {
                $matchIds[] = $m->id_anak;
                $label = match ($kategori) {
                    'stunting'    => StatusGiziService::labelTb($g['tb_u']),
                    'underweight' => StatusGiziService::labelBb($g['bb_u']),
                    default       => StatusGiziService::labelBbTb($g['bb_tb']),
                };
                $detail[$m->id_anak] = ['indikator' => $label, 'tgl' => $m->tgl_kunjungan];
            }
        }

        if (empty($matchIds)) return [];
        $info = $this->anakInfo($matchIds);
        $rows = [];
        foreach ($matchIds as $idAnak) {
            $a = $info[$idAnak] ?? null;
            if (!$a) continue;
            $rows[] = $this->baseRow($a) + [
                'indikator'     => $detail[$idAnak]['indikator'] ?? null,
                'tgl_kunjungan' => $detail[$idAnak]['tgl'] ?? null,
            ];
        }
        return $rows;
    }

    /** Info dasar anak + wilayah dikunci id_anak. */
    private function anakInfo(array $ids): array
    {
        if (empty($ids)) return [];
        return DB::table('anak as a')
            ->leftJoin('kecamatan as kec', 'a.id_kec', '=', 'kec.id')
            ->leftJoin('kelurahan as kel', 'a.id_kel', '=', 'kel.id')
            ->leftJoin('rt', 'a.id_rt', '=', 'rt.id')
            ->leftJoin('posyandu as pos', 'a.id_posyandu', '=', 'pos.id')
            ->whereIn('a.id', $ids)
            ->select(
                'a.id', 'a.nama', 'a.nik', 'a.alamat',
                'kec.name as kecamatan', 'kel.name as kelurahan',
                'rt.name as rt', 'pos.name as posyandu'
            )
            ->get()
            ->keyBy('id')
            ->all();
    }

    private function baseRow(object $a): array
    {
        return [
            'nama'      => $a->nama,
            'nik'       => $a->nik,
            'alamat'    => $a->alamat ?: '-',
            'kecamatan' => $a->kecamatan ?: '-',
            'kelurahan' => $a->kelurahan ?: '-',
            'rt'        => $a->rt ?: '-',
            'posyandu'  => $a->posyandu ?: '-',
        ];
    }

    private function labelKategori(string $kategori): string
    {
        return [
            'sasaran'       => 'Balita Sasaran',
            'hadir'         => 'Hadir (Ditimbang)',
            'stunting'      => 'Stunting',
            'underweight'   => 'Underweight',
            'wasting'       => 'Wasting',
            'gizi_kurang'   => 'Gizi Kurang',
            'gizi_buruk'    => 'Gizi Buruk',
            'bb_tidak_naik' => 'BB Tidak Naik',
        ][$kategori] ?? ucfirst($kategori);
    }

    /** Nilai z-score DB → ?float (kolom bisa null / string desimal). */
    private function zval($v): ?float
    {
        return ($v === null || $v === '') ? null : (float) $v;
    }
}
