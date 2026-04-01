<?php

namespace App\Http\Controllers;

use App\Models\JenisVaksin;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class MasterDataVaksinController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('module.role:superadmin');
    }

    public function index()
    {
        return view('admin.master-data.vaksin.index');
    }

    public function getData(Request $request)
    {
        $query = JenisVaksin::withTrashed()->with('kelompokVaksin');

        return DataTables::of($query)
            ->addColumn('kelompok_badge', function ($vaksin) {
                $kelompok = $vaksin->kelompokVaksin;
                if (!$kelompok) {
                    return '<span class="badge bg-secondary">-</span>';
                }
                $colors = ['IDL' => 'bg-primary', 'IBL' => 'bg-info', 'ISL' => 'bg-success'];
                $color = $colors[$kelompok->kode] ?? 'bg-secondary';
                return '<span class="badge ' . $color . '">' . e($kelompok->kode) . '</span>';
            })
            ->addColumn('status_badge', function ($vaksin) {
                if ($vaksin->trashed()) {
                    return '<span class="badge bg-danger">Dihapus</span>';
                }
                return $vaksin->aktif
                    ? '<span class="badge bg-success">Aktif</span>'
                    : '<span class="badge bg-secondary">Tidak Aktif</span>';
            })
            ->addColumn('action', function ($vaksin) {
                if ($vaksin->trashed()) {
                    $restoreBtn = '<button class="btn btn-sm btn-success btn-restore" data-id="' . $vaksin->id . '" title="Restore"><i class="fa fa-undo"></i></button>';
                    return '<div class="btn-group">' . $restoreBtn . '</div>';
                }
                $editBtn = '<button class="btn btn-sm btn-warning btn-edit" data-id="' . $vaksin->id . '" title="Edit"><i class="fa fa-edit"></i></button>';
                $toggleBtn = '<button class="btn btn-sm btn-info btn-toggle" data-id="' . $vaksin->id . '" title="Toggle Status"><i class="fa fa-sync-alt"></i></button>';
                $deleteBtn = '<button class="btn btn-sm btn-danger btn-delete" data-id="' . $vaksin->id . '" title="Hapus"><i class="fa fa-trash"></i></button>';
                return '<div class="btn-group">' . $editBtn . $toggleBtn . $deleteBtn . '</div>';
            })
            ->rawColumns(['kelompok_badge', 'status_badge', 'action'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50|regex:/^[A-Za-z0-9_-]+$/|unique:jenis_vaksin,kode',
            'nama' => 'required|string|max:255',
            'kategori' => 'required|in:Wajib,Tambahan,Booster',
            'usia_pemberian_min' => 'nullable|integer|min:0',
            'usia_pemberian_max' => 'nullable|integer|min:0',
            'interval_hari' => 'nullable|integer|min:0',
            'keterangan' => 'nullable|string',
            'aktif' => 'nullable|boolean',
        ]);

        $validated['aktif'] = $request->boolean('aktif', true);

        JenisVaksin::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis vaksin berhasil ditambahkan',
        ]);
    }

    public function update(Request $request, $id)
    {
        $vaksin = JenisVaksin::findOrFail($id);

        $validated = $request->validate([
            'kode' => 'required|string|max:50|regex:/^[A-Za-z0-9_-]+$/|unique:jenis_vaksin,kode,' . $id,
            'nama' => 'required|string|max:255',
            'kategori' => 'required|in:Wajib,Tambahan,Booster',
            'usia_pemberian_min' => 'nullable|integer|min:0',
            'usia_pemberian_max' => 'nullable|integer|min:0',
            'interval_hari' => 'nullable|integer|min:0',
            'keterangan' => 'nullable|string',
            'aktif' => 'nullable|boolean',
        ]);

        $validated['aktif'] = $request->boolean('aktif', true);

        $vaksin->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis vaksin berhasil diperbarui',
        ]);
    }

    public function toggleStatus($id)
    {
        $vaksin = JenisVaksin::findOrFail($id);
        $vaksin->aktif = !$vaksin->aktif;
        $vaksin->save();

        return response()->json([
            'success' => true,
            'message' => 'Status vaksin berhasil diubah menjadi ' . ($vaksin->aktif ? 'Aktif' : 'Tidak Aktif'),
        ]);
    }

    public function restore($id)
    {
        $vaksin = JenisVaksin::onlyTrashed()->findOrFail($id);
        $vaksin->restore();

        return response()->json([
            'success' => true,
            'message' => 'Jenis vaksin berhasil di-restore',
        ]);
    }

    public function destroy($id)
    {
        $vaksin = JenisVaksin::findOrFail($id);
        $childCount = $vaksin->imunisasi()->count();

        if ($childCount > 0) {
            // Soft-delete: record has child references
            $vaksin->delete();

            return response()->json([
                'success' => true,
                'message' => 'Jenis vaksin di-nonaktifkan (soft-delete) karena masih digunakan oleh ' . $childCount . ' data imunisasi.',
                'soft_deleted' => true,
            ]);
        }

        // Hard-delete: no child references
        $vaksin->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Jenis vaksin berhasil dihapus',
        ]);
    }
}
