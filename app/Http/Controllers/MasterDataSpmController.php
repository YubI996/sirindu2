<?php

namespace App\Http\Controllers;

use App\Models\SpmKategori;
use App\Support\CapaianSpm;
use App\Traits\MemakaiTahunSpm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

/**
 * Master data SPM — spec docs/superpowers/specs/2026-09-29-dasbor-spm-design.md §4.
 * Definisi kategori (jarang berubah) dan angka per tahun (4× setahun) diedit
 * lewat dua aksi terpisah karena umurnya berbeda.
 */
class MasterDataSpmController extends Controller
{
    use MemakaiTahunSpm;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('module.role:superadmin');
    }

    public function index(Request $request)
    {
        return view('admin.master-data.spm.index', [
            'tahun'     => $this->tahunTervalidasi($request),
            'tahunOpsi' => $this->tahunOpsi(),
        ]);
    }

    public function getData(Request $request)
    {
        $tahun  = $this->tahunTervalidasi($request);
        $ambang = config('spm.ambang');
        $status = config('spm.status');

        $query = SpmKategori::withTrashed()
            ->leftJoin('spm_capaian', function ($join) use ($tahun) {
                $join->on('spm_capaian.id_kategori', '=', 'spm_kategori.id')
                     ->where('spm_capaian.tahun', '=', $tahun);
            })
            ->select([
                'spm_kategori.*',
                'spm_capaian.sasaran',
                'spm_capaian.tw1',
                'spm_capaian.tw2',
                'spm_capaian.tw3',
                'spm_capaian.tw4',
                'spm_capaian.catatan',
            ]);

        $hitung = fn ($row) => CapaianSpm::dari(
            $row->sasaran,
            [$row->tw1, $row->tw2, $row->tw3, $row->tw4],
            $tahun,
            $ambang,
        );

        // `nama` & `satuan` TIDAK di-escape manual di sini: Yajra sudah meng-escape
        // setiap kolom yang tidak terdaftar di rawColumns. Menambah e() membuatnya
        // dua kali, dan nama kategori tercetak sebagai "&lt;script&gt;" harfiah.
        return DataTables::of($query)
            ->addColumn('kumulatif', fn ($row) => $hitung($row)->kumulatif())
            ->addColumn('persen_badge', function ($row) use ($hitung) {
                $persen = $hitung($row)->persen();

                return $persen === null
                    ? '<span class="text-muted">—</span>'
                    : '<strong>' . number_format($persen, 1, ',', '.') . '%</strong>';
            })
            ->addColumn('status_badge', function ($row) use ($hitung, $status) {
                $capaian = $hitung($row);
                $meta    = $status[$capaian->status()];
                $badge   = '<span class="badge ' . $meta['badge'] . '">' . e($meta['label']) . '</span>';

                if ($capaian->laporanTertinggal() && $capaian->twKalender() > 0) {
                    $badge .= ' <span class="badge bg-secondary">TW ' . $capaian->twKalender() . ' belum masuk</span>';
                }

                return $badge;
            })
            ->addColumn('action', function ($row) {
                if ($row->trashed()) {
                    return '<div class="btn-group"><button class="btn btn-sm btn-success btn-restore" data-id="' . $row->id . '" title="Pulihkan"><i class="fa fa-undo"></i></button></div>';
                }

                return '<div class="btn-group">'
                    . '<button class="btn btn-sm btn-primary btn-angka" data-id="' . $row->id . '" title="Isi angka"><i class="fa fa-calculator"></i></button>'
                    . '<button class="btn btn-sm btn-warning btn-edit" data-id="' . $row->id . '" title="Edit kategori"><i class="fa fa-edit"></i></button>'
                    . '<button class="btn btn-sm btn-info btn-toggle" data-id="' . $row->id . '" title="Aktif / nonaktif"><i class="fa fa-sync-alt"></i></button>'
                    . '<button class="btn btn-sm btn-danger btn-delete" data-id="' . $row->id . '" title="Hapus"><i class="fa fa-trash"></i></button>'
                    . '</div>';
            })
            ->rawColumns(['persen_badge', 'status_badge', 'action'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->aturanKategori());
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['urutan'] = (int) $request->input('urutan', 0);

        SpmKategori::create($validated);

        return response()->json(['success' => true, 'message' => 'Kategori SPM berhasil ditambahkan']);
    }

    public function update(Request $request, $id)
    {
        $kategori = SpmKategori::findOrFail($id);

        $validated = $request->validate($this->aturanKategori($id));
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['urutan'] = (int) $request->input('urutan', 0);

        $kategori->update($validated);

        return response()->json(['success' => true, 'message' => 'Kategori SPM berhasil diperbarui']);
    }

    public function toggleStatus($id)
    {
        $kategori = SpmKategori::findOrFail($id);
        $kategori->update(['is_active' => !$kategori->is_active]);

        return response()->json([
            'success' => true,
            'message' => $kategori->is_active ? 'Kategori diaktifkan' : 'Kategori dinonaktifkan',
        ]);
    }

    public function destroy($id)
    {
        SpmKategori::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori dihapus. Angka tahun-tahun sebelumnya tetap tersimpan.',
        ]);
    }

    public function restore($id)
    {
        SpmKategori::withTrashed()->findOrFail($id)->restore();

        return response()->json(['success' => true, 'message' => 'Kategori dipulihkan']);
    }

    /** Keunikan nama mengabaikan baris terhapus supaya namanya bisa dipakai lagi. */
    private function aturanKategori($id = null): array
    {
        return [
            'nama' => [
                'required', 'string', 'max:200',
                Rule::unique('spm_kategori', 'nama')->whereNull('deleted_at')->ignore($id),
            ],
            'satuan'     => ['required', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
            'urutan'     => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }
}
