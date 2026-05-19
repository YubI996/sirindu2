<?php

namespace App\Http\Controllers;

use App\Exports\AgregatImunisasiExport;
use App\Exports\ImunisasiExport;
use App\Models\Imunisasi;
use App\Models\JenisVaksin;
use App\Models\Kelurahan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\DataTables;

class ExportImunisasiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if ($user->isFaskesSurveilans()) {
                abort(403, 'Akses tidak diizinkan.');
            }
            return $next($request);
        });
    }

    public function index()
    {
        $kelurahanList = Kelurahan::orderBy('name')->get();
        $vaksinList = JenisVaksin::aktif()->orderBy('nama')->get();

        return view('admin.export.imunisasi', compact('kelurahanList', 'vaksinList'));
    }

    public function getData(Request $request)
    {
        $query = Imunisasi::query()
            ->with(['anak.kel', 'anak.kec', 'anak.posyandu', 'jenisVaksin']);

        if ($request->filled('bulan')) {
            $parts = explode('-', $request->bulan);
            if (count($parts) === 2) {
                $query->whereYear('tanggal_pemberian', $parts[0])
                      ->whereMonth('tanggal_pemberian', $parts[1]);
            }
        }

        if ($request->filled('kelurahan')) {
            $kelurahan = $request->kelurahan;
            $query->whereHas('anak', function ($q) use ($kelurahan) {
                $q->where('id_kel', $kelurahan);
            });
        }

        if ($request->filled('antigen')) {
            $query->where('id_jenis_vaksin', $request->antigen);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $query->orderBy('tanggal_pemberian', 'desc');

        return DataTables::of($query)
            ->addColumn('nama_anak', function ($row) {
                return $row->anak?->nama ?? '-';
            })
            ->addColumn('nik', function ($row) {
                return $row->anak?->nik ?? '-';
            })
            ->addColumn('jenis_kelamin', function ($row) {
                return ($row->anak?->jk) == 1 ? 'Laki-laki' : 'Perempuan';
            })
            ->addColumn('tanggal_lahir', function ($row) {
                return $row->anak?->tgl_lahir ? Carbon::parse($row->anak->tgl_lahir)->format('d/m/Y') : '-';
            })
            ->addColumn('kelurahan', function ($row) {
                return $row->anak?->kel?->name ?? '-';
            })
            ->addColumn('kecamatan', function ($row) {
                return $row->anak?->kec?->name ?? '-';
            })
            ->addColumn('posyandu', function ($row) {
                return $row->anak?->posyandu?->name ?? '-';
            })
            ->addColumn('jenis_vaksin', function ($row) {
                return $row->jenisVaksin?->nama ?? '-';
            })
            ->addColumn('tanggal_pemberian_fmt', function ($row) {
                return $row->tanggal_pemberian ? $row->tanggal_pemberian->format('d/m/Y') : '-';
            })
            ->addColumn('status_badge', function ($row) {
                return match ($row->status) {
                    'sudah' => '<span class="badge bg-success">Sudah</span>',
                    'belum' => '<span class="badge bg-warning">Belum</span>',
                    'terlambat' => '<span class="badge bg-danger">Terlambat</span>',
                    default => '<span class="badge bg-secondary">-</span>',
                };
            })
            ->rawColumns(['status_badge'])
            ->make(true);
    }

    public function download(Request $request)
    {
        $request->validate([
            'bulan'    => 'nullable|date_format:Y-m',
            'kelurahan'=> 'nullable|integer|exists:kelurahan,id',
            'antigen'  => 'nullable|integer|exists:jenis_vaksin,id',
            'status'   => 'nullable|in:belum,sudah,terlambat',
        ]);

        $export = new ImunisasiExport(
            $request->bulan,
            $request->kelurahan,
            $request->antigen,
            $request->status
        );

        return Excel::download($export, $export->filename());
    }

    public function downloadAgregat(Request $request)
    {
        $request->validate([
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2020|max:2099',
        ]);

        $export = new AgregatImunisasiExport(
            (int) $request->bulan,
            (int) $request->tahun
        );

        return Excel::download($export, $export->filename());
    }
}
