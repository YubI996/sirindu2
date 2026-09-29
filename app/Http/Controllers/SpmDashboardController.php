<?php

namespace App\Http\Controllers;

use App\Models\SpmKategori;
use App\Support\CapaianSpm;
use App\Support\RingkasanSpm;
use App\Traits\MemakaiTahunSpm;
use Illuminate\Http\Request;

/**
 * Dasbor SPM — spec docs/superpowers/specs/2026-09-29-dasbor-spm-design.md §5.
 *
 * Server-render penuh dengan SATU query leftJoin. Datanya puluhan baris, bukan
 * populasi — peringatan chunking di CLAUDE.md tidak berlaku di sini, dan
 * memecahnya jadi endpoint JSON tidak meringankan apa pun.
 */
class SpmDashboardController extends Controller
{
    use MemakaiTahunSpm;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $tahun  = $this->tahunTervalidasi($request);
        $ambang = config('spm.ambang');

        $rows = SpmKategori::query()
            ->where('spm_kategori.is_active', true)
            ->leftJoin('spm_capaian', function ($join) use ($tahun) {
                $join->on('spm_capaian.id_kategori', '=', 'spm_kategori.id')
                     ->where('spm_capaian.tahun', '=', $tahun);
            })
            ->select([
                'spm_kategori.id',
                'spm_kategori.nama',
                'spm_kategori.satuan',
                'spm_kategori.keterangan',
                'spm_kategori.urutan',
                'spm_capaian.sasaran',
                'spm_capaian.tw1',
                'spm_capaian.tw2',
                'spm_capaian.tw3',
                'spm_capaian.tw4',
                'spm_capaian.catatan',
            ])
            ->orderBy('spm_kategori.urutan')
            ->orderBy('spm_kategori.nama')
            ->get();

        $baris = $rows->map(fn ($row) => [
            'kategori' => $row,
            'capaian'  => CapaianSpm::dari(
                $row->sasaran,
                [$row->tw1, $row->tw2, $row->tw3, $row->tw4],
                $tahun,
                $ambang,
            ),
        ])->all();

        return view('admin.spm.dashboard', [
            'tahun'      => $tahun,
            'tahunOpsi'  => $this->tahunOpsi(),
            'baris'      => $baris,
            'ringkasan'  => RingkasanSpm::dari(array_column($baris, 'capaian')),
            'statusMeta' => config('spm.status'),
            'grafik'     => $this->grafik($baris),
        ]);
    }

    /**
     * Data grafik: batang persen (urut tertinggal dulu) + garis kumulatif vs prorata.
     * Dipakai view lewat @json — tidak ada endpoint JSON terpisah.
     */
    private function grafik(array $baris): array
    {
        $batang = [];
        $garis  = [];

        foreach ($baris as $item) {
            /** @var CapaianSpm $capaian */
            $capaian = $item['capaian'];
            $status  = $capaian->status();

            $batang[] = [
                'nama'   => $item['kategori']->nama,
                'persen' => $capaian->persen(),
                'laju'   => $capaian->rasioLaju(),
                'status' => $status,
                'warna'  => config("spm.status.{$status}.warna"),
            ];

            $garis[] = [
                'id'        => $item['kategori']->id,
                'nama'      => $item['kategori']->nama,
                'satuan'    => $item['kategori']->satuan,
                'kumulatif' => $capaian->kumulatifPerTw(),
                'prorata'   => [
                    $capaian->prorataTw(1), $capaian->prorataTw(2),
                    $capaian->prorataTw(3), $capaian->prorataTw(4),
                ],
            ];
        }

        // Paling tertinggal di atas, diukur dengan LAJU (kumulatif ÷ prorata),
        // bukan persen mentah. Persen tanpa triwulan acuannya tidak bermakna:
        // 20% yang baru lapor TW I lebih sehat daripada 50% yang sudah lapor
        // empat triwulan, jadi mengurutkan dengan persen menaruh kategori merah
        // di bawah kategori kuning — persis kebalikan dari judul panelnya.
        // Batangnya tetap menampilkan persen; yang berubah hanya urutannya.
        usort($batang, function ($a, $b) {
            if ($a['laju'] === null && $b['laju'] === null) {
                return strcmp($a['nama'], $b['nama']);
            }
            if ($a['laju'] === null) {
                return 1;
            }
            if ($b['laju'] === null) {
                return -1;
            }

            return $a['laju'] <=> $b['laju'];
        });

        return ['batang' => $batang, 'garis' => $garis];
    }
}
