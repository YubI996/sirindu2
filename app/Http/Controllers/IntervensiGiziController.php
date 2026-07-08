<?php

namespace App\Http\Controllers;

use App\Models\IntervensiGizi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Services\IntervensiGiziService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IntervensiGiziController extends Controller
{
    public function __construct(private IntervensiGiziService $service)
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $f = $this->filters($request);
        $rekap  = $this->service->rekap($f);
        $daftar = $this->service->daftarPrioritas($f);

        $isSuper = auth()->user()->isSuperAdmin();
        $kecamatanList = $isSuper ? Kecamatan::orderBy('name')->get() : collect();
        $kelurahanList = $isSuper ? Kelurahan::orderBy('name')->get() : collect();

        return view('admin.intervensi.index', [
            'rekap'         => $rekap,
            'daftar'        => $daftar,
            'kecamatanList' => $kecamatanList,
            'kelurahanList' => $kelurahanList,
            'isSuper'       => $isSuper,
            'jenisList'     => IntervensiGizi::JENIS,
            'statusList'    => IntervensiGizi::STATUS,
            'filter'        => $f,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_anak'   => 'required|integer|exists:anak,id',
            'jenis'     => ['required', Rule::in(IntervensiGizi::JENIS)],
            'status'    => ['required', Rule::in(IntervensiGizi::STATUS)],
            'tanggal'   => 'nullable|date',
            'pelaksana' => 'nullable|string|max:255',
            'catatan'   => 'nullable|string|max:2000',
        ]);
        $data['created_by'] = auth()->id();
        IntervensiGizi::create($data);

        return redirect()->route('admin.intervensi.index')->with('success', 'Intervensi ditambahkan.');
    }

    public function update(Request $request, IntervensiGizi $intervensi)
    {
        $data = $request->validate([
            'jenis'     => ['required', Rule::in(IntervensiGizi::JENIS)],
            'status'    => ['required', Rule::in(IntervensiGizi::STATUS)],
            'tanggal'   => 'nullable|date',
            'pelaksana' => 'nullable|string|max:255',
            'catatan'   => 'nullable|string|max:2000',
        ]);
        $intervensi->update($data);

        return redirect()->route('admin.intervensi.index')->with('success', 'Intervensi diperbarui.');
    }

    public function destroy(IntervensiGizi $intervensi)
    {
        $intervensi->delete();

        return redirect()->route('admin.intervensi.index')->with('success', 'Intervensi dihapus.');
    }

    /** Filter wilayah + scoping: user non-super dikunci ke kelurahannya. */
    private function filters(Request $request): array
    {
        $user = auth()->user();
        $rt       = $request->query('rt') ? (int) $request->query('rt') : null;
        $posyandu = $request->query('posyandu') ? (int) $request->query('posyandu') : null;

        if (!$user->isSuperAdmin()) {
            return [
                'kec'      => null,
                'kel'      => $user->id_kel ? (int) $user->id_kel : null,
                'rt'       => $rt,
                'posyandu' => $posyandu,
            ];
        }

        return [
            'kec'      => $request->query('kecamatan') ? (int) $request->query('kecamatan') : null,
            'kel'      => $request->query('kelurahan') ? (int) $request->query('kelurahan') : null,
            'rt'       => $rt,
            'posyandu' => $posyandu,
        ];
    }
}
