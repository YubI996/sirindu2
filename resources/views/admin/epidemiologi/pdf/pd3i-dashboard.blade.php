<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Dashboard Surveilans PD3I - {{ $tahun }}</title>
<style>
    body { font-family: Arial, sans-serif; font-size: 10px; color: #1f2937; margin: 0; padding: 0; }
    .page-header { background: #1e40af; color: #fff; padding: 12px 20px; margin-bottom: 16px; }
    .page-header h1 { margin: 0; font-size: 14px; }
    .page-header .meta { font-size: 9px; margin-top: 4px; opacity: 0.85; }
    .section { margin-bottom: 18px; page-break-inside: avoid; }
    .section-title { background: #3b82f6; color: #fff; padding: 5px 10px; font-size: 11px; font-weight: bold; margin-bottom: 6px; }
    .sub-title { font-size: 9px; font-weight: bold; margin: 6px 0 3px; color: #374151; }
    table { width: 100%; border-collapse: collapse; font-size: 9px; }
    th { background: #1e40af; color: #fff; padding: 4px 8px; text-align: left; }
    td { padding: 3px 8px; border-bottom: 1px solid #e5e7eb; }
    tr:nth-child(even) td { background: #f0f7ff; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 8px; font-weight: bold; }
    .badge-confirmed { background: #d1fae5; color: #065f46; }
    .badge-suspected { background: #dbeafe; color: #1e40af; }
    .badge-discarded { background: #f3f4f6; color: #6b7280; }
    .footer { margin-top: 14px; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 8px; color: #6b7280; }
    .two-col { display: table; width: 100%; }
    .col-half { display: table-cell; width: 50%; vertical-align: top; padding-right: 8px; }
    .col-half:last-child { padding-right: 0; padding-left: 8px; }
    .danger { color: #be123c; font-weight: bold; }
</style>
</head>
<body>

<div class="page-header">
    <h1>Dashboard Surveilans PD3I — Kota Bontang</h1>
    <div class="meta">
        Tahun: {{ $tahun }} &nbsp;|&nbsp;
        Penyakit: {{ $namaJenisKasus ?? 'Semua PD3I' }} &nbsp;|&nbsp;
        Wilker: {{ $wilker ?? 'Semua Puskesmas' }} &nbsp;|&nbsp;
        Kelurahan: {{ $namaKelurahan ?? 'Semua Kelurahan' }} &nbsp;|&nbsp;
        Digenerate: {{ now()->format('d M Y H:i') }}
    </div>
</div>

{{-- ===== SECTION 1: KINERJA SURVEILANS ===== --}}
<div class="section">
    <div class="section-title">1. Kinerja Surveilans</div>

    {{-- Campak-Rubella --}}
    <p class="sub-title">Campak-Rubella</p>
    <table>
        <thead>
            <tr>
                <th>Suspek</th><th>Conf. Campak</th><th>Conf. Rubella</th>
                <th>Discarded</th><th>Kematian</th>
                <th class="text-center">% Sampel</th>
                <th class="text-center">% Lab Diterima</th>
                <th class="text-center">Positivity Rate</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                @php $cr = $kinerja['campak_rubella'] ?? [] @endphp
                <td>{{ $cr['suspek'] ?? 0 }}</td>
                <td>{{ $cr['confirmed_campak'] ?? 0 }}</td>
                <td>{{ $cr['confirmed_rubella'] ?? 0 }}</td>
                <td>{{ $cr['discarded'] ?? 0 }}</td>
                <td class="{{ ($cr['meninggal'] ?? 0) > 0 ? 'danger' : '' }}">{{ $cr['meninggal'] ?? 0 }}</td>
                <td class="text-center">{{ $cr['pct_sampel'] ?? 0 }}%</td>
                <td class="text-center">{{ $cr['pct_lab_diterima'] ?? 0 }}%</td>
                <td class="text-center">{{ $cr['positivity_rate'] ?? 0 }}%</td>
            </tr>
        </tbody>
    </table>

    {{-- AFP & Difteri-Pertusis --}}
    <div class="two-col" style="margin-top:8px;">
        <div class="col-half">
            <p class="sub-title">AFP / Polio</p>
            <table>
                <thead><tr><th>Total AFP</th><th>Terkonfirmasi</th><th class="text-center">Non-Polio AFP Rate</th></tr></thead>
                <tbody>
                    @php $afp = $kinerja['afp'] ?? [] @endphp
                    <tr>
                        <td>{{ $afp['total'] ?? 0 }}</td>
                        <td>{{ $afp['confirmed'] ?? 0 }}</td>
                        <td class="text-center" style="color:#9ca3af;">–</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="col-half">
            <p class="sub-title">Difteri</p>
            <table>
                <thead><tr><th>Observasi</th><th>Terkonfirmasi</th></tr></thead>
                <tbody>
                    @php $difteri = $kinerja['difteri'] ?? [] @endphp
                    <tr>
                        <td>{{ $difteri['observasi'] ?? 0 }}</td>
                        <td>{{ $difteri['confirmed'] ?? 0 }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="sub-title" style="margin-top:6px;">Pertusis</p>
            <table>
                <thead><tr><th>Suspek</th></tr></thead>
                <tbody>
                    <tr><td>{{ $kinerja['pertusis']['suspek'] ?? 0 }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ===== SECTION 2: DEMOGRAFI ===== --}}
<div class="section">
    <div class="section-title">2. Demografi Kasus</div>

    {{-- Kelompok Umur --}}
    <p class="sub-title">Distribusi Kelompok Umur</p>
    <table>
        <thead><tr><th>Kelompok Umur</th><th class="text-center">Suspek</th><th class="text-center">Confirmed</th><th class="text-center">Discarded</th><th class="text-center">Total</th></tr></thead>
        <tbody>
            @foreach(($demografi['kelompok_umur'] ?? []) as $ku)
            <tr>
                <td>{{ $ku['label'] }}</td>
                <td class="text-center">{{ $ku['suspek'] }}</td>
                <td class="text-center">{{ $ku['confirmed'] }}</td>
                <td class="text-center">{{ $ku['discarded'] }}</td>
                <td class="text-center">{{ $ku['suspek'] + $ku['confirmed'] + $ku['discarded'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="two-col" style="margin-top:8px;">
        {{-- Status Vaksinasi --}}
        <div class="col-half">
            <p class="sub-title">Status Vaksinasi</p>
            @php $sv = $demografi['status_vaksinasi'] ?? [] @endphp
            <table>
                <thead><tr><th>Status</th><th class="text-center">Jumlah</th></tr></thead>
                <tbody>
                    <tr><td>Lengkap</td><td class="text-center">{{ $sv['lengkap'] ?? 0 }}</td></tr>
                    <tr><td>Tidak Lengkap</td><td class="text-center">{{ $sv['tidak_lengkap'] ?? 0 }}</td></tr>
                    <tr><td>Tidak Ada</td><td class="text-center">{{ $sv['tidak_ada'] ?? 0 }}</td></tr>
                    <tr><td>Tidak Tahu</td><td class="text-center">{{ $sv['tidak_tahu'] ?? 0 }}</td></tr>
                </tbody>
            </table>
        </div>
        {{-- Severity --}}
        <div class="col-half">
            <p class="sub-title">Severity & Komplikasi</p>
            @php $sev = $demografi['severity'] ?? [] @endphp
            <table>
                <thead><tr><th>Indikator</th><th class="text-center">Nilai</th></tr></thead>
                <tbody>
                    <tr><td>% Rawat Inap</td><td class="text-center">{{ $sev['pct_rawat_inap'] ?? 0 }}%</td></tr>
                    <tr><td>Kematian</td><td class="text-center {{ ($sev['meninggal'] ?? 0) > 0 ? 'danger' : '' }}">{{ $sev['meninggal'] ?? 0 }}</td></tr>
                    @php $komp = $sev['komplikasi'] ?? [] @endphp
                    <tr><td>Komplikasi Diare</td><td class="text-center">{{ $komp['diare'] ?? 0 }}</td></tr>
                    <tr><td>Pneumonia</td><td class="text-center">{{ $komp['pneumonia'] ?? 0 }}</td></tr>
                    <tr><td>Bronchopneumonia</td><td class="text-center">{{ $komp['bronchopneumonia'] ?? 0 }}</td></tr>
                    <tr><td>Encephalitis</td><td class="text-center">{{ $komp['encephalitis'] ?? 0 }}</td></tr>
                    <tr><td>Kebutaan</td><td class="text-center">{{ $komp['kebutaan'] ?? 0 }}</td></tr>
                    <tr><td>Otitis Media</td><td class="text-center">{{ $komp['otitis_media'] ?? 0 }}</td></tr>
                    <tr><td>Malnutrisi</td><td class="text-center">{{ $komp['malnutrisi'] ?? 0 }}</td></tr>
                    <tr><td>Ulkus Mukosa Mulut</td><td class="text-center">{{ $komp['ulkus_mukosa_mulut'] ?? 0 }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ===== SECTION 3: TREN ===== --}}
<div class="section">
    <div class="section-title">3. Tren Laporan</div>

    {{-- Bulanan --}}
    <p class="sub-title">Tren Bulanan</p>
    <table>
        <thead>
            <tr>
                @foreach(($tren['bulanan'] ?? []) as $b)
                <th class="text-center">{{ $b['label'] }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach(($tren['bulanan'] ?? []) as $b)
                <th class="text-center" style="background:#374151; font-weight:normal; font-size:8px;">conf.</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach(($tren['bulanan'] ?? []) as $b)
                <td class="text-center">{{ $b['total'] }}</td>
                @endforeach
            </tr>
            <tr>
                @foreach(($tren['bulanan'] ?? []) as $b)
                <td class="text-center">{{ $b['confirmed'] }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    {{-- Per Faskes --}}
    @if(!empty($tren['per_faskes']))
    <p class="sub-title" style="margin-top:8px;">Per Faskes Pelapor</p>
    @php
        $faskesGroups = collect($tren['per_faskes'])->groupBy('faskes');
        $bulanLabels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    @endphp
    <table>
        <thead>
            <tr>
                <th>Faskes</th>
                @foreach($bulanLabels as $bl)<th class="text-center">{{ $bl }}</th>@endforeach
                <th class="text-center">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($faskesGroups as $faskes => $rows)
            @php
                $monthly = array_fill(1, 12, 0);
                foreach($rows as $r) { if($r['bulan'] >= 1 && $r['bulan'] <= 12) $monthly[$r['bulan']] += $r['jumlah']; }
                $total = array_sum($monthly);
            @endphp
            <tr>
                <td>{{ $faskes }}</td>
                @foreach(range(1,12) as $m)<td class="text-center">{{ $monthly[$m] ?: '–' }}</td>@endforeach
                <td class="text-center">{{ $total }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>

{{-- ===== SECTION 4: WILAYAH ===== --}}
<div class="section">
    <div class="section-title">4. Distribusi Wilayah</div>

    {{-- Per Puskesmas --}}
    <p class="sub-title">Per Wilker Puskesmas</p>
    <table>
        <thead><tr><th>Wilker Puskesmas</th><th class="text-center">Suspek</th><th class="text-center">Confirmed</th><th class="text-center">Kematian</th></tr></thead>
        <tbody>
            @forelse($wilayah['per_puskesmas'] ?? [] as $row)
            <tr>
                <td>{{ $row['wilker'] }}</td>
                <td class="text-center">{{ $row['suspek'] }}</td>
                <td class="text-center">{{ $row['confirmed'] }}</td>
                <td class="text-center {{ $row['meninggal'] > 0 ? 'danger' : '' }}">{{ $row['meninggal'] }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center" style="color:#9ca3af;">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="two-col" style="margin-top:8px;">
        {{-- Per Kecamatan --}}
        <div class="col-half">
            <p class="sub-title">Per Kecamatan</p>
            <table>
                <thead><tr><th>Kecamatan</th><th class="text-center">Suspek</th><th class="text-center">Confirmed</th><th class="text-center">Meninggal</th></tr></thead>
                <tbody>
                    @forelse($wilayah['per_kecamatan'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['kecamatan'] }}</td>
                        <td class="text-center">{{ $row['suspek'] }}</td>
                        <td class="text-center">{{ $row['confirmed'] }}</td>
                        <td class="text-center {{ $row['meninggal'] > 0 ? 'danger' : '' }}">{{ $row['meninggal'] }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center" style="color:#9ca3af;">Tidak ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{-- Per Kelurahan --}}
        <div class="col-half">
            <p class="sub-title">Per Kelurahan</p>
            <table>
                <thead><tr><th>Kelurahan</th><th>Kecamatan</th><th class="text-center">Susp</th><th class="text-center">Conf</th><th class="text-center">Mngl</th></tr></thead>
                <tbody>
                    @forelse($wilayah['per_kelurahan'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['kelurahan'] }}</td>
                        <td>{{ $row['kecamatan'] }}</td>
                        <td class="text-center">{{ $row['suspek'] }}</td>
                        <td class="text-center">{{ $row['confirmed'] }}</td>
                        <td class="text-center {{ $row['meninggal'] > 0 ? 'danger' : '' }}">{{ $row['meninggal'] }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center" style="color:#9ca3af;">Tidak ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="footer">
    Dokumen ini digenerate otomatis oleh SIRINDU — Sistem Informasi Realtime Reporting Terpadu &nbsp;|&nbsp;
    Filter aktif: Tahun {{ $tahun }}, Penyakit: {{ $namaJenisKasus ?? 'Semua' }}, Wilker: {{ $wilker ?? 'Semua' }}, Kelurahan: {{ $namaKelurahan ?? 'Semua' }}
</div>

</body>
</html>
