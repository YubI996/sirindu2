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
     * Cache data referensi per-request. Identik untuk semua anak, jadi tak perlu
     * di-query ulang di loop dashboard (sebelumnya sumber N+1 ribuan query).
     * Static agar dibagi lintas instance service (model & controller membuat
     * instance terpisah lewat app()); PHP membersihkannya tiap akhir request.
     */
    private static ?Collection $jenisVaksinAktifCache = null;
    private static bool $idlLoaded = false;
    private static ?KelompokVaksin $idlKelompokCache = null;
    /** @var array<string, KelompokVaksin|null> */
    private static array $kelompokCache = [];

    /** Jenis vaksin aktif beserta kelompokVaksin (untuk statusKejarVaksin). */
    private function jenisVaksinAktif(): Collection
    {
        return self::$jenisVaksinAktifCache ??= JenisVaksin::aktif()->with('kelompokVaksin')->get();
    }

    /** Kelompok IDL beserta jenisVaksin-nya (sekali per request). */
    private function idlKelompok(): ?KelompokVaksin
    {
        if (!self::$idlLoaded) {
            self::$idlLoaded = true;
            self::$idlKelompokCache = KelompokVaksin::where('kode', 'IDL')->with('jenisVaksin')->first();
        }

        return self::$idlKelompokCache;
    }

    /** Kelompok vaksin apa pun (mis. IBL) beserta jenisVaksin-nya, di-cache per kode. */
    private function kelompokByKode(string $kode): ?KelompokVaksin
    {
        if (!array_key_exists($kode, self::$kelompokCache)) {
            self::$kelompokCache[$kode] = KelompokVaksin::where('kode', $kode)->with('jenisVaksin')->first();
        }

        return self::$kelompokCache[$kode];
    }

    /** Imunisasi anak — pakai relasi eager bila sudah dimuat (0 query di loop). */
    private function imunisasiAnak(Anak $anak): Collection
    {
        return $anak->relationLoaded('imunisasi') ? $anak->imunisasi : $anak->imunisasi()->get();
    }

    /**
     * Reset cache statis. Di produksi tak perlu dipanggil (PHP membersihkannya
     * tiap akhir request); wajib dipanggil di setUp() test yang memakai
     * RefreshDatabase — auto-increment MySQL tak ikut rollback transaksi,
     * jadi cache lintas-test bisa menyimpan id KelompokVaksin/JenisVaksin
     * dari test method sebelumnya yang sudah tak berlaku.
     */
    public static function flushCache(): void
    {
        self::$jenisVaksinAktifCache = null;
        self::$idlLoaded = false;
        self::$idlKelompokCache = null;
        self::$kelompokCache = [];
    }

    /**
     * Determine immunization status for one vaccine relative to a child.
     *
     * Returns: 'sudah' | 'belum' | 'terlambat' | 'kadaluarsa' | 'tidak_relevan'
     */
    public function getVaccineStatus(Anak $anak, JenisVaksin $vaksin, ?Imunisasi $record, ?int $usiaSaatIni = null): string
    {
        // HPV is not relevant for males
        if (in_array($vaksin->kode, self::HPV_CODES) && $anak->jk == 1) {
            return 'tidak_relevan';
        }

        if ($record && $record->status === 'sudah') {
            return 'sudah';
        }

        // Umur identik untuk semua vaksin anak → boleh dihitung sekali oleh pemanggil
        // (lihat getJadwal) untuk menghindari ribuan Carbon::parse di loop dashboard.
        $usiaSaatIni ??= (int) Carbon::parse($anak->tgl_lahir)->diffInDays(now());

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
        $jenisVaksin = $this->jenisVaksinAktif();
        $imunisasiDiberikan = $this->imunisasiAnak($anak)->keyBy('id_jenis_vaksin');

        // Hitung sekali per anak (bukan per vaksin) — kunci performa di loop ribuan anak.
        $tglLahirTs  = strtotime($anak->tgl_lahir);
        $usiaSaatIni = (int) Carbon::parse($anak->tgl_lahir)->diffInDays(now());

        $jadwal = [];
        foreach ($jenisVaksin as $vaksin) {
            $record = $imunisasiDiberikan->get($vaksin->id);
            $status = $this->getVaccineStatus($anak, $vaksin, $record, $usiaSaatIni);

            $tanggalMin = date('Y-m-d', $tglLahirTs + $vaksin->usia_pemberian_min * 86400);
            $tanggalMax = date('Y-m-d', $tglLahirTs + $vaksin->usia_pemberian_max * 86400);
            $catchupDeadline = $vaksin->catchup_max_hari
                ? date('Y-m-d', $tglLahirTs + $vaksin->catchup_max_hari * 86400)
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
     * Versi ringan statusKejarVaksin: hanya flag kejar IDL/IBL, tanpa membangun
     * jadwal lengkap & string tanggal. Dipakai di agregasi populasi (getIdlCoverage)
     * agar tak ada ~370rb date() sia-sia saat memindai ribuan anak.
     *
     * @return array{kejar_idl: bool, kejar_ibl: bool}
     */
    public function kejarFlags(Anak $anak): array
    {
        $imunisasi   = $this->imunisasiAnak($anak)->keyBy('id_jenis_vaksin');
        $usiaSaatIni = (int) Carbon::parse($anak->tgl_lahir)->diffInDays(now());

        $kejarIdl = false;
        $kejarIbl = false;

        foreach ($this->jenisVaksinAktif() as $vaksin) {
            $status = $this->getVaccineStatus($anak, $vaksin, $imunisasi->get($vaksin->id), $usiaSaatIni);
            if ($status !== 'terlambat') {
                continue;
            }

            $kode = $vaksin->kelompokVaksin?->kode;
            if ($kode === 'IDL') {
                $kejarIdl = true;
            } elseif ($kode === 'IBL') {
                $kejarIbl = true;
            }
        }

        return ['kejar_idl' => $kejarIdl, 'kejar_ibl' => $kejarIbl];
    }

    /**
     * IDL completeness: true if child has received all IDL vaccines that are applicable.
     */
    public function isIdlLengkap(Anak $anak): bool
    {
        return $this->isKelompokLengkap($anak, 'IDL');
    }

    /**
     * IBL completeness (booster lanjutan baduta): true if child has received
     * all IBL vaccines that are applicable.
     */
    public function isIblLengkap(Anak $anak): bool
    {
        return $this->isKelompokLengkap($anak, 'IBL');
    }

    /** Logika kelengkapan generik untuk satu kelompok vaksin (IDL/IBL/dst). */
    private function isKelompokLengkap(Anak $anak, string $kodeKelompok): bool
    {
        $kelompok = $this->kelompokByKode($kodeKelompok);
        if (!$kelompok) {
            return false;
        }

        $receivedIds = $this->imunisasiAnak($anak)
            ->where('status', 'sudah')
            ->pluck('id_jenis_vaksin')
            ->all();

        $usiaSaatIni = Carbon::parse($anak->tgl_lahir)->diffInDays(now());

        foreach ($kelompok->jenisVaksin as $vaksin) {
            // Skip if not applicable (HPV for male, but IDL/IBL usually has no HPV)
            if (in_array($vaksin->kode, self::HPV_CODES) && $anak->jk == 1) {
                continue;
            }
            // Skip if kadaluarsa (window closed — can't blame child for this)
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
     * Terapkan filter wilayah ke query Anak. Semua dimensi yang diisi digabung
     * dengan AND (bukan saling meniadakan) — cascading filter UI hanya pernah
     * mengisi satu jalur konsisten (mis. kelurahan yang benar-benar ada di
     * kecamatan terpilih), jadi hasilnya identik dengan skema lama yang
     * mutually-exclusive, sekaligus bisa mengombinasikan puskesmas (wilker,
     * lintas kelurahan) dengan RT tanpa saling menimpa.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     */
    private function applyWilayahFilters(\Illuminate\Database\Eloquent\Builder $query, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        if (!empty($filters['id_kecamatan'])) {
            $query->where('id_kec', $filters['id_kecamatan']);
        }
        if (!empty($filters['id_kelurahan'])) {
            $query->where('id_kel', $filters['id_kelurahan']);
        }
        if (!empty($filters['id_rt'])) {
            $query->where('id_rt', $filters['id_rt']);
        }
        if (!empty($filters['id_posyandu'])) {
            $query->where('id_posyandu', $filters['id_posyandu']);
        }
        if (!empty($filters['id_puskesmas'])) {
            $namaPuskesmas = \App\Models\Puskesmas::whereKey($filters['id_puskesmas'])->value('name');
            $kelIds = $namaPuskesmas ? \App\Support\WilkerPuskesmas::catchmentKelurahanIds($namaPuskesmas) : [];
            $query->whereIn('id_kel', $kelIds ?: [0]);
        }

        return $query;
    }

    /**
     * Aggregate IDL coverage stats, optionally filtered by wilayah.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @param  bool  $withKejar  Sertakan hitung "butuh_kejar" (kejar IDL/IBL) di pass yang sama,
     *                           agar dashboard tak perlu memindai populasi dua kali.
     * @return array{total: int, idl_lengkap: int, persen: float, butuh_kejar: int,
     *               per_kelurahan: array<string, array{nama: string, total: int, lengkap: int, persen: float}>}
     */
    public function getIdlCoverage(array $filters = [], bool $withKejar = false): array
    {
        $query = Anak::query()
            ->with(['imunisasi.jenisVaksin', 'kel'])
            ->whereRaw('TIMESTAMPDIFF(MONTH, tgl_lahir, CURDATE()) >= 12');

        $this->applyWilayahFilters($query, $filters);

        $anakList = $query->get();

        $perKelurahan = [];
        $totalLengkap = 0;
        $butuhKejar   = 0;

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

            // Hitung "butuh kejar" di pass yang sama (akurat, lintas seluruh populasi).
            // Pakai versi ringan (flag saja) — tanpa membangun jadwal & string tanggal.
            if ($withKejar) {
                $kejar = $this->kejarFlags($anak);
                if ($kejar['kejar_idl'] || $kejar['kejar_ibl']) {
                    $butuhKejar++;
                }
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
            'butuh_kejar'   => $butuhKejar,
            'per_kelurahan' => $perKelurahan,
        ];
    }

    /**
     * Aggregate IBL (booster lanjutan baduta) coverage stats, analog dengan
     * getIdlCoverage() tapi kohortnya anak ≥24 bulan — usia kelompok IBL
     * (12–23 bulan) sudah lewat sepenuhnya, jadi kelengkapannya bisa dinilai.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return array{total: int, ibl_lengkap: int, persen: float}
     */
    public function getIblCoverage(array $filters = []): array
    {
        $anakList = $this->applyWilayahFilters(Anak::query(), $filters)
            ->with('imunisasi.jenisVaksin')
            ->whereRaw('TIMESTAMPDIFF(MONTH, tgl_lahir, CURDATE()) >= 24')
            ->get();

        $lengkap = 0;
        foreach ($anakList as $anak) {
            if ($this->isIblLengkap($anak)) {
                $lengkap++;
            }
        }

        $total = $anakList->count();

        return [
            'total'       => $total,
            'ibl_lengkap' => $lengkap,
            'persen'      => $total > 0 ? round($lengkap / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Populasi sasaran saat ini: bayi 0–11 bulan (rentang kelompok IDL),
     * baduta pada rentang usia kelompok IBL (fallback 12–23 bulan bila
     * KelompokVaksinSeeder belum dijalankan), dan balita 0–59 bulan
     * (mencakup bayi & baduta di dalamnya — bukan penjumlahan terpisah).
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return array{bayi: int, baduta: int, baduta_min: int, baduta_max: int, balita: int}
     */
    public function getRingkasanSasaran(array $filters = []): array
    {
        $ibl = KelompokVaksin::where('kode', 'IBL')->first();
        $badutaMin = $ibl->usia_pemberian_min ?? 12;
        $badutaMax = $ibl->usia_pemberian_max ?? 23;

        $bayi = $this->applyWilayahFilters(Anak::query(), $filters)
            ->whereRaw('TIMESTAMPDIFF(MONTH, tgl_lahir, CURDATE()) BETWEEN 0 AND 11')
            ->count();

        $baduta = $this->applyWilayahFilters(Anak::query(), $filters)
            ->whereRaw('TIMESTAMPDIFF(MONTH, tgl_lahir, CURDATE()) BETWEEN ? AND ?', [$badutaMin, $badutaMax])
            ->count();

        $balita = $this->applyWilayahFilters(Anak::query(), $filters)
            ->whereRaw('TIMESTAMPDIFF(MONTH, tgl_lahir, CURDATE()) BETWEEN 0 AND 59')
            ->count();

        return [
            'bayi'       => $bayi,
            'baduta'     => $baduta,
            'baduta_min' => $badutaMin,
            'baduta_max' => $badutaMax,
            'balita'     => $balita,
        ];
    }

    /**
     * Funnel jumlah anak (kohort ≥12 bulan) yang sudah menerima tiap dosis
     * kunci, dari HB0 sampai IDL lengkap — untuk melihat di titik mana
     * populasi paling banyak "bocor".
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return list<array{kode: string, label: string, jumlah: int}>
     */
    public function getFunnelDosis(array $filters = []): array
    {
        $tahapan = [
            'HB0'          => 'HB0',
            'DPT-HB-HIB1'  => 'DPT-HB-Hib 1',
            'DPT-HB-HIB2'  => 'DPT-HB-Hib 2',
            'DPT-HB-HIB3'  => 'DPT-HB-Hib 3',
            'MR1'          => 'Campak-Rubela',
        ];

        $cohort = $this->applyWilayahFilters(Anak::query(), $filters)
            ->whereRaw('TIMESTAMPDIFF(MONTH, tgl_lahir, CURDATE()) >= 12')
            ->with('imunisasi.jenisVaksin')
            ->get();

        $jumlah = array_fill_keys(array_keys($tahapan), 0);
        $idlLengkap = 0;
        foreach ($cohort as $anak) {
            $kodeSudah = $anak->imunisasi->where('status', 'sudah')->pluck('jenisVaksin.kode');
            foreach ($tahapan as $kode => $label) {
                if ($kodeSudah->contains($kode)) {
                    $jumlah[$kode]++;
                }
            }
            if ($this->isIdlLengkap($anak)) {
                $idlLengkap++;
            }
        }

        $funnel = [];
        foreach ($tahapan as $kode => $label) {
            $funnel[] = ['kode' => $kode, 'label' => $label, 'jumlah' => $jumlah[$kode]];
        }
        $funnel[] = ['kode' => 'IDL', 'label' => 'IDL lengkap', 'jumlah' => $idlLengkap];

        return $funnel;
    }

    /**
     * Cakupan tiap antigen rutin (kategori Wajib/Booster — antigen BIAS
     * kategori "Tambahan" sengaja dikecualikan, di luar skop dashboard ini).
     * Penyebut per antigen = anak yang usianya SUDAH melewati jendela
     * pemberian antigen tsb (statusnya bukan 'belum' atau 'tidak_relevan'),
     * konsisten dengan metodologi isIdlLengkap()/getVaccineStatus() — bukan
     * seluruh populasi, supaya bayi yang jadwalnya belum tiba tak menurunkan
     * angka secara menyesatkan.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return list<array{kode: string, nama: string, jumlah_sudah: int, jumlah_eligible: int, persen: float}>
     */
    public function getCakupanAntigen(array $filters = []): array
    {
        $vaksinList = JenisVaksin::aktif()
            ->where('kategori', '!=', 'Tambahan')
            ->orderBy('usia_pemberian_min')
            ->get();

        $anakList = $this->applyWilayahFilters(Anak::query(), $filters)
            ->with('imunisasi.jenisVaksin')
            ->get();

        $result = [];
        foreach ($vaksinList as $vaksin) {
            $sudah = 0;
            $eligible = 0;
            foreach ($anakList as $anak) {
                $record = $anak->imunisasi->firstWhere('id_jenis_vaksin', $vaksin->id);
                $status = $this->getVaccineStatus($anak, $vaksin, $record);
                if ($status === 'tidak_relevan' || $status === 'belum') {
                    continue;
                }
                $eligible++;
                if ($status === 'sudah') {
                    $sudah++;
                }
            }
            $result[] = [
                'kode'            => $vaksin->kode,
                'nama'            => $vaksin->nama,
                'jumlah_sudah'    => $sudah,
                'jumlah_eligible' => $eligible,
                'persen'          => $eligible > 0 ? round($sudah / $eligible * 100, 1) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * Kohort populasi (bayi + baduta) per kecamatan → kelurahan, dengan jumlah
     * RT terdaftar dan porsi terhadap total kota. Ini murni distribusi
     * populasi (sasaran), BUKAN cakupan/kelengkapan imunisasi.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return list<array{nama: string, jumlah_rt: int, bayi: int, baduta: int, total: int, persen_kota: float,
     *               kelurahan: list<array{nama: string, jumlah_rt: int, bayi: int, baduta: int, total: int, persen_kota: float}>}>
     */
    public function getKohortWilayah(array $filters = []): array
    {
        $ibl = KelompokVaksin::where('kode', 'IBL')->first();
        $badutaMin = $ibl->usia_pemberian_min ?? 12;
        $badutaMax = $ibl->usia_pemberian_max ?? 23;

        $anakList = $this->applyWilayahFilters(Anak::query(), $filters)
            ->select('id', 'id_kec', 'id_kel', 'tgl_lahir')
            ->get();

        $grandTotal = $anakList->count();

        $rtCountByKel = \App\Models\Rt::query()
            ->selectRaw('id_kelurahan, COUNT(*) as jumlah')
            ->groupBy('id_kelurahan')
            ->pluck('jumlah', 'id_kelurahan');

        $perKel = [];
        foreach ($anakList as $anak) {
            $kelId = $anak->id_kel ?? 0;
            $usiaBulan = Carbon::parse($anak->tgl_lahir)->diffInMonths(now());

            if (!isset($perKel[$kelId])) {
                $perKel[$kelId] = ['id_kec' => $anak->id_kec, 'bayi' => 0, 'baduta' => 0, 'total' => 0];
            }
            $perKel[$kelId]['total']++;
            if ($usiaBulan <= 11) {
                $perKel[$kelId]['bayi']++;
            } elseif ($usiaBulan >= $badutaMin && $usiaBulan <= $badutaMax) {
                $perKel[$kelId]['baduta']++;
            }
        }

        $kelurahanNames = \App\Models\Kelurahan::whereIn('id', array_keys($perKel))->pluck('name', 'id');

        $result = [];
        foreach (\App\Models\Kecamatan::orderBy('name')->get() as $kec) {
            $kelurahanRows = [];
            $kecTotal = $kecBayi = $kecBaduta = $kecRt = 0;

            foreach ($perKel as $kelId => $row) {
                if ((int) $row['id_kec'] !== $kec->id) {
                    continue;
                }
                $jumlahRt = (int) ($rtCountByKel[$kelId] ?? 0);
                $kelurahanRows[] = [
                    'nama'        => $kelurahanNames[$kelId] ?? 'Tidak diketahui',
                    'jumlah_rt'   => $jumlahRt,
                    'bayi'        => $row['bayi'],
                    'baduta'      => $row['baduta'],
                    'total'       => $row['total'],
                    'persen_kota' => $grandTotal > 0 ? round($row['total'] / $grandTotal * 100, 1) : 0.0,
                ];
                $kecTotal += $row['total'];
                $kecBayi += $row['bayi'];
                $kecBaduta += $row['baduta'];
                $kecRt += $jumlahRt;
            }

            if (empty($kelurahanRows)) {
                continue;
            }

            $result[] = [
                'nama'        => $kec->name,
                'jumlah_rt'   => $kecRt,
                'bayi'        => $kecBayi,
                'baduta'      => $kecBaduta,
                'total'       => $kecTotal,
                'persen_kota' => $grandTotal > 0 ? round($kecTotal / $grandTotal * 100, 1) : 0.0,
                'kelurahan'   => $kelurahanRows,
            ];
        }

        return $result;
    }

    /** Status ringkas per puskesmas untuk badge di tabel rincian. */
    private function statusPuskesmas(float $persen, float $doRate): string
    {
        if ($persen < 60) {
            return 'tertinggal';
        }
        if ($doRate > 5) {
            return 'perhatian';
        }

        return 'on_track';
    }

    /**
     * Rincian capaian per puskesmas, dikelompokkan lewat catchment kelurahan
     * WilkerPuskesmas (sumber yang sama dipakai scoping PD3I) — bukan FK
     * langsung id_puskesmas di posyandu, karena itulah sumber kanonik yang
     * sudah menangani variasi ejaan kelurahan/puskesmas di data.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int}  $filters
     * @return list<array{nama: string, sasaran: int, capaian_idl: int, persen: float, do_rate: float, status: string}>
     */
    public function getRincianPuskesmas(array $filters = []): array
    {
        $result = [];
        foreach (\App\Models\Puskesmas::orderBy('name')->get() as $pkm) {
            $kelIds = \App\Support\WilkerPuskesmas::catchmentKelurahanIds($pkm->name);

            $anakList = $this->applyWilayahFilters(Anak::query(), $filters)
                ->whereIn('id_kel', $kelIds ?: [0])
                ->whereRaw('TIMESTAMPDIFF(MONTH, tgl_lahir, CURDATE()) >= 12')
                ->with('imunisasi.jenisVaksin')
                ->get();

            $sasaran = $anakList->count();
            $lengkap = 0;
            $dpt1 = 0;
            $dpt3 = 0;
            foreach ($anakList as $anak) {
                if ($this->isIdlLengkap($anak)) {
                    $lengkap++;
                }
                $kodeSudah = $anak->imunisasi->where('status', 'sudah')->pluck('jenisVaksin.kode');
                if ($kodeSudah->contains('DPT-HB-HIB1')) {
                    $dpt1++;
                }
                if ($kodeSudah->contains('DPT-HB-HIB3')) {
                    $dpt3++;
                }
            }

            $persen = $sasaran > 0 ? round($lengkap / $sasaran * 100, 1) : 0.0;
            $doRate = $dpt1 > 0 ? round(max(0, $dpt1 - $dpt3) / $dpt1 * 100, 1) : 0.0;

            $result[] = [
                'nama'        => $pkm->name,
                'sasaran'     => $sasaran,
                'capaian_idl' => $lengkap,
                'persen'      => $persen,
                'do_rate'     => $doRate,
                'status'      => $this->statusPuskesmas($persen, $doRate),
            ];
        }

        return $result;
    }

    /**
     * Anak yang jatuh tempo antigen HARI INI atau BESOK — dihitung murni dari
     * tanggal lahir + usia_pemberian_min tiap antigen aktif (kategori Wajib/
     * Booster, BIAS "Tambahan" dikecualikan sama seperti getCakupanAntigen()).
     * Beberapa antigen sering jatuh tempo di usia yang sama (mis. DPT-HB-Hib1,
     * PCV1, Polio2 semua di usia 60 hari), jadi dikelompokkan per anak — satu
     * baris per anak berisi semua antigen yang jatuh tempo hari itu, masing-
     * masing dengan status 'sudah'/'belum' sendiri. Ini BUKAN daftar terlambat
     * (itu peran "Kejar"/Proyeksi) — murni yang jadwalnya pas jatuh hari ini/besok.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return array{hari_ini: list<array{anak: Anak, antigen: list<array{nama: string, status: string}>}>,
     *               besok: list<array{anak: Anak, antigen: list<array{nama: string, status: string}>}>}
     */
    public function getSasaranHarianBesok(array $filters = []): array
    {
        $vaksinList = JenisVaksin::aktif()->where('kategori', '!=', 'Tambahan')->get();

        $hasil = ['hari_ini' => [], 'besok' => []];
        $tanggalPerHari = ['hari_ini' => Carbon::today(), 'besok' => Carbon::tomorrow()];

        foreach ($tanggalPerHari as $key => $tanggal) {
            $perAnak = [];

            foreach ($vaksinList as $vaksin) {
                $tglLahirTarget = $tanggal->copy()->subDays($vaksin->usia_pemberian_min)->toDateString();

                $anakList = $this->applyWilayahFilters(Anak::query(), $filters)
                    ->whereDate('tgl_lahir', $tglLahirTarget)
                    ->with(['kel', 'rt', 'posyandu', 'imunisasi' => fn ($q) => $q->where('id_jenis_vaksin', $vaksin->id)])
                    ->get();

                foreach ($anakList as $anak) {
                    if (in_array($vaksin->kode, self::HPV_CODES) && $anak->jk == 1) {
                        continue;
                    }

                    if (!isset($perAnak[$anak->id])) {
                        $perAnak[$anak->id] = ['anak' => $anak, 'antigen' => []];
                    }

                    $record = $anak->imunisasi->first();
                    $perAnak[$anak->id]['antigen'][] = [
                        'kode'   => $vaksin->kode,
                        'nama'   => $vaksin->nama,
                        'status' => ($record && $record->status === 'sudah') ? 'sudah' : 'belum',
                    ];
                }
            }

            $hasil[$key] = array_values($perAnak);
        }

        return $hasil;
    }
}
