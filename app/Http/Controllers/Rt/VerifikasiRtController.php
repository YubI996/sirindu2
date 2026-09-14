<?php

namespace App\Http\Controllers\Rt;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\AnakTautan;
use App\Models\Rt;
use App\Models\VerifikasiAnak;
use App\Services\HashIdService;
use App\Services\TautanIdentitasService;
use App\Services\VerifikasiRtService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Halaman tunggal peran RT (spec §4): tab Warga RT & Belum ber-RT. Seluruh aturan di
 * VerifikasiRtService; controller hanya memilih RT, membentuk baris JSON, dan memetakan error.
 */
class VerifikasiRtController extends Controller
{
    private const LABEL_SUMBER = [
        'operasi_timbang' => 'OT',
        'capil'           => 'Capil',
        'manual'          => 'Manual',
        'dummy'           => 'Dummy',
    ];

    private const LABEL_VIA = [
        'kk'        => 'No KK sama',
        'ortu'      => 'Nama & orang tua mirip',
        'nama_kuat' => 'Nama & orang tua sangat mirip (tanggal beda)',
    ];

    /** Kolom identitas yang dibandingkan berdampingan di tab "Kemungkinan sama". */
    private const FIELD_BANDING = ['nik', 'nama', 'tgl_lahir', 'jk', 'nama_ibu', 'nama_ayah', 'no_kk', 'alamat', 'alamat_ktp', 'posyandu'];

    public function __construct(
        private readonly VerifikasiRtService $svc,
        private readonly TautanIdentitasService $tautan,
    ) {
    }

    public function index(Request $request): View
    {
        $rt = $this->rt($request);
        $rt->load('kelurahan');

        return view('rt.verifikasi', [
            'rt'      => $rt,
            'progres' => $this->svc->progres($rt),
        ]);
    }

    public function warga(Request $request): JsonResponse
    {
        $rt = $this->rt($request);

        return response()->json([
            'rows'    => $this->rows($this->svc->wargaQuery($rt)),
            'progres' => $this->svc->progres($rt),
        ]);
    }

    public function tanpaRt(Request $request): JsonResponse
    {
        $rt = $this->rt($request);

        return response()->json([
            'rows'    => $this->rows($this->svc->tanpaRtQuery($rt)),
            'progres' => $this->svc->progres($rt),
        ]);
    }

    /** Pasangan kandidat (spec §4 tab 3) — berdampingan, dengan daftar kolom yang berbeda. */
    public function kandidat(Request $request): JsonResponse
    {
        $rt = $this->rt($request);
        $rows = $this->tautan->kandidatUntukRt($rt)->map(function ($k) {
            $a = $this->row($k->anakA);
            $b = $this->row($k->anakB);
            $beda = array_values(array_filter(
                self::FIELD_BANDING,
                fn ($f) => trim((string) $a[$f]) !== trim((string) $b[$f])
            ));

            return [
                'a'         => $a,
                'b'         => $b,
                'skor'      => (float) $k->skor,
                'via'       => $k->via,
                'via_label' => self::LABEL_VIA[$k->via] ?? $k->via,
                'beda'      => $beda,
            ];
        })->values()->all();

        return response()->json(['rows' => $rows, 'jumlah' => count($rows)]);
    }

    /** Keputusan RT: sama / beda atas satu kandidat. */
    public function putuskan(Request $request): JsonResponse
    {
        $rt   = $this->rt($request);
        $data = $request->validate([
            'a'         => 'required|string',
            'b'         => 'required|string',
            'keputusan' => ['required', Rule::in(AnakTautan::KEPUTUSAN)],
            'catatan'   => 'nullable|string|max:1000',
        ]);
        $a = Anak::findByHashIdOrFail($data['a']);
        $b = Anak::findByHashIdOrFail($data['b']);

        try {
            $t = $this->tautan->putuskan($a->id, $b->id, $rt, $request->user(), $data['keputusan'], $data['catatan'] ?? null);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $t->id, 'keputusan' => $t->keputusan, 'status' => $t->status]);
    }

    public function usulkan(Request $request, Anak $anak): JsonResponse
    {
        $rt   = $this->rt($request);
        $data = $request->validate([
            'status'  => ['required', Rule::in(VerifikasiAnak::STATUS)],
            'catatan' => 'nullable|string|max:1000',
        ]);

        try {
            $v = $this->svc->usulkan($anak, $rt, $request->user(), $data['status'], $data['catatan'] ?? null);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $anak->refresh();

        return response()->json([
            'id'           => $anak->hashid,
            'verif_status' => $anak->verif_rt_status,
            'verif_label'  => $anak->verif_rt_status ? VerifikasiAnak::LABEL_STATUS[$anak->verif_rt_status] : null,
            'verif_reviu'  => $anak->verif_rt_reviu,
            'verif_at'     => $anak->verif_rt_at ? Carbon::parse($anak->verif_rt_at)->format('Y-m-d H:i') : null,
            'hilang'       => $v->status === 'bukan_rt_ini',
            'progres'      => $this->svc->progres($rt),
        ]);
    }

    /** RT milik user; superadmin boleh memilih lewat ?rt= untuk pratinjau/dukungan. */
    private function rt(Request $request): Rt
    {
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            abort_if(!$request->query('rt'), 403, 'Pilih RT lewat parameter ?rt=');
            return Rt::findOrFail((int) $request->query('rt'));
        }
        abort_if(!$user->isRt() || !$user->id_rt, 403);

        return Rt::findOrFail($user->id_rt);
    }

    private function rows(Builder $q): array
    {
        return $q->with('posyandu:id,name')
            ->orderBy('nama')
            ->get()
            ->map(fn (Anak $a) => $this->row($a))
            ->values()
            ->all();
    }

    /** Satu baris anak untuk JSON halaman RT (identitas lengkap, tanpa data kesehatan). */
    private function row(Anak $a): array
    {
        return [
                'id'           => HashIdService::encode($a->id, 'anak'),
                'sumber'       => $a->sumber,
                'sumber_label' => self::LABEL_SUMBER[$a->sumber] ?? ucfirst((string) $a->sumber),
                'nik'          => $a->nik,
                'nama'         => $a->nama,
                'jk'           => (int) $a->jk === 1 ? 'L' : 'P',
                'tgl_lahir'    => $a->tgl_lahir,
                'nama_ibu'     => $a->nama_ibu,
                'nama_ayah'    => $a->nama_ayah,
                'no_kk'        => $a->no_kk,
                'alamat'       => $a->alamat,
                'alamat_ktp'   => $a->alamat_ktp,
                'posyandu'     => $a->posyandu?->name,
                'verif_status' => $a->verif_rt_status,
                'verif_label'  => $a->verif_rt_status ? VerifikasiAnak::LABEL_STATUS[$a->verif_rt_status] : null,
                'verif_reviu'  => $a->verif_rt_reviu,
                'verif_at'     => $a->verif_rt_at ? Carbon::parse($a->verif_rt_at)->format('Y-m-d H:i') : null,
        ];
    }
}
