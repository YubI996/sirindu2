<?php

namespace App\Http\Controllers;

use App\Models\JenisKasusEpidemiologi;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class MasterDataPenyakitController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('module.role:superadmin');
    }

    public function index()
    {
        return view('admin.master-data.penyakit.index');
    }

    public function getData(Request $request)
    {
        $query = JenisKasusEpidemiologi::withTrashed();

        return DataTables::of($query)
            ->addColumn('kategori_badge', function ($penyakit) {
                $colors = [
                    'PD3I' => 'bg-danger',
                    'menular_langsung' => 'bg-warning',
                    'vector_borne' => 'bg-info',
                    'zoonosis' => 'bg-primary',
                    'lainnya' => 'bg-secondary',
                ];
                $color = $colors[$penyakit->kategori] ?? 'bg-secondary';
                $label = str_replace('_', ' ', ucfirst($penyakit->kategori));
                return '<span class="badge ' . $color . '">' . $label . '</span>';
            })
            ->addColumn('status_badge', function ($penyakit) {
                if ($penyakit->trashed()) {
                    return '<span class="badge bg-danger">Dihapus</span>';
                }
                return $penyakit->is_active
                    ? '<span class="badge bg-success">Aktif</span>'
                    : '<span class="badge bg-secondary">Tidak Aktif</span>';
            })
            ->addColumn('action', function ($penyakit) {
                if ($penyakit->trashed()) {
                    $restoreBtn = '<button class="btn btn-sm btn-success btn-restore" data-id="' . $penyakit->id . '" title="Restore"><i class="fa fa-undo"></i></button>';
                    return '<div class="btn-group">' . $restoreBtn . '</div>';
                }
                $editBtn = '<button class="btn btn-sm btn-warning btn-edit" data-id="' . $penyakit->id . '" title="Edit"><i class="fa fa-edit"></i></button>';
                $toggleBtn = '<button class="btn btn-sm btn-info btn-toggle" data-id="' . $penyakit->id . '" title="Toggle Status"><i class="fa fa-sync-alt"></i></button>';
                $deleteBtn = '<button class="btn btn-sm btn-danger btn-delete" data-id="' . $penyakit->id . '" title="Hapus"><i class="fa fa-trash"></i></button>';
                return '<div class="btn-group">' . $editBtn . $toggleBtn . $deleteBtn . '</div>';
            })
            ->rawColumns(['kategori_badge', 'status_badge', 'action'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_penyakit' => 'required|string|max:20|regex:/^[A-Za-z0-9_]+$/|unique:jenis_kasus_epidemiologi,kode_penyakit',
            'nama_penyakit' => 'required|string|max:255',
            'kategori' => 'required|in:PD3I,menular_langsung,vector_borne,zoonosis,lainnya',
            'deskripsi' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        JenisKasusEpidemiologi::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis penyakit berhasil ditambahkan',
        ]);
    }

    public function update(Request $request, $id)
    {
        $penyakit = JenisKasusEpidemiologi::findOrFail($id);

        $validated = $request->validate([
            'kode_penyakit' => 'required|string|max:20|regex:/^[A-Za-z0-9_]+$/|unique:jenis_kasus_epidemiologi,kode_penyakit,' . $id,
            'nama_penyakit' => 'required|string|max:255',
            'kategori' => 'required|in:PD3I,menular_langsung,vector_borne,zoonosis,lainnya',
            'deskripsi' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $penyakit->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis penyakit berhasil diperbarui',
        ]);
    }

    public function toggleStatus($id)
    {
        $penyakit = JenisKasusEpidemiologi::findOrFail($id);
        $penyakit->is_active = !$penyakit->is_active;
        $penyakit->save();

        return response()->json([
            'success' => true,
            'message' => 'Status penyakit berhasil diubah menjadi ' . ($penyakit->is_active ? 'Aktif' : 'Tidak Aktif'),
        ]);
    }

    public function restore($id)
    {
        $penyakit = JenisKasusEpidemiologi::onlyTrashed()->findOrFail($id);
        $penyakit->restore();

        return response()->json([
            'success' => true,
            'message' => 'Jenis penyakit berhasil di-restore',
        ]);
    }

    public function destroy($id)
    {
        $penyakit = JenisKasusEpidemiologi::findOrFail($id);
        $childCount = $penyakit->surveillanceCases()->count();

        if ($childCount > 0) {
            // Soft-delete: record has child references
            $penyakit->delete();

            return response()->json([
                'success' => true,
                'message' => 'Jenis penyakit di-nonaktifkan (soft-delete) karena masih digunakan oleh ' . $childCount . ' data kasus surveilans.',
                'soft_deleted' => true,
            ]);
        }

        // Hard-delete: no child references
        $penyakit->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Jenis penyakit berhasil dihapus',
        ]);
    }
}
