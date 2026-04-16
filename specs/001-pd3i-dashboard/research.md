# Research: Dashboard Surveilans PD3I

**Branch**: `001-pd3i-dashboard` | **Date**: 2026-04-11

---

## 1. Infrastruktur yang Sudah Ada

### Decision: Gunakan EpidemiologiController + SurveillanceRepository yang sudah ada sebagai pondasi

**Rationale**: `EpidemiologiController` sudah punya pola `dashboard()` + `getDashboardData()` yang mirip kebutuhan dashboard PD3I. `SurveillanceRepository` sudah punya `getDashboardStats`, `getCasesTrend`, `getCasesByDisease`, `getCasesByStatus`, `getCasesByGeography`. Tambahkan metode baru ke repository daripada duplikasi logika query.

**Alternatives considered**:
- Controller terpisah `Pd3iDashboardController` → dipilih karena dashboard PD3I lebih besar dan berbeda signifikan dari dashboard existing
- Menggunakan `buildDashboardData()` langsung → ditolak karena scope filter berbeda (tahun + wilker vs faskes_type + id_faskes)

---

## 2. Chart Library

### Decision: Chart.js v3.9.1 via CDN (sudah ada)

**Rationale**: Sudah digunakan di `dashboard.blade.php` (baris 325: `cdn.jsdelivr.net/npm/chart.js@3.9.1`). Tidak perlu install tambahan. Semua chart types yang dibutuhkan (bar, line, pie, horizontal bar) tersedia di v3.

**Alternatives considered**:
- Apache ECharts → lebih powerful tapi overhead besar, tidak perlu
- ApexCharts → belum ada di project, perlu instalasi baru

---

## 3. Peta Interaktif

### Decision: Leaflet.js (sudah ada)

**Rationale**: Sudah digunakan di `map.blade.php` dan `form-map-picker.blade.php`. Kolom `latitude` dan `longitude` sudah ada di `SurveillanceCase` (cast `decimal:8`). Leaflet tile layer OpenStreetMap cukup untuk kota Bontang.

---

## 4. Export PDF

### Decision: barryvdh/laravel-dompdf (sudah ter-install, v3.1)

**Rationale**: Sudah di-require di `composer.json` dan sudah digunakan di `EpidemiologiController` (`use Barryvdh\DomPDF\Facade\Pdf`). Export PDF dashboard PD3I akan menggunakan pendekatan yang sama: Blade view khusus PDF → render via DomPDF.

**Pendekatan Export**: Karena grafik Chart.js dirender di browser (canvas), PDF harus menggunakan pendekatan salah satu:
- **Chart.js `toBase64Image()`** → kirim sebagai data URL ke server → embed di Blade PDF template
- Atau: render ulang data sebagai tabel HTML (lebih reliable untuk DomPDF)

**Decision**: Data disajikan sebagai tabel HTML di PDF (bukan screenshot canvas) — DomPDF tidak mendukung canvas/JavaScript. Controller menerima `POST` dengan data JSON yang sudah di-render, atau melakukan query ulang semua 4 tab.

---

## 5. Filter Global & Perilaku Parallel Fetch

### Decision: 4 endpoint API dipanggil paralel via `Promise.all` saat halaman load dan saat filter berubah

**Rationale**: Spec mengharuskan semua tab tersedia tanpa loading saat navigasi. Data ratusan kasus/tahun — query ringan, parallel fetch aman tanpa throttling.

**Pattern**:
```javascript
async function fetchAllTabs(params) {
    const [kinerja, demografi, tren, wilayah] = await Promise.all([
        fetch('/admin/epidemiologi/pd3i-dashboard/api/kinerja?' + params),
        fetch('/admin/epidemiologi/pd3i-dashboard/api/demografi?' + params),
        fetch('/admin/epidemiologi/pd3i-dashboard/api/tren?' + params),
        fetch('/admin/epidemiologi/pd3i-dashboard/api/wilayah?' + params),
    ]);
    // update semua chart/tabel sekaligus
}
```

Skeleton spinner per komponen ditampilkan sebelum fetch selesai.

---

## 6. Route & Middleware

### Decision: Route baru di dalam prefix `epidemiologi/` yang sudah ada, middleware `module.role:superadmin`

**Rationale**: Konsisten dengan pola routing existing. Dashboard PD3I hanya untuk `superadmin` (Dinas Kesehatan) — tidak termasuk `surveilans_puskesmas` atau `surveilans_rs`.

**Route group**:
```php
Route::prefix('epidemiologi/pd3i-dashboard')->middleware(['auth', 'module.role:superadmin'])->group(function () {
    Route::get('/', [Pd3iDashboardController::class, 'index'])->name('admin.pd3i.dashboard');
    Route::get('/api/kinerja', [Pd3iDashboardController::class, 'kinerja'])->name('admin.pd3i.apiKinerja');
    Route::get('/api/demografi', [Pd3iDashboardController::class, 'demografi'])->name('admin.pd3i.apiDemografi');
    Route::get('/api/tren', [Pd3iDashboardController::class, 'tren'])->name('admin.pd3i.apiTren');
    Route::get('/api/wilayah', [Pd3iDashboardController::class, 'wilayah'])->name('admin.pd3i.apiWilayah');
    Route::post('/export-pdf', [Pd3iDashboardController::class, 'exportPdf'])->name('admin.pd3i.exportPdf');
});
```

---

## 7. Repository — Metode Baru vs Existing

### Decision: Tambahkan metode baru ke `SurveillanceRepository` dengan prefix `getPd3i`

**Rationale**: Metode existing (`getDashboardStats`, dll.) menggunakan scope `faskes_type`/`id_faskes`. Dashboard PD3I menggunakan scope `tahun` + `id_jenis_kasus` + `wilker_puskesmas`. Parameter berbeda → metode terpisah, bukan overloading metode yang ada.

**Metode baru**:
- `getPd3iKinerja(int $tahun, ?int $jenisKasusId, ?string $wilker): array`
- `getPd3iDemografi(int $tahun, ?int $jenisKasusId, ?string $wilker): array`
- `getPd3iTren(int $tahun, ?int $jenisKasusId, ?string $wilker): array`
- `getPd3iWilayah(int $tahun, ?int $jenisKasusId, ?string $wilker): array`

---

## 8. Skala Data & Caching

### Decision: No caching untuk MVP, tambahkan jika diperlukan kemudian

**Rationale**: Volume ratusan kasus/tahun di kota kecil — query agregasi ringan, tidak butuh cache layer. Semua query menggunakan `YEAR(tanggal_lapor/tanggal_onset)` yang sudah terindeks via kolom tanggal. Target < 3 detik per tab terpenuhi tanpa cache.

---

## 9. PDF Export — Blade Template

### Decision: View terpisah `pd3i-dashboard-pdf.blade.php` dengan layout tabel HTML

**Rationale**: DomPDF tidak support JavaScript/Canvas. Data dikueri ulang server-side dengan filter yang sama. PDF terdiri dari 4 section (satu per tab) dalam satu dokumen landscape A4.

**Structure**:
```
resources/views/admin/epidemiologi/pdf/pd3i-dashboard.blade.php
```

---

## 10. Resolusi Gap dari Dokumen Referensi

| Gap | Resolusi |
|-----|----------|
| Non Polio AFP Rate | Tampilkan "–" + tooltip "Data populasi belum tersedia" |
| `komplikasi_dbd` tidak ada | Skip di dashboard; beri catatan kecil (tidak perlu migration sekarang) |
| Varian Polio/Difteri | Skip; tampilkan `hasil_lab` as-is |
