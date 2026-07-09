@extends('admin::layouts.app')
@section('title')Dashboard Imunisasi@endsection
@section('title-content')Dashboard Imunisasi@endsection
@section('item')Imunisasi@endsection
@section('item-active')Dashboard IDL@endsection

@section('content')
<div class="im-page">
<style>
@import url('https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap');

.im-page{
    --green:oklch(0.60 0.15 145); --green-d:oklch(0.48 0.14 145); --green-dk:oklch(0.38 0.13 145);
    --amber:oklch(0.44 0.13 70); --amber-bg:oklch(0.94 0.06 70);
    --red:oklch(0.50 0.18 25); --red-d:oklch(0.46 0.17 25); --red-bg:oklch(0.94 0.055 25);
    --ink:oklch(0.24 0.02 145); --muted:oklch(0.50 0.015 145); --faint:oklch(0.62 0.012 145);
    --line:oklch(0.90 0.012 145); --bg:oklch(0.98 0.012 145); --card:#fff;
    font-family:'Barlow',system-ui,sans-serif; color:var(--ink);
}
.im-page *{ box-sizing:border-box; }
.im-num{ font-family:'Barlow Condensed','Barlow',sans-serif; font-variant-numeric:tabular-nums; }

/* Section heading */
.im-h{ display:flex; align-items:baseline; gap:.6rem; margin:0 0 .85rem; }
.im-h h2{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:1.18rem; letter-spacing:.01em; margin:0; color:var(--ink); }
.im-h small{ color:var(--faint); font-size:.8rem; }

/* Filter bar */
.im-filter{ display:flex; flex-wrap:wrap; align-items:flex-end; gap:.75rem; margin-bottom:1.4rem; }
.im-filter > div{ display:flex; flex-direction:column; }
.im-filter label{ font-size:.68rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:var(--muted); margin-bottom:.28rem; }
.im-filter select{ height:38px; padding:0 .7rem; border:1px solid oklch(0.84 0.012 145); border-radius:8px; background:var(--card); font-family:inherit; font-size:.85rem; min-width:180px; color:var(--ink); }
.im-filter select:focus{ outline:2px solid oklch(0.60 0.15 145 / .35); outline-offset:1px; border-color:var(--green); }
.im-btn{ display:inline-flex; align-items:center; gap:.4rem; height:38px; padding:0 1rem; border-radius:8px; font-family:inherit; font-weight:600; font-size:.85rem; border:1px solid transparent; cursor:pointer; text-decoration:none; transition:background .14s,border-color .14s,color .14s; }
.im-btn--primary{ background:var(--green-d); color:#fff; }
.im-btn--primary:hover{ background:var(--green-dk); color:#fff; }
.im-btn--ghost{ background:transparent; border-color:var(--line); color:var(--muted); }
.im-btn--ghost:hover{ border-color:var(--faint); color:var(--ink); }
.im-btn--sm{ height:30px; padding:0 .7rem; font-size:.78rem; }

/* Hero coverage panel */
.im-hero{
    background:var(--card); border:1px solid var(--line); border-radius:16px;
    padding:1.5rem 1.7rem; margin-bottom:1.5rem; box-shadow:0 1px 3px oklch(0 0 0 / .04);
    display:grid; grid-template-columns:auto 1fr; gap:1.8rem; align-items:center;
}
.im-hero__fig{ text-align:center; min-width:118px; }
.im-hero__pct{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:4rem; line-height:.85; }
.im-hero__pct span{ font-size:1.5rem; margin-left:.05rem; }
.im-hero--ok .im-hero__pct{ color:var(--green-dk); }
.im-hero--ok .im-hero__pct span{ color:oklch(0.60 0.10 145); }
.im-hero--low .im-hero__pct{ color:var(--red-d); }
.im-hero--low .im-hero__pct span{ color:oklch(0.62 0.10 25); }
.im-hero__lbl{ font-size:.7rem; font-weight:700; letter-spacing:.09em; text-transform:uppercase; color:var(--muted); margin-top:.45rem; }
.im-hero__flag{ display:inline-flex; align-items:center; gap:.25rem; margin-top:.6rem; font-size:.72rem; font-weight:700; padding:.16rem .55rem; border-radius:20px; }
.im-hero__flag.ok{ background:oklch(0.94 0.06 145); color:var(--green-dk); }
.im-hero__flag.low{ background:var(--red-bg); color:var(--red-d); }
.im-hero__body{ min-width:0; }

/* Progress bar w/ national target marker */
.im-bar{ position:relative; height:14px; border-radius:8px; background:oklch(0.93 0.02 145); overflow:hidden; }
.im-bar__fill{ height:100%; border-radius:8px; transition:width .6s cubic-bezier(.22,1,.36,1); }
.im-bar__fill.ok{ background:var(--green); }
.im-bar__fill.mid{ background:oklch(0.72 0.14 80); }
.im-bar__fill.low{ background:oklch(0.62 0.16 30); }
.im-bar__target{ position:absolute; top:-3px; bottom:-3px; width:2px; background:var(--ink); opacity:.55; }
.im-target-cap{ display:flex; justify-content:flex-end; margin-top:.3rem; }
.im-target-cap span{ font-size:.68rem; font-weight:600; color:var(--faint); letter-spacing:.02em; }

/* Mini stats row inside hero */
.im-mini{ display:flex; flex-wrap:wrap; gap:1.6rem; margin-top:1.1rem; }
.im-mini__it{ display:flex; flex-direction:column; }
.im-mini__n{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:1.5rem; line-height:1; color:var(--ink); }
.im-mini__n.warn{ color:var(--red-d); }
.im-mini__k{ font-size:.72rem; font-weight:600; color:var(--muted); margin-top:.25rem; letter-spacing:.02em; }
@media(max-width:640px){ .im-hero{ grid-template-columns:1fr; gap:1.1rem; text-align:left; } .im-hero__fig{ text-align:left; } .im-hero__pct{ font-size:3.2rem; } }

/* Per-kelurahan card */
.im-panel{ background:var(--card); border:1px solid var(--line); border-radius:16px; padding:1.4rem 1.6rem; margin-bottom:1.5rem; box-shadow:0 1px 3px oklch(0 0 0 / .04); }
.im-kel{ padding:.7rem 0; border-top:1px solid var(--line); }
.im-kel:first-of-type{ border-top:none; padding-top:0; }
.im-kel__top{ display:flex; justify-content:space-between; align-items:baseline; gap:1rem; margin-bottom:.4rem; }
.im-kel__name{ font-weight:600; font-size:.92rem; color:var(--ink); }
.im-kel__val{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:.98rem; font-variant-numeric:tabular-nums; white-space:nowrap; }
.im-kel__val.ok{ color:var(--green-dk); }
.im-kel__val.low{ color:var(--red-d); }
.im-kel__val small{ color:var(--faint); font-weight:600; font-size:.78rem; }
.im-kel__gap{ font-size:.74rem; color:var(--faint); margin-top:.3rem; }

/* Table */
.im-tablewrap{ background:var(--card); border:1px solid var(--line); border-radius:16px; overflow:hidden; box-shadow:0 1px 3px oklch(0 0 0 / .04); margin-bottom:1.5rem; }
.im-tablewrap__head{ display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1.1rem 1.4rem .95rem; }
.im-tablewrap__head h2{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:1.18rem; margin:0; color:var(--ink); }
.im-count{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:.82rem; color:var(--muted); background:oklch(0.95 0.012 145); padding:.2rem .6rem; border-radius:20px; }
.im-scroll{ overflow-x:auto; }
.im-table{ width:100%; border-collapse:collapse; font-size:.86rem; }
.im-table thead th{ background:oklch(0.96 0.015 145); text-align:left; padding:.65rem .9rem; font-size:.65rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); white-space:nowrap; }
.im-table thead th:first-child{ text-align:right; width:44px; }
.im-table tbody td{ padding:.7rem .9rem; border-top:1px solid var(--line); vertical-align:top; }
.im-table tbody td:first-child{ text-align:right; color:var(--faint); font-variant-numeric:tabular-nums; }
.im-table tbody tr:hover{ background:oklch(0.985 0.012 145); }
.im-table tbody tr.attn{ background:oklch(0.985 0.02 60); }
.im-table tbody tr.attn:hover{ background:oklch(0.97 0.03 60); }
.im-anak{ font-weight:700; color:var(--green-dk); text-decoration:none; }
.im-anak:hover{ text-decoration:underline; color:var(--green-dk); }
.im-nik{ font-size:.74rem; color:var(--faint); font-variant-numeric:tabular-nums; }
.im-geo,.im-usia{ font-size:.82rem; color:var(--muted); white-space:nowrap; }
.im-usia{ font-variant-numeric:tabular-nums; }
.im-vaks{ font-size:.8rem; color:var(--ink); line-height:1.4; }
.im-vaks .more{ color:var(--faint); }
.im-dash{ color:var(--faint); }

/* Status pills */
.im-st{ display:inline-block; font-size:.72rem; font-weight:700; padding:.12rem .5rem; border-radius:6px; white-space:nowrap; }
.im-st--ok{ background:oklch(0.94 0.06 145); color:var(--green-dk); }
.im-st--idl{ background:var(--red-bg); color:var(--red-d); }
.im-st--belum{ background:var(--amber-bg); color:var(--amber); }
.im-st--ibl{ background:oklch(0.93 0.07 55); color:oklch(0.47 0.14 50); margin-left:.25rem; }
.im-tag{ display:inline-block; font-size:.66rem; font-weight:600; padding:.06rem .38rem; border-radius:5px; background:oklch(0.94 0.012 145); color:var(--muted); margin-left:.3rem; }

/* Two-up analytics grid */
.im-2up{ display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem; }
@media(max-width:900px){ .im-2up{ grid-template-columns:1fr; } }
.im-chartbox{ min-height:60px; }

/* Data table (alasan) */
.im-mini-table{ width:100%; border-collapse:collapse; font-size:.84rem; margin-top:1rem; }
.im-mini-table th{ text-align:left; padding:.45rem .6rem; font-size:.64rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:var(--muted); border-bottom:1px solid var(--line); }
.im-mini-table th.r,.im-mini-table td.r{ text-align:right; }
.im-mini-table td{ padding:.45rem .6rem; border-bottom:1px solid var(--line); }
.im-mini-table tbody tr:last-child td{ border-bottom:none; }
.im-mini-table td.r{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-variant-numeric:tabular-nums; color:var(--ink); }

/* Empty */
.im-empty{ text-align:center; padding:2.4rem 1rem; color:var(--muted); font-size:.88rem; }
.im-empty strong{ display:block; font-family:'Barlow Condensed','Barlow',sans-serif; font-size:1.05rem; color:var(--ink); margin-bottom:.3rem; }

/* Pagination (tame Bootstrap) */
.im-pg{ padding:.9rem 1.4rem; border-top:1px solid var(--line); }
.im-pg .pagination{ margin:0; gap:.25rem; }
.im-pg .page-link{ border:1px solid var(--line); border-radius:8px; color:var(--muted); font-size:.85rem; padding:.35rem .7rem; }
.im-pg .page-item.active .page-link{ background:var(--green-d); border-color:var(--green-d); color:#fff; }
</style>

@php
    $totalAnak      = $coverage['total'];
    $idlLengkap     = $coverage['idl_lengkap'];
    $persen         = $coverage['persen'];
    $targetTercapai = $persen >= 95;
    $selisih        = max(0, 95 - $persen);
    $heroFill       = $persen >= 95 ? 'ok' : ($persen >= 80 ? 'mid' : 'low');
@endphp

{{-- Filter wilayah --}}
<form method="GET" action="{{ route('admin.imunisasiDashboard') }}" class="im-filter">
    <div>
        <label for="filterKec">Kecamatan</label>
        <select name="id_kecamatan" id="filterKec">
            <option value="">Semua Kecamatan</option>
            @foreach($kecamatanList as $kec)
                <option value="{{ $kec->id }}" {{ ($filters['id_kecamatan'] ?? null) == $kec->id ? 'selected' : '' }}>{{ $kec->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filterKel">Kelurahan</label>
        <select name="id_kelurahan" id="filterKel">
            <option value="">Semua Kelurahan</option>
            @foreach($kelurahanList as $kel)
                <option value="{{ $kel->id }}" {{ ($filters['id_kelurahan'] ?? null) == $kel->id ? 'selected' : '' }}>{{ $kel->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filterPos">Posyandu</label>
        <select name="id_posyandu" id="filterPos">
            <option value="">Semua Posyandu</option>
            @foreach($posyanduList as $pos)
                <option value="{{ $pos->id }}" {{ ($filters['id_posyandu'] ?? null) == $pos->id ? 'selected' : '' }}>{{ $pos->name }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="im-btn im-btn--primary">
        <span class="material-symbols-outlined" style="font-size:18px;">filter_alt</span>Terapkan
    </button>
    @if(!empty($filters))
    <a href="{{ route('admin.imunisasiDashboard') }}" class="im-btn im-btn--ghost">Reset</a>
    @endif
</form>

{{-- Hero: cakupan IDL --}}
<section class="im-hero {{ $targetTercapai ? 'im-hero--ok' : 'im-hero--low' }}">
    <div class="im-hero__fig">
        <div class="im-hero__pct im-num">{{ $persen }}<span>%</span></div>
        <div class="im-hero__lbl">Imunisasi Dasar Lengkap</div>
        <div class="im-hero__flag {{ $targetTercapai ? 'ok' : 'low' }}">
            <span class="material-symbols-outlined" style="font-size:15px;">{{ $targetTercapai ? 'verified' : 'trending_up' }}</span>
            {{ $targetTercapai ? 'Target tercapai' : 'Di bawah target' }}
        </div>
    </div>
    <div class="im-hero__body">
        <div class="im-bar">
            <div class="im-bar__fill {{ $heroFill }}" style="width:{{ min(100, $persen) }}%"></div>
            <div class="im-bar__target" style="left:95%"></div>
        </div>
        <div class="im-target-cap"><span>Target nasional 95%</span></div>
        <div class="im-mini">
            <div class="im-mini__it">
                <span class="im-mini__n im-num">{{ number_format($totalAnak) }}</span>
                <span class="im-mini__k">Total anak (≥12 bln)</span>
            </div>
            <div class="im-mini__it">
                <span class="im-mini__n im-num">{{ number_format($idlLengkap) }}</span>
                <span class="im-mini__k">Sudah lengkap</span>
            </div>
            <div class="im-mini__it">
                <span class="im-mini__n im-num {{ $butuhKejar > 0 ? 'warn' : '' }}">{{ number_format($butuhKejar) }}</span>
                <span class="im-mini__k">Butuh kejar (IDL/IBL)</span>
            </div>
            @if(!$targetTercapai)
            <div class="im-mini__it">
                <span class="im-mini__n im-num">{{ $selisih }}%</span>
                <span class="im-mini__k">Selisih ke target</span>
            </div>
            @endif
        </div>
    </div>
</section>

{{-- Cakupan per kelurahan --}}
@if(count($coverage['per_kelurahan']) > 0)
<div class="im-panel">
    <div class="im-h"><h2>Cakupan IDL per Kelurahan</h2><small>garis vertikal = target 95%</small></div>
    @foreach($coverage['per_kelurahan'] as $row)
    @php
        $pct  = $row['persen'];
        $fill = $pct >= 95 ? 'ok' : ($pct >= 80 ? 'mid' : 'low');
    @endphp
    <div class="im-kel">
        <div class="im-kel__top">
            <span class="im-kel__name">{{ $row['nama'] }}</span>
            <span class="im-kel__val im-num {{ $pct >= 95 ? 'ok' : 'low' }}">{{ $pct }}% <small>({{ $row['lengkap'] }}/{{ $row['total'] }})</small></span>
        </div>
        <div class="im-bar" style="height:12px;">
            <div class="im-bar__fill {{ $fill }}" style="width:{{ min(100, $pct) }}%"></div>
            <div class="im-bar__target" style="left:95%"></div>
        </div>
        @if($pct < 95)
        <div class="im-kel__gap">Kurang {{ 95 - $pct }}% dari target nasional</div>
        @endif
    </div>
    @endforeach
</div>
@endif

{{-- Daftar anak --}}
<div class="im-tablewrap">
    <div class="im-tablewrap__head">
        <h2>Daftar Anak</h2>
        <span class="im-count im-num">{{ number_format($anakList->total()) }} anak</span>
    </div>
    <div class="im-scroll">
        <table class="im-table">
            <thead>
                <tr>
                    <th>#</th><th>Nama Anak</th><th>Usia</th><th>Kelurahan</th>
                    <th>Posyandu</th><th>Status IDL</th><th>Vaksin Terlewat</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($anakList as $row)
                @php
                    $anak    = $row['anak'];
                    $diff    = (new DateTime($anak->tgl_lahir))->diff(new DateTime());
                    $usiaStr = $diff->y . 'th ' . $diff->m . 'bl';
                    $attn    = !$row['idl_lengkap'] && ($row['kejar_idl'] || $row['kejar_ibl']);
                @endphp
                <tr class="{{ $attn ? 'attn' : '' }}">
                    <td>{{ $anakList->firstItem() + $loop->index }}</td>
                    <td>
                        <a href="{{ route('admin.showAnak', $anak->hashid) }}" class="im-anak">{{ $anak->nama }}</a>
                        <div class="im-nik">{{ $anak->nik }}@if($anak->isDummyNik())<span class="im-tag">NIK dummy</span>@endif</div>
                    </td>
                    <td class="im-usia">{{ $usiaStr }}</td>
                    <td class="im-geo">{{ $anak->kel?->nama ?? '—' }}</td>
                    <td class="im-geo">{{ $anak->posyandu?->nama ?? '—' }}</td>
                    <td>
                        @if($row['idl_lengkap'])
                            <span class="im-st im-st--ok">Lengkap</span>
                        @elseif($row['kejar_idl'])
                            <span class="im-st im-st--idl">Kejar IDL</span>
                        @else
                            <span class="im-st im-st--belum">Belum lengkap</span>
                        @endif
                        @if($row['kejar_ibl'])<span class="im-st im-st--ibl">Kejar IBL</span>@endif
                    </td>
                    <td>
                        @if(count($row['vaksin_kejar']) > 0)
                            <span class="im-vaks">{{ implode(', ', array_slice($row['vaksin_kejar'], 0, 3)) }}@if(count($row['vaksin_kejar']) > 3)<span class="more"> +{{ count($row['vaksin_kejar']) - 3 }} lainnya</span>@endif</span>
                        @else
                            <span class="im-dash">—</span>
                        @endif
                    </td>
                    <td><a href="{{ route('admin.jadwalImunisasi', $anak->hashid) }}" class="im-btn im-btn--ghost im-btn--sm">Jadwal</a></td>
                </tr>
                @empty
                <tr><td colspan="8"><div class="im-empty"><strong>Tidak ada anak pada filter ini</strong>Sesuaikan filter wilayah di atas untuk melihat daftar anak.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($anakList->hasPages())
    <div class="im-pg">{{ $anakList->links('pagination::bootstrap-4') }}</div>
    @endif
</div>

{{-- Analitik: alasan + korelasi --}}
<div class="im-2up">
    <div class="im-panel" style="margin-bottom:0;">
        <div class="im-h"><h2>Alasan Tidak Imunisasi</h2></div>
        <p style="color:var(--faint); font-size:.8rem; margin:-.5rem 0 .9rem;">Dari kunjungan terakhir tiap anak pada filter aktif.</p>
        @if(count($alasanTidakImunisasi) > 0)
        <div class="im-chartbox"><canvas id="chartAlasan" height="150"></canvas></div>
        <table class="im-mini-table">
            <thead><tr><th>Alasan</th><th class="r" style="width:110px;">Jumlah</th></tr></thead>
            <tbody>
            @foreach($alasanTidakImunisasi as $alasan => $jumlah)
                <tr><td>{{ $alasan }}</td><td class="r im-num">{{ $jumlah }}</td></tr>
            @endforeach
            </tbody>
        </table>
        @else
        <div class="im-empty"><strong>Belum ada data</strong>Tidak ada catatan alasan tidak imunisasi pada filter ini.</div>
        @endif
    </div>

    <div class="im-panel" style="margin-bottom:0;">
        <div class="im-h"><h2>IDL vs Stunting</h2></div>
        <p style="color:var(--faint); font-size:.8rem; margin:-.5rem 0 .9rem;">Tiap titik = 1 kelurahan. Korelasi tingkat wilayah, bukan kausalitas individual.</p>
        @if(count($korelasiData) > 0)
        <div class="im-chartbox"><canvas id="chartKorelasi" height="150"></canvas></div>
        @else
        <div class="im-empty"><strong>Belum ada data</strong>Belum ada balita terukur untuk membentuk korelasi.</div>
        @endif
    </div>
</div>
</div>{{-- /im-page --}}
@endsection

@section('custom_scripts')
{{-- Daftar Anak dipaginasi server-side (lihat AdminController::imunisasiDashboard). --}}

@php $needChart = count($korelasiData) > 0 || count($alasanTidakImunisasi) > 0; @endphp
@if($needChart)
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endif

@if($needChart)
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = "'Barlow', system-ui, sans-serif";
    Chart.defaults.color = '#5a675a';

    var GREEN = 'rgba(0,166,81,0.75)', GREEN_S = 'rgba(0,166,81,0.9)';
    var AMBER = 'rgba(202,138,4,0.82)', AMBER_S = 'rgba(180,120,0,0.95)';
    var INK   = 'rgba(45,58,45,0.55)';
    var GRID  = 'rgba(60,70,60,0.08)';

    {{-- Bar horizontal: alasan tidak imunisasi --}}
    @if(count($alasanTidakImunisasi) > 0)
    (function () {
        var ctx = document.getElementById('chartAlasan');
        if (!ctx) return;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: @json(array_keys($alasanTidakImunisasi)),
                datasets: [{
                    data: @json(array_values($alasanTidakImunisasi)),
                    backgroundColor: AMBER, hoverBackgroundColor: AMBER_S,
                    borderRadius: 4, barThickness: 'flex', maxBarThickness: 26
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: true,
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID } },
                    y: { grid: { display: false } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (c) { return c.parsed.x + ' anak'; } } }
                }
            }
        });
    })();
    @endif

    {{-- Scatter korelasi IDL vs stunting --}}
    @if(count($korelasiData) > 0)
    (function () {
        var ctx = document.getElementById('chartKorelasi');
        if (!ctx) return;
        var data = @json($korelasiData);
        var points = data.map(function (d) {
            return { x: d.idl_pct, y: d.stunting_pct, nama: d.nama, total: d.total_balita, idl: d.idl_lengkap, stunt: d.stunting };
        });

        // Garis tren linear sederhana (least squares).
        var n = points.length, sx = 0, sy = 0, sxy = 0, sxx = 0;
        points.forEach(function (p) { sx += p.x; sy += p.y; sxy += p.x * p.y; sxx += p.x * p.x; });
        var trend = [];
        if (n >= 2 && (n * sxx - sx * sx) !== 0) {
            var b = (n * sxy - sx * sy) / (n * sxx - sx * sx);
            var a = (sy - b * sx) / n;
            trend = [{ x: 0, y: a }, { x: 100, y: a + b * 100 }];
        }

        new Chart(ctx, {
            type: 'scatter',
            data: {
                datasets: [
                    { label: 'Kelurahan', data: points, backgroundColor: GREEN, hoverBackgroundColor: GREEN_S, pointRadius: 6, pointHoverRadius: 8 },
                    { type: 'line', label: 'Tren', data: trend, borderColor: INK, borderDash: [6, 4], borderWidth: 2, pointRadius: 0, fill: false }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: true,
                scales: {
                    x: { title: { display: true, text: '% IDL' }, min: 0, max: 100, grid: { color: GRID } },
                    y: { title: { display: true, text: '% Stunting' }, min: 0, max: 100, grid: { color: GRID } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (c) {
                                var p = c.raw;
                                if (p.nama === undefined) return '';
                                return [p.nama, 'IDL: ' + p.idl + '/' + p.total + ' (' + p.x + '%)', 'Stunting: ' + p.stunt + '/' + p.total + ' (' + p.y + '%)'];
                            }
                        }
                    }
                }
            }
        });
    })();
    @endif
})();
</script>
@endif
@endsection
