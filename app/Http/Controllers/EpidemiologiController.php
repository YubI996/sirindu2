<?php

namespace App\Http\Controllers;

use App\Repositories\Admin\Epidemiologi\SurveillanceRepository;
use App\Http\Requests\Epidemiologi\StoreSurveillanceCaseRequest;
use App\Http\Requests\Epidemiologi\UpdateSurveillanceCaseRequest;
use App\Models\SurveillanceCase;
use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class EpidemiologiController extends Controller
{
    protected $surveillanceRepository;

    public function __construct(SurveillanceRepository $surveillanceRepository)
    {
        $this->middleware('auth');
        $this->middleware('is_admin');
        $this->surveillanceRepository = $surveillanceRepository;
    }

    // ==================== DASHBOARD & ANALYTICS ====================

    /**
     * Show analytics dashboard
     */
    public function dashboard()
    {
        // Cache dashboard stats for 5 minutes
        $stats = Cache::remember('epi_dashboard_stats', 300, function () {
            return $this->surveillanceRepository->getDashboardStats();
        });

        // Get recent cases (last 10)
        $recentCases = SurveillanceCase::with(['jenisKasus', 'kecamatan', 'kelurahan'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get cases trend data for charts
        $trendData = $this->surveillanceRepository->getCasesTrend(12);
        $diseaseData = $this->surveillanceRepository->getCasesByDisease();
        $statusData = $this->surveillanceRepository->getCasesByStatus();
        $geoData = $this->surveillanceRepository->getCasesByGeography('kecamatan');

        return view('admin.epidemiologi.dashboard', compact(
            'stats',
            'recentCases',
            'trendData',
            'diseaseData',
            'statusData',
            'geoData'
        ));
    }

    /**
     * Show map dashboard
     */
    public function mapDashboard()
    {
        $diseases = JenisKasusEpidemiologi::active()->get();
        $kecamatanList = Kecamatan::all();

        return view('admin.epidemiologi.map', compact('diseases', 'kecamatanList'));
    }

    /**
     * Get map data (AJAX endpoint)
     */
    public function getMapData(Request $request)
    {
        $query = SurveillanceCase::with(['jenisKasus', 'kecamatan', 'kelurahan', 'rt']);

        // Apply filters
        if ($request->has('disease_id') && $request->disease_id != '') {
            $query->where('id_jenis_kasus', $request->disease_id);
        }

        if ($request->has('status') && $request->status != '') {
            $query->where('status_kasus', $request->status);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('tanggal_onset', [$request->start_date, $request->end_date]);
        }

        $cases = $query->get();

        // Group by kelurahan for map coloring
        $casesByKelurahan = $cases->groupBy('id_kel')->map(function ($group) {
            return [
                'name' => $group->first()->kelurahan->name ?? 'Unknown',
                'count' => $group->count(),
                'cases' => $group->map(function ($case) {
                    return [
                        'id' => $case->id,
                        'no_registrasi' => $case->no_registrasi,
                        'nama' => $case->nama_lengkap,
                        'disease' => $case->jenisKasus->nama_penyakit ?? 'Unknown',
                        'status' => $case->status_kasus,
                        'tanggal_onset' => $case->tanggal_onset->format('d/m/Y'),
                    ];
                })->toArray()
            ];
        });

        return response()->json([
            'casesByKelurahan' => $casesByKelurahan,
            'totalCases' => $cases->count(),
        ]);
    }

    // ==================== CRUD OPERATIONS ====================

    /**
     * Display list of surveillance cases
     */
    public function index()
    {
        $diseases = JenisKasusEpidemiologi::active()->get();
        $kecamatanList = Kecamatan::all();

        return view('admin.epidemiologi.index', compact('diseases', 'kecamatanList'));
    }

    /**
     * Get surveillance cases for DataTables (AJAX)
     */
    public function getSurveillanceCases(Request $request)
    {
        $query = SurveillanceCase::with(['jenisKasus', 'kecamatan', 'kelurahan', 'rt']);

        return DataTables::of($query)
            ->filter(function ($query) use ($request) {
                // Search by name or NIK
                if ($request->has('search') && $request->search['value'] != '') {
                    $searchValue = $request->search['value'];
                    $query->where(function ($q) use ($searchValue) {
                        $q->where('nama_lengkap', 'like', "%{$searchValue}%")
                          ->orWhere('nik', 'like', "%{$searchValue}%")
                          ->orWhere('no_registrasi', 'like', "%{$searchValue}%");
                    });
                }

                // Filter by disease
                if ($request->has('disease_filter') && $request->disease_filter != '') {
                    $query->where('id_jenis_kasus', $request->disease_filter);
                }

                // Filter by status
                if ($request->has('status_filter') && $request->status_filter != '') {
                    $query->where('status_kasus', $request->status_filter);
                }

                // Filter by kecamatan
                if ($request->has('kecamatan_filter') && $request->kecamatan_filter != '') {
                    $query->where('id_kec', $request->kecamatan_filter);
                }

                // Filter by date range
                if ($request->has('start_date') && $request->has('end_date')) {
                    $query->whereBetween('tanggal_onset', [$request->start_date, $request->end_date]);
                }
            })
            ->addColumn('disease', function ($case) {
                return $case->jenisKasus->nama_penyakit ?? '-';
            })
            ->addColumn('location', function ($case) {
                $kec = $case->kecamatan->name ?? '-';
                $kel = $case->kelurahan->name ?? '-';
                return "{$kec} / {$kel}";
            })
            ->addColumn('status_badge', function ($case) {
                $badges = [
                    'suspected' => 'warning',
                    'probable' => 'info',
                    'confirmed' => 'danger',
                    'discarded' => 'secondary',
                ];
                $badge = $badges[$case->status_kasus] ?? 'secondary';
                $label = ucfirst($case->status_kasus);
                return "<span class='badge bg-{$badge}'>{$label}</span>";
            })
            ->addColumn('outcome_badge', function ($case) {
                $badges = [
                    'sembuh' => 'success',
                    'meninggal' => 'danger',
                    'dalam_perawatan' => 'warning',
                    'pindah' => 'info',
                    'unknown' => 'secondary',
                ];
                $badge = $badges[$case->kondisi_akhir] ?? 'secondary';
                $labels = [
                    'sembuh' => 'Sembuh',
                    'meninggal' => 'Meninggal',
                    'dalam_perawatan' => 'Dalam Perawatan',
                    'pindah' => 'Pindah',
                    'unknown' => 'Unknown',
                ];
                $label = $labels[$case->kondisi_akhir] ?? 'Unknown';
                return "<span class='badge bg-{$badge}'>{$label}</span>";
            })
            ->addColumn('action', function ($case) {
                $showUrl = route('admin.epidemiologi.show', $case->id);
                $editUrl = route('admin.epidemiologi.edit', $case->id);
                $deleteUrl = route('admin.epidemiologi.destroy', $case->id);

                return "
                    <div class='btn-group' role='group'>
                        <a href='{$showUrl}' class='btn btn-sm btn-info' title='Detail'>
                            <i class='fa fa-eye'></i>
                        </a>
                        <a href='{$editUrl}' class='btn btn-sm btn-warning' title='Edit'>
                            <i class='fa fa-edit'></i>
                        </a>
                        <button type='button' class='btn btn-sm btn-danger' onclick='deleteCase({$case->id})' title='Hapus'>
                            <i class='fa fa-trash'></i>
                        </button>
                    </div>
                ";
            })
            ->rawColumns(['status_badge', 'outcome_badge', 'action'])
            ->make(true);
    }

    /**
     * Show form to create new surveillance case
     */
    public function create()
    {
        $diseases = JenisKasusEpidemiologi::active()->orderBy('nama_penyakit')->get();
        $kecamatanList = Kecamatan::all();

        // Generate registration number
        $lastCase = SurveillanceCase::latest('id')->first();
        $nextNumber = $lastCase ? ($lastCase->id + 1) : 1;
        $suggestedRegNumber = 'EPI-' . date('Y') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        return view('admin.epidemiologi.create', compact('diseases', 'kecamatanList', 'suggestedRegNumber'));
    }

    /**
     * Store a new surveillance case
     */
    public function store(StoreSurveillanceCaseRequest $request)
    {
        try {
            $case = $this->surveillanceRepository->storeCase($request);

            // Clear dashboard cache
            Cache::forget('epi_dashboard_stats');

            Alert::success('Berhasil', 'Kasus surveillance berhasil ditambahkan');
            return redirect()->route('admin.epidemiologi.index');
        } catch (\Exception $e) {
            Alert::error('Gagal', 'Terjadi kesalahan: ' . $e->getMessage());
            return back()->withInput();
        }
    }

    /**
     * Show details of a surveillance case
     */
    public function show($id)
    {
        $case = SurveillanceCase::with([
            'jenisKasus',
            'kecamatan',
            'kelurahan',
            'rt',
            'petugasInput',
            'creator',
            'updater'
        ])->findOrFail($id);

        return view('admin.epidemiologi.show', compact('case'));
    }

    /**
     * Show form to edit surveillance case
     */
    public function edit($id)
    {
        $case = SurveillanceCase::findOrFail($id);
        $diseases = JenisKasusEpidemiologi::active()->orderBy('nama_penyakit')->get();
        $kecamatanList = Kecamatan::all();

        // Get kelurahan and RT for selected kecamatan
        $kelurahanList = Kelurahan::where('id_kecamatan', $case->id_kec)->get();
        $rtList = Rt::where('id_kelurahan', $case->id_kel)->get();

        return view('admin.epidemiologi.edit', compact('case', 'diseases', 'kecamatanList', 'kelurahanList', 'rtList'));
    }

    /**
     * Update a surveillance case
     */
    public function update(UpdateSurveillanceCaseRequest $request, $id)
    {
        try {
            $case = $this->surveillanceRepository->updateCase($request, $id);

            // Clear dashboard cache
            Cache::forget('epi_dashboard_stats');

            Alert::success('Berhasil', 'Kasus surveillance berhasil diperbarui');
            return redirect()->route('admin.epidemiologi.index');
        } catch (\Exception $e) {
            Alert::error('Gagal', 'Terjadi kesalahan: ' . $e->getMessage());
            return back()->withInput();
        }
    }

    /**
     * Delete a surveillance case
     */
    public function destroy($id)
    {
        try {
            $this->surveillanceRepository->deleteCase($id);

            // Clear dashboard cache
            Cache::forget('epi_dashboard_stats');

            return response()->json([
                'success' => true,
                'message' => 'Kasus berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus kasus: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== AJAX HELPER METHODS ====================

    /**
     * Get kelurahan by kecamatan (AJAX)
     */
    public function getKelurahan($id_kec)
    {
        $kelurahan = Kelurahan::where('id_kecamatan', $id_kec)
            ->orderBy('name')
            ->pluck('name', 'id');

        return response()->json($kelurahan);
    }

    /**
     * Get RT by kelurahan (AJAX)
     */
    public function getRt($id_kel)
    {
        $rt = Rt::where('id_kelurahan', $id_kel)
            ->orderBy('name')
            ->pluck('name', 'id');

        return response()->json($rt);
    }

    /**
     * Check if NIK is already registered (AJAX)
     */
    public function checkNik($nik)
    {
        $exists = SurveillanceCase::where('nik', $nik)->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'NIK sudah terdaftar' : 'NIK tersedia'
        ]);
    }

    // ==================== EXPORT METHODS ====================

    /**
     * Export cases to Excel
     */
    public function exportExcel(Request $request)
    {
        try {
            // This will be implemented with Maatwebsite/Excel later
            // For now, return a simple CSV export

            $query = SurveillanceCase::with(['jenisKasus', 'kecamatan', 'kelurahan']);

            // Apply filters from request
            if ($request->has('disease_id') && $request->disease_id != '') {
                $query->where('id_jenis_kasus', $request->disease_id);
            }

            if ($request->has('status') && $request->status != '') {
                $query->where('status_kasus', $request->status);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('tanggal_onset', [$request->start_date, $request->end_date]);
            }

            $cases = $query->get();

            $filename = 'surveillance_cases_' . date('YmdHis') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function () use ($cases) {
                $file = fopen('php://output', 'w');

                // CSV Headers
                fputcsv($file, [
                    'No Registrasi',
                    'NIK',
                    'Nama Lengkap',
                    'Tanggal Lahir',
                    'Jenis Kelamin',
                    'Alamat',
                    'Kecamatan',
                    'Kelurahan',
                    'Jenis Kasus',
                    'Tanggal Onset',
                    'Tanggal Lapor',
                    'Status Kasus',
                    'Status Rawat',
                    'Kondisi Akhir',
                ]);

                // CSV Data
                foreach ($cases as $case) {
                    fputcsv($file, [
                        $case->no_registrasi,
                        $case->nik,
                        $case->nama_lengkap,
                        $case->tanggal_lahir->format('d/m/Y'),
                        $case->jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan',
                        $case->alamat_lengkap,
                        $case->kecamatan->name ?? '-',
                        $case->kelurahan->name ?? '-',
                        $case->jenisKasus->nama_penyakit ?? '-',
                        $case->tanggal_onset->format('d/m/Y'),
                        $case->tanggal_lapor->format('d/m/Y'),
                        $case->status_kasus,
                        $case->status_rawat,
                        $case->kondisi_akhir,
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Alert::error('Gagal', 'Gagal export data: ' . $e->getMessage());
            return back();
        }
    }

    /**
     * Export single case to PDF
     */
    public function exportPdf($id)
    {
        $case = SurveillanceCase::with([
            'jenisKasus',
            'kecamatan',
            'kelurahan',
            'rt',
            'petugasInput'
        ])->findOrFail($id);

        // For now, return a print-friendly view
        // Later can be enhanced with PDF library like DomPDF or wkhtmltopdf

        return view('admin.epidemiologi.print', compact('case'));
    }
}
