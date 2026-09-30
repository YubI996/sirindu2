@extends('admin::layouts.app')
@section('title') Dashboard Imunisasi @endsection
@section('title-content') Dashboard Imunisasi @endsection
@section('item') Imunisasi @endsection
@section('item-active') Dashboard IDL @endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/dasbor-base.css') }}">
@endpush

@section('content')
<div class="im-page">
<style>

/* Day tabs (Sasaran hari ini & besok) */
.im-daytab{ padding:.45rem .9rem; border:0; border-radius:6px; background:transparent; font-family:inherit; font-weight:600; font-size:.82rem; color:var(--muted); cursor:pointer; }
.im-daytab--on{ background:#fff; color:var(--ink); box-shadow:0 1px 2px oklch(0 0 0 / .1); }

/* Antigen due-date chips */
.im-chip{ display:inline-block; padding:.15rem .5rem; border-radius:5px; font-size:.7rem; font-weight:600; white-space:nowrap; }
.im-chip--ok{ background:oklch(0.94 0.06 145); color:var(--green-dk); }
.im-chip--belum{ background:oklch(0.95 0.012 145); color:var(--muted); }

/* Progress bar (reused across cards/tables). ok/mid/low = status performa (hijau/kuning/merah) —
   JANGAN dipakai untuk bar yang bukan status (mis. porsi populasi); pakai .neutral. */
.im-bar{ position:relative; height:8px; border-radius:8px; background:oklch(0.93 0.02 145); overflow:hidden; }
.im-bar__fill{ height:100%; border-radius:8px; }
.im-bar__fill.ok{ background:var(--green); }
.im-bar__fill.mid{ background:oklch(0.72 0.14 80); }
.im-bar__fill.low{ background:oklch(0.62 0.16 30); }
.im-bar__fill.neutral{ background:oklch(0.62 0.09 240); }

/* Generic panel */
.im-panel{ background:var(--card); border:1px solid var(--line); border-radius:16px; padding:1.4rem 1.6rem; margin-bottom:1.5rem; box-shadow:0 1px 3px oklch(0 0 0 / .04); }
.im-panel--flush{ padding:0; overflow:hidden; }

/* Two-up grid */
.im-grid2{ display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem; align-items:start; }
@media(max-width:980px){ .im-grid2{ grid-template-columns:1fr; } }

/* Kohort table (details/summary drilldown) */
.im-kohort-head{ display:flex; align-items:center; justify-content:space-between; padding:1rem 1.4rem; background:oklch(0.97 0.012 145); border-bottom:1px solid var(--line); }
.im-kohort-head h2{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:1.05rem; margin:0; }
.im-kec{ border-top:1px solid var(--line); }
.im-kec:first-of-type{ border-top:none; }
.im-kec > summary{ list-style:none; cursor:pointer; display:flex; align-items:center; gap:.9rem; padding:.85rem 1.4rem; font-weight:700; font-size:.92rem; }
.im-kec > summary::-webkit-details-marker{ display:none; }
.im-kec > summary::before{ content:'▸'; color:var(--green-d); font-size:.72rem; width:10px; }
.im-kec[open] > summary::before{ content:'▾'; }
.im-kec > summary .im-kec__meta{ color:var(--faint); font-weight:400; font-size:.78rem; }
.im-kec > summary .im-kec__stats{ margin-left:auto; display:flex; align-items:center; gap:1rem; }
.im-kec table{ width:100%; border-collapse:collapse; }
.im-kec td{ padding:.55rem 1.4rem; font-size:.84rem; border-top:1px solid oklch(0.95 0.012 145); }
.im-kec td.im-kel-nama{ padding-left:2.6rem; color:var(--muted); }
.im-kec td.r{ text-align:right; font-variant-numeric:tabular-nums; }
.im-kec td.bar{ width:150px; }

/* Legend (dipakai cakupan per antigen) */
.im-legend{ display:flex; flex-wrap:wrap; gap:.9rem; font-size:.74rem; color:var(--muted); }
.im-legend span{ display:inline-flex; align-items:center; gap:.35rem; }
.im-legend i{ width:9px; height:9px; border-radius:2px; display:inline-block; }

/* Puskesmas rincian table */
.im-table{ width:100%; border-collapse:collapse; font-size:.86rem; }
.im-table thead th{ background:oklch(0.96 0.015 145); text-align:left; padding:.65rem .9rem; font-size:.65rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); white-space:nowrap; }
.im-table tbody td{ padding:.7rem .9rem; border-top:1px solid var(--line); vertical-align:middle; }
.im-table tbody tr:hover{ background:oklch(0.985 0.012 145); }
.im-table .r{ text-align:right; font-variant-numeric:tabular-nums; }

/* Funnel */
.im-funnel__row{ display:flex; align-items:center; gap:.7rem; margin-bottom:.55rem; }
.im-funnel__lbl{ width:120px; font-size:.8rem; font-weight:600; flex-shrink:0; }
.im-funnel__track{ flex:1; height:18px; border-radius:4px; background:oklch(0.93 0.02 145); overflow:hidden; }
.im-funnel__fill{ height:100%; background:var(--green-d); }
.im-funnel__val{ width:90px; text-align:right; font-size:.8rem; font-weight:600; flex-shrink:0; }

/* Antigen coverage bars */
.im-antigen{ display:grid; grid-template-columns:1fr 1fr; gap:.6rem 2rem; }
@media(max-width:900px){ .im-antigen{ grid-template-columns:1fr; } }
.im-antigen__row{ display:flex; align-items:center; gap:.7rem; }
.im-antigen__lbl{ width:150px; font-size:.8rem; font-weight:600; flex-shrink:0; }
.im-antigen__track{ position:relative; flex:1; height:8px; border-radius:99px; background:oklch(0.93 0.02 145); overflow:hidden; }
.im-antigen__target{ position:absolute; top:0; bottom:0; left:95%; width:2px; background:var(--ink); opacity:.5; }
.im-antigen__val{ width:110px; text-align:right; font-size:.8rem; font-weight:600; flex-shrink:0; }
.im-antigen__val small{ color:var(--faint); font-weight:400; }

/* Data table (alasan) */
.im-mini-table{ width:100%; border-collapse:collapse; font-size:.84rem; margin-top:1rem; }
.im-mini-table th{ text-align:left; padding:.45rem .6rem; font-size:.64rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:var(--muted); border-bottom:1px solid var(--line); }
.im-mini-table th.r,.im-mini-table td.r{ text-align:right; }
.im-mini-table td{ padding:.45rem .6rem; border-bottom:1px solid var(--line); }
.im-mini-table tbody tr:last-child td{ border-bottom:none; }
.im-mini-table td.r{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-variant-numeric:tabular-nums; color:var(--ink); }
.im-chartbox{ min-height:60px; }

/* Empty */
.im-empty{ text-align:center; padding:2.4rem 1rem; color:var(--muted); font-size:.88rem; }
.im-empty strong{ display:block; font-family:'Barlow Condensed','Barlow',sans-serif; font-size:1.05rem; color:var(--ink); margin-bottom:.3rem; }
</style>

@php
    $totalAnak      = $coverage['total'];
    $idlLengkap     = $coverage['idl_lengkap'];
    $persenIdl      = $coverage['persen'];
    $bucketOf = function (float $p) { return $p >= 95 ? 'ok' : ($p >= 60 ? 'mid' : 'low'); };
@endphp

{{-- Filter wilayah --}}
<form method="GET" action="{{ route('admin.imunisasiDashboard') }}" class="im-filter">
    <div>
        <label for="filterTahun">Tahun sasaran</label>
        <select name="tahun" id="filterTahun">
            @foreach($pilihanTahun as $t)
                <option value="{{ $t }}" {{ $tahun == $t ? 'selected' : '' }}>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filterKec">Kecamatan</label>
        <select name="id_kecamatan" id="filterKec">
            <option value="">Semua kecamatan</option>
            @foreach($kecamatanList as $kec)
                <option value="{{ $kec->id }}" {{ ($filters['id_kecamatan'] ?? null) == $kec->id ? 'selected' : '' }}>{{ $kec->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filterKel">Kelurahan</label>
        <select name="id_kelurahan" id="filterKel">
            <option value="">Semua kelurahan</option>
            @foreach($kelurahanList as $kel)
                <option value="{{ $kel->id }}" data-kec="{{ $kel->id_kecamatan }}" {{ ($filters['id_kelurahan'] ?? null) == $kel->id ? 'selected' : '' }}>{{ $kel->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filterRt">RT</label>
        <select name="id_rt" id="filterRt">
            <option value="">Semua RT</option>
        </select>
    </div>
    <div>
        <label for="filterPkm">Puskesmas</label>
        <select name="id_puskesmas" id="filterPkm">
            <option value="">Semua puskesmas</option>
            @foreach($puskesmasList as $pkm)
                <option value="{{ $pkm->id }}" {{ ($filters['id_puskesmas'] ?? null) == $pkm->id ? 'selected' : '' }}>{{ $pkm->name }}</option>
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

{{-- Tab (BIAS belum dibangun — nonaktif) --}}
<div class="im-tabs">
    <button type="button" class="im-tab">Imunisasi Rutin</button>
    <button type="button" class="im-tab im-tab--off" disabled title="Belum tersedia">BIAS <span class="im-soon">Segera hadir</span></button>
</div>

{{-- Data sasaran: populasi kohort tahun terpilih, bukan capaian --}}
<div class="im-h">
    <h2>Data sasaran</h2>
    <small>{{ $sasaran['label'] }} &middot; bukan ukuran capaian</small>
</div>
<div class="im-cards im-cards--5">
    <div class="im-card">
        <div class="im-card__lbl">BBL &middot; bayi baru lahir</div>
        <div class="im-card__val im-num">{{ number_format($sasaran['bbl']['jumlah']) }}</div>
        <div class="im-card__sub">umur 0 &ndash; 1 bln 29 hr &middot; lahir {{ $sasaran['bbl']['rentang'][0] }} s.d. {{ $sasaran['bbl']['rentang'][1] }}</div>
    </div>
    <div class="im-card">
        <div class="im-card__lbl">SI &middot; Surviving Infant</div>
        <div class="im-card__val im-num">{{ number_format($sasaran['si']['jumlah']) }}</div>
        <div class="im-card__sub">umur 2 bln &ndash; blm genap 12 bln &middot; lahir {{ $sasaran['si']['rentang'][0] }} s.d. {{ $sasaran['si']['rentang'][1] }}</div>
    </div>
    <div class="im-card">
        <div class="im-card__lbl">Baduta &middot; bawah dua tahun</div>
        <div class="im-card__val im-num">{{ number_format($sasaran['baduta']['jumlah']) }}</div>
        <div class="im-card__sub">kohort {{ $sasaran['tahun'] - 1 }} &middot; lahir {{ $sasaran['baduta']['rentang'][0] }} s.d. {{ $sasaran['baduta']['rentang'][1] }}</div>
    </div>
    <div class="im-card im-card--na">
        <div class="im-card__lbl">WUS hamil</div>
        <div class="im-card__val im-num" style="color:var(--faint);">&mdash;</div>
        <div class="im-card__sub">Data belum tersedia &mdash; sistem ini belum mencatat data ibu/wanita usia subur</div>
    </div>
    <div class="im-card im-card--na">
        <div class="im-card__lbl">WUS tidak hamil</div>
        <div class="im-card__val im-num" style="color:var(--faint);">&mdash;</div>
        <div class="im-card__sub">Data belum tersedia &mdash; sistem ini belum mencatat data ibu/wanita usia subur</div>
    </div>
</div>
<p class="im-note">
    BBL dan SI tidak tumpang tindih &mdash; tiap anak masuk tepat satu kelompok, jadi BBL + SI =
    seluruh kelahiran periode. Anak di luar ketiga kohort ini tidak muncul di bagian statistik mana
    pun, sehingga jumlah di halaman ini tidak sama dengan jumlah anak terdaftar.
    Cakupan kini dihitung terhadap kohort tahunan, termasuk anak yang jadwal vaksinnya belum tiba.
    Karena itu, cakupan antigen dan IDL tahun berjalan dapat berada di bawah target dan meningkat
    seiring anak mencapai usia pemberian serta menerima vaksin. Penurunan angka setelah perubahan
    metode ini tidak serta-merta berarti penurunan kinerja layanan.
</p>

{{-- Data capaian: performa per kelompok imunisasi wajib, terpisah jelas dari data sasaran di atas --}}
<div class="im-h"><h2>Data capaian</h2><small>% kelengkapan terhadap target 95% &middot; penyebut = kohort {{ $tahun }} (IDL atas SI, IBL atas Baduta)</small></div>
<div class="im-cards im-cards--3">
    <div class="im-card">
        <div class="im-card__lbl" style="display:flex; align-items:baseline; justify-content:space-between;">
            <span>IDL &middot; SI kohort {{ $tahun }}</span>
            <span class="im-badge {{ $persenIdl >= 95 ? 'im-badge--ok' : 'im-badge--warn' }}">{{ $persenIdl >= 95 ? 'On track' : 'Tertinggal' }}</span>
        </div>
        <div class="im-card__val im-num {{ $bucketOf($persenIdl) }}">{{ $persenIdl }}%</div>
        <div class="im-bar"><div class="im-bar__fill {{ $bucketOf($persenIdl) }}" style="width:{{ min(100,$persenIdl) }}%"></div></div>
        <div class="im-card__sub">{{ number_format($idlLengkap) }} dari {{ number_format($totalAnak) }} anak kelompok SI</div>
        <div class="im-card__foot" style="color:var(--faint); font-size:.76rem;">Imunisasi Dasar Lengkap</div>
    </div>
    <div class="im-card">
        <div class="im-card__lbl" style="display:flex; align-items:baseline; justify-content:space-between;">
            <span>IBL &middot; Baduta kohort {{ $tahun - 1 }}</span>
            <span class="im-badge {{ $iblCoverage['persen'] >= 95 ? 'im-badge--ok' : 'im-badge--warn' }}">{{ $iblCoverage['persen'] >= 95 ? 'On track' : 'Tertinggal' }}</span>
        </div>
        <div class="im-card__val im-num {{ $bucketOf($iblCoverage['persen']) }}">{{ $iblCoverage['persen'] }}%</div>
        <div class="im-bar"><div class="im-bar__fill {{ $bucketOf($iblCoverage['persen']) }}" style="width:{{ min(100,$iblCoverage['persen']) }}%"></div></div>
        <div class="im-card__sub">{{ number_format($iblCoverage['ibl_lengkap']) }} dari {{ number_format($iblCoverage['total']) }} anak kelompok Baduta</div>
        <div class="im-card__foot" style="color:var(--faint); font-size:.76rem;">Booster DPT-HB-Hib + PCV + Campak-Rubela</div>
    </div>
    <div class="im-card">
        <div class="im-card__lbl" style="display:flex; align-items:baseline; justify-content:space-between;">
            <span>Kejar &middot; IDL/IBL</span>
            <span class="im-badge {{ $butuhKejar > 0 ? 'im-badge--warn' : 'im-badge--ok' }}">{{ $butuhKejar > 0 ? 'Perlu tindak lanjut' : 'Tidak ada' }}</span>
        </div>
        <div class="im-card__val im-num {{ $butuhKejar > 0 ? 'warn' : 'ok' }}">{{ number_format($butuhKejar) }}</div>
        <div class="im-card__sub">menurut tanggal hari ini &middot; <strong>tidak mengikuti tahun sasaran</strong></div>
        <div class="im-card__foot">
            <a href="{{ route('admin.earlyWarning') }}" class="im-card__link">Lihat daftar anaknya di Proyeksi &rarr;</a>
        </div>
    </div>
</div>

{{-- Kohort per kecamatan & kelurahan --}}
<div class="im-h"><h2>Kohort per kecamatan &amp; kelurahan</h2><small>Sasaran BBL, SI, dan Baduta tahun {{ $tahun }} (bukan cakupan) &middot; klik kecamatan untuk detail kelurahan</small></div>
<div class="im-panel im-panel--flush" style="margin-bottom:1.5rem;">
    @if(count($kohortWilayah) > 0)
    <div class="im-kohort-head">
        <h2>Wilayah</h2>
        <span style="font-size:.78rem; color:var(--faint);">RT &middot; BBL &middot; SI &middot; Baduta &middot; Total &middot; % Kota</span>
    </div>
    @foreach($kohortWilayah as $kec)
    <details class="im-kec">
        <summary>
            {{ $kec['nama'] }}
            <span class="im-kec__meta">{{ $kec['jumlah_rt'] }} RT</span>
            <span class="im-kec__stats im-num">
                <span>{{ number_format($kec['bbl']) }} BBL</span>
                <span>{{ number_format($kec['si']) }} SI</span>
                <span>{{ number_format($kec['baduta']) }} baduta</span>
                <strong>{{ number_format($kec['total']) }} total</strong>
                <span style="color:var(--faint);">{{ $kec['persen_kota'] }}% kota</span>
            </span>
        </summary>
        <table>
            <thead>
                <tr>
                    <th>Kelurahan</th>
                    <th class="r" style="width:70px;">RT</th>
                    <th class="r" style="width:80px;">BBL</th>
                    <th class="r" style="width:80px;">SI</th>
                    <th class="r" style="width:90px;">Baduta</th>
                    <th class="r" style="width:90px;">Total</th>
                    <th class="bar">Distribusi</th>
                    <th class="r" style="width:60px;">% Kota</th>
                </tr>
            </thead>
            <tbody>
            @foreach($kec['kelurahan'] as $kel)
            <tr>
                <td class="im-kel-nama">{{ $kel['nama'] }}</td>
                <td class="r" style="width:70px;">{{ $kel['jumlah_rt'] }} RT</td>
                <td class="r" style="width:80px;">{{ number_format($kel['bbl']) }} BBL</td>
                <td class="r" style="width:80px;">{{ number_format($kel['si']) }} SI</td>
                <td class="r" style="width:100px;">{{ number_format($kel['baduta']) }} baduta</td>
                <td class="r" style="width:90px;"><strong>{{ number_format($kel['total']) }}</strong></td>
                <td class="bar">
                    <div class="im-bar"><div class="im-bar__fill neutral" style="width:{{ min(100, $kel['persen_kota']) }}%"></div></div>
                </td>
                <td class="r" style="width:60px; color:var(--faint);">{{ $kel['persen_kota'] }}%</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </details>
    @endforeach
    @else
    <div class="im-empty"><strong>Tidak ada data</strong>Tidak ada anak pada filter wilayah ini.</div>
    @endif
</div>

{{-- Sasaran per kecamatan --}}
<div class="im-h"><h2>Sasaran per kecamatan</h2><small>Porsi sasaran BBL, SI, dan Baduta tahun {{ $tahun }} &mdash; bukan capaian</small></div>
<div class="im-panel" style="margin-bottom:1.5rem;">
    @forelse($kohortWilayah as $kec)
    <div style="margin-bottom:.85rem;">
        <div style="display:flex; justify-content:space-between; font-size:.86rem; font-weight:600; margin-bottom:.3rem;">
            <span>{{ $kec['nama'] }}</span>
            <span class="im-num">{{ number_format($kec['total']) }} anak &middot; {{ $kec['persen_kota'] }}% kota</span>
        </div>
        <div class="im-bar" style="height:9px;"><div class="im-bar__fill neutral" style="width:{{ min(100,$kec['persen_kota']) }}%"></div></div>
    </div>
    @empty
    <div class="im-empty"><strong>Tidak ada data</strong></div>
    @endforelse
</div>

{{-- Rincian per puskesmas --}}
<div class="im-h"><h2>Rincian per puskesmas</h2><small>Sasaran = kohort SI {{ $tahun }} &middot; wilayah kerja via catchment kelurahan</small></div>
<div class="im-panel im-panel--flush" style="margin-bottom:1.5rem;">
    <div class="im-scroll" style="overflow-x:auto;">
        <table class="im-table">
            <thead><tr>
                <th>Puskesmas</th><th class="r">Sasaran</th><th class="r">Capaian IDL</th>
                <th style="width:190px;">% terhadap target</th><th class="r">DO rate</th><th class="r">Status</th>
            </tr></thead>
            <tbody>
            @forelse($rincianPuskesmas as $pkm)
            <tr>
                <td style="font-weight:600;">{{ $pkm['nama'] }}</td>
                <td class="r">{{ number_format($pkm['sasaran']) }}</td>
                <td class="r">{{ number_format($pkm['capaian_idl']) }}</td>
                <td>
                    <div style="display:flex; align-items:center; gap:.6rem;">
                        <div class="im-bar" style="flex:1;"><div class="im-bar__fill {{ $bucketOf($pkm['persen']) }}" style="width:{{ min(100,$pkm['persen']) }}%"></div></div>
                        <span class="im-num" style="width:44px; text-align:right;">{{ $pkm['persen'] }}%</span>
                    </div>
                </td>
                <td class="r im-num" style="font-weight:700; color:{{ $pkm['do_rate'] > 5 ? 'var(--red-d)' : 'var(--ink)' }};">{{ $pkm['do_rate'] }}%</td>
                <td class="r">
                    <span class="im-badge {{ $pkm['status'] === 'tertinggal' ? 'im-badge--warn' : ($pkm['status'] === 'perhatian' ? 'im-badge--warn' : 'im-badge--ok') }}">
                        {{ ['on_track' => 'On track', 'perhatian' => 'Perhatian', 'tertinggal' => 'Tertinggal', 'tidak_ada_data' => 'Tanpa data'][$pkm['status']] ?? $pkm['status'] }}
                    </span>
                </td>
            </tr>
            @empty
            <tr><td colspan="6"><div class="im-empty"><strong>Tidak ada data</strong></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Cakupan per antigen: section wajib ke-3, urut dari yang paling tertinggal --}}
<div class="im-h"><h2>Cakupan per antigen</h2><small>% terhadap kelompok kohort sesuai jendela antigen &middot; diurutkan dari yang paling tertinggal &middot; garis putus-putus = target 95%</small></div>
<div class="im-panel" style="margin-bottom:1.5rem;">
    @php $antigenUrut = collect($cakupanAntigen)->sortBy('persen')->values(); @endphp
    @if($antigenUrut->count() > 0)
    <div class="im-antigen">
        @foreach($antigenUrut as $ag)
        <div class="im-antigen__row" title="{{ $ag['jumlah_sudah'] }} dari {{ $ag['jumlah_penyebut'] }} anak kelompok {{ $ag['kelompok'] }} kohort {{ $tahun }}">
            <span class="im-antigen__lbl">{{ $ag['nama'] }}</span>
            <div class="im-antigen__track">
                <div class="im-bar__fill {{ $bucketOf($ag['persen']) }}" style="height:100%; width:{{ min(100,$ag['persen']) }}%"></div>
                <div class="im-antigen__target"></div>
            </div>
            <span class="im-antigen__val im-num">{{ $ag['persen'] }}% <small>&middot; {{ number_format($ag['jumlah_sudah']) }}/{{ number_format($ag['jumlah_penyebut']) }}</small></span>
        </div>
        @endforeach
    </div>
    <div class="im-legend" style="margin-top:1rem; padding-top:.8rem; border-top:1px solid var(--line);">
        <span><i style="background:var(--green)"></i>&ge;95% sesuai target</span>
        <span><i style="background:oklch(0.72 0.14 80)"></i>60&ndash;94%</span>
        <span><i style="background:oklch(0.62 0.16 30)"></i>&lt;60% kritis</span>
    </div>
    @else
    <div class="im-empty"><strong>Tidak ada data</strong></div>
    @endif
</div>
@if(!empty($antigenDilewati))
<p class="im-note">
    {{ count($antigenDilewati) }} antigen dilewatkan dari cakupan di atas karena batas usia
    pemberian belum diisi di master vaksin, sehingga tidak bisa ditentukan kelompok kohort
    penyebutnya: {{ implode(', ', $antigenDilewati) }}. Lengkapi
    <em>usia pemberian maksimal</em> antigen tersebut di Master Data Vaksin agar ikut
    dihitung.
</p>
@endif

{{-- Funnel dosis: pelengkap cakupan per antigen, fokus ke rangkaian dosis kunci IDL --}}
<div class="im-h"><h2>Funnel dosis</h2><small>Kohort SI {{ $tahun }} &middot; jumlah anak yang sudah menerima tiap dosis, berurutan sesuai jadwal</small></div>
<div class="im-panel" style="margin-bottom:1.5rem;">
    @if(count($funnel) > 0)
    @php $funnelBase = max(1, $funnel[0]['jumlah']); @endphp
    @foreach($funnel as $tahap)
    @php $retensi = round($tahap['jumlah'] / $funnelBase * 100, 1); @endphp
    <div class="im-funnel__row">
        <span class="im-funnel__lbl">{{ $tahap['label'] }}</span>
        <div class="im-funnel__track"><div class="im-funnel__fill" style="width:{{ min(100, $retensi) }}%"></div></div>
        <span class="im-funnel__val im-num">{{ number_format($tahap['jumlah']) }} <small style="color:var(--faint); font-weight:400;">&middot; {{ $retensi }}%</small></span>
    </div>
    @endforeach
    <div style="margin-top:.6rem; font-size:.74rem; color:var(--faint);">% dihitung relatif terhadap tahap pertama ({{ $funnel[0]['label'] }} = 100%), untuk melihat di mana populasi paling banyak berhenti &mdash; ini gambaran drop-out per dosis, lebih rinci daripada satu angka drop-out tunggal.</div>
    @else
    <div class="im-empty"><strong>Tidak ada data</strong></div>
    @endif
</div>

{{-- Sasaran hari ini & besok: murni jadwal usia, bukan sesi posyandu/konfirmasi kehadiran --}}
<div class="im-h" style="justify-content:space-between; display:flex; width:100%;">
    <div style="display:flex; align-items:baseline; gap:.6rem;">
        <h2>Sasaran hari ini &amp; besok</h2>
        <small>jadwal menurut tanggal hari ini &middot; <strong>tidak mengikuti tahun sasaran</strong></small>
    </div>
</div>
<div class="im-panel im-panel--flush" style="margin-bottom:1.5rem;">
    <div style="display:flex; gap:.4rem; padding:.9rem 1.4rem; background:oklch(0.97 0.012 145); border-bottom:1px solid var(--line);">
        <button type="button" id="btnHariIni" class="im-daytab im-daytab--on">Hari ini &middot; {{ count($sasaranHarian['hari_ini']) }} anak</button>
        <button type="button" id="btnBesok" class="im-daytab">Besok &middot; {{ count($sasaranHarian['besok']) }} anak</button>
    </div>

    @foreach(['hari_ini' => 'tabelHariIni', 'besok' => 'tabelBesok'] as $key => $domId)
    <div id="{{ $domId }}" {{ $key === 'besok' ? 'hidden' : '' }}>
        @if(count($sasaranHarian[$key]) > 0)
        <div class="im-scroll" style="overflow-x:auto;">
            <table class="im-table">
                <thead><tr>
                    <th>Nama anak</th><th>Usia</th><th>Kelurahan / RT</th><th>Posyandu</th><th>Antigen jatuh tempo</th>
                </tr></thead>
                <tbody>
                @foreach($sasaranHarian[$key] as $baris)
                @php
                    $anak = $baris['anak'];
                    $usiaHari = (int) \Carbon\Carbon::parse($anak->tgl_lahir)->diffInDays(now());
                    $usiaStr = $usiaHari < 60 ? $usiaHari . ' hari' : (new DateTime($anak->tgl_lahir))->diff(new DateTime())->format('%y th %m bl');
                @endphp
                <tr>
                    <td style="font-weight:600;">{{ $anak->nama }}</td>
                    <td class="im-num" style="white-space:nowrap;">{{ $usiaStr }}</td>
                    <td style="color:var(--muted); font-size:.82rem;">{{ $anak->kel->name ?? '—' }} @if($anak->rt) / RT {{ $anak->rt->name }} @endif</td>
                    <td style="color:var(--muted); font-size:.82rem;">{{ $anak->posyandu->name ?? '—' }}</td>
                    <td>
                        <div style="display:flex; gap:.35rem; flex-wrap:wrap;">
                            @foreach($baris['antigen'] as $ag)
                            <span class="im-chip {{ $ag['status'] === 'sudah' ? 'im-chip--ok' : 'im-chip--belum' }}">{{ $ag['nama'] }} &middot; {{ $ag['status'] === 'sudah' ? 'Sudah' : 'Belum' }}</span>
                            @endforeach
                        </div>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="im-empty"><strong>Tidak ada anak jatuh tempo</strong>Tidak ada antigen yang jatuh tempo pada {{ $key === 'hari_ini' ? 'hari ini' : 'besok' }} untuk filter wilayah ini.</div>
        @endif
    </div>
    @endforeach
</div>

{{-- Analitik: alasan + korelasi (dipertahankan dari dashboard lama) --}}
<div class="im-grid2">
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
<script>
(function () {
    var URL_KEL_BY_KEC = '{{ url("admin/get-kel-dasar-anak") }}';
    var URL_RT_BY_KEL  = '{{ url("admin/get-rt-by-kel-anak") }}';
    var SELECTED_RT     = '{{ $filters['id_rt'] ?? '' }}';
    var $kec = $('#filterKec'), $kel = $('#filterKel'), $rt = $('#filterRt');

    function fillSelect($sel, data, placeholder, selected) {
        $sel.empty().append($('<option>', { value: '', text: placeholder }));
        $.each(data, function (id, name) {
            $sel.append($('<option>', { value: id, text: name, selected: String(id) === String(selected) }));
        });
    }

    function loadRt(kelId, selected) {
        if (!kelId) { fillSelect($rt, {}, 'Semua RT'); return; }
        $.getJSON(URL_RT_BY_KEL + '/' + kelId, function (d) { fillSelect($rt, d, 'Semua RT', selected); });
    }

    // Kelurahan awal sudah dirender server-side (semua kelurahan) — saring ke kecamatan terpilih saja.
    // Catatan: jQuery `:hidden` TIDAK bisa dipakai pada <option> (selalu terhitung hidden
    // karena tak punya box model saat select tertutup) — makanya validitas dicek dari
    // data-kec langsung, bukan dari visibility, supaya pilihan awal dari query string
    // (mis. hasil reload filter) tidak ikut ke-reset walau kecamatannya masih cocok.
    function filterKelOptionsByKec(kecId) {
        var currentVal = $kel.val();
        var stillValid = false;
        $kel.find('option[data-kec]').each(function () {
            var match = !kecId || String($(this).data('kec')) === String(kecId);
            $(this).toggle(match);
            if (match && this.value === currentVal) { stillValid = true; }
        });
        if (currentVal && !stillValid) { $kel.val(''); }
    }

    $kec.on('change', function () {
        filterKelOptionsByKec(this.value);
        $kel.val('');
        fillSelect($rt, {}, 'Semua RT');
    });

    $kel.on('change', function () { loadRt(this.value, ''); });

    // State awal (reload dengan filter aktif dari query string).
    if ($kec.val()) { filterKelOptionsByKec($kec.val()); }
    if ($kel.val()) { loadRt($kel.val(), SELECTED_RT); }
})();
</script>

<script>
(function () {
    var btnHariIni = document.getElementById('btnHariIni');
    var btnBesok = document.getElementById('btnBesok');
    var tabelHariIni = document.getElementById('tabelHariIni');
    var tabelBesok = document.getElementById('tabelBesok');
    if (!btnHariIni || !btnBesok) return;

    btnHariIni.addEventListener('click', function () {
        tabelHariIni.hidden = false;
        tabelBesok.hidden = true;
        btnHariIni.classList.add('im-daytab--on');
        btnBesok.classList.remove('im-daytab--on');
    });
    btnBesok.addEventListener('click', function () {
        tabelBesok.hidden = false;
        tabelHariIni.hidden = true;
        btnBesok.classList.add('im-daytab--on');
        btnHariIni.classList.remove('im-daytab--on');
    });
})();
</script>

@php $needChart = count($korelasiData) > 0 || count($alasanTidakImunisasi) > 0; @endphp
@if($needChart)
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
