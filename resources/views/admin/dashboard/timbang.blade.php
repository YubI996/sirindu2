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
.tb-page { font-family:'Barlow',sans-serif; color:oklch(0.18 0.012 145); }

/* Filter bar */
.tb-filter {
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    background:#fff; border-radius:12px; padding:14px 20px;
    border:1px solid oklch(0.87 0.012 145);
    box-shadow:0 1px 4px oklch(0 0 0 / 0.04); margin-bottom:22px;
}
.tb-filter label { font-size:.8125rem; font-weight:700; color:oklch(0.36 0.010 145); }
.tb-filter select {
    padding:6px 12px; border-radius:8px; font-size:.8125rem;
    border:1px solid oklch(0.82 0.012 145); background:#fafafa;
    font-family:'Barlow',sans-serif; min-width:160px;
}
.tb-filter-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:0 16px; height:36px; border-radius:8px;
    background:oklch(0.48 0.14 145); color:#fff;
    font-family:'Barlow',sans-serif; font-size:.8125rem; font-weight:700;
    border:none; cursor:pointer;
    transition:background .14s;
}
.tb-filter-btn:hover { background:oklch(0.40 0.13 145); }
.tb-filter-btn .material-symbols-outlined { font-size:16px; }

/* Section label */
.tb-section {
    font-size:.6875rem; font-weight:800; letter-spacing:.10em;
    text-transform:uppercase; color:oklch(0.48 0.14 145);
    margin:0 0 12px; display:flex; align-items:center; gap:8px;
}
.tb-section::after { content:''; flex:1; height:1px; background:oklch(0.90 0.025 145); }

/* KPI cards */
.tb-kpi-grid {
    display:grid; grid-template-columns:repeat(4,1fr); gap:14px;
    margin-bottom:28px;
}
@media(max-width:900px){ .tb-kpi-grid{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:480px){ .tb-kpi-grid{ grid-template-columns:1fr; } }

.tb-kpi {
    background:#fff; border-radius:12px; padding:20px;
    border:1px solid oklch(0.87 0.012 145);
    box-shadow:0 1px 4px oklch(0 0 0 / 0.04);
    display:flex; align-items:center; gap:16px;
    transition:transform .18s, box-shadow .18s;
}
.tb-kpi:hover { transform:translateY(-3px); box-shadow:0 6px 18px oklch(0.48 0.14 145 / 0.12); }
.tb-kpi__icon {
    width:52px; height:52px; border-radius:12px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:24px;
}
.tb-kpi__icon--green  { background:oklch(0.48 0.14 145); color:#fff; }
.tb-kpi__icon--amber  { background:#d97706; color:#fff; }
.tb-kpi__icon--red    { background:#dc2626; color:#fff; }
.tb-kpi__icon--blue   { background:#0891b2; color:#fff; }
.tb-kpi__icon--violet { background:#7c3aed; color:#fff; }
.tb-kpi__icon--teal   { background:#0d9488; color:#fff; }
.tb-kpi__icon--orange { background:#ea580c; color:#fff; }
.tb-kpi__val { font-size:1.75rem; font-weight:800; color:oklch(0.18 0.012 145); line-height:1.1; }
.tb-kpi__lbl { font-size:.8rem; color:oklch(0.44 0.010 145); margin-top:2px; }
.tb-kpi__sub { font-size:.7rem; color:oklch(0.56 0.008 145); margin-top:1px; }

/* Chart cards */
.tb-chart-grid { display:grid; gap:16px; margin-bottom:24px; }
.tb-chart-grid--3 { grid-template-columns:repeat(3,1fr); }
.tb-chart-grid--2 { grid-template-columns:repeat(2,1fr); }
.tb-chart-grid--1-2 { grid-template-columns:1fr 2fr; }
@media(max-width:960px){
    .tb-chart-grid--3,.tb-chart-grid--2,.tb-chart-grid--1-2 { grid-template-columns:1fr; }
}

.tb-card {
    background:#fff; border-radius:12px; padding:20px 22px;
    border:1px solid oklch(0.87 0.012 145);
    box-shadow:0 1px 4px oklch(0 0 0 / 0.04);
}
.tb-card__title {
    font-size:.9375rem; font-weight:800; color:oklch(0.18 0.012 145);
    margin:0 0 4px; display:flex; align-items:center; gap:8px;
}
.tb-card__title .material-symbols-outlined { font-size:18px; color:oklch(0.48 0.14 145); }
.tb-card__sub { font-size:.8rem; color:oklch(0.48 0.008 145); margin:0 0 16px; }
.tb-card canvas { max-width:100%; }

/* Stunting highlight */
.tb-stunting-row { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
.tb-highlight {
    flex:1; min-width:140px; border-radius:10px; padding:14px 16px;
    display:flex; align-items:center; gap:12px;
}
.tb-highlight--danger  { background:oklch(0.96 0.06 25 / 0.25); border:1px solid oklch(0.88 0.08 25 / 0.4); }
.tb-highlight--warning { background:oklch(0.96 0.08 75 / 0.25); border:1px solid oklch(0.88 0.10 75 / 0.4); }
.tb-highlight--green   { background:oklch(0.95 0.06 145 / 0.3); border:1px solid oklch(0.83 0.08 145 / 0.4); }
.tb-highlight__pct { font-size:2rem; font-weight:900; }
.tb-highlight--danger  .tb-highlight__pct { color:oklch(0.42 0.17 25); }
.tb-highlight--warning .tb-highlight__pct { color:oklch(0.42 0.14 70); }
.tb-highlight--green   .tb-highlight__pct { color:oklch(0.38 0.13 145); }
.tb-highlight__lbl { font-size:.8125rem; font-weight:700; line-height:1.3; }
.tb-highlight--danger  .tb-highlight__lbl { color:oklch(0.36 0.14 25); }
.tb-highlight--warning .tb-highlight__lbl { color:oklch(0.40 0.12 70); }
.tb-highlight--green   .tb-highlight__lbl { color:oklch(0.32 0.12 145); }

/* Coverage table */
.tb-cov-table { width:100%; border-collapse:collapse; font-size:.8125rem; }
.tb-cov-table thead th {
    background:oklch(0.95 0.016 145); padding:9px 12px;
    font-size:.6875rem; font-weight:800; letter-spacing:.06em;
    text-transform:uppercase; color:oklch(0.44 0.010 145);
    white-space:nowrap;
}
.tb-cov-table tbody td { padding:10px 12px; border-top:1px solid oklch(0.93 0.012 145); vertical-align:middle; }
.tb-cov-table tbody tr:hover { background:oklch(0.97 0.012 145); }
.tb-bar-wrap { width:100px; background:oklch(0.91 0.04 145 / 0.4); border-radius:4px; height:8px; display:inline-block; vertical-align:middle; }
.tb-bar { height:100%; border-radius:4px; background:oklch(0.48 0.14 145); transition:width .4s; }
.tb-bar--amber { background:#d97706; }

/* ASI bulan chart bar */
.tb-asi-bar { display:flex; flex-direction:column; gap:8px; }
.tb-asi-row { display:flex; align-items:center; gap:10px; font-size:.8rem; }
.tb-asi-row .tb-asi-lbl { width:70px; color:oklch(0.36 0.010 145); font-weight:700; flex-shrink:0; }
.tb-asi-row .tb-asi-track { flex:1; background:oklch(0.91 0.04 145 / 0.4); border-radius:4px; height:14px; }
.tb-asi-row .tb-asi-fill { height:100%; border-radius:4px; background:oklch(0.48 0.14 145); }
.tb-asi-row .tb-asi-pct { width:44px; text-align:right; font-weight:700; color:oklch(0.36 0.012 145); }

/* Pitting edema */
.tb-pe-list { display:flex; flex-direction:column; gap:8px; }
.tb-pe-row { display:flex; align-items:center; gap:10px; font-size:.8rem; }
.tb-pe-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.tb-pe-row .tb-pe-lbl { flex:1; color:oklch(0.36 0.010 145); }
.tb-pe-row .tb-pe-cnt { font-weight:700; color:oklch(0.24 0.012 145); }

/* Loading overlay */
.tb-loading { text-align:center; padding:32px; color:oklch(0.52 0.008 145); }
.tb-spin { animation:tb-spin .8s linear infinite; display:inline-block; font-size:28px; color:oklch(0.48 0.14 145 / 0.6); }
@keyframes tb-spin{ to{ transform:rotate(360deg); } }
</style>

{{-- ── Filter bar ────────────────────────────────────────────── --}}
<div class="tb-filter">
    <span class="material-symbols-outlined" style="font-size:20px;color:oklch(0.48 0.14 145);">filter_alt</span>
    <label for="f-tahun">Tahun</label>
    <select id="f-tahun">
        <option value="">Semua Tahun</option>
        @foreach($tahunList as $th)
        <option value="{{ $th }}">{{ $th }}</option>
        @endforeach
    </select>
    <label for="f-kel">Kelurahan</label>
    <select id="f-kel">
        <option value="">Semua Kelurahan</option>
        @foreach($kelurahanList as $kel)
        <option value="{{ $kel->id }}">{{ $kel->name }}</option>
        @endforeach
    </select>
    <button class="tb-filter-btn" id="btn-apply">
        <span class="material-symbols-outlined">search</span>Terapkan
    </button>
    <button class="tb-filter-btn" id="btn-reset" style="background:oklch(0.56 0.010 145 / 0.6);">
        <span class="material-symbols-outlined">restart_alt</span>Reset
    </button>
</div>

{{-- ── KPI Cards ─────────────────────────────────────────────── --}}
<p class="tb-section"><span class="material-symbols-outlined" style="font-size:16px;">monitoring</span>Ringkasan Operasi Timbang</p>
<div class="tb-kpi-grid" id="kpi-grid">
    <div class="tb-kpi"><div class="tb-kpi__icon tb-kpi__icon--green"><span class="material-symbols-outlined">groups</span></div><div><div class="tb-kpi__val" id="kpi-ditimbang">—</div><div class="tb-kpi__lbl">Anak Pernah Ditimbang</div><div class="tb-kpi__sub" id="kpi-coverage">dari <span id="kpi-total-anak">—</span> terdaftar</div></div></div>
    <div class="tb-kpi"><div class="tb-kpi__icon tb-kpi__icon--blue"><span class="material-symbols-outlined">event_available</span></div><div><div class="tb-kpi__val" id="kpi-kunjungan">—</div><div class="tb-kpi__lbl">Total Kunjungan Timbang</div><div class="tb-kpi__sub">sesuai filter</div></div></div>
    <div class="tb-kpi"><div class="tb-kpi__icon tb-kpi__icon--amber"><span class="material-symbols-outlined">vaccines</span></div><div><div class="tb-kpi__val" id="kpi-vita">—</div><div class="tb-kpi__lbl">Coverage Vitamin A</div><div class="tb-kpi__sub">kunjungan terakhir per anak</div></div></div>
    <div class="tb-kpi"><div class="tb-kpi__icon tb-kpi__icon--teal"><span class="material-symbols-outlined">restaurant</span></div><div><div class="tb-kpi__val" id="kpi-mbg">—</div><div class="tb-kpi__lbl">Coverage MBG</div><div class="tb-kpi__sub">Makanan Bergizi</div></div></div>
</div>

{{-- ── STATUS GIZI ───────────────────────────────────────────── --}}
<p class="tb-section"><span class="material-symbols-outlined" style="font-size:16px;">emergency</span>Status Gizi Balita (Kebijakan Pimpinan)</p>

<div class="tb-stunting-row" id="stunting-row">
    <div class="tb-highlight tb-highlight--danger">
        <div><div class="tb-highlight__pct" id="hl-stunting">—%</div><div class="tb-highlight__lbl">Stunting<br><small style="font-weight:400;">(TB/U &lt; -2SD)</small></div></div>
    </div>
    <div class="tb-highlight tb-highlight--warning">
        <div><div class="tb-highlight__pct" id="hl-underweight">—%</div><div class="tb-highlight__lbl">Berat Kurang<br><small style="font-weight:400;">(BB/U &lt; -2SD)</small></div></div>
    </div>
    <div class="tb-highlight tb-highlight--green">
        <div><div class="tb-highlight__pct" id="hl-normal">—%</div><div class="tb-highlight__lbl">Status Gizi Normal<br><small style="font-weight:400;">(BB/U)</small></div></div>
    </div>
</div>

<div class="tb-chart-grid tb-chart-grid--3" style="margin-bottom:28px;">
    <div class="tb-card">
        <p class="tb-card__title"><span class="material-symbols-outlined">height</span>Status TB/U (Stunting)</p>
        <p class="tb-card__sub">Distribusi tinggi badan per usia — kunjungan terakhir</p>
        <canvas id="chart-tbu" height="220"></canvas>
    </div>
    <div class="tb-card">
        <p class="tb-card__title"><span class="material-symbols-outlined">monitor_weight</span>Status BB/U (Gizi)</p>
        <p class="tb-card__sub">Distribusi berat badan per usia — kunjungan terakhir</p>
        <canvas id="chart-bbu" height="220"></canvas>
    </div>
    <div class="tb-card">
        <p class="tb-card__title"><span class="material-symbols-outlined">calculate</span>Status IMT/U</p>
        <p class="tb-card__sub">Indeks massa tubuh per usia</p>
        <canvas id="chart-imtu" height="220"></canvas>
    </div>
</div>

{{-- ── TREN ──────────────────────────────────────────────────── --}}
<p class="tb-section"><span class="material-symbols-outlined" style="font-size:16px;">trending_up</span>Tren Perkembangan</p>
<div class="tb-chart-grid tb-chart-grid--2" style="margin-bottom:28px;">
    <div class="tb-card">
        <p class="tb-card__title"><span class="material-symbols-outlined">event</span>Kunjungan Timbang (12 Bulan Terakhir)</p>
        <p class="tb-card__sub">Jumlah kunjungan per bulan</p>
        <canvas id="chart-kunjungan" height="200"></canvas>
    </div>
    <div class="tb-card">
        <p class="tb-card__title"><span class="material-symbols-outlined">show_chart</span>Rata-rata BB & TB per Usia</p>
        <p class="tb-card__sub">Bulan usia 0–60 (balita)</p>
        <canvas id="chart-growth" height="200"></canvas>
    </div>
</div>

{{-- ── COVERAGE WILAYAH ─────────────────────────────────────── --}}
<p class="tb-section"><span class="material-symbols-outlined" style="font-size:16px;">location_on</span>Ketercapaian per Wilayah (Kinerja Pemerintah)</p>
<div class="tb-chart-grid tb-chart-grid--2" style="margin-bottom:28px;">
    <div class="tb-card">
        <p class="tb-card__title"><span class="material-symbols-outlined">bar_chart</span>Coverage Timbang per Kelurahan</p>
        <p class="tb-card__sub">% anak pernah ditimbang dari total terdaftar</p>
        <canvas id="chart-cov-kel" height="220"></canvas>
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
                <canvas id="chart-cara" height="130"></canvas>
            </div>
        </div>
    </div>
</div>
</div>{{-- /tb-page --}}
@endsection

@section('scripts')
@parent
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
'use strict';

var API_RINGKASAN = '{{ route("admin.timbang.ringkasan") }}';
var API_GIZI      = '{{ route("admin.timbang.gizi") }}';
var API_TREN      = '{{ route("admin.timbang.tren") }}';
var API_COVERAGE  = '{{ route("admin.timbang.coverage") }}';
var API_PROGRAM   = '{{ route("admin.timbang.program") }}';

var charts = {};

// ── Filters ──────────────────────────────────────────────────
function getParams(){
    var t = document.getElementById('f-tahun').value;
    var k = document.getElementById('f-kel').value;
    var p = [];
    if(t) p.push('tahun='+t);
    if(k) p.push('kelurahan='+k);
    return p.length ? '?'+p.join('&') : '';
}

document.getElementById('btn-apply').addEventListener('click', loadAll);
document.getElementById('btn-reset').addEventListener('click', function(){
    document.getElementById('f-tahun').value = '';
    document.getElementById('f-kel').value   = '';
    loadAll();
});

// ── Utils ─────────────────────────────────────────────────────
function pct(v){ return (v !== null && v !== undefined) ? v+'%' : '—%'; }
function num(v){ return (v !== null && v !== undefined) ? Number(v).toLocaleString('id') : '—'; }

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
var GREEN     = 'oklch(0.48 0.14 145)';
var GREEN_A   = 'oklch(0.48 0.14 145 / 0.75)';
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
        document.getElementById('kpi-ditimbang').textContent   = num(d.total_ditimbang);
        document.getElementById('kpi-total-anak').textContent  = num(d.total_anak);
        document.getElementById('kpi-coverage').innerHTML      = 'Coverage <strong style="color:oklch(0.38 0.13 145)">'+pct(d.coverage)+'</strong> dari '+num(d.total_anak)+' terdaftar';
        document.getElementById('kpi-kunjungan').textContent   = num(d.total_kunjungan);
        document.getElementById('kpi-vita').textContent        = pct(d.vit_a_coverage);
        document.getElementById('kpi-mbg').textContent         = pct(d.mbg_rate);
    });
}

// ── GIZI ──────────────────────────────────────────────────────
function loadGizi(){
    $.getJSON(API_GIZI+getParams(), function(d){
        // Highlights
        document.getElementById('hl-stunting').textContent    = pct(d.stunting_pct);
        document.getElementById('hl-underweight').textContent = pct(d.underweight_pct);
        var normalPct = d.total > 0 ? Math.round(d.bb_u.normal / d.total * 100) : 0;
        document.getElementById('hl-normal').textContent = normalPct+'%';

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

        // IMT/U donut
        destroyChart('chart-imtu');
        var imt = d.imt_u;
        charts['chart-imtu'] = new Chart(document.getElementById('chart-imtu'), {
            type:'doughnut',
            data:{
                labels:['Normal','Kurang','Buruk','Lebih','Obesitas'],
                datasets:[{ data:[imt.normal,imt.kurang,imt.buruk,imt.lebih,imt.obesitas],
                    backgroundColor:[GREEN_A,AMBER,RED,BLUE,VIOLET], borderWidth:2, borderColor:'#fff' }]
            },
            options:{ plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } }, cutout:'65%' }
        });
    });
}

// ── TREN ──────────────────────────────────────────────────────
function loadTren(){
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
    });
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
            : '<div style="text-align:center;padding:24px;color:#94a3b8;">Belum ada data</div>';
    });
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
        document.getElementById('asi-bar').innerHTML = asiHtml || '<div style="color:#94a3b8;font-size:.8rem;">Belum ada data ASI</div>';

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
        document.getElementById('pe-list').innerHTML = peHtml || '<div style="color:#94a3b8;font-size:.8rem;">—</div>';

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
    });
}

function escHtml(s){
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function loadAll(){
    loadRingkasan();
    loadGizi();
    loadTren();
    loadCoverage();
    loadProgram();
}

loadAll();

})();
</script>
@endsection
