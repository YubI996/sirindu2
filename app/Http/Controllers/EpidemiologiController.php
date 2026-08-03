<?php

namespace App\Http\Controllers;

use App\Repositories\Admin\Epidemiologi\SurveillanceRepository;
use App\Http\Requests\Epidemiologi\StoreSurveillanceCaseRequest;
use App\Http\Requests\Epidemiologi\UpdateSurveillanceCaseRequest;
use App\Models\SurveillanceCase;
use App\Models\JenisKasusEpidemiologi;
use App\Models\LokasiPenularanMaster;
use App\Models\SekolahDasar;
use App\Models\SekolahMenengahPertama;
use App\Models\SekolahMenengahAtas;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\Puskesmas;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Exports\SurveillanceExport;
use App\Imports\Pd3iImport;
use App\Jobs\ImportPd3iJob;
use App\Models\ImportLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class EpidemiologiController extends Controller
{
    /** Peringatan pasca-simpan; tidak pernah menghalangi penyimpanan. */
    private const PESAN_LAB_BELUM_LENGKAP = 'Kasus berhasil disimpan. '
        . 'Status lab sudah positif/negatif, namun belum ada spesimen dengan tanggal pengambilan. '
        . 'Lengkapi detail laboratorium bila datanya sudah tersedia.';

    protected $surveillanceRepository;

    public function __construct(SurveillanceRepository $surveillanceRepository)
    {
        $this->middleware('auth');
        // Semua user surveilans (superadmin + faskes puskesmas + faskes RS) bisa akses
        $this->middleware('module.role:superadmin,surveilans_puskesmas,surveilans_rs');
        $this->surveillanceRepository = $surveillanceRepository;
    }

    /**
     * Resolve wilker puskesmas dari id_kel via pemetaan terpusat WilkerPuskesmas.
     * Mengembalikan nama wilker atau string kosong jika tidak ditemukan.
     */
    protected function resolveWilker(int $idKel): string
    {
        return \App\Support\WilkerPuskesmas::wilkerForKelurahanId($idKel);
    }

    // ==================== DASHBOARD & ANALYTICS ====================

    /**
     * Show analytics dashboard
     */
    public function dashboard()
    {
        $user = auth()->user();
        $diseases = JenisKasusEpidemiologi::active()->get();

        $dashboardData = $this->buildDashboardData($user);

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

        $diseaseId = $request->filled('disease_id') ? (int) $request->disease_id : null;

        $data = $this->buildDashboardData($user, $diseaseId);

        // Convert recent cases to array for JSON
        $data['recentCases'] = $data['recentCases']->map(function ($case) {
            return [
                'id' => $case->id,
                'no_registrasi' => $case->no_registrasi,
                'nama_lengkap' => $case->nama_lengkap,
                'penyakit' => $case->jenisKasus->nama_penyakit ?? '-',
                'kecamatan' => $case->kecamatan->name ?? '-',
                'kelurahan' => $case->kelurahan->name ?? '-',
                'tanggal_onset' => $case->tanggal_onset?->format('d/m/Y') ?? '-',
                'tanggal_onset_iso' => $case->tanggal_onset?->format('Y-m-d') ?? '',
                'status_kasus' => $case->status_kasus,
                'show_url' => route('admin.epidemiologi.show', $case->id),
            ];
        });

        return response()->json($data);
    }

    /**
     * Build dashboard data arrays (shared between initial load and AJAX)
     */
    private function buildDashboardData($user, ?int $diseaseId = null)
    {
        // Scope ke kasus yang boleh dilihat user (Dinkes = semua, faskes = wilayahnya).
        $scopeUser = $user->isSuperAdmin() ? null : $user;

        $stats = $this->surveillanceRepository->getDashboardStats($scopeUser, $diseaseId);

        $recentQuery = SurveillanceCase::with(['jenisKasus', 'kecamatan', 'kelurahan'])
            ->visibleTo($scopeUser);
        if ($diseaseId) {
            $recentQuery->where('id_jenis_kasus', $diseaseId);
        }
        $recentCases = $recentQuery->orderBy('created_at', 'desc')->limit(10)->get();

        $trendData    = $this->surveillanceRepository->getCasesTrend(12, $scopeUser, $diseaseId);
        $diseaseData  = $this->surveillanceRepository->getCasesByDisease($scopeUser, $diseaseId);
        $statusData   = $this->surveillanceRepository->getCasesByStatus($scopeUser, $diseaseId);
        $geoData      = $this->surveillanceRepository->getCasesByGeography('kecamatan', $scopeUser, $diseaseId);
        $facilityData = $this->surveillanceRepository->getCasesByFacilityType($scopeUser, $diseaseId);

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

        // Data scoping: faskes hanya lihat data wilayahnya di peta (Dinkes = semua).
        // Peta di dasbor PD3I bersifat city-wide untuk semua peran → kirim city_wide=1.
        $user = auth()->user();
        $cityWide = $request->boolean('city_wide');
        $query->visibleTo(($cityWide || $user->isSuperAdmin()) ? null : $user);

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

        // Data scoping: batasi ke kasus yang boleh dilihat user (Dinkes = semua,
        // puskesmas = kelurahan catchment wilker-nya, RS = kasus yang diinput RS).
        $user = auth()->user();
        $query->visibleTo($user->isSuperAdmin() ? null : $user);

        // Filter opsional untuk user puskesmas (spec FR-013): mempersempit tampilan
        // ke kasus yang DILAPORKAN oleh faskes ini saja (subset dari catchment).
        $filterMode = $request->get('filter_mode', 'wilker'); // default: seluruh wilker
        if ($user->isSurveilansPuskesmas() && $filterMode === 'dilaporkan') {
            $query->where('faskes_type', $user->faskes_type)
                  ->where('id_faskes', $user->getFaskesId());
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
            // No. Epid yang sudah terdaftar bukan sekadar kesalahan input — biasanya
            // kasusnya memang sudah ada dan yang dimaksud petugas adalah memperbarui.
            if ($redirect = $this->cegatNoEpidTerdaftar($request)) {
                return $redirect;
            }

            // Override wilker_puskesmas berdasarkan kelurahan yang dipilih
            if ($request->filled('id_kel')) {
                $request->merge(['wilker_puskesmas' => $this->resolveWilker((int) $request->id_kel)]);
            }

            $fotoPath  = $request->hasFile('foto_dokumentasi')
                ? $this->processAndStoreImage($request->file('foto_dokumentasi'))
                : null;
            $fotoPath2 = $request->hasFile('foto_dokumentasi_2')
                ? $this->processAndStoreImage($request->file('foto_dokumentasi_2'))
                : null;

            $case = DB::transaction(function () use ($request, $fotoPath, $fotoPath2) {
                $case = $this->surveillanceRepository->storeCase($request, $fotoPath, $fotoPath2);
                $this->surveillanceRepository->syncImunisasi($case, $request->input('imunisasi', []));
                $this->surveillanceRepository->syncFaskesBerobat($case, $request->input('faskes_berobat', []));
                $this->surveillanceRepository->syncSpesimen($case, $request->input('spesimen', []));
                $this->surveillanceRepository->syncKontakErat($case, $request->input('kontak_erat', []));
                return $case;
            });

            $this->clearEpiCache();

            if ($this->labBelumLengkap($request)) {
                Alert::warning('Tersimpan', self::PESAN_LAB_BELUM_LENGKAP);
            } else {
                Alert::success('Berhasil', 'Kasus surveillance berhasil ditambahkan');
            }
            return redirect()->route('admin.epidemiologi.index');
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Jaring pengaman balapan: nomor lolos pengecekan di atas tapi keburu
            // dipakai permintaan lain sebelum commit. Kolom no_registrasi UNIQUE,
            // jadi DB menolak — sampaikan dengan bahasa yang bisa ditindaklanjuti.
            Alert::error('Gagal', 'No. Epid tersebut baru saja terdaftar oleh petugas lain. '
                . 'Muat ulang halaman, lalu perbarui data yang sudah ada.');
            return back()->withInput();
        } catch (\Exception $e) {
            Alert::error('Gagal', 'Terjadi kesalahan: ' . $e->getMessage());
            return back()->withInput();
        }
    }

    /**
     * Cegat No. Epid yang sudah terdaftar sebelum kasus baru dibuat.
     *
     * Nomor epidemiologi bersifat unik per kasus. Bila petugas mengetik nomor yang
     * sudah ada, hampir selalu maksudnya memperbarui kasus tersebut — jadi arahkan
     * ke sana, jangan cuma menolak.
     *
     * BATAS PRIVASI: arahan hanya diberikan bila kasusnya boleh dilihat petugas ybs.
     * Untuk kasus milik faskes lain, jangan bocorkan id/nama pasien — cukup beri tahu
     * nomornya terpakai dan arahkan ke Dinkes.
     *
     * @return \Illuminate\Http\RedirectResponse|null null bila nomor aman dipakai
     */
    private function cegatNoEpidTerdaftar(StoreSurveillanceCaseRequest $request)
    {
        $noReg = trim((string) $request->input('no_registrasi', ''));

        if ($noReg === '') {
            return null; // akan di-generate otomatis
        }

        $existing = SurveillanceCase::where('no_registrasi', $noReg)->first();

        if (!$existing) {
            return null;
        }

        if (!$existing->isVisibleTo(auth()->user())) {
            return back()->withInput()->withErrors([
                'no_registrasi' => "No. Epid {$noReg} sudah terdaftar pada faskes lain. "
                    . 'Hubungi Dinas Kesehatan untuk memperbarui data tersebut.',
            ]);
        }

        return back()->withInput()->with('epid_duplikat', [
            'id'            => $existing->id,
            'no_registrasi' => $existing->no_registrasi,
            'nama_lengkap'  => $existing->nama_lengkap,
            'url_edit'      => route('admin.epidemiologi.edit', $existing->id),
        ]);
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
        // Cegah faskes mengedit kasus di luar wilayahnya (juga saat akses URL langsung).
        $this->authorizeFaskesAccess(SurveillanceCase::findOrFail($id));

        try {
            // Override wilker_puskesmas berdasarkan kelurahan yang dipilih
            if ($request->filled('id_kel')) {
                $request->merge(['wilker_puskesmas' => $this->resolveWilker((int) $request->id_kel)]);
            }

            $fotoPath  = $request->hasFile('foto_dokumentasi')
                ? $this->processAndStoreImage($request->file('foto_dokumentasi'))
                : null;
            $deleteFoto = $request->boolean('hapus_foto_dokumentasi');

            $fotoPath2  = $request->hasFile('foto_dokumentasi_2')
                ? $this->processAndStoreImage($request->file('foto_dokumentasi_2'))
                : null;
            $deleteFoto2 = $request->boolean('hapus_foto_dokumentasi_2');

            $case = DB::transaction(function () use ($request, $id, $fotoPath, $deleteFoto, $fotoPath2, $deleteFoto2) {
                $case = $this->surveillanceRepository->updateCase($request, $id, $fotoPath, $deleteFoto, $fotoPath2, $deleteFoto2);
                $this->surveillanceRepository->syncImunisasi($case, $request->input('imunisasi', []));
                $this->surveillanceRepository->syncFaskesBerobat($case, $request->input('faskes_berobat', []));
                $this->surveillanceRepository->syncSpesimen($case, $request->input('spesimen', []));
                $this->surveillanceRepository->syncKontakErat($case, $request->input('kontak_erat', []));
                return $case;
            });

            $this->clearEpiCache();

            if ($this->labBelumLengkap($request)) {
                Alert::warning('Tersimpan', self::PESAN_LAB_BELUM_LENGKAP);
            } else {
                Alert::success('Berhasil', 'Kasus surveillance berhasil diperbarui');
            }
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
            $case = SurveillanceCase::findOrFail($id);
            if ($case->foto_dokumentasi) {
                Storage::delete($case->foto_dokumentasi);
            }
            if ($case->foto_dokumentasi_2) {
                Storage::delete($case->foto_dokumentasi_2);
            }
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

    /**
     * Re-encode uploaded image via GD to strip metadata and embedded payloads.
     * Uses UUID filename to prevent path traversal and enumeration.
     */
    private function processAndStoreImage(UploadedFile $file): string
    {
        $tmp = $file->getRealPath();
        $ext = strtolower($file->getClientOriginalExtension());
        $dest = 'dokumentasi/' . Str::uuid() . '.' . $ext;

        if (in_array($ext, ['jpg', 'jpeg'])) {
            $img = imagecreatefromjpeg($tmp);
            ob_start();
            imagejpeg($img, null, 85);
            $data = ob_get_clean();
            imagedestroy($img);
        } else {
            $img = imagecreatefrompng($tmp);
            imagesavealpha($img, true);
            ob_start();
            imagepng($img, null, 6);
            $data = ob_get_clean();
            imagedestroy($img);
        }

        Storage::put($dest, $data);
        return $dest;
    }

    /**
     * Serve a case photo via authenticated route with security headers.
     * File is on private local disk — not accessible via /storage/.
     */
    public function servePhoto($id, $slot = 1)
    {
        $case = SurveillanceCase::findOrFail($id);
        $this->authorizeFaskesAccess($case);

        $path = $slot == 2 ? $case->foto_dokumentasi_2 : $case->foto_dokumentasi;

        abort_unless($path && Storage::exists($path), 404);

        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';

        return response(Storage::get($path), 200, [
            'Content-Type'           => $mime,
            'Content-Disposition'    => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=3600',
        ]);
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
     * Cari biodata pasien berdasarkan NIK untuk autofill form (AJAX).
     *
     * NIK boleh dipakai di banyak kasus (orang sama, kasus berbeda) — endpoint ini
     * hanya menyediakan biodata agar entri lebih cepat, BUKAN validasi keunikan.
     *
     * Prioritas sumber: kasus surveilans terbaru dengan NIK sama (field paling
     * lengkap & relevan surveilans), lalu tabel anak sebagai fallback registry.
     */
    public function lookupNik($nik)
    {
        // 1) Kasus surveilans lama (paling relevan). Sengaja tanpa scope wilayah:
        //    biodata ini toh akan diketik ulang oleh petugas untuk NIK yang sama.
        $case = SurveillanceCase::where('nik', $nik)->latest('id')->first();
        if ($case) {
            return response()->json([
                'found'  => true,
                'source' => 'surveillance',
                'data'   => [
                    'nama_lengkap'    => $case->nama_lengkap,
                    'tanggal_lahir'   => optional($case->tanggal_lahir)->format('Y-m-d'),
                    'jenis_kelamin'   => $case->jenis_kelamin,
                    'alamat_lengkap'  => $case->alamat_lengkap,
                    'id_kec'          => $case->id_kec,
                    'id_kel'          => $case->id_kel,
                    'id_rt'           => $case->id_rt,
                    'no_telepon'      => $case->no_telepon,
                    'nama_orang_tua'  => $case->nama_orang_tua,
                    'no_hp_orang_tua' => $case->no_hp_orang_tua,
                ],
            ]);
        }

        // 2) Tabel anak (master registry) sebagai fallback.
        $anak = \App\Models\Anak::where('nik', $nik)->first();
        if ($anak) {
            $jenisKelamin = $anak->jk == 1 ? 'L' : ($anak->jk == 2 ? 'P' : null);

            return response()->json([
                'found'  => true,
                'source' => 'anak',
                'data'   => [
                    'nama_lengkap'    => $anak->nama,
                    'tanggal_lahir'   => $anak->tgl_lahir ? Carbon::parse($anak->tgl_lahir)->format('Y-m-d') : null,
                    'jenis_kelamin'   => $jenisKelamin,
                    'alamat_lengkap'  => $anak->alamat ?: $anak->alamat_ktp,
                    'id_kec'          => $anak->id_kec,
                    'id_kel'          => $anak->id_kel,
                    'id_rt'           => $anak->id_rt,
                    'no_telepon'      => null,
                    'nama_orang_tua'  => $anak->nama_ibu ?: $anak->nama_ayah,
                    'no_hp_orang_tua' => $anak->no_hp,
                ],
            ]);
        }

        return response()->json(['found' => false, 'source' => null, 'data' => null]);
    }

    // ==================== IMPORT METHODS ====================

    /**
     * Simpan file Excel PD3I dan antrikan job import di latar belakang.
     */
    public function importExcel(Request $request)
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403, 'Hanya superadmin yang dapat mengimpor data.');

        $request->validate([
            'file_import' => 'required|file|mimes:xlsx,xls,csv|max:20480',
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
     * Ulangi import menggunakan file yang sama dari log sebelumnya.
     */
    public function reimportExcel(ImportLog $log)
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403);

        if (!\Illuminate\Support\Facades\Storage::exists($log->file_path)) {
            return redirect()->route('admin.epidemiologi.index')
                ->with('import_queued', "File \"{$log->filename}\" sudah tidak tersedia. Silakan upload ulang.");
        }

        $newLog = ImportLog::create([
            'user_id'   => auth()->id(),
            'filename'  => $log->filename,
            'file_path' => $log->file_path,
            'type'      => 'pd3i',
            'status'    => 'pending',
        ]);

        ImportPd3iJob::dispatch($newLog);

        return redirect()->route('admin.epidemiologi.index')
            ->with('import_queued', "Mengulang import \"{$log->filename}\" di latar belakang. Cek status import di bawah.");
    }

    /**
     * Kembalikan status import log terbaru (untuk polling AJAX).
     */
    public function importStatus()
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403);

        $logs = ImportLog::where('user_id', auth()->id())
            ->where('type', 'pd3i')
            ->latest()
            ->take(5)
            ->get(['id', 'filename', 'status', 'success_count', 'failure_count', 'failures', 'started_at', 'completed_at', 'created_at']);

        return response()->json($logs);
    }

    /**
     * Hapus satu log import.
     */
    public function destroyImportLog(ImportLog $log)
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403);
        abort_if(in_array($log->status, ['pending', 'processing']), 422, 'Tidak bisa menghapus log yang sedang diproses.');

        \Illuminate\Support\Facades\Storage::delete($log->file_path);
        $log->delete();

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin.epidemiologi.index');
    }

    /**
     * Hapus semua log import milik user (kecuali yang masih pending/processing).
     */
    public function destroyAllImportLogs()
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403);

        $logs = ImportLog::where('user_id', auth()->id())
            ->whereIn('status', ['done', 'failed'])
            ->get();

        foreach ($logs as $log) {
            \Illuminate\Support\Facades\Storage::delete($log->file_path);
            $log->delete();
        }

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin.epidemiologi.index');
    }

    // ==================== EXPORT METHODS ====================

    /**
     * Export cases to Excel (superadmin only)
     */
    public function exportExcel(Request $request)
    {
        abort_if(auth()->user()->isFaskesSurveilans(), 403, 'Faskes tidak memiliki izin export data.');

        try {
            // Export penuh: pakai SurveillanceExport yang sama dengan dashboard PD3I
            // (seluruh field kasus + relasi, kolom dinamis). Tahun null = semua tahun,
            // sesuai tabel halaman ini; hormati filter jenis penyakit & status.
            $export = new SurveillanceExport(
                tahun: null,
                jenisKasusId: $request->filled('disease_id') ? (int) $request->disease_id : null,
                statusKasus: $request->filled('status') ? $request->status : null,
            );

            $filename = 'surveilans-pd3i-' . date('Ymd_His') . '.xlsx';

            return Excel::download($export, $filename);
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

        abort_unless(
            $case->isVisibleTo($user),
            403,
            'Anda tidak memiliki izin mengakses kasus di luar wilayah Anda.'
        );
    }

    // ==================== LOKASI PENULARAN ====================

    /**
     * Get lokasi penularan for searchable dropdown (AJAX)
     */
    public function getLokasiPenularan(Request $request)
    {
        $search = $request->get('q', '');

        $lokasiQuery = LokasiPenularanMaster::orderBy('kategori')->orderBy('nama');
        if ($search) {
            $lokasiQuery->where('nama', 'like', "%{$search}%");
        }

        $results = $lokasiQuery->get()->groupBy('kategori')->map(function ($items, $kategori) {
            return [
                'text' => $kategori,
                'children' => $items->map(fn($item) => ['id' => $item->nama, 'text' => $item->nama])->values(),
            ];
        })->values()->toArray();

        $sekolahGroups = [
            'SD/Sederajat'      => SekolahDasar::class,
            'SMP/Sederajat'     => SekolahMenengahPertama::class,
            'SMA/SMK/Sederajat' => SekolahMenengahAtas::class,
        ];

        foreach ($sekolahGroups as $label => $model) {
            $query = $model::orderBy('nama');
            if ($search) {
                $query->where('nama', 'like', "%{$search}%");
            }
            $items = $query->get();
            if ($items->isNotEmpty()) {
                $results[] = [
                    'text'     => $label,
                    'children' => $items->map(fn($s) => ['id' => $s->nama, 'text' => $s->nama])->values()->toArray(),
                ];
            }
        }

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
     * Kasus dengan status lab 'diperiksa' tapi tanpa satu pun spesimen bertanggal.
     *
     * Sejak 2026-07-23 kondisi ini TIDAK lagi menghalangi penyimpanan (dulu lewat
     * required_if pada tanggal_hasil_lab, yang menunjuk field yang sudah tidak ada
     * di form sehingga submit gagal tanpa pesan yang bisa dilihat petugas).
     * Sekarang cukup jadi peringatan setelah data tersimpan.
     */
    private function labBelumLengkap(Request $request): bool
    {
        if ($request->input('status_lab') !== 'diperiksa') {
            return false;
        }

        foreach ((array) $request->input('spesimen', []) as $spesimen) {
            if (! empty($spesimen['tanggal_ambil_spesimen'])) {
                return false;
            }
        }

        return true;
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
