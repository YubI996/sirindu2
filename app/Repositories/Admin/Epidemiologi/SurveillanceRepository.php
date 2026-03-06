<?php

namespace App\Repositories\Admin\Epidemiologi;

use App\Repositories\Admin\Core\Epidemiologi\SurveillanceRepositoryInterface;
use App\Models\SurveillanceCase;
use App\Models\JenisKasusEpidemiologi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SurveillanceRepository implements SurveillanceRepositoryInterface
{
    protected $model;

    public function __construct(SurveillanceCase $model)
    {
        $this->model = $model;
    }

    /**
     * Boolean checkbox fields that need special handling (unchecked = not in request)
     */
    private const BOOLEAN_FIELDS = [
        'gejala_demam', 'gejala_batuk', 'gejala_pilek', 'gejala_sakit_kepala',
        'gejala_mual', 'gejala_muntah', 'gejala_diare', 'gejala_ruam',
        'gejala_sesak_napas', 'gejala_nyeri_otot', 'gejala_nyeri_sendi',
        'gejala_lemas', 'gejala_kehilangan_nafsu_makan', 'gejala_mata_merah',
        'gejala_pembengkakan_kelenjar', 'gejala_kejang', 'gejala_penurunan_kesadaran',
        'gejala_adenopathy', 'gejala_arthralgia', 'gejala_kehamilan',
        'komplikasi_diare', 'komplikasi_kebutaan', 'komplikasi_pneumonia',
        'komplikasi_malnutrisi', 'komplikasi_bronchopneumonia', 'komplikasi_otitis_media',
        'komplikasi_encephalitis', 'komplikasi_ulkus_mukosa_mulut',
        'riwayat_kontak_kasus',
    ];

    /**
     * Prepare validated data with boolean handling and auto-computed fields
     */
    private function prepareData($request)
    {
        $data = $request->validated();

        // Handle boolean checkbox fields: unchecked checkboxes are absent from request
        foreach (self::BOOLEAN_FIELDS as $field) {
            $data[$field] = $request->has($field) ? 1 : 0;
        }

        // Auto-calculate kategori_umur from tanggal_lahir
        if (!empty($data['tanggal_lahir'])) {
            $tanggalLahir = Carbon::parse($data['tanggal_lahir']);
            $data['kategori_umur'] = $this->getKategoriUmur($tanggalLahir->diffInYears(now()));
        }

        // Auto-populate reporter from authenticated user
        $user = Auth::user();
        $data['nama_pelapor'] = $user->name;
        $data['instansi_pelapor'] = optional($user->puskesmas)->name ?? optional($user->rs)->name ?? $data['instansi_pelapor'] ?? null;
        $data['telepon_pelapor'] = $data['telepon_pelapor'] ?? null;

        return $data;
    }

    /**
     * Store a new surveillance case
     */
    public function storeCase($request)
    {
        return DB::transaction(function () use ($request) {
            $data = $this->prepareData($request);

            // Defaults for new cases
            $data['tanggal_lapor'] = $data['tanggal_lapor'] ?? now()->toDateString();
            $data['sumber_penularan'] = $data['sumber_penularan'] ?? 'unknown';
            $data['riwayat_imunisasi'] = $data['riwayat_imunisasi'] ?? 'tidak_tahu';
            $data['status_lab'] = $data['status_lab'] ?? 'belum_diperiksa';
            $data['kondisi_akhir'] = $data['kondisi_akhir'] ?? 'dalam_perawatan';
            $data['status_kasus'] = $data['status_kasus'] ?? 'suspected';

            // System fields
            $data['id_petugas_input'] = Auth::id();
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();
            $data['faskes_type'] = Auth::user()->faskes_type;
            $data['id_faskes'] = Auth::user()->getFaskesId();

            return SurveillanceCase::create($data);
        });
    }

    /**
     * Update an existing surveillance case
     */
    public function updateCase($request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $case = SurveillanceCase::findOrFail($id);

            $data = $this->prepareData($request);
            $data['updated_by'] = Auth::id();

            $case->update($data);

            return $case;
        });
    }

    /**
     * Delete a surveillance case
     */
    public function deleteCase($id)
    {
        $case = SurveillanceCase::findOrFail($id);
        return $case->delete();
    }

    /**
     * Get dashboard statistics
     *
     * @param string|null $faskesType  'puskesmas' or 'rs' (null = all)
     * @param int|null    $faskesId    ID of puskesmas/rs (null = all)
     */
    public function getDashboardStats(?string $faskesType = null, ?int $faskesId = null, ?int $diseaseId = null)
    {
        $base = $this->scopedQuery($faskesType, $faskesId);

        if ($diseaseId) {
            $base->where('id_jenis_kasus', $diseaseId);
        }

        $stats = [
            'total_cases' => (clone $base)->count(),
            'suspected_cases' => (clone $base)->byStatus('suspected')->count(),
            'probable_cases' => (clone $base)->byStatus('probable')->count(),
            'confirmed_cases' => (clone $base)->byStatus('confirmed')->count(),
            'discarded_cases' => (clone $base)->byStatus('discarded')->count(),
            'death_cases' => (clone $base)->byOutcome('meninggal')->count(),
            'recovered_cases' => (clone $base)->byOutcome('sembuh')->count(),
            'in_treatment_cases' => (clone $base)->byOutcome('dalam_perawatan')->count(),
        ];

        return $stats;
    }

    /**
     * Get cases grouped by geography
     */
    public function getCasesByGeography($level = 'kecamatan', ?array $faskesScope = null, ?int $diseaseId = null)
    {
        $applyDisease = function ($query) use ($diseaseId) {
            if ($diseaseId) {
                $query->where('id_jenis_kasus', $diseaseId);
            }
            return $query;
        };

        if ($level === 'kecamatan') {
            $query = $applyDisease($this->scopedQueryFromArray($faskesScope))
                ->select('id_kec', DB::raw('count(*) as total'))
                ->with('kecamatan:id,name')
                ->groupBy('id_kec');
            return $query->get();
        } elseif ($level === 'kelurahan') {
            $query = $applyDisease($this->scopedQueryFromArray($faskesScope))
                ->select('id_kel', DB::raw('count(*) as total'))
                ->with('kelurahan:id,name')
                ->groupBy('id_kel');
            return $query->get();
        } elseif ($level === 'rt') {
            $query = $applyDisease($this->scopedQueryFromArray($faskesScope))
                ->select('id_rt', 'id_kel', DB::raw('count(*) as total'))
                ->with(['rt:id,name', 'kelurahan:id,name'])
                ->groupBy('id_rt', 'id_kel');
            return $query->get();
        }

        return collect();
    }

    /**
     * Get cases trend over months
     */
    public function getCasesTrend($months = 12, ?array $faskesScope = null, ?int $diseaseId = null)
    {
        $startDate = Carbon::now()->subMonths($months);

        $query = $this->scopedQueryFromArray($faskesScope);

        if ($diseaseId) {
            $query->where('id_jenis_kasus', $diseaseId);
        }

        return $query->select(
                DB::raw('YEAR(tanggal_onset) as year'),
                DB::raw('MONTH(tanggal_onset) as month'),
                DB::raw('count(*) as total')
            )
            ->where('tanggal_onset', '>=', $startDate)
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();
    }

    /**
     * Get cases grouped by disease type (only active diseases)
     */
    public function getCasesByDisease(?array $faskesScope = null, ?int $diseaseId = null)
    {
        $query = $this->scopedQueryFromArray($faskesScope)
            ->select('id_jenis_kasus', DB::raw('count(*) as total'))
            ->whereHas('jenisKasus', function ($q) {
                $q->where('is_active', true);
            })
            ->with('jenisKasus:id,nama_penyakit,kode_penyakit')
            ->groupBy('id_jenis_kasus')
            ->orderByDesc('total');

        if ($diseaseId) {
            $query->where('id_jenis_kasus', $diseaseId);
        }

        return $query->get();
    }

    /**
     * Get cases grouped by status
     */
    public function getCasesByStatus(?array $faskesScope = null, ?int $diseaseId = null)
    {
        $query = $this->scopedQueryFromArray($faskesScope);

        if ($diseaseId) {
            $query->where('id_jenis_kasus', $diseaseId);
        }

        return $query->select('status_kasus', DB::raw('count(*) as total'))
            ->groupBy('status_kasus')
            ->get();
    }

    /**
     * Build a base query scoped to a specific faskes (or unscoped if null).
     */
    private function scopedQuery(?string $faskesType, ?int $faskesId)
    {
        $query = $this->model->newQuery();

        if ($faskesType && $faskesId) {
            $query->where('faskes_type', $faskesType)->where('id_faskes', $faskesId);
        }

        return $query;
    }

    /**
     * Build a scoped query from an array ['faskes_type' => ..., 'id_faskes' => ...] or null.
     */
    private function scopedQueryFromArray(?array $faskesScope)
    {
        if ($faskesScope) {
            return $this->scopedQuery($faskesScope['faskes_type'], $faskesScope['id_faskes']);
        }

        return $this->model->newQuery();
    }

    /**
     * Determine age category from age in years
     */
    private function getKategoriUmur($umurTahun)
    {
        if ($umurTahun < 1) return 'bayi';
        if ($umurTahun >= 1 && $umurTahun < 5) return 'balita';
        if ($umurTahun >= 5 && $umurTahun < 12) return 'anak';
        if ($umurTahun >= 12 && $umurTahun < 18) return 'remaja';
        if ($umurTahun >= 18 && $umurTahun < 60) return 'dewasa';
        return 'lansia';
    }
}
