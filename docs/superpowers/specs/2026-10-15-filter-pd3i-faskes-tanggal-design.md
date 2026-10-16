# Filter Dasbor PD3I: Faskes Pelapor & Rentang Tanggal Laporan

Tanggal: 15 Oktober 2026
Status: disetujui di percakapan, menunggu reviu spec

## Permintaan

Dinkes (Zainuri, 14 Okt 2026): dasbor PD3I diberi dua filter tambahan.

1. Filter nama faskes pelapor.
2. Filter rentang tanggal laporan.

Keputusan pemilik produk:

- Rentang tanggal **berada di dalam Tahun** yang dipilih dan tidak bisa lintas tahun. Filter Tahun tetap ada.
- Angka per 100.000 penduduk dan grafik tetap dihitung seperti sekarang.
- Kode memakai satu objek filter (pendekatan a), bukan argumen berurutan yang terus bertambah.

## Perilaku

### Faskes Pelapor

- Komponen pilihan ganda (`ms-dropdown`), sama dengan Kab/Kota, Wilker, dan Kelurahan. Diletakkan setelah Kelurahan.
- Opsinya adalah nilai `surveillance_cases.instansi_pelapor` yang ada di data: distinct, tidak kosong, urut abjad. Polanya sama dengan daftar Wilker di `Pd3iDashboardController::index()`.
- Penyaringan: `whereIn(TRIM(instansi_pelapor), [...])`; opsi juga `DISTINCT TRIM(...)` — `Pd3iImport` menyimpan nama tanpa trim (amandemen audit 16 Okt).
- Kolom `id_faskes_pelapor` tidak pernah terisi di data nyata, sehingga label tabel "Per Faskes" (`COALESCE(rs.name, instansi_pelapor, …)`) sama dengan `instansi_pelapor`. Nama di filter dan nama di tabel itu konsisten. `id_faskes_pelapor` tidak disentuh.
- Penulisan nama faskes yang berbeda-beda (mis. "PKM Bontang Utara I" dan "Puskesmas Bontang Utara 1") tetap tampil sebagai opsi terpisah. Normalisasi nama tidak termasuk lingkup ini.

### Rentang tanggal laporan

- Dua input `type="date"`: **Tgl lapor dari** dan **s/d**. Keduanya opsional.
- `min`/`max` input dikunci ke 1 Januari–31 Desember Tahun terpilih. Saat Tahun diganti, kedua input dikosongkan.
- Hanya satu yang diisi: rentang terbuka sampai batas tahun (hanya "dari" berarti dari tanggal itu sampai 31 Des; hanya "s/d" berarti dari 1 Jan sampai tanggal itu).
- Normalisasi di server, karena URL bisa diketik tangan:
  - Tanggal yang formatnya bukan `Y-m-d` atau tidak valid dianggap kosong.
  - Tanggal di luar Tahun terpilih dianggap kosong, tidak digeser ke batas tahun.
  - Bila "dari" lebih besar dari "s/d", keduanya ditukar.
- Penyaringan: `where('tanggal_lapor', '>=', dari)` (kolom DATE, ramah indeks) dan/atau `'<=', sampai`, di samping `whereYear` yang tetap ada.

### Cakupan

| Bagian | Faskes | Rentang |
|---|---|---|
| Kinerja (KPI, AFP rate, % lab) | ya | ya |
| Demografi | ya | ya |
| Wilayah (peta/tabel) | ya | ya |
| Tren bulanan + per faskes/kecamatan/kelurahan | ya | ya (bulan di luar rentang bernilai 0) |
| Tren **5 tahunan** (`YEAR(tanggal_lapor)` tahun-4..tahun) | ya | **tidak** |
| Tren **epiweek** (`tanggal_onset`, 2 tahun) | ya | **tidak** |
| Ekspor Excel | ya | ya |
| Ekspor PDF | ya | ya; header mencantumkan periode dan faskes |

Grafik 5 tahunan dan epiweek memang lintas tahun, sehingga rentang di dalam satu tahun tidak bermakna di sana. Saat rentang aktif, di bawah kedua grafik itu muncul teks yang **selalu terlihat** (bukan tooltip): "Grafik ini tidak mengikuti rentang tanggal laporan." Teks ini mencegah orang salah membaca angka.

Penyebut per 100.000 tetap penduduk Tahun terpilih (`JumlahPenduduk`), tanpa prorata terhadap panjang rentang.

## Desain kode

### `App\Support\FilterPd3i` (baru)

Value object `final`, properti `readonly`:

```php
public function __construct(
    public readonly int $tahun,
    public readonly ?int $jenisKasusId = null,
    public readonly ?array $wilker = null,
    public readonly ?array $kelurahanId = null,
    public readonly ?array $kabKota = null,
    public readonly ?array $faskesPelapor = null,
    ?string $tanggalDari = null,
    ?string $tanggalSampai = null,
)
```

- Konstruktor menormalkan rentang (aturan pada bagian "Rentang tanggal laporan") lalu menyimpannya sebagai `public readonly ?string $tanggalDari` dan `$tanggalSampai` (`Y-m-d` atau `null`). Logika ini hanya memakai Carbon, tanpa `config()`/`now()`, supaya bisa diuji dengan `PHPUnit\Framework\TestCase` polos sesuai konvensi unit test proyek.
- `static dariRequest(Request $r): self` membaca `tahun` (bawaan tahun berjalan), `jenis_kasus_id`, `wilker[]`, `kelurahan_id[]`, `kab_kota[]`, `faskes_pelapor[]`, `tanggal_dari`, dan `tanggal_sampai`. `normalizeMulti()` pindah dari controller ke sini.
- `terapkan(Builder $q): void` menerapkan `whereYear`, rentang, dan `terapkanTanpaWaktu()`.
- `terapkanTanpaWaktu(Builder $q): void` menerapkan jenis kasus, kab_kota, wilker, id_kel, dan instansi_pelapor. Dipakai tren 5 tahunan dan epiweek, yang memasang batas waktunya sendiri.
- `adaRentang(): bool`.
- `labelPeriode(): string` menghasilkan "Tahun 2026" atau "1 Mar 2026 – 30 Jun 2026" (bulan berbahasa Indonesia; ujung yang kosong diisi batas tahun). Dipakai header PDF.

Kolom yang dipakai `terapkan*()` diberi prefix tabel (`surveillance_cases.`), karena `per_faskes` me-`leftJoin` `rumah_sakits`.

### `SurveillanceRepository`

- `getPd3iKinerja`, `getPd3iDemografi`, `getPd3iTren`, dan `getPd3iWilayah` kini menerima `(FilterPd3i $f)`.
- `pd3iBaseQuery(FilterPd3i $f)` = `SurveillanceCase::query()` lalu `$f->terapkan()`.
- `applyPd3iGeoFilters` dihapus; pemanggilnya (tren 5 tahunan, epiweek) memakai `$f->terapkanTanpaWaktu()`.
- Penduduk tetap `$f->tahun`.

### `Pd3iDashboardController`

- `parsePd3iFilters()` dan `normalizeMulti()` diganti `FilterPd3i::dariRequest($request)`.
- `index()` menambah `$faskesPelapors`.
- `exportPdf()` meneruskan `periode` (`labelPeriode()`) dan `namaFaskes` (gabungan nama faskes dengan koma) ke view PDF.

### `SurveillanceExport`

Konstruktor bernama yang ada dipertahankan, karena `EpidemiologiController::exportExcel` memakai `tahun: null` (semua tahun) dan itu tidak bisa diwakili `FilterPd3i` yang mewajibkan tahun. Tambahan:

- Properti opsional `?FilterPd3i $filterPd3i`, diisi lewat `static untukDasborPd3i(FilterPd3i $f): self`.
- `baseQuery()`: bila `filterPd3i` ada, panggil `$this->filterPd3i->terapkan($q)` sebagai ganti blok tahun/jenis/geo. `visibleTo(auth()->user())` tetap selalu dipasang lebih dulu.
- `Pd3iDashboardController::exportExcel` memakai `untukDasborPd3i()`.

### Tampilan `pd3i-dashboard.blade.php`

- `ms-dropdown` `data-ms="faskes_pelapor"` setelah Kelurahan.
- Dua `input[type=date]` (`#filter-tgl-dari`, `#filter-tgl-sampai`) dengan `<label>` yang terhubung `for`/`id`.
- `buildParams()` menambah `faskes_pelapor[]`, `tanggal_dari`, dan `tanggal_sampai` ke query AJAX, input tersembunyi form PDF, dan href Excel.
- Handler perubahan Tahun memperbarui `min`/`max` dan mengosongkan kedua tanggal.
- Elemen catatan "tidak mengikuti rentang" di bawah grafik 5 tahunan dan epiweek, tampil hanya bila salah satu tanggal terisi.

### PDF `admin/epidemiologi/pdf/pd3i-dashboard.blade.php`

Header menampilkan baris **Periode** (selalu ada) dan **Faskes Pelapor** (selalu; "Semua Faskes" bila tak dipilih — amandemen audit 16 Okt, konsisten dengan baris lain), sejajar dengan baris Wilker/Kelurahan yang sudah ada.

## Pengujian

Unit, `tests/Unit/Support/FilterPd3iTest.php` (TestCase polos):

- Tanpa tanggal: `adaRentang()` false.
- Hanya "dari": "sampai" tetap null.
- "dari" > "s/d": keduanya ditukar.
- Tanggal di luar tahun dan format salah (`'15/03/2026'`, `'2026-02-30'`) menjadi null.
- `labelPeriode()` untuk tanpa rentang, rentang penuh, dan rentang satu ujung.

Feature, `tests/Feature/Epidemiologi/Pd3iFilterFaskesTanggalTest.php`:

- Kinerja: kasus dua faskes, filter satu faskes, maka total hanya menghitung faskes itu.
- Kinerja: kasus Februari dan Juni, rentang 1 Mei–30 Jun, maka hanya kasus Juni.
- Tren: dengan rentang aktif, data 5 tahunan tetap memuat kasus di luar rentang, sementara bulanan tidak. Filter faskes berlaku di keduanya.
- Tren `per_faskes` dengan rentang aktif (memastikan prefix kolom tidak ambigu pada join).
- Excel: `SurveillanceExport::untukDasborPd3i()->query()->count()` menghormati faskes dan rentang, dan akun faskes tetap hanya melihat datanya sendiri.
- PDF: respons berisi teks periode dan nama faskes.
- `index`: opsi faskes berisi nilai distinct tanpa string kosong.

Tes lama `Pd3iKinerjaAfpRateTest` dan `Pd3iLabPercentagesTest` disesuaikan ke `getPd3iKinerja(new FilterPd3i(tahun: 2026))`. `ExportScopingFaskesTest` tidak berubah.

## Di luar lingkup

- Normalisasi atau penggabungan nama faskes pelapor.
- Mengisi `id_faskes_pelapor`.
- Rentang lintas tahun dan prorata penduduk.
