<?php

namespace App\Services;

use App\Models\Posyandu;
use App\Models\Puskesmas;

/**
 * Cocokkan nama posyandu berkas e-PPGBM ke master `posyandu`.
 *
 * Latar (temuan prod, berkas Data OT Juni 2026 — 9.884 baris): master ditulis
 * dengan angka ROMAWI ("Sejahtera II", "Berseri IX"), sedangkan ekspor e-PPGBM
 * memakai angka ARAB ("SEJAHTERA 2", "BERSERI 9"). Matcher lama
 * (OtFinalRegistriImport::resolveFaskes) mencocokkan nama apa adanya lalu jatuh
 * ke `LIKE '%nama%'` GLOBAL, sehingga:
 *   - 2.352 baris (23,8%) berakhir id_posyandu NULL → 35 dari 121 posyandu
 *     tampil kosong di dashboard meski anaknya ada;
 *   - 644 baris (6,5%) menempel DIAM-DIAM ke posyandu lain
 *     (EDELWEIS → "Griya Edelweis", MUTIARA → "Mutiara Insan", dst).
 *
 * Aturan di sini sengaja konservatif: lebih baik NULL + dilaporkan daripada
 * seorang anak tercatat di posyandu milik orang lain. Urutan percobaan:
 *
 *   1. dalam puskesmas yang sama: nama identik setelah normalisasi
 *   2. dalam puskesmas yang sama: identik setelah spasi diabaikan
 *      ("MEKAR SARI" = "Mekarsari")
 *   3. dalam puskesmas yang sama: akhiran " 1" dianggap opsional di KEDUA sisi
 *      ("FLAMBOYAN" = "Flamboyan 1", "CENDRAWASIH1" = "Cendrawasih")
 *   4. seluruh master: nama identik DAN hanya ada satu — menolong baris yang
 *      id_puskesmas-nya meleset di master (mis. "Cendana" tercatat di Bontang
 *      Utara 1 padahal berkas menyebut Bontang Utara II)
 *
 * Lebih dari satu kandidat di tahap mana pun → 'ambigu', TIDAK PERNAH ditebak.
 * Langkah 4 sengaja hanya menerima nama identik: melonggarkannya akan membuat
 * "MUTIARA" dari Bontang Selatan II tersedot ke "Mutiara 1" milik Bontang
 * Utara 1.
 */
class FaskesMatcher
{
    /** Angka Romawi yang dipakai master; di luar ini biarkan apa adanya. */
    protected const ROMAWI = [
        'I' => '1', 'II' => '2', 'III' => '3', 'IV' => '4', 'V' => '5', 'VI' => '6',
        'VII' => '7', 'VIII' => '8', 'IX' => '9', 'X' => '10', 'XI' => '11', 'XII' => '12',
    ];

    /** @var array<int,array{id:int,nama:string,id_puskesmas:int|null}> */
    protected array $master = [];

    /** @var array<string,array<int,int>> puskesmas ternormalisasi => indeks $master */
    protected array $perPuskesmas = [];

    /** @var array<string,array<int,int>> nama ternormalisasi => indeks $master */
    protected array $perNama = [];

    /** @var array<int,array{id:int,nama:string}> */
    protected array $puskesmas = [];

    public function __construct()
    {
        $namaPuskesmas = Puskesmas::pluck('name', 'id')->all();

        foreach ($namaPuskesmas as $id => $nama) {
            $this->puskesmas[] = ['id' => (int) $id, 'nama' => (string) $nama];
        }

        foreach (Posyandu::select('id', 'name', 'id_puskesmas')->get() as $p) {
            $i = count($this->master);
            $this->master[$i] = [
                'id'           => (int) $p->id,
                'nama'         => (string) $p->name,
                'id_puskesmas' => $p->id_puskesmas ? (int) $p->id_puskesmas : null,
            ];

            $pus = $namaPuskesmas[$p->id_puskesmas] ?? '';
            $this->perPuskesmas[self::normalisasi($pus)][] = $i;
            $this->perNama[self::normalisasi((string) $p->name)][] = $i;
        }
    }

    /**
     * Cocokkan nama PUSKESMAS berkas ke master.
     *
     * Cacatnya sama persis dengan posyandu: master "Bontang Utara 1", berkas
     * "BONTANG UTARA I" — exact gagal dan LIKE '%BONTANG UTARA I%' juga tak
     * cocok, sehingga 7.386 dari 9.884 baris Juni 2026 (74,7%) berakhir
     * id_puskesmas NULL.
     *
     * Sengaja TANPA tahap "nomor 1 opsional": untuk puskesmas itu akan
     * menyamakan "Bontang Utara" dengan "Bontang Utara 1" — dua wilayah kerja
     * berbeda.
     *
     * @return array{id:int|null,alasan:string,kandidat:array<int,string>}
     */
    public function cocokkanPuskesmas(string $nama): array
    {
        $nama = trim($nama);
        if ($nama === '') {
            return ['id' => null, 'alasan' => 'kosong', 'kandidat' => []];
        }

        foreach (['exact' => 'normalisasi', 'tanpa-spasi' => 'rata'] as $alasan => $fn) {
            $kandidat = array_values(array_filter(
                $this->puskesmas,
                fn (array $p) => self::$fn($p['nama']) === self::$fn($nama)
            ));

            if (count($kandidat) === 1) {
                return ['id' => $kandidat[0]['id'], 'alasan' => $alasan, 'kandidat' => []];
            }
            if (count($kandidat) > 1) {
                return [
                    'id'       => null,
                    'alasan'   => 'ambigu',
                    'kandidat' => array_column($kandidat, 'nama'),
                ];
            }
        }

        return ['id' => null, 'alasan' => 'tak-ada', 'kandidat' => []];
    }

    /**
     * @return array{id:int|null,alasan:string,kandidat:array<int,string>}
     *         alasan: exact|tanpa-spasi|nomor-1-opsional|global-unik|ambigu|tak-ada|kosong
     */
    public function cocokkan(string $nama, ?string $puskesmas = null): array
    {
        $nama = trim($nama);
        if ($nama === '') {
            return $this->hasil(null, 'kosong');
        }

        $pool = $this->perPuskesmas[self::normalisasi((string) $puskesmas)] ?? [];

        $tahapan = [
            'exact'            => fn (string $a, string $b) => self::normalisasi($a) === self::normalisasi($b),
            'tanpa-spasi'      => fn (string $a, string $b) => self::rata($a) === self::rata($b),
            'nomor-1-opsional' => fn (string $a, string $b) => self::pokok($a) === self::pokok($b),
        ];

        foreach ($tahapan as $alasan => $cocok) {
            $kandidat = array_values(array_filter(
                $pool,
                fn (int $i) => $cocok($this->master[$i]['nama'], $nama)
            ));

            if (count($kandidat) === 1) {
                return $this->hasil($this->master[$kandidat[0]]['id'], $alasan);
            }
            if (count($kandidat) > 1) {
                return $this->hasil(null, 'ambigu', $kandidat);
            }
        }

        // Puskesmas di master bisa meleset; nama yang identik DAN unik se-master
        // masih aman dipulihkan.
        $global = $this->perNama[self::normalisasi($nama)] ?? [];
        if (count($global) === 1) {
            return $this->hasil($this->master[$global[0]]['id'], 'global-unik');
        }
        if (count($global) > 1) {
            return $this->hasil(null, 'ambigu', $global);
        }

        return $this->hasil(null, 'tak-ada');
    }

    /**
     * Master yang MIRIP dengan $nama — bahan tinjauan manusia, bukan hasil
     * pencocokan. Dipakai untuk mengisi berkas "perlu-keputusan" supaya Dinkes
     * punya kandidat konkret; aplikasi sendiri tidak pernah memilih dari sini.
     *
     * @return array<int,string> "Nama Posyandu (Nama Puskesmas)"
     */
    public function saran(string $nama): array
    {
        $cari = self::rata($nama);
        if ($cari === '') {
            return [];
        }

        $namaPuskesmas = [];
        foreach ($this->puskesmas as $p) {
            $namaPuskesmas[$p['id']] = $p['nama'];
        }

        $saran = [];
        foreach ($this->master as $m) {
            $kandidat = self::rata($m['nama']);
            if (str_contains($kandidat, $cari) || str_contains($cari, $kandidat)) {
                $saran[] = $m['nama'] . ' (' . ($namaPuskesmas[$m['id_puskesmas']] ?? '?') . ')';
            }
        }

        return $saran;
    }

    /** @param array<int,int> $idx */
    protected function hasil(?int $id, string $alasan, array $idx = []): array
    {
        return [
            'id'       => $id,
            'alasan'   => $alasan,
            // Sertakan id: dua master bisa bernama persis sama, dan tanpa id
            // laporan tinjauan jadi "Anggrek | Anggrek" yang tak bisa dipakai.
            'kandidat' => array_map(
                fn (int $i) => $this->master[$i]['nama'] . ' #' . $this->master[$i]['id'],
                $idx
            ),
        ];
    }

    /**
     * "Sejahtera II" → "SEJAHTERA 2"; "CENDRAWASIH1" → "CENDRAWASIH 1".
     * Tanda baca jadi pemisah, angka yang menempel huruf dipisah, Romawi → Arab.
     */
    public static function normalisasi(string $s): string
    {
        $s = strtoupper(trim($s));
        $s = preg_replace('/[^A-Z0-9]+/', ' ', $s);
        $s = preg_replace('/(?<=[A-Z])(?=[0-9])/', ' ', $s);

        $token = array_map(
            fn (string $t) => self::ROMAWI[$t] ?? $t,
            preg_split('/\s+/', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: []
        );

        return implode(' ', $token);
    }

    /** Normalisasi tanpa spasi sama sekali: "MEKAR SARI" = "MEKARSARI". */
    protected static function rata(string $s): string
    {
        return str_replace(' ', '', self::normalisasi($s));
    }

    /** Normalisasi dengan akhiran " 1" dibuang: "Flamboyan 1" = "FLAMBOYAN". */
    protected static function pokok(string $s): string
    {
        return preg_replace('/\s+1$/', '', self::normalisasi($s));
    }
}
