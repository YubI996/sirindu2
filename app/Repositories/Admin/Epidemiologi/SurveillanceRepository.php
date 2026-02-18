<?php

namespace App\Repositories\Admin\Epidemiologi;

use App\Models\SurveillanceCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SurveillanceRepository
{
    public function storeCase($request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->except(['_token']);
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();

            if (!empty($data['tanggal_lahir'])) {
                $data['kategori_umur'] = $this->getKategoriUmur(
                    \Carbon\Carbon::parse($data['tanggal_lahir'])->age
                );
            }

            // Cast boolean gejala fields
            $gejalaFields = [
                'gejala_demam', 'gejala_ruam', 'gejala_batuk', 'gejala_pilek',
                'gejala_konjungtivitis', 'gejala_sesak_napas', 'gejala_nyeri_tenggorokan',
                'gejala_membran_tenggorokan', 'gejala_kejang', 'gejala_lumpuh_layuh',
                'gejala_kaku_rahang', 'gejala_spasme', 'gejala_tali_pusat',
                'gejala_diare', 'gejala_muntah', 'gejala_pendarahan', 'gejala_nyeri_sendi',
            ];
            foreach ($gejalaFields as $field) {
                $data[$field] = isset($data[$field]) ? 1 : 0;
            }

            return SurveillanceCase::create($data);
        });
    }

    public function updateCase($request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $case = SurveillanceCase::findOrFail($id);
            $data = $request->except(['_token', '_method']);
            $data['updated_by'] = Auth::id();

            if (!empty($data['tanggal_lahir'])) {
                $data['kategori_umur'] = $this->getKategoriUmur(
                    \Carbon\Carbon::parse($data['tanggal_lahir'])->age
                );
            }

            $gejalaFields = [
                'gejala_demam', 'gejala_ruam', 'gejala_batuk', 'gejala_pilek',
                'gejala_konjungtivitis', 'gejala_sesak_napas', 'gejala_nyeri_tenggorokan',
                'gejala_membran_tenggorokan', 'gejala_kejang', 'gejala_lumpuh_layuh',
                'gejala_kaku_rahang', 'gejala_spasme', 'gejala_tali_pusat',
                'gejala_diare', 'gejala_muntah', 'gejala_pendarahan', 'gejala_nyeri_sendi',
            ];
            foreach ($gejalaFields as $field) {
                $data[$field] = isset($data[$field]) ? 1 : 0;
            }

            $case->update($data);
            return $case;
        });
    }

    public function deleteCase($id)
    {
        $case = SurveillanceCase::findOrFail($id);
        return $case->delete();
    }

    public function getDashboardStats()
    {
        return [
            'total'            => SurveillanceCase::count(),
            'bulan_ini'        => SurveillanceCase::whereMonth('tanggal_lapor', now()->month)
                                    ->whereYear('tanggal_lapor', now()->year)->count(),
            'konfirmasi'       => SurveillanceCase::where('status_kasus', 'konfirmasi')->count(),
            'suspek'           => SurveillanceCase::where('status_kasus', 'suspek')->count(),
            'meninggal'        => SurveillanceCase::where('kondisi_akhir', 'meninggal')->count(),
            'aktif'            => SurveillanceCase::where('kondisi_akhir', 'dalam_perawatan')->count(),
            'lab_positif'      => SurveillanceCase::where('status_lab', 'positif')->count(),
            'minggu_ini'       => SurveillanceCase::whereBetween('tanggal_lapor', [now()->startOfWeek(), now()->endOfWeek()])->count(),
        ];
    }

    public function getCasesByGeography($level = 'kecamatan')
    {
        if ($level === 'kelurahan') {
            return SurveillanceCase::select('id_kel', DB::raw('count(*) as total'))
                ->with('kelurahan')
                ->groupBy('id_kel')
                ->get();
        }
        return SurveillanceCase::select('id_kec', DB::raw('count(*) as total'))
            ->with('kecamatan')
            ->groupBy('id_kec')
            ->get();
    }

    public function getCasesTrend($months = 12)
    {
        return SurveillanceCase::select(
                DB::raw('YEAR(tanggal_onset) as tahun'),
                DB::raw('MONTH(tanggal_onset) as bulan'),
                DB::raw('count(*) as total')
            )
            ->where('tanggal_onset', '>=', now()->subMonths($months))
            ->groupBy('tahun', 'bulan')
            ->orderBy('tahun')
            ->orderBy('bulan')
            ->get();
    }

    public function getCasesByDisease()
    {
        return SurveillanceCase::select('id_jenis_kasus', DB::raw('count(*) as total'))
            ->with('jenisKasus')
            ->groupBy('id_jenis_kasus')
            ->orderByDesc('total')
            ->get();
    }

    public function getCasesByStatus()
    {
        return SurveillanceCase::select('status_kasus', DB::raw('count(*) as total'))
            ->groupBy('status_kasus')
            ->get();
    }

    private function getKategoriUmur($umurTahun)
    {
        if ($umurTahun < 1)  return 'bayi';
        if ($umurTahun < 5)  return 'balita';
        if ($umurTahun < 12) return 'anak';
        if ($umurTahun < 18) return 'remaja';
        if ($umurTahun < 60) return 'dewasa';
        return 'lansia';
    }
}
