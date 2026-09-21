# Dasbor Kesmas — Tumbuh Kembang Balita & Posyandu

Tanggal: 2026-09-21 · Status: disetujui (percakapan 21 Sep 2026) · Spec 2 dari 2 (Spec 1 = Data Kesmas,
`2026-09-15-data-kesmas-design.md`)

## 1. Tujuan & keputusan pemilik produk

Halaman dasbor baru `admin/kesmas-dashboard` yang mewujudkan mockup Stitch `docs/dasbor kesmas/`
("Dashboard Pelayanan Tumbuh Kembang Balita & Posyandu") di atas data yang sudah ada di SIRINDU:
pengukuran (`data_anak`), imunisasi, dan kolom Kesmas dari Spec 1.

Keputusan yang sudah diambil (jangan ditawar ulang tanpa pemilik produk):

1. **Ikuti mockup penuh** — termasuk kartu IDL/IBL dan registri longitudinal per anak, walau
   sebagian angkanya juga ada di dasbor imunisasi/timbang. Angka yang sama **wajib** berasal dari
   service yang sama (`ImunisasiStatusService`, `StatusGiziService`), bukan dihitung ulang.
2. **Prorata per periode** untuk syarat "N× setahun": tahun ×1, semester ×½, triwulan ×¼.
   DDTKA & Vit A untuk filter triwulan dievaluasi pada **semester induknya** (jadwalnya semesteran).
3. **`ddtka` teks tak kosong = 1× DDTKA.** Kolom tetap teks bebas; tidak ada perubahan form.
   Label domain per usia di blok SDIDTK adalah teks statis pedoman SDIDTK, bukan metrik.
4. **Pembagi = anak terdaftar dengan umur pada akhir periode** (bukan hari ini), di wilayah
   terfilter. Tahun lalu bisa dilihat ulang dengan sasaran yang benar.
5. **Seksi tambahan "Layanan & Lingkungan"** untuk field Kesmas yang tidak ada di mockup, dengan
   pembagi *anak yang datanya terisi* dan jumlah "belum diisi" selalu ditampilkan — NULL berarti
   belum ditanya, bukan "tidak" (prinsip Spec 1 §1.3).
6. **Pendekatan A**: agregat server-render (pola dasbor imunisasi) + registri lewat endpoint JSON.
7. Akses mengikuti Export Kesmas: super-admin & admin sama-sama bisa buka, `isFaskesSurveilans()`
   ditolak 403, tanpa scoping per kelurahan (filter wilayah dari request).

## 2. Periode & sasaran

### 2.1 Filter

| Parameter | Nilai | Default |
|---|---|---|
| `tahun` | integer 2020–tahun ini+1 | tahun ini |
| `periode` | `tahun` · `s1` · `s2` · `tw1` … `tw4` | `tahun` |
| `id_kecamatan`, `id_kelurahan`, `id_rt`, `id_posyandu`, `id_puskesmas` | `exists` | kosong |
| `usia` (chip) | `semua` · `bayi` (0–11) · `baduta` (12–23) · `balita` (24–59) · `prasekolah` (60–72) | `semua` |

Chip `usia` hanya memengaruhi registri (dibawa ke `api/registri`) dan penyorotan baris SDIDTK;
kartu SPM selalu per kohortnya. Filter wilayah memakai helper yang sama dengan dasbor imunisasi:
`id_puskesmas` → `WilkerPuskesmas::catchmentKelurahanIds()`.

### 2.2 `App\Support\PeriodeKesmas` (value object, tanpa DB)

```php
PeriodeKesmas::dari(int $tahun, string $periode): self
->awal(): Carbon            // 1 Jan / 1 Jul / awal triwulan
->akhir(): Carbon           // 31 Des / 30 Jun / akhir triwulan
->p(): float                // 1, 0.5, 0.25
->semesterAwal(), ->semesterAkhir(): Carbon   // semester induk (untuk DDTKA & Vit A); untuk `tahun` = awal..akhir
->syarat(int $nPerTahun): int                  // (int) ceil($nPerTahun * $this->p())
->label(): string           // "Tahun 2026" | "Semester I 2026 (1 Jan–30 Jun)" | "Triwulan III 2026 (1 Jul–30 Sep)"
```

`syarat(8)` → 8 / 4 / 2; `syarat(2)` → 2 / 1 / 1. Untuk triwulan, `syarat(2)` dievaluasi pada
`semesterAwal..semesterAkhir`; `syarat(8)` pada `awal..akhir`. `periode` tidak valid → 422.

### 2.3 Sasaran

Umur = `TIMESTAMPDIFF(MONTH, anak.tgl_lahir, :akhir)`; anak dengan `tgl_lahir` NULL atau umur < 0
tidak dihitung. Sasaran per kelompok dihitung dalam **satu** query `SUM(CASE WHEN umur BETWEEN a AND b
THEN 1 ELSE 0 END)` untuk: bayi 0–11, baduta 12–23, anak balita 12–59, balita 0–59, balita 24–59,
prasekolah 60–72, semua 0–72, dan umur tahun 0 / 1 / 2 / 3–6 (untuk CKG).

### 2.4 Subquery kunjungan per anak

Dibangun sekali, dipakai kartu 1–4 lewat `joinSub` ke `anak`:

```sql
SELECT id_anak,
  SUM(tgl_kunjungan BETWEEN :awal AND :akhir)                                          AS n_timbang,
  SUM(tgl_kunjungan BETWEEN :semAwal AND :semAkhir AND ddtka IS NOT NULL AND ddtka <> '') AS n_ddtka,
  SUM(tgl_kunjungan BETWEEN :semAwal AND :semAkhir AND vit_a = 1)                       AS n_vita,
  SUM(tgl_kunjungan BETWEEN :awal AND :akhir AND lk > 0)                                AS n_lk
FROM data_anak WHERE tgl_kunjungan BETWEEN LEAST(:awal,:semAwal) AND GREATEST(:akhir,:semAkhir)
GROUP BY id_anak
```

Semua `sumber` kunjungan ikut (OT, manual, capil, imunisasi) — berbeda dari dasbor timbang yang
mengunci `operasi_timbang`. Tidak ada pengecualian `sumber` (termasuk `dummy` — nilai itu menandai
asal baris, bukan data palsu; dasbor imunisasi juga tidak mengecualikannya).

## 3. Definisi tiap angka

Notasi: `T8 = syarat(8)`, `T2 = syarat(2)`. Semua "anak" = `COUNT(DISTINCT anak.id)`.

| Blok | Sasaran | Pembilang |
|---|---|---|
| **K1 · SPM Pelayanan Kesehatan Balita** | 0–59 bln | anak yang memenuhi syarat K2 (bila 0–11) atau K3 (bila 12–59) |
| **K2 · Kohort Bayi 0–11** | 0–11 bln | `n_timbang ≥ T8 ∧ n_ddtka ≥ T2 ∧ (umur < 6 ∨ n_vita ≥ 1) ∧ n_lk ≥ 1`. Sub-%: 8× Tbg, 2× DDTKA, Vit A (pembagi bayi 6–11), LK, IDL (dari `getIdlCoverage`, **bukan** syarat). Footer: "Sisa belum lengkap: n (x %)" |
| **K3 · Kohort Anak Balita 12–59** | 12–59 bln | `n_timbang ≥ T8 ∧ n_ddtka ≥ T2 ∧ n_vita ≥ T2`. Sub-%: 8× Tbg, 2× DDTKA, Vit A (2×), IBL (dari `getIblCoverage`). Footer: "Kesenjangan target: n (x %)" |
| **K4 · Pemantauan Lengkap T&K** | 0–72 bln | `n_timbang ≥ T8 ∧ n_ddtka ≥ T2`. Footer: "n balita perlu perhatian" = anak yang kunjungan **terakhir dalam periode** punya `UPPER(TRIM(ntob)) = 'T'` atau `zscore_bb_u <= -2.01` |
| **SDIDTK** | 0–11 · 12–23 · 24–59 · 60–72 + total 0–72 | anak dengan ≥ 1 kunjungan ber-`ddtka` dalam `awal..akhir` (bukan semester induk — ini realisasi periode). Callout "Fokus intervensi": kelompok dengan gap (100 − %) terbesar; teks: "Kelompok {label} ({a}–{b} bln) memiliki gap terbesar ({gap} %)". Bila semua sasaran 0 → callout disembunyikan |
| **CKG** | umur tahun 0 ("Bayi baru lahir"), 1, 2, 3–6 | anak dengan `tgl_penanda_ckg` dalam `awal..akhir` (di kunjungan mana pun). Footer: "Bebas karies" = kunjungan dalam periode `pemeriksaan_gigi = 'Sehat'` ÷ kunjungan `pemeriksaan_gigi IS NOT NULL`; "Rujuk dokter gigi" = anak dengan ≥ 1 kunjungan `rujukan = 'Dokter gigi'` dalam periode |
| **IDL & IBL** | — | `getIdlCoverage($filters)`, `getIblCoverage($filters)`, `getAlasanTidakImunisasi($filters)` (4 teratas). Angka **tidak** terpengaruh `periode` (status imunisasi adalah keadaan saat ini) — dicatat di keterangan kartu |
| **Layanan per kunjungan** (9 layanan `config('kesmas.layanan')`) | anak dengan ≥ 1 kunjungan dalam periode yang kolomnya `IS NOT NULL` | anak dengan ≥ 1 kunjungan dalam periode yang kolomnya `= 1`. Tambahan: "belum diisi" = sasaran 0–72 − pembagi |
| **Skrining neonatal** (SHK, SHAK, G6PD, Hep B) | bayi 0–11 dengan kolom `IS NOT NULL` | sebaran `normal` / `tidak_normal` / `belum` (Hep B: `non_reaktif` / `reaktif` / `belum`). "belum diisi" = bayi 0–11 − pembagi |
| **Sanitasi** (air bersih, jamban sehat, keluarga merokok) | anak 0–72 dengan kolom `IS NOT NULL` | `= 1`. Untuk merokok, yang ditampilkan adalah % **ya** dengan warna terbalik (tinggi = buruk). "belum diisi" = sasaran − pembagi |

Aturan tampilan angka: sasaran/pembagi 0 → persen "—" dan bar kosong; persen dibulatkan 1 desimal,
pemisah ribuan titik & desimal koma (helper yang sudah dipakai dasbor imunisasi).

### 3.1 Registri (`GET admin/kesmas-dashboard/api/registri`)

Parameter: semua filter §2.1 + `q` (nama / NIK / nama ibu / nama ayah, `LIKE %q%`) + `status_gizi`
(`semua` · `normal` · `stunted` · `underweight` · `wasted` · `perhatian`) + `page`. 20 baris/halaman,
urut `nama`. Sasaran = anak di kelompok `usia` (umur pada akhir periode), sama dengan pembagi kartu.

Per baris: `id`, `nama`, `nik`, `jk`, `umur_bln`, `tgl_lahir`, `nama_ibu`, `nama_ayah`, `kelurahan`,
`rt`, `posyandu`, kunjungan terakhir dalam periode (`tgl`, `bb`, `tb`, `lk`, `kategori` dari
`StatusGiziService::enumEppgbm(zscore_bb_u, zscore_pb_u, zscore_bb_pb)`), `idl` & `ibl`
(`lengkap` / `belum` / `belum_usia` via `isIdlLengkap()` / `isIblLengkap()` — 20 model per halaman),
`catatan` (`catatan_pengukuran` kunjungan terakhir, dipotong 80 karakter), `url_detail`.

`status_gizi = perhatian` = definisi "perlu perhatian" K4. Kategori lain dari `enumEppgbm` pada
kunjungan terakhir: `stunted` = `tb_u` ∈ {stunted, severely_stunted}; `underweight` = `bb_u` ∈
{underweight, severely_underweight}; `wasted` = `bb_tb` ∈ {wasted, severely_wasted}; `normal` = ketiganya
bukan kategori di atas dan tidak `perhatian`. Badge di tabel menampilkan kategori terburuk dengan urutan
wasted > underweight > stunted > lainnya > normal. Anak tanpa kunjungan dalam periode
tetap tampil (antropometri "—") kecuali filter `status_gizi` ≠ `semua`.

Respons: `{ data: [...], total, page, last_page, per_page: 20 }`. NIK ditampilkan penuh seperti di
Data Anak (halaman ini di balik login yang sama).

## 4. Arsitektur

### 4.1 Berkas

| Berkas | Isi |
|---|---|
| `app/Support/PeriodeKesmas.php` | §2.2 |
| `app/Support/FilterWilayahAnak.php` | trait: `applyWilayahFilters($query, array $filters, string $alias = '')` — dipindah dari `ImunisasiStatusService` (isi sama, ditambah dukungan alias tabel untuk `DB::table('anak as a')`) |
| `app/Services/KesmasDashboardService.php` | `sasaran(PeriodeKesmas, array $filters): array`, `spmKohort()`, `pemantauanTk()`, `sdidtk()`, `ckg()`, `layananLingkungan()`, `registri(PeriodeKesmas, array $filters, string $q, string $statusGizi, int $page): array`. Hanya `DB::table`; model `Anak` cuma di `registri()` (≤ 20 per halaman) |
| `app/Http/Controllers/KesmasDashboardController.php` | `index(Request)`, `registri(Request): JsonResponse`; constructor tolak `isFaskesSurveilans()` |
| `resources/views/admin/kesmas/dashboard.blade.php` + `partials/_filter.blade.php`, `_registri.blade.php` | §5 |
| `public/css/dasbor-base.css` | token & komponen dasar `.im-*` yang dipindah dari `admin/imunisasi/dashboard.blade.php` |
| `routes/web.php` | di grup `is_admin`: `Route::prefix('kesmas-dashboard')` → `admin.kesmas.dashboard`, `admin.kesmas.registri` |
| `leftsidebar.blade.php` | item "Kesmas" di submenu Dashboard, kedua cabang (super-admin & admin), setelah "Imunisasi"; `$dashboard` routeIs + `admin.kesmas.*` |

### 4.2 Perubahan berkas lama

- `AdminController::alasanTidakImunisasiData()` (privat) → `ImunisasiStatusService::getAlasanTidakImunisasi(array $filters): array`, isi dipindah apa adanya; `imunisasiDashboard()` memanggil versi service. Tes dasbor imunisasi yang ada mengunci perilakunya.
- `ImunisasiStatusService::applyWilayahFilters()` → memakai trait §4.1.
- `admin/imunisasi/dashboard.blade.php`: blok CSS dasar dihapus, diganti `<link rel="stylesheet" href="{{ asset('css/dasbor-base.css') }}">`; aturan khusus halaman tetap inline. Nama kelas tidak berubah.

### 4.3 Aliran

```
GET /admin/kesmas-dashboard?tahun&periode&wilayah…&usia
  → validasi → PeriodeKesmas → service: sasaran, spmKohort, pemantauanTk, sdidtk, ckg, layananLingkungan
  → ImunisasiStatusService: getIdlCoverage, getIblCoverage, getAlasanTidakImunisasi
  → view (semua kartu terisi server-side)
JS: fetch api/registri?…&q&status_gizi&page → render tabel (skeleton → baris | empty-state)
```

Ganti filter utama = submit form (reload). Chip usia, cari, status gizi, paginasi = hanya `fetch`
registri (tanpa reload), nilai chip ikut ditulis ke URL (`history.replaceState`) supaya bisa dibagikan.

### 4.4 Performa

Target < 2 detik & memori < 16 MB tambahan pada 10 rb anak / 100 rb kunjungan, `memory_limit` prod
128 MB. Cara: (1) subquery §2.4 sekali, (2) sasaran satu query, (3) setiap blok satu–dua query agregat,
(4) tanpa loop PHP per anak di halaman utama, (5) registri `LIMIT 20` + `COUNT(*)` terpisah.
Dikunci `KesmasDashboardMemoriTest` (pola `ImunisasiDashboardMemoriTest`: 2.000 anak × 6 kunjungan,
kenaikan memori puncak < 16 MB untuk seluruh halaman, dan jumlah query gabungan enam method
`KesmasDashboardService` (tanpa `ImunisasiStatusService`) ≤ 20 lewat `DB::enableQueryLog()`).

## 5. Tampilan

Bahasa visual = dasbor imunisasi (`.im-page`: token oklch hijau Kemenkes, Barlow / Barlow Condensed,
kartu radius 14 px). Prefiks kelas halaman ini `.km-` untuk yang khusus.

1. **Kepala** — judul "Dashboard Kesmas — Tumbuh Kembang Balita"; baris kecil
   "{wilayah terpilih atau 'Kota Bontang'} · {PeriodeKesmas::label()}". Kanan: tombol `Export Data`
   (`admin.export.kesmas.index` + query wilayah).
2. **Filter bar** (`_filter`) — Tahun, Periode, Kecamatan → Kelurahan → RT (cascade lewat atribut
   `data-kec` / `data-kel`; **jangan** jQuery `:hidden` pada `<option>`), Posyandu, Puskesmas,
   `Terapkan` / `Reset`. Chip usia = `<button aria-pressed>`.
3. **Baris 1 — 4 kartu SPM** (`.im-cards`: 4 → 2 → 1 kolom). Tiap kartu: label kecil, judul, sub,
   angka besar `n / sasaran · %`, bar progres, chip sub-indikator berwarna (≥ 80 hijau · 60–79 amber ·
   < 60 merah — warna dari token yang sudah lolos kontras), footer sesuai §3. Footer K4 menautkan ke
   registri dengan `status_gizi=perhatian` (scroll ke tabel + set select).
4. **Baris 2 — 3 blok** (3 → 1 kolom): SDIDTK (baris per usia: label, domain statis kecil, bar,
   `n / sasaran (%)`; callout kuning "Fokus intervensi"), CKG (baris per usia + footer bebas karies &
   rujuk gigi), IDL & IBL (donut Chart.js CDN yang sama: IDL lengkap / belum lengkap / IBL; daftar
   4 penyebab; tautan "Lihat dasbor imunisasi →"; keterangan "status saat ini, tidak mengikuti
   periode").
5. **Baris 3 — Layanan & Lingkungan** (kartu lebar, 3 panel: Layanan per kunjungan, Skrining
   neonatal, Sanitasi rumah). Bar horizontal `n / terisi · %`; skrining = bar bertumpuk 3 warna
   dengan legenda teks (bukan warna saja). Setiap panel memuat baris "**x anak belum diisi**". Bila
   pembagi 0 untuk seluruh panel → empty-state "Belum ada data — lengkapi lewat Edit Anak (kartu
   Kesmas)", bukan bar 0 %.
6. **Baris 4 — Registri** (`_registri`) — header: "Registri Longitudinal & Pelayanan Balita",
   badge "Total: n balita", input cari (`label` sr-only, debounce 300 ms), select status gizi.
   Tabel: No · Nama & NIK (+ JK, umur) · Orang tua · Wilayah & Posyandu · Antropometri terakhir
   (BB / TB / LK + badge gizi) · IDL · IBL · Catatan (teks + tgl) · Aksi (Detail →
   `admin.showAnak`). Badge IDL/IBL: Lengkap (hijau), Belum (merah), Belum masuk usia (abu, teks
   miring). Skeleton 5 baris saat memuat; empty-state "Tidak ada balita yang cocok"; galat fetch →
   baris "Gagal memuat registri — coba lagi" dengan tombol ulang. Paginasi "Menampilkan a–b dari n"
   + prev / halaman / next; `<tbody aria-live="polite">`.

Aturan lintas seksi: kontras ≥ 4,5 : 1 untuk semua teks/badge (badge teal `#0891b2`+putih ditolak
audit Spec 1 — jangan dipakai); tiap kontrol filter punya `label for` / `id` unik; BS4
(`data-toggle`, bukan `data-bs-toggle`); layout HP: kartu 1 kolom, tabel `overflow-x:auto` dengan
`min-width`, gutter 16 px; `@section('x') isi @endsection` selalu berspasi; angka memakai
`font-variant-numeric: tabular-nums`.

## 6. Penanganan galat

| Kondisi | Perilaku |
|---|---|
| `periode` / `tahun` / wilayah tidak valid | 422 (halaman: redirect back dengan error; JSON: 422 `{errors}`) |
| Pengguna `isFaskesSurveilans()` | 403 di kedua route |
| Sasaran 0 (wilayah tanpa anak) | semua kartu tampil "—", registri empty-state; tidak ada pembagian nol |
| Kolom Kesmas belum ada di DB (prod belum migrate) | tidak ditangani khusus — migrasi Spec 1 prasyarat; dicatat di README deploy |
| `KelompokVaksin` IBL belum di-seed | mengikuti fallback `ImunisasiStatusService` (12–23) |
| Fetch registri gagal (jaringan/500) | baris galat + tombol "Coba lagi"; kartu tidak terpengaruh |

## 7. Pengujian

| Tes | Cakupan |
|---|---|
| `tests/Unit/PeriodeKesmasTest` | awal/akhir/p/semester/syarat untuk 7 periode; `syarat(8)` = 8/4/2, `syarat(2)` = 2/1/1; label; periode tak dikenal → exception |
| `tests/Feature/Kesmas/KesmasDashboardServiceTest` | Data mini yang **dirakit per kasus** (pola `ImunisasiRutinDashboardServiceTest`; `ImunisasiStatusService::flushCache()` di `setUp`): sasaran umur pada akhir periode (anak lahir setelah akhir periode tidak dihitung; anak yang kini 6 thn tetap balita untuk tahun lalu); K2 lolos/gagal tiap syarat (timbang 7 vs 8, ddtka 1 vs 2, vit A umur < 6 dibebaskan, lk 0); K3 dengan triwulan → T8 = 2, DDTKA dievaluasi semester induk; K1 = gabungan K2 ∪ K3; K4 "perlu perhatian" via `ntob='T'` dan via `zscore_bb_u`; SDIDTK per kelompok + fokus intervensi; CKG per umur tahun + bebas karies + rujuk gigi; layanan: pembagi hanya yang terisi, `belum diisi` benar; skrining & sanitasi; filter wilayah & puskesmas (catchment) |
| `tests/Feature/Kesmas/KesmasDashboardControllerTest` | 200 untuk super-admin & admin, 403 faskes surveilans, 302 tamu; angka kartu muncul di HTML (assertion dikurung per kartu, helper seperti `baris()`); validasi 422; tautan Export membawa filter; sidebar memuat menu "Kesmas" di kedua cabang; `novalidate` tidak diperlukan (tanpa `required`) |
| `tests/Feature/Kesmas/KesmasRegistriTest` | JSON bentuk & paginasi 20; `q` nama/NIK/ortu; `status_gizi` tiap nilai; `usia`; IDL/IBL `belum_usia` untuk bayi < 12 bln; anak tanpa kunjungan tampil "—"; 403/422 |
| `tests/Feature/Kesmas/KesmasDashboardMemoriTest` | 2.000 anak × 6 kunjungan: memori puncak < 16 MB (halaman), query service Kesmas ≤ 20 |
| `tests/Feature/Kesmas/KesmasDashboardBladeTest` | tidak ada `@section('x')isi@endsection` tanpa spasi; tidak ada `data-bs-toggle`; tidak ada `:hidden` pada option; `label for` = `id` untuk semua kontrol filter; `aria-live` ada |
| Tes lama | `ImunisasiDashboardControllerTest` & `ImunisasiRutinDashboardServiceTest` tetap hijau setelah pemindahan `alasanTidakImunisasi` & trait wilayah |

Browser (manual, Chrome, sebelum commit akhir): buka dasbor imunisasi (CSS dasar tak berubah);
dasbor Kesmas dengan tiap periode; cascade Kec→Kel→RT; chip usia & cari registri tanpa reload;
tautan K4 → registri `perhatian`; lebar 375 px.

## 8. Di luar lingkup

- Tombol mockup Sinkron Data, Unduh Laporan, Muat Ulang, Catat Kunjungan, Ingatkan WhatsApp; export PDF.
- Topbar/sidebar mockup — memakai layout SIRINDU.
- Mengubah `ddtka` jadi select hasil (Sesuai/Meragukan/Penyimpangan) — ditolak sementara (§1.3).
- Sasaran manual/proyeksi per puskesmas — ditolak (§1.4).
- Scoping per kelurahan untuk akun admin — mengikuti konvensi Export Kesmas.
- Quicklink beranda — tidak ditambah (menu sidebar cukup).
- Perbaikan temuan audit Spec 1 (badge teal, tooltip, collapse) — pekerjaan terpisah.

## 9. Alternatif yang ditolak

- **Hanya indikator baru + tautan ke dasbor lain** — pemilik produk memilih mockup penuh.
- **Semua JSON + JS (pola dasbor timbang)** — dua pola dalam satu modul tanpa keuntungan; tes tampilan lebih sulit.
- **Semua server-render termasuk registri** — tiap balik halaman menghitung ulang agregat se-kota.
- **Tahun kalender penuh / 12 bulan bergulir** untuk syarat SPM — tahun berjalan kosong / tak selaras laporan tahunan.
- **Umur hari ini sebagai sasaran** — salah untuk tahun lalu.
