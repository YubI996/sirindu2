<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Surveilans PD3I — {{ $tahun }}</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;500;600;700&family=Barlow:wght@400;500;600&display=swap');

* { box-sizing: border-box; }

body {
    font-family: 'Barlow', Arial, sans-serif;
    font-size: 9.5px;
    color: #0d1a0d;
    margin: 0;
    padding: 0;
    background: #fff;
    line-height: 1.45;
}

/* ─── DOCUMENT HEADER ─── */
.doc-header-row {
    display: table;
    width: 100%;
    background: #003d1f;
    color: #fff;
}
.doc-header-brand {
    display: table-cell;
    width: 62%;
    padding: 14px 22px;
    vertical-align: middle;
}
.doc-header-info {
    display: table-cell;
    width: 38%;
    padding: 12px 20px 12px 18px;
    vertical-align: middle;
    text-align: right;
    background: rgba(0,0,0,0.12);
}
.brand-eyebrow {
    font-family: 'Barlow Condensed', 'Arial Narrow', Arial, sans-serif;
    font-size: 7.5px;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.55);
    margin: 0 0 5px;
}
.brand-title {
    font-family: 'Barlow Condensed', 'Arial Narrow', Arial, sans-serif;
    font-size: 18px;
    font-weight: 700;
    letter-spacing: 0.01em;
    color: #fff;
    margin: 0;
    line-height: 1;
}
.brand-sub {
    font-family: 'Barlow Condensed', 'Arial Narrow', Arial, sans-serif;
    font-size: 9.5px;
    font-weight: 500;
    color: rgba(255,255,255,0.65);
    margin: 4px 0 0;
    letter-spacing: 0.03em;
}
.info-date {
    font-family: 'Barlow Condensed', 'Arial Narrow', Arial, sans-serif;
    font-size: 8.5px;
    font-weight: 600;
    color: rgba(255,255,255,0.80);
    margin-bottom: 7px;
    letter-spacing: 0.04em;
}
.info-grid {
    font-size: 7.5px;
    color: rgba(255,255,255,0.60);
    line-height: 1.8;
}
.info-grid strong {
    color: rgba(255,255,255,0.88);
    font-weight: 600;
}

/* ─── Green accent bar ─── */
.accent-bar {
    height: 3px;
    background: #00A651;
    margin-bottom: 18px;
}

/* ─── Content wrapper ─── */
.content { padding: 0 22px 4px; }

/* ─── Sections ─── */
.section { margin-bottom: 20px; page-break-inside: avoid; }

.section-heading {
    margin: 0 0 8px;
    padding-bottom: 5px;
    border-bottom: 1.5px solid #c8dec8;
}
.section-num {
    display: inline-block;
    width: 17px;
    height: 17px;
    background: #00A651;
    color: #fff;
    font-family: 'Barlow Condensed', 'Arial Narrow', Arial, sans-serif;
    font-size: 10px;
    font-weight: 700;
    text-align: center;
    line-height: 17px;
    border-radius: 50%;
    margin-right: 6px;
    vertical-align: middle;
}
.section-label {
    font-family: 'Barlow Condensed', 'Arial Narrow', Arial, sans-serif;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: #003d1f;
    vertical-align: middle;
}

/* ─── Sub-headings ─── */
.sub-title {
    font-family: 'Barlow Condensed', 'Arial Narrow', Arial, sans-serif;
    font-size: 8px;
    font-weight: 700;
    letter-spacing: 0.10em;
    text-transform: uppercase;
    color: #00A651;
    margin: 8px 0 4px;
}

/* ─── Tables ─── */
table { width: 100%; border-collapse: collapse; font-size: 8.5px; }

thead th {
    background: #003d1f;
    color: #fff;
    padding: 4px 7px;
    text-align: left;
    font-family: 'Barlow Condensed', 'Arial Narrow', Arial, sans-serif;
    font-size: 7.5px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
tbody td {
    padding: 3.5px 7px;
    border-bottom: 1px solid #e2eee2;
    color: #1a2e1a;
}
tbody tr:nth-child(even) td { background: #f3faf3; }
tbody tr:last-child td { border-bottom: 2px solid #bcd8bc; }

/* ─── Utilities ─── */
.text-center { text-align: center; }
.text-right  { text-align: right; }
.text-muted  { color: #8faa8f; }
.danger      { color: #b91c1c; font-weight: 700; }

/* ─── Two-column ─── */
.two-col { display: table; width: 100%; }
.col-half {
    display: table-cell;
    width: 50%;
    vertical-align: top;
    padding-right: 10px;
}
.col-half:last-child { padding-right: 0; padding-left: 10px; }

/* ─── Document footer ─── */
.doc-footer {
    margin-top: 18px;
    padding-top: 7px;
    border-top: 1px solid #c8dec8;
    display: table;
    width: 100%;
    font-size: 7.5px;
    color: #5a7a5a;
}
.doc-footer-left  { display: table-cell; text-align: left; }
.doc-footer-right { display: table-cell; text-align: right; opacity: 0.7; }
</style>
</head>
<body>

{{-- ===== DOCUMENT HEADER ===== --}}
<div class="doc-header-row">
    <div class="doc-header-brand">
        <div class="brand-eyebrow">Dinas Kesehatan Kota Bontang &nbsp;&bull;&nbsp; SIRINDU</div>
        <div class="brand-title">Laporan Surveilans PD3I</div>
        <div class="brand-sub">Penyakit yang Dapat Dicegah dengan Imunisasi</div>
    </div>
    <div class="doc-header-info">
        <div class="info-date">{{ now()->format('d M Y') }} &nbsp;&bull;&nbsp; {{ now()->format('H:i') }} WIB</div>
        <div class="info-grid">
            <strong>Tahun:</strong> {{ $tahun }}<br>
            <strong>Penyakit:</strong> {{ $namaJenisKasus ?? 'Semua PD3I' }}<br>
            <strong>Kab/Kota:</strong> {{ $namaKabKota ?? 'Semua Kab/Kota' }}<br>
            <strong>Wilker:</strong> {{ $wilker ?? 'Semua Puskesmas' }}<br>
            <strong>Kelurahan:</strong> {{ $namaKelurahan ?? 'Semua Kelurahan' }}
        </div>
    </div>
</div>
<div class="accent-bar"></div>

<div class="content">

{{-- ===== SECTION 1: KINERJA SURVEILANS ===== --}}
<div class="section">
    <div class="section-heading">
        <span class="section-num">1</span>
        <span class="section-label">Kinerja Surveilans</span>
    </div>

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
            @php $cr = $kinerja['campak_rubella'] ?? [] @endphp
            <tr>
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

    <div class="two-col" style="margin-top:10px;">
        <div class="col-half">
            <p class="sub-title">AFP / Polio</p>
            <table>
                <thead><tr><th>Total AFP</th><th>Terkonfirmasi</th><th class="text-center">Non-Polio AFP Rate</th></tr></thead>
                <tbody>
                    @php $afp = $kinerja['afp'] ?? [] @endphp
                    <tr>
                        <td>{{ $afp['total'] ?? 0 }}</td>
                        <td>{{ $afp['confirmed'] ?? 0 }}</td>
                        <td class="text-center">{{ isset($afp['npafp_rate']) && $afp['npafp_rate'] !== null ? number_format($afp['npafp_rate'], 2, ',', '.') : '–' }}</td>
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
            <p class="sub-title" style="margin-top:8px;">Pertusis</p>
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
    <div class="section-heading">
        <span class="section-num">2</span>
        <span class="section-label">Demografi Kasus</span>
    </div>

    <p class="sub-title">Distribusi Kelompok Umur</p>
    <table>
        <thead>
            <tr>
                <th>Kelompok Umur</th>
                <th class="text-center">Suspek</th>
                <th class="text-center">Confirmed</th>
                <th class="text-center">Discarded</th>
                <th class="text-center">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($demografi['kelompok_umur'] ?? []) as $ku)
            <tr>
                <td>{{ $ku['label'] }}</td>
                <td class="text-center">{{ $ku['suspek'] }}</td>
                <td class="text-center">{{ $ku['confirmed'] }}</td>
                <td class="text-center">{{ $ku['discarded'] }}</td>
                <td class="text-center"><strong>{{ $ku['suspek'] + $ku['confirmed'] + $ku['discarded'] }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="two-col" style="margin-top:10px;">
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
        <div class="col-half">
            <p class="sub-title">Severity &amp; Komplikasi</p>
            @php
                $sev  = $demografi['severity'] ?? [];
                $komp = $sev['komplikasi'] ?? [];
            @endphp
            <table>
                <thead><tr><th>Indikator</th><th class="text-center">Nilai</th></tr></thead>
                <tbody>
                    <tr><td>% Rawat Inap</td><td class="text-center">{{ $sev['pct_rawat_inap'] ?? 0 }}%</td></tr>
                    <tr>
                        <td>Kematian</td>
                        <td class="text-center {{ ($sev['meninggal'] ?? 0) > 0 ? 'danger' : '' }}">{{ $sev['meninggal'] ?? 0 }}</td>
                    </tr>
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
    <div class="section-heading">
        <span class="section-num">3</span>
        <span class="section-label">Tren Laporan</span>
    </div>

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
                <th class="text-center" style="background:#1a4d2a; font-weight:500; font-size:7px; letter-spacing:0.04em;">conf.</th>
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

    @if(!empty($tren['per_faskes']))
    <p class="sub-title" style="margin-top:10px;">Per Faskes Pelapor</p>
    @php
        $faskesGroups = collect($tren['per_faskes'])->groupBy('faskes');
        $bulanLabels  = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
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
                foreach ($rows as $r) {
                    if ($r['bulan'] >= 1 && $r['bulan'] <= 12) {
                        $monthly[$r['bulan']] += $r['jumlah'];
                    }
                }
                $rowTotal = array_sum($monthly);
            @endphp
            <tr>
                <td>{{ $faskes }}</td>
                @foreach(range(1,12) as $m)
                <td class="text-center">@if($monthly[$m]){{ $monthly[$m] }}@else<span class="text-muted">–</span>@endif</td>
                @endforeach
                <td class="text-center"><strong>{{ $rowTotal }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>

{{-- ===== SECTION 4: WILAYAH ===== --}}
<div class="section">
    <div class="section-heading">
        <span class="section-num">4</span>
        <span class="section-label">Distribusi Wilayah</span>
    </div>

    <p class="sub-title">Per Wilker Puskesmas</p>
    <table>
        <thead>
            <tr>
                <th>Wilker Puskesmas</th>
                <th class="text-center">Suspek</th>
                <th class="text-center">Confirmed</th>
                <th class="text-center">Kematian</th>
            </tr>
        </thead>
        <tbody>
            @forelse($wilayah['per_puskesmas'] ?? [] as $row)
            <tr>
                <td>{{ $row['wilker'] }}</td>
                <td class="text-center">{{ $row['suspek'] }}</td>
                <td class="text-center">{{ $row['confirmed'] }}</td>
                <td class="text-center {{ $row['meninggal'] > 0 ? 'danger' : '' }}">{{ $row['meninggal'] }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="two-col" style="margin-top:10px;">
        <div class="col-half">
            <p class="sub-title">Per Kecamatan</p>
            <table>
                <thead>
                    <tr>
                        <th>Kecamatan</th>
                        <th class="text-center">Suspek</th>
                        <th class="text-center">Confirmed</th>
                        <th class="text-center">Meninggal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($wilayah['per_kecamatan'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['kecamatan'] }}</td>
                        <td class="text-center">{{ $row['suspek'] }}</td>
                        <td class="text-center">{{ $row['confirmed'] }}</td>
                        <td class="text-center {{ $row['meninggal'] > 0 ? 'danger' : '' }}">{{ $row['meninggal'] }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted">Tidak ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="col-half">
            <p class="sub-title">Per Kelurahan</p>
            <table>
                <thead>
                    <tr>
                        <th>Kelurahan</th>
                        <th>Kecamatan</th>
                        <th class="text-center">Susp</th>
                        <th class="text-center">Conf</th>
                        <th class="text-center">Mngl</th>
                    </tr>
                </thead>
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
                    <tr><td colspan="5" class="text-center text-muted">Tidak ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="doc-footer">
    <div class="doc-footer-left">
        <strong>SIRINDU</strong> &mdash; Sistem Informasi Realtime Reporting Terpadu &nbsp;&bull;&nbsp; Dinas Kesehatan Kota Bontang
    </div>
    <div class="doc-footer-right">
        Tahun {{ $tahun }} &bull; {{ $namaJenisKasus ?? 'Semua PD3I' }} &bull; {{ $namaKabKota ?? 'Semua Kab/Kota' }} &bull; {{ $wilker ?? 'Semua Wilker' }} &bull; {{ $namaKelurahan ?? 'Semua Kelurahan' }}
    </div>
</div>

</div>{{-- end .content --}}

</body>
</html>
