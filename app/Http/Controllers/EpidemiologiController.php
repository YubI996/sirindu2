<?php

namespace App\Http\Controllers;

use App\Repositories\Admin\Epidemiologi\SurveillanceRepository;
use App\Http\Requests\Epidemiologi\StoreSurveillanceCaseRequest;
use App\Http\Requests\Epidemiologi\UpdateSurveillanceCaseRequest;
use App\Models\SurveillanceCase;
use App\Models\JenisKasusEpidemiologi;
use App\Models\LokasiPenularanMaster;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\Puskesmas;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Imports\Pd3iImport;
use App\Jobs\ImportPd3iJob;
use App\Models\ImportLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class EpidemiologiController extends Controller
{
    /**
     * Pemetaan nama kelurahan (uppercase) ke nama Wilker Puskesmas.
     * Sinkron dengan WILKER_MAP di form-section-a.blade.php.
     */
    protected const WILKER_MAP = [
        'API-API'            => 'Bontang Utara 1',
        'BONTANG BARU'       => 'Bontang Utara 1',
        'GUNUNG ELAI'        => 'Bontang Utara 1',
        'BONTANG KUALA'      => 'Bontang Utara 1',
        'GUNTUNG'            => 'Bontang Utara 2',
        'LOK TUAN'           => 'Bontang Utara 2',
        'BELIMBING'          => 'Bontang Barat',
        'KANAAN'             => 'Bontang Barat',
        'GUNUNG TELIHAN'     => 'Bontang Barat',
        'BONTANG LESTARI'    => 'Bontang Lestari',
        'TANJUNG LAUT'       => 'Bontang Selatan 1',
        'TANJUNG LAUT INDAH' => 'Bontang Selatan 1',
        'SATIMPO'            => 'Bontang Selatan 1',
        'BERBAS PANTAI'      => 'Bontang Selatan 2',
        'BEREBAS TENGAH'     => 'Bontang Selatan 2',
    ];

    protected $surveillanceRepository;

    public function __construct(SurveillanceRepository $surveillanceRepository)
    {
        $this->middleware('auth');
        // Semua user surveilans (superadmin + faskes puskesmas + faskes RS) bisa akses
        $this->middleware('module.role:superadmin,surveilans_puskesmas,surveilans_rs');
        $this->surveillanceRepository = $surveillanceRepository;
    }

    /**
     * Resolve wilker puskesmas dari id_kel berdasarkan pemetaan statis WILKER_MAP.
     * Mengembalikan nama wilker atau string kosong jika tidak ditemukan.
     */
    protected function resolveWilker(int $idKel): string
    {
        $kelurahan = \App\Models\Kelurahan::find($idKel);
        if (! $kelurahan) {
            return '';
        }
        $namaUpper = strtoupper(trim($kelurahan->name));
        return static::WILKER_MAP[$namaUpper] ?? '';
    }

    // ==================== DASHBOARD & ANALYTICS ====================

    /**
     * Show analytics dashboard
     */
    public function dashboard()
    {
        $user = auth()->user();
        $diseases = JenisKasusEpidemiologi::active()->get();

        $faskesScope = $user->isFaskesSurveilans()
            ? ['faskes_type' => $user->faskes_type, 'id_faskes' => $user->getFaskesId()]
            : null;

        $dashboardData = $this->buildDashboardData($user, $faskesScope);

        return view('admin.epidemiologi.dashboard', array_merge(
            $dashboardData,
            ['diseases' => $diseases]
        ));
    }

    /**
     * AJAX endpoint for filtered dashboard data
     */
    public function getDashboardData(Request $request)
    {
        $user = auth()->user();

        $faskesScope = $user->isFaskesSurveilans()
            ? ['faskes_type' => $user->faskes_type, 'id_faskes' => $user->getFaskesId()]
            : null;

        $diseaseId = $request->filled('disease_id') ? (int) $request->disease_id : null;

        $data = $this->buildDashboardData($user, $faskesScope, $diseaseId);

        // Convert recent cases to array for JSON
        $data['recentCases'] = $data['recentCases']->map(function ($case) {
            return [
                'id' => $case->id,
                'no_registrasi' => $case->no_registrasi,
                'nama_lengkap' => $case->nama_lengkap,
                'penyakit' => $case->jenisKasus->nama_penyakit ?? '-',
                'kecamatan' => $case->kecamatan->name ?? '-',
                'kelurahan' => $case->kelurahan->name ?? '-',
                'tanggal_onset' => $case->tanggal_onset->format('d/m/Y'),
                'tanggal_onset_iso' => $case->tanggal_onset->format('Y-m-d'),
                'status_kasus' => $case->status_kasus,
                'show_url' => route('admin.epidemiologi.show', $case->id),
            ];
        });

        return response()->json($data);
    }

    /**
     * Build dashboard data arrays (shared between initial load and AJAX)
     */
    private function buildDashboardData($user, ?array $faskesScope, ?int $diseaseId = null)
    {
        $faskesType = $faskesScope['faskes_type'] ?? null;
        $faskesId = $faskesScope['id_faskes'] ?? null;

        $stats = $this->surveillanceRepository->getDashboardStats($faskesType, $faskesId ? (int) $faskesId : null, $diseaseId);

        $recentQuery = SurveillanceCase::with(['jenisKasus', 'kecamatan', 'kelurahan']);
        if ($faskesScope) {
            $recentQuery->where('faskes_type', $faskesScope['faskes_type'])
                        ->where('id_faskes', $faskesScope['id_faskes']);
        }
        if ($diseaseId) {
            $recentQuery->where('id_jenis_kasus', $diseaseId);
        }
        $recentCases = $recentQuery->orderBy('created_at', 'desc')->limit(10)->get();

        $trendData    = $this->surveillanceRepository->getCasesTrend(12, $faskesScope, $diseaseId);
        $diseaseData  = $this->surveillanceRepository->getCasesByDisease($faskesScope, $diseaseId);
        $statusData   = $this->surveillanceRepository->getCasesByStatus($faskesScope, $diseaseId);
        $geoData      = $this->surveillanceRepository->getCasesByGeography('kecamatan', $faskesScope, $diseaseId);
        $facilityData = $this->surveillanceRepository->getCasesByFacilityType($faskesScope, $diseaseId);

        return compact('stats', 'recentCases', 'trendData', 'diseaseData', 'statusData', 'geoData', 'facilityData');
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

        // Data scoping: faskes hanya lihat data sendiri di peta
        $user = auth()->user();
        if ($user->isFaskesSurveilans()) {
            $query->where('faskes_type', $user->faskes_type)
                  ->where('id_faskes', $user->getFaskesId());
        }

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

        // Group by kecamatan
        $casesByKecamatan = $cases->groupBy('id_kec')->map(function ($group) {
            return [
                'name' => $group->first()->kecamatan->name ?? 'Unknown',
                'count' => $group->count(),
                'cases' => $group->map(function ($case) {
                    return [
                        'id' => $case->id,
                        'nama' => $case->nama_lengkap,
                        'disease' => $case->jenisKasus->nama_penyakit ?? 'Unknown',
                        'status' => $case->status_kasus,
                        'tanggal_onset' => $case->tanggal_onset->format('d/m/Y'),
                    ];
                })->toArray()
            ];
        });

        // Group by RT — kasus tanpa id_rt dikelompokkan sebagai 'Tidak Terdefinisi'
        $casesByRT = $cases->groupBy('id_rt')->map(function ($group) {
            $rt = $group->first()->rt;
            $rtName = $rt?->name ?? 'Tidak Terdefinisi';
            $kelurahanName = $group->first()->kelurahan?->name ?? null;
            return [
                'name'      => $rtName,
                'kelurahan' => $kelurahanName,
                'undefined' => $rt === null,
                'count' => $group->count(),
                'cases' => $group->map(function ($case) {
                    return [
                        'id'             => $case->id,
                        'no_registrasi'  => $case->no_registrasi,
                        'nama'           => $case->nama_lengkap,
                        'disease'        => $case->jenisKasus->nama_penyakit ?? '-',
                        'status'         => $case->status_kasus,
                        'kelurahan'      => $case->kelurahan?->name ?? '-',
                        'tanggal_onset'  => $case->tanggal_onset->format('d/m/Y'),
                    ];
                })->toArray()
            ];
        });

        // Individual case markers (only cases with coordinates)
        $caseMarkers = $cases->filter(function ($case) {
            return $case->latitude && $case->longitude;
        })->map(function ($case) {
            return [
                'id' => $case->id,
                'nama' => $case->nama_lengkap,
                'disease' => $case->jenisKasus->nama_penyakit ?? 'Unknown',
                'status' => $case->status_kasus,
                'tanggal_onset' => $case->tanggal_onset->format('d/m/Y'),
                'lat' => (float) $case->latitude,
                'lng' => (float) $case->longitude,
            ];
        })->values();

        // Hitung kasus tanpa RT (id_rt null) — ditampilkan sebagai catatan di layer RT
        $undefinedRtCount = $cases->whereNull('id_rt')->count();

        return response()->json([
            'casesByKelurahan' => $casesByKelurahan,
            'casesByKecamatan' => $casesByKecamatan,
            'casesByRT' => $casesByRT,
            'caseMarkers' => $caseMarkers,
            'totalCases' => $cases->count(),
            'undefinedRtCount' => $undefinedRtCount,
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
        $isFaskes = auth()->user()->isFaskesSurveilans();

        $importLogs = auth()->user()->isSuperAdmin()
            ? ImportLog::where('user_id', auth()->id())->latest()->take(5)->get()
            : collect();

        return view('admin.epidemiologi.index', compact('diseases', 'kecamatanList', 'isFaskes', 'importLogs'));
    }

    /**
     * Get surveillance cases for DataTables (AJAX)
     */
    public function getSurveillanceCases(Request $request)
    {
        $query = SurveillanceCase::with(['jenisKasus', 'kecamatan', 'kelurahan', 'rt']);

        // Data scoping berdasarkan filter_mode dan peran pengguna
        $user = auth()->user();
        $filterMode = $request->get('filter_mode', 'dilaporkan'); // default: dilaporkan

        if ($user->isFaskesSurveilans()) {
            if ($filterMode === 'wilker' && $user->puskesmas) {
                // Filter berdasarkan wilker: semua kasus yang wilker_puskesmasnya cocok dengan nama puskesmas user
                $wilkerName = $user->puskesmas->name;
                $query->where('wilker_puskesmas', $wilkerName);
            } else {
                // Default: hanya kasus yang dilaporkan faskes ini
                $query->where('faskes_type', $user->faskes_type)
                      ->where('id_faskes', $user->getFaskesId());
            }
        }

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
                $showUrl   = route('admin.epidemiologi.show', $case->id);
                $editUrl   = route('admin.epidemiologi.edit', $case->id);
                $isFaskes  = auth()->user()->isFaskesSurveilans();

                $deleteBtn = $isFaskes ? '' :
                    "<button type='button' class='btn btn-sm btn-danger' onclick='deleteCase({$case->id})' title='Hapus'>
                        <i class='fa fa-trash'></i>
                    </button>";

                return "
                    <div class='btn-group' role='group'>
                        <a href='{$showUrl}' class='btn btn-sm btn-info' title='Detail'>
                            <i class='fa fa-eye'></i>
                        </a>
                        <a href='{$editUrl}' class='btn btn-sm btn-warning' title='Edit'>
                            <i class='fa fa-edit'></i>
                        </a>
                        {$deleteBtn}
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
        $puskesmasList = Puskesmas::orderBy('name')->get();

        return view('admin.epidemiologi.create', compact('diseases', 'kecamatanList', 'puskesmasList'));
    }

    /**
     * Store a new surveillance case
     */
    public function store(StoreSurveillanceCaseRequest $request)
    {
        try {
            // Override wilker_puskesmas berdasarkan kelurahan yang dipilih
            if ($request->filled('id_kel')) {
                $request->merge(['wilker_puskesmas' => $this->resolveWilker((int) $request->id_kel)]);
            }

            $case = DB::transaction(function () use ($request) {
                $case = $this->surveillanceRepository->storeCase($request);
                $this->surveillanceRepository->syncImunisasi($case, $request->input('imunisasi', []));
                $this->surveillanceRepository->syncFaskesBerobat($case, $request->input('faskes_berobat', []));
                $this->surveillanceRepository->syncSpesimen($case, $request->input('spesimen', []));
                $this->surveillanceRepository->syncKontakErat($case, $request->input('kontak_erat', []));
                return $case;
            });

            $this->clearEpiCache();

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
            'updater',
            'imunisasi',
            'faskesBerobat',
            'spesimen.jenisKasusTerkonfirmasi',
            'kontakErat',
        ])->findOrFail($id);

        $this->authorizeFaskesAccess($case);

        return view('admin.epidemiologi.show', compact('case'));
    }

    /**
     * Show form to edit surveillance case
     */
    public function edit($id)
    {
        $case = SurveillanceCase::findOrFail($id);
        $case->load(['imunisasi', 'faskesBerobat', 'spesimen', 'kontakErat']);
        $this->authorizeFaskesAccess($case);
        $diseases = JenisKasusEpidemiologi::active()->orderBy('nama_penyakit')->get();
        $kecamatanList = Kecamatan::all();
        $puskesmasList = Puskesmas::orderBy('name')->get();

        // Get kelurahan and RT for selected kecamatan
        $kelurahanList = Kelurahan::where('id_kecamatan', $case->id_kec)->get();
        $rtList = Rt::where('id_kelurahan', $case->id_kel)->get();

        return view('admin.epidemiologi.edit', compact('case', 'diseases', 'kecamatanList', 'puskesmasList', 'kelurahanList', 'rtList'));
    }

    /**
     * Update a surveillance case
     */
    public function update(UpdateSurveillanceCaseRequest $request, $id)
    {
        try {
            // Override wilker_puskesmas berdasarkan kelurahan yang dipilih
            if ($request->filled('id_kel')) {
                $request->merge(['wilker_puskesmas' => $this->resolveWilker((int) $request->id_kel)]);
            }

            $case = DB::transaction(function () use ($request, $id) {
                $case = $this->surveillanceRepository->updateCase($request, $id);
                $this->surveillanceRepository->syncImunisasi($case, $request->input('imunisasi', []));
                $this->surveillanceRepository->syncFaskesBerobat($case, $request->input('faskes_berobat', []));
                $this->surveillanceRepository->syncSpesimen($case, $request->input('spesimen', []));
                $this->surveillanceRepository->syncKontakErat($case, $request->input('kontak_erat', []));
                return $case;
            });

            $this->clearEpiCache();

            Alert::success('Berhasil', 'Kasus surveillance berhasil diperbarui');
            return redirect()->route('admin.epidemiologi.index');
        } catch (\Exception $e) {
            Alert::error('Gagal', 'Terjadi kesalahan: ' . $e->getMessage());
            return back()->withInput();
        }
    }

    /**
     * Delete a surveillance case.
     *
     * @access superadmin only — faskes (puskesmas/rs) tidak diizinkan menghapus kasus.
     */
    public function destroy($id)
    {
        abort_if(auth()->user()->isFaskesSurveilans(), 403, 'Faskes tidak memiliki izin menghapus kasus.');

        try {
            $this->surveillanceRepository->deleteCase($id);

            $this->clearEpiCache();

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

    // ==================== IMPORT METHODS ====================

    /**
     * Simpan file Excel PD3I dan antrikan job import di latar belakang.
     */
    public function importExcel(Request $request)
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403, 'Hanya superadmin yang dapat mengimpor data.');

        $request->validate([
            'file_import' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        $file     = $request->file('file_import');
        $filename = $file->getClientOriginalName();
        $path     = $file->store('imports/pd3i');

        $log = ImportLog::create([
            'user_id'   => auth()->id(),
            'filename'  => $filename,
            'file_path' => $path,
            'status'    => 'pending',
        ]);

        ImportPd3iJob::dispatch($log);

        return redirect()->route('admin.epidemiologi.index')
            ->with('import_queued', "File \"{$filename}\" telah diterima dan sedang diproses di latar belakang. Cek status import di bawah.");
    }

    /**
     * Kembalikan status import log terbaru (untuk polling AJAX).
     */
    public function importStatus()
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403);

        $logs = ImportLog::where('user_id', auth()->id())
            ->latest()
            ->take(5)
            ->get(['id', 'filename', 'status', 'success_count', 'failure_count', 'failures', 'started_at', 'completed_at', 'created_at']);

        return response()->json($logs);
    }

    // ==================== EXPORT METHODS ====================

    /**
     * Export cases to Excel (superadmin only)
     */
    public function exportExcel(Request $request)
    {
        abort_if(auth()->user()->isFaskesSurveilans(), 403, 'Faskes tidak memiliki izin export data.');

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
     * Export single case to PDF (MR-01 form)
     */
    public function exportPdfMR01($id)
    {
        $case = SurveillanceCase::with([
            'jenisKasus',
            'kecamatan',
            'kelurahan',
            'rt',
            'petugasInput',
            'imunisasi',
            'faskesBerobat',
            'spesimen.jenisKasusTerkonfirmasi',
            'kontakErat',
        ])->findOrFail($id);

        $this->authorizeFaskesAccess($case);

        $disease = $case->jenisKasus;

        $pdf = Pdf::loadView('admin.epidemiologi.pdf.formulir-mr01', compact('case', 'disease'))
            ->setPaper('a4', 'portrait');

        $filename = 'MR01_' . ($case->no_registrasi ?? $case->id) . '.pdf';

        return $pdf->download($filename);
    }

    // ==================== PRIVATE HELPERS ====================

    /**
     * Abort 403 if faskes user tries to access a case that doesn't belong to their faskes.
     */
    private function authorizeFaskesAccess(SurveillanceCase $case): void
    {
        $user = auth()->user();

        if ($user->isFaskesSurveilans()) {
            abort_unless(
                $case->faskes_type === $user->faskes_type && $case->id_faskes == $user->getFaskesId(),
                403,
                'Anda tidak memiliki izin mengakses kasus dari faskes lain.'
            );
        }
    }

    // ==================== LOKASI PENULARAN ====================

    /**
     * Get lokasi penularan for searchable dropdown (AJAX)
     */
    public function getLokasiPenularan(Request $request)
    {
        $search = $request->get('q', '');

        $query = LokasiPenularanMaster::orderBy('kategori')->orderBy('nama');

        if ($search) {
            $query->where('nama', 'like', "%{$search}%");
        }

        $results = $query->get()->groupBy('kategori')->map(function ($items, $kategori) {
            return [
                'text' => $kategori,
                'children' => $items->map(function ($item) {
                    return ['id' => $item->nama, 'text' => $item->nama];
                })->values(),
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Store a new custom lokasi penularan
     */
    public function storeLokasiPenularan(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kategori' => 'required|in:Sekolah,Tempat Kerja,Gym,Tempat Ibadah,Lainnya',
        ]);

        $lokasi = LokasiPenularanMaster::create([
            'nama' => $validated['nama'],
            'kategori' => $validated['kategori'],
            'is_custom' => true,
        ]);

        return response()->json([
            'success' => true,
            'id' => $lokasi->nama,
            'text' => $lokasi->nama,
            'message' => 'Lokasi penularan berhasil ditambahkan',
        ]);
    }

    /**
     * Clear all epidemiologi dashboard caches (global + per-faskes).
     */
    private function clearEpiCache(): void
    {
        Cache::forget('epi_dashboard_stats');

        $user = auth()->user();
        if ($user->faskes_type && $user->getFaskesId()) {
            Cache::forget("epi_dashboard_faskes_{$user->faskes_type}_{$user->getFaskesId()}");
        }
    }
}
