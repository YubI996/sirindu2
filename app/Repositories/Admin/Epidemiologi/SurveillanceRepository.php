<?php

namespace App\Repositories\Admin\Epidemiologi;

use App\Repositories\Admin\Core\Epidemiologi\SurveillanceRepositoryInterface;
use App\Models\SurveillanceCase;
use App\Models\JenisKasusEpidemiologi;
use App\Models\EpidCounter;
use App\Models\LokasiPenularanMaster;
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

        // Auto-derive legacy management fields from faskes_berobat MoD rows.
        // These fields are no longer submitted via form — always overwritten here.
        $faskesRows = $request->input('faskes_berobat', []);
        $firstRow = collect($faskesRows)->first(fn($r) => !empty($r['nama_faskes']));
        if ($firstRow) {
            $perawatanMap = ['inap' => 'rawat_inap', 'jalan' => 'rawat_jalan'];
            $data['status_rawat']        = $perawatanMap[$firstRow['jenis_perawatan'] ?? ''] ?? 'rawat_jalan';
            $data['nama_faskes_rawat']   = $firstRow['nama_faskes'];
            $data['tanggal_masuk_rawat'] = $firstRow['tanggal_berobat'] ?: null;
            $data['tanggal_keluar_rawat'] = $firstRow['tanggal_keluar'] ?: null;
        } else {
            // No faskes_berobat rows — satisfy NOT NULL columns with safe defaults
            $data['status_rawat']        = 'rawat_jalan';
            $data['nama_faskes_rawat']   = '-';
            $data['tanggal_masuk_rawat'] = null;
            $data['tanggal_keluar_rawat'] = null;
        }

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
            // riwayat_imunisasi nullable — null berarti belum diisi, bukan 'tidak_tahu'
            $data['status_lab'] = $data['status_lab'] ?? 'belum_diperiksa';
            $data['kondisi_akhir'] = $data['kondisi_akhir'] ?? 'dalam_perawatan';
            $data['status_kasus'] = $data['status_kasus'] ?? 'suspected';

            // System fields
            $data['id_petugas_input'] = Auth::id();
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();
            $data['faskes_type'] = Auth::user()->faskes_type;
            $data['id_faskes'] = Auth::user()->getFaskesId();

            // Auto-generate nomor epidemiologi
            $data['no_registrasi'] = $this->generateNoRegistrasi($data['id_jenis_kasus']);

            return SurveillanceCase::create($data);
        });
    }

    /**
     * Generate nomor registrasi epidemiologi.
     * Format: [prefix]-1710[YY][NNN] or 1710[YY][NNN] for AFP/Polio.
     */
    private function generateNoRegistrasi(int $idJenisKasus): string
    {
        $jenisKasus = JenisKasusEpidemiologi::findOrFail($idJenisKasus);
        $kodePenyakit = $jenisKasus->kode_penyakit;

        $prefixMap = [
            'CAMPAK_RUBELLA' => 'C',
            'DIFTERI_OBS' => 'D',
            'PERTUSIS' => 'P',
            'TETANUS_NEO' => 'TN',
            // AFP has no prefix
        ];

        $tahun = now()->year;
        $yy = substr((string) $tahun, -2);
        $sequence = EpidCounter::getNextSequence($tahun);
        $nnn = str_pad($sequence, 3, '0', STR_PAD_LEFT);

        $baseNumber = "1710{$yy}{$nnn}";

        if (!isset($prefixMap[$kodePenyakit])) {
            return $baseNumber;
        }

        return $prefixMap[$kodePenyakit] . '-' . $baseNumber;
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
     * Get cases grouped by facility type (lokasi penularan category).
     */
    public function getCasesByFacilityType(?array $faskesScope = null, ?int $diseaseId = null)
    {
        $query = $this->scopedQueryFromArray($faskesScope)
            ->whereNotNull('lokasi_penularan')
            ->where('lokasi_penularan', '!=', '');

        if ($diseaseId) {
            $query->where('id_jenis_kasus', $diseaseId);
        }

        $cases = $query->select('lokasi_penularan', DB::raw('count(*) as total'))
            ->groupBy('lokasi_penularan')
            ->get();

        // Map lokasi_penularan text to kategori from master table
        $masterLookup = LokasiPenularanMaster::pluck('kategori', 'nama');

        $grouped = [];
        foreach ($cases as $case) {
            $kategori = $masterLookup[$case->lokasi_penularan] ?? 'Lainnya';
            if (!isset($grouped[$kategori])) {
                $grouped[$kategori] = 0;
            }
            $grouped[$kategori] += $case->total;
        }

        // Convert to collection format matching other chart methods
        return collect($grouped)->map(function ($total, $kategori) {
            return ['kategori' => $kategori, 'total' => $total];
        })->sortByDesc('total')->values();
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
     * Upsert 5 imunisasi rows for a surveillance case.
     * If $imunisasiData is empty, inserts 5 default rows with diberikan = 'tidak_tahu'.
     */
    public function syncImunisasi(SurveillanceCase $case, array $imunisasiData): void
    {
        $antigenLabels = [
            1 => 'MR1 / DPT-HB-Hib 1 / OPV1',
            2 => 'MR2 / DPT-HB-Hib Booster / OPV2',
            3 => 'MR3 / DT kelas 1 SD',
            4 => 'MMR / TD kelas 2 dan 5',
            5 => 'Kampanye / ORI / SUBPIN / PIN',
        ];

        for ($ke = 1; $ke <= 5; $ke++) {
            $row = $imunisasiData[$ke] ?? [];
            $case->imunisasi()->updateOrCreate(
                ['imunisasi_ke' => $ke],
                [
                    'nama_antigen'      => $antigenLabels[$ke],
                    'diberikan'         => $row['diberikan'] ?? 'tidak_tahu',
                    'sumber_informasi'  => $row['sumber_informasi'] ?? null,
                    'tanggal_imunisasi' => $row['tanggal_imunisasi'] ?: null,
                ]
            );
        }
    }

    /**
     * Delete-then-insert faskes berobat rows, assigning urutan from index.
     */
    public function syncFaskesBerobat(SurveillanceCase $case, array $data): void
    {
        $case->faskesBerobat()->delete();

        $urutan = 1;
        foreach ($data as $row) {
            if (empty($row['jenis_faskes']) || empty($row['nama_faskes'])) {
                continue;
            }
            $case->faskesBerobat()->create([
                'urutan'           => $urutan++,
                'jenis_faskes'     => $row['jenis_faskes'],
                'nama_faskes'      => $row['nama_faskes'],
                'tanggal_berobat'  => $row['tanggal_berobat'] ?: null,
                'jenis_perawatan'  => $row['jenis_perawatan'] ?: null,
                'tanggal_keluar'   => $row['tanggal_keluar'] ?: null,
            ]);
        }
    }

    /**
     * Delete-then-insert spesimen rows. Rows with empty jenis_spesimen are skipped.
     */
    public function syncSpesimen(SurveillanceCase $case, array $data): void
    {
        $case->spesimen()->delete();

        $urutan = 1;
        foreach ($data as $row) {
            if (empty($row['jenis_spesimen'])) {
                continue;
            }
            $case->spesimen()->create([
                'urutan'                        => $urutan++,
                'jenis_spesimen'                => $row['jenis_spesimen'],
                'tanggal_ambil_spesimen'        => $row['tanggal_ambil_spesimen'] ?: null,
                'tanggal_kirim_sampel'          => $row['tanggal_kirim_sampel'] ?: null,
                'tanggal_terima_lab'            => $row['tanggal_terima_lab'] ?: null,
                'status_pemeriksaan'            => $row['status_pemeriksaan'] ?: null,
                'penyakit_terkonfirmasi'        => $row['penyakit_terkonfirmasi'] ?: null,
                'nama_variant_genotype'         => $row['nama_variant_genotype'] ?: null,
            ]);
        }
    }

    /**
     * Delete-then-insert kontak erat rows. Rows with empty nama are skipped.
     */
    public function syncKontakErat(SurveillanceCase $case, array $data): void
    {
        $case->kontakErat()->delete();

        $urutan = 1;
        foreach ($data as $row) {
            if (empty($row['nama'])) {
                continue;
            }
            $case->kontakErat()->create([
                'urutan'                          => $urutan++,
                'nama'                            => $row['nama'],
                'hubungan'                        => $row['hubungan'] ?: null,
                'tanggal_lahir'                   => $row['tanggal_lahir'] ?: null,
                'no_telepon'                      => $row['no_telepon'] ?: null,
                'alamat'                          => $row['alamat'] ?: null,
                'tanggal_kontak_terakhir'         => $row['tanggal_kontak_terakhir'] ?: null,
                'ada_gejala'                      => !empty($row['ada_gejala']),
                'jumlah_imunisasi_campak_rubella' => isset($row['jumlah_imunisasi_campak_rubella']) && $row['jumlah_imunisasi_campak_rubella'] !== '' ? (int) $row['jumlah_imunisasi_campak_rubella'] : null,
                'catatan'                         => $row['catatan'] ?: null,
            ]);
        }
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

    // ==================== PD3I DASHBOARD METHODS ====================

    /**
     * Anonymise a full name — keeps first initial + last initial only.
     */
    private function samarkanNama(?string $nama): string
    {
        if (!$nama) return '–';
        $parts = array_filter(explode(' ', trim($nama)));
        $first = reset($parts);
        $last  = end($parts);
        $result = strtoupper($first[0] ?? '') . '***';
        if ($last && $last !== $first) {
            $result .= ' ' . strtoupper($last[0]) . '.';
        }
        return $result;
    }

    /**
     * Base query builder for PD3I dashboard — applies year/jenis_kasus/wilker/kelurahan filters.
     */
    private function pd3iBaseQuery(int $tahun, ?int $jenisKasusId, ?string $wilker, ?int $kelurahanId = null): \Illuminate\Database\Eloquent\Builder
    {
        $query = SurveillanceCase::query()->whereYear('tanggal_lapor', $tahun);

        if ($jenisKasusId) {
            $query->where('id_jenis_kasus', $jenisKasusId);
        }

        if ($wilker) {
            $query->where('wilker_puskesmas', $wilker);
        }

        if ($kelurahanId) {
            $query->where('id_kel', $kelurahanId);
        }

        return $query;
    }

    /**
     * Kinerja Surveilans scorecard data for all 4 disease panels.
     *
     * Disease IDs: 1=Campak-Rubella, 2=Difteri, 3=AFP, 4=Pertusis
     */
    public function getPd3iKinerja(int $tahun, ?int $jenisKasusId, ?string $wilker, ?int $kelurahanId = null): array
    {
        $base = $this->pd3iBaseQuery($tahun, $jenisKasusId, $wilker, $kelurahanId);

        // ===== Campak-Rubella (id=1) =====
        $cr = (clone $base)->where('id_jenis_kasus', 1);
        $crTotal = (clone $cr)->count();
        // pct_sampel: kasus yang status_lab = 'diperiksa_lab' (sampel diambil & dikirim ke lab)
        $crSampel = (clone $cr)->where('status_lab', 'diperiksa_lab')->count();
        $crLabDiterima = (clone $cr)->whereNotNull('tanggal_hasil_lab')->count();
        $crLabDiperiksa = (clone $cr)->whereIn('status_lab', ['diperiksa_lab','proses','positif','negatif'])->count();
        $crLabPositif = (clone $cr)->where('status_lab', 'positif')->count();

        $campakRubella = [
            'suspek'            => (clone $cr)->where('status_kasus', 'suspected')->count(),
            'confirmed_campak'  => (clone $cr)->where('status_kasus', 'confirmed')
                                              ->whereRaw('LOWER(hasil_lab) LIKE ?', ['%campak%'])
                                              ->count(),
            'confirmed_rubella' => (clone $cr)->where('status_kasus', 'confirmed')
                                              ->whereRaw('LOWER(hasil_lab) LIKE ?', ['%rubella%'])
                                              ->count(),
            'discarded'         => (clone $cr)->where('status_kasus', 'discarded')->count(),
            'meninggal'         => (clone $cr)->where('kondisi_akhir', 'meninggal')->count(),
            'pct_sampel'        => $crTotal > 0 ? round($crSampel / $crTotal * 100, 1) : 0,
            'pct_lab_diterima'  => $crTotal > 0 ? round($crLabDiterima / $crTotal * 100, 1) : 0,
            'positivity_rate'   => $crLabDiperiksa > 0 ? round($crLabPositif / $crLabDiperiksa * 100, 1) : 0,
        ];

        // ===== AFP/Polio (id=3) =====
        $afp = (clone $base)->where('id_jenis_kasus', 3);
        $afpData = [
            'total'      => (clone $afp)->count(),
            'confirmed'  => (clone $afp)->where('status_kasus', 'confirmed')->count(),
            'npafp_rate' => null, // Butuh data populasi eksternal
        ];

        // ===== Difteri (id=2) =====
        $difteri = (clone $base)->where('id_jenis_kasus', 2);
        $difteriData = [
            'observasi' => (clone $difteri)->count(),
            'confirmed' => (clone $difteri)->where('status_kasus', 'confirmed')->count(),
        ];

        // ===== Pertusis (id=4) =====
        $pertusis = (clone $base)->where('id_jenis_kasus', 4);
        $pertusisData = [
            'suspek' => (clone $pertusis)->count(),
        ];

        return [
            'campak_rubella' => $campakRubella,
            'afp'            => $afpData,
            'difteri'        => $difteriData,
            'pertusis'       => $pertusisData,
        ];
    }

    /**
     * Tren data: epiweek curve, monthly trend, per faskes/kecamatan/kelurahan.
     */
    public function getPd3iTren(int $tahun, ?int $jenisKasusId, ?string $wilker, ?int $kelurahanId = null): array
    {
        $bulanLabels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

        // ===== Epiweek (based on tanggal_onset, tahun-1 to tahun) =====
        $epiBase = SurveillanceCase::query()
            ->whereNotNull('tanggal_onset')
            ->whereRaw('YEAR(tanggal_onset) BETWEEN ? AND ?', [$tahun - 1, $tahun]);
        if ($jenisKasusId) $epiBase->where('id_jenis_kasus', $jenisKasusId);
        if ($wilker) $epiBase->where('wilker_puskesmas', $wilker);
        if ($kelurahanId) $epiBase->where('id_kel', $kelurahanId);

        $epiweek = $epiBase
            ->select(
                DB::raw('YEARWEEK(tanggal_onset, 3) as epiweek'),
                DB::raw('COUNT(*) as suspek'),
                DB::raw("SUM(CASE WHEN status_kasus='confirmed' THEN 1 ELSE 0 END) as confirmed")
            )
            ->groupBy('epiweek')
            ->orderBy('epiweek')
            ->get()
            ->map(function ($r) {
                $yw   = str_pad((string) $r->epiweek, 6, '0', STR_PAD_LEFT);
                $year = substr($yw, 0, 4);
                $week = substr($yw, 4, 2);
                return ['week' => $year . '-W' . $week, 'suspek' => (int)$r->suspek, 'confirmed' => (int)$r->confirmed];
            })->values()->toArray();

        // ===== Bulanan 12 bulan (based on tanggal_lapor) =====
        $base = $this->pd3iBaseQuery($tahun, $jenisKasusId, $wilker, $kelurahanId);

        $bulananRaw = (clone $base)
            ->select(
                DB::raw('MONTH(tanggal_lapor) as bulan'),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status_kasus='confirmed' THEN 1 ELSE 0 END) as confirmed")
            )
            ->groupBy('bulan')
            ->orderBy('bulan')
            ->get()->keyBy('bulan');

        $bulanan = [];
        for ($m = 1; $m <= 12; $m++) {
            $row = $bulananRaw->get($m);
            $bulanan[] = [
                'bulan'     => $m,
                'label'     => $bulanLabels[$m - 1],
                'total'     => $row ? (int)$row->total : 0,
                'confirmed' => $row ? (int)$row->confirmed : 0,
            ];
        }

        // ===== Per Faskes =====
        $perFaskes = (clone $base)
            ->select(
                DB::raw('MONTH(surveillance_cases.tanggal_lapor) as bulan'),
                DB::raw('COALESCE(rs.name, surveillance_cases.instansi_pelapor, \'Tidak Diketahui\') as faskes'),
                DB::raw('COUNT(*) as jumlah')
            )
            ->leftJoin('rumah_sakits as rs', 'surveillance_cases.id_faskes_pelapor', '=', 'rs.id')
            ->groupBy('bulan', 'faskes')
            ->orderBy('bulan')
            ->get()
            ->map(fn($r) => ['bulan' => (int)$r->bulan, 'faskes' => $r->faskes, 'jumlah' => (int)$r->jumlah])
            ->toArray();

        // ===== Per Kecamatan =====
        $perKecamatan = (clone $base)
            ->select(
                DB::raw('MONTH(surveillance_cases.tanggal_lapor) as bulan'),
                DB::raw('COALESCE(kec.name, \'Tidak Diketahui\') as kecamatan'),
                DB::raw('COUNT(*) as jumlah')
            )
            ->leftJoin('kecamatan as kec', 'surveillance_cases.id_kec', '=', 'kec.id')
            ->groupBy('bulan', 'kecamatan')
            ->orderBy('bulan')
            ->get()
            ->map(fn($r) => ['bulan' => (int)$r->bulan, 'kecamatan' => $r->kecamatan, 'jumlah' => (int)$r->jumlah])
            ->toArray();

        // ===== Per Kelurahan =====
        $perKelurahan = (clone $base)
            ->select(
                DB::raw('MONTH(surveillance_cases.tanggal_lapor) as bulan'),
                DB::raw('COALESCE(kel.name, \'Tidak Diketahui\') as kelurahan'),
                DB::raw('COALESCE(kec.name, \'Tidak Diketahui\') as kecamatan'),
                DB::raw('COUNT(*) as jumlah')
            )
            ->leftJoin('kelurahan as kel', 'surveillance_cases.id_kel', '=', 'kel.id')
            ->leftJoin('kecamatan as kec', 'surveillance_cases.id_kec', '=', 'kec.id')
            ->groupBy('bulan', 'kelurahan', 'kecamatan')
            ->orderBy('bulan')
            ->get()
            ->map(fn($r) => ['bulan' => (int)$r->bulan, 'kelurahan' => $r->kelurahan, 'kecamatan' => $r->kecamatan, 'jumlah' => (int)$r->jumlah])
            ->toArray();

        return [
            'epiweek'       => $epiweek,
            'bulanan'       => $bulanan,
            'per_faskes'    => $perFaskes,
            'per_kecamatan' => $perKecamatan,
            'per_kelurahan' => $perKelurahan,
        ];
    }

    /**
     * Demografi data: kelompok umur, status vaksinasi, severity.
     */
    public function getPd3iDemografi(int $tahun, ?int $jenisKasusId, ?string $wilker, ?int $kelurahanId = null): array
    {
        $base = $this->pd3iBaseQuery($tahun, $jenisKasusId, $wilker, $kelurahanId);

        // ===== Kelompok Umur =====
        $buckets = ['< 6 bulan','6-8 bulan','9-11 bulan','12-17 bulan','18-59 bulan','5-9 tahun','10-14 tahun','>= 15 tahun','Tidak Diketahui'];

        $umurRaw = (clone $base)
            ->select(
                DB::raw("CASE
                    WHEN tanggal_lahir IS NULL THEN 'Tidak Diketahui'
                    WHEN TIMESTAMPDIFF(MONTH, tanggal_lahir, COALESCE(tanggal_onset, NOW())) < 6 THEN '< 6 bulan'
                    WHEN TIMESTAMPDIFF(MONTH, tanggal_lahir, COALESCE(tanggal_onset, NOW())) BETWEEN 6 AND 8 THEN '6-8 bulan'
                    WHEN TIMESTAMPDIFF(MONTH, tanggal_lahir, COALESCE(tanggal_onset, NOW())) BETWEEN 9 AND 11 THEN '9-11 bulan'
                    WHEN TIMESTAMPDIFF(MONTH, tanggal_lahir, COALESCE(tanggal_onset, NOW())) BETWEEN 12 AND 17 THEN '12-17 bulan'
                    WHEN TIMESTAMPDIFF(MONTH, tanggal_lahir, COALESCE(tanggal_onset, NOW())) BETWEEN 18 AND 59 THEN '18-59 bulan'
                    WHEN TIMESTAMPDIFF(MONTH, tanggal_lahir, COALESCE(tanggal_onset, NOW())) BETWEEN 60 AND 119 THEN '5-9 tahun'
                    WHEN TIMESTAMPDIFF(MONTH, tanggal_lahir, COALESCE(tanggal_onset, NOW())) BETWEEN 120 AND 179 THEN '10-14 tahun'
                    ELSE '>= 15 tahun'
                END as kelompok"),
                'status_kasus',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('kelompok', 'status_kasus')
            ->get();

        $umurMap = [];
        foreach ($umurRaw as $row) {
            $k = $row->kelompok;
            if (!isset($umurMap[$k])) {
                $umurMap[$k] = ['label' => $k, 'suspek' => 0, 'confirmed' => 0, 'discarded' => 0];
            }
            if ($row->status_kasus === 'suspected')  $umurMap[$k]['suspek']    = (int)$row->total;
            elseif ($row->status_kasus === 'confirmed') $umurMap[$k]['confirmed'] = (int)$row->total;
            elseif ($row->status_kasus === 'discarded') $umurMap[$k]['discarded'] = (int)$row->total;
        }
        $kelompokUmur = array_map(
            fn($b) => $umurMap[$b] ?? ['label' => $b, 'suspek' => 0, 'confirmed' => 0, 'discarded' => 0],
            $buckets
        );

        // ===== Status Vaksinasi =====
        $vaksinasiRaw = (clone $base)
            ->select('riwayat_imunisasi', DB::raw('COUNT(*) as total'))
            ->groupBy('riwayat_imunisasi')
            ->get()->keyBy('riwayat_imunisasi');

        $statusVaksinasi = [
            'tidak_ada'     => (int)($vaksinasiRaw->get('tidak_ada')?->total ?? 0),
            'tidak_lengkap' => (int)($vaksinasiRaw->get('tidak_lengkap')?->total ?? 0),
            'lengkap'       => (int)($vaksinasiRaw->get('lengkap')?->total ?? 0),
            'tidak_tahu'    => (int)($vaksinasiRaw->get('tidak_tahu')?->total ?? 0),
        ];

        // ===== Severity =====
        $total    = (clone $base)->count();
        $rawatInap = (clone $base)->where('status_rawat', 'rawat_inap')->count();
        $meninggal = (clone $base)->where('kondisi_akhir', 'meninggal')->count();

        $komp = (clone $base)->select(
            DB::raw('SUM(komplikasi_diare) as diare'),
            DB::raw('SUM(komplikasi_kebutaan) as kebutaan'),
            DB::raw('SUM(komplikasi_pneumonia) as pneumonia'),
            DB::raw('SUM(komplikasi_malnutrisi) as malnutrisi'),
            DB::raw('SUM(komplikasi_bronchopneumonia) as bronchopneumonia'),
            DB::raw('SUM(komplikasi_otitis_media) as otitis_media'),
            DB::raw('SUM(komplikasi_encephalitis) as encephalitis'),
            DB::raw('SUM(komplikasi_ulkus_mukosa_mulut) as ulkus_mukosa_mulut')
        )->first();

        $severity = [
            'pct_rawat_inap' => $total > 0 ? round($rawatInap / $total * 100, 1) : 0,
            'komplikasi' => [
                'diare'              => (int)($komp?->diare ?? 0),
                'kebutaan'           => (int)($komp?->kebutaan ?? 0),
                'pneumonia'          => (int)($komp?->pneumonia ?? 0),
                'malnutrisi'         => (int)($komp?->malnutrisi ?? 0),
                'bronchopneumonia'   => (int)($komp?->bronchopneumonia ?? 0),
                'otitis_media'       => (int)($komp?->otitis_media ?? 0),
                'encephalitis'       => (int)($komp?->encephalitis ?? 0),
                'ulkus_mukosa_mulut' => (int)($komp?->ulkus_mukosa_mulut ?? 0),
            ],
            'meninggal' => $meninggal,
        ];

        return [
            'kelompok_umur'    => array_values($kelompokUmur),
            'status_vaksinasi' => $statusVaksinasi,
            'severity'         => $severity,
        ];
    }

    /**
     * Wilayah data: per puskesmas/kecamatan/kelurahan tables + peta markers.
     */
    public function getPd3iWilayah(int $tahun, ?int $jenisKasusId, ?string $wilker, ?int $kelurahanId = null): array
    {
        $base = $this->pd3iBaseQuery($tahun, $jenisKasusId, $wilker, $kelurahanId);

        // ===== Per Puskesmas =====
        $perPuskesmas = (clone $base)
            ->select(
                DB::raw("COALESCE(wilker_puskesmas, 'Tidak Diketahui') as wilker"),
                DB::raw("SUM(CASE WHEN status_kasus='suspected' THEN 1 ELSE 0 END) as suspek"),
                DB::raw("SUM(CASE WHEN status_kasus='confirmed' THEN 1 ELSE 0 END) as confirmed"),
                DB::raw("SUM(CASE WHEN kondisi_akhir='meninggal' THEN 1 ELSE 0 END) as meninggal")
            )
            ->groupBy('wilker')->orderBy('wilker')->get()
            ->map(fn($r) => ['wilker' => $r->wilker, 'suspek' => (int)$r->suspek, 'confirmed' => (int)$r->confirmed, 'meninggal' => (int)$r->meninggal])
            ->toArray();

        // ===== Per Kecamatan =====
        $perKecamatan = (clone $base)
            ->select(
                DB::raw("COALESCE(kec.name, 'Tidak Diketahui') as kecamatan"),
                DB::raw("SUM(CASE WHEN surveillance_cases.status_kasus='suspected' THEN 1 ELSE 0 END) as suspek"),
                DB::raw("SUM(CASE WHEN surveillance_cases.status_kasus='confirmed' THEN 1 ELSE 0 END) as confirmed"),
                DB::raw("SUM(CASE WHEN surveillance_cases.kondisi_akhir='meninggal' THEN 1 ELSE 0 END) as meninggal")
            )
            ->leftJoin('kecamatan as kec', 'surveillance_cases.id_kec', '=', 'kec.id')
            ->groupBy('kecamatan')->orderBy('kecamatan')->get()
            ->map(fn($r) => ['kecamatan' => $r->kecamatan, 'suspek' => (int)$r->suspek, 'confirmed' => (int)$r->confirmed, 'meninggal' => (int)$r->meninggal])
            ->toArray();

        // ===== Per Kelurahan =====
        $perKelurahan = (clone $base)
            ->select(
                DB::raw("COALESCE(kec.name, 'Tidak Diketahui') as kecamatan"),
                DB::raw("COALESCE(kel.name, 'Tidak Diketahui') as kelurahan"),
                DB::raw("SUM(CASE WHEN surveillance_cases.status_kasus='suspected' THEN 1 ELSE 0 END) as suspek"),
                DB::raw("SUM(CASE WHEN surveillance_cases.status_kasus='confirmed' THEN 1 ELSE 0 END) as confirmed"),
                DB::raw("SUM(CASE WHEN surveillance_cases.kondisi_akhir='meninggal' THEN 1 ELSE 0 END) as meninggal")
            )
            ->leftJoin('kelurahan as kel', 'surveillance_cases.id_kel', '=', 'kel.id')
            ->leftJoin('kecamatan as kec', 'surveillance_cases.id_kec', '=', 'kec.id')
            ->groupBy('kecamatan', 'kelurahan')->orderBy('kecamatan')->orderBy('kelurahan')->get()
            ->map(fn($r) => ['kecamatan' => $r->kecamatan, 'kelurahan' => $r->kelurahan, 'suspek' => (int)$r->suspek, 'confirmed' => (int)$r->confirmed, 'meninggal' => (int)$r->meninggal])
            ->toArray();

        // ===== Peta =====
        $peta = (clone $base)
            ->with('jenisKasus:id,nama_penyakit')
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->get()
            ->map(fn($r) => [
                'id'       => $r->id,
                'lat'      => (float)$r->latitude,
                'lng'      => (float)$r->longitude,
                'nama'     => $this->samarkanNama($r->nama_lengkap),
                'penyakit' => $r->jenisKasus?->nama_penyakit ?? '–',
                'status'   => $r->status_kasus,
            ])->toArray();

        return [
            'per_puskesmas' => $perPuskesmas,
            'per_kecamatan' => $perKecamatan,
            'per_kelurahan' => $perKelurahan,
            'peta'          => $peta,
        ];
    }
}
