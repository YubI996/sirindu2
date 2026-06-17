<?php

namespace App\Services;

use App\Models\Anak;
use App\Models\Imunisasi;
use App\Models\JenisVaksin;
use App\Models\KelompokVaksin;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ImunisasiStatusService
{
    private const HPV_CODES = ['HPV', 'HPV1', 'HPV2'];

    /**
     * Determine immunization status for one vaccine relative to a child.
     *
     * Returns: 'sudah' | 'belum' | 'terlambat' | 'kadaluarsa' | 'tidak_relevan'
     */
    public function getVaccineStatus(Anak $anak, JenisVaksin $vaksin, ?Imunisasi $record): string
    {
        // HPV is not relevant for males
        if (in_array($vaksin->kode, self::HPV_CODES) && $anak->jk == 1) {
            return 'tidak_relevan';
        }

        if ($record && $record->status === 'sudah') {
            return 'sudah';
        }

        $usiaSaatIni = Carbon::parse($anak->tgl_lahir)->diffInDays(now());

        // HB0 and other non-catchable vaccines
        if (!$vaksin->bisa_dikejar && $usiaSaatIni > $vaksin->usia_pemberian_max) {
            return 'kadaluarsa';
        }

        // Per-vaccine catch-up deadline exceeded
        if ($vaksin->catchup_max_hari && $usiaSaatIni > $vaksin->catchup_max_hari) {
            return 'kadaluarsa';
        }

        // Schedule overdue but still catchable
        if ($usiaSaatIni > $vaksin->usia_pemberian_max) {
            return 'terlambat';
        }

        return 'belum';
    }

    /**
     * Return all vaccine schedules for a child with computed status.
     *
     * @return array<int, array{vaksin: JenisVaksin, tanggal_min: string, tanggal_max: string,
     *                          catchup_deadline: string|null, status: string, imunisasi: Imunisasi|null}>
     */
    public function getJadwal(Anak $anak): array
    {
        $jenisVaksin = JenisVaksin::aktif()->get();
        $imunisasiDiberikan = Imunisasi::where('id_anak', $anak->id)
            ->get()
            ->keyBy('id_jenis_vaksin');

        $jadwal = [];
        foreach ($jenisVaksin as $vaksin) {
            $record = $imunisasiDiberikan->get($vaksin->id);
            $status = $this->getVaccineStatus($anak, $vaksin, $record);

            $tanggalMin = date('Y-m-d', strtotime($anak->tgl_lahir . ' +' . $vaksin->usia_pemberian_min . ' days'));
            $tanggalMax = date('Y-m-d', strtotime($anak->tgl_lahir . ' +' . $vaksin->usia_pemberian_max . ' days'));
            $catchupDeadline = $vaksin->catchup_max_hari
                ? date('Y-m-d', strtotime($anak->tgl_lahir . ' +' . $vaksin->catchup_max_hari . ' days'))
                : null;

            $jadwal[] = [
                'vaksin'           => $vaksin,
                'tanggal_min'      => $tanggalMin,
                'tanggal_max'      => $tanggalMax,
                'catchup_deadline' => $catchupDeadline,
                'status'           => $status,
                'imunisasi'        => $record,
            ];
        }

        return $jadwal;
    }

    /**
     * Return vaccines that are overdue (terlambat) for a child, ordered by priority.
     * These are the vaccines that should be given in the catch-up session.
     *
     * @return array<int, array{vaksin: JenisVaksin, tanggal_anjuran: string, catatan: string}>
     */
    public function getCatchupPlan(Anak $anak): array
    {
        $jadwal = $this->getJadwal($anak);
        $plan = [];
        $today = now()->toDateString();

        foreach ($jadwal as $item) {
            if ($item['status'] !== 'terlambat') {
                continue;
            }

            $vaksin = $item['vaksin'];
            $catatan = '';

            // DPT: minimum 28-day interval between doses
            if (str_starts_with($vaksin->kode, 'DPT') && $vaksin->interval_hari) {
                $lastDpt = Imunisasi::where('id_anak', $anak->id)
                    ->whereHas('jenisVaksin', fn($q) => $q->where('kode', 'like', 'DPT%'))
                    ->where('status', 'sudah')
                    ->orderByDesc('tanggal_pemberian')
                    ->first();

                if ($lastDpt && $lastDpt->tanggal_pemberian) {
                    $earliest = $lastDpt->tanggal_pemberian->addDays($vaksin->interval_hari)->toDateString();
                    if ($earliest > $today) {
                        $catatan = 'Paling cepat: ' . \Carbon\Carbon::parse($earliest)->isoFormat('D MMMM Y');
                        $today_use = $earliest;
                    } else {
                        $today_use = $today;
                    }
                } else {
                    $today_use = $today;
                }
            } else {
                $today_use = $today;
            }

            $plan[] = [
                'vaksin'         => $vaksin,
                'tanggal_anjuran' => $today_use,
                'catatan'         => $catatan,
            ];
        }

        // Sort by usia_pemberian_min (give earlier vaccines first)
        usort($plan, fn($a, $b) => $a['vaksin']->usia_pemberian_min <=> $b['vaksin']->usia_pemberian_min);

        return $plan;
    }

    /**
     * IDL completeness: true if child has received all IDL vaccines that are applicable.
     */
    public function isIdlLengkap(Anak $anak): bool
    {
        $idl = KelompokVaksin::where('kode', 'IDL')->with('jenisVaksin')->first();
        if (!$idl) {
            return false;
        }

        $receivedIds = Imunisasi::where('id_anak', $anak->id)
            ->where('status', 'sudah')
            ->pluck('id_jenis_vaksin')
            ->toArray();

        foreach ($idl->jenisVaksin as $vaksin) {
            // Skip if not applicable (HPV for male, but IDL usually has no HPV)
            if (in_array($vaksin->kode, self::HPV_CODES) && $anak->jk == 1) {
                continue;
            }
            // Skip if kadaluarsa (window closed — can't blame child for this)
            $usiaSaatIni = Carbon::parse($anak->tgl_lahir)->diffInDays(now());
            if (!$vaksin->bisa_dikejar && $usiaSaatIni > $vaksin->usia_pemberian_max) {
                continue;
            }

            if (!in_array($vaksin->id, $receivedIds)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Aggregate IDL coverage stats, optionally filtered by id_kelurahan or id_kecamatan.
     *
     * @param  array{id_kelurahan?: int, id_kecamatan?: int, id_posyandu?: int}  $filters
     * @return array{total: int, idl_lengkap: int, persen: float,
     *               per_kelurahan: array<string, array{nama: string, total: int, lengkap: int, persen: float}>}
     */
    public function getIdlCoverage(array $filters = []): array
    {
        $query = Anak::query()
            ->with(['imunisasi.jenisVaksin', 'kel'])
            ->whereRaw('TIMESTAMPDIFF(MONTH, tgl_lahir, CURDATE()) >= 12');

        if (!empty($filters['id_posyandu'])) {
            $query->where('id_posyandu', $filters['id_posyandu']);
        } elseif (!empty($filters['id_kelurahan'])) {
            $query->where('id_kel', $filters['id_kelurahan']);
        } elseif (!empty($filters['id_kecamatan'])) {
            $query->where('id_kec', $filters['id_kecamatan']);
        }

        $anakList = $query->get();

        $perKelurahan = [];
        $totalLengkap = 0;

        foreach ($anakList as $anak) {
            $namaKel = $anak->kel?->name ?? 'Tidak Diketahui';
            $kelId   = $anak->id_kel ?? 0;

            if (!isset($perKelurahan[$kelId])) {
                $perKelurahan[$kelId] = [
                    'nama'    => $namaKel,
                    'total'   => 0,
                    'lengkap' => 0,
                    'persen'  => 0.0,
                ];
            }

            $perKelurahan[$kelId]['total']++;

            if ($this->isIdlLengkap($anak)) {
                $perKelurahan[$kelId]['lengkap']++;
                $totalLengkap++;
            }
        }

        // Calculate percentages
        foreach ($perKelurahan as &$row) {
            $row['persen'] = $row['total'] > 0
                ? round(($row['lengkap'] / $row['total']) * 100, 1)
                : 0.0;
        }

        $total = $anakList->count();

        return [
            'total'         => $total,
            'idl_lengkap'   => $totalLengkap,
            'persen'        => $total > 0 ? round(($totalLengkap / $total) * 100, 1) : 0.0,
            'per_kelurahan' => $perKelurahan,
        ];
    }
}
