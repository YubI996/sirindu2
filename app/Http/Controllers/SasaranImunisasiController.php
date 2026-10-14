<?php

namespace App\Http\Controllers;

use App\Exports\SasaranImunisasiExport;
use App\Services\ImunisasiStatusService;
use App\Support\KohortImunisasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daftar nama dan alamat (BNBA) di balik kartu Data sasaran dasbor imunisasi (BBL, SI, Baduta),
 * dengan filter tahun dan wilayah yang sama persis dengan dasbor — jumlah barisnya = angka kartu.
 * Pola modal mengikuti dasbor Operasi Timbang (TimbangDashboardController::daftar).
 */
class SasaranImunisasiController extends Controller
{
    /** Kunci URL → kelompok KohortImunisasi + label tampilan. */
    public const KELOMPOK = [
        'bbl'    => ['kode' => 'BBL',    'label' => 'BBL (Bayi Baru Lahir)'],
        'si'     => ['kode' => 'SI',     'label' => 'SI (Surviving Infant)'],
        'baduta' => ['kode' => 'BADUTA', 'label' => 'Baduta (Bawah Dua Tahun)'],
    ];

    public function __construct(private ImunisasiStatusService $service)
    {
    }

    /**
     * Filter wilayah dasbor imunisasi dari query string. Dipakai juga oleh
     * AdminController::imunisasiDashboard supaya kartu dan daftar tak pernah beda tafsir.
     *
     * @return array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_puskesmas?: int, id_posyandu?: int}
     */
    public static function filterDasbor(Request $request): array
    {
        return array_filter([
            'id_kecamatan' => $request->integer('id_kecamatan') ?: null,
            'id_kelurahan' => $request->integer('id_kelurahan') ?: null,
            'id_rt'        => $request->integer('id_rt') ?: null,
            'id_puskesmas' => $request->integer('id_puskesmas') ?: null,
            'id_posyandu'  => $request->integer('id_posyandu') ?: null,
        ]);
    }

    public function daftar(Request $request): JsonResponse
    {
        [$kelompok, $kohort] = $this->kelompokDanKohort($request);

        return response()->json([
            'kelompok' => $kelompok,
            'judul'    => self::KELOMPOK[$kelompok]['label'] . ' · Kohort ' . $kohort->tahun(),
            'rows'     => $this->service->getDaftarSasaran($kohort, self::KELOMPOK[$kelompok]['kode'], self::filterDasbor($request)),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$kelompok, $kohort] = $this->kelompokDanKohort($request);
        $rows = $this->service->getDaftarSasaran($kohort, self::KELOMPOK[$kelompok]['kode'], self::filterDasbor($request));

        return (new SasaranImunisasiExport($rows, strtoupper($kelompok) . ' Kohort ' . $kohort->tahun()))
            ->unduh('sasaran-' . $kelompok . '-kohort-' . $kohort->tahun() . '-' . now()->format('Ymd_His') . '.xlsx');
    }

    /** @return array{0: string, 1: KohortImunisasi} */
    private function kelompokDanKohort(Request $request): array
    {
        $kelompok = (string) $request->query('kelompok');
        abort_unless(array_key_exists($kelompok, self::KELOMPOK), 404);

        return [$kelompok, KohortImunisasi::dari(KohortImunisasi::tahunTervalidasi($request->query('tahun')))];
    }
}
