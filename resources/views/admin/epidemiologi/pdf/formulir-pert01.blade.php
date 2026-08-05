<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>PERT-01 - {{ $case->no_registrasi }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; line-height: 1.3; }
        .page { padding: 18px 22px; }
        .page + .page { page-break-before: always; }

        .doc-code { font-weight: bold; font-size: 9pt; }
        .doc-title { text-align: center; font-weight: bold; font-size: 11pt; margin: 10px 0 12px; }

        table { width: 100%; border-collapse: collapse; }
        .t { border: 1px solid #000; }
        .t td, .t th { border: 1px solid #000; padding: 3px 6px; vertical-align: middle; }
        .lbl { font-weight: bold; }
        .sh { font-weight: bold; text-align: center; padding: 3px; border: 1px solid #000; }
        .sh-orange { background: #f4b084; } .sh-blue { background: #bdd7ee; }
        .sh-green  { background: #c6efce; } .sh-yellow { background: #ffe699; }
        .sh-slate  { background: #b4c6e7; } .sh-lblue { background: #ddebf7; }

        .rb { display: inline-block; width: 11px; height: 11px; border: 1px solid #000; border-radius: 50%;
              vertical-align: middle; margin-right: 2px; }
        .rb-on { background: #000; }
        .cb { display: inline-block; width: 11px; height: 11px; border: 1px solid #000; vertical-align: middle;
              text-align: center; line-height: 11px; font-size: 8pt; font-family: 'DejaVu Sans', sans-serif; margin-right: 2px; }
        .cb-on { background: #000; color: #fff; }
        .muted { color: #888; }
    </style>
</head>
<body>
@php
    $rb = fn($v) => $v ? '<span class="rb rb-on"></span>' : '<span class="rb"></span>';
    $cb = fn($v) => $v ? '<span class="cb cb-on">&#10003;</span>' : '<span class="cb"></span>';
    $fmt = fn($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d-M-Y') : '';
    $jk = strtoupper(substr((string) $case->jenis_kelamin, 0, 1));
    $umurTahun = $case->tanggal_lahir ? $case->tanggal_lahir->age : null;
    $umurBulan = $case->tanggal_lahir ? (int) $case->tanggal_lahir->diffInMonths(now()) % 12 : null;
    $umurHari  = $case->tanggal_lahir ? (int) $case->tanggal_lahir->diff(now())->format('%d') : null;
    $spesimen  = $case->spesimen ?? collect();
    $sp1 = $spesimen->get(0); $sp2 = $spesimen->get(1);
    $isRS = ($case->status_rawat === 'rawat_inap') || (bool) $case->nama_faskes_rawat;
    $instansiUpper = strtoupper($case->instansi_pelapor ?? '');
    $sumber = (str_starts_with($instansiUpper, 'RS') || str_starts_with($instansiUpper, 'RUMAH SAKIT')
               || $case->petugasInput?->faskes_type === 'rs') ? 'Rumah Sakit' : 'Puskesmas';
    $hidup = in_array($case->kondisi_akhir, ['sembuh', 'dalam_perawatan'], true);
    $meninggal = $case->kondisi_akhir === 'meninggal';
    $lost = in_array($case->kondisi_akhir, ['unknown', 'pindah'], true);
@endphp

{{-- ============ HALAMAN 1 ============ --}}
<div class="page">
    <div class="doc-code">Form PERT-01</div>
    <div class="doc-title">Form PERT 01 (Form Investigasi Kasus Suspek Pertusis)</div>

    <table class="t">
        <tr>
            <td class="lbl" style="width:14%">Provinsi</td><td style="width:24%">{{ $case->provinsi ?? 'Kalimantan Timur' }}</td>
            <td class="lbl" style="width:14%">Kabupaten</td><td style="width:20%">{{ $case->kab_kota ?? 'Bontang' }}</td>
            <td class="lbl" style="width:12%">Nomor EPID</td><td>{{ $case->no_registrasi }}</td>
        </tr>
        <tr>
            <td class="lbl">Sumber Laporan</td><td>{{ $sumber }}</td>
            <td class="lbl" colspan="1">Nama unit pelapor</td><td colspan="3">{{ $case->instansi_pelapor ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal Terima Laporan</td><td>{{ $fmt($case->tanggal_lapor) }}</td>
            <td class="lbl">Tanggal Pelacakan</td><td colspan="3">{{ $fmt($case->tanggal_penyelidikan ?? null) }}</td>
        </tr>
    </table>

    {{-- INFORMASI KASUS --}}
    <table class="t" style="margin-top:-1px;">
        <tr><td colspan="6" class="sh sh-orange">INFORMASI KASUS</td></tr>
        <tr>
            <td class="lbl" style="width:16%">Nama Kasus</td><td colspan="3">{{ $case->nama_lengkap }}</td>
            <td class="lbl" style="width:14%">Jenis Kelamin</td><td>{!! $rb($jk==='L') !!} L &nbsp; {!! $rb($jk==='P') !!} P</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal Lahir</td><td>{{ $fmt($case->tanggal_lahir) }}</td>
            <td class="lbl" style="width:8%">Umur</td>
            <td colspan="3">{{ $umurTahun !== null ? $umurTahun : '' }} Tahun {{ $umurBulan !== null ? $umurBulan : '' }} Bulan {{ $umurHari !== null ? $umurHari : '' }} Hari</td>
        </tr>
        <tr><td class="lbl">Alamat</td><td colspan="5">{{ $case->alamat_lengkap }}</td></tr>
        <tr>
            <td class="lbl">Kelurahan</td><td colspan="2">{{ $case->kelurahan->name ?? '' }}</td>
            <td class="lbl">Kecamatan</td><td colspan="2">{{ $case->kecamatan->name ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">Nama Orangtua/Wali</td><td colspan="2">{{ $case->nama_orang_tua ?? '' }}</td>
            <td class="lbl">No. Kontak Orangtua/Wali</td><td colspan="2">{{ $case->no_telepon ?? '' }}</td>
        </tr>
    </table>

    {{-- INFORMASI KLINIS --}}
    <table class="t" style="margin-top:-1px;">
        <tr><td colspan="4" class="sh sh-blue">INFORMASI KLINIS</td></tr>
        <tr>
            <td class="lbl" style="width:24%">Batuk terus menerus</td>
            <td style="width:22%">{!! $rb($case->gejala_batuk) !!} Ya &nbsp; {!! $rb(!$case->gejala_batuk) !!} Tidak</td>
            <td class="lbl" style="width:20%">Tanggal Mulai Batuk</td><td>{{ $fmt($case->tanggal_onset) }}</td>
        </tr>
        <tr>
            <td class="lbl">Apnea</td>
            <td>{!! $rb(false) !!} Ya &nbsp; {!! $rb(false) !!} Tidak</td>
            <td class="lbl">Tanggal Mulai Apnea</td><td></td>
        </tr>
        <tr>
            <td class="lbl">Gejala lain</td>
            <td colspan="3">
                {!! $cb(false) !!} Batuk rejan &nbsp;&nbsp;
                {!! $cb($case->gejala_muntah) !!} Muntah setelah batuk &nbsp;&nbsp;
                {!! $cb((bool) ($case->gejala_lainnya ?? false)) !!} Lainnya: {{ $case->gejala_lainnya ?? '' }}
            </td>
        </tr>
    </table>

    {{-- RIWAYAT PENGOBATAN --}}
    <table class="t" style="margin-top:-1px;">
        <tr><td colspan="4" class="sh sh-green">RIWAYAT PENGOBATAN</td></tr>
        <tr>
            <td class="lbl" style="width:36%">Apakah kasus dirawat di Rumah Sakit?</td>
            <td colspan="3">{!! $rb($isRS) !!} Ya &nbsp; {!! $rb(!$isRS) !!} Tidak</td>
        </tr>
        <tr>
            <td class="lbl">Nama Rumah Sakit</td><td>{{ $case->nama_faskes_rawat ?? '' }}</td>
            <td class="lbl" style="width:20%">Nomor Rekam Medik</td><td>{{ $case->no_rekam_medik ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal Masuk Rawat Inap</td><td>{{ $fmt($case->tanggal_masuk_rawat) }}</td>
            <td class="lbl">Tanggal Keluar</td><td>{{ $fmt($case->tanggal_keluar_rawat) }}</td>
        </tr>
    </table>

    {{-- RIWAYAT VAKSINASI --}}
    <table class="t" style="margin-top:-1px;">
        <tr><td colspan="4" class="sh sh-yellow">RIWAYAT VAKSINASI</td></tr>
        @foreach(['usia 2 bulan','usia 3 bulan','usia 4 bulan','usia 18 bulan'] as $usia)
        <tr>
            <td class="lbl" style="width:44%">Imunisasi pertusis (DPT-HB-HiB) {{ $usia }}</td>
            <td style="width:16%"></td>
            <td class="lbl" style="width:18%">Sumber Informasi</td><td></td>
        </tr>
        @endforeach
        <tr>
            <td class="lbl">Pernah menerima imunisasi DPT-HB-HiB saat ORI?</td><td></td>
            <td class="lbl">Sumber Informasi</td><td></td>
        </tr>
        <tr>
            <td class="lbl">Tanggal Vaksinasi DPT-HB-HiB terakhir</td>
            <td colspan="3">{{ $fmt($case->tanggal_imunisasi_terakhir) }}</td>
        </tr>
    </table>
</div>

{{-- ============ HALAMAN 2 ============ --}}
<div class="page">
    <div class="doc-code">Form PERT-01 &nbsp;&mdash;&nbsp; No. EPID: {{ $case->no_registrasi }}</div>

    {{-- INFORMASI EPIDEMIOLOGIS --}}
    <table class="t" style="margin-top:6px;">
        <tr><td colspan="4" class="sh sh-slate">INFORMASI EPIDEMIOLOGIS</td></tr>
        <tr>
            <td class="lbl" style="width:44%">Apakah ada anggota keluarga/masyarakat sekitar yang mengalami sakit sama?</td>
            <td style="width:16%"></td>
            <td class="lbl" style="width:12%">Jumlah</td><td></td>
        </tr>
        <tr>
            <td class="lbl">Apakah bepergian 1 bulan terakhir?</td>
            <td>{!! $rb((bool) $case->riwayat_perjalanan) !!} Ya &nbsp; {!! $rb(!$case->riwayat_perjalanan) !!} Tidak</td>
            <td class="lbl">Lokasi</td><td>{{ $case->riwayat_perjalanan ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal pergi</td><td></td>
            <td class="lbl">Tanggal kembali</td><td></td>
        </tr>
    </table>

    {{-- INFORMASI SPESIMEN --}}
    <table class="t" style="margin-top:-1px;">
        <tr><td colspan="4" class="sh sh-lblue">INFORMASI SPESIMEN</td></tr>
        <tr>
            <td class="lbl" style="width:26%">Apakah spesimen diambil</td>
            <td style="width:24%">{!! $rb((bool) $sp1) !!} Ya &nbsp; {!! $rb(!$sp1) !!} Tidak</td>
            <td class="lbl" style="width:22%">Jenis Spesimen</td><td>{{ $sp1?->jenis_spesimen ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal ambil spesimen</td><td>{{ $fmt($sp1?->tanggal_ambil_spesimen) }}</td>
            <td class="lbl">Tanggal pengiriman spesimen ke lab</td><td>{{ $fmt($sp1?->tanggal_kirim_sampel) }}</td>
        </tr>
        <tr>
            <td class="lbl">Apakah spesimen lain diambil</td>
            <td>{!! $rb((bool) $sp2) !!} Ya &nbsp; {!! $rb(!$sp2) !!} Tidak</td>
            <td class="lbl">Jenis Sampel Lain</td><td>{{ $sp2?->jenis_spesimen ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal ambil spesimen</td><td>{{ $fmt($sp2?->tanggal_ambil_spesimen) }}</td>
            <td class="lbl">Tanggal pengiriman spesimen ke lab</td><td>{{ $fmt($sp2?->tanggal_kirim_sampel) }}</td>
        </tr>
    </table>

    {{-- Keadaan & pelaksana --}}
    <table class="t" style="margin-top:-1px;">
        <tr>
            <td class="lbl" style="width:22%">Keadaan saat ini</td>
            <td>{!! $rb($hidup) !!} Hidup &nbsp;&nbsp; {!! $rb($meninggal) !!} Meninggal &nbsp;&nbsp; {!! $rb($lost) !!} Lost to follow-up</td>
        </tr>
        <tr>
            <td class="lbl">Pelaksana investigasi</td>
            <td>{{ $case->petugasInput->name ?? ($case->nama_pelapor ?? '') }}</td>
        </tr>
    </table>

    {{-- Tanda tangan petugas --}}
    <div style="margin-top:26px; margin-left:6px;">
        <div style="font-weight:bold;">Petugas Pelaksana</div>
        <div style="margin-top:40px;">( {{ $case->petugasInput->name ?? ($case->nama_pelapor ?? '________________') }} )</div>
        <div>No. Kontak : {{ $case->telepon_pelapor ?? '' }}</div>
    </div>

    <div style="margin-top:20px; text-align:right; font-size:8pt; color:#555;">
        Dicetak: {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
</body>
</html>
