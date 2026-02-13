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
     * Store a new surveillance case
     */
    public function storeCase($request)
    {
        return DB::transaction(function () use ($request) {
            // Auto-calculate kategori_umur from tanggal_lahir
            $tanggalLahir = Carbon::parse($request->tanggal_lahir);
            $umurTahun = $tanggalLahir->diffInYears(now());

            $kategoriUmur = $this->getKategoriUmur($umurTahun);

            $case = SurveillanceCase::create([
                // Category A: Patient Identity
                'no_registrasi' => $request->no_registrasi,
                'nik' => $request->nik,
                'nama_lengkap' => $request->nama_lengkap,
                'tanggal_lahir' => $request->tanggal_lahir,
                'kategori_umur' => $kategoriUmur,
                'jenis_kelamin' => $request->jenis_kelamin,
                'alamat_lengkap' => $request->alamat_lengkap,
                'id_kec' => $request->id_kec,
                'id_kel' => $request->id_kel,
                'id_rt' => $request->id_rt,
                'no_telepon' => $request->no_telepon,

                // Category B: Reporter Identity
                'nama_pelapor' => $request->nama_pelapor,
                'jabatan_pelapor' => $request->jabatan_pelapor,
                'instansi_pelapor' => $request->instansi_pelapor,
                'telepon_pelapor' => $request->telepon_pelapor,

                // Category C: Case Data
                'id_jenis_kasus' => $request->id_jenis_kasus,
                'kode_icd10' => $request->kode_icd10,
                'tanggal_onset' => $request->tanggal_onset,
                'tanggal_konsultasi' => $request->tanggal_konsultasi,
                'tanggal_lapor' => $request->tanggal_lapor ?? now(),
                'sumber_penularan' => $request->sumber_penularan ?? 'unknown',
                'lokasi_penularan' => $request->lokasi_penularan,

                // Category D: Symptoms (boolean)
                'gejala_demam' => $request->has('gejala_demam') ? 1 : 0,
                'gejala_batuk' => $request->has('gejala_batuk') ? 1 : 0,
                'gejala_pilek' => $request->has('gejala_pilek') ? 1 : 0,
                'gejala_sakit_kepala' => $request->has('gejala_sakit_kepala') ? 1 : 0,
                'gejala_mual' => $request->has('gejala_mual') ? 1 : 0,
                'gejala_muntah' => $request->has('gejala_muntah') ? 1 : 0,
                'gejala_diare' => $request->has('gejala_diare') ? 1 : 0,
                'gejala_ruam' => $request->has('gejala_ruam') ? 1 : 0,
                'gejala_sesak_napas' => $request->has('gejala_sesak_napas') ? 1 : 0,
                'gejala_nyeri_otot' => $request->has('gejala_nyeri_otot') ? 1 : 0,
                'gejala_nyeri_sendi' => $request->has('gejala_nyeri_sendi') ? 1 : 0,
                'gejala_lemas' => $request->has('gejala_lemas') ? 1 : 0,
                'gejala_kehilangan_nafsu_makan' => $request->has('gejala_kehilangan_nafsu_makan') ? 1 : 0,
                'gejala_mata_merah' => $request->has('gejala_mata_merah') ? 1 : 0,
                'gejala_pembengkakan_kelenjar' => $request->has('gejala_pembengkakan_kelenjar') ? 1 : 0,
                'gejala_kejang' => $request->has('gejala_kejang') ? 1 : 0,
                'gejala_penurunan_kesadaran' => $request->has('gejala_penurunan_kesadaran') ? 1 : 0,

                // Category E: History
                'riwayat_perjalanan' => $request->riwayat_perjalanan,
                'riwayat_kontak_kasus' => $request->has('riwayat_kontak_kasus') ? 1 : 0,
                'riwayat_imunisasi' => $request->riwayat_imunisasi ?? 'tidak_tahu',
                'tanggal_imunisasi_terakhir' => $request->tanggal_imunisasi_terakhir,

                // Category F: Laboratory
                'status_lab' => $request->status_lab ?? 'belum_diperiksa',
                'tanggal_pengambilan_spesimen' => $request->tanggal_pengambilan_spesimen,
                'jenis_spesimen' => $request->jenis_spesimen,
                'hasil_lab' => $request->hasil_lab,
                'tanggal_hasil_lab' => $request->tanggal_hasil_lab,

                // Category G: Management
                'status_rawat' => $request->status_rawat,
                'nama_faskes_rawat' => $request->nama_faskes_rawat,
                'tanggal_masuk_rawat' => $request->tanggal_masuk_rawat,
                'tanggal_keluar_rawat' => $request->tanggal_keluar_rawat,

                // Category H: Final Status
                'kondisi_akhir' => $request->kondisi_akhir ?? 'dalam_perawatan',
                'tanggal_kondisi_akhir' => $request->tanggal_kondisi_akhir,
                'penyebab_kematian' => $request->penyebab_kematian,

                // Category I: Contact Investigation
                'jumlah_kontak_serumah' => $request->jumlah_kontak_serumah ?? 0,
                'jumlah_kontak_diluar_rumah' => $request->jumlah_kontak_diluar_rumah ?? 0,
                'jumlah_kontak_bergejala' => $request->jumlah_kontak_bergejala ?? 0,
                'tindak_lanjut_kontak' => $request->tindak_lanjut_kontak,

                // Category J: Metadata
                'status_kasus' => $request->status_kasus ?? 'suspected',
                'id_petugas_input' => Auth::id(),
                'id_faskes_pelapor' => $request->id_faskes_pelapor,
                'catatan_tambahan' => $request->catatan_tambahan,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            return $case;
        });
    }

    /**
     * Update an existing surveillance case
     */
    public function updateCase($request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $case = SurveillanceCase::findOrFail($id);

            // Auto-calculate kategori_umur if tanggal_lahir is updated
            $kategoriUmur = $case->kategori_umur;
            if ($request->filled('tanggal_lahir')) {
                $tanggalLahir = Carbon::parse($request->tanggal_lahir);
                $umurTahun = $tanggalLahir->diffInYears(now());
                $kategoriUmur = $this->getKategoriUmur($umurTahun);
            }

            $case->update([
                // Category A: Patient Identity
                'no_registrasi' => $request->no_registrasi,
                'nik' => $request->nik,
                'nama_lengkap' => $request->nama_lengkap,
                'tanggal_lahir' => $request->tanggal_lahir,
                'kategori_umur' => $kategoriUmur,
                'jenis_kelamin' => $request->jenis_kelamin,
                'alamat_lengkap' => $request->alamat_lengkap,
                'id_kec' => $request->id_kec,
                'id_kel' => $request->id_kel,
                'id_rt' => $request->id_rt,
                'no_telepon' => $request->no_telepon,

                // Category B: Reporter Identity
                'nama_pelapor' => $request->nama_pelapor,
                'jabatan_pelapor' => $request->jabatan_pelapor,
                'instansi_pelapor' => $request->instansi_pelapor,
                'telepon_pelapor' => $request->telepon_pelapor,

                // Category C: Case Data
                'id_jenis_kasus' => $request->id_jenis_kasus,
                'kode_icd10' => $request->kode_icd10,
                'tanggal_onset' => $request->tanggal_onset,
                'tanggal_konsultasi' => $request->tanggal_konsultasi,
                'tanggal_lapor' => $request->tanggal_lapor,
                'sumber_penularan' => $request->sumber_penularan,
                'lokasi_penularan' => $request->lokasi_penularan,

                // Category D: Symptoms
                'gejala_demam' => $request->has('gejala_demam') ? 1 : 0,
                'gejala_batuk' => $request->has('gejala_batuk') ? 1 : 0,
                'gejala_pilek' => $request->has('gejala_pilek') ? 1 : 0,
                'gejala_sakit_kepala' => $request->has('gejala_sakit_kepala') ? 1 : 0,
                'gejala_mual' => $request->has('gejala_mual') ? 1 : 0,
                'gejala_muntah' => $request->has('gejala_muntah') ? 1 : 0,
                'gejala_diare' => $request->has('gejala_diare') ? 1 : 0,
                'gejala_ruam' => $request->has('gejala_ruam') ? 1 : 0,
                'gejala_sesak_napas' => $request->has('gejala_sesak_napas') ? 1 : 0,
                'gejala_nyeri_otot' => $request->has('gejala_nyeri_otot') ? 1 : 0,
                'gejala_nyeri_sendi' => $request->has('gejala_nyeri_sendi') ? 1 : 0,
                'gejala_lemas' => $request->has('gejala_lemas') ? 1 : 0,
                'gejala_kehilangan_nafsu_makan' => $request->has('gejala_kehilangan_nafsu_makan') ? 1 : 0,
                'gejala_mata_merah' => $request->has('gejala_mata_merah') ? 1 : 0,
                'gejala_pembengkakan_kelenjar' => $request->has('gejala_pembengkakan_kelenjar') ? 1 : 0,
                'gejala_kejang' => $request->has('gejala_kejang') ? 1 : 0,
                'gejala_penurunan_kesadaran' => $request->has('gejala_penurunan_kesadaran') ? 1 : 0,

                // Category E: History
                'riwayat_perjalanan' => $request->riwayat_perjalanan,
                'riwayat_kontak_kasus' => $request->has('riwayat_kontak_kasus') ? 1 : 0,
                'riwayat_imunisasi' => $request->riwayat_imunisasi,
                'tanggal_imunisasi_terakhir' => $request->tanggal_imunisasi_terakhir,

                // Category F: Laboratory
                'status_lab' => $request->status_lab,
                'tanggal_pengambilan_spesimen' => $request->tanggal_pengambilan_spesimen,
                'jenis_spesimen' => $request->jenis_spesimen,
                'hasil_lab' => $request->hasil_lab,
                'tanggal_hasil_lab' => $request->tanggal_hasil_lab,

                // Category G: Management
                'status_rawat' => $request->status_rawat,
                'nama_faskes_rawat' => $request->nama_faskes_rawat,
                'tanggal_masuk_rawat' => $request->tanggal_masuk_rawat,
                'tanggal_keluar_rawat' => $request->tanggal_keluar_rawat,

                // Category H: Final Status
                'kondisi_akhir' => $request->kondisi_akhir,
                'tanggal_kondisi_akhir' => $request->tanggal_kondisi_akhir,
                'penyebab_kematian' => $request->penyebab_kematian,

                // Category I: Contact Investigation
                'jumlah_kontak_serumah' => $request->jumlah_kontak_serumah ?? 0,
                'jumlah_kontak_diluar_rumah' => $request->jumlah_kontak_diluar_rumah ?? 0,
                'jumlah_kontak_bergejala' => $request->jumlah_kontak_bergejala ?? 0,
                'tindak_lanjut_kontak' => $request->tindak_lanjut_kontak,

                // Category J: Metadata
                'status_kasus' => $request->status_kasus,
                'id_faskes_pelapor' => $request->id_faskes_pelapor,
                'catatan_tambahan' => $request->catatan_tambahan,
                'updated_by' => Auth::id(),
            ]);

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
     */
    public function getDashboardStats()
    {
        $stats = [
            'total_cases' => $this->model->count(),
            'suspected_cases' => $this->model->byStatus('suspected')->count(),
            'probable_cases' => $this->model->byStatus('probable')->count(),
            'confirmed_cases' => $this->model->byStatus('confirmed')->count(),
            'discarded_cases' => $this->model->byStatus('discarded')->count(),
            'death_cases' => $this->model->byOutcome('meninggal')->count(),
            'recovered_cases' => $this->model->byOutcome('sembuh')->count(),
            'in_treatment_cases' => $this->model->byOutcome('dalam_perawatan')->count(),
        ];

        return $stats;
    }

    /**
     * Get cases grouped by geography
     */
    public function getCasesByGeography($level = 'kecamatan')
    {
        if ($level === 'kecamatan') {
            return $this->model
                ->select('id_kec', DB::raw('count(*) as total'))
                ->with('kecamatan:id,name')
                ->groupBy('id_kec')
                ->get();
        } elseif ($level === 'kelurahan') {
            return $this->model
                ->select('id_kel', DB::raw('count(*) as total'))
                ->with('kelurahan:id,name')
                ->groupBy('id_kel')
                ->get();
        } elseif ($level === 'rt') {
            return $this->model
                ->select('id_rt', 'id_kel', DB::raw('count(*) as total'))
                ->with(['rt:id,name', 'kelurahan:id,name'])
                ->groupBy('id_rt', 'id_kel')
                ->get();
        }

        return collect();
    }

    /**
     * Get cases trend over months
     */
    public function getCasesTrend($months = 12)
    {
        $startDate = Carbon::now()->subMonths($months);

        return $this->model
            ->select(
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
     * Get cases grouped by disease type
     */
    public function getCasesByDisease()
    {
        return $this->model
            ->select('id_jenis_kasus', DB::raw('count(*) as total'))
            ->with('jenisKasus:id,nama_penyakit,kode_penyakit')
            ->groupBy('id_jenis_kasus')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Get cases grouped by status
     */
    public function getCasesByStatus()
    {
        return $this->model
            ->select('status_kasus', DB::raw('count(*) as total'))
            ->groupBy('status_kasus')
            ->get();
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
