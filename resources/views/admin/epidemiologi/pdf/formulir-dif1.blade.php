<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>DIF-1 - {{ $case->no_registrasi }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 8.5pt; color: #000; line-height: 1.25; }
        .page { padding: 14px 20px; }
        .header { position: relative; margin-bottom: 6px; }
        .header .logo { position: absolute; top: 0; left: 0; width: 100px; }
        .header .code { position: absolute; top: 4px; right: 0; font-size: 10pt; font-weight: bold; }
        .header .title { text-align: center; font-size: 11pt; font-weight: bold; margin: 18px 0 6px; }

        table { width: 100%; border-collapse: collapse; }
        .data-table { border: 1px solid #000; }
        .data-table td, .data-table th { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
        .section-header { background-color: #d9d9d9; font-weight: bold; padding: 3px 5px; border: 1px solid #000; }
        .lbl { background-color: #fafafa; }
        .cb { display: inline-block; width: 10px; height: 10px; border: 1px solid #000; text-align: center;
              font-size: 8pt; line-height: 10px; margin-right: 2px; vertical-align: middle; font-family: 'DejaVu Sans', sans-serif; }
        .cb-checked { background-color: #000; color: #fff; }
        .muted { color: #888; }
    </style>
</head>
<body>
@php
    $cb  = fn($v) => $v ? '<span class="cb cb-checked">&#10003;</span>' : '<span class="cb"></span>';
    $fmt = fn($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d-M-Y') : '';
    $logoPath = public_path('images/logo-kemenkes.png');
    $jk = strtoupper(substr((string) $case->jenis_kelamin, 0, 1));
    $umurTahun = $case->tanggal_lahir ? $case->tanggal_lahir->age : null;
    $umurBulan = $case->tanggal_lahir ? (int) $case->tanggal_lahir->diffInMonths(now()) % 12 : null;
    $spesimen  = $case->spesimen ?? collect();
    $sp1 = $spesimen->get(0);
    $jenisSp = strtolower($sp1?->jenis_spesimen ?? '');
    $isRS = ($case->status_rawat === 'rawat_inap') || (bool) $case->nama_faskes_rawat;
    $riw = $case->riwayat_imunisasi;
    $imBelum  = $riw === 'tidak';
    $imPernah = in_array($riw, ['lengkap', 'tidak_lengkap'], true);
    $imTdkTahu = !$imBelum && !$imPernah;
    $kontakErat = $case->kontakErat ?? collect();
@endphp

{{-- ============ HALAMAN 1 ============ --}}
<div class="page">
    <div class="header">
        @if(file_exists($logoPath))<img src="{{ $logoPath }}" class="logo" alt="Kemenkes">@endif
        <div class="code">Form DIF-1</div>
        <div class="title">Formulir Penyelidikan Epidemiologi Suspek Difteri</div>
    </div>

    <table class="data-table">
        <tr>
            <td class="lbl" style="width:14%">Provinsi</td><td style="width:36%">{{ $case->provinsi ?? 'Kalimantan Timur' }}</td>
            <td class="lbl" style="width:14%">No. EPID</td><td>{{ $case->no_registrasi }}</td>
        </tr>
        <tr>
            <td class="lbl">Kab/Kota</td><td>{{ $case->kab_kota ?? 'Bontang' }}</td>
            <td class="lbl">Puskesmas</td><td>{{ $case->instansi_pelapor ?? '' }}</td>
        </tr>
    </table>

    {{-- I. Identitas Pelapor --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="2" class="section-header">I. Identitas Pelapor</td></tr>
        <tr><td class="lbl" style="width:34%">1. Nama</td><td>{{ $case->nama_pelapor ?? '' }}</td></tr>
        <tr><td class="lbl">2. Nama Kantor &amp; Jabatan</td><td>{{ trim(($case->instansi_pelapor ?? '').' '.($case->jabatan_pelapor ? '— '.$case->jabatan_pelapor : '')) }}</td></tr>
        <tr><td class="lbl">3. Kabupaten/Kota</td><td>{{ $case->kab_kota ?? 'Bontang' }}</td></tr>
        <tr><td class="lbl">4. Provinsi</td><td>{{ $case->provinsi ?? 'Kalimantan Timur' }}</td></tr>
        <tr><td class="lbl">5. Tanggal Terima Laporan</td><td>{{ $fmt($case->tanggal_lapor) }}</td></tr>
        <tr><td class="lbl">6. Tanggal Pelacakan Laporan</td><td>{{ $fmt($case->tanggal_penyelidikan ?? null) }}</td></tr>
    </table>

    {{-- II. Identitas Penderita --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="4" class="section-header">II. Identitas Penderita</td></tr>
        <tr><td class="lbl" style="width:22%">1. Nama</td><td colspan="3">{{ $case->nama_lengkap }}</td></tr>
        <tr><td class="lbl">2. Nama Orang Tua/KK</td><td colspan="3">{{ $case->nama_orang_tua ?? '' }}</td></tr>
        <tr>
            <td class="lbl">3. Jenis Kelamin</td>
            <td style="width:28%">{!! $cb($jk === 'L') !!} L &nbsp; {!! $cb($jk === 'P') !!} P</td>
            <td class="lbl" style="width:12%">Tgl. Lahir</td><td>{{ $fmt($case->tanggal_lahir) }}</td>
        </tr>
        <tr>
            <td class="lbl">4. Umur</td>
            <td>{{ $umurTahun !== null ? $umurTahun.' tahun' : '' }} {{ $umurBulan !== null ? $umurBulan.' bulan' : '' }}</td>
            <td class="lbl">5/6. BB / TB</td><td>{{ $case->berat_badan ?? '' }} <span class="muted">Kg</span> / {{ $case->tinggi_badan ?? '' }} <span class="muted">Cm</span></td>
        </tr>
        <tr><td class="lbl">8. Alamat Lengkap</td><td colspan="3">{{ $case->alamat_lengkap }}</td></tr>
        <tr>
            <td class="lbl">9. Desa/Kelurahan</td><td>{{ $case->kelurahan->name ?? '' }}</td>
            <td class="lbl">Kecamatan</td><td>{{ $case->kecamatan->name ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">11. Kabupaten/Kota</td><td>{{ $case->kab_kota ?? 'Bontang' }}</td>
            <td class="lbl">Provinsi</td><td>{{ $case->provinsi ?? 'Kalimantan Timur' }}</td>
        </tr>
        <tr>
            <td class="lbl">12. Tel/HP</td><td>{{ $case->no_telepon ?? '' }}</td>
            <td class="lbl">13. Pekerjaan</td><td>{{ $case->pekerjaan ?? '' }}</td>
        </tr>
        <tr><td class="lbl">15. Wali yang dapat dihubungi</td><td colspan="3">{{ $case->nama_wali ?? '' }}</td></tr>
        <tr><td class="lbl">21. No. Telepon/HP Wali</td><td colspan="3">{{ $case->no_hp_wali ?? '' }}</td></tr>
    </table>

    {{-- III. Riwayat Sakit --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="3" class="section-header">III. Riwayat Sakit</td></tr>
        <tr><td class="lbl" style="width:34%">1. Tanggal mulai sakit (sakit tenggorokan)</td><td colspan="2">{{ $fmt($case->tanggal_onset) }}</td></tr>
        <tr><td class="lbl">2. Keluhan utama yang mendorong berobat</td><td colspan="2">{{ $case->keluhan_utama ?? '' }}</td></tr>
        <tr><td class="lbl" rowspan="6" style="vertical-align:top;">3. Gejala dan Tanda Sakit</td>
            <td>{!! $cb($case->gejala_demam) !!} a) Demam</td><td style="width:24%">Tanggal: {{ $fmt($case->tanggal_demam ?? null) }}</td></tr>
        <tr><td>{!! $cb(false) !!} b) Sakit Tenggorokan</td><td>Tanggal:</td></tr>
        <tr><td>{!! $cb(false) !!} c) Leher Bengkak</td><td>Tanggal:</td></tr>
        <tr><td>{!! $cb($case->gejala_sesak_napas) !!} d) Sesak nafas</td><td>Tanggal:</td></tr>
        <tr><td>{!! $cb(false) !!} e) Pseudomembran</td><td>Tanggal:</td></tr>
        <tr><td colspan="2">f) Gejala lain: {{ $case->gejala_lainnya ?? '' }}</td></tr>
    </table>
</div>

{{-- ============ HALAMAN 2 ============ --}}
<div class="page" style="page-break-before: always;">
    <div style="text-align:right; font-weight:bold; font-size:10pt; margin-bottom:4px;">Form DIF-1 (Hal. 2) &nbsp; No. EPID: {{ $case->no_registrasi }}</div>

    {{-- Status imunisasi & spesimen (lanjutan III) --}}
    <table class="data-table">
        <tr><td class="lbl" style="width:34%">4. Status imunisasi Difteri</td>
            <td colspan="2">{!! $cb($imBelum) !!} Belum Pernah &nbsp; {!! $cb($imPernah) !!} Pernah &nbsp; {!! $cb($imTdkTahu) !!} Tidak tahu</td></tr>
        <tr><td class="lbl">Jika pernah — DPT-HB-Hib 1, 2, 3</td><td colspan="2">Tanggal/tahun: <span class="muted">…… / …… / ……</span></td></tr>
        <tr><td class="lbl">DPT-HB-Hib Booster (18 bln)</td><td colspan="2">Tanggal/tahun: <span class="muted">…… / …… / ……</span></td></tr>
        <tr><td class="lbl">DT kelas 1 &nbsp;|&nbsp; TD kelas 2 &amp; 5</td><td colspan="2">Tanggal terakhir imunisasi: {{ $fmt($case->tanggal_imunisasi_terakhir) }}</td></tr>
        <tr><td class="lbl">Sumber Informasi</td><td colspan="2">{!! $cb(false) !!} KMS &nbsp; {!! $cb(false) !!} Buku KIA &nbsp; {!! $cb(false) !!} Ingatan responden &nbsp; {!! $cb(false) !!} Lain-lain</td></tr>
        <tr><td class="lbl">5. Status Gizi</td><td colspan="2">{!! $cb(false) !!} Buruk &nbsp; {!! $cb(false) !!} Kurang &nbsp; {!! $cb(false) !!} Baik</td></tr>
        <tr><td class="lbl">6. Jenis Spesimen diambil</td>
            <td colspan="2">
                {!! $cb(str_contains($jenisSp, 'tenggorok')) !!} Tenggorokan &nbsp;
                {!! $cb(str_contains($jenisSp, 'hidung')) !!} Hidung &nbsp;
                {!! $cb(str_contains($jenisSp, 'kedua')) !!} Keduanya
            </td></tr>
        <tr><td class="lbl">7. Tanggal pengambilan spesimen</td><td>{{ $fmt($sp1?->tanggal_ambil_spesimen) }}</td><td class="lbl" style="width:20%">No. Kode Spesimen: </td></tr>
        <tr><td class="lbl">8. Tanggal pengiriman spesimen</td><td colspan="2">{{ $fmt($sp1?->tanggal_kirim_sampel) }}</td></tr>
    </table>

    {{-- IV. Riwayat Pengobatan --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="3" class="section-header">IV. Riwayat Pengobatan</td></tr>
        <tr><td class="lbl" style="width:34%" rowspan="5">1. Penderita berobat ke</td>
            <td>{!! $cb($isRS) !!} a. Rumah Sakit</td><td style="width:32%">Tgl: {{ $fmt($case->tanggal_masuk_rawat) }} &nbsp; Tracheostomi: Ya/Tidak</td></tr>
        <tr><td>{!! $cb(!$isRS && $case->status_rawat === 'rawat_jalan') !!} b. Puskesmas</td><td>Tgl:</td></tr>
        <tr><td>{!! $cb(false) !!} c. Dokter Praktek Swasta</td><td>Tgl:</td></tr>
        <tr><td>{!! $cb(false) !!} d. Perawat/Mantri/Bidan</td><td>Tgl:</td></tr>
        <tr><td>{!! $cb(false) !!} e. Tidak Berobat</td><td></td></tr>
        <tr><td class="lbl">2. Diagnosis sebagai suspek difteri</td><td colspan="2">{!! $cb($case->status_kasus !== 'discarded') !!} Ya &nbsp; {!! $cb($case->status_kasus === 'discarded') !!} Tidak</td></tr>
        <tr><td class="lbl">3. Pemberian antibiotik</td><td colspan="2">{!! $cb(false) !!} Ya &nbsp; {!! $cb(false) !!} Tidak &nbsp; Jenis: {{ $case->antibiotik ?? '' }}</td></tr>
        <tr><td class="lbl">4. Pemberian ADS</td><td colspan="2">{!! $cb(false) !!} Ya, Dosis (IU): ……… &nbsp; {!! $cb(false) !!} Tidak, Alasan: ………</td></tr>
        <tr><td class="lbl">6. Kondisi kasus saat ini</td>
            <td colspan="2">
                {!! $cb($case->kondisi_akhir === 'dalam_perawatan') !!} Masih sakit &nbsp;
                {!! $cb($case->kondisi_akhir === 'sembuh') !!} Sembuh &nbsp;
                {!! $cb($case->kondisi_akhir === 'meninggal') !!} Meninggal
                @if(in_array($case->kondisi_akhir, ['sembuh','meninggal'])) &nbsp; Tgl: {{ $fmt($case->tanggal_kondisi_akhir) }} @endif
            </td></tr>
    </table>

    {{-- IV. Riwayat Kontak --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="2" class="section-header">IV. Riwayat Kontak</td></tr>
        <tr><td class="lbl" style="width:55%">1. Dalam 10 hari terakhir sd 2 hari setelah antibiotik, pernah bepergian?</td>
            <td>{!! $cb((bool) $case->riwayat_perjalanan) !!} Pernah — {{ $case->riwayat_perjalanan ?? '' }} &nbsp; {!! $cb(!$case->riwayat_perjalanan) !!} Tidak</td></tr>
        <tr><td class="lbl">2. Pernah berkunjung ke rumah teman/saudara sehat/sakit dengan gejala sama?</td>
            <td>{!! $cb(false) !!} Pernah &nbsp; {!! $cb(false) !!} Tidak pernah &nbsp; {!! $cb(false) !!} Tidak jelas</td></tr>
    </table>
</div>

{{-- ============ HALAMAN 3 ============ --}}
<div class="page" style="page-break-before: always;">
    <div style="text-align:right; font-weight:bold; font-size:10pt; margin-bottom:4px;">Form DIF-1 (Hal. 3) &nbsp; No. EPID: {{ $case->no_registrasi }}</div>
    <div class="section-header" style="margin-bottom:4px;">V. Kontak Kasus</div>
    <p style="font-size:8pt; text-align:justify; margin-bottom:5px;">
        Kontak kasus adalah mereka yang pernah kontak dengan penderita difteri sejak 10 hari sebelum timbul gejala
        sakit tenggorok sampai 2 hari setelah pengobatan (masa penularan): tinggal satu rumah/asrama, tetangga/kerabat/
        pengasuh, teman kelas/bermain/guru, teman kerja, petugas kesehatan yang merawat kasus.
    </p>
    <table class="data-table" style="font-size:8pt;">
        <thead><tr style="background:#f0f0f0; font-weight:bold;">
            <th style="width:4%; text-align:center;">No</th>
            <th style="width:26%">Nama</th>
            <th style="width:8%; text-align:center;">Umur (thn)</th>
            <th style="width:26%">Alamat</th>
            <th style="width:16%">Hub. dgn Kasus</th>
            <th>Jml imunisasi Difteri (DPT-HB-Hib/DT/Td)</th>
        </tr></thead>
        <tbody>
            @forelse($kontakErat as $i => $k)
            <tr>
                <td style="text-align:center;">{{ $i + 1 }}</td>
                <td>{{ $k->nama }}</td>
                <td style="text-align:center;">{{ $k->tanggal_lahir ? (int) $k->tanggal_lahir->diffInYears(now()) : '' }}</td>
                <td>{{ $k->alamat ?? '' }}</td>
                <td>{{ $k->hubungan ?? '' }}</td>
                <td></td>
            </tr>
            @empty
            @for($r = 0; $r < 12; $r++)
            <tr><td style="text-align:center;">{{ $r + 1 }}</td><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
            @endfor
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:12px; text-align:right; font-size:8pt; color:#555;">
        Dicetak: {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp; {{ $case->nama_pelapor ?? '' }}
    </div>
</div>
</body>
</html>
