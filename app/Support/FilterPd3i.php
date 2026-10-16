<?php

namespace App\Support;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Request;

/**
 * Filter Dasbor PD3I — satu tempat untuk membaca, menormalkan, dan menerapkan
 * filter (spec docs/superpowers/specs/2026-10-15-filter-pd3i-faskes-tanggal-design.md).
 *
 * Rentang tanggal lapor selalu DI DALAM $tahun: tanggal di luar tahun atau tak
 * valid dianggap kosong (tidak digeser ke batas tahun), "dari" > "s/d" ditukar.
 * Tanpa config(); now() hanya di dariRequest() — supaya bisa diuji dengan
 * TestCase polos.
 */
final class FilterPd3i
{
    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public readonly ?string $tanggalDari;
    public readonly ?string $tanggalSampai;

    public function __construct(
        public readonly int $tahun,
        public readonly ?int $jenisKasusId = null,
        public readonly ?array $wilker = null,
        public readonly ?array $kelurahanId = null,
        public readonly ?array $kabKota = null,
        public readonly ?array $faskesPelapor = null,
        ?string $tanggalDari = null,
        ?string $tanggalSampai = null,
    ) {
        $dari   = $this->tanggalDalamTahun($tanggalDari);
        $sampai = $this->tanggalDalamTahun($tanggalSampai);

        if ($dari !== null && $sampai !== null && $dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        $this->tanggalDari   = $dari;
        $this->tanggalSampai = $sampai;
    }

    public static function dariRequest(Request $request): self
    {
        $teks = fn (string $k) => is_string($request->input($k)) ? $request->input($k) : null;

        return new self(
            tahun:         (int) $request->get('tahun', now()->year),
            jenisKasusId:  $request->filled('jenis_kasus_id') ? (int) $request->input('jenis_kasus_id') : null,
            wilker:        self::multi($request->input('wilker')),
            kelurahanId:   self::multi($request->input('kelurahan_id'), true),
            kabKota:       self::multi($request->input('kab_kota')),
            faskesPelapor: self::multi($request->input('faskes_pelapor')),
            tanggalDari:   $teks('tanggal_dari'),
            tanggalSampai: $teks('tanggal_sampai'),
        );
    }

    /** Tahun + rentang tanggal lapor + seluruh filter non-waktu. */
    public function terapkan(Builder $query): void
    {
        $query->whereYear('surveillance_cases.tanggal_lapor', $this->tahun);

        // tanggal_lapor bertipe DATE → pembanding langsung (tetap bisa memakai indeks).
        if ($this->tanggalDari !== null) {
            $query->where('surveillance_cases.tanggal_lapor', '>=', $this->tanggalDari);
        }
        if ($this->tanggalSampai !== null) {
            $query->where('surveillance_cases.tanggal_lapor', '<=', $this->tanggalSampai);
        }

        $this->terapkanTanpaWaktu($query);
    }

    /**
     * Filter non-waktu saja — untuk tren 5 tahunan & epiweek yang memasang batas
     * waktunya sendiri (lintas tahun, jadi rentang di dalam tahun tak bermakna).
     * Kolom berprefix tabel: beberapa query me-leftJoin rumah_sakits/kecamatan/kelurahan.
     */
    public function terapkanTanpaWaktu(Builder $query): void
    {
        if ($this->jenisKasusId) {
            $query->where('surveillance_cases.id_jenis_kasus', $this->jenisKasusId);
        }
        if (!empty($this->kabKota)) {
            $query->whereIn('surveillance_cases.kab_kota', $this->kabKota);
        }
        if (!empty($this->wilker)) {
            $query->whereIn('surveillance_cases.wilker_puskesmas', $this->wilker);
        }
        if (!empty($this->kelurahanId)) {
            $query->whereIn('surveillance_cases.id_kel', $this->kelurahanId);
        }
        if (!empty($this->faskesPelapor)) {
            // Pd3iImport menyimpan nama tanpa trim; opsi dropdown juga di-TRIM, jadi
            // bandingkan nilai yang di-trim di kedua sisi.
            $query->whereIn(new Expression('TRIM(surveillance_cases.instansi_pelapor)'), $this->faskesPelapor);
        }
    }

    public function adaRentang(): bool
    {
        return $this->tanggalDari !== null || $this->tanggalSampai !== null;
    }

    /** "Tahun 2026" atau "1 Mar 2026 – 30 Jun 2026" (ujung kosong = batas tahun). */
    public function labelPeriode(): string
    {
        if (!$this->adaRentang()) {
            return 'Tahun ' . $this->tahun;
        }

        return $this->formatTanggal($this->tanggalDari ?? "{$this->tahun}-01-01")
            . ' – '
            . $this->formatTanggal($this->tanggalSampai ?? "{$this->tahun}-12-31");
    }

    /** "_2026-05-01_sd_2026-06-30" untuk nama berkas ekspor, "" bila tanpa rentang. */
    public function akhiranNamaBerkas(): string
    {
        if (!$this->adaRentang()) {
            return '';
        }

        return '_' . ($this->tanggalDari ?? "{$this->tahun}-01-01")
            . '_sd_' . ($this->tanggalSampai ?? "{$this->tahun}-12-31");
    }

    private function formatTanggal(string $ymd): string
    {
        [$y, $m, $d] = explode('-', $ymd);

        return (int) $d . ' ' . self::BULAN[(int) $m - 1] . ' ' . $y;
    }

    private function tanggalDalamTahun(?string $nilai): ?string
    {
        $nilai = $nilai === null ? '' : trim($nilai);
        if ($nilai === '') {
            return null;
        }

        // '!' mereset field lain; format ulang harus identik → menolak 2026-02-30 & 2026-3-1.
        $tanggal = DateTimeImmutable::createFromFormat('!Y-m-d', $nilai);
        if ($tanggal === false || $tanggal->format('Y-m-d') !== $nilai) {
            return null;
        }

        return (int) $tanggal->format('Y') === $this->tahun ? $nilai : null;
    }

    /** Normalisasi multiselect (name[]=… atau skalar) → array bersih, atau null bila kosong. */
    private static function multi($value, bool $asInt = false): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $items = is_array($value) ? $value : [$value];
        // Hanya skalar: `wilker[][]=x` dulu memicu "Array to string conversion" → 500.
        $items = array_filter($items, fn ($v) => is_scalar($v) && trim((string) $v) !== '');

        if (empty($items)) {
            return null;
        }

        $items = $asInt
            ? array_map('intval', $items)
            : array_map(fn ($v) => trim((string) $v), $items);

        return array_values(array_unique($items));
    }
}
