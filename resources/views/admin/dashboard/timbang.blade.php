@extends('admin::layouts.app')
@section('title') Dashboard Gizi & Timbang — SIRINDU @endsection
@section('title-content') Dashboard Gizi & Operasi Timbang @endsection
@section('item') Gizi & Timbang @endsection
@section('item-active') Dashboard @endsection

@section('content')
<div class="tb-page">
<style>
/* ================================================================
   Dashboard Gizi & Timbang — scoped to .tb-page
   ================================================================ */
@import url('https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700&display=swap');

.tb-page{
    --green:oklch(0.60 0.15 145); --green-d:oklch(0.48 0.14 145); --green-dk:oklch(0.38 0.13 145);
    --ink:oklch(0.24 0.02 145); --muted:oklch(0.50 0.015 145); --faint:oklch(0.62 0.012 145);
    --line:oklch(0.90 0.012 145); --line-soft:oklch(0.94 0.010 145);
    --bg:oklch(0.98 0.012 145); --card:#fff; --thead:oklch(0.96 0.016 145);
    --shadow:0 1px 3px oklch(0.30 0.03 145 / .06);
    --shadow-lg:0 10px 30px oklch(0.42 0.06 145 / .14);
    --danger:oklch(0.52 0.19 25); --danger-bg:oklch(0.95 0.045 25); --danger-ln:oklch(0.88 0.06 25);
    --warn:oklch(0.52 0.13 62);   --warn-bg:oklch(0.95 0.055 72);  --warn-ln:oklch(0.88 0.07 72);
    --info:oklch(0.52 0.10 235);  --info-bg:oklch(0.95 0.04 235);
    font-family:'Barlow',system-ui,sans-serif; color:var(--ink);
}
.tb-page *{ box-sizing:border-box; }
.tb-num{ font-family:'Barlow Condensed','Barlow',sans-serif; font-variant-numeric:tabular-nums; letter-spacing:.005em; }

/* Filter bar */
.tb-filter {
    display:flex; align-items:center; gap:.65rem; flex-wrap:wrap;
    background:var(--card); border-radius:14px; padding:.9rem 1.15rem;
    border:1px solid var(--line);
    box-shadow:var(--shadow); margin-bottom:1.5rem;
}
.tb-filter label { font-size:.7rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--muted); }
.tb-filter select {
    height:36px; padding:0 .7rem; border-radius:9px; font-size:.85rem;
    border:1px solid oklch(0.84 0.012 145); background:var(--bg);
    font-family:inherit; color:var(--ink); min-width:150px;
    transition:border-color .14s, box-shadow .14s;
}
.tb-filter select:focus { outline:none; border-color:var(--green); box-shadow:0 0 0 3px oklch(0.60 0.15 145 / .16); }
.tb-filter-btn {
    display:inline-flex; align-items:center; gap:.4rem;
    padding:0 1rem; height:36px; border-radius:9px;
    background:var(--green-d); color:#fff;
    font-family:inherit; font-size:.83rem; font-weight:700;
    border:1px solid transparent; cursor:pointer; text-decoration:none;
    transition:background .14s, border-color .14s, color .14s;
}
.tb-filter-btn:hover { background:var(--green-dk); color:#fff; }
.tb-filter-btn .material-symbols-outlined { font-size:16px; }
.tb-filter-btn--ghost { background:transparent; border-color:var(--line); color:var(--muted); }
.tb-filter-btn--ghost:hover { background:transparent; border-color:var(--faint); color:var(--ink); }

/* Section label */
.tb-section {
    font-size:.7rem; font-weight:800; letter-spacing:.11em;
    text-transform:uppercase; color:var(--green-d);
    margin:0 0 .9rem; display:flex; align-items:center; gap:.5rem;
}
.tb-section .material-symbols-outlined { font-size:16px; }
.tb-section::after { content:''; flex:1; height:1px; background:linear-gradient(90deg,var(--line),transparent); }
.tb-section small { font-weight:600; text-transform:none; letter-spacing:0; color:var(--faint); }

/* KPI cards */
.tb-kpi-grid {
    display:grid; grid-template-columns:repeat(4,1fr); gap:.9rem;
    margin-bottom:1.75rem;
}
.tb-kpi-grid--6 { grid-template-columns:repeat(3,1fr); }
@media(max-width:900px){ .tb-kpi-grid,.tb-kpi-grid--6{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:480px){ .tb-kpi-grid,.tb-kpi-grid--6{ grid-template-columns:1fr; } }

.tb-kpi {
    position:relative; background:var(--card); border-radius:14px; padding:1.1rem 1.15rem;
    border:1px solid var(--line);
    box-shadow:var(--shadow);
    display:flex; align-items:center; gap:.9rem;
    transition:transform .2s cubic-bezier(.22,1,.36,1), box-shadow .2s, border-color .2s;
}
.tb-kpi--click { cursor:pointer; }
.tb-kpi--click:hover { transform:translateY(-3px); box-shadow:var(--shadow-lg); border-color:oklch(0.84 0.03 145); }
.tb-kpi--click:focus-visible { outline:2px solid var(--green); outline-offset:2px; }
.tb-kpi__icon {
    width:44px; height:44px; border-radius:12px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
}
.tb-kpi__icon .material-symbols-outlined { font-size:22px; }
.tb-kpi__icon--green  { background:oklch(0.95 0.05 145); color:var(--green-d); }
.tb-kpi__icon--amber  { background:var(--warn-bg); color:var(--warn); }
.tb-kpi__icon--red    { background:var(--danger-bg); color:var(--danger); }
.tb-kpi__icon--blue   { background:var(--info-bg); color:var(--info); }
.tb-kpi__icon--violet { background:oklch(0.94 0.05 300); color:oklch(0.50 0.16 300); }
.tb-kpi__icon--teal   { background:oklch(0.94 0.05 190); color:oklch(0.48 0.10 190); }
.tb-kpi__icon--orange { background:oklch(0.94 0.065 55); color:oklch(0.52 0.15 52); }
.tb-kpi__val { font-size:2rem; font-weight:700; color:var(--ink); line-height:1.02; }
.tb-kpi__pct { font-size:1.05rem; font-weight:600; color:var(--muted); margin-left:.35rem; }
.tb-kpi__lbl { font-size:.82rem; font-weight:600; color:var(--muted); margin-top:.15rem; }
.tb-kpi__sub { font-size:.7rem; color:var(--faint); margin-top:.05rem; }
.tb-kpi__go {
    position:absolute; top:.7rem; right:.75rem; font-size:18px; color:var(--faint);
    opacity:0; transform:translateX(-4px); transition:opacity .18s, transform .18s;
}
.tb-kpi--click:hover .tb-kpi__go { opacity:1; transform:none; }

/* Entrance */
@keyframes tb-rise { from{ opacity:0; transform:translateY(10px); } to{ opacity:1; transform:none; } }
.tb-kpi-grid--6 .tb-kpi,
.tb-stunting-row .tb-highlight { opacity:0; animation:tb-rise .5s cubic-bezier(.22,1,.36,1) forwards; }
.tb-kpi-grid--6 .tb-kpi:nth-child(1){ animation-delay:.02s; }
.tb-kpi-grid--6 .tb-kpi:nth-child(2){ animation-delay:.06s; }
.tb-kpi-grid--6 .tb-kpi:nth-child(3){ animation-delay:.10s; }
.tb-kpi-grid--6 .tb-kpi:nth-child(4){ animation-delay:.14s; }
.tb-kpi-grid--6 .tb-kpi:nth-child(5){ animation-delay:.18s; }
.tb-kpi-grid--6 .tb-kpi:nth-child(6){ animation-delay:.22s; }
.tb-stunting-row .tb-highlight:nth-child(1){ animation-delay:.06s; }
.tb-stunting-row .tb-highlight:nth-child(2){ animation-delay:.12s; }
.tb-stunting-row .tb-highlight:nth-child(3){ animation-delay:.18s; }

/* Chart cards */
.tb-chart-grid { display:grid; gap:1rem; margin-bottom:1.5rem; }
.tb-chart-grid--3 { grid-template-columns:repeat(3,1fr); }
.tb-chart-grid--2 { grid-template-columns:repeat(2,1fr); }
.tb-chart-grid--1-2 { grid-template-columns:1fr 2fr; }
@media(max-width:960px){
    .tb-chart-grid--3,.tb-chart-grid--2,.tb-chart-grid--1-2 { grid-template-columns:1fr; }
}

.tb-card {
    background:var(--card); border-radius:14px; padding:1.25rem 1.35rem;
    border:1px solid var(--line);
    box-shadow:var(--shadow);
}
.tb-card__title {
    font-size:.95rem; font-weight:700; color:var(--ink);
    margin:0 0 .15rem; display:flex; align-items:center; gap:.5rem;
}
.tb-card__title .material-symbols-outlined { font-size:18px; color:var(--green-d); }
.tb-card__sub { font-size:.8rem; color:var(--faint); margin:0 0 1rem; }
.tb-card canvas { max-width:100%; }

/* Stunting highlight */
.tb-stunting-row { display:flex; gap:.8rem; margin-bottom:1.25rem; flex-wrap:wrap; }
.tb-highlight {
    flex:1; min-width:150px; border-radius:12px; padding:1rem 1.1rem;
    display:flex; align-items:center; gap:.75rem; border:1px solid transparent;
}
.tb-highlight--danger  { background:var(--danger-bg); border-color:var(--danger-ln); }
.tb-highlight--warning { background:var(--warn-bg);   border-color:var(--warn-ln); }
.tb-highlight--green   { background:oklch(0.95 0.05 145); border-color:oklch(0.86 0.06 145); }
.tb-highlight__pct { font-size:2.1rem; font-weight:700; line-height:.95; }
.tb-highlight--danger  .tb-highlight__pct { color:var(--danger); }
.tb-highlight--warning .tb-highlight__pct { color:oklch(0.48 0.13 62); }
.tb-highlight--green   .tb-highlight__pct { color:var(--green-dk); }
.tb-highlight__lbl { font-size:.82rem; font-weight:700; line-height:1.25; }
.tb-highlight--danger  .tb-highlight__lbl { color:oklch(0.42 0.13 25); }
.tb-highlight--warning .tb-highlight__lbl { color:oklch(0.42 0.10 62); }
.tb-highlight--green   .tb-highlight__lbl { color:var(--green-dk); }
.tb-highlight__lbl small { font-weight:500; opacity:.85; }

/* Coverage table */
.tb-cov-table { width:100%; border-collapse:collapse; font-size:.83rem; }
.tb-cov-table thead th {
    background:var(--thead); padding:.6rem .8rem; text-align:left;
    font-size:.66rem; font-weight:800; letter-spacing:.06em;
    text-transform:uppercase; color:var(--muted);
    white-space:nowrap;
}
.tb-cov-table tbody td { padding:.65rem .8rem; border-top:1px solid var(--line-soft); vertical-align:middle; font-variant-numeric:tabular-nums; }
.tb-cov-table tbody tr:hover { background:var(--bg); }
.tb-bar-wrap { width:96px; background:oklch(0.92 0.03 145); border-radius:5px; height:8px; display:inline-block; vertical-align:middle; overflow:hidden; }
.tb-bar { height:100%; border-radius:5px; background:var(--green-d); transition:width .5s cubic-bezier(.22,1,.36,1); }
.tb-bar--amber { background:oklch(0.62 0.12 62); }

/* ASI bulan chart bar */
.tb-asi-bar { display:flex; flex-direction:column; gap:.5rem; }
.tb-asi-row { display:flex; align-items:center; gap:.65rem; font-size:.8rem; }
.tb-asi-row .tb-asi-lbl { width:66px; color:var(--muted); font-weight:700; flex-shrink:0; }
.tb-asi-row .tb-asi-track { flex:1; background:oklch(0.92 0.03 145); border-radius:5px; height:12px; overflow:hidden; }
.tb-asi-row .tb-asi-fill { height:100%; border-radius:5px; background:var(--green-d); transition:width .5s cubic-bezier(.22,1,.36,1); }
.tb-asi-row .tb-asi-pct { width:42px; text-align:right; font-weight:700; color:var(--ink); font-variant-numeric:tabular-nums; }

/* Pitting edema */
.tb-pe-list { display:flex; flex-direction:column; gap:.5rem; }
.tb-pe-row { display:flex; align-items:center; gap:.6rem; font-size:.82rem; }
.tb-pe-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.tb-pe-row .tb-pe-lbl { flex:1; color:var(--muted); }
.tb-pe-row .tb-pe-cnt { font-weight:700; color:var(--ink); font-variant-numeric:tabular-nums; }

/* Loading overlay */
.tb-loading { text-align:center; padding:2rem; color:var(--faint); }
.tb-spin { animation:tb-spin .8s linear infinite; display:inline-block; font-size:26px; color:oklch(0.60 0.15 145 / .6); }
@keyframes tb-spin{ to{ transform:rotate(360deg); } }

/* Daftar modal */
.tb-modal-back {
    display:none; position:fixed; inset:0; z-index:1080;
    background:oklch(0.20 0.02 145 / .5); padding:4vh 16px; overflow:auto;
}
.tb-modal-back.open { display:block; animation:tb-fade .18s ease-out; }
.tb-modal {
    background:var(--card); border-radius:16px; max-width:980px; margin:0 auto;
    box-shadow:0 24px 60px oklch(0.15 0.02 145 / .34); overflow:hidden;
    animation:tb-pop .22s cubic-bezier(.22,1,.36,1);
}
.tb-modal__head {
    display:flex; align-items:center; gap:.75rem; padding:1.1rem 1.35rem;
    border-bottom:1px solid var(--line);
}
.tb-modal__head h3 { font-family:'Barlow Condensed','Barlow',sans-serif; font-size:1.25rem; font-weight:700; margin:0; color:var(--ink); }
.tb-modal__count { font-size:.8rem; color:var(--muted); }
.tb-modal__close { margin-left:auto; background:none; border:none; cursor:pointer; font-size:24px; line-height:1; color:var(--faint); border-radius:8px; width:32px; height:32px; transition:background .14s,color .14s; }
.tb-modal__close:hover { background:var(--bg); color:var(--ink); }
.tb-modal__body { padding:1rem 1.35rem 1.35rem; max-height:62vh; overflow:auto; }
.tb-modal__tools { display:flex; gap:.6rem; margin-bottom:.85rem; flex-wrap:wrap; align-items:center; }
.tb-modal__search { flex:1; min-width:180px; padding:.5rem .75rem; border:1px solid oklch(0.84 0.012 145); border-radius:9px; font-size:.85rem; font-family:inherit; background:var(--bg); color:var(--ink); }
.tb-modal__search:focus { outline:none; border-color:var(--green); box-shadow:0 0 0 3px oklch(0.60 0.15 145 / .16); }
.tb-modal__filter { height:38px; padding:0 .6rem; border:1px solid oklch(0.84 0.012 145); border-radius:9px; font-size:.82rem; font-family:inherit; background:var(--bg); color:var(--ink); min-width:130px; }
.tb-modal__filter:focus { outline:none; border-color:var(--green); box-shadow:0 0 0 3px oklch(0.60 0.15 145 / .16); }
.tb-dt { width:100%; border-collapse:collapse; font-size:.82rem; }
.tb-dt thead th { background:var(--thead); padding:.55rem .7rem; text-align:left; font-size:.66rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:var(--muted); white-space:nowrap; position:sticky; top:0; }
.tb-dt tbody td { padding:.55rem .7rem; border-top:1px solid var(--line-soft); }
.tb-dt tbody tr:hover { background:var(--bg); }
@keyframes tb-fade { from{ opacity:0; } }
@keyframes tb-pop { from{ opacity:0; transform:translateY(10px) scale(.99); } }

@media(prefers-reduced-motion:reduce){
    .tb-kpi-grid--6 .tb-kpi,
    .tb-stunting-row .tb-highlight,
    .tb-modal-back.open,
    .tb-modal { animation:none !important; opacity:1 !important; }
}
</style>

{{-- ── Filter bar ────────────────────────────────────────────── --}}
@php($isSuper = Auth::user()->isSuperAdmin())
<div class="tb-filter">
    <span class="material-symbols-outlined" style="font-size:20px;color:oklch(0.48 0.14 145);">filter_alt</span>
    <label for="f-tahun">Tahun</label>
    <select id="f-tahun">
        <option value="">Semua Tahun</option>
        @foreach($tahunList as $th)
        <option value="{{ $th }}">{{ $th }}</option>
        @endforeach
    </select>
    @if($isSuper)
    <label for="f-kec">Kecamatan</label>
    <select id="f-kec">
        <option value="">Semua Kecamatan</option>
        @foreach($kecamatanList as $kc)
        <option value="{{ $kc->id }}">{{ $kc->name }}</option>
        @endforeach
    </select>
    <label for="f-kel">Kelurahan</label>
    <select id="f-kel">
        <option value="">Semua Kelurahan</option>
        @foreach($kelurahanList as $kel)
        <option value="{{ $kel->id }}">{{ $kel->name }}</option>
        @endforeach
    </select>
    @else
    <input type="hidden" id="f-kec" value="">
    <input type="hidden" id="f-kel" value="{{ $kelurahanList->first()->id ?? '' }}">
    @endif
    <label for="f-posyandu">Posyandu</label>
    <select id="f-posyandu">
        <option value="">Semua Posyandu</option>
    </select>
    <label for="f-rt">RT</label>
    <select id="f-rt">
        <option value="">Semua RT</option>
    </select>
    <button class="tb-filter-btn" id="btn-apply">
        <span class="material-symbols-outlined">search</span>Terapkan
    </button>
    <button class="tb-filter-btn tb-filter-btn--ghost" id="btn-reset">
        <span class="material-symbols-outlined">restart_alt</span>Reset
    </button>
    <a class="tb-filter-btn tb-filter-btn--ghost" id="btn-peta" href="{{ route('admin.map') }}">
        <span class="material-symbols-outlined">map</span>Peta Sebaran
    </a>
</div>

{{-- ── KPI Cards (6 kartu clickable) ─────────────────────────── --}}
<p class="tb-section"><span class="material-symbols-outlined">monitoring</span>Ringkasan Operasi Timbang <small>klik kartu untuk melihat daftar nama</small></p>
<div class="tb-kpi-grid tb-kpi-grid--6" id="kpi-grid">
    <div class="tb-kpi tb-kpi--click" data-kategori="sasaran" role="button" tabindex="0" aria-label="Balita sasaran — buka daftar">
        <div class="tb-kpi__icon tb-kpi__icon--green"><span class="material-symbols-outlined">groups</span></div>
        <div><div class="tb-kpi__val tb-num" id="kpi-sasaran">—</div><div class="tb-kpi__lbl">Balita Sasaran</div><div class="tb-kpi__sub">total terdaftar (filter)</div></div>
        <span class="tb-kpi__go material-symbols-outlined" aria-hidden="true">arrow_forward</span>
    </div>
    <div class="tb-kpi tb-kpi--click" data-kategori="hadir" role="button" tabindex="0" aria-label="Hadir ditimbang — buka daftar">
        <div class="tb-kpi__icon tb-kpi__icon--blue"><span class="material-symbols-outlined">event_available</span></div>
        <div><div class="tb-kpi__val tb-num" id="kpi-hadir">—</div><div class="tb-kpi__lbl">Hadir (Ditimbang)</div><div class="tb-kpi__sub" id="kpi-coverage">coverage —</div></div>
        <span class="tb-kpi__go material-symbols-outlined" aria-hidden="true">arrow_forward</span>
    </div>
    <div class="tb-kpi tb-kpi--click" data-kategori="stunting" role="button" tabindex="0" aria-label="Stunting — buka daftar">
        <div class="tb-kpi__icon tb-kpi__icon--red"><span class="material-symbols-outlined">height</span></div>
        <div><div class="tb-kpi__val tb-num"><span id="kpi-stunting">—</span><span class="tb-kpi__pct tb-num" id="pct-stunting"></span></div><div class="tb-kpi__lbl">Stunting</div><div class="tb-kpi__sub">TB/U &lt; -2SD</div></div>
        <span class="tb-kpi__go material-symbols-outlined" aria-hidden="true">arrow_forward</span>
    </div>
    <div class="tb-kpi tb-kpi--click" data-kategori="gizi_kurang" role="button" tabindex="0" aria-label="Gizi kurang — buka daftar">
        <div class="tb-kpi__icon tb-kpi__icon--amber"><span class="material-symbols-outlined">monitor_weight</span></div>
        <div><div class="tb-kpi__val tb-num"><span id="kpi-gizi-kurang">—</span><span class="tb-kpi__pct tb-num" id="pct-gizi-kurang"></span></div><div class="tb-kpi__lbl">Wasting</div><div class="tb-kpi__sub">BB/TB -2,01 s.d. -3,00 SD</div></div>
        <span class="tb-kpi__go material-symbols-outlined" aria-hidden="true">arrow_forward</span>
    </div>
    <div class="tb-kpi tb-kpi--click" data-kategori="gizi_buruk" role="button" tabindex="0" aria-label="Gizi buruk — buka daftar">
        <div class="tb-kpi__icon tb-kpi__icon--red"><span class="material-symbols-outlined">emergency</span></div>
        <div><div class="tb-kpi__val tb-num"><span id="kpi-gizi-buruk">—</span><span class="tb-kpi__pct tb-num" id="pct-gizi-buruk"></span></div><div class="tb-kpi__lbl">Gizi Buruk</div><div class="tb-kpi__sub">BB/TB &lt; -3SD</div></div>
        <span class="tb-kpi__go material-symbols-outlined" aria-hidden="true">arrow_forward</span>
    </div>
    <div class="tb-kpi tb-kpi--click" data-kategori="bb_tidak_naik" role="button" tabindex="0" aria-label="BB tidak naik — buka daftar">
        <div class="tb-kpi__icon tb-kpi__icon--orange"><span class="material-symbols-outlined">trending_down</span></div>
        <div><div class="tb-kpi__val tb-num" id="kpi-bbtn">—</div><div class="tb-kpi__lbl">Underweight</div><div class="tb-kpi__sub">2 kunjungan / NTOB</div></div>
        <span class="tb-kpi__go material-symbols-outlined" aria-hidden="true">arrow_forward</span>
    </div>
</div>

{{-- ── STATUS GIZI ───────────────────────────────────────────── --}}
<p class="tb-section"><span class="material-symbols-outlined" style="font-size:16px;">emergency</span>Status Gizi Balita</p>

<div class="tb-chart-grid tb-chart-grid--3" style="margin-bottom:28px;">
    <div class="tb-card">
        <p class="tb-card__title"><span class="material-symbols-outlined">height</span>Status TB/U (Stunting)</p>
        <p class="tb-card__sub">Distribusi tinggi badan per usia — kunjungan terakhir</p>
        <canvas id="chart-tbu" height="220" role="img" aria-label="Distribusi status TB/U (stunting)"></canvas>
    </div>
    <div class="tb-card">
        <p class="tb-card__title"><span class="material-symbols-outlined">monitor_weight</span>Status BB/U (Gizi)</p>
        <p class="tb-card__sub">Distribusi berat badan per usia — kunjungan terakhir</p>
        <canvas id="chart-bbu" height="220" role="img" aria-label="Distribusi status BB/U (gizi)"></canvas>
    </div>
    <div class="tb-card">
        <p class="tb-card__title"><span class="material-symbols-outlined">calculate</span>Status BB/TB</p>
        <p class="tb-card__sub">Berat badan menurut tinggi badan</p>
        <canvas id="chart-bbtb" height="220" role="img" aria-label="Distribusi status BB/TB"></canvas>
    </div>
</div>

{{-- ── TREN ──────────────────────────────────────────────────── --}}
<p class="tb-section"><span class="material-symbols-outlined" style="font-size:16px;">trending_up</span>Tren Perkembangan</p>
<div class="tb-chart-grid tb-chart-grid--2" style="margin-bottom:28px;">
    <div class="tb-card">
        <p class="tb-card__title"><span class="material-symbols-outlined">event</span>Kunjungan Timbang <span id="kunjungan-range">(12 Bulan Terakhir)</span></p>
        <p class="tb-card__sub">Jumlah kunjungan per bulan</p>
        <canvas id="chart-kunjungan" height="200" role="img" aria-label="Tren jumlah kunjungan timbang per bulan"></canvas>
    </div>
    <div class="tb-card">
        <p class="tb-card__title"><span class="material-symbols-outlined">show_chart</span>Rata-rata BB & TB per Usia</p>
        <p class="tb-card__sub">Bulan usia 0–60 (balita)</p>
        <canvas id="chart-growth" height="200" role="img" aria-label="Rata-rata berat dan tinggi badan per bulan usia"></canvas>
    </div>
</div>

{{-- ── COVERAGE WILAYAH ─────────────────────────────────────── --}}
<p class="tb-section"><span class="material-symbols-outlined" style="font-size:16px;">location_on</span>Ketercapaian per Wilayah</p>
<div class="tb-chart-grid tb-chart-grid--2" style="margin-bottom:28px;">
    <div class="tb-card">
        <p class="tb-card__title"><span class="material-symbols-outlined">bar_chart</span>Coverage Timbang per Kelurahan</p>
        <p class="tb-card__sub">% anak pernah ditimbang dari total terdaftar</p>
        <canvas id="chart-cov-kel" height="220" role="img" aria-label="Coverage timbang dan vitamin A per kelurahan"></canvas>
    </div>
    <div class="tb-card" style="overflow-x:auto;">
        <p class="tb-card__title"><span class="material-symbols-outlined">table_chart</span>Detail Coverage per Kelurahan</p>
        <p class="tb-card__sub">Terurut dari coverage tertinggi</p>
        <div id="cov-table-wrap"><div class="tb-loading"><span class="material-symbols-outlined tb-spin">sync</span></div></div>
    </div>
</div>

{{-- ── PROGRAM TIMBANG ──────────────────────────────────────── --}}
<p class="tb-section"><span class="material-symbols-outlined" style="font-size:16px;">fact_check</span>Indikator Program Operasi Timbang</p>
<div class="tb-chart-grid tb-chart-grid--2" style="margin-bottom:24px;">
    <div class="tb-card">
        <p class="tb-card__title"><span class="material-symbols-outlined">baby_changing_station</span>Cakupan ASI Eksklusif (Bulan 0–6)</p>
        <p class="tb-card__sub">% anak yang mendapat ASI pada masing-masing bulan usia</p>
        <div class="tb-asi-bar" id="asi-bar">
            <div class="tb-loading"><span class="material-symbols-outlined tb-spin">sync</span></div>
        </div>
    </div>
    <div class="tb-card">
        <p class="tb-card__title"><span class="material-symbols-outlined">medical_information</span>Pitting Edema & Cara Ukur</p>
        <p class="tb-card__sub">Distribusi tingkat edema dan metode pengukuran</p>
        <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">
            <div style="flex:1;min-width:140px;">
                <div style="font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:oklch(0.44 0.010 145);margin-bottom:10px;">Pitting Edema</div>
                <div class="tb-pe-list" id="pe-list">
                    <div class="tb-loading"><span class="material-symbols-outlined tb-spin">sync</span></div>
                </div>
            </div>
            <div style="flex:1;min-width:140px;">
                <div style="font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:oklch(0.44 0.010 145);margin-bottom:10px;">Cara Ukur</div>
                <canvas id="chart-cara" height="130" role="img" aria-label="Distribusi cara ukur"></canvas>
            </div>
        </div>
    </div>
</div>
{{-- ── Daftar modal ─────────────────────────────────────────── --}}
<div class="tb-modal-back" id="daftar-modal">
    <div class="tb-modal">
        <div class="tb-modal__head">
            <span class="material-symbols-outlined" style="color:oklch(0.48 0.14 145);">list_alt</span>
            <div>
                <h3 id="daftar-title">Daftar</h3>
                <div class="tb-modal__count" id="daftar-count">—</div>
            </div>
            <button class="tb-modal__close" id="daftar-close" aria-label="Tutup">&times;</button>
        </div>
        <div class="tb-modal__body">
            <div class="tb-modal__tools">
                <input type="text" class="tb-modal__search" id="daftar-search" placeholder="Cari nama / NIK / wilayah…">
                <select class="tb-modal__filter" id="daftar-f-kec"><option value="">Semua Kecamatan</option></select>
                <select class="tb-modal__filter" id="daftar-f-kel"><option value="">Semua Kelurahan</option></select>
                <select class="tb-modal__filter" id="daftar-f-rt"><option value="">Semua RT</option></select>
                <a class="tb-filter-btn" id="daftar-export" style="background:oklch(0.48 0.14 145);text-decoration:none;" href="#">
                    <span class="material-symbols-outlined">download</span>Export Excel
                </a>
            </div>
            <div id="daftar-table-wrap">
                <div class="tb-loading"><span class="material-symbols-outlined tb-spin">sync</span></div>
            </div>
        </div>
    </div>
</div>
</div>{{-- /tb-page --}}
@endsection

@section('scripts')
@parent
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function(){
'use strict';

var API_RINGKASAN = '{{ route("admin.timbang.ringkasan") }}';
var API_GIZI      = '{{ route("admin.timbang.gizi") }}';
var API_TREN      = '{{ route("admin.timbang.tren") }}';
var API_COVERAGE  = '{{ route("admin.timbang.coverage") }}';
var API_PROGRAM   = '{{ route("admin.timbang.program") }}';
var API_DAFTAR    = '{{ route("admin.timbang.daftar") }}';
var API_DAFTAR_EXPORT = '{{ route("admin.timbang.daftar.export") }}';
var URL_KEL_BY_KEC = '{{ url("admin/get-kel-dasar-anak") }}';
var URL_RT_BY_KEL  = '{{ url("admin/get-rt-by-kel-anak") }}';
var URL_POS_BY_KEL = '{{ url("admin/get-posyandu-by-kel-anak") }}';
var IS_SUPER = {{ Auth::user()->isSuperAdmin() ? 'true' : 'false' }};

var charts = {};

// ── Filters ──────────────────────────────────────────────────
function val(id){ var el = document.getElementById(id); return el ? el.value : ''; }

function getParams(){
    var p = [];
    if(val('f-tahun'))    p.push('tahun='+val('f-tahun'));
    if(val('f-kec'))      p.push('kecamatan='+val('f-kec'));
    if(val('f-kel'))      p.push('kelurahan='+val('f-kel'));
    if(val('f-rt'))       p.push('rt='+val('f-rt'));
    if(val('f-posyandu')) p.push('posyandu='+val('f-posyandu'));
    return p.length ? '?'+p.join('&') : '';
}

function fillSelect(id, data, placeholder){
    var sel = document.getElementById(id);
    if(!sel || sel.tagName !== 'SELECT') return;
    sel.innerHTML = '<option value="">'+placeholder+'</option>';
    $.each(data, function(k, name){ sel.appendChild(new Option(name, k)); });
}

function loadRtPosyandu(kelId){
    if(!kelId){ fillSelect('f-rt', {}, 'Semua RT'); fillSelect('f-posyandu', {}, 'Semua Posyandu'); return; }
    $.getJSON(URL_RT_BY_KEL+'/'+kelId, function(d){ fillSelect('f-rt', d, 'Semua RT'); });
    $.getJSON(URL_POS_BY_KEL+'/'+kelId, function(d){ fillSelect('f-posyandu', d, 'Semua Posyandu'); });
}

// Cascade (superadmin punya dropdown; faskes terkunci ke kelurahannya)
if(IS_SUPER){
    $('#f-kec').on('change', function(){
        var kec = this.value;
        fillSelect('f-rt', {}, 'Semua RT');
        fillSelect('f-posyandu', {}, 'Semua Posyandu');
        if(!kec){ fillSelect('f-kel', {}, 'Semua Kelurahan'); return; }
        $.getJSON(URL_KEL_BY_KEC+'/'+kec, function(d){ fillSelect('f-kel', d, 'Semua Kelurahan'); });
    });
    $('#f-kel').on('change', function(){ loadRtPosyandu(this.value); });
}

document.getElementById('btn-apply').addEventListener('click', loadAll);
document.getElementById('btn-reset').addEventListener('click', function(){
    document.getElementById('f-tahun').value = '';
    if(IS_SUPER){ document.getElementById('f-kec').value = ''; document.getElementById('f-kel').value = ''; }
    fillSelect('f-rt', {}, 'Semua RT');
    fillSelect('f-posyandu', {}, 'Semua Posyandu');
    if(!IS_SUPER){ loadRtPosyandu(val('f-kel')); }
    loadAll();
});

// ── Utils ─────────────────────────────────────────────────────
function pct(v){ return (v !== null && v !== undefined) ? v+'%' : '—%'; }
function num(v){ return (v !== null && v !== undefined) ? Number(v).toLocaleString('id') : '—'; }

// ── Error handling ────────────────────────────────────────────
function fail(label){
    return function(xhr){
        console.error('[Timbang] gagal memuat '+label+':', xhr && xhr.status, xhr && xhr.responseText);
    };
}
function showError(id, label){
    var el = document.getElementById(id);
    if(el){
        el.innerHTML = '<div style="text-align:center;padding:20px;color:#dc2626;font-size:.8rem;">'
            +'<span class="material-symbols-outlined" style="vertical-align:middle;font-size:18px;">error</span> '
            +'Gagal memuat '+label+'</div>';
    }
}
function kpiFail(){
    ['kpi-sasaran','kpi-hadir','kpi-stunting','kpi-gizi-kurang','kpi-gizi-buruk','kpi-bbtn'].forEach(function(id){
        var el = document.getElementById(id);
        if(el) el.textContent = '!';
    });
}

function destroyChart(id){
    if(charts[id]){ charts[id].destroy(); delete charts[id]; }
}

function spinCard(canvasId){
    var c = document.getElementById(canvasId);
    if(!c) return;
    destroyChart(canvasId);
    var ctx = c.getContext('2d');
    ctx.clearRect(0,0,c.width,c.height);
}

// ── Chart.js defaults ─────────────────────────────────────────
var GREEN     = 'rgb(0,133,64)';          // Kemenkes green (border/line) — rgb: Chart.js can't parse oklch
var GREEN_A   = 'rgba(0,166,81,0.72)';    // Kemenkes green (fill)
var RED       = '#dc2626';
var AMBER     = '#d97706';
var BLUE      = '#0891b2';
var VIOLET    = '#7c3aed';
var TEAL      = '#0d9488';
var SLATE     = '#64748b';
var LIGHT     = '#e2e8f0';

// ── RINGKASAN ─────────────────────────────────────────────────
function loadRingkasan(){
    $.getJSON(API_RINGKASAN+getParams(), function(d){
        document.getElementById('kpi-sasaran').textContent = num(d.total_anak);
        document.getElementById('kpi-hadir').textContent   = num(d.total_ditimbang);
        document.getElementById('kpi-coverage').innerHTML  = 'coverage <strong style="color:oklch(0.38 0.13 145)">'+pct(d.coverage)+'</strong>';
        document.getElementById('kpi-bbtn').textContent    = num(d.bb_tidak_naik);
    }).fail(function(xhr){ kpiFail(); fail('ringkasan')(xhr); });
}

// ── GIZI ──────────────────────────────────────────────────────
function loadGizi(){
    $.getJSON(API_GIZI+getParams(), function(d){
        // Persen dilebur ke kartu KPI (jumlah · persen)
        function setPct(id, v){ var el=document.getElementById(id); if(el) el.textContent = (v!==null && v!==undefined) ? '· '+v+'%' : ''; }
        setPct('pct-stunting', d.stunting_pct);
        setPct('pct-gizi-kurang', d.total>0 ? Math.round(d.gizi_kurang/d.total*1000)/10 : null);
        setPct('pct-gizi-buruk',  d.total>0 ? Math.round(d.gizi_buruk/d.total*1000)/10 : null);

        // KPI cards gizi
        document.getElementById('kpi-stunting').textContent    = num(d.stunting);
        document.getElementById('kpi-gizi-kurang').textContent = num(d.gizi_kurang);
        document.getElementById('kpi-gizi-buruk').textContent  = num(d.gizi_buruk);

        // TB/U donut
        destroyChart('chart-tbu');
        var tbu = d.tb_u;
        charts['chart-tbu'] = new Chart(document.getElementById('chart-tbu'), {
            type:'doughnut',
            data:{
                labels:['Normal','Pendek','Sangat Pendek','Tinggi'],
                datasets:[{ data:[tbu.normal,tbu.pendek,tbu.sangat_pendek,tbu.tinggi],
                    backgroundColor:[GREEN_A,AMBER,RED,BLUE], borderWidth:2, borderColor:'#fff' }]
            },
            options:{ plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } }, cutout:'65%' }
        });

        // BB/U donut
        destroyChart('chart-bbu');
        var bbu = d.bb_u;
        charts['chart-bbu'] = new Chart(document.getElementById('chart-bbu'), {
            type:'doughnut',
            data:{
                labels:['Normal','Kurang','Sangat Kurang','Lebih'],
                datasets:[{ data:[bbu.normal,bbu.kurang,bbu.sangat_kurang,bbu.lebih],
                    backgroundColor:[GREEN_A,AMBER,RED,BLUE], borderWidth:2, borderColor:'#fff' }]
            },
            options:{ plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } }, cutout:'65%' }
        });

        // BB/TB donut
        destroyChart('chart-bbtb');
        var bbtb = d.bb_tb;
        charts['chart-bbtb'] = new Chart(document.getElementById('chart-bbtb'), {
            type:'doughnut',
            data:{
                labels:['Normal','Kurang','Buruk','Lebih','Obesitas'],
                datasets:[{ data:[bbtb.normal,bbtb.kurang,bbtb.buruk,bbtb.lebih,bbtb.obesitas],
                    backgroundColor:[GREEN_A,AMBER,RED,BLUE,VIOLET], borderWidth:2, borderColor:'#fff' }]
            },
            options:{ plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } }, cutout:'65%' }
        });
    }).fail(fail('status gizi'));
}

// ── TREN ──────────────────────────────────────────────────────
function loadTren(){
    var rangeEl = document.getElementById('kunjungan-range');
    if(rangeEl){
        var ty = document.getElementById('f-tahun').value;
        rangeEl.textContent = ty ? '(Tahun '+ty+')' : '(12 Bulan Terakhir)';
    }
    $.getJSON(API_TREN+getParams(), function(d){
        // Kunjungan bulanan
        destroyChart('chart-kunjungan');
        var kj = d.kunjungan;
        charts['chart-kunjungan'] = new Chart(document.getElementById('chart-kunjungan'), {
            type:'bar',
            data:{
                labels: kj.map(function(r){ return r.bulan; }),
                datasets:[{ label:'Kunjungan', data:kj.map(function(r){ return r.total; }),
                    backgroundColor:GREEN_A, borderColor:GREEN, borderWidth:1.5, borderRadius:5 }]
            },
            options:{ plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true } } }
        });

        // Growth trend
        destroyChart('chart-growth');
        var gr = d.growth;
        charts['chart-growth'] = new Chart(document.getElementById('chart-growth'), {
            type:'line',
            data:{
                labels: gr.map(function(r){ return 'Bln '+r.bln; }),
                datasets:[
                    { label:'Rata-rata BB (kg)', data:gr.map(function(r){ return r.avg_bb; }),
                      borderColor:BLUE, backgroundColor:'transparent', tension:.3, pointRadius:2 },
                    { label:'Rata-rata TB (cm)', data:gr.map(function(r){ return r.avg_tb; }),
                      borderColor:GREEN, backgroundColor:'transparent', tension:.3, pointRadius:2 }
                ]
            },
            options:{ plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } },
                scales:{ y:{ beginAtZero:false } } }
        });
    }).fail(fail('tren'));
}

// ── COVERAGE ──────────────────────────────────────────────────
function loadCoverage(){
    $.getJSON(API_COVERAGE+getParams(), function(rows){
        var top = rows.slice(0,15);

        // Bar chart
        destroyChart('chart-cov-kel');
        charts['chart-cov-kel'] = new Chart(document.getElementById('chart-cov-kel'), {
            type:'bar',
            data:{
                labels: top.map(function(r){ return r.nama; }),
                datasets:[
                    { label:'Coverage Timbang (%)', data:top.map(function(r){ return r.coverage_pct; }),
                      backgroundColor:GREEN_A, borderColor:GREEN, borderWidth:1.5, borderRadius:4 },
                    { label:'Coverage Vit A (%)', data:top.map(function(r){ return r.vit_a_pct; }),
                      backgroundColor:'rgba(217,119,6,0.65)', borderColor:AMBER, borderWidth:1.5, borderRadius:4 }
                ]
            },
            options:{
                indexAxis:'y',
                plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } },
                scales:{ x:{ beginAtZero:true, max:100 } }
            }
        });

        // Table
        var html = '<table class="tb-cov-table"><thead><tr><th>Kelurahan</th><th>Total</th><th>Ditimbang</th><th>Coverage</th><th>Vit A</th></tr></thead><tbody>';
        rows.forEach(function(r){
            var cw = Math.round(r.coverage_pct);
            var vw = Math.round(r.vit_a_pct);
            html += '<tr>'
                +'<td>'+escHtml(r.nama)+'</td>'
                +'<td>'+num(r.total_anak)+'</td>'
                +'<td>'+num(r.ditimbang)+'</td>'
                +'<td style="white-space:nowrap;">'
                  +'<div class="tb-bar-wrap"><div class="tb-bar" style="width:'+cw+'%"></div></div>'
                  +' <strong>'+r.coverage_pct+'%</strong>'
                +'</td>'
                +'<td style="white-space:nowrap;">'
                  +'<div class="tb-bar-wrap"><div class="tb-bar tb-bar--amber" style="width:'+vw+'%"></div></div>'
                  +' '+r.vit_a_pct+'%'
                +'</td>'
                +'</tr>';
        });
        html += '</tbody></table>';
        document.getElementById('cov-table-wrap').innerHTML = rows.length ? html
            : '<div style="text-align:center;padding:24px;color:var(--faint);">Belum ada data</div>';
    }).fail(function(xhr){ showError('cov-table-wrap', 'coverage'); fail('coverage')(xhr); });
}

// ── PROGRAM ───────────────────────────────────────────────────
var PE_LABELS = {0:'Tidak Ada',1:'Ringan (1)',2:'Sedang (2)',3:'Berat (3)'};
var PE_COLORS = {0:'#94a3b8',1:'#f59e0b',2:'#f97316',3:'#dc2626'};

function loadProgram(){
    $.getJSON(API_PROGRAM+getParams(), function(d){
        // ASI per bulan
        var asiHtml = '';
        (d.asi_per_bulan||[]).forEach(function(row){
            var p = row.pct !== null ? row.pct : 0;
            asiHtml += '<div class="tb-asi-row">'
                +'<div class="tb-asi-lbl">Bulan '+row.bulan+'</div>'
                +'<div class="tb-asi-track"><div class="tb-asi-fill" style="width:'+p+'%"></div></div>'
                +'<div class="tb-asi-pct">'+(row.pct !== null ? row.pct+'%' : '—')+'</div>'
                +'</div>';
        });
        document.getElementById('asi-bar').innerHTML = asiHtml || '<div style="color:var(--faint);font-size:.8rem;">Belum ada data ASI</div>';

        // Pitting edema
        var peHtml = '';
        var peTotal = 0;
        (d.pitting_edema||[]).forEach(function(r){ peTotal += r.total; });
        (d.pitting_edema||[]).forEach(function(r){
            var lbl = PE_LABELS[r.level] || ('Level '+r.level);
            var col = PE_COLORS[r.level] || '#64748b';
            var pct = peTotal>0 ? Math.round(r.total/peTotal*100) : 0;
            peHtml += '<div class="tb-pe-row">'
                +'<div class="tb-pe-dot" style="background:'+col+'"></div>'
                +'<div class="tb-pe-lbl">'+lbl+' ('+pct+'%)</div>'
                +'<div class="tb-pe-cnt">'+num(r.total)+'</div>'
                +'</div>';
        });
        document.getElementById('pe-list').innerHTML = peHtml || '<div style="color:var(--faint);font-size:.8rem;">—</div>';

        // Cara ukur donut
        destroyChart('chart-cara');
        var cu = d.cara_ukur||[];
        if(cu.length){
            charts['chart-cara'] = new Chart(document.getElementById('chart-cara'),{
                type:'doughnut',
                data:{
                    labels:cu.map(function(r){ return r.cara||'—'; }),
                    datasets:[{ data:cu.map(function(r){ return r.total; }),
                        backgroundColor:[GREEN_A,BLUE,AMBER,SLATE], borderWidth:2, borderColor:'#fff' }]
                },
                options:{ plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } }, cutout:'60%' }
            });
        }
    }).fail(function(xhr){ showError('asi-bar','data ASI'); showError('pe-list','data edema'); fail('program')(xhr); });
}

function escHtml(s){
    return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── DAFTAR MODAL (actionable list) ────────────────────────────
var KAT_LABEL = {
    sasaran:'Balita Sasaran', hadir:'Hadir (Ditimbang)', stunting:'Stunting',
    gizi_kurang:'Gizi Kurang', gizi_buruk:'Gizi Buruk', bb_tidak_naik:'BB Tidak Naik'
};
var daftarRows = [];

function uniqSorted(vals){
    return Array.from(new Set(vals)).filter(function(v){ return v && v !== '-'; })
        .sort(function(a,b){ return String(a).localeCompare(String(b),'id',{numeric:true}); });
}
function fillDaftarSelect(id, values, placeholder){
    var sel = document.getElementById(id);
    if(!sel) return;
    sel.innerHTML = '<option value="">'+placeholder+'</option>';
    values.forEach(function(v){ sel.appendChild(new Option(v, v)); });
}
function refreshDaftarRt(){
    var kec = val('daftar-f-kec'), kel = val('daftar-f-kel');
    var rts = daftarRows.filter(function(r){ return (!kec||r.kecamatan===kec) && (!kel||r.kelurahan===kel); })
        .map(function(r){ return r.rt; });
    fillDaftarSelect('daftar-f-rt', uniqSorted(rts), 'Semua RT');
}
function refreshDaftarKel(){
    var kec = val('daftar-f-kec');
    var kels = daftarRows.filter(function(r){ return !kec || r.kecamatan===kec; })
        .map(function(r){ return r.kelurahan; });
    fillDaftarSelect('daftar-f-kel', uniqSorted(kels), 'Semua Kelurahan');
    refreshDaftarRt();
}
function initDaftarFilters(){
    fillDaftarSelect('daftar-f-kec', uniqSorted(daftarRows.map(function(r){ return r.kecamatan; })), 'Semua Kecamatan');
    refreshDaftarKel();
}
function applyDaftarFilter(){ renderDaftar(val('daftar-search')); }

function renderDaftar(filterText){
    var rows = daftarRows;
    var fk = val('daftar-f-kec'), fl = val('daftar-f-kel'), fr = val('daftar-f-rt');
    if(fk) rows = rows.filter(function(r){ return r.kecamatan === fk; });
    if(fl) rows = rows.filter(function(r){ return r.kelurahan === fl; });
    if(fr) rows = rows.filter(function(r){ return r.rt === fr; });
    if(filterText){
        var q = filterText.toLowerCase();
        rows = rows.filter(function(r){
            return (String(r.nama||'')+' '+String(r.nik||'')+' '+String(r.kelurahan||'')
                +' '+String(r.rt||'')+' '+String(r.posyandu||'')+' '+String(r.alamat||'')).toLowerCase().indexOf(q) >= 0;
        });
    }
    if(!rows.length){
        document.getElementById('daftar-table-wrap').innerHTML =
            '<div style="text-align:center;padding:28px;color:var(--faint);">Tidak ada data</div>';
        return;
    }
    var h = '<table class="tb-dt"><thead><tr>'
        +'<th>No</th><th>Nama</th><th>NIK</th><th>Kelurahan</th><th>RT</th><th>Posyandu</th>'
        +'<th>Alamat Domisili</th><th>Indikator</th><th>Tgl Kunjungan</th></tr></thead><tbody>';
    rows.forEach(function(r, i){
        h += '<tr><td>'+(i+1)+'</td>'
            +'<td>'+escHtml(r.nama)+'</td>'
            +'<td>'+escHtml(r.nik)+'</td>'
            +'<td>'+escHtml(r.kelurahan)+'</td>'
            +'<td>'+escHtml(r.rt)+'</td>'
            +'<td>'+escHtml(r.posyandu)+'</td>'
            +'<td>'+escHtml(r.alamat)+'</td>'
            +'<td>'+escHtml(r.indikator)+'</td>'
            +'<td>'+escHtml(r.tgl_kunjungan)+'</td></tr>';
    });
    h += '</tbody></table>';
    document.getElementById('daftar-table-wrap').innerHTML = h;
}

function openDaftar(kategori){
    var params = getParams();
    var sep = params ? '&' : '?';
    document.getElementById('daftar-title').textContent = KAT_LABEL[kategori] || 'Daftar';
    document.getElementById('daftar-count').textContent = 'Memuat…';
    document.getElementById('daftar-search').value = '';
    document.getElementById('daftar-table-wrap').innerHTML =
        '<div class="tb-loading"><span class="material-symbols-outlined tb-spin">sync</span></div>';
    document.getElementById('daftar-export').href = API_DAFTAR_EXPORT+params+sep+'kategori='+kategori;
    document.getElementById('daftar-modal').classList.add('open');

    $.getJSON(API_DAFTAR+params+sep+'kategori='+kategori, function(d){
        daftarRows = d.rows || [];
        document.getElementById('daftar-count').textContent = daftarRows.length+' anak';
        initDaftarFilters();
        renderDaftar('');
    }).fail(function(){
        document.getElementById('daftar-count').textContent = '';
        document.getElementById('daftar-table-wrap').innerHTML =
            '<div style="text-align:center;padding:24px;color:#dc2626;">Gagal memuat daftar</div>';
    });
}

function closeDaftar(){ document.getElementById('daftar-modal').classList.remove('open'); }

document.querySelectorAll('.tb-kpi--click').forEach(function(card){
    card.addEventListener('click', function(){ openDaftar(card.getAttribute('data-kategori')); });
    card.addEventListener('keydown', function(e){
        if(e.key === 'Enter' || e.key === ' '){ e.preventDefault(); openDaftar(card.getAttribute('data-kategori')); }
    });
});
document.getElementById('daftar-close').addEventListener('click', closeDaftar);
document.getElementById('daftar-modal').addEventListener('click', function(e){ if(e.target === this) closeDaftar(); });
document.getElementById('daftar-search').addEventListener('input', function(){ renderDaftar(this.value); });
document.getElementById('daftar-f-kec').addEventListener('change', function(){ refreshDaftarKel(); applyDaftarFilter(); });
document.getElementById('daftar-f-kel').addEventListener('change', function(){ refreshDaftarRt(); applyDaftarFilter(); });
document.getElementById('daftar-f-rt').addEventListener('change', applyDaftarFilter);

function loadAll(){
    loadRingkasan();
    loadGizi();
    loadTren();
    loadCoverage();
    loadProgram();
}

// Faskes terkunci ke kelurahannya → muat opsi RT/Posyandu-nya saat awal.
if(!IS_SUPER){ loadRtPosyandu(val('f-kel')); }

loadAll();

})();
</script>
@endsection
