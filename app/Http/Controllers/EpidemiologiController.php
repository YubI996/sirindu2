<?php

namespace App\Http\Controllers;

use App\Http\Requests\Epidemiologi\StoreSurveillanceCaseRequest;
use App\Http\Requests\Epidemiologi\UpdateSurveillanceCaseRequest;
use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use App\Repositories\Admin\Epidemiologi\SurveillanceRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Yajra\DataTables\Facades\DataTables;

class EpidemiologiController extends Controller
{
    protected $repo;

    public function __construct(SurveillanceRepository $repo)
    {
        $this->middleware(['auth', 'is_admin']);
        $this->repo = $repo;
    }

    public function dashboard()
    {
        $stats = Cache::remember('epi_dashboard_stats', 300, fn() => $this->repo->getDashboardStats());
        $recentCases = SurveillanceCase::with('jenisKasus', 'kelurahan')
            ->orderByDesc('tanggal_lapor')
            ->limit(10)
            ->get();
        $byDisease   = $this->repo->getCasesByDisease();
        $byStatus    = $this->repo->getCasesByStatus();
        $trend       = $this->repo->getCasesTrend(12);
        $byKecamatan = $this->repo->getCasesByGeography('kecamatan');

        return view('admin.epidemiologi.dashboard', compact(
            'stats', 'recentCases', 'byDisease', 'byStatus', 'trend', 'byKecamatan'
        ));
    }

    public function mapDashboard()
    {
        $diseases    = JenisKasusEpidemiologi::active()->orderBy('nama_penyakit')->get();
        $kecamatanList = Kecamatan::orderBy('nama_kecamatan')->get();
        return view('admin.epidemiologi.map', compact('diseases', 'kecamatanList'));
    }

    public function getMapData(Request $request)
    {
        $query = SurveillanceCase::with('kelurahan', 'jenisKasus')
            ->select('id', 'id_kel', 'id_jenis_kasus', 'status_kasus', 'kondisi_akhir', 'tanggal_onset', 'nama_lengkap');

        if ($request->filled('id_jenis_kasus')) {
            $query->where('id_jenis_kasus', $request->id_jenis_kasus);
        }
        if ($request->filled('status_kasus')) {
            $query->where('status_kasus', $request->status_kasus);
        }
        if ($request->filled('start_date')) {
            $query->where('tanggal_onset', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('tanggal_onset', '<=', $request->end_date);
        }

        $cases = $query->get();

        // Group by kelurahan
        $grouped = $cases->groupBy('id_kel')->map(function ($group) {
            $kelurahan = $group->first()->kelurahan;
            return [
                'id_kel'       => $group->first()->id_kel,
                'nama_kel'     => $kelurahan ? $kelurahan->nama_kelurahan : 'Unknown',
                'total'        => $group->count(),
                'by_disease'   => $group->groupBy('id_jenis_kasus')->map(fn($g) => [
                    'nama'  => $g->first()->jenisKasus->nama_penyakit ?? '-',
                    'total' => $g->count(),
                ])->values(),
                'recent'       => $group->sortByDesc('tanggal_onset')->take(3)->values()->map(fn($c) => [
                    'nama'          => $c->nama_lengkap,
                    'tanggal_onset' => $c->tanggal_onset?->format('d/m/Y'),
                    'status'        => $c->status_kasus,
                ]),
            ];
        })->values();

        return response()->json($grouped);
    }

    public function index()
    {
        $diseases      = JenisKasusEpidemiologi::active()->orderBy('nama_penyakit')->get();
        $kecamatanList = Kecamatan::orderBy('nama_kecamatan')->get();
        return view('admin.epidemiologi.index', compact('diseases', 'kecamatanList'));
    }

    public function getSurveillanceCases(Request $request)
    {
        $query = SurveillanceCase::with('jenisKasus', 'kelurahan')->select('surveillance_cases.*');

        if ($request->filled('id_jenis_kasus')) {
            $query->where('id_jenis_kasus', $request->id_jenis_kasus);
        }
        if ($request->filled('status_kasus')) {
            $query->where('status_kasus', $request->status_kasus);
        }
        if ($request->filled('id_kec')) {
            $query->where('id_kec', $request->id_kec);
        }
        if ($request->filled('start_date')) {
            $query->where('tanggal_onset', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('tanggal_onset', '<=', $request->end_date);
        }

        return DataTables::of($query)
            ->addColumn('jenis_kasus_nama', fn($row) => $row->jenisKasus->nama_penyakit ?? '-')
            ->addColumn('wilayah', fn($row) => $row->kelurahan->nama_kelurahan ?? '-')
            ->addColumn('status_kasus_badge', function ($row) {
                $colors = [
                    'suspek'     => 'warning',
                    'probable'   => 'info',
                    'konfirmasi' => 'danger',
                    'discarded'  => 'secondary',
                ];
                $color = $colors[$row->status_kasus] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst($row->status_kasus) . '</span>';
            })
            ->addColumn('kondisi_badge', function ($row) {
                $colors = [
                    'sembuh'           => 'success',
                    'dalam_perawatan'  => 'warning',
                    'meninggal'        => 'danger',
                    'tidak_diketahui'  => 'secondary',
                ];
                $color = $colors[$row->kondisi_akhir] ?? 'secondary';
                $label = str_replace('_', ' ', ucfirst($row->kondisi_akhir));
                return '<span class="badge badge-' . $color . '">' . $label . '</span>';
            })
            ->addColumn('action', function ($row) {
                return '
                    <a href="' . route('admin.epidemiologi.show', $row->id) . '" class="btn btn-sm btn-info" title="Detail">
                        <i class="fa fa-eye"></i>
                    </a>
                    <a href="' . route('admin.epidemiologi.edit', $row->id) . '" class="btn btn-sm btn-warning" title="Edit">
                        <i class="fa fa-edit"></i>
                    </a>
                    <button class="btn btn-sm btn-danger btn-delete" data-id="' . $row->id . '" title="Hapus">
                        <i class="fa fa-trash"></i>
                    </button>';
            })
            ->rawColumns(['status_kasus_badge', 'kondisi_badge', 'action'])
            ->make(true);
    }

    public function create()
    {
        $diseases      = JenisKasusEpidemiologi::active()->orderBy('nama_penyakit')->get();
        $kecamatanList = Kecamatan::orderBy('nama_kecamatan')->get();

        // Generate suggested registration number
        $year  = now()->format('Y');
        $count = SurveillanceCase::whereYear('created_at', $year)->count() + 1;
        $suggestedRegNumber = 'EPI-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        return view('admin.epidemiologi.create', compact('diseases', 'kecamatanList', 'suggestedRegNumber'));
    }

    public function store(StoreSurveillanceCaseRequest $request)
    {
        $case = $this->repo->storeCase($request);
        Cache::forget('epi_dashboard_stats');

        return redirect()
            ->route('admin.epidemiologi.show', $case->id)
            ->with('success', 'Kasus epidemiologi berhasil disimpan.');
    }

    public function show($id)
    {
        $case = SurveillanceCase::with([
            'jenisKasus', 'kecamatan', 'kelurahan', 'rt', 'createdBy', 'updatedBy'
        ])->findOrFail($id);

        return view('admin.epidemiologi.show', compact('case'));
    }

    public function edit($id)
    {
        $case          = SurveillanceCase::findOrFail($id);
        $diseases      = JenisKasusEpidemiologi::active()->orderBy('nama_penyakit')->get();
        $kecamatanList = Kecamatan::orderBy('nama_kecamatan')->get();
        $kelurahanList = $case->id_kec ? Kelurahan::where('id_kec', $case->id_kec)->orderBy('nama_kelurahan')->get() : collect();
        $rtList        = $case->id_kel ? Rt::where('id_kel', $case->id_kel)->orderBy('no_rt')->get() : collect();

        return view('admin.epidemiologi.edit', compact('case', 'diseases', 'kecamatanList', 'kelurahanList', 'rtList'));
    }

    public function update(UpdateSurveillanceCaseRequest $request, $id)
    {
        $this->repo->updateCase($request, $id);
        Cache::forget('epi_dashboard_stats');

        return redirect()
            ->route('admin.epidemiologi.show', $id)
            ->with('success', 'Kasus epidemiologi berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $this->repo->deleteCase($id);
        Cache::forget('epi_dashboard_stats');
        return response()->json(['success' => true, 'message' => 'Kasus berhasil dihapus.']);
    }

    // AJAX: Cascading selects
    public function getKelurahan($id_kec)
    {
        $data = Kelurahan::where('id_kec', $id_kec)->orderBy('nama_kelurahan')->get(['id', 'nama_kelurahan']);
        return response()->json($data);
    }

    public function getRt($id_kel)
    {
        $data = Rt::where('id_kel', $id_kel)->orderBy('no_rt')->get(['id', 'no_rt']);
        return response()->json($data);
    }

    public function checkNik(Request $request)
    {
        $exists = SurveillanceCase::where('nik', $request->nik)->exists();
        return response()->json(['exists' => $exists]);
    }

    public function exportExcel(Request $request)
    {
        $query = SurveillanceCase::with('jenisKasus', 'kelurahan', 'kecamatan');

        if ($request->filled('id_jenis_kasus')) {
            $query->where('id_jenis_kasus', $request->id_jenis_kasus);
        }
        if ($request->filled('status_kasus')) {
            $query->where('status_kasus', $request->status_kasus);
        }

        $cases = $query->orderByDesc('tanggal_lapor')->get();

        $filename = 'epidemiologi-' . now()->format('Ymd') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($cases) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'No Registrasi', 'Nama', 'L/P', 'Tgl Lahir', 'Alamat',
                'Jenis Kasus', 'Tgl Onset', 'Tgl Lapor',
                'Status Kasus', 'Kondisi Akhir', 'Status Lab',
            ]);
            foreach ($cases as $c) {
                fputcsv($file, [
                    $c->no_registrasi,
                    $c->nama_lengkap,
                    $c->jenis_kelamin,
                    $c->tanggal_lahir?->format('d/m/Y'),
                    $c->alamat_lengkap,
                    $c->jenisKasus->nama_penyakit ?? '-',
                    $c->tanggal_onset?->format('d/m/Y'),
                    $c->tanggal_lapor?->format('d/m/Y'),
                    $c->status_kasus,
                    $c->kondisi_akhir,
                    $c->status_lab,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
