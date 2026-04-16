# Implementation Plan: Dashboard Surveilans PD3I

**Branch**: `001-pd3i-dashboard` | **Date**: 2026-04-11 | **Spec**: [spec.md](spec.md)  
**Input**: Feature specification dari `specs/001-pd3i-dashboard/spec.md`

---

## Summary

Buat halaman dashboard surveilans PD3I 4-tab (Kinerja Surveilans, Demografi, Tren, Wilayah) yang dapat diakses oleh pengguna `super-admin`. Dashboard mengambil data dari `surveillance_cases` via 4 endpoint API yang dipanggil paralel saat filter diubah. Semua tab tersedia tanpa loading saat navigasi. Tersedia tombol export PDF satu file semua tab. Stack: Laravel 12 + Chart.js (sudah ada) + Leaflet.js (sudah ada) + DomPDF (sudah ada).

---

## Technical Context

**Language/Version**: PHP 8.2 + Laravel 12  
**Primary Dependencies**: Chart.js v3.9.1 (CDN), Leaflet.js (CDN), barryvdh/laravel-dompdf v3.1, Bootstrap 5  
**Storage**: MySQL — tabel `surveillance_cases` (existing, read-only untuk dashboard)  
**Testing**: PHPUnit / Laravel `php artisan test`  
**Target Platform**: Web server (Laragon local dev, produksi Linux)  
**Project Type**: Web application (tambahan fitur pada existing Laravel app)  
**Performance Goals**: Semua 4 tab data dimuat dalam < 3 detik; navigasi antar tab instan  
**Constraints**: Data ~ratusan kasus/tahun; tidak butuh paginasi; no caching untuk MVP  
**Scale/Scope**: Single kota (Bontang); 1 peran (super-admin / Dinas Kesehatan)

---

## Constitution Check

Constitution belum dikonfigurasi (template kosong). Tidak ada gate yang dapat dievaluasi.  
*Re-check: N/A*

---

## Project Structure

### Documentation (this feature)

```text
specs/001-pd3i-dashboard/
├── plan.md              ← file ini
├── research.md          ← keputusan teknis & resolusi gap
├── data-model.md        ← entitas, kolom, response shapes
├── contracts/
│   └── api-endpoints.md ← 5 endpoint contracts (4 GET API + 1 POST PDF)
└── tasks.md             ← dibuat oleh /speckit.tasks
```

### Source Code (repository root)

```text
app/Http/Controllers/
└── Pd3iDashboardController.php      ← controller baru (index + 4 API + exportPdf)

app/Repositories/Admin/Epidemiologi/
└── SurveillanceRepository.php       ← tambah 4 metode getPd3i*

resources/views/admin/epidemiologi/
├── pd3i-dashboard.blade.php         ← halaman utama (4 tab, filter, chart.js, leaflet)
└── pdf/
    └── pd3i-dashboard.blade.php     ← template PDF (tabel HTML, no canvas)

routes/
└── web.php                          ← tambah 6 route di dalam prefix epidemiologi/
```

**Structure Decision**: Single-project web application. Controller baru `Pd3iDashboardController` dipilih (tidak ditambahkan ke `EpidemiologiController` yang sudah 39K+ baris) untuk menjaga kohesi dan keterbacaan. Repository `SurveillanceRepository` diextend dengan metode `getPd3i*` karena scope query berbeda dari metode existing.

---

## Complexity Tracking

Tidak ada pelanggaran constitution yang perlu dijustifikasi.

---

## Phase 0: Research ✅

Selesai — lihat [research.md](research.md)

**Keputusan kunci**:
1. Chart.js v3.9.1 & Leaflet.js & DomPDF sudah tersedia — tidak perlu instalasi baru
2. Controller terpisah `Pd3iDashboardController` (bukan extend `EpidemiologiController`)
3. 4 endpoint API paralel via `Promise.all` di JavaScript
4. PDF export: query ulang server-side → Blade template tabel HTML
5. Middleware: `module.role:superadmin` (bukan `surveilans_puskesmas/rs`)
6. Non Polio AFP Rate → `null` (tampilkan "–" di UI)
7. `komplikasi_dbd` → tidak ada; skip tanpa migration baru

---

## Phase 1: Design & Contracts ✅

### 1.1 Data Model

Lihat [data-model.md](data-model.md)

- Tidak ada migration baru — dashboard read-only dari tabel existing
- 4 response shapes terdefinisi (kinerja, demografi, tren, wilayah)
- Kelompok umur dihitung via `TIMESTAMPDIFF(MONTH, tanggal_lahir, tanggal_onset)` — 9 bucket

### 1.2 API Contracts

Lihat [contracts/api-endpoints.md](contracts/api-endpoints.md)

- 4 GET endpoint + 1 POST export-pdf
- Semua GET menerima query params: `tahun`, `jenis_kasus_id`, `wilker`
- Response JSON shapes terdefinisi dengan nilai default (0 untuk bucket kosong)

### 1.3 Rencana Implementasi Detail

#### Step 1 — Route (web.php)

Tambahkan di dalam blok `Route::prefix('admin')->middleware(['auth'])->group(...)` yang sudah ada, di bawah prefix `epidemiologi/`:

```php
Route::prefix('epidemiologi/pd3i-dashboard')
    ->middleware(['module.role:superadmin'])
    ->group(function () {
        Route::get('/', [Pd3iDashboardController::class, 'index'])
            ->name('admin.pd3i.dashboard');
        Route::get('/api/kinerja', [Pd3iDashboardController::class, 'kinerja'])
            ->name('admin.pd3i.apiKinerja');
        Route::get('/api/demografi', [Pd3iDashboardController::class, 'demografi'])
            ->name('admin.pd3i.apiDemografi');
        Route::get('/api/tren', [Pd3iDashboardController::class, 'tren'])
            ->name('admin.pd3i.apiTren');
        Route::get('/api/wilayah', [Pd3iDashboardController::class, 'wilayah'])
            ->name('admin.pd3i.apiWilayah');
        Route::post('/export-pdf', [Pd3iDashboardController::class, 'exportPdf'])
            ->name('admin.pd3i.exportPdf');
    });
```

#### Step 2 — Repository Methods (`SurveillanceRepository`)

Tambahkan 4 public methods dengan signature:

```php
public function getPd3iKinerja(int $tahun, ?int $jenisKasusId, ?string $wilker): array
public function getPd3iDemografi(int $tahun, ?int $jenisKasusId, ?string $wilker): array
public function getPd3iTren(int $tahun, ?int $jenisKasusId, ?string $wilker): array
public function getPd3iWilayah(int $tahun, ?int $jenisKasusId, ?string $wilker): array
```

Helper private:
```php
private function pd3iBaseQuery(int $tahun, ?int $jenisKasusId, ?string $wilker): Builder
```
→ applies `YEAR(tanggal_lapor) = $tahun`, `id_jenis_kasus`, `wilker_puskesmas` filter.

#### Step 3 — Controller (`Pd3iDashboardController`)

```php
class Pd3iDashboardController extends Controller
{
    public function __construct(SurveillanceRepository $repo) { ... }
    
    public function index(): View         // pass diseases + wilker list
    public function kinerja(Request $r): JsonResponse
    public function demografi(Request $r): JsonResponse
    public function tren(Request $r): JsonResponse
    public function wilayah(Request $r): JsonResponse
    public function exportPdf(Request $r): Response   // PDF download
}
```

Setiap API method:
1. Validasi `tahun` (int, min:2020, max:tahun+1, default:year)
2. Validasi `jenis_kasus_id` (nullable int)
3. Validasi `wilker` (nullable string)
4. Panggil repository method
5. Return `response()->json($data)`

#### Step 4 — View (`pd3i-dashboard.blade.php`)

Struktur HTML:
```
@extends('admin::layouts.app')

Header: Judul + tombol Export PDF
Filter bar: [Tahun ▾] [Penyakit ▾] [Wilker ▾] [Terapkan]

Tab Nav: Kinerja | Demografi | Tren | Wilayah

Tab Panes (semua dimuat, hidden via CSS):
  #tab-kinerja  → 4 panel scorecard (campak-rubella, AFP, difteri, pertusis)
  #tab-demografi → bar chart umur + pie vaksinasi + panel severity
  #tab-tren     → canvas epiweek + canvas bulanan + stacked per-faskes + per-kec + per-kel
  #tab-wilayah  → 3 tabel + Leaflet map

<script>
  // Promise.all fetch on load & on filter change
  // Skeleton show/hide per component
  // Chart.js instance management (destroy & recreate on update)
  // Leaflet map init & marker layer update
</script>
```

#### Step 5 — PDF Template (`pdf/pd3i-dashboard.blade.php`)

Layout A4 Landscape, inline CSS (DomPDF tidak support external CSS):
```
Section 1: Kinerja Surveilans — tabel scorecard per penyakit
Section 2: Demografi — tabel kelompok umur + tabel status vaksinasi + tabel komplikasi
Section 3: Tren — tabel bulanan per faskes (12 kolom bulan × n baris faskes)
Section 4: Wilayah — tabel per Puskesmas + per kecamatan + per kelurahan
Footer: Filter aktif + tanggal generate
```

---

## Urutan Implementasi (Dependencies)

```
Step 1: Route          → tidak ada dependency
Step 2: Repository     → dependency: SurveillanceCase model (sudah ada)
Step 3: Controller     → dependency: Step 1 (route names) + Step 2 (repository)
Step 4: View utama     → dependency: Step 3 (route names untuk API fetch URLs)
Step 5: PDF template   → dependency: Step 3 (exportPdf memanggil view ini)
```

**Urutan implementasi**: 2 → 1 → 3 → 4 → 5

---

## Kisi-kisi Query per Tab

### Tab Kinerja

```sql
-- Base (berlaku semua panel)
WHERE YEAR(tanggal_lapor) = :tahun
  [AND id_jenis_kasus = :jenisKasusId]
  [AND wilker_puskesmas = :wilker]

-- Campak-Rubella panel
AND jenis_kasus = (SELECT id FROM jenis_kasus_epidemiologi WHERE nama_penyakit LIKE '%Campak%Rubella%')

-- % Sampel
COUNT(CASE WHEN tanggal_pengambilan_spesimen IS NOT NULL THEN 1 END) / COUNT(*) * 100

-- Positivity rate
COUNT(CASE WHEN status_lab = 'positif' THEN 1 END) /
NULLIF(COUNT(CASE WHEN status_lab != 'belum_diperiksa' THEN 1 END), 0) * 100
```

### Tab Demografi — Kelompok Umur

```sql
SELECT
  CASE
    WHEN TIMESTAMPDIFF(MONTH, tanggal_lahir, tanggal_onset) < 6 THEN '< 6 bulan'
    WHEN TIMESTAMPDIFF(MONTH, tanggal_lahir, tanggal_onset) BETWEEN 6 AND 8 THEN '6–8 bulan'
    ...
    WHEN tanggal_lahir IS NULL THEN 'Tidak Diketahui'
  END as kelompok,
  status_kasus,
  COUNT(*) as total
FROM surveillance_cases
WHERE YEAR(tanggal_lapor) = :tahun ...
GROUP BY kelompok, status_kasus
```

### Tab Tren — Epiweek

```sql
SELECT
  YEARWEEK(tanggal_onset, 3) as epiweek,
  COUNT(*) as suspek,
  SUM(CASE WHEN status_kasus = 'confirmed' THEN 1 ELSE 0 END) as confirmed
FROM surveillance_cases
WHERE YEAR(tanggal_onset) BETWEEN :tahun-1 AND :tahun
  [AND id_jenis_kasus = :jenisKasusId]
  [AND wilker_puskesmas = :wilker]
GROUP BY epiweek
ORDER BY epiweek
```

### Tab Wilayah — Peta

```sql
SELECT id, latitude, longitude, nama_lengkap,
       jenis_kasus_epidemiologi.nama_penyakit, status_kasus
FROM surveillance_cases
JOIN jenis_kasus_epidemiologi ON id_jenis_kasus = jenis_kasus_epidemiologi.id
WHERE YEAR(tanggal_lapor) = :tahun
  AND latitude IS NOT NULL AND longitude IS NOT NULL
  [AND id_jenis_kasus = :jenisKasusId]
  [AND wilker_puskesmas = :wilker]
```
