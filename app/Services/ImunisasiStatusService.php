<?php

namespace App\Services;

use App\Models\Anak;
use App\Models\Imunisasi;
use App\Models\JenisVaksin;
use App\Models\KelompokVaksin;
use App\Support\FilterWilayahAnak;
use App\Support\KohortImunisasi;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ImunisasiStatusService
{
    use FilterWilayahAnak;

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
     * Kolom anak yang dibaca logika status/agregat — cukup ini, bukan 70 kolom tabel anak.
     * Wajib ada 'id' (kunci chunkById) dan 'id_kel' (relasi kel).
     */
    private const KOLOM_ANAK_AGREGAT = ['id', 'tgl_lahir', 'jk', 'id_kec', 'id_kel'];

    /**
     * Jalankan $fn untuk tiap anak hasil $query, dimuat per potongan 500 baris
     * dengan kolom seperlunya. Populasi se-kota (≥10 rb anak) TIDAK boleh
     * dihidrasi sekaligus: 70 kolom + relasi imunisasi ≈ 17–80 KB/anak, dan
     * dasbor memindai populasi enam kali per request — insiden prod 16 Sep 2026
     * "Allowed memory size of 134217728 bytes exhausted". Memori puncak kini
     * sebatas satu potongan, berapa pun jumlah anak.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Anak>  $query
     * @param  callable(Anak): void  $fn
     * @param  array<int|string, mixed>  $with  relasi yang dimuat per potongan
     * @return int  jumlah anak yang diproses
     */
    private function eachAnak(\Illuminate\Database\Eloquent\Builder $query, callable $fn, array $with = ['imunisasi.jenisVaksin']): int
    {
        $jumlah = 0;
        $query->select(self::KOLOM_ANAK_AGREGAT)->with($with)
            ->chunkById(500, function ($potongan) use ($fn, &$jumlah) {
                foreach ($potongan as $anak) {
                    $fn($anak);
                    $jumlah++;
                }
            });

        return $jumlah;
    }

    /**
     * Cakupan IDL atas kohort SI tahun terpilih. Penyebutnya SI karena itulah
     * denominator doktrin program untuk imunisasi dasar lengkap — bukan
     * "semua anak ≥ 12 bulan", yang ikut menyeret anak 4–5 tahun.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return array{total: int, idl_lengkap: int, persen: float,
     *               per_kelurahan: array<string, array{nama: string, total: int, lengkap: int, persen: float}>}
     */
    public function getIdlCoverage(KohortImunisasi $kohort, array $filters = []): array
    {
        $query = $this->scopeKohort(
            $this->applyWilayahFilters(Anak::query(), $filters),
            $kohort,
            'SI'
        );

        $perKelurahan = [];
        $totalLengkap = 0;

        $total = $this->eachAnak($query, function (Anak $anak) use (&$perKelurahan, &$totalLengkap) {
            $namaKel = $anak->kel?->name ?? 'Tidak Diketahui';
            $kelId   = $anak->id_kel ?? 0;

            if (!isset($perKelurahan[$kelId])) {
                $perKelurahan[$kelId] = ['nama' => $namaKel, 'total' => 0, 'lengkap' => 0, 'persen' => 0.0];
            }

            $perKelurahan[$kelId]['total']++;

            if ($this->isIdlLengkap($anak)) {
                $perKelurahan[$kelId]['lengkap']++;
                $totalLengkap++;
            }
        }, ['imunisasi.jenisVaksin', 'kel']);

        foreach ($perKelurahan as &$row) {
            $row['persen'] = $row['total'] > 0 ? round(($row['lengkap'] / $row['total']) * 100, 1) : 0.0;
        }

        return [
            'total'         => $total,
            'idl_lengkap'   => $totalLengkap,
            'persen'        => $total > 0 ? round(($totalLengkap / $total) * 100, 1) : 0.0,
            'per_kelurahan' => $perKelurahan,
        ];
    }

    /**
     * Jumlah anak yang perlu dikejar IDL/IBL menurut TANGGAL BERJALAN.
     *
     * Angka operasional — sengaja TIDAK menerima KohortImunisasi. Kartunya di
     * dasbor menaut ke halaman Proyeksi (admin.earlyWarning) yang menghitung
     * dengan tanggal berjalan; kalau angka ini dikohortkan, kartu dan daftar
     * yang ditautnya tidak akan pernah cocok, tanpa error apa pun.
     *
     * Populasinya sengaja sama dengan sebelum pemisahan (anak ≥ 12 bulan) agar
     * nilainya tidak bergeser. Memperluasnya ke bayi < 12 bulan adalah
     * keputusan terpisah.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     */
    public function getButuhKejar(array $filters = []): int
    {
        $query = $this->applyWilayahFilters(Anak::query(), $filters)
            ->whereRaw('TIMESTAMPDIFF(MONTH, tgl_lahir, CURDATE()) >= 12');

        $butuh = 0;
        $this->eachAnak($query, function (Anak $anak) use (&$butuh) {
            $kejar = $this->kejarFlags($anak);
            if ($kejar['kejar_idl'] || $kejar['kejar_ibl']) {
                $butuh++;
            }
        });

        return $butuh;
    }

    /**
     * Cakupan IBL (booster baduta) atas kohort Baduta tahun terpilih.
     * Menggantikan penyebut lama "anak ≥ 24 bulan", yang populasinya
     * mengambang ikut hari ini sehingga angkanya tak bisa dikunci sebagai
     * capaian tahun tertentu.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return array{total: int, ibl_lengkap: int, persen: float}
     */
    public function getIblCoverage(KohortImunisasi $kohort, array $filters = []): array
    {
        $query = $this->scopeKohort(
            $this->applyWilayahFilters(Anak::query(), $filters),
            $kohort,
            'BADUTA'
        );

        $lengkap = 0;
        $total = $this->eachAnak($query, function (Anak $anak) use (&$lengkap) {
            if ($this->isIblLengkap($anak)) {
                $lengkap++;
            }
        });

        return [
            'total'       => $total,
            'ibl_lengkap' => $lengkap,
            'persen'      => $total > 0 ? round($lengkap / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Batasi query anak ke rentang tanggal lahir satu kelompok kohort.
     * Ini pengganti seluruh `whereRaw('TIMESTAMPDIFF(MONTH, tgl_lahir,
     * CURDATE()) ...')` di jalur statistik: penyebutnya jadi tetap, tidak
     * bergeser tiap hari, sehingga angkanya bisa dikunci sebagai capaian
     * tahun tertentu.
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder
     * @param  TQuery  $query
     * @return TQuery
     */
    private function scopeKohort($query, KohortImunisasi $kohort, string $kelompok)
    {
        return $query->whereBetween('tgl_lahir', $kohort->rentang($kelompok));
    }

    /**
     * Populasi sasaran satu tahun kohort, dipilah BBL / SI / Baduta.
     * BBL & SI adalah partisi bersih (tiap anak tepat satu kelompok);
     * Baduta diambil dari kohort tahun sebelumnya secara utuh.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return array{tahun: int, label: string,
     *               bbl: array{jumlah: int, rentang: array{0: string, 1: string}},
     *               si: array{jumlah: int, rentang: array{0: string, 1: string}},
     *               baduta: array{jumlah: int, rentang: array{0: string, 1: string}}}
     */
    public function getRingkasanSasaran(KohortImunisasi $kohort, array $filters = []): array
    {
        $kelompok = function (string $nama) use ($kohort, $filters): array {
            $jumlah = $this->scopeKohort(
                $this->applyWilayahFilters(Anak::query(), $filters),
                $kohort,
                $nama
            )->count();

            return ['jumlah' => $jumlah, 'rentang' => $kohort->rentang($nama)];
        };

        return [
            'tahun'  => $kohort->tahun(),
            'label'  => $kohort->label(),
            'bbl'    => $kelompok('BBL'),
            'si'     => $kelompok('SI'),
            'baduta' => $kelompok('BADUTA'),
        ];
    }

    /**
     * Funnel jumlah anak (kohort SI) yang sudah menerima tiap dosis kunci,
     * dari HB0 sampai IDL lengkap — untuk melihat di titik mana populasi
     * paling banyak "bocor".
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return list<array{kode: string, label: string, jumlah: int}>
     */
    public function getFunnelDosis(KohortImunisasi $kohort, array $filters = []): array
    {
        $tahapan = [
            'HB0'          => 'HB0',
            'DPT-HB-HIB1'  => 'DPT-HB-Hib 1',
            'DPT-HB-HIB2'  => 'DPT-HB-Hib 2',
            'DPT-HB-HIB3'  => 'DPT-HB-Hib 3',
            'MR1'          => 'Campak-Rubela',
        ];

        $cohort = $this->scopeKohort(
            $this->applyWilayahFilters(Anak::query(), $filters),
            $kohort,
            'SI'
        );

        $jumlah = array_fill_keys(array_keys($tahapan), 0);
        $idlLengkap = 0;
        $this->eachAnak($cohort, function (Anak $anak) use ($tahapan, &$jumlah, &$idlLengkap) {
            $kodeSudah = $anak->imunisasi->where('status', 'sudah')->pluck('jenisVaksin.kode');
            foreach ($tahapan as $kode => $label) {
                if ($kodeSudah->contains($kode)) {
                    $jumlah[$kode]++;
                }
            }
            if ($this->isIdlLengkap($anak)) {
                $idlLengkap++;
            }
        });

        $funnel = [];
        foreach ($tahapan as $kode => $label) {
            $funnel[] = ['kode' => $kode, 'label' => $label, 'jumlah' => $jumlah[$kode]];
        }
        $funnel[] = ['kode' => 'IDL', 'label' => 'IDL lengkap', 'jumlah' => $idlLengkap];

        return $funnel;
    }

    /**
     * Kelompok kohort yang jadi penyebut satu antigen, ditentukan dari
     * `usia_pemberian_max` (satuan HARI) — bukan daftar kode yang ditulis
     * tangan, supaya antigen baru otomatis kebagian.
     *
     *   ≤  59 hari  → SELURUH kohort  (HB0, BCG, Polio 1)
     *   ≤ 364 hari  → SI              (RV1 … MR1)
     *     lainnya   → BADUTA          (PCV3, MR2, DPT-HB-Hib 4)
     *
     * Penyebut antigen bayi baru lahir sengaja SELURUH kohort, bukan kelompok
     * BBL: HB0 diberikan 0–7 hari setelah lahir, jadi setiap anak dalam
     * periode menerimanya. Kalau penyebutnya kelompok BBL (yang hanya berisi
     * kelahiran Februari–Maret), HB0 milik ±10 bulan kelahiran lain tidak
     * masuk pembilang maupun penyebut mana pun.
     */
    private function kelompokPenyebutAntigen(int $usiaPemberianMax): string
    {
        return match (true) {
            $usiaPemberianMax <= 59  => 'SELURUH',
            $usiaPemberianMax <= 364 => 'SI',
            default                  => 'BADUTA',
        };
    }

    /** Titik masuk pengujian untuk kelompokPenyebutAntigen(). */
    public function kelompokPenyebutAntigenUntukUji(int $usiaPemberianMax): string
    {
        return $this->kelompokPenyebutAntigen($usiaPemberianMax);
    }

    /**
     * Cakupan tiap antigen rutin atas kohort tahun terpilih. Penyebutnya
     * kelompok kohort yang sesuai jendela antigen (lihat
     * kelompokPenyebutAntigen), MENGGANTIKAN metodologi lama "anak yang
     * jendela usianya sudah lewat". Konsekuensinya semua persen turun untuk
     * kohort berjalan — itu memang perilaku cakupan tahunan.
     *
     * Satu pass populasi atas gabungan BADUTA ∪ SELURUH, yang kebetulan
     * bersambung: [1 Apr X-2 .. 31 Mar X].
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return list<array{kode: string, nama: string, kelompok: string, jumlah_sudah: int, jumlah_penyebut: int, persen: float}>
     */
    public function getCakupanAntigen(KohortImunisasi $kohort, array $filters = []): array
    {
        $vaksinList = JenisVaksin::aktif()
            ->where('kategori', '!=', 'Tambahan')
            ->orderBy('usia_pemberian_min')
            ->get();

        $kelompokVaksin = [];
        foreach ($vaksinList as $vaksin) {
            $kelompokVaksin[$vaksin->id] = $this->kelompokPenyebutAntigen((int) $vaksin->usia_pemberian_max);
        }

        [$awal] = $kohort->rentang('BADUTA');
        [, $akhir] = $kohort->rentang('SELURUH');

        $sudah = $penyebut = array_fill_keys($vaksinList->pluck('id')->all(), 0);

        $this->eachAnak(
            $this->applyWilayahFilters(Anak::query(), $filters)
                ->whereBetween('tgl_lahir', [$awal, $akhir]),
            function (Anak $anak) use ($vaksinList, $kelompokVaksin, $kohort, &$sudah, &$penyebut) {
                $anggota = $kohort->kelompokDari((string) $anak->tgl_lahir);

                foreach ($vaksinList as $vaksin) {
                    if (!in_array($kelompokVaksin[$vaksin->id], $anggota, true)) {
                        continue;
                    }

                    $record = $anak->imunisasi->firstWhere('id_jenis_vaksin', $vaksin->id);

                    if ($this->getVaccineStatus($anak, $vaksin, $record) === 'tidak_relevan') {
                        continue;
                    }

                    $penyebut[$vaksin->id]++;

                    if ($record && $record->status === 'sudah') {
                        $sudah[$vaksin->id]++;
                    }
                }
            }
        );

        $result = [];
        foreach ($vaksinList as $vaksin) {
            $n = $penyebut[$vaksin->id];
            $result[] = [
                'kode'            => $vaksin->kode,
                'nama'            => $vaksin->nama,
                'kelompok'        => $kelompokVaksin[$vaksin->id],
                'jumlah_sudah'    => $sudah[$vaksin->id],
                'jumlah_penyebut' => $n,
                'persen'          => $n > 0 ? round($sudah[$vaksin->id] / $n * 100, 1) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * Kohort populasi (BBL + SI + Baduta) per kecamatan → kelurahan, dengan jumlah
     * RT terdaftar dan porsi terhadap total kota. Ini murni distribusi
     * populasi (sasaran), BUKAN cakupan/kelengkapan imunisasi.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return list<array{nama: string, jumlah_rt: int, bbl: int, si: int, baduta: int, total: int, persen_kota: float,
     *               kelurahan: list<array{nama: string, jumlah_rt: int, bbl: int, si: int, baduta: int, total: int, persen_kota: float}>}>
     */
    public function getKohortWilayah(KohortImunisasi $kohort, array $filters = []): array
    {
        $rtCountByKel = \App\Models\Rt::query()
            ->selectRaw('id_kelurahan, COUNT(*) as jumlah')
            ->groupBy('id_kelurahan')
            ->pluck('jumlah', 'id_kelurahan');

        [$awal] = $kohort->rentang('BADUTA');
        [, $akhir] = $kohort->rentang('SELURUH');

        $perKel = [];
        $grandTotal = $this->eachAnak(
            $this->applyWilayahFilters(Anak::query(), $filters)
                ->whereBetween('tgl_lahir', [$awal, $akhir]),
            function (Anak $anak) use (&$perKel, $kohort) {
                $kelId = $anak->id_kel ?? 0;
                $anggota = $kohort->kelompokDari((string) $anak->tgl_lahir);

                if (!isset($perKel[$kelId])) {
                    $perKel[$kelId] = ['id_kec' => $anak->id_kec, 'bbl' => 0, 'si' => 0, 'baduta' => 0, 'total' => 0];
                }

                $perKel[$kelId]['total']++;

                if (in_array('BBL', $anggota, true)) {
                    $perKel[$kelId]['bbl']++;
                } elseif (in_array('SI', $anggota, true)) {
                    $perKel[$kelId]['si']++;
                } elseif (in_array('BADUTA', $anggota, true)) {
                    $perKel[$kelId]['baduta']++;
                }
            },
            with: [] // distribusi populasi murni, tak perlu relasi imunisasi
        );

        $kelurahanNames = \App\Models\Kelurahan::whereIn('id', array_keys($perKel))->pluck('name', 'id');

        $result = [];
        foreach (\App\Models\Kecamatan::orderBy('name')->get() as $kec) {
            $kelurahanRows = [];
            $kecTotal = $kecBbl = $kecSi = $kecBaduta = $kecRt = 0;

            foreach ($perKel as $kelId => $row) {
                if ((int) $row['id_kec'] !== $kec->id) {
                    continue;
                }
                $jumlahRt = (int) ($rtCountByKel[$kelId] ?? 0);
                $kelurahanRows[] = [
                    'nama'        => $kelurahanNames[$kelId] ?? 'Tidak diketahui',
                    'jumlah_rt'   => $jumlahRt,
                    'bbl'         => $row['bbl'],
                    'si'          => $row['si'],
                    'baduta'      => $row['baduta'],
                    'total'       => $row['total'],
                    'persen_kota' => $grandTotal > 0 ? round($row['total'] / $grandTotal * 100, 1) : 0.0,
                ];
                $kecTotal  += $row['total'];
                $kecBbl    += $row['bbl'];
                $kecSi     += $row['si'];
                $kecBaduta += $row['baduta'];
                $kecRt     += $jumlahRt;
            }

            if (empty($kelurahanRows)) {
                continue;
            }

            $result[] = [
                'nama'        => $kec->name,
                'jumlah_rt'   => $kecRt,
                'bbl'         => $kecBbl,
                'si'          => $kecSi,
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
    public function getRincianPuskesmas(KohortImunisasi $kohort, array $filters = []): array
    {
        $result = [];
        foreach (\App\Models\Puskesmas::orderBy('name')->get() as $pkm) {
            $kelIds = \App\Support\WilkerPuskesmas::catchmentKelurahanIds($pkm->name);

            $query = $this->scopeKohort(
                $this->applyWilayahFilters(Anak::query(), $filters)->whereIn('id_kel', $kelIds ?: [0]),
                $kohort,
                'SI'
            );

            $lengkap = 0;
            $dpt1 = 0;
            $dpt3 = 0;
            $sasaran = $this->eachAnak($query, function (Anak $anak) use (&$lengkap, &$dpt1, &$dpt3) {
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
            });

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

    /**
     * Sebaran alasan tidak imunisasi dari KUNJUNGAN TERAKHIR tiap anak (wilayah
     * terfilter); nilai di luar config('imunisasi.alasan_tidak_imunisasi') digabung
     * ke "Lainnya". Dipakai dasbor imunisasi & dasbor Kesmas.
     *
     * @return array<string, int>  alasan => jumlah, urut menurun
     */
    public function getAlasanTidakImunisasi(array $filters): array
    {
        $maxTgl = DB::table('data_anak as dm')
            ->join('anak as am', 'dm.id_anak', '=', 'am.id')
            ->selectRaw('dm.id_anak, MAX(dm.tgl_kunjungan) as max_tgl')
            ->whereNotNull('dm.tgl_kunjungan');
        if (!empty($filters['id_posyandu']))      $maxTgl->where('am.id_posyandu', $filters['id_posyandu']);
        elseif (!empty($filters['id_kelurahan'])) $maxTgl->where('am.id_kel', $filters['id_kelurahan']);
        elseif (!empty($filters['id_kecamatan'])) $maxTgl->where('am.id_kec', $filters['id_kecamatan']);
        $maxTgl->groupBy('dm.id_anak');

        $values = DB::table('data_anak as da')
            ->joinSub($maxTgl, 'm', function ($j) {
                $j->on('m.id_anak', '=', 'da.id_anak')->on('m.max_tgl', '=', 'da.tgl_kunjungan');
            })
            ->whereNotNull('da.alasan_tidak_imunisasi')
            ->where('da.alasan_tidak_imunisasi', '!=', '')
            ->pluck('da.alasan_tidak_imunisasi');

        $known  = config('imunisasi.alasan_tidak_imunisasi', []);
        $counts = [];
        foreach ($values as $val) {
            $val = trim((string) $val);
            if ($val === '') continue;
            $bucket = in_array($val, $known, true) ? $val : 'Lainnya';
            $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
        }
        arsort($counts);

        return $counts;
    }
}
