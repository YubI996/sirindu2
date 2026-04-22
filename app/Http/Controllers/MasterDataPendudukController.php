<?php

namespace App\Http\Controllers;

use App\Models\JumlahPenduduk;
use App\Models\Kelurahan;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class MasterDataPendudukController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('module.role:superadmin');
    }

    public function index()
    {
        $kelurahans = Kelurahan::orderBy('name')->get();
        $tahunList  = range(date('Y'), 2000, -1);

        return view('admin.master-data.penduduk.index', compact('kelurahans', 'tahunList'));
    }

    public function getData(Request $request)
    {
        $query = JumlahPenduduk::with('kelurahan')->select('jumlah_penduduk.*');

        if ($request->filled('tahun')) {
            $query->where('tahun', $request->tahun);
        }
        if ($request->filled('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        return DataTables::of($query)
            ->addColumn('nama_kelurahan', fn($r) => $r->kelurahan->name ?? '-')
            ->addColumn('jumlah_fmt', fn($r) => number_format($r->jumlah_penduduk, 0, ',', '.'))
            ->addColumn('action', function ($r) {
                $edit   = '<button class="btn btn-sm btn-warning btn-edit" data-id="' . $r->id . '" title="Edit"><i class="fa fa-edit"></i></button>';
                $delete = '<button class="btn btn-sm btn-danger btn-delete" data-id="' . $r->id . '" title="Hapus"><i class="fa fa-trash"></i></button>';
                return '<div class="btn-group">' . $edit . $delete . '</div>';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tahun'            => 'required|integer|min:2000|max:2100',
            'kategori'         => 'required|in:Total,Dibawah 15 Tahun',
            'id_kelurahan'     => 'required|exists:kelurahan,id',
            'jumlah_penduduk'  => 'required|integer|min:0',
        ], [
            'unique' => 'Data untuk tahun, kategori, dan kelurahan ini sudah ada.',
        ]);

        $exists = JumlahPenduduk::where('tahun', $validated['tahun'])
            ->where('kategori', $validated['kategori'])
            ->where('id_kelurahan', $validated['id_kelurahan'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'errors'  => ['tahun' => ['Data untuk kombinasi tahun, kategori, dan kelurahan ini sudah ada.']],
            ], 422);
        }

        JumlahPenduduk::create($validated);

        return response()->json(['success' => true, 'message' => 'Data penduduk berhasil ditambahkan']);
    }

    public function update(Request $request, $id)
    {
        $record = JumlahPenduduk::findOrFail($id);

        $validated = $request->validate([
            'tahun'            => 'required|integer|min:2000|max:2100',
            'kategori'         => 'required|in:Total,Dibawah 15 Tahun',
            'id_kelurahan'     => 'required|exists:kelurahan,id',
            'jumlah_penduduk'  => 'required|integer|min:0',
        ]);

        $exists = JumlahPenduduk::where('tahun', $validated['tahun'])
            ->where('kategori', $validated['kategori'])
            ->where('id_kelurahan', $validated['id_kelurahan'])
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'errors'  => ['tahun' => ['Data untuk kombinasi tahun, kategori, dan kelurahan ini sudah ada.']],
            ], 422);
        }

        $record->update($validated);

        return response()->json(['success' => true, 'message' => 'Data penduduk berhasil diperbarui']);
    }

    public function destroy($id)
    {
        JumlahPenduduk::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Data penduduk berhasil dihapus']);
    }
}
