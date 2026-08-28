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

    // Bag G: tempat berobat per jenis faskes (kestrad & RS), dengan kolom lama
    // nama_rs / nama_pengobatan_tradisional sebagai cadangan untuk data impor.
    $faskes = $case->faskesBerobat ?? collect();
    $byJenis = fn(string $j) => $faskes->firstWhere('jenis_faskes', $j);
    $fTrad = $byJenis('pengobatan_tradisional');
    $fRs   = $byJenis('rs');
    $namaTrad = $fTrad?->nama_faskes ?? $case->nama_pengobatan_tradisional;
    $tglTrad  = $fTrad?->tanggal_berobat ?? $case->tanggal_kunjungan_tradisional;
    $namaRs   = $fRs?->nama_faskes ?? $case->nama_rs ?? $case->nama_faskes_rawat;
    $tglRs    = $fRs?->tanggal_berobat ?? $case->tanggal_kunjungan_rs ?? $case->tanggal_masuk_rawat;
    $berobatRs = (bool) ($fRs || $case->nama_rs || ($case->nama_faskes_rawat && $case->nama_faskes_rawat !== '-'));

    // Bag D3: grid anggota gerak. Kolom tanda_* berupa teks bebas — dianggap
    // "ada kelumpuhan" bila terisi dan bukan kalimat penyangkalan.
    $anggotaGerak = [
        ['label' => 'Tungkai kanan', 'tanda' => $case->tanda_tungkai_kanan, 'raba' => $case->rasa_raba_tungkai_kanan],
        ['label' => 'Tungkai kiri',  'tanda' => $case->tanda_tungkai_kiri,  'raba' => $case->rasa_raba_tungkai_kiri],
        ['label' => 'Lengan kanan',  'tanda' => $case->tanda_lengan_kanan,  'raba' => $case->rasa_raba_lengan_kanan],
        ['label' => 'Lengan kiri',   'tanda' => $case->tanda_lengan_kiri,   'raba' => $case->rasa_raba_lengan_kiri],
    ];
    $tandaNegatif = ['tidak', 'tidak ada', 'normal', '-', 'tidak lumpuh'];

    // Bag E: imunisasi polio per slot antigen (lihat form-section-e).
    $imun = collect($case->imunisasi ?? [])->keyBy('imunisasi_ke');

    // Bag E: riwayat bepergian terstruktur, teks riwayat_perjalanan sebagai cadangan.
    $pernahBepergian = $case->riwayat_bepergian === 'ya' || (bool) $case->riwayat_perjalanan;
    $lokasiBepergian = $case->lokasi_bepergian ?: $case->riwayat_perjalanan;

    // Petugas investigasi = pelapor kasus (Bag B), bukan user yang membuka export.
    $petugas = $case->nama_pelapor ?: ($case->petugasInput->name ?? '');
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
            <td class="lbl">Tanggal Penyelidikan</td><td colspan="2">{{ $fmt($case->tanggal_penyidikan) }}</td>
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
            {{-- "Mulai sakit/gejala awal sebelum lumpuh" = tanggal demam (prodromal
                 sebelum kelumpuhan pada AFP). "Mulai lemah/lumpuh" = tanggal_onset. --}}
            <td class="lbl" style="width:34%">Tanggal mulai sakit/gejala awal sebelum lumpuh</td>
            <td style="width:26%">{{ $fmt($case->tanggal_demam) }}</td>
            <td class="lbl" style="width:20%">Tanggal mulai lemah/lumpuh</td>
            <td>{{ $fmt($case->tanggal_onset) }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal meninggal (bila meninggal)</td>
            <td colspan="3">{{ $meninggal ? $fmt($case->tanggal_kondisi_akhir) : '' }}</td>
        </tr>
        <tr>
            {{-- Bag G: baris pengobatan tradisional/kestrad. --}}
            <td class="lbl">Setelah lemah/lumpuh, menjalani pengobatan tradisional/alternatif?</td>
            <td colspan="3">{!! $cb((bool) $namaTrad) !!} Ya &nbsp; {!! $cb(!$namaTrad) !!} Tidak
                &nbsp;&nbsp; Nama tempat: {{ $namaTrad ?: '………' }} &nbsp; Tanggal: {{ $fmt($tglTrad) ?: '………' }}</td>
        </tr>
        <tr>
            {{-- Bag G: baris Rumah Sakit. --}}
            <td class="lbl">Setelah lemah/lumpuh, berobat ke Rumah Sakit?</td>
            <td colspan="3">
                {!! $cb($berobatRs) !!} Ya &nbsp; {!! $cb(!$berobatRs) !!} Tidak
                &nbsp;&nbsp; Nama RS: {{ $namaRs ?? '' }} &nbsp; Tgl berobat: {{ $fmt($tglRs) }}
            </td>
        </tr>
        <tr>
            {{-- Bag G2: diagnosis dokter; Bag G: no. rekam medik. --}}
            <td class="lbl">Diagnosis</td><td>{{ $case->diagnosis_dokter ?: ($case->diagnosis ?? '') }}</td>
            <td class="lbl">No. rekam medik</td><td>{{ $case->no_rekam_medik ?? '' }}</td>
        </tr>
    </table>

    {{-- Pertanyaan penentu — jawaban yang memicu STOP investigasi diberi latar gelap --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr>
            <td class="lbl" style="width:54%">Apakah kelemahan/kelumpuhan sifatnya akut (1-14 hari)?</td>
            <td style="width:12%; text-align:center;">{!! $cb($case->kelumpuhan_akut === 'ya') !!} Ya</td>
            <td style="width:12%; text-align:center; background:#808080; color:#fff;">{!! $cb($case->kelumpuhan_akut === 'tidak') !!} Tidak</td>
            <td rowspan="2" style="width:22%; background:#808080; color:#fff; font-weight:bold; text-align:center; vertical-align:middle;">Stop investigasi</td>
        </tr>
        <tr>
            <td class="lbl">Apakah kelemahan/kelumpuhan sifatnya layuh (flaccid)?</td>
            <td style="text-align:center;">{!! $cb($case->kelumpuhan_flaccid === 'ya') !!} Ya</td>
            <td style="text-align:center; background:#808080; color:#fff;">{!! $cb($case->kelumpuhan_flaccid === 'tidak') !!} Tidak</td>
        </tr>
        <tr>
            <td class="lbl">Apakah kelemahan/kelumpuhan disebabkan rudapaksa?</td>
            <td style="text-align:center; background:#808080; color:#fff;">{!! $cb($case->kelumpuhan_rudapaksa === 'ya') !!} Ya</td>
            <td style="text-align:center;">{!! $cb($case->kelumpuhan_rudapaksa === 'tidak') !!} Tidak</td>
            <td></td>
        </tr>
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
        @foreach($anggotaGerak as $ag)
        @php
            $tandaTeks = trim((string) $ag['tanda']);
            $adaTanda  = $tandaTeks !== '' && !in_array(strtolower($tandaTeks), $tandaNegatif, true);
            $tidakAdaTanda = $tandaTeks !== '' && !$adaTanda;
        @endphp
        <tr>
            <td>{{ $ag['label'] }}</td>
            <td style="text-align:center;">{!! $cb($adaTanda) !!} Ya &nbsp; {!! $cb($tidakAdaTanda) !!} Tidak
                @if($adaTanda)<br><span style="font-size:7.5pt;">{{ $tandaTeks }}</span>@endif</td>
            <td style="text-align:center;">{{ $adaTanda ? $case->kekuatan_otot : '' }}</td>
            <td style="text-align:center;">{!! $cb($ag['raba'] === 'ya') !!} Ya &nbsp; {!! $cb($ag['raba'] === 'tidak') !!} Tidak</td>
        </tr>
        @endforeach
        <tr><td colspan="4">Lain-lain (muka, leher, dll): {{ $case->lokasi_kelemahan_lain ?: '…………………………' }}</td></tr>
    </table>

    {{-- IV. Riwayat Kontak --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="2" class="section-header">IV. Riwayat Kontak</td></tr>
        <tr>
            <td class="lbl" style="width:50%">Dalam 35 hari terakhir, pernah bepergian ke luar kab/prov/negeri?</td>
            <td>{!! $cb($pernahBepergian) !!} Ya — Lokasi: {{ $lokasiBepergian ?? '' }}
                &nbsp; {!! $cb(!$pernahBepergian) !!} Tidak</td>
        </tr>
        <tr>
            <td class="lbl">Dalam 75 hari terakhir, kontak dengan anak yang baru imunisasi polio oral?</td>
            <td>{!! $cb($case->kontak_polio_oral === 'ya') !!} Ya &nbsp; {!! $cb($case->kontak_polio_oral === 'tidak') !!} Tidak &nbsp; {!! $cb($case->kontak_polio_oral === 'tidak_tahu') !!} Tidak tahu</td>
        </tr>
    </table>
</div>

{{-- ============ HALAMAN 2 ============ --}}
<div class="page" style="page-break-before: always;">
    <div style="text-align:right; font-weight:bold; font-size:10pt; margin-bottom:4px;">FP-1 (Hal. 2) &nbsp; No. Epid: {{ $case->no_registrasi }}</div>

    {{-- V. Sanitasi Dasar — seluruhnya dari Bag D3. --}}
    @php
        $jamban  = $case->jenis_jamban;
        $diapers = $case->pembuangan_diapers;
    @endphp
    <table class="data-table">
        <tr><td colspan="2" class="section-header">V. Sanitasi Dasar: Jamban dan Pembuangan Tinja</td></tr>
        <tr><td class="lbl" style="width:55%">Memiliki jamban sendiri di rumah?</td><td>{!! $cb($case->jamban_sendiri === 'ya') !!} Ya &nbsp; {!! $cb($case->jamban_sendiri === 'tidak') !!} Tidak</td></tr>
        <tr><td class="lbl">Jenis jamban yang digunakan?</td><td>
            {!! $cb($jamban === 'leher_angsa_septic') !!} Jamban leher angsa dengan septic tank<br>
            {!! $cb($jamban === 'cemplung') !!} Jamban cemplung (tanpa septic tank)<br>
            {!! $cb($jamban === 'sungai_kebun_kolam') !!} Jamban di sungai/kebun/kolam (tidak sehat)<br>
            {!! $cb($jamban === 'lainnya') !!} Lainnya, ..............................
        </td></tr>
        <tr><td class="lbl">Selalu menggunakan jamban untuk BAB?</td><td>{!! $cb($case->selalu_gunakan_jamban === 'ya') !!} Ya, selalu &nbsp; {!! $cb($case->selalu_gunakan_jamban === 'kadang_kadang') !!} Kadang &nbsp; {!! $cb($case->selalu_gunakan_jamban === 'tidak') !!} Tidak</td></tr>
        <tr><td class="lbl">Jamban dilengkapi saluran pembuangan kedap & aman?</td><td>{!! $cb($case->jamban_saluran_kedap === 'ya') !!} Ya &nbsp; {!! $cb($case->jamban_saluran_kedap === 'tidak') !!} Tidak</td></tr>
        <tr><td class="lbl">Pembuangan diapers (jika masih pakai)</td><td>{!! $cb($diapers === 'sampah_tertutup') !!} Sampah tertutup &nbsp; {!! $cb($diapers === 'sungai_kebun') !!} Sungai/kebun &nbsp; {!! $cb($diapers === 'dibakar') !!} Dibakar &nbsp; {!! $cb($diapers === 'lainnya') !!} Lainnya</td></tr>
    </table>

    {{-- VI. Status Imunisasi Polio --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr><td colspan="3" class="section-header">VI. Status Imunisasi Polio</td></tr>
        {{-- Bag E: slot antigen 1–5 = OPV/IPV/Hexavalen rutin, lalu OPV/IPV tambahan.
             Aplikasi menyimpan status "diberikan" (ya/tidak/tidak tahu) + tanggal,
             bukan jumlah dosis, jadi kotak 1x–4x tetap diisi manual. --}}
        @foreach([1 => 'Imunisasi rutin — OPV', 2 => 'Imunisasi rutin — IPV', 3 => 'Imunisasi rutin — Hexavalen', 4 => 'Imunisasi tambahan — OPV', 5 => 'Imunisasi tambahan — IPV'] as $ke => $im)
        @php $row = $imun->get($ke); @endphp
        <tr>
            <td class="lbl" style="width:28%">{{ $im }}</td>
            <td>{!! $cb(false) !!} 1x {!! $cb(false) !!} 2x {!! $cb(false) !!} 3x {!! $cb(false) !!} 4x
                {!! $cb(optional($row)->diberikan === 'tidak') !!} Belum pernah
                {!! $cb($row === null || optional($row)->diberikan === 'tidak_tahu') !!} Tidak tahu</td>
            <td style="width:26%">{{ collect([$fmt(optional($row)->tanggal_imunisasi), optional($row)->sumber_informasi])->filter()->implode(' — ') }}</td>
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
        {{-- Belum ada kolomnya di aplikasi; sengaja dibiarkan kosong untuk diisi manual. --}}
        <tr><td class="lbl">Alasan tidak diambil spesimen</td><td colspan="3"></td></tr>
    </table>

    {{-- Petugas & Hasil --}}
    <table class="data-table" style="margin-top:-1px;">
        <tr>
            <td class="lbl" style="width:16%">Petugas investigasi</td>
            <td style="width:34%">{{ $petugas }}</td>
            {{-- Bag G2: diagnosis & identitas dokter pemeriksa. --}}
            <td class="lbl" style="width:18%">Hasil pemeriksaan / Diagnosis*</td>
            <td>{{ $case->diagnosis_dokter ?: ($case->hasil_lab ?? '') }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanda tangan</td><td style="height:32px;"></td>
            <td class="lbl">Nama dokter</td><td>{{ $case->nama_dokter ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl" rowspan="2" style="vertical-align:top;"></td>
            <td rowspan="2"></td>
            <td class="lbl">No. Telp./HP</td><td>{{ $case->no_telp_dokter ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanda tangan**</td><td style="height:32px;"></td>
        </tr>
    </table>

    {{-- Keterangan/footnote sesuai formulir asli --}}
    <div style="font-size:7.5pt; margin-top:8px; text-align:justify; line-height:1.3;">
        <p><strong>*</strong> Penulisan diagnosis: AFP, parese, plegi, dan febris bukan merupakan diagnosis.
        Apabila belum dapat dipastikan diagnosisnya, silakan dikonsultasikan dengan dokter spesialis anak/dokter
        spesialis saraf/dokter spesialis kedokteran fisik dan rehabilitasi/dokter umum/komite ahli surveilans PD3I
        di masing-masing provinsi.</p>
        <p style="margin-top:3px;"><strong>**</strong> Formulir FP-1 dapat ditandatangani oleh pejabat berwenang
        jika tidak ada dokter di fasilitas kesehatan tersebut.</p>
    </div>

    <div style="margin-top:10px; text-align:right; font-size:8pt; color:#555;">
        Dicetak: {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp; {{ $petugas }}
    </div>
</div>
</body>
</html>
