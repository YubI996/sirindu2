<?php

namespace App\Http\Controllers;

use App\Jobs\PindaiIdentitasJob;
use App\Models\AnakTautan;
use App\Models\Rt;
use App\Models\VerifikasiAnak;
use App\Services\TautanIdentitasService;
use App\Services\VerifikasiRtService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Antrean reviu usulan RT (spec §6.1–6.2): tab Status domisili & Tautan identitas.
 * Faskes melihat kelurahannya, superadmin semua.
 */
class VerifikasiRtReviuController extends Controller
{
    public function __construct(
        private readonly VerifikasiRtService $svc,
        private readonly TautanIdentitasService $tautan,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $q = $this->svc->antreanQuery($user);

        if ($request->query('rt')) {
            $q->where('id_rt', (int) $request->query('rt'));
        }
        if ($request->query('status') && in_array($request->query('status'), VerifikasiAnak::STATUS, true)) {
            $q->where('status', $request->query('status'));
        }

        $rtList = Rt::query()
            ->when(!$user->isSuperAdmin(), fn ($r) => $r->where('id_kelurahan', (int) $user->id_kel))
            ->orderBy('name')->get();

        // Tab "Dicurigai sama" hanya untuk Dinkes dan menjadi tab bawaannya; peran lain tetap mulai dari domisili.
        $dinkes = $user->isSuperAdmin();
        $tab = match (true) {
            $request->query('tab') === 'tautan'    => 'tautan',
            $request->query('tab') === 'domisili'  => 'domisili',
            $request->query('tab') === 'dicurigai' && $dinkes => 'dicurigai',
            default                                => $dinkes ? 'dicurigai' : 'domisili',
        };
        $antreanTautan = $this->tautan->antreanTautanQuery($user)
            ->paginate(30, ['*'], 'halaman_tautan')
            ->withQueryString();

        return view('admin.verifikasi-rt.index', [
            'antrean'           => $q->paginate(50)->withQueryString(),
            'rtList'            => $rtList,
            'label'             => VerifikasiAnak::LABEL_STATUS,
            'filter'            => ['rt' => $request->query('rt'), 'status' => $request->query('status')],
            'tab'               => $tab,
            'antreanTautan'     => $antreanTautan,
            'ringkasanKandidat' => $this->tautan->ringkasanKandidat(),
            'menungguGabung'    => $this->tautan->menungguGabung(),
            'kandidat'          => $tab === 'dicurigai'
                ? $this->tautan->kandidatDicurigaiQuery()->paginate(20, ['*'], 'halaman_kandidat')->withQueryString()
                : null,
            'jumlahDicurigai'   => $dinkes ? $this->tautan->kandidatDicurigaiQuery()->count() : 0,
            'sedangMemindai'    => Cache::has(PindaiIdentitasJob::KUNCI),
        ]);
    }

    public function tinjau(Request $request, VerifikasiAnak $verifikasi): RedirectResponse
    {
        $data = $request->validate([
            'setuju'  => 'required|boolean',
            'catatan' => 'nullable|string|max:1000',
        ]);

        try {
            $this->svc->tinjau($verifikasi, $request->user(), (bool) $data['setuju'], $data['catatan'] ?? null);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.verifikasiRt.index', ['tab' => 'domisili'] + $request->only('rt', 'status', 'page'))
            ->with('success', $data['setuju'] ? 'Usulan disetujui.' : 'Usulan ditolak.');
    }

    public function tinjauTautan(Request $request, AnakTautan $tautan): RedirectResponse
    {
        $data = $request->validate([
            'setuju'  => 'required|boolean',
            'catatan' => 'nullable|string|max:1000',
        ]);

        try {
            $this->tautan->tinjauTautan($tautan, $request->user(), (bool) $data['setuju'], $data['catatan'] ?? null);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.verifikasiRt.index', ['tab' => 'tautan'] + $request->only('rt', 'halaman_tautan'))
            ->with('success', $data['setuju'] ? 'Tautan disetujui.' : 'Tautan ditolak.');
    }

    /** Dinkes memutuskan langsung satu pasangan "Dicurigai sama": memutus + menyetujui sekaligus. */
    public function putuskan(Request $request): RedirectResponse
    {
        abort_if(!$request->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'id_anak_a'  => 'required|integer',
            'id_anak_b'  => 'required|integer',
            'keputusan'  => 'required|in:sama,beda',
            'catatan'    => 'nullable|string|max:1000',
        ]);

        try {
            $this->tautan->putuskanLangsung((int) $data['id_anak_a'], (int) $data['id_anak_b'], $request->user(), $data['keputusan'], $data['catatan'] ?? null);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (InvalidArgumentException | ModelNotFoundException $e) {
            return back()->with('error', $e instanceof ModelNotFoundException ? 'Salah satu anak sudah tidak ada.' : $e->getMessage());
        }

        return redirect()->route('admin.verifikasiRt.index', ['tab' => 'dicurigai'] + $request->only('halaman_kandidat'))
            ->with('success', $data['keputusan'] === 'sama'
                ? 'Ditandai SAMA dan disetujui. Pasangan menunggu di halaman Penggabungan.'
                : 'Ditandai BEDA orang dan disetujui. Pasangan tidak akan diusulkan lagi.');
    }

    /** Pindai ulang kandidat (antrean) — hanya Dinkes. Satu pindai pada satu waktu. */
    public function pindai(Request $request): RedirectResponse
    {
        abort_if(!$request->user()->isSuperAdmin(), 403);

        if (!Cache::add(PindaiIdentitasJob::KUNCI, now()->toDateTimeString(), PindaiIdentitasJob::KUNCI_DETIK)) {
            return redirect()->route('admin.verifikasiRt.index', ['tab' => 'dicurigai'])
                ->with('error', 'Pindai sebelumnya masih berjalan. Tunggu selesai, lalu muat ulang halaman.');
        }

        try {
            PindaiIdentitasJob::dispatch();
        } catch (\Throwable $e) {
            Cache::forget(PindaiIdentitasJob::KUNCI); // gagal diantrekan: jangan mengunci
            throw $e;
        }

        return redirect()->route('admin.verifikasiRt.index', ['tab' => 'dicurigai'])
            ->with('success', 'Pindai ulang dijadwalkan. Hasil muncul setelah worker antrean memprosesnya.');
    }
}
