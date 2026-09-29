@extends('admin::layouts.app')
@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
@endpush
@section('title') Dasbor SPM @endsection
@section('title-content') Dasbor SPM @endsection
@section('item') Dashboard @endsection
@section('item-active') SPM @endsection

@section('content')
<style>
    :root {
        --spm-primary: oklch(0.48 0.14 145);
        --spm-bg: #f5f7f8;
        --spm-surface: #ffffff;
        --spm-text: #0f172a;
        --spm-muted: #64748b;
        --spm-border: #e2e8f0;
        --spm-radius: 12px;
        --spm-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.06), 0 1px 2px -1px rgb(0 0 0 / 0.06);
    }
    .spm-page { font-family: 'Barlow', sans-serif; color: var(--spm-text); }
    .spm-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 24px; }
    .spm-header__title { font-size: 1.75rem; font-weight: 800; margin: 0; letter-spacing: -0.02em; }
    .spm-header__sub { font-size: 0.875rem; color: var(--spm-muted); font-weight: 500; margin: 2px 0 0; }
    .spm-filter select { height: 42px; padding: 0 14px; border-radius: var(--spm-radius); border: 1.5px solid var(--spm-border); background: #fff; font-family: 'Barlow', sans-serif; font-weight: 700; font-size: 0.8125rem; }

    .spm-cards { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }
    @media (max-width: 1200px) { .spm-cards { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 700px)  { .spm-cards { grid-template-columns: repeat(2, 1fr); } }
    .spm-card { background: var(--spm-surface); border: 1px solid var(--spm-border); border-radius: var(--spm-radius); box-shadow: var(--spm-shadow); padding: 18px 20px; }
    .spm-card__label { font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--spm-muted); }
    .spm-card__angka { font-size: 1.75rem; font-weight: 800; line-height: 1.1; margin-top: 6px; }
    .spm-card__ket { font-size: 0.75rem; color: var(--spm-muted); margin-top: 4px; }

    .spm-panel { background: var(--spm-surface); border: 1px solid var(--spm-border); border-radius: var(--spm-radius); box-shadow: var(--spm-shadow); overflow: hidden; margin-bottom: 24px; }
    .spm-panel__header { background: linear-gradient(135deg, #047857 0%, var(--spm-primary) 100%); color: #fff; padding: 14px 22px; font-weight: 700; font-size: 1.05rem; }
    .spm-table { width: 100%; border-collapse: collapse; }
    .spm-table th { background: #f8fafc; border-bottom: 2px solid var(--spm-border); color: var(--spm-muted); font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; padding: 12px 14px; white-space: nowrap; }
    .spm-table td { padding: 12px 14px; font-size: 0.8125rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .spm-table tbody tr:nth-child(even) { background: rgba(248, 250, 252, 0.5); }
    .spm-angka { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

    /* Bar + tanda prorata. Tanda digambar dengan ::after supaya SELALU sejajar
       dengan bar-nya sendiri; jangan diganti anotasi canvas. */
    .spm-bar { position: relative; height: 10px; background: #eef2f6; border-radius: 9999px; min-width: 90px; }
    .spm-bar__isi { height: 100%; border-radius: 9999px; }
    .spm-bar__prorata { position: absolute; top: -3px; width: 2px; height: 16px; background: #0f172a; opacity: 0.55; }

    .spm-chip { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 9999px; font-size: 0.6875rem; font-weight: 700; border: 1px solid transparent; }
    .spm-chip.bg-success   { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
    .spm-chip.bg-info      { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .spm-chip.bg-warning   { background: #fffbeb; color: #b45309; border-color: #fde68a; }
    .spm-chip.bg-danger    { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    .spm-chip.bg-secondary { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }

    /* Chart.js dengan maintainAspectRatio:false mengambil tinggi dari SINI,
       bukan dari atribut height pada <canvas>. */
    .spm-kanvas { position: relative; width: 100%; }

    .spm-catatan td { background: #fffdf5; font-size: 0.78rem; color: #78350f; border-top: none; }
    .spm-empty { padding: 48px 24px; text-align: center; color: var(--spm-muted); }
    .spm-empty__judul { font-size: 1.1rem; font-weight: 700; color: var(--spm-text); margin-bottom: 6px; }
</style>

@php
    $angka = fn ($nilai, $desimal = 0) => $nilai === null ? '—' : number_format($nilai, $desimal, ',', '.');
@endphp

<div class="spm-page">
    <div class="spm-header">
        <div>
            <h1 class="spm-header__title">Dasbor SPM {{ $tahun }}</h1>
            <p class="spm-header__sub">Ketercapaian Standar Pelayanan Minimal — sasaran setahun, capaian per triwulan</p>
        </div>
        <form method="GET" action="{{ route('admin.spm.dashboard') }}" class="spm-filter">
            <label for="pilihTahun" style="margin:0 8px 0 0; font-weight:700; font-size:0.8125rem;">Tahun</label>
            <select name="tahun" id="pilihTahun" onchange="this.form.submit()">
                @foreach ($tahunOpsi as $opsi)
                    <option value="{{ $opsi }}" {{ $opsi === $tahun ? 'selected' : '' }}>{{ $opsi }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if (count($baris) === 0)
        <div class="spm-panel">
            <div class="spm-empty">
                <p class="spm-empty__judul">Belum ada kategori SPM yang aktif</p>
                <p>Kategori, sasaran, dan capaian triwulan diisi di Master Data SPM.</p>
                @if (auth()->user()->isSuperAdmin())
                    <a href="{{ route('admin.masterdata.spm.index') }}" style="font-weight:700; color:var(--spm-primary);">
                        Buka Master Data SPM →
                    </a>
                @else
                    <p style="font-size:0.8125rem;">Hubungi admin Dinkes untuk menambahkannya.</p>
                @endif
            </div>
        </div>
    @else
        <div class="spm-cards">
            <div class="spm-card">
                <div class="spm-card__label">Kategori Aktif</div>
                <div class="spm-card__angka">{{ $ringkasan['jumlah_kategori'] }}</div>
                <div class="spm-card__ket">dipantau tahun {{ $tahun }}</div>
            </div>
            <div class="spm-card">
                <div class="spm-card__label">Rata-rata Capaian</div>
                <div class="spm-card__angka">{{ $ringkasan['rata_rata'] === null ? '—' : $angka($ringkasan['rata_rata'], 1) . '%' }}</div>
                <div class="spm-card__ket">
                    dari {{ $ringkasan['dihitung'] }} kategori
                    @if ($ringkasan['belum'] + $ringkasan['tanpa_sasaran'] > 0)
                        · {{ $ringkasan['belum'] }} belum dilaporkan, tidak dihitung
                    @endif
                </div>
            </div>
            <div class="spm-card">
                <div class="spm-card__label">Tercapai</div>
                <div class="spm-card__angka" style="color:#047857;">{{ $ringkasan['tercapai'] }}</div>
                <div class="spm-card__ket">capaian ≥ 100% sasaran</div>
            </div>
            <div class="spm-card">
                <div class="spm-card__label">Tertinggal</div>
                <div class="spm-card__angka" style="color:#b45309;">{{ $ringkasan['tertinggal'] }}</div>
                <div class="spm-card__ket">di bawah laju triwulan</div>
            </div>
            <div class="spm-card">
                <div class="spm-card__label">Belum Dilaporkan</div>
                <div class="spm-card__angka" style="color:#64748b;">{{ $ringkasan['belum'] }}</div>
                <div class="spm-card__ket">tidak sama dengan capaian nol</div>
            </div>
        </div>

        <div class="spm-panel">
            <div class="spm-panel__header">Ketercapaian per Kategori — paling tertinggal di atas</div>
            <div style="padding: 18px 22px;">
                {{-- Tinggi WAJIB di kontainer, bukan di atribut canvas: dengan
                     maintainAspectRatio:false Chart.js mengabaikan atribut height
                     dan mengikuti kontainer, sehingga grafik memanjang tanpa batas. --}}
                <div class="spm-kanvas" style="height: {{ max(180, count($grafik['batang']) * 34) }}px;">
                    <canvas id="spmBatang"></canvas>
                </div>
            </div>
        </div>

        <div class="spm-panel">
            <div class="spm-panel__header">Laju Kumulatif per Triwulan</div>
            <div style="padding: 18px 22px;">
                <div style="margin-bottom: 14px;">
                    <label for="spmPilihKategori" style="font-weight:700; font-size:0.8125rem; margin-right:8px;">Kategori</label>
                    <select id="spmPilihKategori" style="height:38px; padding:0 12px; border-radius:10px; border:1.5px solid var(--spm-border); font-family:'Barlow',sans-serif; font-weight:600;">
                        @foreach ($grafik['garis'] as $g)
                            <option value="{{ $g['id'] }}">{{ $g['nama'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="spm-kanvas" style="height: 300px;">
                    <canvas id="spmGaris"></canvas>
                </div>
                <p style="font-size:0.75rem; color:var(--spm-muted); margin-top:10px;">
                    Garis putus-putus = target penuh tiap triwulan (25/50/75/100% sasaran).
                    Titik kumulatif berhenti di triwulan terakhir yang dilaporkan — triwulan yang belum masuk
                    tidak digambar sebagai nol.
                </p>
            </div>
        </div>

        <div class="spm-panel">
            <div class="spm-panel__header">Rincian per Kategori</div>
            <div class="table-responsive">
                <table class="spm-table">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th class="spm-angka">Sasaran</th>
                            <th class="spm-angka">TW I</th>
                            <th class="spm-angka">TW II</th>
                            <th class="spm-angka">TW III</th>
                            <th class="spm-angka">TW IV</th>
                            <th class="spm-angka">Kumulatif</th>
                            <th style="min-width:120px;">Laju</th>
                            <th class="spm-angka">%</th>
                            <th>Status</th>
                            <th class="spm-angka">Selisih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($baris as $item)
                            @php
                                $kategori = $item['kategori'];
                                $capaian  = $item['capaian'];
                                $status   = $capaian->status();
                                $meta     = $statusMeta[$status];
                                $persen   = $capaian->persen();
                                $lebar    = $persen === null ? 0 : min(100, max(0, $persen));
                                // Tanda prorata hanya bermakna kalau sudah ada triwulan
                                // yang dilaporkan DAN sasarannya lebih dari nol.
                                $prorata  = ($capaian->sasaran() > 0 && $capaian->twTerisi() > 0)
                                    ? min(100, $capaian->twTerisi() * 25)
                                    : null;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $kategori->nama }}</strong>
                                    <div style="font-size:0.72rem; color:var(--spm-muted);">{{ $kategori->satuan }}</div>
                                </td>
                                <td class="spm-angka">{{ $angka($capaian->sasaran(), 0) }}</td>
                                @foreach ([1, 2, 3, 4] as $n)
                                    <td class="spm-angka">{{ $angka($capaian->tw($n), 0) }}</td>
                                @endforeach
                                <td class="spm-angka"><strong>{{ $angka($capaian->kumulatif(), 0) }}</strong></td>
                                <td>
                                    <div class="spm-bar">
                                        <div class="spm-bar__isi" style="width: {{ $lebar }}%; background: {{ $meta['warna'] }};"></div>
                                        @if ($prorata !== null)
                                            <span class="spm-bar__prorata" style="left: {{ $prorata }}%;"
                                                  title="Target s.d. TW {{ $capaian->twTerisi() }}"></span>
                                        @endif
                                    </div>
                                </td>
                                <td class="spm-angka">{{ $persen === null ? '—' : $angka($persen, 1) . '%' }}</td>
                                <td>
                                    <span class="spm-chip {{ $meta['badge'] }}">{{ $meta['label'] }}</span>
                                    @if ($capaian->laporanTertinggal() && $capaian->twKalender() > 0)
                                        <span class="spm-chip bg-secondary" style="margin-top:4px;">
                                            laporan TW {{ $capaian->twKalender() }} belum masuk
                                        </span>
                                    @endif
                                    @foreach ($capaian->twKosong() as $kosong)
                                        <span class="spm-chip bg-secondary" style="margin-top:4px;">TW {{ $kosong }} kosong</span>
                                    @endforeach
                                </td>
                                <td class="spm-angka">{{ $angka($capaian->selisih(), 0) }}</td>
                            </tr>
                            @if (!empty($kategori->catatan))
                                <tr class="spm-catatan">
                                    <td colspan="11"><strong>Catatan:</strong> {{ $kategori->catatan }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

@if (count($baris) > 0)
@section('custom_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    var data = @json($grafik);

    var batang = new Chart(document.getElementById('spmBatang').getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.batang.map(function (d) { return d.nama; }),
            datasets: [{
                label: 'Capaian (%)',
                data: data.batang.map(function (d) { return d.persen === null ? 0 : d.persen; }),
                backgroundColor: data.batang.map(function (d) { return d.warna; }),
                borderRadius: 6,
                barThickness: 18
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            var d = data.batang[ctx.dataIndex];
                            if (d.persen === null) { return 'Belum dilaporkan'; }
                            return d.persen.toFixed(1).replace('.', ',') + '%';
                        }
                    }
                }
            },
            scales: {
                x: { beginAtZero: true, ticks: { callback: function (v) { return v + '%'; } } },
                y: { ticks: { font: { family: 'Barlow', size: 11 } } }
            }
        }
    });

    var ctxGaris = document.getElementById('spmGaris').getContext('2d');
    var garis = null;

    function gambarGaris(id) {
        var pilihan = data.garis.filter(function (g) { return String(g.id) === String(id); })[0];
        if (!pilihan) { return; }

        if (garis) { garis.destroy(); }

        garis = new Chart(ctxGaris, {
            type: 'line',
            data: {
                labels: ['TW I', 'TW II', 'TW III', 'TW IV'],
                datasets: [
                    {
                        label: 'Kumulatif (' + pilihan.satuan + ')',
                        data: pilihan.kumulatif,
                        borderColor: '#047857',
                        backgroundColor: 'rgba(4, 120, 87, 0.12)',
                        tension: 0.25,
                        fill: true,
                        spanGaps: false
                    },
                    {
                        label: 'Target prorata',
                        data: pilihan.prorata,
                        borderColor: '#64748b',
                        borderDash: [6, 4],
                        pointRadius: 0,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { font: { family: 'Barlow' } } } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    var pemilih = document.getElementById('spmPilihKategori');
    if (pemilih) {
        gambarGaris(pemilih.value);
        pemilih.addEventListener('change', function () { gambarGaris(this.value); });
    }
})();
</script>
@endsection
@endif
