<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>MR-01 - {{ $case->no_registrasi }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; line-height: 1.3; }
        .page { padding: 15px 20px; }

        /* Header */
        .header { position: relative; margin-bottom: 8px; }
        .header .logo { position: absolute; top: 0; right: 0; width: 80px; }
        .header .mr-code { font-size: 11pt; font-weight: bold; }
        .header .title { text-align: center; font-size: 12pt; font-weight: bold; margin: 10px 0 8px; }

        /* Tables */
        table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 2px 4px; vertical-align: top; }
        .info-table .label { font-weight: bold; white-space: nowrap; }

        .section-header {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            padding: 3px;
            border: 1px solid #000;
            font-size: 9pt;
        }
        .section-header-orange {
            background-color: #f4b084;
            font-weight: bold;
            text-align: center;
            padding: 3px;
            border: 1px solid #000;
            font-size: 9pt;
        }
        .section-header-green {
            background-color: #c6efce;
            font-weight: bold;
            text-align: center;
            padding: 3px;
            border: 1px solid #000;
            font-size: 9pt;
        }
        .section-header-blue {
            background-color: #bdd7ee;
            font-weight: bold;
            text-align: center;
            padding: 3px;
            border: 1px solid #000;
            font-size: 9pt;
        }

        .data-table { border: 1px solid #000; }
        .data-table td, .data-table th {
            border: 1px solid #000;
            padding: 3px 5px;
            vertical-align: top;
        }
        .data-table .field-label { font-weight: bold; background-color: #fafafa; width: 30%; }

        .cb { display: inline-block; width: 11px; height: 11px; border: 1px solid #000; text-align: center; font-size: 8pt; line-height: 11px; margin-right: 2px; vertical-align: middle; }
        .cb-checked { background-color: #000; color: #fff; }

        .underline-field { border-bottom: 1px solid #000; min-width: 80px; display: inline-block; padding: 0 3px; }
    </style>
</head>
<body>
<div class="page">

    {{-- ===== HEADER ===== --}}
    <div class="header">
        <div class="mr-code">MR-01</div>
        @php
            $logoPath = public_path('images/logo-kemenkes.png');
        @endphp
        @if(file_exists($logoPath))
            <img src="{{ $logoPath }}" class="logo" alt="Kemenkes RI">
        @endif
        <div class="title">FORM INVESTIGASI KASUS {{ strtoupper($disease->nama_penyakit ?? 'PD3I') }}</div>
    </div>

    {{-- ===== TOP INFO ===== --}}
    <table class="info-table" style="margin-bottom: 6px;">
        <tr>
            <td class="label" style="width:12%">Provinsi</td>
            <td style="width:22%; border-bottom:1px solid #000;">{{ $case->provinsi ?? 'KALIMANTAN TIMUR' }}</td>
            <td class="label" style="width:12%">Kabupaten</td>
            <td style="width:18%; border-bottom:1px solid #000;">{{ $case->kab_kota ?? 'BONTANG' }}</td>
            <td class="label" style="width:12%">Nomor Epid</td>
            <td style="width:24%; border-bottom:1px solid #000;">{{ $case->no_registrasi ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Status KLB</td>
            <td style="border-bottom:1px solid #000;"></td>
            <td></td>
            <td></td>
            <td class="label">Nomor KLB</td>
            <td style="border-bottom:1px solid #000;"></td>
        </tr>
        @php
            $instansiUpper = strtoupper($case->instansi_pelapor ?? '');
            $instansiIsRS  = str_starts_with($instansiUpper, 'RS')
                          || str_starts_with($instansiUpper, 'RUMAH SAKIT');
            $sumberLaporan = ($instansiIsRS || $case->petugasInput?->faskes_type === 'rs')
                ? 'Rumah Sakit'
                : 'Puskesmas';
        @endphp
        <tr>
            <td class="label">Sumber Laporan</td>
            <td style="border-bottom:1px solid #000;">{{ $sumberLaporan }}</td>
            <td></td>
            <td></td>
            <td class="label">Nama Unit Pelapor</td>
            <td style="border-bottom:1px solid #000;">{{ $case->instansi_pelapor ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Terima Laporan</td>
            <td style="border-bottom:1px solid #000;">{{ $case->tanggal_terima_laporan ? $case->tanggal_terima_laporan->format('d-M-Y') : ($case->tanggal_lapor ? $case->tanggal_lapor->format('d-M-Y') : '') }}</td>
            <td></td>
            <td></td>
            <td class="label">Tanggal Pelacakan</td>
            <td style="border-bottom:1px solid #000;">{{ $case->tanggal_penyidikan ? $case->tanggal_penyidikan->format('d-M-Y') : '' }}</td>
        </tr>
    </table>

    {{-- ===== INFORMASI KASUS ===== --}}
    <table class="data-table">
        <tr><td colspan="6" class="section-header">INFORMASI KASUS</td></tr>
        <tr>
            <td class="field-label" style="width:15%">NIK</td>
            <td colspan="5">{{ $case->nik }}</td>
        </tr>
        <tr>
            <td class="field-label">Nama Kasus</td>
            <td colspan="3">{{ $case->nama_lengkap }}</td>
            <td class="field-label" style="width:12%">Jenis Kelamin</td>
            <td style="width:10%">{{ $case->jenis_kelamin }}</td>
        </tr>
        <tr>
            <td class="field-label">Tanggal Lahir</td>
            <td>{{ $case->tanggal_lahir ? $case->tanggal_lahir->format('d-M-Y') : '-' }}</td>
            <td class="field-label" style="width:8%">Umur:</td>
            <td>
                @php
                    $umurTahun = $case->tanggal_lahir ? $case->tanggal_lahir->age : 0;
                    $umurBulan = $case->tanggal_lahir ? (int)$case->tanggal_lahir->diffInMonths(now()) % 12 : 0;
                    $umurHari  = $case->tanggal_lahir ? (int)$case->tanggal_lahir->diff(now())->format('%d') : 0;
                @endphp
                {{ $umurTahun }} Tahun {{ $umurBulan }} Bulan {{ $umurHari }} Hari
            </td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td class="field-label">Alamat</td>
            <td colspan="5">{{ $case->alamat_lengkap }}</td>
        </tr>
        <tr>
            <td class="field-label">Kelurahan</td>
            <td colspan="2">{{ $case->kelurahan->name ?? '-' }}</td>
            <td class="field-label" style="width:12%">Kecamatan</td>
            <td colspan="2">{{ $case->kecamatan->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="field-label">Nama Orangtua/Wali</td>
            <td colspan="2">{{ $case->nama_orang_tua ?? '-' }}</td>
            <td class="field-label">No. Kontak Orangtua/Wali</td>
            <td colspan="2">{{ $case->no_hp_orang_tua ?? ($case->no_telepon ?? '-') }}</td>
        </tr>
    </table>

    {{-- ===== INFORMASI KLINIS ===== --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="8" class="section-header">INFORMASI KLINIS</td></tr>
        @php
            $cb = function($val) {
                return $val ? '<span class="cb cb-checked">&#10003;</span>' : '<span class="cb"></span>';
            };
        @endphp
        <tr>
            <td class="field-label" style="width:18%"><strong>Demam</strong></td>
            <td style="width:7%">{!! $cb($case->gejala_demam) !!} Ya</td>
            <td style="width:7%">{!! $cb(!$case->gejala_demam) !!} Tidak</td>
            <td style="width:18%">Tanggal Mulai Demam</td>
            <td colspan="4">{{ $case->tanggal_demam ? $case->tanggal_demam->format('d-M-Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="field-label"><strong>Ruam Makulopapular</strong></td>
            <td>{!! $cb($case->gejala_ruam) !!} Ya</td>
            <td>{!! $cb(!$case->gejala_ruam) !!} Tidak</td>
            <td>Tanggal Mulai Ruam</td>
            <td colspan="4">{{ $case->tanggal_onset ? $case->tanggal_onset->format('d-M-Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="field-label"><strong>Gejala lain</strong></td>
            <td colspan="2">
                {!! $cb($case->gejala_batuk) !!} Batuk<br>
                {!! $cb($case->gejala_pilek) !!} Pilek<br>
                {!! $cb($case->gejala_mata_merah) !!} Mata Merah<br>
                {!! $cb($case->gejala_lemas) !!} Lainnya
            </td>
            <td colspan="2">
                {!! $cb($case->gejala_adenopathy) !!} Adenopathy<br>
                {!! $cb($case->gejala_arthralgia) !!} Arthralgia<br>
                {!! $cb($case->gejala_kehamilan) !!} Kehamilan
            </td>
            <td colspan="3">
                @if($case->gejala_lainnya)
                    Lainnya: {{ $case->gejala_lainnya }}
                @endif
            </td>
        </tr>
    </table>

    {{-- ===== KOMPLIKASI ===== --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr>
            <td class="field-label" style="width:18%"><strong>Komplikasi</strong></td>
            <td style="width:27%">
                {!! $cb($case->komplikasi_diare) !!} Diare<br>
                {!! $cb($case->komplikasi_kebutaan) !!} Kebutaan<br>
                {!! $cb($case->komplikasi_pneumonia) !!} Pneumonia<br>
                {!! $cb($case->komplikasi_malnutrisi) !!} Malnutrisi
            </td>
            <td style="width:27%">
                {!! $cb($case->komplikasi_bronchopneumonia) !!} Bronchopneumonia<br>
                {!! $cb($case->komplikasi_otitis_media) !!} Otitis Media<br>
                {!! $cb($case->komplikasi_encephalitis) !!} Encephalitis<br>
                {!! $cb($case->komplikasi_ulkus_mukosa_mulut) !!} Ulkus mukosa mulut
            </td>
            <td style="width:28%"></td>
        </tr>
    </table>

    {{-- ===== RIWAYAT PENGOBATAN ===== --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="6" class="section-header-orange">RIWAYAT PENGOBATAN</td></tr>
        <tr>
            <td style="width:35%">Apakah kasus dirawat di Rumah Sakit?</td>
            <td style="width:15%">
                {!! $cb($case->status_rawat === 'rawat_inap') !!} Ya
            </td>
            <td colspan="4">
                {!! $cb($case->status_rawat !== 'rawat_inap') !!} Tidak
            </td>
        </tr>
        <tr>
            <td class="field-label">Nama Rumah Sakit</td>
            <td colspan="2">{{ $case->nama_faskes_rawat ?? ($case->nama_rs ?? '-') }}</td>
            <td class="field-label" style="width:18%">Nomor Rekam Medik</td>
            <td colspan="2">-</td>
        </tr>
        <tr>
            <td class="field-label">Tanggal Masuk Rawat Inap</td>
            <td colspan="2">{{ $case->tanggal_masuk_rawat ? $case->tanggal_masuk_rawat->format('d-M-Y') : '-' }}</td>
            <td class="field-label">Tanggal Keluar</td>
            <td colspan="2">{{ $case->tanggal_keluar_rawat ? $case->tanggal_keluar_rawat->format('d-M-Y') : '-' }}</td>
        </tr>
    </table>

    {{-- ===== RIWAYAT IMUNISASI ===== --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="6" class="section-header">RIWAYAT IMUNISASI</td></tr>
        @php
            $imunisasiLabels = [
                'imunisasi_1' => 'Imunisasi campak-rubela dosis 1',
                'imunisasi_2' => 'Imunisasi campak-rubela dosis 2',
                'imunisasi_3' => 'Imunisasi campak-rubela saat BIAS',
            ];
        @endphp
        @foreach($imunisasiLabels as $field => $label)
        <tr>
            <td style="width:35%">{{ $label }}</td>
            <td style="width:12%">
                @php $val = $case->$field; @endphp
                @if($val === 'ya' || $val === 'Ya')
                    Ya
                @elseif($val === 'tidak' || $val === 'Tidak')
                    Tidak
                @else
                    Tidak Tahu
                @endif
            </td>
            <td class="field-label" style="width:15%">Sumber Informasi</td>
            <td colspan="3">{{ $case->sumber_informasi_imunisasi ?? '-' }}</td>
        </tr>
        @endforeach
        <tr>
            <td style="width:35%">Pernah menerima imunisasi Measles Mumps Rubella (MMR) sebelumnya?</td>
            <td style="width:12%">{{ $case->imunisasi_4 ?? 'Tidak Tahu' }}</td>
            <td class="field-label" style="width:15%">Sumber Informasi</td>
            <td colspan="3">{{ $case->sumber_informasi_imunisasi ?? '-' }}</td>
        </tr>
        <tr>
            <td>Pernah menerima imunisasi campak-rubela saat imunisasi tambahan campak-rubela?</td>
            <td>{{ $case->imunisasi_5 ?? 'Tidak Tahu' }}</td>
            <td class="field-label">Sumber Informasi</td>
            <td colspan="3">{{ $case->sumber_informasi_imunisasi ?? '-' }}</td>
        </tr>
        <tr>
            <td>Tanggal imunisasi campak-rubela terakhir</td>
            <td>{{ $case->tanggal_imunisasi_terakhir ? $case->tanggal_imunisasi_terakhir->format('d-M-Y') : '-' }}</td>
            <td class="field-label">Sumber Informasi</td>
            <td colspan="3">{{ $case->sumber_informasi_imunisasi ?? '-' }}</td>
        </tr>
    </table>

    {{-- ===== INFORMASI EPIDEMIOLOGIS ===== --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="6" class="section-header-green">INFORMASI EPIDEMIOLOGIS</td></tr>
        <tr>
            <td style="width:35%">Pemberian Vitamin A</td>
            <td colspan="5">{{ $case->vitamin_a ?? '-' }}</td>
        </tr>
        <tr>
            <td>Apakah ada anggota keluarga atau masyarakat sekitar yang mengalami sakit yang sama? Jumlah</td>
            <td colspan="2">{{ $case->keluarga_sakit_sama ?? '-' }}</td>
            <td class="field-label" style="width:10%">Jumlah</td>
            <td colspan="2">{{ $case->jumlah_keluarga_sakit ?? '-' }}</td>
        </tr>
        <tr>
            <td>Apakah bepergian 1 bulan terakhir? Lokasi</td>
            <td colspan="5">{{ $case->lokasi_bepergian ?? ($case->riwayat_perjalanan ?? '-') }}</td>
        </tr>
        <tr>
            <td class="field-label">Tanggal pergi</td>
            <td colspan="2">{{ $case->tanggal_bepergian ? $case->tanggal_bepergian->format('d-M-Y') : '-' }}</td>
            <td class="field-label">Tanggal Kembali</td>
            <td colspan="2">-</td>
        </tr>
    </table>

    {{-- ===== INFORMASI LABORATORIUM ===== --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="6" class="section-header-blue">INFORMASI LABORATORIUM</td></tr>
        <tr>
            <td style="width:30%">Apakah spesimen darah diambil</td>
            <td style="width:10%">
                @if($case->jenis_spesimen && str_contains(strtolower($case->jenis_spesimen), 'darah'))
                    Ya
                @elseif($case->jenis_spesimen)
                    Tidak
                @else
                    -
                @endif
            </td>
            <td class="field-label" style="width:18%">Jenis Sampel Darah</td>
            <td style="width:10%">{{ str_contains(strtolower($case->jenis_spesimen ?? ''), 'serum') ? 'Serum' : '-' }}</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td>Tanggal ambil spesimen darah</td>
            <td colspan="2">{{ $case->tanggal_pengambilan_spesimen ? $case->tanggal_pengambilan_spesimen->format('d-M-Y') : '-' }}</td>
            <td class="field-label">Tanggal pengiriman spesimen ke lab</td>
            <td colspan="2">-</td>
        </tr>
        <tr>
            <td>Apakah spesimen lain diambil</td>
            <td>{{ $case->jenis_spesimen_2 ? 'Ya' : 'Tidak' }}</td>
            <td class="field-label">Jenis Sampel Lain</td>
            <td colspan="3">{{ $case->jenis_spesimen_2 ?? '-' }}</td>
        </tr>
        <tr>
            <td>Tanggal Ambil Spesimen</td>
            <td colspan="2">{{ $case->tanggal_spesimen_2 ? $case->tanggal_spesimen_2->format('d-M-Y') : '-' }}</td>
            <td class="field-label">Tanggal Pengiriman Spesimen Ke Lab</td>
            <td colspan="2">-</td>
        </tr>
    </table>

    {{-- ===== KONDISI AKHIR ===== --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr>
            <td style="width:18%"><strong>Keadaan Saat Ini</strong></td>
            <td style="width:20%">
                {!! $cb($case->kondisi_akhir === 'sembuh' || $case->kondisi_akhir === 'dalam_perawatan') !!} Hidup
            </td>
            <td style="width:20%">
                {!! $cb($case->kondisi_akhir === 'meninggal') !!} Meninggal
            </td>
            <td>
                {!! $cb($case->kondisi_akhir === 'unknown' || $case->kondisi_akhir === 'pindah') !!} Lost To Follow Up
            </td>
        </tr>
        <tr>
            <td><strong>Pelaksana Investigasi</strong></td>
            <td colspan="3">{{ $case->petugasInput->name ?? ($case->nama_pelapor ?? '-') }}</td>
        </tr>
    </table>

</div>

{{-- ===== HALAMAN 2 ===== --}}
<div class="page" style="page-break-before: always;">

    {{-- Header halaman 2 --}}
    <table style="width:100%; border:none; margin-bottom:8px;" class="info-table">
        <tr>
            <td style="width:15%; vertical-align:top; padding-top:2px;">
                <div class="mr-code">MR-01 (Hal. 2)</div>
            </td>
            <td style="vertical-align:middle; text-align:center; padding:0 8px;">
                <div style="font-size:10pt; font-weight:bold; margin-bottom:3px;">
                    FORMULIR PENYELIDIKAN EPIDEMIOLOGI PENYAKIT YANG DAPAT DICEGAH DENGAN IMUNISASI (PD3I)
                </div>
                <div style="font-size:9pt;">
                    Nomor: <strong>{{ $case->no_registrasi }}</strong> &nbsp;|&nbsp; Penyakit: <strong>{{ $case->jenisKasus->nama_penyakit ?? '-' }}</strong>
                </div>
            </td>
            <td style="width:85px; vertical-align:top; text-align:right;">
                @if(file_exists($logoPath))
                    <img src="{{ $logoPath }}" style="width:75px;" alt="Kemenkes RI">
                @endif
            </td>
        </tr>
    </table>

    {{-- Kontak Erat --}}
    @php
        $kontakErat       = $case->kontakErat ?? collect();
        $namaPenyakit     = $case->jenisKasus->nama_penyakit ?? 'PD3I';
        // Hilangkan awalan "Suspek " agar tidak dobel dengan kata "suspek" di teks narasi
        $namaPenyakitBersih = preg_replace('/^Suspek\s+/i', '', $namaPenyakit);
    @endphp

    {{-- Narasi investigasi kontak --}}
    <p style="font-size:8.5pt; font-weight:bold; margin:0 0 3px;">Kontak erat suspek {{ $namaPenyakitBersih }}</p>
    <p style="font-size:8pt; margin:0 0 3px; text-align:justify;">
        Identifikasi semua orang yang berhubungan (kontak) dengan suspek {{ $namaPenyakitBersih }} selama masa
        inkubasi (7&ndash;21 hari sebelum timbulnya ruam) dan selama fase menular (7 hari sebelum dan 7 hari setelah
        timbulnya ruam).
        Yang termasuk dalam kategori kontak kasus adalah: tinggal satu rumah / asrama, tetangga / kerabat / pengasuh,
        teman kelas / bermain / guru, teman kerja, petugas kesehatan, yang merawat kasus.
        Catat kontak tersebut pada tabel di bawah ini, tentukan apakah mereka sedang sakit atau tidak.
        Apabila ada kontak yang sedang hamil beri tanda bintang (*) pada nama kontak di tabel dan pastikan ibu hamil
        dijauhkan dari suspek.
    </p>

    <div class="section-header" style="margin-bottom:4px;">DATA KONTAK ERAT</div>
    <table class="data-table" style="font-size:8pt;">
        <thead>
            <tr style="background:#f0f0f0; font-weight:bold;">
                <th style="width:4%; text-align:center;">No</th>
                <th style="width:18%">Nama</th>
                <th style="width:10%">Hubungan</th>
                <th style="width:8%">Umur (Thn)</th>
                <th style="width:18%">Alamat</th>
                <th style="width:12%">Tgl Kontak Terakhir</th>
                <th style="width:14%">Status Imunisasi {{ $namaPenyakit }}</th>
                <th style="width:8%; text-align:center;">Bergejala</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($kontakErat as $i => $k)
            @php
                $umurThn = $k->tanggal_lahir ? (int) $k->tanggal_lahir->diffInYears(now()) : null;
                $jml = $k->jumlah_imunisasi_campak_rubella;
                $statusImunisasi = is_null($jml) ? 'Tidak Tahu' : ($jml === 0 ? 'Belum' : 'Sudah (' . $jml . 'x)');
            @endphp
            <tr>
                <td style="text-align:center;">{{ $i + 1 }}</td>
                <td>{{ $k->nama }}</td>
                <td>{{ $k->hubungan ?? '-' }}</td>
                <td style="text-align:center;">{{ $umurThn ?? '-' }}</td>
                <td>{{ $k->alamat ?? '-' }}</td>
                <td style="text-align:center;">{{ $k->tanggal_kontak_terakhir ? $k->tanggal_kontak_terakhir->format('d/m/Y') : '-' }}</td>
                <td style="text-align:center;">{{ $statusImunisasi }}</td>
                <td style="text-align:center;">{{ $k->ada_gejala ? 'Ya' : 'Tidak' }}</td>
                <td>{{ $k->catatan ?? '' }}</td>
            </tr>
            @empty
            @for($r = 0; $r < 10; $r++)
            <tr>
                <td style="text-align:center;">{{ $r + 1 }}</td>
                <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
            </tr>
            @endfor
            @endforelse
        </tbody>
    </table>

    {{-- Footer --}}
    <div style="margin-top:16px; text-align:right; font-size:8pt; color:#555;">
        Dicetak: {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp; {{ $case->nama_pelapor ?? '' }}
    </div>

</div>
</body>
</html>
