<?php

namespace App\Exports;

use App\Models\Anak;
use App\Services\KesmasPresenter as K;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Sheet "Per Kunjungan" — satu baris per data_anak (filter wilayah anak + rentang tgl_kunjungan). */
final class KesmasKunjunganSheet
{
    /** Usia (bln), BB, TB — tetap numerik, termasuk nol. */
    private const KOLOM_ANGKA = ['D', 'E', 'F'];

    /** Jumlah anak per kelompok; kunjungan seluruh kelompok dimuat sekaligus (≈ 8 KB per baris). */
    private const POTONGAN = 100;

    public function __construct(private array $filter) {}

    /** Anak lolos filter wilayah, urut nama — kolom sempit saja; `id` pengurut kedua agar potongan stabil. */
    private function anakQuery(): Builder
    {
        return KesmasAnakSheet::terapkanWilayah(
            Anak::query()->select('id', 'nik', 'nama')->orderBy('nama')->orderBy('id'),
            $this->filter
        );
    }

    /**
     * Kunjungan sekelompok anak (rentang tanggal), kronologis. `DB::table()` bukan model, karena
     * DataAnak tak punya cast/accessor (nilainya sama persis). Tetap `select *`: dengan ±80 kolom
     * sebaris ≈ 8 KB, makanya kelompoknya kecil (POTONGAN) — jangan menyaring kolom di sini, karena
     * kolom yang terlupa dari map() menjadi sel kosong tanpa error.
     */
    private function kunjunganQuery(Collection $idAnak): QueryBuilder
    {
        $q = DB::table('data_anak')->whereIn('id_anak', $idAnak)->orderBy('tgl_kunjungan')->orderBy('id');

        if (!empty($this->filter['dari'])) {
            $q->whereDate('tgl_kunjungan', '>=', $this->filter['dari']);
        }
        if (!empty($this->filter['sampai'])) {
            $q->whereDate('tgl_kunjungan', '<=', $this->filter['sampai']);
        }

        return $q;
    }

    /**
     * Judul lalu satu baris per kunjungan, urut nama anak lalu tanggal. Anak dibaca per kelompok
     * (kolom sempit), lalu kunjungan satu kelompok diambil dengan SATU query — tidak pernah `get()`
     * seluruhnya.
     *
     * Bukan join + `ORDER BY anak.nama` dengan offset: pengurutan hasil join itu diulang di setiap
     * halaman (terukur 18,8 dtk untuk 16 rb kunjungan, vs 0,6 dtk tanpa pengurutan) dan membuat
     * ekspor seluruh kota mendekati batas waktu server web.
     *
     * @return Generator<int, list<string|int|float|null>>
     */
    public function baris(): Generator
    {
        yield $this->headings();

        $angka = KesmasExport::indeksKolom(self::KOLOM_ANGKA);
        foreach ($this->anakQuery()->lazy(500)->chunk(self::POTONGAN) as $potongan) {
            $kelompok = $potongan->values()->collect(); // anak berkolom sempit; dipakai dua kali di bawah
            $kunjungan = $this->kunjunganQuery($kelompok->pluck('id'))->get()->groupBy('id_anak');

            foreach ($kelompok as $anak) {
                foreach ($kunjungan->get($anak->id, []) as $d) {
                    // map() membaca nik & nama dari baris kunjungan (dulu kolom hasil join).
                    $d->nik = $anak->nik;
                    $d->nama = $anak->nama;

                    yield KesmasExport::rapikan($this->map($d), $angka);
                }
            }
        }
    }

    public function title(): string
    {
        return 'Per Kunjungan';
    }

    public function headings(): array
    {
        $h = ['NIK', 'Nama', 'Tgl Kunjungan', 'Usia (bln)', 'BB (kg)', 'TB (cm)', 'Tgl Penanda CKG'];
        foreach (config('kesmas.layanan') as $def) {
            $h[] = $def['kolom'];
        }

        return array_merge($h, [
            'Pemeriksaan Gigi', 'Rujukan', 'MT Pangan Lokal', 'Catatan', 'Pemeriksaan Lainnya', 'Pola Makan', 'Pola Asuh', 'Intervensi',
        ]);
    }

    /** @param object $d baris data_anak (stdClass) yang sudah diberi nik & nama anaknya */
    public function map($d): array
    {
        $r = [$d->nik, $d->nama, $d->tgl_kunjungan, $d->bln, $d->bb, $d->tb, $d->tgl_penanda_ckg];
        foreach (array_keys(config('kesmas.layanan')) as $k) {
            $r[] = K::yaTidak($d->$k);
        }

        return array_merge($r, [
            $d->pemeriksaan_gigi, $d->rujukan, $d->mt_pangan_lokal, $d->catatan_pengukuran,
            $d->pemeriksaan_lainnya, $d->pola_makan, $d->pola_asuh, $d->intervensi,
        ]);
    }
}
