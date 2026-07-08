<?php

namespace App\Http\Controllers;

use App\Exports\AllExport;
use App\Imports\KohortImport;
use App\Jobs\ImportKohortJob;
use App\Models\ImportLog;
use App\Repositories\Admin\User\UserRepository as UserInterface;
use App\Repositories\Admin\Anak\AnakRepository as AnakInterface;
use App\Http\Requests\Admin\User\storeUserRequest;
use App\Http\Requests\Admin\Anak\storeAnakRequest;
use App\Http\Requests\Admin\Anak\updateAnakRequest;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Imunisasi;
use App\Models\JenisVaksin;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\PrioritasGizi;
use App\Models\RumahSakit;
use App\Models\Rt;
use App\Models\User;
use App\Support\WilkerPuskesmas;
use App\Support\Kuantil;
use App\Services\PetaPrioritasService;
use Illuminate\Support\Facades\Auth;
use App\Exports\AnakExport;
use App\Exports\PrioritasTierExport;
use App\Exports\VaccineNeedsExport;
use App\Models\AllData;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;


use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;
use App\Services\PayUService\Exception;

use function PHPUnit\Framework\isEmpty;

class AdminController extends Controller
{
    protected $userRepository;
    protected $anakRepository;
    protected \App\Services\StatusGiziService $statusGizi;

    public function __construct(
        UserInterface $userRepository,
        AnakInterface $anakRepository,
        \App\Services\StatusGiziService $statusGizi
    ) {
        $this->middleware('auth');
        $this->userRepository = $userRepository;
        $this->anakRepository = $anakRepository;
        $this->statusGizi = $statusGizi;
    }

    /*------------------------------------------
--------------------------------------------
ANAK
--------------------------------------------
--------------------------------------------*/

    public function anak()
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        $kohortImportLogs = collect();

        if ($isSuperAdmin) {
            $kohortImportLogs = ImportLog::where('user_id', auth()->id())
                ->where('type', 'kohort')
                ->latest()
                ->take(5)
                ->get();
        }

        return view('admin.anak.index', compact('isSuperAdmin', 'kohortImportLogs'));
    }

    /**
     * Simpan file Excel kohort dan antrikan job import di latar belakang.
     */
    public function importKohort(Request $request)
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403, 'Hanya superadmin yang dapat mengimpor data.');

        $request->validate([
            'file_kohort' => 'required|file|mimes:xlsx|max:20480',
        ]);

        $file     = $request->file('file_kohort');
        $filename = $file->getClientOriginalName();
        $path     = $file->store('imports/kohort');

        $log = ImportLog::create([
            'user_id'   => auth()->id(),
            'filename'  => $filename,
            'file_path' => $path,
            'type'      => 'kohort',
            'status'    => 'pending',
        ]);

        ImportKohortJob::dispatch($log);

        return redirect()->route('admin.anak')
            ->with('import_queued', "File \"{$filename}\" telah diterima dan sedang diproses di latar belakang. Cek status import di bawah.");
    }

    /**
     * Kembalikan status import kohort terbaru untuk polling AJAX.
     */
    public function importKohortStatus()
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403);

        $logs = ImportLog::where('user_id', auth()->id())
            ->where('type', 'kohort')
            ->latest()
            ->take(5)
            ->get(['id', 'filename', 'status', 'success_count', 'failure_count', 'failures', 'started_at', 'completed_at', 'created_at']);

        return response()->json($logs);
    }

    public function getAnak()
    {
        $data = Anak::select('id', 'no_kk', 'nik', 'nama', 'nik_ortu', 'nama_ibu', 'nama_ayah', 'jk', 'tempat_lahir', 'tgl_lahir');
        return DataTables::of($data)
            ->addIndexColumn()
            ->editColumn('edit', function ($data) {
                $btn = '
                <div class="dropdown">
                <button class="btn btn-warning btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                   Pilihan
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="' . route('admin.editAnak', $data->hashid) . '">Edit Data Anak</a>
                    <a class="dropdown-item" href="' . route('admin.showAnak', $data->hashid) . '">Lihat Detail</a>
                    <a class="dropdown-item" href="' . route('admin.chartAnak', $data->hashid) . '">Grafik Data Anak</a>
                    <a class="dropdown-item" href="' . route('admin.dataAnak', $data->hashid) . '">Tambah Data Berkala</a>
                    <a class="dropdown-item" href="' . route('admin.imunisasiLengkap', $data->hashid) . '">Data Imunisasi</a>
                    <a class="dropdown-item" href="' . route('admin.jadwalImunisasi', $data->hashid) . '">Jadwal Imunisasi</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="#" onclick="deleteItemAnak(this); return false;" data-id="' . $data->hashid . '">Hapus Data</a>
                </div>
                </div>
                ';
                return $btn;
            })
            ->rawColumns(['edit'])
            ->escapeColumns([])
            ->make(true);
    }


    public function createAnak()
    {
        $kec = Kecamatan::all();
        //$kel = Kelurahan::all();
        return view('admin.anak.create', compact('kec'));
    }

    public function getPuskesmasAnak($id)
    {
        $puskesmas = Puskesmas::where('id_kecamatan', $id)->pluck('name', 'id');
        return response()->json($puskesmas);
    }

    public function getKelAnak($id)
    {
        $kel = Kelurahan::where('id_kecamatan', $id)->pluck('name', 'id');
        return response()->json($kel);
    }

    public function getRtAnak($id)
    {
        $rt = Rt::where('id_posyandu', $id)->pluck('name', 'id');
        return response()->json($rt);
    }

    public function getRtByKelAnak($id)
    {
        $rt = Rt::where('id_kelurahan', $id)->pluck('name', 'id');
        return response()->json($rt);
    }
    public function getPosyanduAnak($id)
    {
        $posyandu = Posyandu::where('id_puskesmas', $id)->pluck('name', 'id');
        return response()->json($posyandu);
    }

    /** Posyandu pada satu kelurahan (lewat tabel rt yg menaut kel↔posyandu). */
    public function getPosyanduByKelAnak($id)
    {
        $posyandu = Posyandu::whereIn('id', Rt::where('id_kelurahan', $id)->pluck('id_posyandu')->filter()->unique())
            ->orderBy('name')
            ->pluck('name', 'id');
        return response()->json($posyandu);
    }

    public function editAnak($id)
    {
        $anak = Anak::findByHashIdOrFail($id);
        $kec = Kecamatan::all();
        $kel = Kelurahan::all();
        // Anak hasil import bisa belum punya baris DataAnak (data kelahiran).
        // firstOrNew memberi instance kosong agar form edit tidak error null.
        $dt = DataAnak::firstOrNew(['id_anak' => $anak->id]);
        $dataAnak = DataAnak::where('id_anak', $anak->id)->get();
        return view('admin.anak.edit', compact('anak', 'kec', 'kel', 'dt', 'dataAnak'));
    }

    public function updateAnak(Request $request, $id)
    {
        try {
            $anak = Anak::findByHashIdOrFail($id);
            $this->anakRepository->updateAnak($request, $anak->id);
            Alert::success('Anak', 'Berhasil Mengubah Data');
            return redirect()->route('admin.anak');
        } catch (\Throwable $e) {
            Alert::error('Anak', 'Gagal Mengubah Data');
            return redirect()->route('admin.anak');
        }
    }

    public function storeAnak(storeAnakRequest $request)
    {
        try {
            $anak = $this->anakRepository->storeAnak($request);
            Alert::success('Anak', 'Berhasil Menambahkan Data');
            return redirect()->route('admin.anak');
        } catch (Throwable $e) {
            Alert::error('Anak', 'Gagal Menambahkan Data');
            return redirect()->route('admin.anak');
        }
    }

    public function showAnak($id)
    {
        $anak = Anak::findByHashIdOrFail($id);

        // Get location data
        $kecamatan = Kecamatan::find($anak->id_kec);
        $kelurahan = Kelurahan::find($anak->id_kel);
        $rt = Rt::find($anak->id_rt);
        $puskesmas = Puskesmas::find($anak->id_puskesmas);
        $posyandu = Posyandu::find($anak->id_posyandu);

        // Calculate age
        $tglLahir = \Carbon\Carbon::parse($anak->tgl_lahir);
        $now = \Carbon\Carbon::now();
        $usia = $tglLahir->diff($now);

        // Total age in months
        $usiaBulan = ($usia->y * 12) + $usia->m;

        $usiaText = '';
        if ($usia->y > 0) {
            $usiaText .= $usia->y . ' tahun ';
        }
        if ($usia->m > 0) {
            $usiaText .= $usia->m . ' bulan';
        }
        if (empty($usiaText)) {
            $usiaText = $usia->d . ' hari';
            $usiaBulan = 0;
        }
        $usiaText = trim($usiaText);

        // Get immunization records
        $imunisasi = Imunisasi::with('jenisVaksin')
            ->where('id_anak', $anak->id)
            ->orderBy('tanggal_pemberian', 'desc')
            ->get();

        $dataAnak = DB::table('anak')
            ->join('data_anak', 'anak.id', '=', 'data_anak.id_anak')
            ->select('data_anak.id', 'jk', 'tgl_kunjungan', 'bln', 'posisi', 'tb', 'bb')
            ->where('data_anak.id_anak', $anak->id)
            ->orderBy('tgl_kunjungan', 'desc')
            ->get();

        $no = 0;
        $hasilx = [];
        $latestData = null;

        foreach ($dataAnak as $key => $data) {
            $no++;
            $g = $this->statusGizi->klasifikasi($data->bb, $data->tb, $data->bln, $data->posisi, $data->jk);

            $err = null;
            if ($g['bmi'] < 10.2 || $g['bmi'] > 21.1) {
                $err = "Nilai BMI tidak normal";
            } elseif ($g['tinggi'] < 44.2 || $g['tinggi'] > 123.9) {
                $err = "Nilai Tinggi Badan tidak normal";
            } elseif ($g['berat'] < 1.9 || $g['berat'] > 31.2) {
                $err = "Nilai Berat Badan tidak normal";
            }
            // Tampilan show bersifat semua-atau-tidak: bila ada indikator yg
            // referensinya kurang, keempatnya ditandai tidak tersedia.
            $tidakAda = 'Data Z-Score tidak tersedia';
            $imt = $bb = $tb = $bt = $tidakAda;
            if ($g['tersedia']) {
                $imt = $g['imt'];
                $bb = $g['bb'];
                $tb = $g['tb'];
                $bt = $g['bt'];
            } else {
                $err = $err ?? "Data referensi Z-Score belum tersedia";
            }

            $hasilx[$key] = [
                "no" => $no,
                "bln" => $g['bln'],
                "tgl_kunjungan" => $data->tgl_kunjungan,
                "tinggi" => $g['tinggi'],
                "berat" => $g['berat'],
                "bmi" => $g['bmi'],
                "imt" => $imt,
                "bb" => $bb,
                "tb" => $tb,
                "bt" => $bt,
                "err" => $err,
            ];
        }

        // Get latest measurement for current health status
        $latestData = !empty($hasilx) ? end($hasilx) : null;

        // Alasan tidak menerima imunisasi dari kunjungan terakhir anak,
        // ditampilkan di bagian Riwayat Imunisasi pada halaman detail.
        $alasanTidakImunisasiTerakhir = DataAnak::where('id_anak', $anak->id)
            ->orderByDesc('tgl_kunjungan')
            ->orderByDesc('id')
            ->value('alasan_tidak_imunisasi');

        // Get Z-Score reference data for charts
        $zScoreRef = [];
        if (!empty($hasilx)) {
            $ages = array_column($hasilx, 'bln');
            $minAge = min($ages);
            $maxAge = max($ages);

            // BB/U (Weight for Age) - jenis_tbl = 2
            $bbURef = DB::table('z_score')
                ->select('acuan', 'm2sd', 'm1sd', 'sd', '1sd', '2sd')
                ->where('jenis_tbl', 2)
                ->where('jk', $anak->jk)
                ->whereBetween('acuan', [$minAge, $maxAge])
                ->orderBy('acuan')
                ->get()
                ->keyBy('acuan');

            // TB/U (Height for Age) - jenis_tbl = 3
            $tbURef = DB::table('z_score')
                ->select('acuan', 'var', 'm2sd', 'm1sd', 'sd', '1sd', '2sd')
                ->where('jenis_tbl', 3)
                ->where('jk', $anak->jk)
                ->whereBetween('acuan', [$minAge, $maxAge])
                ->orderBy('acuan')
                ->get();

            // IMT/U (BMI for Age) - jenis_tbl = 1
            $imtURef = DB::table('z_score')
                ->select('acuan', 'var', 'm2sd', 'm1sd', 'sd', '1sd', '2sd')
                ->where('jenis_tbl', 1)
                ->where('jk', $anak->jk)
                ->whereBetween('acuan', [$minAge, $maxAge])
                ->orderBy('acuan')
                ->get();

            $zScoreRef = [
                'bb_u' => $bbURef,
                'tb_u' => $tbURef,
                'imt_u' => $imtURef,
            ];
        }

        return view('admin.anak.show', compact(
            'anak',
            'hasilx',
            'latestData',
            'alasanTidakImunisasiTerakhir',
            'kecamatan',
            'kelurahan',
            'rt',
            'puskesmas',
            'posyandu',
            'usiaText',
            'usiaBulan',
            'imunisasi',
            'zScoreRef'
        ));
    }


    public function chartAnak($id)
    {
        $anak = Anak::findByHashIdOrFail($id);
        return view('admin.anak.chart', compact('anak'));
    }

    public function getChartAnak($id)
    {
        $anak = Anak::findByHashIdOrFail($id);

        $tbAnak = DB::table('anak')
            ->join('data_anak', 'anak.id', '=', 'data_anak.id_anak')
            ->select('tb')
            ->where('data_anak.id_anak', $anak->id)
            ->get();

        $blnAnak = DB::table('anak')
            ->join('data_anak', 'anak.id', '=', 'data_anak.id_anak')
            ->select('bln')
            ->where('data_anak.id_anak', $anak->id)
            ->get();

        $bbAnak = DB::table('anak')
            ->join('data_anak', 'anak.id', '=', 'data_anak.id_anak')
            ->select('bb')
            ->where('data_anak.id_anak', $anak->id)
            ->get();

        return response()->json([
            'tb' => $tbAnak,
            'bln' => $blnAnak,
            'bb' => $bbAnak,

        ]);
    }

    public function destroyAnak($id)
    {
        $anak = Anak::findByHashIdOrFail($id);
        $this->anakRepository->destroyAnak($anak->id);
        return response()->json([
            'success' => true
        ]);
    }

    public function dataAnak($id)
    {
        $anak = Anak::findByHashIdOrFail($id);
        $jenisVaksin = $this->anakRepository->getJenisVaksin();
        $alasanTidakImunisasi = config('imunisasi.alasan_tidak_imunisasi', []);
        return view('admin.anak.data-anak', compact('anak', 'jenisVaksin', 'alasanTidakImunisasi'));
    }

    public function storeDataAnak(Request $request)
    {
        $request->validate([
            'id_anak_hash' => 'required',
            'tgl_kunjungan' => 'required|date',
            'posisi' => 'required|in:H,L',
            'tb' => 'required|numeric',
            'bb' => 'required|numeric',
            'lla' => 'required|numeric',
            'lk' => 'required|numeric',
        ], [
            'tgl_kunjungan.required' => 'Tanggal kunjungan wajib diisi.',
            'tgl_kunjungan.date' => 'Format tanggal kunjungan tidak valid.',
            'posisi.required' => 'Posisi wajib dipilih.',
            'posisi.in' => 'Posisi harus H atau L.',
            'tb.required' => 'Tinggi badan wajib diisi.',
            'tb.numeric' => 'Tinggi badan harus berupa angka.',
            'bb.required' => 'Berat badan wajib diisi.',
            'bb.numeric' => 'Berat badan harus berupa angka.',
            'lla.required' => 'Lingkar lengan atas wajib diisi.',
            'lla.numeric' => 'Lingkar lengan atas harus berupa angka.',
            'lk.required' => 'Lingkar kepala wajib diisi.',
            'lk.numeric' => 'Lingkar kepala harus berupa angka.',
        ]);

        try {
            $anak = Anak::findByHashIdOrFail($request->id_anak_hash);

            // Umur (bln) dihitung otomatis dari tgl lahir → tgl kunjungan,
            // tidak lagi diinput manual di form.
            $bln = usia_bulan($anak->tgl_lahir, $request->tgl_kunjungan) ?? 0;

            // Alasan "Lainnya" → ambil dari input teks bebas.
            $alasan = $request->alasan_tidak_imunisasi;
            if ($alasan === 'Lainnya') {
                $alasan = $request->alasan_tidak_imunisasi_lain;
            }

            $request->merge([
                'id_anak' => $anak->id,
                'bln' => $bln,
                'alasan_tidak_imunisasi' => $alasan,
            ]);

            DB::beginTransaction();

            $this->anakRepository->storeDataAnak($request);

            // Simpan data imunisasi jika ada
            if ($request->has('imunisasi') && is_array($request->imunisasi)) {
                foreach ($request->imunisasi as $dataImunisasi) {
                    if (empty($dataImunisasi['id_jenis_vaksin'])) {
                        continue;
                    }
                    $imunisasiRequest = new Request([
                        'id_anak' => $anak->id,
                        'id_jenis_vaksin' => $dataImunisasi['id_jenis_vaksin'],
                        'dosis' => $dataImunisasi['dosis'] ?? 1,
                        'tanggal_pemberian' => $dataImunisasi['tanggal_pemberian'],
                        'batch_number' => $dataImunisasi['batch_number'] ?? null,
                        'lokasi_pemberian' => $dataImunisasi['lokasi_pemberian'] ?? null,
                        'reaksi_kipi' => $dataImunisasi['reaksi_kipi'] ?? null,
                        'catatan' => $dataImunisasi['catatan'] ?? null,
                    ]);
                    $this->anakRepository->storeImunisasiDetail($imunisasiRequest);
                }
            }

            DB::commit();

            Alert::success('Data Anak', 'Berhasil Menambahkan Data');
            return redirect()->route('admin.anak');
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('storeDataAnak error: ' . $e->getMessage());
            Alert::error('Data Anak', 'Gagal Menambahkan Data');
            return redirect()->route('admin.anak');
        }
    }

    public function updateDataAnak(Request $request, $id)
    {
        try {
            $this->anakRepository->updateDataAnak($request, $id);
            Alert::success('Anak', 'Berhasil Mengubah Data Berkala Anak');
            return redirect()->route('admin.anak');
        } catch (Throwable $e) {
            Alert::error('Anak', 'Gagal Mengubah Data Berkala Anak');
            return redirect()->route('admin.anak');
        }
    }

    // ==================== IMUNISASI METHODS ====================

    public function imunisasiLengkap($id)
    {
        $data = Anak::findByHashIdOrFail($id);
        $imunisasiList = $this->anakRepository->getImunisasiByAnak($data->id);
        $jenisVaksin = $this->anakRepository->getJenisVaksin();
        return view('admin.anak.imunisasi-lengkap', compact('data', 'imunisasiList', 'jenisVaksin'));
    }

    public function jadwalImunisasi($id)
    {
        $data = Anak::findByHashIdOrFail($id);
        $jadwal = $this->anakRepository->getJadwalImunisasi($data->id);
        $catchupPlan = $this->anakRepository->getCatchupPlan($data->id);
        $jenisVaksin = $this->anakRepository->getJenisVaksin();
        return view('admin.anak.jadwal-imunisasi', compact('data', 'jadwal', 'catchupPlan', 'jenisVaksin'));
    }

    public function imunisasiDashboard(Request $request)
    {
        $filters = array_filter([
            'id_kecamatan' => $request->integer('id_kecamatan') ?: null,
            'id_kelurahan' => $request->integer('id_kelurahan') ?: null,
            'id_posyandu'  => $request->integer('id_posyandu') ?: null,
        ]);

        $service  = app(\App\Services\ImunisasiStatusService::class);
        // Satu pass populasi: cakupan IDL + hitung "butuh kejar" akurat (lintas semua anak).
        $coverage = $service->getIdlCoverage($filters, withKejar: true);
        $butuhKejar = $coverage['butuh_kejar'] ?? 0;

        // Daftar anak ≥12 bln — dipaginasi server-side agar tak membangun/mengirim ribuan
        // baris. Hanya baris pada halaman aktif yang dihitung statusnya.
        $anakKejarQuery = Anak::with(['imunisasi.jenisVaksin', 'kel', 'posyandu'])
            ->whereRaw('TIMESTAMPDIFF(MONTH, tgl_lahir, CURDATE()) >= 12')
            ->orderBy('nama');

        if (!empty($filters['id_posyandu'])) {
            $anakKejarQuery->where('id_posyandu', $filters['id_posyandu']);
        } elseif (!empty($filters['id_kelurahan'])) {
            $anakKejarQuery->where('id_kel', $filters['id_kelurahan']);
        } elseif (!empty($filters['id_kecamatan'])) {
            $anakKejarQuery->where('id_kec', $filters['id_kecamatan']);
        }

        $anakList = $anakKejarQuery->paginate(25)->withQueryString();
        $anakList->getCollection()->transform(function ($anak) use ($service) {
            $kejar = $anak->statusKejarVaksin();

            return [
                'anak'          => $anak,
                'idl_lengkap'   => $service->isIdlLengkap($anak),
                'kejar_idl'     => $kejar['kejar_idl'],
                'kejar_ibl'     => $kejar['kejar_ibl'],
                'vaksin_kejar'  => $kejar['vaksin_kejar'],
            ];
        });

        $kecamatanList = \App\Models\Kecamatan::orderBy('name')->get();
        $kelurahanList = \App\Models\Kelurahan::orderBy('name')->get();
        $posyanduList  = \App\Models\Posyandu::orderBy('name')->get();

        // Korelasi cakupan IDL vs prevalensi stunting per kelurahan (Paket E).
        $korelasiData = $this->korelasiStuntingVaksin($filters, $coverage);
        $alasanTidakImunisasi = $this->alasanTidakImunisasiData($filters);

        return view('admin.imunisasi.dashboard', compact(
            'coverage', 'anakList', 'butuhKejar', 'filters',
            'kecamatanList', 'kelurahanList', 'posyanduList', 'korelasiData',
            'alasanTidakImunisasi'
        ));
    }

    /**
     * Data korelasi per kelurahan: % IDL (ImunisasiStatusService) vs % stunting
     * (StatusGiziService pada kunjungan terakhir). Kelurahan tanpa balita terukur di-skip.
     *
     * @return list<array{nama:string,total_balita:int,idl_lengkap:int,idl_pct:float,stunting:int,stunting_pct:float}>
     */
    private function korelasiStuntingVaksin(array $filters, array $coverage): array
    {
        // Kunjungan terakhir per anak (terfilter wilayah) → stunting per kelurahan.
        $maxTgl = DB::table('data_anak as dm')
            ->join('anak as am', 'dm.id_anak', '=', 'am.id')
            ->selectRaw('dm.id_anak, MAX(dm.tgl_kunjungan) as max_tgl')
            ->whereNotNull('dm.tgl_kunjungan');
        if (!empty($filters['id_posyandu']))      $maxTgl->where('am.id_posyandu', $filters['id_posyandu']);
        elseif (!empty($filters['id_kelurahan'])) $maxTgl->where('am.id_kel', $filters['id_kelurahan']);
        elseif (!empty($filters['id_kecamatan'])) $maxTgl->where('am.id_kec', $filters['id_kecamatan']);
        $maxTgl->groupBy('dm.id_anak');

        $rows = DB::table('data_anak as da')
            ->joinSub($maxTgl, 'm', function ($j) {
                $j->on('m.id_anak', '=', 'da.id_anak')->on('m.max_tgl', '=', 'da.tgl_kunjungan');
            })
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->where('da.bln', '<=', 60)->where('da.bb', '>', 0)->where('da.tb', '>', 0)
            ->select('a.id_kel', 'da.bb', 'da.tb', 'da.bln', 'da.posisi', 'a.jk')
            ->get();

        $stuntPerKel = [];
        foreach ($rows as $m) {
            $g = $this->statusGizi->klasifikasi($m->bb, $m->tb, $m->bln, $m->posisi, $m->jk);
            if ($g['enum']['tb_u'] === null) continue;
            $k = $m->id_kel ?? 0;
            if (!isset($stuntPerKel[$k])) $stuntPerKel[$k] = ['measured' => 0, 'stunting' => 0];
            $stuntPerKel[$k]['measured']++;
            if (in_array($g['enum']['tb_u'], ['severely_stunted', 'stunted'], true)) {
                $stuntPerKel[$k]['stunting']++;
            }
        }

        $out = [];
        foreach (($coverage['per_kelurahan'] ?? []) as $kelId => $idl) {
            $st = $stuntPerKel[$kelId] ?? null;
            if (!$st || $st['measured'] <= 0) continue; // butuh sumbu Y (stunting)
            $out[] = [
                'nama'         => $idl['nama'],
                'total_balita' => $st['measured'],
                'idl_lengkap'  => $idl['lengkap'],
                'idl_pct'      => $idl['persen'],
                'stunting'     => $st['stunting'],
                'stunting_pct' => round($st['stunting'] / $st['measured'] * 100, 1),
            ];
        }

        return $out;
    }

    /**
     * Distribusi alasan tidak menerima imunisasi, dari kunjungan terakhir tiap
     * anak (terfilter wilayah). Nilai non-config dikelompokkan ke "Lainnya".
     *
     * @return array<string,int> label alasan => jumlah, terurut menurun.
     */
    private function alasanTidakImunisasiData(array $filters): array
    {
        // Kunjungan terakhir per anak (pola sama seperti korelasiStuntingVaksin).
        $maxTgl = DB::table('data_anak as dm')
            ->join('anak as am', 'dm.id_anak', '=', 'am.id')
            ->selectRaw('dm.id_anak, MAX(dm.tgl_kunjungan) as max_tgl')
            ->whereNotNull('dm.tgl_kunjungan');
        if (!empty($filters['id_posyandu']))      $maxTgl->where('am.id_posyandu', $filters['id_posyandu']);
        elseif (!empty($filters['id_kelurahan'])) $maxTgl->where('am.id_kel', $filters['id_kelurahan']);
        elseif (!empty($filters['id_kecamatan'])) $maxTgl->where('am.id_kec', $filters['id_kecamatan']);
        $maxTgl->groupBy('dm.id_anak');

        $values = DB::table('data_anak as da')
            ->joinSub($maxTgl, 'm', function ($j) {
                $j->on('m.id_anak', '=', 'da.id_anak')->on('m.max_tgl', '=', 'da.tgl_kunjungan');
            })
            ->whereNotNull('da.alasan_tidak_imunisasi')
            ->where('da.alasan_tidak_imunisasi', '!=', '')
            ->pluck('da.alasan_tidak_imunisasi');

        $known  = config('imunisasi.alasan_tidak_imunisasi', []);
        $counts = [];
        foreach ($values as $val) {
            $val = trim((string) $val);
            if ($val === '') continue;
            $bucket = in_array($val, $known, true) ? $val : 'Lainnya';
            $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
        }
        arsort($counts);

        return $counts;
    }

    public function storeImunisasiDetail(Request $request)
    {
        try {
            $anak = Anak::findByHashIdOrFail($request->id_anak_hash);

            $request->merge(['id_anak' => $anak->id]);

            $request->validate([
                'id_anak' => 'required|exists:anak,id',
                'id_jenis_vaksin' => 'required|exists:jenis_vaksin,id',
                'tanggal_pemberian' => 'required|date',
            ]);

            $this->anakRepository->storeImunisasiDetail($request);
            Alert::success('Imunisasi', 'Berhasil Menambahkan Data Imunisasi');
            return redirect()->route('admin.imunisasiLengkap', $anak->hashid);
        } catch (\Throwable $e) {
            Alert::error('Imunisasi', 'Gagal Menambahkan Data Imunisasi: ' . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    public function updateImunisasiDetail(Request $request, $id)
    {
        try {
            $imunisasi = Imunisasi::findOrFail($id);
            $anak = Anak::findOrFail($imunisasi->id_anak);
            $this->anakRepository->updateImunisasiDetail($request, $id);
            Alert::success('Imunisasi', 'Berhasil Mengubah Data Imunisasi');
            return redirect()->route('admin.imunisasiLengkap', $anak->hashid);
        } catch (\Throwable $e) {
            Alert::error('Imunisasi', 'Gagal Mengubah Data Imunisasi: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    public function deleteImunisasiDetail($id)
    {
        try {
            $imunisasi = Imunisasi::findOrFail($id);
            $anak = Anak::findOrFail($imunisasi->id_anak);
            $this->anakRepository->deleteImunisasiDetail($id);
            Alert::success('Imunisasi', 'Berhasil Menghapus Data Imunisasi');
            return redirect()->route('admin.imunisasiLengkap', $anak->hashid);
        } catch (\Throwable $e) {
            Alert::error('Imunisasi', 'Gagal Menghapus Data Imunisasi');
            return redirect()->back();
        }
    }

    public function exportView()
    {
        $kec = Kecamatan::all();
        return view('admin.anak.export', compact('kec'));
    }

    public function formViewExport(Request $request)
    {
        return Excel::download(new AnakExport($request), 'data-anak.xlsx');
        // $from_date = $request->from_date;
        // $to_date = $request->to_date;
        // $kec = $request->id_kec;
        // $kel = $request->id_kel;
        // $rt = $request->id_rt;
        // $puskesmas = $request->id_puskesmas;
        // $posyandu = $request->id_posyandu;
        // if ($request->from_date != '' && $request->to_date != '') {
        //     if ($request->id_kec !== "0" && $request->id_kec !== null) {

        //         if ($request->id_puskesmas !== "0" && $request->id_puskesmas !== null) {
        //             $export = DB::table('alldata')->whereBetween('tgl_kunjungan', array($request->from_date, $request->to_date))
        //                 ->where('idKec', $kec)->where('idPuskes', $puskesmas)
        //                 ->get();
        //             return view('admin.anak.export.view', compact('export'));
        //             if ($request->id_posyandu !== "0" && $request->id_posyandu !== null) {
        //                 $export = DB::table('alldata')->whereBetween('tgl_kunjungan', array($request->from_date, $request->to_date))
        //                     ->where('idKec', $kec)->where('idPuskes', $puskesmas)->where('idPos', $posyandu)
        //                     ->get();
        //                 return view('admin.anak.export.view', compact('export'));
        //             }
        //         } elseif ($request->id_kelurahan !== "0" && $request->id_kelurahan !== null) {
        //             $export = DB::table('alldata')->whereBetween('tgl_kunjungan', array($request->from_date, $request->to_date))
        //                 ->where('idKec', $kec)->where('idKel', $kel)
        //                 ->get();
        //             return view('admin.anak.export.view', compact('export'));
        //             if ($request->id_rt !== "0" && $request->id_rt !== null) {
        //                 $export = DB::table('alldata')->whereBetween('tgl_kunjungan', array($request->from_date, $request->to_date))
        //                     ->where('idKec', $kec)->where('idKel', $kel)->where('idRt', $rt)
        //                     ->get();
        //                 return view('admin.anak.export.view', compact('export'));
        //             }
        //         } else {
        //             $export = DB::table('alldata')->whereBetween('tgl_kunjungan', array($request->from_date, $request->to_date))->where('idKec', $kec)->get();
        //             return view('admin.anak.export.view', compact('export'));
        //         }
        //     }
        // } else {
        //     $export = DB::table('alldata')->whereBetween('tgl_kunjungan', array($request->from_date, $request->to_date))->get();
        //     return view('admin.anak.export.view', compact('export'));
        // }
    }

    public function formViewExportExcel()
    {
        //return Excel::download(new AnakExport(), 'data-anak.xlsx');
    }

    public function exportExcel(Request $request)
    {
        //return Excel::download(new AnakExport($request), 'data-anak.xlsx');
        // if ($request->id_kec !== "0" && $request->id_kec !== null) {
        //     if ($request->id_puskesmas !== "0" && $request->id_puskesmas !== null) {
        //         return (new FastExcel(AllData::where('idKec', $request->id_kec)
        //             ->where('idPuskes', $request->id_puskesmas)
        //             ->get()))->download(
        //             'all-data-anak.xlsx',
        //             function ($data) {
        //                 return [
        //                     'No KK' => $data->no_kk,
        //                     'NIK' => $data->nik,
        //                     'Nama' => $data->nama,
        //                     'Nik Orang Tua' => $data->nik_ortu,
        //                     'Nama Ibu' => $data->nama_ibu,
        //                     'Nama Ayah' => $data->nama_ayah,
        //                     'Jenis Kelamin' => $data->jk,
        //                     'Tempat Lahir' => $data->tempat_lahir,
        //                     'Tanggal Lahir' => $data->tgl_lahir,
        //                     'Golongan Darah' => $data->golda,
        //                     'Anak Ke-' => $data->anak,
        //                     'Catatan' => $data->catatan,
        //                     'hbo' => $data->hbo,
        //                     'bcg' => $data->bcg,
        //                     'polio1' => $data->polio1,
        //                     'dpthb_hib1' => $data->dpthb_hib1,
        //                     'polio2' => $data->polio2,
        //                     'dpthb_hib2' => $data->dpthb_hib2,
        //                     'polio3' => $data->polio3,
        //                     'dpthb_hib3' => $data->dpthb_hib3,
        //                     'polio4' => $data->polio4,
        //                     'campak' => $data->campak,
        //                     'Kecamatan' => $data->nameKec,
        //                     'Kelurahan' => $data->nameKel,
        //                     'Puskesmas' => $data->namePuskes,
        //                     'Posyandu' => $data->namePos,
        //                     'RT' => $data->nameRt,
        //                     'Bulan' => $data->bln,
        //                     'Posisi' => $data->posisi,
        //                     'Tinggi Badan' => $data->tb,
        //                     'Berat Badan' => $data->bb,
        //                     'BMI' => ($data->tb > 0) ? round(10000 * $data->bb / pow($data->tb, 2), 2) : null,
        //                     'Lingkar Lengan Atas' => $data->lla,
        //                     'Lingkar Kepala' => $data->lk,
        //                     'NTOB' => $data->ntob,
        //                     'ASI' => $data->asi,
        //                     'Vitamin A' => $data->vit_a,
        //                     'Nama Petugas' => $data->namaPetugas,
        //                 ];
        //             },
        //         );

        //         if ($request->id_posyandu !== "0" && $request->id_posyandu !== null) {
        //             return (new FastExcel(
        //                 AllData::where('idKec', $request->id_kec)
        //                     ->where('idPuskes', $request->id_puskesmas)
        //                     ->where('idPos', $request->id_posyandu)->get()
        //             ))->download(
        //                 'all-data-anak.xlsx',
        //                 function ($data) {
        //                     return [
        //                         'No KK' => $data->no_kk,
        //                         'NIK' => $data->nik,
        //                         'Nama' => $data->nama,
        //                         'Nik Orang Tua' => $data->nik_ortu,
        //                         'Nama Ibu' => $data->nama_ibu,
        //                         'Nama Ayah' => $data->nama_ayah,
        //                         'Jenis Kelamin' => $data->jk,
        //                         'Tempat Lahir' => $data->tempat_lahir,
        //                         'Tanggal Lahir' => $data->tgl_lahir,
        //                         'Golongan Darah' => $data->golda,
        //                         'Anak Ke-' => $data->anak,
        //                         'Catatan' => $data->catatan,
        //                         'hbo' => $data->hbo,
        //                         'bcg' => $data->bcg,
        //                         'polio1' => $data->polio1,
        //                         'dpthb_hib1' => $data->dpthb_hib1,
        //                         'polio2' => $data->polio2,
        //                         'dpthb_hib2' => $data->dpthb_hib2,
        //                         'polio3' => $data->polio3,
        //                         'dpthb_hib3' => $data->dpthb_hib3,
        //                         'polio4' => $data->polio4,
        //                         'campak' => $data->campak,
        //                         'Kecamatan' => $data->nameKec,
        //                         'Kelurahan' => $data->nameKel,
        //                         'Puskesmas' => $data->namePuskes,
        //                         'Posyandu' => $data->namePos,
        //                         'RT' => $data->nameRt,
        //                         'Bulan' => $data->bln,
        //                         'Posisi' => $data->posisi,
        //                         'Tinggi Badan' => $data->tb,
        //                         'Berat Badan' => $data->bb,
        //                         'BMI' => ($data->tb > 0) ? round(10000 * $data->bb / pow($data->tb, 2), 2) : null,
        //                         'Lingkar Lengan Atas' => $data->lla,
        //                         'Lingkar Kepala' => $data->lk,
        //                         'NTOB' => $data->ntob,
        //                         'ASI' => $data->asi,
        //                         'Vitamin A' => $data->vit_a,
        //                         'Nama Petugas' => $data->namaPetugas,
        //                     ];
        //                 },
        //             );
        //         }
        //     } elseif ($request->id_kelurahan !== "0" && $request->id_kelurahan !== null) {
        //         return (new FastExcel(AllData::where('idKec', $request->id_kec)
        //             ->where('idKel', $request->id_kel)
        //             ->get()))->download(
        //             'all-data-anak.xlsx',
        //             function ($data) {
        //                 return [
        //                     'No KK' => $data->no_kk,
        //                     'NIK' => $data->nik,
        //                     'Nama' => $data->nama,
        //                     'Nik Orang Tua' => $data->nik_ortu,
        //                     'Nama Ibu' => $data->nama_ibu,
        //                     'Nama Ayah' => $data->nama_ayah,
        //                     'Jenis Kelamin' => $data->jk,
        //                     'Tempat Lahir' => $data->tempat_lahir,
        //                     'Tanggal Lahir' => $data->tgl_lahir,
        //                     'Golongan Darah' => $data->golda,
        //                     'Anak Ke-' => $data->anak,
        //                     'Catatan' => $data->catatan,
        //                     'hbo' => $data->hbo,
        //                     'bcg' => $data->bcg,
        //                     'polio1' => $data->polio1,
        //                     'dpthb_hib1' => $data->dpthb_hib1,
        //                     'polio2' => $data->polio2,
        //                     'dpthb_hib2' => $data->dpthb_hib2,
        //                     'polio3' => $data->polio3,
        //                     'dpthb_hib3' => $data->dpthb_hib3,
        //                     'polio4' => $data->polio4,
        //                     'campak' => $data->campak,
        //                     'Kecamatan' => $data->nameKec,
        //                     'Kelurahan' => $data->nameKel,
        //                     'Puskesmas' => $data->namePuskes,
        //                     'Posyandu' => $data->namePos,
        //                     'RT' => $data->nameRt,
        //                     'Bulan' => $data->bln,
        //                     'Posisi' => $data->posisi,
        //                     'Tinggi Badan' => $data->tb,
        //                     'Berat Badan' => $data->bb,
        //                     'BMI' => ($data->tb > 0) ? round(10000 * $data->bb / pow($data->tb, 2), 2) : null,
        //                     'Lingkar Lengan Atas' => $data->lla,
        //                     'Lingkar Kepala' => $data->lk,
        //                     'NTOB' => $data->ntob,
        //                     'ASI' => $data->asi,
        //                     'Vitamin A' => $data->vit_a,
        //                     'Nama Petugas' => $data->namaPetugas,
        //                 ];
        //             },
        //         );

        //         if ($request->id_rt !== "0" && $request->id_rt !== null) {
        //             return (new FastExcel(
        //                 AllData::where('idKec', $request->id_kec)
        //                     ->where('idKel', $request->id_kel)
        //                     ->where('idRt', $request->id_rt)->get()
        //             ))->download(
        //                 'all-data-anak.xlsx',
        //                 function ($data) {
        //                     return [
        //                         'No KK' => $data->no_kk,
        //                         'NIK' => $data->nik,
        //                         'Nama' => $data->nama,
        //                         'Nik Orang Tua' => $data->nik_ortu,
        //                         'Nama Ibu' => $data->nama_ibu,
        //                         'Nama Ayah' => $data->nama_ayah,
        //                         'Jenis Kelamin' => $data->jk,
        //                         'Tempat Lahir' => $data->tempat_lahir,
        //                         'Tanggal Lahir' => $data->tgl_lahir,
        //                         'Golongan Darah' => $data->golda,
        //                         'Anak Ke-' => $data->anak,
        //                         'Catatan' => $data->catatan,
        //                         'hbo' => $data->hbo,
        //                         'bcg' => $data->bcg,
        //                         'polio1' => $data->polio1,
        //                         'dpthb_hib1' => $data->dpthb_hib1,
        //                         'polio2' => $data->polio2,
        //                         'dpthb_hib2' => $data->dpthb_hib2,
        //                         'polio3' => $data->polio3,
        //                         'dpthb_hib3' => $data->dpthb_hib3,
        //                         'polio4' => $data->polio4,
        //                         'campak' => $data->campak,
        //                         'Kecamatan' => $data->nameKec,
        //                         'Kelurahan' => $data->nameKel,
        //                         'Puskesmas' => $data->namePuskes,
        //                         'Posyandu' => $data->namePos,
        //                         'RT' => $data->nameRt,
        //                         'Bulan' => $data->bln,
        //                         'Posisi' => $data->posisi,
        //                         'Tinggi Badan' => $data->tb,
        //                         'Berat Badan' => $data->bb,
        //                         'BMI' => ($data->tb > 0) ? round(10000 * $data->bb / pow($data->tb, 2), 2) : null,
        //                         'Lingkar Lengan Atas' => $data->lla,
        //                         'Lingkar Kepala' => $data->lk,
        //                         'NTOB' => $data->ntob,
        //                         'ASI' => $data->asi,
        //                         'Vitamin A' => $data->vit_a,
        //                         'Nama Petugas' => $data->namaPetugas,
        //                     ];
        //                 },
        //             );
        //         }
        //     } else {
        //         return (new FastExcel(AllData::where('idKec', $request->id_kec)->get()))->download(
        //             'all-data-anak.xlsx',
        //             function ($data) {
        //                 return [
        //                     'No KK' => $data->no_kk,
        //                     'NIK' => $data->nik,
        //                     'Nama' => $data->nama,
        //                     'Nik Orang Tua' => $data->nik_ortu,
        //                     'Nama Ibu' => $data->nama_ibu,
        //                     'Nama Ayah' => $data->nama_ayah,
        //                     'Jenis Kelamin' => $data->jk,
        //                     'Tempat Lahir' => $data->tempat_lahir,
        //                     'Tanggal Lahir' => $data->tgl_lahir,
        //                     'Golongan Darah' => $data->golda,
        //                     'Anak Ke-' => $data->anak,
        //                     'Catatan' => $data->catatan,
        //                     'hbo' => $data->hbo,
        //                     'bcg' => $data->bcg,
        //                     'polio1' => $data->polio1,
        //                     'dpthb_hib1' => $data->dpthb_hib1,
        //                     'polio2' => $data->polio2,
        //                     'dpthb_hib2' => $data->dpthb_hib2,
        //                     'polio3' => $data->polio3,
        //                     'dpthb_hib3' => $data->dpthb_hib3,
        //                     'polio4' => $data->polio4,
        //                     'campak' => $data->campak,
        //                     'Kecamatan' => $data->nameKec,
        //                     'Kelurahan' => $data->nameKel,
        //                     'Puskesmas' => $data->namePuskes,
        //                     'Posyandu' => $data->namePos,
        //                     'RT' => $data->nameRt,
        //                     'Bulan' => $data->bln,
        //                     'Posisi' => $data->posisi,
        //                     'Tinggi Badan' => $data->tb,
        //                     'Berat Badan' => $data->bb,
        //                     'BMI' => ($data->tb > 0) ? round(10000 * $data->bb / pow($data->tb, 2), 2) : null,
        //                     'Lingkar Lengan Atas' => $data->lla,
        //                     'Lingkar Kepala' => $data->lk,
        //                     'NTOB' => $data->ntob,
        //                     'ASI' => $data->asi,
        //                     'Vitamin A' => $data->vit_a,
        //                     'Nama Petugas' => $data->namaPetugas,
        //                 ];
        //             },
        //         );
        //     }
        // }
    }

    public function exportAllExcel()
    {
        //return Excel::download(new AllExport, 'all-data-anak.xlsx');
        // $datax= AllData::all();
        return (new FastExcel(AllData::all()))->download(
            'all-data-anak.xlsx',
            function ($data) {
                return [
                    'No KK' => $data->no_kk,
                    'NIK' => $data->nik,
                    'Nama' => $data->nama,
                    'Nik Orang Tua' => $data->nik_ortu,
                    'Nama Ibu' => $data->nama_ibu,
                    'Nama Ayah' => $data->nama_ayah,
                    'Jenis Kelamin' => $data->jk,
                    'Tempat Lahir' => $data->tempat_lahir,
                    'Tanggal Lahir' => $data->tgl_lahir,
                    'Golongan Darah' => $data->golda,
                    'Anak Ke-' => $data->anak,
                    'Catatan' => $data->catatan,
                    'Kecamatan' => $data->nameKec,
                    'Kelurahan' => $data->nameKel,
                    'Puskesmas' => $data->namePuskes,
                    'Posyandu' => $data->namePos,
                    'RT' => $data->nameRt,
                    'Tanggal Kunjungan' => $data->tgl_kunjungan,
                    'Bulan' => $data->bln,
                    'Posisi' => $data->posisi,
                    'Tinggi Badan' => $data->tb,
                    'Berat Badan' => $data->bb,
                    'BMI' => ($data->tb > 0) ? round(10000 * $data->bb / pow($data->tb, 2), 2) : null,
                    // 'BB/U' => z_score($data->bb, $data->tb, $data->bln, $data->posisi, $data->jk),
                    'Lingkar Lengan Atas' => $data->lla,
                    'Lingkar Kepala' => $data->lk,
                    'NTOB' => $data->ntob,
                    'ASI' => $data->asi,
                    'Vitamin A' => $data->vit_a,
                    'Nama Petugas' => $data->namaPetugas,
                ];
            },
        );
    }

    /*------------------------------------------
--------------------------------------------
IBU
--------------------------------------------
--------------------------------------------*/
    public function ibu()
    {
        return view('admin.ibu.index');
    }

    public function ibuHamil()
    {
        return view('admin.ibu_hamil.index');
    }
    /*------------------------------------------
--------------------------------------------
All Super Admin Controller
--------------------------------------------
--------------------------------------------*/
    public function superAdminHome()
    {
        return view('admin.dashboard.super_admin', [
            'quicklinks' => $this->berandaQuicklinks(auth()->user()),
        ]);
    }
    /*------------------------------------------
--------------------------------------------
All Admin Controller
--------------------------------------------
--------------------------------------------*/
    public function adminHome()
    {
        return view('admin.dashboard.admin', [
            'quicklinks' => $this->berandaQuicklinks(auth()->user()),
        ]);
    }

    /**
     * Token role beranda untuk menyaring quicklink (Paket F).
     */
    private function berandaRole(\App\Models\User $user): string
    {
        if ($user->isSuperAdmin())       return 'superadmin';
        if ($user->isFaskesSurveilans()) return 'surveilans';
        return 'imunisasi'; // legacy admin / imunisasi faskes
    }

    /**
     * Daftar quicklink beranda yang tersedia bagi role user, ditandai mana
     * yang dipilih tampil. NULL preferensi = semua tampil (default).
     *
     * @return list<array{key:string,label:string,icon:string,url:string,selected:bool}>
     */
    private function berandaQuicklinks(\App\Models\User $user): array
    {
        $role     = $this->berandaRole($user);
        $selected = $user->beranda_quicklinks; // array<string>|null

        $out = [];
        foreach (config('beranda.quicklinks', []) as $ql) {
            if (!in_array($role, $ql['roles'], true)) continue;
            $out[] = [
                'key'      => $ql['key'],
                'label'    => $ql['label'],
                'icon'     => $ql['icon'],
                'url'      => route($ql['route']),
                'selected' => $selected === null ? true : in_array($ql['key'], $selected, true),
            ];
        }

        return $out;
    }

    /**
     * Simpan preferensi quicklink beranda user (Paket F). Hanya menerima key
     * yang valid bagi role user (cegah tamper).
     */
    public function updateQuicklinks(Request $request)
    {
        $user      = auth()->user();
        $role      = $this->berandaRole($user);
        $tersedia  = collect(config('beranda.quicklinks', []))
            ->filter(fn ($q) => in_array($role, $q['roles'], true))
            ->pluck('key')
            ->all();

        $dipilih = collect($request->input('keys', []))
            ->filter(fn ($k) => in_array($k, $tersedia, true))
            ->values()
            ->all();

        $user->beranda_quicklinks = $dipilih;
        $user->save();

        return response()->json(['status' => 'ok', 'keys' => $dipilih]);
    }

    /**
     * Analytics Dashboard - Comprehensive health data visualization (Optimized for massive data)
     */
    public function analyticsDashboard()
    {
        // Dropdown data for filters
        $kelurahanList = Kelurahan::orderBy('name')->get();
        $vaksinList = JenisVaksin::aktif()->orderBy('nama')->get();

        // Use caching for expensive queries (5 minutes cache)
        $cacheTime = 300;

        // Total Statistics (fast queries)
        $totalAnak = Anak::count();
        $totalDataAnak = DB::table('data_anak')->count();
        $totalImunisasi = DB::table('imunisasi')->count();

        // Gender Distribution
        $genderDistribution = DB::table('anak')
            ->select('jk', DB::raw('count(*) as total'))
            ->groupBy('jk')
            ->get()
            ->mapWithKeys(fn($item) => [$item->jk == 1 ? 'Laki-laki' : 'Perempuan' => $item->total]);

        // Age Distribution (in years)
        $ageDistribution = DB::table('anak')
            ->selectRaw('FLOOR(DATEDIFF(CURDATE(), tgl_lahir) / 365) as age_year, count(*) as total')
            ->whereRaw('DATEDIFF(CURDATE(), tgl_lahir) / 365 < 10')
            ->groupBy('age_year')
            ->orderBy('age_year')
            ->get();

        // Geographic Distribution (by Kecamatan)
        $kecamatanDistribution = DB::table('anak')
            ->join('kecamatan', 'anak.id_kec', '=', 'kecamatan.id')
            ->select('kecamatan.name', DB::raw('count(*) as total'))
            ->groupBy('kecamatan.name')
            ->get();

        // Kelurahan Distribution
        $kelurahanDistribution = DB::table('anak')
            ->join('kelurahan', 'anak.id_kel', '=', 'kelurahan.id')
            ->select('kelurahan.name', DB::raw('count(*) as total'))
            ->groupBy('kelurahan.name')
            ->orderByDesc('total')
            ->get();

        // Posyandu Distribution (Top 15)
        $posyanduDistribution = DB::table('anak')
            ->join('posyandu', 'anak.id_posyandu', '=', 'posyandu.id')
            ->select('posyandu.name', DB::raw('count(*) as total'))
            ->groupBy('posyandu.name')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        // Immunization Status
        $imunisasiStatus = DB::table('imunisasi')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn($item) => [$item->status => $item->total]);

        // Vaccine Coverage
        $vaccineCoverage = DB::table('imunisasi')
            ->join('jenis_vaksin', 'imunisasi.id_jenis_vaksin', '=', 'jenis_vaksin.id')
            ->select('jenis_vaksin.kode', 'jenis_vaksin.nama', DB::raw('count(*) as total'))
            ->where('imunisasi.status', 'sudah')
            ->groupBy('jenis_vaksin.kode', 'jenis_vaksin.nama')
            ->orderBy('jenis_vaksin.id')
            ->get();

        // Breastfeeding (ASI) Status - Only from latest visits
        $asiStatus = DB::table('data_anak')
            ->select('asi', DB::raw('count(DISTINCT id_anak) as total'))
            ->whereIn('id', function($query) {
                $query->selectRaw('MAX(id)')
                    ->from('data_anak')
                    ->groupBy('id_anak');
            })
            ->groupBy('asi')
            ->get()
            ->mapWithKeys(fn($item) => [
                $item->asi == 1 ? 'ASI Eksklusif' : ($item->asi == 0 ? 'Tidak ASI Eksklusif' : 'Tidak Diketahui') => $item->total
            ]);

        // Growth Trend (Average by Month) - Limit to 0-60 months
        $growthTrend = DB::table('data_anak')
            ->select('bln', DB::raw('AVG(bb) as avg_bb'), DB::raw('AVG(tb) as avg_tb'), DB::raw('COUNT(*) as count'))
            ->whereBetween('bln', [0, 60])
            ->groupBy('bln')
            ->orderBy('bln')
            ->get();

        // Z-Score Status Analysis (Optimized with batch processing)
        $zScoreAnalysis = $this->calculateZScoreAnalysisOptimized();

        // Monthly Visit Trend (Last 12 months)
        $visitTrend = DB::table('data_anak')
            ->selectRaw("DATE_FORMAT(tgl_kunjungan, '%Y-%m') as month, COUNT(*) as total")
            ->whereRaw("tgl_kunjungan >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Incomplete Immunization Count (optimized - just count)
        $incompleteImunisasiCount = $this->getIncompleteImunisasiCount();

        // Recent Activities (Last 20 visits)
        $recentActivities = DB::table('data_anak')
            ->join('anak', 'data_anak.id_anak', '=', 'anak.id')
            ->select('anak.nama', 'data_anak.tgl_kunjungan', 'data_anak.bb', 'data_anak.tb', 'data_anak.bln')
            ->orderByDesc('data_anak.tgl_kunjungan')
            ->limit(20)
            ->get();

        // RT Distribution
        $rtDistribution = DB::table('anak')
            ->join('rt', 'anak.id_rt', '=', 'rt.id')
            ->select('rt.name', DB::raw('count(*) as total'))
            ->groupBy('rt.name')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        return view('admin.dashboard.analytics', compact(
            'totalAnak',
            'totalDataAnak',
            'totalImunisasi',
            'genderDistribution',
            'ageDistribution',
            'kecamatanDistribution',
            'kelurahanDistribution',
            'posyanduDistribution',
            'imunisasiStatus',
            'vaccineCoverage',
            'asiStatus',
            'growthTrend',
            'zScoreAnalysis',
            'visitTrend',
            'incompleteImunisasiCount',
            'recentActivities',
            'rtDistribution',
            'kelurahanList',
            'vaksinList'
        ));
    }

    /**
     * AJAX endpoint: return filtered analytics data for all dashboard charts
     */
    public function analyticsFilterImunisasi(Request $request)
    {
        $bulan = $request->input('bulan');
        $filterKel = $request->input('kelurahan');
        $antigen = $request->input('antigen');
        $status = $request->input('status');

        // --- Anak filter (kelurahan) ---
        $applyAnakFilter = function($query, $table = 'anak') use ($filterKel) {
            if ($filterKel) $query->where("$table.id_kel", $filterKel);
            return $query;
        };

        // --- Imunisasi filter (all 4 filters) ---
        $applyImunisasiFilter = function($query) use ($bulan, $filterKel, $antigen, $status) {
            if ($bulan) {
                $parts = explode('-', $bulan);
                $query->whereYear('imunisasi.tanggal_pemberian', $parts[0])
                      ->whereMonth('imunisasi.tanggal_pemberian', $parts[1]);
            }
            if ($filterKel) {
                $query->whereExists(function($sub) use ($filterKel) {
                    $sub->select(DB::raw(1))
                        ->from('anak')
                        ->whereColumn('anak.id', 'imunisasi.id_anak')
                        ->where('anak.id_kel', $filterKel);
                });
            }
            if ($antigen) $query->where('imunisasi.id_jenis_vaksin', $antigen);
            if ($status) $query->where('imunisasi.status', $status);
            return $query;
        };

        // --- data_anak filter (kelurahan via anak join) ---
        $applyDataAnakFilter = function($query) use ($filterKel) {
            if ($filterKel) {
                $query->whereExists(function($sub) use ($filterKel) {
                    $sub->select(DB::raw(1))
                        ->from('anak')
                        ->whereColumn('anak.id', 'data_anak.id_anak')
                        ->where('anak.id_kel', $filterKel);
                });
            }
            return $query;
        };

        // ========== Stat Cards ==========
        $anakQuery = Anak::query();
        $applyAnakFilter($anakQuery);
        $totalAnak = $anakQuery->count();

        $dataAnakQuery = DB::table('data_anak');
        $applyDataAnakFilter($dataAnakQuery);
        $totalDataAnak = $dataAnakQuery->count();

        $imunisasiCountQuery = DB::table('imunisasi');
        $applyImunisasiFilter($imunisasiCountQuery);
        $totalImunisasi = $imunisasiCountQuery->count();

        // ========== Gender Distribution ==========
        $genderQuery = DB::table('anak')->select('jk', DB::raw('count(*) as total'));
        $applyAnakFilter($genderQuery);
        $genderDistribution = $genderQuery->groupBy('jk')->get()
            ->mapWithKeys(fn($item) => [$item->jk == 1 ? 'Laki-laki' : 'Perempuan' => $item->total]);

        // ========== Age Distribution ==========
        $ageQuery = DB::table('anak')
            ->selectRaw('FLOOR(DATEDIFF(CURDATE(), tgl_lahir) / 365) as age_year, count(*) as total')
            ->whereRaw('DATEDIFF(CURDATE(), tgl_lahir) / 365 < 10');
        $applyAnakFilter($ageQuery);
        $ageDistribution = $ageQuery->groupBy('age_year')->orderBy('age_year')->get();

        // ========== Kecamatan Distribution ==========
        $kecQuery = DB::table('anak')
            ->join('kecamatan', 'anak.id_kec', '=', 'kecamatan.id')
            ->select('kecamatan.name', DB::raw('count(*) as total'));
        $applyAnakFilter($kecQuery);
        $kecamatanDistribution = $kecQuery->groupBy('kecamatan.name')->get();

        // ========== Kelurahan Distribution ==========
        $kelQuery = DB::table('anak')
            ->join('kelurahan', 'anak.id_kel', '=', 'kelurahan.id')
            ->select('kelurahan.name', DB::raw('count(*) as total'));
        $applyAnakFilter($kelQuery);
        $kelurahanDistribution = $kelQuery->groupBy('kelurahan.name')->orderByDesc('total')->get();

        // ========== Posyandu Distribution ==========
        $posyanduQuery = DB::table('anak')
            ->join('posyandu', 'anak.id_posyandu', '=', 'posyandu.id')
            ->select('posyandu.name', DB::raw('count(*) as total'));
        $applyAnakFilter($posyanduQuery);
        $posyanduDistribution = $posyanduQuery->groupBy('posyandu.name')->orderByDesc('total')->limit(15)->get();

        // ========== Imunisasi Status ==========
        $statusQuery = DB::table('imunisasi')->select('status', DB::raw('count(*) as total'));
        $applyImunisasiFilter($statusQuery);
        $imunisasiStatus = $statusQuery->groupBy('status')->get()
            ->mapWithKeys(fn($item) => [$item->status => $item->total]);

        // ========== Vaccine Coverage ==========
        $coverageQuery = DB::table('imunisasi')
            ->join('jenis_vaksin', 'imunisasi.id_jenis_vaksin', '=', 'jenis_vaksin.id')
            ->select('jenis_vaksin.kode', 'jenis_vaksin.nama', DB::raw('count(*) as total'));
        if (!$status) $coverageQuery->where('imunisasi.status', 'sudah');
        $applyImunisasiFilter($coverageQuery);
        $vaccineCoverage = $coverageQuery->groupBy('jenis_vaksin.kode', 'jenis_vaksin.nama')
            ->orderBy('jenis_vaksin.id')->get();

        // ========== ASI Status ==========
        $asiSubQuery = DB::table('data_anak')->selectRaw('MAX(id)')->groupBy('id_anak');
        if ($filterKel) {
            $asiSubQuery->whereExists(function($sub) use ($filterKel) {
                $sub->select(DB::raw(1))->from('anak')
                    ->whereColumn('anak.id', 'data_anak.id_anak')
                    ->where('anak.id_kel', $filterKel);
            });
        }
        $asiStatus = DB::table('data_anak')
            ->select('asi', DB::raw('count(DISTINCT id_anak) as total'))
            ->whereIn('id', $asiSubQuery)
            ->groupBy('asi')->get()
            ->mapWithKeys(fn($item) => [
                $item->asi == 1 ? 'ASI Eksklusif' : ($item->asi == 0 ? 'Tidak ASI Eksklusif' : 'Tidak Diketahui') => $item->total
            ]);

        // ========== Growth Trend ==========
        $growthQuery = DB::table('data_anak')
            ->select('bln', DB::raw('AVG(bb) as avg_bb'), DB::raw('AVG(tb) as avg_tb'), DB::raw('COUNT(*) as count'))
            ->whereBetween('bln', [0, 60]);
        $applyDataAnakFilter($growthQuery);
        $growthTrend = $growthQuery->groupBy('bln')->orderBy('bln')->get();

        // ========== Visit Trend ==========
        $visitQuery = DB::table('data_anak')
            ->selectRaw("DATE_FORMAT(tgl_kunjungan, '%Y-%m') as month, COUNT(*) as total")
            ->whereRaw("tgl_kunjungan >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)");
        $applyDataAnakFilter($visitQuery);
        $visitTrend = $visitQuery->groupBy('month')->orderBy('month')->get();

        // ========== Z-Score (filtered by kelurahan) ==========
        $zScoreResults = [
            'imt_u' => ['normal' => 0, 'kurang' => 0, 'buruk' => 0, 'lebih' => 0, 'obesitas' => 0],
            'bb_u' => ['normal' => 0, 'kurang' => 0, 'sangat_kurang' => 0, 'lebih' => 0],
            'tb_u' => ['normal' => 0, 'pendek' => 0, 'sangat_pendek' => 0, 'tinggi' => 0],
        ];
        $measurementQuery = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->whereIn('da.id', function($q) use ($filterKel) {
                $sub = $q->selectRaw('MAX(id)')->from('data_anak')->groupBy('id_anak');
                if ($filterKel) {
                    $sub->whereExists(function($s) use ($filterKel) {
                        $s->select(DB::raw(1))->from('anak')
                            ->whereColumn('anak.id', 'data_anak.id_anak')
                            ->where('anak.id_kel', $filterKel);
                    });
                }
            })
            ->where('da.bln', '<=', 60)
            ->select('da.id_anak', 'da.bb', 'da.tb', 'da.bln', 'da.posisi', 'a.jk');
        if ($filterKel) $measurementQuery->where('a.id_kel', $filterKel);
        $latestMeasurements = $measurementQuery->get();

        // Pemetaan enum kanonik → bucket. IMT di sini hanya 5 pita (>+2SD = obesitas).
        $imtMap = [
            'severely_wasted' => 'buruk', 'wasted' => 'kurang', 'normal' => 'normal',
            'risiko_lebih' => 'lebih', 'overweight' => 'obesitas', 'obese' => 'obesitas',
        ];
        $bbMap = [
            'severely_underweight' => 'sangat_kurang', 'underweight' => 'kurang',
            'normal' => 'normal', 'lebih' => 'lebih',
        ];
        $tbMap = [
            'severely_stunted' => 'sangat_pendek', 'stunted' => 'pendek',
            'normal' => 'normal', 'tinggi' => 'tinggi',
        ];

        foreach ($latestMeasurements as $m) {
            $g = $this->statusGizi->klasifikasi($m->bb, $m->tb, $m->bln, $m->posisi, $m->jk);
            if ($g['enum']['imt_u'] !== null) $zScoreResults['imt_u'][$imtMap[$g['enum']['imt_u']]]++;
            if ($g['enum']['bb_u'] !== null)  $zScoreResults['bb_u'][$bbMap[$g['enum']['bb_u']]]++;
            if ($g['enum']['tb_u'] !== null)  $zScoreResults['tb_u'][$tbMap[$g['enum']['tb_u']]]++;
        }

        // ========== Recent Activities ==========
        $recentQuery = DB::table('data_anak')
            ->join('anak', 'data_anak.id_anak', '=', 'anak.id')
            ->select('anak.nama', 'data_anak.tgl_kunjungan', 'data_anak.bb', 'data_anak.tb', 'data_anak.bln')
            ->orderByDesc('data_anak.tgl_kunjungan')->limit(20);
        if ($filterKel) $recentQuery->where('anak.id_kel', $filterKel);
        $recentActivities = $recentQuery->get()->map(function($a) {
            $bmi = $a->tb > 0 ? round(10000 * $a->bb / pow($a->tb, 2), 1) : 0;
            return [
                'nama' => $a->nama,
                'tgl_kunjungan' => \Carbon\Carbon::parse($a->tgl_kunjungan)->format('d M Y'),
                'bln' => $a->bln,
                'bb' => $a->bb,
                'tb' => $a->tb,
                'bmi' => $bmi,
            ];
        });

        // ========== Incomplete Imunisasi ==========
        $completeQuery = DB::table('imunisasi')
            ->join('jenis_vaksin', 'imunisasi.id_jenis_vaksin', '=', 'jenis_vaksin.id')
            ->where('imunisasi.status', 'sudah')
            ->where('jenis_vaksin.kategori', 'Imunisasi Dasar')
            ->groupBy('imunisasi.id_anak')
            ->havingRaw('COUNT(DISTINCT jenis_vaksin.kode) >= 11')
            ->select('imunisasi.id_anak');
        if ($filterKel) {
            $completeQuery->whereExists(function($sub) use ($filterKel) {
                $sub->select(DB::raw(1))->from('anak')
                    ->whereColumn('anak.id', 'imunisasi.id_anak')
                    ->where('anak.id_kel', $filterKel);
            });
        }
        $completeCount = $completeQuery->get()->count();
        $incompleteImunisasiCount = $totalAnak - $completeCount;

        return response()->json([
            'totalAnak' => $totalAnak,
            'totalDataAnak' => $totalDataAnak,
            'totalImunisasi' => $totalImunisasi,
            'incompleteImunisasiCount' => $incompleteImunisasiCount,
            'genderDistribution' => ['labels' => $genderDistribution->keys(), 'data' => $genderDistribution->values()],
            'ageDistribution' => ['labels' => $ageDistribution->pluck('age_year')->map(fn($a) => $a . ' Tahun'), 'data' => $ageDistribution->pluck('total')],
            'kecamatanDistribution' => ['labels' => $kecamatanDistribution->pluck('name'), 'data' => $kecamatanDistribution->pluck('total')],
            'kelurahanDistribution' => ['labels' => $kelurahanDistribution->pluck('name'), 'data' => $kelurahanDistribution->pluck('total')],
            'posyanduDistribution' => ['labels' => $posyanduDistribution->pluck('name'), 'data' => $posyanduDistribution->pluck('total')],
            'imunisasiStatus' => ['labels' => $imunisasiStatus->keys()->map(fn($s) => ucfirst($s))->values(), 'data' => $imunisasiStatus->values(), 'raw' => $imunisasiStatus],
            'vaccineCoverage' => ['labels' => $vaccineCoverage->pluck('kode'), 'data' => $vaccineCoverage->pluck('total')],
            'asiStatus' => ['labels' => $asiStatus->keys(), 'data' => $asiStatus->values()],
            'growthTrend' => [
                'labels' => $growthTrend->pluck('bln')->map(fn($b) => 'Bln ' . $b),
                'avg_bb' => $growthTrend->pluck('avg_bb')->map(fn($v) => round($v, 2)),
                'avg_tb' => $growthTrend->pluck('avg_tb')->map(fn($v) => round($v, 2)),
            ],
            'visitTrend' => ['labels' => $visitTrend->pluck('month'), 'data' => $visitTrend->pluck('total')],
            'zScoreAnalysis' => $zScoreResults,
            'recentActivities' => $recentActivities,
        ]);
    }

    /**
     * Calculate Z-Score analysis using optimized batch processing
     */
    private function calculateZScoreAnalysisOptimized()
    {
        $results = [
            'imt_u' => ['normal' => 0, 'kurang' => 0, 'buruk' => 0, 'lebih' => 0, 'obesitas' => 0],
            'bb_u' => ['normal' => 0, 'kurang' => 0, 'sangat_kurang' => 0, 'lebih' => 0],
            'tb_u' => ['normal' => 0, 'pendek' => 0, 'sangat_pendek' => 0, 'tinggi' => 0],
        ];

        // Get latest measurement for each child using subquery
        $latestMeasurements = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->whereIn('da.id', function($query) {
                $query->selectRaw('MAX(id)')
                    ->from('data_anak')
                    ->groupBy('id_anak');
            })
            ->where('da.bln', '<=', 60)
            ->select('da.id_anak', 'da.bb', 'da.tb', 'da.bln', 'da.posisi', 'a.jk')
            ->get();

        // Pemetaan enum kanonik → bucket. IMT 5 pita (>+2SD = obesitas).
        $imtMap = [
            'severely_wasted' => 'buruk', 'wasted' => 'kurang', 'normal' => 'normal',
            'risiko_lebih' => 'lebih', 'overweight' => 'obesitas', 'obese' => 'obesitas',
        ];
        $bbMap = [
            'severely_underweight' => 'sangat_kurang', 'underweight' => 'kurang',
            'normal' => 'normal', 'lebih' => 'lebih',
        ];
        $tbMap = [
            'severely_stunted' => 'sangat_pendek', 'stunted' => 'pendek',
            'normal' => 'normal', 'tinggi' => 'tinggi',
        ];

        foreach ($latestMeasurements as $m) {
            $g = $this->statusGizi->klasifikasi($m->bb, $m->tb, $m->bln, $m->posisi, $m->jk);
            if ($g['enum']['imt_u'] !== null) $results['imt_u'][$imtMap[$g['enum']['imt_u']]]++;
            if ($g['enum']['bb_u'] !== null)  $results['bb_u'][$bbMap[$g['enum']['bb_u']]]++;
            if ($g['enum']['tb_u'] !== null)  $results['tb_u'][$tbMap[$g['enum']['tb_u']]]++;
        }

        return $results;
    }

    /**
     * Get count of children with incomplete basic immunization (optimized)
     */
    private function getIncompleteImunisasiCount()
    {
        $totalChildren = Anak::count();

        // Count children with complete basic immunization (11 vaccines)
        $completeCount = DB::table('imunisasi')
            ->join('jenis_vaksin', 'imunisasi.id_jenis_vaksin', '=', 'jenis_vaksin.id')
            ->where('imunisasi.status', 'sudah')
            ->where('jenis_vaksin.kategori', 'Imunisasi Dasar')
            ->groupBy('imunisasi.id_anak')
            ->havingRaw('COUNT(DISTINCT jenis_vaksin.kode) >= 11')
            ->select('imunisasi.id_anak')
            ->get()
            ->count();

        return $totalChildren - $completeCount;
    }

    /**
     * Geographic Map Dashboard with GeoJSON integration
     */
    public function mapDashboard()
    {
        // Get child counts per kelurahan
        $kelurahanStats = DB::table('anak')
            ->join('kelurahan', 'anak.id_kel', '=', 'kelurahan.id')
            ->select('kelurahan.name as kelurahan_name', DB::raw('count(*) as total_anak'))
            ->groupBy('kelurahan.name')
            ->get()
            ->keyBy('kelurahan_name');

        // Get child counts per kecamatan
        $kecamatanStats = DB::table('anak')
            ->join('kecamatan', 'anak.id_kec', '=', 'kecamatan.id')
            ->select('kecamatan.name as kecamatan_name', DB::raw('count(*) as total_anak'))
            ->groupBy('kecamatan.name')
            ->get()
            ->keyBy('kecamatan_name');

        // Get child counts per RT
        $rtStats = DB::table('anak')
            ->join('rt', 'anak.id_rt', '=', 'rt.id')
            ->select('rt.name as rt_name', DB::raw('count(*) as total_anak'))
            ->groupBy('rt.name')
            ->get()
            ->keyBy('rt_name');

        // Get Z-Score status per kelurahan (optimized)
        $kelurahanZScore = $this->getZScoreByKelurahanOptimized();

        // Get Z-Score status per RT (optimized)
        $rtZScore = $this->getZScoreByRTOptimized();

        // Get immunization coverage per kelurahan and RT
        $kelurahanImunisasi = $this->getImunisasiByKelurahanOptimized();
        $rtImunisasi = $this->getImunisasiByRTOptimized();

        // Get posyandu locations with stats
        $posyanduStats = DB::table('anak')
            ->join('posyandu', 'anak.id_posyandu', '=', 'posyandu.id')
            ->join('kelurahan', 'anak.id_kel', '=', 'kelurahan.id')
            ->select(
                'posyandu.name as posyandu_name',
                'kelurahan.name as kelurahan_name',
                DB::raw('count(*) as total_anak')
            )
            ->groupBy('posyandu.name', 'kelurahan.name')
            ->orderByDesc('total_anak')
            ->limit(30)
            ->get();

        // Summary stats
        $totalAnak = Anak::count();
        $totalKelurahan = DB::table('kelurahan')->count();
        $totalRT = DB::table('rt')->count();
        $kelurahanWithData = $kelurahanStats->count();
        $rtWithData = $rtStats->count();

        // Ringkasan stunting/wasting dari snapshot (BB/TB) agar konsisten dgn popup peta.
        $snap = DB::table('prioritas_gizi')->whereNotNull('usia_bln');
        $totalStunting = (clone $snap)->where('stunting', 1)->count();
        $totalWasting = (clone $snap)->where(function ($q) {
            $q->where('gizi_buruk', 1)->orWhere('gizi_kurang', 1);
        })->count();

        // Immunization summary
        $totalImunisasiLengkap = collect($kelurahanImunisasi)->sum('lengkap');
        $persenImunisasiLengkap = $totalAnak > 0 ? round(($totalImunisasiLengkap / $totalAnak) * 100, 1) : 0;

        // Agregasi prioritas per wilayah (dari snapshot) + ambang kuantil per indikator.
        $peta = app(PetaPrioritasService::class);
        $petaAgregat = [
            'kecamatan' => $peta->agregat('kecamatan'),
            'kelurahan' => $peta->agregat('kelurahan'),
            'rt'        => $peta->agregat('rt'),
        ];

        $indikatorNilai = [
            'stunting'  => 'stunting_pct',
            'gizi'      => 'gizi_kurang_buruk_pct',
            'bbtn'      => 'bb_tidak_naik_pct',
            'prioritas' => 'anak_prioritas',
        ];
        $petaKuantil = [];
        foreach ($petaAgregat as $level => $wilayah) {
            foreach ($indikatorNilai as $indikator => $kolom) {
                $nilai = array_map(fn ($w) => (float) $w[$kolom], array_values($wilayah));
                $petaKuantil[$level][$indikator] = Kuantil::ambangTertil($nilai);
            }
        }

        return view('admin.dashboard.map', compact(
            'kelurahanStats',
            'kecamatanStats',
            'rtStats',
            'kelurahanZScore',
            'rtZScore',
            'kelurahanImunisasi',
            'rtImunisasi',
            'posyanduStats',
            'totalAnak',
            'totalKelurahan',
            'totalRT',
            'kelurahanWithData',
            'rtWithData',
            'totalStunting',
            'totalWasting',
            'totalImunisasiLengkap',
            'persenImunisasiLengkap',
            'petaAgregat',
            'petaKuantil'
        ));
    }

    /**
     * API endpoint for map data
     */
    public function getMapData()
    {
        $kelurahanStats = DB::table('anak')
            ->join('kelurahan', 'anak.id_kel', '=', 'kelurahan.id')
            ->select('kelurahan.name as kelurahan_name', DB::raw('count(*) as total_anak'))
            ->groupBy('kelurahan.name')
            ->get()
            ->keyBy('kelurahan_name');

        $kecamatanStats = DB::table('anak')
            ->join('kecamatan', 'anak.id_kec', '=', 'kecamatan.id')
            ->select('kecamatan.name as kecamatan_name', DB::raw('count(*) as total_anak'))
            ->groupBy('kecamatan.name')
            ->get()
            ->keyBy('kecamatan_name');

        $zScoreStats = $this->getZScoreByKelurahan();

        return response()->json([
            'kelurahan' => $kelurahanStats,
            'kecamatan' => $kecamatanStats,
            'zscore' => $zScoreStats
        ]);
    }

    /**
     * Early Warning System Dashboard
     */
    public function earlyWarningSystem(Request $request)
    {
        $alerts = [];
        $priorityList = [];

        // Pagination parameters
        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);
        $filter = $request->get('filter', 'all');

        // Eager load all necessary relationships to avoid N+1 queries
        $children = Anak::with([
            'kec',
            'kel',
            'posyandu',
            'latestDataAnak',
            'imunisasi' => function($query) {
                $query->where('status', 'sudah')->with('jenisVaksin');
            }
        ])->get();

        // Klasifikasi status gizi via StatusGiziService (cache referensi internal).

        // Vaccine schedule reference (age in months)
        $vaccineSchedule = [
            'HB0' => ['min' => 0, 'max' => 0, 'desc' => 'Saat lahir'],
            'BCG' => ['min' => 0, 'max' => 1, 'desc' => '0-1 bulan'],
            'POLIO1' => ['min' => 0, 'max' => 1, 'desc' => '0-1 bulan'],
            'POLIO2' => ['min' => 2, 'max' => 3, 'desc' => '2-3 bulan'],
            'POLIO3' => ['min' => 3, 'max' => 4, 'desc' => '3-4 bulan'],
            'POLIO4' => ['min' => 4, 'max' => 5, 'desc' => '4-5 bulan'],
            'DPT-HB-HIB1' => ['min' => 2, 'max' => 3, 'desc' => '2-3 bulan'],
            'DPT-HB-HIB2' => ['min' => 3, 'max' => 4, 'desc' => '3-4 bulan'],
            'DPT-HB-HIB3' => ['min' => 4, 'max' => 5, 'desc' => '4-5 bulan'],
            'IPV' => ['min' => 4, 'max' => 5, 'desc' => '4-5 bulan'],
            'CAMPAK' => ['min' => 9, 'max' => 12, 'desc' => '9-12 bulan'],
        ];

        // Vaccine needs per location
        $vaccineNeedsByLocation = [];
        $vaccineNeedsByType = [];

        // Vaccine projection data structure - kebutuhan vaksin berdasarkan periode waktu
        $vaccineProjection = [
            '1_month' => [], // Kebutuhan 1 bulan ke depan
            '6_months' => [], // Kebutuhan 6 bulan ke depan
            '12_months' => [], // Kebutuhan 12 bulan ke depan
        ];

        foreach ($children as $child) {
            $childAlerts = [];
            $riskScore = 0;

            // Get latest measurement from eager loaded relationship
            $latest = $child->latestDataAnak;

            // Get location names from eager loaded relationships
            $kecamatan = $child->kec;
            $kelurahan = $child->kel;
            $posyandu = $child->posyandu;

            // Calculate age
            $usia = \Carbon\Carbon::parse($child->tgl_lahir)->diffInMonths(now());

            // Skip invalid data (age > 60 months or test data)
            if ($usia > 120) continue;

            $posyanduName = $posyandu ? $posyandu->name : '-';
            $kelurahanName = $kelurahan ? $kelurahan->name : '-';
            $kecamatanName = $kecamatan ? $kecamatan->name : '-';

            $childData = [
                'id' => $child->id,
                'hashid' => $child->hashid,
                'nama' => $child->nama,
                'usia_bulan' => $usia,
                'jk' => $child->jk == 1 ? 'Laki-laki' : 'Perempuan',
                'kecamatan' => $kecamatanName,
                'kelurahan' => $kelurahanName,
                'id_kel' => $kelurahan?->id,
                'posyandu' => $posyanduName,
                'alerts' => [],
                'risk_score' => 0,
                'risk_level' => 'normal',
                'last_visit' => null,
                'zscore_status' => []
            ];

            // Alert 1: No measurement data
            if (!$latest) {
                $childAlerts[] = [
                    'type' => 'danger',
                    'icon' => 'fa-exclamation-circle',
                    'message' => 'Belum pernah dilakukan pengukuran',
                    'category' => 'measurement'
                ];
                $riskScore += 30;
            } else {
                $childData['last_visit'] = $latest->tgl_kunjungan;
                $daysSinceVisit = \Carbon\Carbon::parse($latest->tgl_kunjungan)->diffInDays(now());

                // Alert 2: No recent measurement (> 2 months)
                if ($daysSinceVisit > 60) {
                    $childAlerts[] = [
                        'type' => 'warning',
                        'icon' => 'fa-calendar-times',
                        'message' => 'Tidak ada kunjungan ' . round($daysSinceVisit / 30) . ' bulan terakhir',
                        'category' => 'visit'
                    ];
                    $riskScore += 15;
                }

                // Calculate Z-Score status
                if ($latest->bln <= 60) {
                    $g = $this->statusGizi->klasifikasi($latest->bb, $latest->tb, $latest->bln, $latest->posisi, $child->jk);
                    $bmi = $g['bmi'];

                    // TB/U (stunting). Enum null bila referensi tak ada → tak diisi.
                    switch ($g['enum']['tb_u']) {
                        case 'severely_stunted':
                            $childAlerts[] = ['type' => 'danger', 'icon' => 'fa-child', 'message' => 'SANGAT PENDEK (Severely Stunted)', 'category' => 'stunting'];
                            $childData['zscore_status']['tb_u'] = 'severely_stunted';
                            $riskScore += 40;
                            break;
                        case 'stunted':
                            $childAlerts[] = ['type' => 'warning', 'icon' => 'fa-child', 'message' => 'Pendek (Stunted)', 'category' => 'stunting'];
                            $childData['zscore_status']['tb_u'] = 'stunted';
                            $riskScore += 25;
                            break;
                        case 'normal':
                        case 'tinggi':
                            $childData['zscore_status']['tb_u'] = 'normal';
                            break;
                    }

                    // IMT/U (wasting/overweight). risiko_lebih (+1..+2SD) = normal di sini.
                    switch ($g['enum']['imt_u']) {
                        case 'severely_wasted':
                            $childAlerts[] = ['type' => 'danger', 'icon' => 'fa-weight', 'message' => 'GIZI BURUK (Severely Wasted)', 'category' => 'wasting'];
                            $childData['zscore_status']['imt_u'] = 'severely_wasted';
                            $riskScore += 40;
                            break;
                        case 'wasted':
                            $childAlerts[] = ['type' => 'warning', 'icon' => 'fa-weight', 'message' => 'Gizi Kurang (Wasted)', 'category' => 'wasting'];
                            $childData['zscore_status']['imt_u'] = 'wasted';
                            $riskScore += 25;
                            break;
                        case 'obese':
                            $childAlerts[] = ['type' => 'warning', 'icon' => 'fa-hamburger', 'message' => 'Obesitas', 'category' => 'obesity'];
                            $childData['zscore_status']['imt_u'] = 'obese';
                            $riskScore += 20;
                            break;
                        case 'overweight':
                            $childAlerts[] = ['type' => 'info', 'icon' => 'fa-hamburger', 'message' => 'Gizi Lebih (Overweight)', 'category' => 'overweight'];
                            $childData['zscore_status']['imt_u'] = 'overweight';
                            $riskScore += 10;
                            break;
                        case 'risiko_lebih':
                        case 'normal':
                            $childData['zscore_status']['imt_u'] = 'normal';
                            break;
                    }

                    // BB/U (underweight). lebih (>+1SD) = normal di sini.
                    switch ($g['enum']['bb_u']) {
                        case 'severely_underweight':
                            $childAlerts[] = ['type' => 'danger', 'icon' => 'fa-balance-scale', 'message' => 'BB SANGAT KURANG (Severely Underweight)', 'category' => 'underweight'];
                            $childData['zscore_status']['bb_u'] = 'severely_underweight';
                            $riskScore += 35;
                            break;
                        case 'underweight':
                            $childAlerts[] = ['type' => 'warning', 'icon' => 'fa-balance-scale', 'message' => 'BB Kurang (Underweight)', 'category' => 'underweight'];
                            $childData['zscore_status']['bb_u'] = 'underweight';
                            $riskScore += 20;
                            break;
                        case 'normal':
                        case 'lebih':
                            $childData['zscore_status']['bb_u'] = 'normal';
                            break;
                    }

                    // Store measurement data
                    $childData['bb'] = $latest->bb;
                    $childData['tb'] = $latest->tb;
                    $childData['bmi'] = $bmi;
                }
            }

            // Alert 3: Incomplete immunization
            $requiredVaccines = ['HB0', 'BCG', 'POLIO1', 'POLIO2', 'POLIO3', 'POLIO4', 'DPT-HB-HIB1', 'DPT-HB-HIB2', 'DPT-HB-HIB3', 'IPV', 'CAMPAK'];
            // Use eager loaded imunisasi data instead of querying
            $received = $child->imunisasi->pluck('jenisVaksin.kode')->toArray();

            $missing = array_diff($requiredVaccines, $received);
            $childData['imunisasi_lengkap'] = count($requiredVaccines) - count($missing);
            $childData['imunisasi_missing'] = $missing;

            if (count($missing) > 0) {
                $urgency = count($missing) > 5 ? 'danger' : (count($missing) > 2 ? 'warning' : 'info');
                $childAlerts[] = [
                    'type' => $urgency,
                    'icon' => 'fa-syringe',
                    'message' => count($missing) . ' vaksin belum lengkap',
                    'category' => 'immunization'
                ];
                $riskScore += count($missing) * 3;

                // Track vaccine needs by location
                $locationKey = $posyanduName !== '-' ? $posyanduName : $kelurahanName;
                if (!isset($vaccineNeedsByLocation[$locationKey])) {
                    $vaccineNeedsByLocation[$locationKey] = [
                        'posyandu' => $posyanduName,
                        'kelurahan' => $kelurahanName,
                        'kecamatan' => $kecamatanName,
                        'vaccines' => [],
                        'total_children' => 0,
                        'children' => []
                    ];
                }
                $vaccineNeedsByLocation[$locationKey]['total_children']++;
                $vaccineNeedsByLocation[$locationKey]['children'][] = [
                    'nama' => $child->nama,
                    'usia' => $usia,
                    'missing' => $missing
                ];

                // Track by vaccine type
                foreach ($missing as $vaccine) {
                    if (!isset($vaccineNeedsByLocation[$locationKey]['vaccines'][$vaccine])) {
                        $vaccineNeedsByLocation[$locationKey]['vaccines'][$vaccine] = 0;
                    }
                    $vaccineNeedsByLocation[$locationKey]['vaccines'][$vaccine]++;

                    // Global vaccine needs
                    if (!isset($vaccineNeedsByType[$vaccine])) {
                        $vaccineNeedsByType[$vaccine] = [
                            'count' => 0,
                            'schedule' => $vaccineSchedule[$vaccine] ?? ['min' => 0, 'max' => 60, 'desc' => '-'],
                            'locations' => []
                        ];
                    }
                    $vaccineNeedsByType[$vaccine]['count']++;
                    if (!in_array($locationKey, $vaccineNeedsByType[$vaccine]['locations'])) {
                        $vaccineNeedsByType[$vaccine]['locations'][] = $locationKey;
                    }

                    // Track vaccine projection by time period
                    $schedule = $vaccineSchedule[$vaccine] ?? null;
                    if ($schedule) {
                        $minAge = $schedule['min'];
                        $maxAge = $schedule['max'];

                        // Determine projection periods (in months from now)
                        $periods = [
                            '1_month' => 1,
                            '6_months' => 6,
                            '12_months' => 12,
                        ];

                        foreach ($periods as $periodKey => $monthsAhead) {
                            $futureAge = $usia + $monthsAhead;

                            // Include vaccine if:
                            // 1. Child is already at or past minimum age (overdue/due now), OR
                            // 2. Child will reach minimum age within this period
                            $isNeeded = ($usia >= $minAge) || ($usia < $minAge && $futureAge >= $minAge);

                            if ($isNeeded) {
                                // Initialize vaccine entry if not exists
                                if (!isset($vaccineProjection[$periodKey][$vaccine])) {
                                    $vaccineProjection[$periodKey][$vaccine] = [
                                        'count' => 0,
                                        'posyandu' => [],
                                    ];
                                }
                                $vaccineProjection[$periodKey][$vaccine]['count']++;

                                // Track by posyandu
                                if (!isset($vaccineProjection[$periodKey][$vaccine]['posyandu'][$locationKey])) {
                                    $vaccineProjection[$periodKey][$vaccine]['posyandu'][$locationKey] = 0;
                                }
                                $vaccineProjection[$periodKey][$vaccine]['posyandu'][$locationKey]++;
                            }
                        }
                    }
                }
            }

            // Alert 4: Kejar vaksin status (catch-up immunization)
            $kejarStatus = $child->statusKejarVaksin();
            $childData['kejar_idl'] = $kejarStatus['kejar_idl'];
            $childData['kejar_ibl'] = $kejarStatus['kejar_ibl'];

            if ($kejarStatus['kejar_idl']) {
                $childAlerts[] = [
                    'type' => 'danger',
                    'icon' => 'fa-exclamation-triangle',
                    'message' => 'Kejar IDL - imunisasi dasar belum lengkap',
                    'category' => 'kejar_vaksin'
                ];
                $riskScore += 15;
            }

            if ($kejarStatus['kejar_ibl']) {
                $childAlerts[] = [
                    'type' => 'warning',
                    'icon' => 'fa-exclamation-triangle',
                    'message' => 'Kejar IBL - imunisasi booster belum lengkap',
                    'category' => 'kejar_vaksin'
                ];
                $riskScore += 10;
            }

            // Calculate risk level
            if ($riskScore >= 50) {
                $childData['risk_level'] = 'high';
            } elseif ($riskScore >= 25) {
                $childData['risk_level'] = 'medium';
            } elseif ($riskScore > 0) {
                $childData['risk_level'] = 'low';
            }

            $childData['alerts'] = $childAlerts;
            $childData['risk_score'] = $riskScore;

            // Add to priority list if has alerts
            if (count($childAlerts) > 0) {
                $priorityList[] = $childData;
            }
        }

        // Sort by risk score (highest first)
        usort($priorityList, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);

        // Apply filter
        $filteredList = $priorityList;
        if ($filter !== 'all') {
            $filteredList = array_filter($priorityList, function($c) use ($filter) {
                switch ($filter) {
                    case 'high': return $c['risk_level'] === 'high';
                    case 'medium': return $c['risk_level'] === 'medium';
                    case 'low': return $c['risk_level'] === 'low';
                    case 'stunting': return isset($c['zscore_status']['tb_u']) && in_array($c['zscore_status']['tb_u'], ['stunted', 'severely_stunted']);
                    case 'immunization': return count($c['imunisasi_missing']) > 0;
                    default: return true;
                }
            });
            $filteredList = array_values($filteredList);
        }

        // Generate summary statistics (before pagination)
        $summary = [
            'total_children' => count($children),
            'children_with_alerts' => count($priorityList),
            'high_risk' => count(array_filter($priorityList, fn($c) => $c['risk_level'] === 'high')),
            'medium_risk' => count(array_filter($priorityList, fn($c) => $c['risk_level'] === 'medium')),
            'low_risk' => count(array_filter($priorityList, fn($c) => $c['risk_level'] === 'low')),
            'stunting_cases' => count(array_filter($priorityList, fn($c) => isset($c['zscore_status']['tb_u']) && in_array($c['zscore_status']['tb_u'], ['stunted', 'severely_stunted']))),
            'wasting_cases' => count(array_filter($priorityList, fn($c) => isset($c['zscore_status']['imt_u']) && in_array($c['zscore_status']['imt_u'], ['wasted', 'severely_wasted']))),
            'no_measurement' => count(array_filter($priorityList, fn($c) => $c['last_visit'] === null)),
            'incomplete_immunization' => count(array_filter($priorityList, fn($c) => count($c['imunisasi_missing']) > 0)),
        ];

        // Paginate the filtered list
        $totalFiltered = count($filteredList);
        $totalPages = ceil($totalFiltered / $perPage);
        $page = min($page, $totalPages > 0 ? $totalPages : 1);
        $offset = ($page - 1) * $perPage;
        $paginatedList = array_slice($filteredList, $offset, $perPage);

        // Pagination data
        $pagination = [
            'current_page' => (int)$page,
            'per_page' => (int)$perPage,
            'total' => $totalFiltered,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
        ];

        // Sort vaccine needs by location (most needs first)
        uasort($vaccineNeedsByLocation, fn($a, $b) => $b['total_children'] <=> $a['total_children']);

        // Sort vaccine needs by type (most needed first)
        uasort($vaccineNeedsByType, fn($a, $b) => $b['count'] <=> $a['count']);

        // Sort vaccine projection by count (most needed first)
        foreach ($vaccineProjection as $period => &$vaccines) {
            uasort($vaccines, fn($a, $b) => $b['count'] <=> $a['count']);
        }

        $prioritasTiers = $this->buildPrioritasTiers();

        return view('admin.dashboard.early-warning', compact(
            'paginatedList',
            'priorityList',
            'summary',
            'pagination',
            'filter',
            'vaccineNeedsByLocation',
            'vaccineNeedsByType',
            'vaccineSchedule',
            'vaccineProjection',
            'prioritasTiers'
        ));
    }

    private function buildPrioritasTiers(): array
    {
        $prioritasRows = PrioritasGizi::query()
            ->whereNotNull('prioritas')
            ->join('anak', 'anak.id', '=', 'prioritas_gizi.id_anak')
            ->leftJoin('kelurahan', 'anak.id_kel', '=', 'kelurahan.id')
            ->leftJoin('rt', 'anak.id_rt', '=', 'rt.id')
            ->leftJoin('posyandu', 'anak.id_posyandu', '=', 'posyandu.id')
            ->select(
                'prioritas_gizi.prioritas',
                'prioritas_gizi.usia_bln',
                'anak.id as anak_id',
                'anak.nama',
                'anak.nik',
                'anak.id_kel',
                'kelurahan.name as kelurahan',
                'rt.name as rt',
                'posyandu.name as posyandu'
            )
            ->orderBy('anak.nama')
            ->get();

        $prioritasTiers = [1 => [], 2 => [], 3 => []];
        foreach ($prioritasRows as $r) {
            $prioritasTiers[(int) $r->prioritas][] = [
                'nama' => $r->nama,
                'nik' => $r->nik,
                'usia_bln' => $r->usia_bln,
                'posyandu' => $r->posyandu ?: '-',
                'puskesmas' => WilkerPuskesmas::wilkerForKelurahanId($r->id_kel ? (int) $r->id_kel : null) ?: '-',
                'kelurahan' => $r->kelurahan ?: '-',
                'rt' => $r->rt ?: '-',
                'hashid' => \App\Services\HashIdService::encode((int) $r->anak_id, 'anak'),
            ];
        }

        return $prioritasTiers;
    }

    public function exportPrioritasTier(Request $request)
    {
        $tier = (int) $request->query('tier', 1);
        $tiers = $this->buildPrioritasTiers();
        $rows = $tiers[$tier] ?? [];

        $judul = ['Prioritas 1 - Gizi Buruk', 'Prioritas 2 - Stunting', 'Prioritas 3 - BB Tidak Naik'][$tier - 1] ?? 'Prioritas';
        $file = 'prioritas-' . $tier . '-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new PrioritasTierExport($rows, $judul), $file);
    }

    public function exportVaccineNeeds()
    {
        $requiredVaccines = ['HB0', 'BCG', 'POLIO1', 'POLIO2', 'POLIO3', 'POLIO4', 'DPT-HB-HIB1', 'DPT-HB-HIB2', 'DPT-HB-HIB3', 'IPV', 'CAMPAK'];
        $rows = [];

        // Eager load all relationships to avoid N+1 queries
        $children = Anak::with([
            'kec',
            'kel',
            'posyandu',
            'imunisasi' => function($query) {
                $query->where('status', 'sudah')->with('jenisVaksin');
            }
        ])->get();

        foreach ($children as $child) {
            $usia = \Carbon\Carbon::parse($child->tgl_lahir)->diffInMonths(now());
            if ($usia > 120) {
                continue;
            }

            // Use eager loaded relationships instead of manual find()
            $posyanduName = $child->posyandu ? $child->posyandu->name : '-';
            $kelurahanName = $child->kel ? $child->kel->name : '-';
            $kecamatanName = $child->kec ? $child->kec->name : '-';

            // Use eager loaded imunisasi data
            $received = $child->imunisasi->pluck('jenisVaksin.kode')->toArray();

            $missing = array_values(array_diff($requiredVaccines, $received));
            if (count($missing) === 0) {
                continue;
            }

            $rows[] = [
                $child->nama,
                $usia,
                $child->jk == 1 ? 'Laki-laki' : 'Perempuan',
                $posyanduName,
                $kelurahanName,
                $kecamatanName,
                implode(', ', $missing),
                count($missing),
            ];
        }

        return Excel::download(new VaccineNeedsExport($rows), 'proyeksi-kebutuhan-vaksin.xlsx');
    }

    /**
     * Get Z-Score statistics grouped by Kelurahan
     */
    private function getZScoreByKelurahan()
    {
        $results = [];
        $kelurahanList = DB::table('kelurahan')->get();

        foreach ($kelurahanList as $kel) {
            // Eager load children with their latest data
            $children = Anak::where('id_kel', $kel->id)
                ->with('latestDataAnak')
                ->get();

            $stats = [
                'total' => 0,
                'normal' => 0,
                'stunting' => 0,
                'wasting' => 0,
                'overweight' => 0,
                'gizi_kurang' => 0,
                'gizi_buruk' => 0
            ];

            foreach ($children as $child) {
                $latest = $child->latestDataAnak;

                if (!$latest || $latest->bln > 60) continue;

                $stats['total']++;

                $g = $this->statusGizi->klasifikasi($latest->bb, $latest->tb, $latest->bln, $latest->posisi, $child->jk);
                $this->akumulasiStatistikGizi($stats, $g);
            }

            if ($stats['total'] > 0) {
                $results[$kel->name] = $stats;
            }
        }

        return $results;
    }

    /**
     * Get Z-Score statistics by Kelurahan (Optimized for massive data)
     */
    private function getZScoreByKelurahanOptimized()
    {
        $results = [];

        // Get latest measurements with kelurahan grouping
        $measurements = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->join('kelurahan as k', 'a.id_kel', '=', 'k.id')
            ->whereIn('da.id', function($query) {
                $query->selectRaw('MAX(id)')
                    ->from('data_anak')
                    ->groupBy('id_anak');
            })
            ->where('da.bln', '<=', 60)
            ->select('k.name as kelurahan', 'da.bb', 'da.tb', 'da.bln', 'da.posisi', 'a.jk')
            ->get()
            ->groupBy('kelurahan');

        foreach ($measurements as $kelName => $children) {
            $stats = ['total' => 0, 'normal' => 0, 'stunting' => 0, 'wasting' => 0, 'overweight' => 0, 'gizi_kurang' => 0, 'gizi_buruk' => 0];

            foreach ($children as $m) {
                $stats['total']++;
                $g = $this->statusGizi->klasifikasi($m->bb, $m->tb, $m->bln, $m->posisi, $m->jk);
                $this->akumulasiStatistikGizi($stats, $g);
            }

            if ($stats['total'] > 0) {
                $results[$kelName] = $stats;
            }
        }

        return $results;
    }

    /**
     * Akumulasi statistik gizi wilayah dari hasil StatusGiziService:
     * stunting (TB/U <-2SD), wasting (IMT/U <-2SD), overweight (>+2SD), sisanya normal.
     * Hanya menghitung bila indikatornya punya referensi (enum non-null).
     */
    private function akumulasiStatistikGizi(array &$stats, array $g): void
    {
        if (in_array($g['enum']['tb_u'], ['severely_stunted', 'stunted'], true)) {
            $stats['stunting']++;
        }
        switch ($g['enum']['imt_u']) {
            case 'severely_wasted':
                $stats['wasting']++;
                if (isset($stats['gizi_buruk'])) $stats['gizi_buruk']++;
                break;
            case 'wasted':
                $stats['wasting']++;
                if (isset($stats['gizi_kurang'])) $stats['gizi_kurang']++;
                break;
            case 'overweight':
            case 'obese':
                $stats['overweight']++;
                break;
            case 'normal':
            case 'risiko_lebih':
                $stats['normal']++;
                break;
        }
    }

    /**
     * Get Z-Score statistics by RT (Optimized for massive data)
     */
    private function getZScoreByRTOptimized()
    {
        $results = [];

        // Get latest measurements with RT grouping
        $measurements = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->join('rt as r', 'a.id_rt', '=', 'r.id')
            ->whereIn('da.id', function($query) {
                $query->selectRaw('MAX(id)')
                    ->from('data_anak')
                    ->groupBy('id_anak');
            })
            ->where('da.bln', '<=', 60)
            ->select('r.name as rt_name', 'da.bb', 'da.tb', 'da.bln', 'da.posisi', 'a.jk')
            ->get()
            ->groupBy('rt_name');

        foreach ($measurements as $rtName => $children) {
            $stats = ['total' => 0, 'normal' => 0, 'stunting' => 0, 'wasting' => 0, 'overweight' => 0, 'gizi_kurang' => 0, 'gizi_buruk' => 0];

            foreach ($children as $m) {
                $stats['total']++;
                $g = $this->statusGizi->klasifikasi($m->bb, $m->tb, $m->bln, $m->posisi, $m->jk);
                $this->akumulasiStatistikGizi($stats, $g);
            }

            if ($stats['total'] > 0) {
                $results[$rtName] = $stats;
            }
        }

        return $results;
    }

    /**
     * Get immunization coverage statistics by Kelurahan
     */
    private function getImunisasiByKelurahanOptimized()
    {
        $results = [];

        // Basic immunization vaccine codes (11 vaccines)
        $basicVaccineCodes = ['HB0', 'BCG', 'POLIO1', 'POLIO2', 'POLIO3', 'POLIO4',
                              'DPT-HB-HIB1', 'DPT-HB-HIB2', 'DPT-HB-HIB3', 'IPV', 'CAMPAK'];

        // Get children grouped by kelurahan with their completed basic immunizations
        $children = DB::table('anak as a')
            ->join('kelurahan as k', 'a.id_kel', '=', 'k.id')
            ->leftJoin('imunisasi as i', function ($join) {
                $join->on('a.id', '=', 'i.id_anak')
                     ->where('i.status', '=', 'sudah');
            })
            ->leftJoin('jenis_vaksin as jv', function ($join) {
                $join->on('i.id_jenis_vaksin', '=', 'jv.id')
                     ->where('jv.kategori', '=', 'Imunisasi Dasar');
            })
            ->select('k.name as kelurahan', 'a.id as anak_id', 'jv.kode as vaksin_kode')
            ->get()
            ->groupBy('kelurahan');

        foreach ($children as $kelName => $rows) {
            $anakVaccines = [];
            foreach ($rows as $row) {
                if (!isset($anakVaccines[$row->anak_id])) {
                    $anakVaccines[$row->anak_id] = [];
                }
                if ($row->vaksin_kode) {
                    $anakVaccines[$row->anak_id][$row->vaksin_kode] = true;
                }
            }

            $totalAnak = count($anakVaccines);
            $lengkap = 0;
            $perVaksin = array_fill_keys($basicVaccineCodes, 0);

            foreach ($anakVaccines as $anakId => $vaccines) {
                $completedBasic = 0;
                foreach ($basicVaccineCodes as $code) {
                    if (isset($vaccines[$code])) {
                        $perVaksin[$code]++;
                        $completedBasic++;
                    }
                }
                if ($completedBasic >= 11) {
                    $lengkap++;
                }
            }

            $results[$kelName] = [
                'total_anak' => $totalAnak,
                'lengkap' => $lengkap,
                'persen_lengkap' => $totalAnak > 0 ? round(($lengkap / $totalAnak) * 100, 1) : 0,
                'per_vaksin' => $perVaksin,
            ];
        }

        return $results;
    }

    /**
     * Get immunization coverage statistics by RT
     */
    private function getImunisasiByRTOptimized()
    {
        $results = [];

        $basicVaccineCodes = ['HB0', 'BCG', 'POLIO1', 'POLIO2', 'POLIO3', 'POLIO4',
                              'DPT-HB-HIB1', 'DPT-HB-HIB2', 'DPT-HB-HIB3', 'IPV', 'CAMPAK'];

        $children = DB::table('anak as a')
            ->join('rt as r', 'a.id_rt', '=', 'r.id')
            ->leftJoin('imunisasi as i', function ($join) {
                $join->on('a.id', '=', 'i.id_anak')
                     ->where('i.status', '=', 'sudah');
            })
            ->leftJoin('jenis_vaksin as jv', function ($join) {
                $join->on('i.id_jenis_vaksin', '=', 'jv.id')
                     ->where('jv.kategori', '=', 'Imunisasi Dasar');
            })
            ->select('r.name as rt_name', 'a.id as anak_id', 'jv.kode as vaksin_kode')
            ->get()
            ->groupBy('rt_name');

        foreach ($children as $rtName => $rows) {
            $anakVaccines = [];
            foreach ($rows as $row) {
                if (!isset($anakVaccines[$row->anak_id])) {
                    $anakVaccines[$row->anak_id] = [];
                }
                if ($row->vaksin_kode) {
                    $anakVaccines[$row->anak_id][$row->vaksin_kode] = true;
                }
            }

            $totalAnak = count($anakVaccines);
            $lengkap = 0;
            $perVaksin = array_fill_keys($basicVaccineCodes, 0);

            foreach ($anakVaccines as $anakId => $vaccines) {
                $completedBasic = 0;
                foreach ($basicVaccineCodes as $code) {
                    if (isset($vaccines[$code])) {
                        $perVaksin[$code]++;
                        $completedBasic++;
                    }
                }
                if ($completedBasic >= 11) {
                    $lengkap++;
                }
            }

            $results[$rtName] = [
                'total_anak' => $totalAnak,
                'lengkap' => $lengkap,
                'persen_lengkap' => $totalAnak > 0 ? round(($lengkap / $totalAnak) * 100, 1) : 0,
                'per_vaksin' => $perVaksin,
            ];
        }

        return $results;
    }

    /*------------------------------------------
--------------------------------------------
All User Controller
--------------------------------------------
--------------------------------------------*/
    public function user()
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403);
        $user = User::all();
        $kec = Kecamatan::all();
        $puskesmas = Puskesmas::orderBy('name')->get();
        $rs = RumahSakit::orderBy('name')->get();
        return view('admin.user.index', compact('user', 'kec', 'puskesmas', 'rs'));
    }

    public function storeUser(storeUserRequest $request)
    {
        try {
            $plainPassword = $this->userRepository->storeUser($request);
            Alert::success('User Dibuat', "Password awal: <strong>{$plainPassword}</strong><br>Simpan sebelum menutup halaman ini.")->html();
            return redirect()->route('super.admin.user');
        } catch (Throwable $e) {
            Alert::error('Gagal', 'User gagal dibuat: ' . $e->getMessage());
            return redirect()->route('super.admin.user');
        }
    }

    public function editUser($id)
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403);
        $user = User::findOrFail($id);
        $kec = Kecamatan::all();
        $puskesmas = Puskesmas::orderBy('name')->get();
        $rs = RumahSakit::orderBy('name')->get();
        return view('admin.user.edit', compact('user', 'kec', 'puskesmas', 'rs'));
    }

    public function updateUser(storeUserRequest $request, $id)
    {
        try {
            $this->userRepository->updateUser($request, $id);
            Alert::success('Berhasil', 'Data user berhasil diperbarui.');
            return redirect()->route('super.admin.user');
        } catch (Throwable $e) {
            Alert::error('Gagal', 'User gagal diperbarui: ' . $e->getMessage());
            return redirect()->route('super.admin.user');
        }
    }

    public function destroyUser($id)
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403);
        try {
            $this->userRepository->destroyUser($id);
            Alert::success('Berhasil', 'User berhasil dihapus.');
            return redirect()->route('super.admin.user');
        } catch (Throwable $e) {
            Alert::error('Gagal', 'User gagal dihapus: ' . $e->getMessage());
            return redirect()->route('super.admin.user');
        }
    }
}
