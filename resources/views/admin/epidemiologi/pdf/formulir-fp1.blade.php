<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>FP-1 - {{ $case->no_registrasi }}</title>
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
        .fill { border-bottom: 1px solid #999; min-height: 12px; }
    </style>
</head>
<body>
@php
    $cb = fn($v) => $v ? '<span class="cb cb-checked">&#10003;</span>' : '<span class="cb"></span>';
    $fmt = fn($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d-M-Y') : '';
    $logoPath = public_path('images/logo-kemenkes.png');
    $jk = strtoupper(substr((string) $case->jenis_kelamin, 0, 1));
    $umurTahun = $case->tanggal_lahir ? $case->tanggal_lahir->age : null;
    $spesimen = $case->spesimen ?? collect();
    $sp1 = $spesimen->get(0);
    $sp2 = $spesimen->get(1);
    $instansiUpper = strtoupper($case->instansi_pelapor ?? '');
    $isRS = str_starts_with($instansiUpper, 'RS') || str_starts_with($instansiUpper, 'RUMAH SAKIT')
            || ($case->petugasInput?->faskes_type === 'rs');
    $meninggal = $case->kondisi_akhir === 'meninggal';
@endphp

{{-- ============ HALAMAN 1 ============ --}}
<div class="page">
    <div class="header">
        @if(file_exists($logoPath))<img src="{{ $logoPath }}" class="logo" alt="Kemenkes">@endif
        <div class="code">FP-1</div>
        <div class="title">Formulir Penyelidikan Epidemiologi Kasus AFP</div>
    </div>

    <table class="data-table">
        <tr>
            <td class="lbl" style="width:16%">Provinsi</td>
            <td style="width:18%">{{ $case->provinsi ?? 'Kalimantan Timur' }}</td>
            <td class="lbl" style="width:14%">Kab/Kota</td>
            <td style="width:18%">{{ $case->kab_kota ?? 'Bontang' }}</td>
            <td class="lbl" style="width:14%">Nomor EPID</td>
            <td>{{ $case->no_registrasi }}</td>
        </tr>
        <tr><td class="lbl">Sumber laporan berasal</td><td colspan="5">{{ $isRS ? 'Rumah Sakit' : 'Puskesmas' }}</td></tr>
        <tr><td class="lbl">Nama instansi pelapor</td><td colspan="5">{{ $case->instansi_pelapor ?? '' }}</td></tr>
        <tr>
            <td class="lbl">Tanggal laporan diterima</td><td colspan="2">{{ $fmt($case->tanggal_lapor) }}</td>
            <td class="lbl">Tanggal Penyelidikan</td><td colspan="2">{{ $fmt($case->tanggal_penyelidikan ?? null) }}</td>
        </tr>
    </table>

    {{-- I. Identitas --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="4" class="section-header">I. Identitas Penderita</td></tr>
        <tr>
            <td class="lbl" style="width:16%">Nama penderita</td>
            <td style="width:44%">{{ $case->nama_lengkap }}</td>
            <td class="lbl" style="width:14%">Jenis kelamin</td>
            <td>{!! $cb($jk === 'L') !!} Laki-laki &nbsp; {!! $cb($jk === 'P') !!} Perempuan</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal lahir</td><td>{{ $fmt($case->tanggal_lahir) }}</td>
            <td class="lbl">Umur</td>
            <td>{{ $umurTahun !== null ? $umurTahun.' tahun' : '' }} <span class="muted">…… bulan …… hari</span></td>
        </tr>
        <tr><td class="lbl">Alamat</td><td colspan="3">{{ $case->alamat_lengkap }}</td></tr>
        <tr>
            <td class="lbl">Kelurahan/desa</td><td>{{ $case->kelurahan->name ?? '' }}</td>
            <td class="lbl">Kecamatan</td><td>{{ $case->kecamatan->name ?? '' }}</td>
        </tr>
        <tr><td class="lbl">Nama orang tua</td><td colspan="3">{{ $case->nama_orang_tua ?? '' }}</td></tr>
    </table>

    {{-- II. Riwayat Sakit --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="4" class="section-header">II. Riwayat Sakit</td></tr>
        <tr>
            <td class="lbl" style="width:34%">Tanggal mulai sakit/gejala awal sebelum lumpuh</td>
            <td style="width:26%">{{ $fmt($case->tanggal_onset) }}</td>
            <td class="lbl" style="width:20%">Tanggal mulai lemah/lumpuh</td>
            <td>{{ $fmt($case->tanggal_lumpuh ?? null) }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal meninggal (bila meninggal)</td>
            <td colspan="3">{{ $meninggal ? $fmt($case->tanggal_kondisi_akhir) : '' }}</td>
        </tr>
        <tr>
            <td class="lbl">Setelah lemah/lumpuh, menjalani pengobatan tradisional/alternatif?</td>
            <td colspan="3">{!! $cb(false) !!} Ya &nbsp; {!! $cb(false) !!} Tidak
                &nbsp;&nbsp; Nama tempat: <span class="muted">………</span> &nbsp; Tanggal: <span class="muted">………</span></td>
        </tr>
        <tr>
            <td class="lbl">Setelah lemah/lumpuh, berobat ke Rumah Sakit?</td>
            <td colspan="3">
                {!! $cb($case->status_rawat === 'rawat_inap' || $case->nama_faskes_rawat) !!} Ya &nbsp;
                {!! $cb(!($case->status_rawat === 'rawat_inap' || $case->nama_faskes_rawat)) !!} Tidak
                &nbsp;&nbsp; Nama RS: {{ $case->nama_faskes_rawat ?? '' }} &nbsp; Tgl berobat: {{ $fmt($case->tanggal_masuk_rawat) }}
            </td>
        </tr>
        <tr>
            <td class="lbl">Diagnosis</td><td>{{ $case->diagnosis ?? '' }}</td>
            <td class="lbl">No. rekam medik</td><td>{{ $case->no_rekam_medik ?? '' }}</td>
        </tr>
        <tr><td colspan="4">
            Kelemahan/kelumpuhan akut (1-14 hari)? {!! $cb(false) !!} Ya {!! $cb(false) !!} Tidak &nbsp;|&nbsp;
            Layuh (flaccid)? {!! $cb(false) !!} Ya {!! $cb(false) !!} Tidak &nbsp;|&nbsp;
            Disebabkan rudapaksa? {!! $cb(false) !!} Ya {!! $cb(false) !!} Tidak
        </td></tr>
    </table>

    {{-- III. Gejala/Tanda --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="4" class="section-header">III. Gejala/Tanda</td></tr>
        <tr>
            <td class="lbl" style="width:40%">Demam sebelum lemah/lumpuh?</td>
            <td colspan="3">{!! $cb($case->gejala_demam) !!} Ya &nbsp; {!! $cb(!$case->gejala_demam) !!} Tidak</td>
        </tr>
        <tr>
            <td class="lbl" style="text-align:center;">Anggota gerak</td>
            <td class="lbl" style="text-align:center;">Kelumpuhan/Kelemahan</td>
            <td class="lbl" style="text-align:center;">Kekuatan Otot (0-5)</td>
            <td class="lbl" style="text-align:center;">Gangguan rasa raba</td>
        </tr>
        @foreach(['Tungkai kanan','Tungkai kiri','Lengan kanan','Lengan kiri'] as $limb)
        <tr>
            <td>{{ $limb }}</td>
            <td style="text-align:center;">{!! $cb(false) !!} Ya &nbsp; {!! $cb(false) !!} Tidak</td>
            <td></td>
            <td style="text-align:center;">{!! $cb(false) !!} Ya &nbsp; {!! $cb(false) !!} Tidak</td>
        </tr>
        @endforeach
        <tr><td colspan="4">Lain-lain (muka, leher, dll): <span class="muted">…………………………</span></td></tr>
    </table>

    {{-- IV. Riwayat Kontak --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="2" class="section-header">IV. Riwayat Kontak</td></tr>
        <tr>
            <td class="lbl" style="width:50%">Dalam 35 hari terakhir, pernah bepergian ke luar kab/prov/negeri?</td>
            <td>{!! $cb((bool) $case->riwayat_perjalanan) !!} Ya — Lokasi: {{ $case->riwayat_perjalanan ?? '' }}
                &nbsp; {!! $cb(!$case->riwayat_perjalanan) !!} Tidak</td>
        </tr>
        <tr>
            <td class="lbl">Dalam 75 hari terakhir, kontak dengan anak yang baru imunisasi polio oral?</td>
            <td>{!! $cb(false) !!} Ya &nbsp; {!! $cb(false) !!} Tidak &nbsp; {!! $cb(false) !!} Tidak tahu</td>
        </tr>
    </table>
</div>

{{-- ============ HALAMAN 2 ============ --}}
<div class="page" style="page-break-before: always;">
    <div style="text-align:right; font-weight:bold; font-size:10pt; margin-bottom:4px;">FP-1 (Hal. 2) &nbsp; No. Epid: {{ $case->no_registrasi }}</div>

    {{-- V. Sanitasi (tidak tercatat di sistem) --}}
    <table class="data-table">
        <tr><td colspan="2" class="section-header">V. Sanitasi Dasar: Jamban dan Pembuangan Tinja</td></tr>
        <tr><td class="lbl" style="width:55%">Memiliki jamban sendiri di rumah?</td><td>{!! $cb(false) !!} Ya &nbsp; {!! $cb(false) !!} Tidak</td></tr>
        <tr><td class="lbl">Jenis jamban</td><td>{!! $cb(false) !!} Leher angsa + septic tank &nbsp; {!! $cb(false) !!} Cemplung &nbsp; {!! $cb(false) !!} Sungai/kebun/kolam &nbsp; {!! $cb(false) !!} Lainnya</td></tr>
        <tr><td class="lbl">Selalu menggunakan jamban untuk BAB?</td><td>{!! $cb(false) !!} Ya, selalu &nbsp; {!! $cb(false) !!} Kadang &nbsp; {!! $cb(false) !!} Tidak</td></tr>
        <tr><td class="lbl">Jamban dilengkapi saluran pembuangan kedap & aman?</td><td>{!! $cb(false) !!} Ya &nbsp; {!! $cb(false) !!} Tidak</td></tr>
        <tr><td class="lbl">Pembuangan diapers (jika masih pakai)</td><td>{!! $cb(false) !!} Sampah tertutup &nbsp; {!! $cb(false) !!} Sungai/kebun &nbsp; {!! $cb(false) !!} Dibakar &nbsp; {!! $cb(false) !!} Lainnya</td></tr>
    </table>

    {{-- VI. Status Imunisasi Polio --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="3" class="section-header">VI. Status Imunisasi Polio</td></tr>
        @foreach(['Imunisasi rutin — OPV','Imunisasi rutin — IPV','Imunisasi rutin — Hexavalen','Imunisasi tambahan — OPV','Imunisasi tambahan — IPV'] as $im)
        <tr>
            <td class="lbl" style="width:28%">{{ $im }}</td>
            <td colspan="2">{!! $cb(false) !!} 1x {!! $cb(false) !!} 2x {!! $cb(false) !!} 3x {!! $cb(false) !!} 4x {!! $cb(false) !!} Belum pernah {!! $cb(false) !!} Tidak tahu</td>
        </tr>
        @endforeach
        <tr>
            <td class="lbl">Tanggal imunisasi polio terakhir</td>
            <td colspan="2">{{ $fmt($case->tanggal_imunisasi_terakhir) }}
                @unless($case->tanggal_imunisasi_terakhir) {!! $cb(false) !!} Tidak tahu @endunless</td>
        </tr>
    </table>

    {{-- VII. Pengumpulan Spesimen --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="4" class="section-header">VII. Pengumpulan Spesimen</td></tr>
        <tr>
            <td class="lbl" style="width:16%"></td>
            <td class="lbl">Tanggal ambil</td>
            <td class="lbl">Tanggal kirim (Kab/Kota ke Prov)</td>
            <td class="lbl">Tanggal kirim (ke Lab)</td>
        </tr>
        <tr>
            <td class="lbl">Spesimen I</td>
            <td>{{ $fmt($sp1?->tanggal_ambil_spesimen) }}</td>
            <td>{{ $fmt($sp1?->tanggal_kirim_sampel) }}</td>
            <td>{{ $fmt($sp1?->tanggal_terima_lab) }}</td>
        </tr>
        <tr>
            <td class="lbl">Spesimen II</td>
            <td>{{ $fmt($sp2?->tanggal_ambil_spesimen) }}</td>
            <td>{{ $fmt($sp2?->tanggal_kirim_sampel) }}</td>
            <td>{{ $fmt($sp2?->tanggal_terima_lab) }}</td>
        </tr>
        <tr><td class="lbl">Alasan tidak diambil spesimen</td><td colspan="3">{{ $case->alasan_spesimen ?? '' }}</td></tr>
    </table>

    {{-- Petugas & Hasil --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr>
            <td class="lbl" style="width:16%">Petugas investigasi</td>
            <td style="width:34%">{{ $case->petugasInput->name ?? ($case->nama_pelapor ?? '') }}</td>
            <td class="lbl" style="width:16%">Hasil pemeriksaan / Diagnosis</td>
            <td>{{ $case->hasil_lab ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanda tangan</td><td style="height:34px;"></td>
            <td class="lbl">Nama dokter</td><td></td>
        </tr>
    </table>

    <div style="margin-top:14px; text-align:right; font-size:8pt; color:#555;">
        Dicetak: {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp; {{ $case->nama_pelapor ?? '' }}
    </div>
</div>
</body>
</html>
