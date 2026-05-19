@extends('admin::layouts.app')

@section('title') Import Data @endsection
@section('title-content') Import Data CSV @endsection
@section('item') Import @endsection
@section('item-active') Data @endsection

@section('content')
<div class="imp-page">

<style>
/* ================================================================
   Import Data — scoped to .imp-page
   ================================================================ */
.imp-page { font-family: 'Barlow', sans-serif; color: oklch(0.18 0.012 145); }

/* -- Flash alerts -- */
.imp-alert {
    display: flex; align-items: center; gap: 10px;
    padding: 13px 18px; border-radius: 10px;
    font-size: 0.875rem; font-weight: 600;
    margin-bottom: 20px;
}
.imp-alert .material-symbols-outlined { font-size: 20px; flex-shrink: 0; }
.imp-alert-info    { background: oklch(0.94 0.08 220 / 0.25); color: oklch(0.35 0.12 225); }
.imp-alert-success { background: oklch(0.93 0.07 145 / 0.3);  color: oklch(0.35 0.13 145); }
.imp-alert-error   { background: oklch(0.96 0.06 25 / 0.3);   color: oklch(0.40 0.13 25);  }

/* -- Page header -- */
.imp-header { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
.imp-header__icon {
    width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
    background: oklch(0.93 0.04 145 / 0.5);
    display: flex; align-items: center; justify-content: center;
    color: oklch(0.48 0.14 145);
}
.imp-header__icon .material-symbols-outlined { font-size: 28px; }
.imp-header__meta h1 {
    font-size: 1.65rem; font-weight: 800; letter-spacing: -0.02em;
    color: oklch(0.18 0.012 145); margin: 0 0 5px; line-height: 1.2;
}
.imp-header__meta p { margin: 0; font-size: 0.875rem; color: oklch(0.44 0.010 145); }

/* -- Tab bar -- */
.imp-tabs {
    display: flex; gap: 5px; flex-wrap: wrap;
    background: oklch(0.95 0.018 145); border-radius: 12px;
    padding: 5px; margin-bottom: 24px; width: fit-content; max-width: 100%;
}
.imp-tab-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 0 20px; height: 40px; border-radius: 9px;
    font-family: 'Barlow', sans-serif; font-size: 0.875rem; font-weight: 700;
    color: oklch(0.44 0.010 145); background: transparent; border: none;
    cursor: pointer; white-space: nowrap;
    transition: background 0.16s ease-out, color 0.16s ease-out, box-shadow 0.16s;
}
.imp-tab-btn .material-symbols-outlined { font-size: 18px; }
.imp-tab-btn:hover { color: oklch(0.48 0.14 145); background: oklch(0.91 0.05 145 / 0.6); }
.imp-tab-btn.is-active {
    background: oklch(0.48 0.14 145); color: #fff;
    box-shadow: 0 2px 8px oklch(0.48 0.14 145 / 0.35);
}

/* -- Tab panels -- */
.imp-panel { display: none; }
.imp-panel.is-active { display: block; }

/* -- Panel grid (guide + upload) -- */
.imp-panel-grid {
    display: grid; grid-template-columns: 320px 1fr;
    gap: 20px; align-items: start; margin-bottom: 32px;
}
@media (max-width: 768px) { .imp-panel-grid { grid-template-columns: 1fr; } }

/* -- Guide column -- */
.imp-guide {
    background: oklch(0.96 0.016 145); border-radius: 12px;
    border: 1px solid oklch(0.90 0.025 145); padding: 22px;
}
.imp-guide__label {
    font-size: 0.6875rem; font-weight: 800; letter-spacing: 0.10em;
    text-transform: uppercase; color: oklch(0.48 0.14 145); margin: 0 0 10px;
}
.imp-guide p { font-size: 0.875rem; color: oklch(0.34 0.012 145); line-height: 1.6; margin: 0 0 14px; }
.imp-guide__rule {
    background: oklch(0.91 0.05 145 / 0.5); border-radius: 8px;
    padding: 11px 13px; font-size: 0.8rem; color: oklch(0.34 0.012 145);
    line-height: 1.55; margin-bottom: 16px;
}
.imp-guide__rule > strong:first-child {
    color: oklch(0.36 0.13 145); display: block; margin-bottom: 4px;
    font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.06em;
}
.imp-template-link {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 0 14px; height: 36px; border-radius: 8px;
    font-size: 0.8125rem; font-weight: 700;
    color: oklch(0.48 0.14 145);
    background: oklch(0.91 0.05 145 / 0.5);
    border: 1px solid oklch(0.78 0.08 145 / 0.45);
    text-decoration: none !important;
    transition: background 0.14s, border-color 0.14s;
}
.imp-template-link:hover {
    background: oklch(0.87 0.07 145 / 0.6); border-color: oklch(0.68 0.10 145 / 0.5);
    color: oklch(0.38 0.13 145);
}
.imp-template-link .material-symbols-outlined { font-size: 16px; }

/* -- Upload column -- */
.imp-upload-col {
    background: #fff; border-radius: 12px; padding: 24px;
    border: 1px solid oklch(0.87 0.012 145);
    box-shadow: 0 1px 4px oklch(0 0 0 / 0.04);
}

/* -- Upload zone -- */
.imp-upload-zone {
    display: block; position: relative; cursor: pointer;
    border: 1.5px dashed oklch(0.60 0.15 145 / 0.40);
    border-radius: 10px; padding: 38px 24px; text-align: center;
    background: oklch(0.97 0.012 145);
    transition: background 0.18s ease-out, border-color 0.18s ease-out;
    margin-bottom: 12px;
}
.imp-upload-zone:hover, .imp-upload-zone:focus-within {
    background: oklch(0.93 0.04 145 / 0.5);
    border-color: oklch(0.48 0.14 145);
}
.imp-upload-zone.has-file {
    background: oklch(0.93 0.04 145 / 0.5);
    border-style: solid; border-color: oklch(0.48 0.14 145);
}
.imp-upload-zone__icon {
    display: block; font-size: 42px;
    color: oklch(0.60 0.12 145 / 0.55); margin-bottom: 12px;
    transition: color 0.18s, transform 0.20s cubic-bezier(0.22, 1, 0.36, 1);
}
.imp-upload-zone:hover .imp-upload-zone__icon,
.imp-upload-zone.has-file .imp-upload-zone__icon {
    color: oklch(0.48 0.14 145); transform: translateY(-3px);
}
.imp-upload-zone__label {
    display: block; font-size: 0.9375rem; font-weight: 700;
    color: oklch(0.28 0.012 145); margin-bottom: 4px;
}
.imp-upload-zone__hint { font-size: 0.8rem; color: oklch(0.48 0.008 145); }
.imp-upload-zone input[type="file"] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer;
    width: 100%; height: 100%;
}

/* -- File name display -- */
.imp-fname {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 12px; border-radius: 8px; min-height: 40px;
    background: oklch(0.96 0.016 145); font-size: 0.8125rem;
    color: oklch(0.36 0.010 145); margin-bottom: 14px;
}
.imp-fname .material-symbols-outlined { font-size: 16px; color: oklch(0.48 0.14 145); flex-shrink: 0; }
.imp-fname span { word-break: break-all; }

/* -- Upload button -- */
.imp-btn-upload {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; height: 46px; border-radius: 10px;
    background: oklch(0.48 0.14 145); color: #fff;
    font-family: 'Barlow', sans-serif; font-size: 0.9375rem; font-weight: 700;
    border: none; cursor: pointer;
    box-shadow: 0 3px 10px oklch(0.48 0.14 145 / 0.28);
    transition: background 0.16s, box-shadow 0.16s, transform 0.12s;
}
.imp-btn-upload:hover {
    background: oklch(0.40 0.13 145);
    box-shadow: 0 5px 16px oklch(0.48 0.14 145 / 0.38);
    transform: translateY(-1px);
}
.imp-btn-upload:active { transform: translateY(0); }
.imp-btn-upload:disabled {
    background: oklch(0.70 0.05 145 / 0.7); cursor: not-allowed;
    transform: none; box-shadow: none;
}
.imp-btn-upload .material-symbols-outlined { font-size: 20px; }

/* -- History section -- */
.imp-history-header {
    display: flex; flex-wrap: wrap; align-items: center;
    justify-content: space-between; gap: 12px; margin-bottom: 14px;
}
.imp-history-title {
    font-size: 1rem; font-weight: 800; color: oklch(0.18 0.012 145);
    margin: 0; display: flex; align-items: center; gap: 8px;
}
.imp-history-title .material-symbols-outlined { font-size: 20px; color: oklch(0.48 0.14 145); }
.imp-history-actions { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }

.imp-filter-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 0 12px; height: 32px; border-radius: 7px;
    font-family: 'Barlow', sans-serif; font-size: 0.8rem; font-weight: 700;
    color: oklch(0.44 0.010 145); background: oklch(0.95 0.016 145);
    border: 1px solid oklch(0.87 0.012 145); cursor: pointer;
    transition: all 0.14s;
}
.imp-filter-btn:hover, .imp-filter-btn.is-active {
    background: oklch(0.91 0.05 145 / 0.6);
    color: oklch(0.48 0.14 145); border-color: oklch(0.78 0.08 145 / 0.5);
}

.imp-refresh-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 7px;
    background: transparent; border: 1px solid oklch(0.87 0.012 145);
    color: oklch(0.44 0.010 145); cursor: pointer; transition: all 0.14s;
}
.imp-refresh-btn:hover { background: oklch(0.95 0.016 145); color: oklch(0.48 0.14 145); }
.imp-refresh-btn.spinning .material-symbols-outlined { animation: imp-spin 0.8s linear infinite; }
.imp-refresh-btn .material-symbols-outlined { font-size: 18px; }
@keyframes imp-spin { to { transform: rotate(360deg); } }

/* -- Log table -- */
.imp-log-wrap {
    background: #fff; border-radius: 12px;
    border: 1px solid oklch(0.87 0.012 145); overflow: hidden;
    box-shadow: 0 1px 4px oklch(0 0 0 / 0.04);
}
.imp-log-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
.imp-log-table thead th {
    background: oklch(0.95 0.016 145); padding: 11px 16px;
    font-size: 0.6875rem; font-weight: 800; letter-spacing: 0.07em;
    text-transform: uppercase; color: oklch(0.44 0.010 145); white-space: nowrap;
}
.imp-log-table tbody tr { border-top: 1px solid oklch(0.93 0.012 145); transition: background 0.10s; }
.imp-log-table tbody tr:hover { background: oklch(0.97 0.012 145); }
.imp-log-table tbody td { padding: 13px 16px; vertical-align: middle; color: oklch(0.24 0.012 145); }
.imp-log-table .td-file { font-weight: 600; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.imp-log-table .td-muted { color: oklch(0.48 0.008 145); font-size: 0.8rem; }

/* -- Status badges -- */
.imp-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 9px; border-radius: 100px;
    font-size: 0.6875rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; white-space: nowrap;
}
.imp-badge .material-symbols-outlined { font-size: 11px; }
.imp-badge-pending    { background: oklch(0.95 0.09 80 / 0.4); color: oklch(0.52 0.13 75); }
.imp-badge-processing { background: oklch(0.93 0.09 220 / 0.4); color: oklch(0.44 0.15 228); }
.imp-badge-done       { background: oklch(0.92 0.07 145 / 0.5); color: oklch(0.36 0.12 145); }
.imp-badge-failed     { background: oklch(0.95 0.07 25 / 0.4); color: oklch(0.46 0.16 25); }

/* -- Type chips -- */
.imp-chip {
    display: inline-block; padding: 2px 8px; border-radius: 6px;
    font-size: 0.6875rem; font-weight: 700; letter-spacing: 0.04em;
}
.imp-chip-anak       { background: oklch(0.92 0.07 228 / 0.35); color: oklch(0.42 0.15 228); }
.imp-chip-pengukuran { background: oklch(0.92 0.08 295 / 0.35); color: oklch(0.42 0.14 295); }
.imp-chip-imunisasi  { background: oklch(0.92 0.07 145 / 0.4);  color: oklch(0.36 0.12 145); }

/* -- Action buttons -- */
.imp-act {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 9px; border-radius: 6px; font-family: 'Barlow', sans-serif;
    font-size: 0.75rem; font-weight: 700; border: 1px solid; cursor: pointer;
    background: transparent; text-decoration: none !important; white-space: nowrap;
    transition: background 0.12s;
}
.imp-act .material-symbols-outlined { font-size: 13px; }
.imp-act-warn   { color: #b45309; border-color: #d97706; }
.imp-act-warn:hover { background: #fffbeb; color: #92400e; }
.imp-act-sec    { color: oklch(0.44 0.010 145); border-color: oklch(0.78 0.010 145); }
.imp-act-sec:hover { background: oklch(0.95 0.016 145); }
.imp-act-del    { color: oklch(0.46 0.16 25); border-color: oklch(0.72 0.12 25 / 0.7); }
.imp-act-del:hover { background: oklch(0.97 0.04 25 / 0.5); }

/* -- Empty state -- */
.imp-empty {
    text-align: center; padding: 48px 24px; color: oklch(0.52 0.008 145);
}
.imp-empty .material-symbols-outlined { font-size: 52px; color: oklch(0.82 0.04 145); display: block; margin-bottom: 10px; }
.imp-empty p { font-size: 0.9375rem; margin: 0; }

/* -- Inline error panel -- */
.imp-err-row td { padding: 0 !important; }
.imp-err-panel {
    background: oklch(0.97 0.02 25); border-top: 1px solid oklch(0.90 0.06 25 / 0.4);
    padding: 12px 20px 14px; font-size: 0.8125rem;
}
.imp-err-panel strong { color: oklch(0.40 0.13 25); display: block; margin-bottom: 6px; }
.imp-err-panel ul { margin: 0; padding-left: 18px; color: oklch(0.36 0.10 25); }
.imp-err-panel ul li { margin-bottom: 3px; }

@media (prefers-reduced-motion: reduce) {
    .imp-tab-btn, .imp-upload-zone, .imp-btn-upload,
    .imp-template-link, .imp-filter-btn, .imp-refresh-btn, .imp-act { transition-duration: 0.01ms !important; }
    .imp-upload-zone .imp-upload-zone__icon { transition-duration: 0.01ms !important; }
    .imp-refresh-btn.spinning .material-symbols-outlined { animation: none !important; }
}
</style>

{{-- ── Flash messages ──────────────────────────────────────────── --}}
@if(session('import_queued'))
<div class="imp-alert imp-alert-info" role="status">
    <span class="material-symbols-outlined">sync</span>
    {{ session('import_queued') }}
</div>
@endif
@if(session('error'))
<div class="imp-alert imp-alert-error" role="alert">
    <span class="material-symbols-outlined">error</span>
    {{ session('error') }}
</div>
@endif

{{-- ── Page header ─────────────────────────────────────────────── --}}
<div class="imp-header">
    <div class="imp-header__icon">
        <span class="material-symbols-outlined">upload_file</span>
    </div>
    <div class="imp-header__meta">
        <h1>Import Data CSV</h1>
        <p>Unggah file CSV terstandarisasi untuk mengimpor data Anak, Pengukuran Berkala, atau Imunisasi secara massal.</p>
    </div>
</div>

{{-- ── Tab bar ──────────────────────────────────────────────────── --}}
<div class="imp-tabs" role="tablist" aria-label="Pilih tipe import">
    <button class="imp-tab-btn is-active" data-tab="anak"
        role="tab" aria-selected="true" aria-controls="panel-anak" id="tab-anak">
        <span class="material-symbols-outlined">child_care</span>
        Data Anak
    </button>
    <button class="imp-tab-btn" data-tab="pengukuran"
        role="tab" aria-selected="false" aria-controls="panel-pengukuran" id="tab-pengukuran">
        <span class="material-symbols-outlined">straighten</span>
        Pengukuran Berkala
    </button>
    <button class="imp-tab-btn" data-tab="imunisasi"
        role="tab" aria-selected="false" aria-controls="panel-imunisasi" id="tab-imunisasi">
        <span class="material-symbols-outlined">vaccines</span>
        Imunisasi
    </button>
</div>

{{-- =====================================================================
     PANEL: Data Anak
     ===================================================================== --}}
<div class="imp-panel is-active" id="panel-anak" role="tabpanel" aria-labelledby="tab-anak">
    <div class="imp-panel-grid">
        <div class="imp-guide">
            <p class="imp-guide__label">Data yang diimpor</p>
            <p>Identitas dasar anak: NIK, nama, tanggal lahir, jenis kelamin, alamat, data orang tua, informasi kelahiran, dan faskes (posyandu &amp; puskesmas).</p>
            <div class="imp-guide__rule">
                <strong>Logika Upsert</strong>
                Jika NIK sudah ada → data anak di-<em>update</em>.<br>
                Jika NIK kosong/baru → data anak baru dibuat.
            </div>
            <a href="{{ route('admin.importCsv.template', 'anak') }}" class="imp-template-link" download>
                <span class="material-symbols-outlined">download</span>
                Unduh Template CSV
            </a>
        </div>
        <div class="imp-upload-col">
            <form method="POST" action="{{ route('admin.importCsv.anak') }}"
                  enctype="multipart/form-data" class="imp-form" data-type="anak">
                @csrf
                <label class="imp-upload-zone" for="file-anak" id="zone-anak">
                    <span class="material-symbols-outlined imp-upload-zone__icon">cloud_upload</span>
                    <span class="imp-upload-zone__label">Klik atau seret file CSV ke sini</span>
                    <span class="imp-upload-zone__hint">Format .csv &mdash; maksimal 10 MB</span>
                    <input type="file" id="file-anak" name="file_anak" accept=".csv,text/csv" required>
                </label>
                <div class="imp-fname" id="fname-anak">
                    <span class="material-symbols-outlined">description</span>
                    <span>Belum ada file dipilih</span>
                </div>
                <button type="submit" class="imp-btn-upload">
                    <span class="material-symbols-outlined">upload</span>
                    Upload &amp; Import
                </button>
            </form>
        </div>
    </div>
</div>

{{-- =====================================================================
     PANEL: Pengukuran Berkala
     ===================================================================== --}}
<div class="imp-panel" id="panel-pengukuran" role="tabpanel" aria-labelledby="tab-pengukuran">
    <div class="imp-panel-grid">
        <div class="imp-guide">
            <p class="imp-guide__label">Data yang diimpor</p>
            <p>Pengukuran berkala: berat badan, tinggi badan, lingkar kepala, LiLA, NTOB, DDTKA, tanggal kunjungan, serta nilai z-score status gizi.</p>
            <div class="imp-guide__rule">
                <strong>Pencocokan Anak (2 dari 3)</strong>
                Isi minimal 2 dari 3 kolom: <strong>NIK</strong>, <strong>nama</strong>, atau <strong>tanggal lahir</strong>. Sistem mencari anak yang cocok sebelum menyimpan data.
            </div>
            <a href="{{ route('admin.importCsv.template', 'pengukuran') }}" class="imp-template-link" download>
                <span class="material-symbols-outlined">download</span>
                Unduh Template CSV
            </a>
        </div>
        <div class="imp-upload-col">
            <form method="POST" action="{{ route('admin.importCsv.pengukuran') }}"
                  enctype="multipart/form-data" class="imp-form" data-type="pengukuran">
                @csrf
                <label class="imp-upload-zone" for="file-pengukuran" id="zone-pengukuran">
                    <span class="material-symbols-outlined imp-upload-zone__icon">cloud_upload</span>
                    <span class="imp-upload-zone__label">Klik atau seret file CSV ke sini</span>
                    <span class="imp-upload-zone__hint">Format .csv &mdash; maksimal 10 MB</span>
                    <input type="file" id="file-pengukuran" name="file_pengukuran" accept=".csv,text/csv" required>
                </label>
                <div class="imp-fname" id="fname-pengukuran">
                    <span class="material-symbols-outlined">description</span>
                    <span>Belum ada file dipilih</span>
                </div>
                <button type="submit" class="imp-btn-upload">
                    <span class="material-symbols-outlined">upload</span>
                    Upload &amp; Import
                </button>
            </form>
        </div>
    </div>
</div>

{{-- =====================================================================
     PANEL: Imunisasi
     ===================================================================== --}}
<div class="imp-panel" id="panel-imunisasi" role="tabpanel" aria-labelledby="tab-imunisasi">
    <div class="imp-panel-grid">
        <div class="imp-guide">
            <p class="imp-guide__label">Data yang diimpor</p>
            <p>Riwayat imunisasi: kode vaksin, dosis, tanggal pemberian, batch, lokasi, status (sudah/belum/terlambat), dan reaksi KIPI.</p>
            <div class="imp-guide__rule">
                <strong>Pencocokan Anak (2 dari 3)</strong>
                Isi minimal 2 dari 3 kolom: <strong>NIK</strong>, <strong>nama</strong>, atau <strong>tanggal lahir</strong>. Sistem mencari anak yang cocok sebelum menyimpan data.
            </div>
            <a href="{{ route('admin.importCsv.template', 'imunisasi') }}" class="imp-template-link" download>
                <span class="material-symbols-outlined">download</span>
                Unduh Template CSV
            </a>
        </div>
        <div class="imp-upload-col">
            <form method="POST" action="{{ route('admin.importCsv.imunisasi') }}"
                  enctype="multipart/form-data" class="imp-form" data-type="imunisasi">
                @csrf
                <label class="imp-upload-zone" for="file-imunisasi" id="zone-imunisasi">
                    <span class="material-symbols-outlined imp-upload-zone__icon">cloud_upload</span>
                    <span class="imp-upload-zone__label">Klik atau seret file CSV ke sini</span>
                    <span class="imp-upload-zone__hint">Format .csv &mdash; maksimal 10 MB</span>
                    <input type="file" id="file-imunisasi" name="file_imunisasi" accept=".csv,text/csv" required>
                </label>
                <div class="imp-fname" id="fname-imunisasi">
                    <span class="material-symbols-outlined">description</span>
                    <span>Belum ada file dipilih</span>
                </div>
                <button type="submit" class="imp-btn-upload">
                    <span class="material-symbols-outlined">upload</span>
                    Upload &amp; Import
                </button>
            </form>
        </div>
    </div>
</div>

{{-- =====================================================================
     Riwayat Import
     ===================================================================== --}}
<div>
    <div class="imp-history-header">
        <h2 class="imp-history-title">
            <span class="material-symbols-outlined">history</span>
            Riwayat Import
        </h2>
        <div class="imp-history-actions">
            <button class="imp-filter-btn is-active" data-filter="all">Semua</button>
            <button class="imp-filter-btn" data-filter="anak">Anak</button>
            <button class="imp-filter-btn" data-filter="pengukuran">Pengukuran</button>
            <button class="imp-filter-btn" data-filter="imunisasi">Imunisasi</button>
            <button class="imp-refresh-btn" id="imp-btn-refresh" title="Muat ulang riwayat">
                <span class="material-symbols-outlined">refresh</span>
            </button>
        </div>
    </div>

    <div class="imp-log-wrap">
        <div id="imp-log-container">
            @if($logs->isEmpty())
            <div class="imp-empty">
                <span class="material-symbols-outlined">inbox</span>
                <p>Belum ada riwayat import</p>
            </div>
            @else
            <table class="imp-log-table">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Tipe</th>
                        <th>Status</th>
                        <th>Berhasil</th>
                        <th>Gagal</th>
                        <th>Selesai</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="imp-log-tbody">
                    @foreach($logs as $log)
                    <tr data-log-id="{{ $log->id }}" data-type="{{ $log->type }}" data-status="{{ $log->status }}">
                        <td class="td-file" title="{{ $log->filename }}">{{ $log->filename }}</td>
                        <td>
                            <span class="imp-chip imp-chip-{{ $log->type }}">
                                {{ ['anak'=>'Anak','pengukuran'=>'Pengukuran','imunisasi'=>'Imunisasi'][$log->type] ?? $log->type }}
                            </span>
                        </td>
                        <td>
                            <span class="imp-badge imp-badge-{{ $log->status }}">
                                <span class="material-symbols-outlined">
                                    {{ ['pending'=>'schedule','processing'=>'sync','done'=>'check_circle','failed'=>'error'][$log->status] ?? 'info' }}
                                </span>
                                {{ $log->statusLabel() }}
                            </span>
                        </td>
                        <td>{{ $log->success_count ?? '—' }}</td>
                        <td>
                            @if(!is_null($log->failure_count) && $log->failure_count > 0)
                                <span style="color:oklch(0.46 0.16 25);font-weight:700;">{{ $log->failure_count }}</span>
                            @elseif($log->failure_count === 0)
                                0
                            @else
                                —
                            @endif
                        </td>
                        <td class="td-muted">{{ $log->completed_at ? $log->completed_at->format('d/m/Y H:i') : '—' }}</td>
                        <td style="white-space:nowrap;">
                            @if($log->failures && count($log->failures) > 0 && ($log->isDone() || $log->isFailed()))
                            <button class="imp-act imp-act-warn imp-btn-error"
                                    data-failures="{{ json_encode($log->failures) }}"
                                    aria-label="Lihat detail error">
                                <span class="material-symbols-outlined">warning</span>Error
                            </button>
                            @endif
                            @if($log->isDone() || $log->isFailed())
                            <form method="POST" action="{{ route('admin.importCsv.reimport', $log->id) }}"
                                  style="display:inline;"
                                  onsubmit="return confirm('Ulangi import file ini?')">
                                @csrf
                                <button type="submit" class="imp-act imp-act-sec" title="Ulangi import">
                                    <span class="material-symbols-outlined">replay</span>Ulang
                                </button>
                            </form>
                            <button class="imp-act imp-act-del imp-btn-del"
                                    data-id="{{ $log->id }}"
                                    data-url="{{ route('admin.importCsv.destroyLog', $log->id) }}"
                                    title="Hapus log ini"
                                    aria-label="Hapus log import">
                                <span class="material-symbols-outlined">delete</span>
                            </button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>

</div>{{-- /imp-page --}}
@endsection

@section('scripts')
@parent
<script>
(function () {
    'use strict';

    var activeFilter = 'all';
    var pollTimer    = null;
    var CSRF         = '{{ csrf_token() }}';
    var STATUS_URL   = '{{ route("admin.importCsv.status") }}';
    var REIMPORT_BASE = '{{ url("admin/import-csv/reimport") }}';
    var DEL_BASE      = '{{ url("admin/import-csv/log") }}';

    /* ── Tab switching ──────────────────────────────────────────── */
    document.querySelectorAll('.imp-tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = btn.dataset.tab;
            document.querySelectorAll('.imp-tab-btn').forEach(function (b) {
                b.classList.toggle('is-active', b.dataset.tab === tab);
                b.setAttribute('aria-selected', b.dataset.tab === tab ? 'true' : 'false');
            });
            document.querySelectorAll('.imp-panel').forEach(function (p) {
                p.classList.toggle('is-active', p.id === 'panel-' + tab);
            });
        });
    });

    /* ── File input: show filename + mark zone ──────────────────── */
    document.querySelectorAll('.imp-upload-zone input[type="file"]').forEach(function (input) {
        input.addEventListener('change', function () {
            var type  = input.closest('.imp-form').dataset.type;
            var zone  = document.getElementById('zone-' + type);
            var disp  = document.getElementById('fname-' + type);
            var fname = input.files.length ? input.files[0].name : null;
            zone.classList.toggle('has-file', !!fname);
            disp.querySelector('span:last-child').textContent = fname || 'Belum ada file dipilih';
        });
    });

    /* ── Drag-over visual feedback ──────────────────────────────── */
    document.querySelectorAll('.imp-upload-zone').forEach(function (zone) {
        zone.addEventListener('dragover',  function (e) { e.preventDefault(); zone.classList.add('has-file'); });
        zone.addEventListener('dragleave', function () {
            var inp = zone.querySelector('input[type="file"]');
            if (!inp || !inp.files.length) zone.classList.remove('has-file');
        });
        zone.addEventListener('drop', function (e) { e.preventDefault(); });
    });

    /* ── Prevent double-submit ──────────────────────────────────── */
    document.querySelectorAll('.imp-form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('.imp-btn-upload');
            if (!btn) return;
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined" style="animation:imp-spin .8s linear infinite">sync</span>&nbsp;Mengunggah…';
        });
    });

    /* ── Filter buttons ─────────────────────────────────────────── */
    document.querySelectorAll('.imp-filter-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            activeFilter = btn.dataset.filter;
            document.querySelectorAll('.imp-filter-btn').forEach(function (b) {
                b.classList.toggle('is-active', b.dataset.filter === activeFilter);
            });
            applyFilter();
        });
    });

    function applyFilter() {
        var tbody = document.getElementById('imp-log-tbody');
        if (!tbody) return;
        tbody.querySelectorAll('tr[data-type]').forEach(function (row) {
            row.style.display = (activeFilter === 'all' || row.dataset.type === activeFilter) ? '' : 'none';
            /* hide associated error row if any */
            var next = row.nextElementSibling;
            if (next && next.classList.contains('imp-err-row')) {
                next.style.display = row.style.display;
            }
        });
    }

    /* ── Refresh button ─────────────────────────────────────────── */
    document.getElementById('imp-btn-refresh').addEventListener('click', function () {
        fetchLogs(true);
    });

    /* ── Inline error rows ──────────────────────────────────────── */
    $(document).on('click', '.imp-btn-error', function () {
        var $btn  = $(this);
        var $row  = $btn.closest('tr');
        var $next = $row.next('.imp-err-row');

        if ($next.length) { $next.toggle(); return; }

        var failures = $btn.data('failures');
        if (!failures || !failures.length) return;

        var items = failures.map(function (f) {
            return '<li>' + escHtml(String(f)) + '</li>';
        }).join('');
        var cols  = $row.find('td').length;

        $row.after(
            '<tr class="imp-err-row"><td colspan="' + cols + '">' +
            '<div class="imp-err-panel"><strong>Baris bermasalah:</strong><ul>' + items + '</ul></div>' +
            '</td></tr>'
        );
    });

    /* ── Delete log ─────────────────────────────────────────────── */
    $(document).on('click', '.imp-btn-del', function () {
        var $btn = $(this);
        var url  = $btn.data('url');
        if (!confirm('Hapus log import ini?')) return;

        $.ajax({
            url    : url,
            type   : 'DELETE',
            data   : { _token: CSRF },
            success: function () {
                var $row  = $btn.closest('tr');
                var $err  = $row.next('.imp-err-row');
                if ($err.length) $err.remove();
                $row.fadeOut(180, function () { $row.remove(); checkEmpty(); });
            },
            error  : function () { alert('Gagal menghapus. Coba lagi.'); }
        });
    });

    function checkEmpty() {
        var tbody = document.getElementById('imp-log-tbody');
        if (tbody && tbody.querySelectorAll('tr[data-type]').length === 0) {
            document.getElementById('imp-log-container').innerHTML =
                '<div class="imp-empty"><span class="material-symbols-outlined">inbox</span>' +
                '<p>Belum ada riwayat import</p></div>';
        }
    }

    /* ── AJAX polling ───────────────────────────────────────────── */
    function hasPending() {
        return !!document.querySelector('#imp-log-tbody tr[data-status="pending"], #imp-log-tbody tr[data-status="processing"]');
    }

    function fetchLogs(manual) {
        var $btn = $('#imp-btn-refresh');
        $btn.addClass('spinning');

        $.getJSON(STATUS_URL)
            .done(function (logs) { renderLogs(logs); applyFilter(); })
            .fail(function ()     { /* silently ignore */ })
            .always(function ()   { $btn.removeClass('spinning'); schedulePoll(); });
    }

    function schedulePoll() {
        clearTimeout(pollTimer);
        if (hasPending()) {
            pollTimer = setTimeout(function () { fetchLogs(false); }, 5000);
        }
    }

    /* ── Render helpers ─────────────────────────────────────────── */
    var typeLabel  = { anak: 'Anak', pengukuran: 'Pengukuran', imunisasi: 'Imunisasi' };
    var typeChip   = { anak: 'imp-chip-anak', pengukuran: 'imp-chip-pengukuran', imunisasi: 'imp-chip-imunisasi' };
    var stLabel    = { pending: 'Menunggu', processing: 'Diproses', done: 'Selesai', failed: 'Gagal' };
    var stBadge    = { pending: 'imp-badge-pending', processing: 'imp-badge-processing', done: 'imp-badge-done', failed: 'imp-badge-failed' };
    var stIcon     = { pending: 'schedule', processing: 'sync', done: 'check_circle', failed: 'error' };

    function renderLogs(logs) {
        var container = document.getElementById('imp-log-container');
        if (!container) return;

        if (!logs || !logs.length) {
            container.innerHTML = '<div class="imp-empty"><span class="material-symbols-outlined">inbox</span><p>Belum ada riwayat import</p></div>';
            return;
        }

        var rows = logs.map(function (log) {
            var acts = '';
            if (log.failures && log.failures.length && (log.status === 'done' || log.status === 'failed')) {
                var safe = JSON.stringify(log.failures).replace(/"/g, '&quot;');
                acts += '<button class="imp-act imp-act-warn imp-btn-error" data-failures="' + safe + '">' +
                    '<span class="material-symbols-outlined">warning</span>Error</button> ';
            }
            if (log.status === 'done' || log.status === 'failed') {
                acts += '<form method="POST" action="' + REIMPORT_BASE + '/' + log.id + '" style="display:inline;" onsubmit="return confirm(\'Ulangi import file ini?\')">' +
                    '<input type="hidden" name="_token" value="' + CSRF + '">' +
                    '<button type="submit" class="imp-act imp-act-sec"><span class="material-symbols-outlined">replay</span>Ulang</button></form> ';
                acts += '<button class="imp-act imp-act-del imp-btn-del" data-id="' + log.id + '" data-url="' + DEL_BASE + '/' + log.id + '">' +
                    '<span class="material-symbols-outlined">delete</span></button>';
            }
            var failCell = (log.failure_count !== null && log.failure_count !== undefined)
                ? (log.failure_count > 0
                    ? '<span style="color:oklch(0.46 0.16 25);font-weight:700;">' + log.failure_count + '</span>'
                    : '0')
                : '—';

            return '<tr data-log-id="' + log.id + '" data-type="' + log.type + '" data-status="' + log.status + '">' +
                '<td class="td-file" title="' + escHtml(log.filename) + '">' + escHtml(log.filename) + '</td>' +
                '<td><span class="imp-chip ' + (typeChip[log.type] || '') + '">' + (typeLabel[log.type] || log.type) + '</span></td>' +
                '<td><span class="imp-badge ' + (stBadge[log.status] || '') + '"><span class="material-symbols-outlined">' + (stIcon[log.status] || 'info') + '</span>' + (stLabel[log.status] || log.status) + '</span></td>' +
                '<td>' + (log.success_count !== null && log.success_count !== undefined ? log.success_count : '—') + '</td>' +
                '<td>' + failCell + '</td>' +
                '<td class="td-muted">' + (log.completed_at ? fmtDate(log.completed_at) : '—') + '</td>' +
                '<td style="white-space:nowrap;">' + acts + '</td>' +
                '</tr>';
        }).join('');

        container.innerHTML =
            '<table class="imp-log-table"><thead><tr>' +
            '<th>File</th><th>Tipe</th><th>Status</th><th>Berhasil</th><th>Gagal</th><th>Selesai</th><th></th>' +
            '</tr></thead><tbody id="imp-log-tbody">' + rows + '</tbody></table>';
    }

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmtDate(dt) {
        try {
            var d = new Date(dt);
            return ('0'+d.getDate()).slice(-2)+'/'+('0'+(d.getMonth()+1)).slice(-2)+'/'+d.getFullYear()+
                ' '+('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2);
        } catch (e) { return dt; }
    }

    /* ── Kick off polling if needed ─────────────────────────────── */
    schedulePoll();

})();
</script>
@endsection
