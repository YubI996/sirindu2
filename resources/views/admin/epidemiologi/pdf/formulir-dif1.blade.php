<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>DIF-1 - {{ $case->no_registrasi }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; line-height: 1.4; }
        .page { padding: 20px 26px; }
        .page + .page { page-break-before: always; }

        .doc-code { font-weight: bold; font-size: 9pt; }
        .doc-title { text-align: center; font-weight: bold; font-size: 11.5pt; margin: 6px 0 12px; text-decoration: underline; }
        .sec-title { font-weight: bold; margin: 11px 0 3px; }

        table { border-collapse: collapse; }
        .lay { width: 100%; }
        .lay td { padding: 2px 3px; vertical-align: bottom; }
        .lay td.top { vertical-align: top; }
        .num { width: 26px; vertical-align: top; }
        .val { border-bottom: 1px dotted #000; }

        .cbx { display: inline-block; width: 11px; height: 11px; border: 1px solid #000; vertical-align: middle;
               text-align: center; line-height: 11px; font-size: 8pt; font-family: 'DejaVu Sans', sans-serif; margin-right: 2px; }
        .cbx-on { background: #000; color: #fff; }
        .muted { color: #777; }

        /* Kotak NO EPID */
        .epid { border-collapse: collapse; }
        .epid td { border: 1px solid #000; text-align: center; font-weight: bold; width: 34px; height: 22px; font-size: 9pt; }
        .epid .plain { border: none; width: auto; font-weight: bold; padding-right: 4px; }
        .epid .cap { border: none; font-weight: normal; font-size: 6.5pt; line-height: 1.05; padding-top: 2px; }

        /* Tabel kontak (hal. 3) tetap bergaris seperti asli */
        .grid { width: 100%; border: 1px solid #000; }
        .grid td, .grid th { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
    </style>
</head>
<body>
@php
    $cb  = fn($v) => $v ? '<span class="cbx cbx-on">&#10003;</span>' : '<span class="cbx"></span>';
    $fmt = fn($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d-m-Y') : '';
    $jk = strtoupper(substr((string) $case->jenis_kelamin, 0, 1));
    $umurTahun = $case->tanggal_lahir ? $case->tanggal_lahir->age : null;
    $umurBulan = $case->tanggal_lahir ? (int) $case->tanggal_lahir->diffInMonths(now()) % 12 : null;

    $spesimen  = $case->spesimen ?? collect();
    $sp1 = $spesimen->get(0);
    $jenisSpAll = strtolower($spesimen->pluck('jenis_spesimen')->implode(' '));
    $spTenggorok = str_contains($jenisSpAll, 'tenggorok');
    $spHidung    = str_contains($jenisSpAll, 'hidung') || str_contains($jenisSpAll, 'nasal');
    $spKeduanya  = str_contains($jenisSpAll, 'kedua') || ($spTenggorok && $spHidung);

    // Bag G: tempat berobat per jenis faskes (bukan tebakan dari status_rawat).
    $faskes = $case->faskesBerobat ?? collect();
    $byJenis = fn(string $jenis) => $faskes->firstWhere('jenis_faskes', $jenis);
    $fRs        = $byJenis('rs');
    $fPuskesmas = $byJenis('puskesmas');
    $fKlinik    = $byJenis('klinik');
    $fLainnya   = $byJenis('lainnya');
    $tidakBerobat = $faskes->isEmpty();

    // Bag E: riwayat imunisasi per antigen (slot 1–5 sesuai form-section-e).
    $imun = collect($case->imunisasi ?? [])->keyBy('imunisasi_ke');
    $sumberImun = strtolower((string) ($case->sumber_informasi_imunisasi
        ?? optional($imun->first())->sumber_informasi ?? ''));

    $riw = $case->riwayat_imunisasi;
    $imBelum   = $riw === 'tidak' || $riw === 'tidak_ada';
    $imPernah  = in_array($riw, ['lengkap', 'tidak_lengkap'], true);
    $imTdkTahu = !$imBelum && !$imPernah;

    // Bag D: keluhan utama dirangkai dari gejala klinis yang dicentang
    // (reviu klien Agustus 2026: "Bagian D gejala klinis yang dicentang").
    $gejalaLabels = [
        'gejala_demam'                  => 'Demam',
        'gejala_sakit_tenggorokan'      => 'Sakit Tenggorokan',
        'gejala_leher_bengkak'          => 'Leher Bengkak',
        'gejala_pseudomembran'          => 'Pseudomembran',
        'gejala_sesak_napas'            => 'Sesak Napas',
        'gejala_batuk'                  => 'Batuk',
        'gejala_pilek'                  => 'Pilek',
        'gejala_mual'                   => 'Mual',
        'gejala_muntah'                 => 'Muntah',
        'gejala_lemas'                  => 'Lemas',
        'gejala_kehilangan_nafsu_makan' => 'Hilang Nafsu Makan',
        'gejala_kejang'                 => 'Kejang',
        'gejala_penurunan_kesadaran'    => 'Penurunan Kesadaran',
    ];
    $keluhanUtama = collect($gejalaLabels)
        ->filter(fn($label, $field) => (bool) $case->{$field})
        ->values()
        ->when((bool) $case->gejala_lainnya, fn($c) => $c->push($case->gejala_lainnya))
        ->implode(', ');

    // Bag E: bepergian terstruktur, dengan teks riwayat_perjalanan sebagai cadangan.
    $pernahBepergian = $case->riwayat_bepergian === 'ya' || (bool) $case->riwayat_perjalanan;
    $tidakBepergian  = $case->riwayat_bepergian === 'tidak';
    $daerahBepergian = $case->lokasi_bepergian ?: $case->riwayat_perjalanan;

    $kontakErat = $case->kontakErat ?? collect();
    // Uraikan NO EPID -> D - [Kd Prov][Kd Kab][Tahun][No urut]
    $epidPrefix = preg_match('/^([A-Z]+)-/', (string) $case->no_registrasi, $m) ? $m[1] : 'D';
    $epidBody = preg_replace('/^[A-Z]+-/', '', (string) $case->no_registrasi);
    $eProv = substr($epidBody, 0, 2); $eKab = substr($epidBody, 2, 2);
    $eThn = substr($epidBody, 4, 2); $eNo = substr($epidBody, 6, 3);
@endphp

{{-- ============ HALAMAN 1 ============ --}}
<div class="page">
    <div class="doc-code">Form DIF-1</div>
    <div class="doc-title">FORMULIR PENYELIDIKAN EPIDEMIOLOGI SUSPEK DIFTERI</div>

    {{-- Provinsi/Kab/Puskesmas + kotak NO EPID --}}
    <table class="lay">
        <tr>
            <td style="width:48%" class="top">
                <table class="lay">
                    <tr><td style="width:80px"><strong>Provinsi</strong></td><td>:</td><td class="val">{{ $case->provinsi ?? 'Kalimantan Timur' }}</td></tr>
                    <tr><td><strong>Kab/Kota</strong></td><td>:</td><td class="val">{{ $case->kab_kota ?? 'Bontang' }}</td></tr>
                    <tr><td><strong>Puskesmas</strong></td><td>:</td><td class="val">{{ $case->instansi_pelapor ?? '' }}</td></tr>
                </table>
            </td>
            <td class="top" style="padding-left:10px;">
                <table class="lay"><tr><td style="vertical-align:top;"><strong>NO EPID:</strong></td>
                <td>
                    <table class="epid">
                        <tr>
                            <td class="plain">{{ $epidPrefix }}</td>
                            <td class="plain">-</td>
                            <td style="background:#bdd7ee;">{{ $eProv }}</td>
                            <td style="background:#f4b084;">{{ $eKab }}</td>
                            <td style="background:#ffe699;">{{ $eThn }}</td>
                            <td style="background:#c6e0b4;">{{ $eNo }}</td>
                        </tr>
                        <tr>
                            <td class="cap"></td><td class="cap"></td>
                            <td class="cap">Kode<br>Provinsi</td><td class="cap">Kode<br>Kab/Kota</td>
                            <td class="cap">Tahun<br>Kasus</td><td class="cap">No. urut<br>(001..)</td>
                        </tr>
                    </table>
                </td></tr></table>
            </td>
        </tr>
    </table>

    {{-- I. Identitas Pelapor --}}
    <div class="sec-title">I. Identitas Pelapor</div>
    <table class="lay">
        <tr><td class="num">1</td><td style="width:180px">Nama</td><td>:</td><td class="val">{{ $case->nama_pelapor ?? '' }}</td></tr>
        <tr><td class="num">2</td><td>Nama Kantor &amp; Jabatan</td><td>:</td><td class="val">{{ trim(($case->instansi_pelapor ?? '').($case->jabatan_pelapor ? ' — '.$case->jabatan_pelapor : '')) }}</td></tr>
        <tr><td class="num">3</td><td>Kabupaten/Kota</td><td>:</td><td class="val">{{ $case->kab_kota ?? 'Bontang' }}</td></tr>
        <tr><td class="num">4</td><td>Provinsi</td><td>:</td><td class="val">{{ $case->provinsi ?? 'Kalimantan Timur' }}</td></tr>
        <tr><td class="num">5</td><td>Tanggal Terima Laporan</td><td>:</td><td class="val">{{ $fmt($case->tanggal_lapor) }}</td></tr>
        {{-- Bag B "Tanggal Penyelidikan" — kolomnya bernama tanggal_penyidikan. --}}
        <tr><td class="num">6</td><td>Tanggal Pelacakan Laporan</td><td>:</td><td class="val">{{ $fmt($case->tanggal_penyidikan) }}</td></tr>
    </table>

    {{-- II. Identitas Penderita --}}
    <div class="sec-title">II. Identitas Penderita</div>
    <table class="lay">
        <tr><td class="num">1.</td><td style="width:170px">Nama</td><td>:</td><td class="val" colspan="3">{{ $case->nama_lengkap }}</td></tr>
        <tr><td class="num">2.</td><td>Nama Orang Tua/KK</td><td>:</td><td class="val" colspan="3">{{ $case->nama_orang_tua ?? '' }}</td></tr>
        <tr>
            <td class="num">3.</td><td>Jenis Kelamin</td><td>:</td>
            <td style="width:18%">{!! $cb($jk==='L') !!} L &nbsp; {!! $cb($jk==='P') !!} P</td>
            <td style="width:12%">Tgl. Lahir :</td><td class="val">{{ $fmt($case->tanggal_lahir) }}</td>
        </tr>
        <tr><td class="num">4.</td><td>Umur</td><td>:</td><td class="val" colspan="3">{{ $umurTahun !== null ? $umurTahun.' tahun' : '' }} {{ $umurBulan !== null ? $umurBulan.' bulan' : '' }}</td></tr>
        <tr><td class="num">5.</td><td>Berat Badan</td><td>:</td><td class="val" colspan="3">{{ $case->berat_badan ?? '' }} <span class="muted">Kg</span></td></tr>
        <tr><td class="num">6.</td><td>Tinggi Badan</td><td>:</td><td class="val" colspan="3">{{ $case->tinggi_badan ?? '' }} <span class="muted">Cm</span></td></tr>
        <tr><td class="num">8.</td><td>Alamat Lengkap</td><td>:</td><td class="val" colspan="3">{{ $case->alamat_lengkap }}</td></tr>
        <tr>
            <td class="num">9.</td><td>Desa/Kelurahan</td><td>:</td><td class="val">{{ $case->kelurahan->name ?? '' }}</td>
            <td>Kecamatan :</td><td class="val">{{ $case->kecamatan->name ?? '' }}</td>
        </tr>
        <tr>
            <td class="num">11.</td><td>Kabupaten/Kota</td><td>:</td><td class="val">{{ $case->kab_kota ?? 'Bontang' }}</td>
            <td>Provinsi :</td><td class="val">{{ $case->provinsi ?? 'Kalimantan Timur' }}</td>
        </tr>
        {{-- 12–21 seluruhnya dari Bag A (identitas), sesuai coretan klien. --}}
        <tr><td class="num">12.</td><td>Tel/HP</td><td>:</td><td class="val" colspan="3">{{ $case->no_telepon ?? '' }}</td></tr>
        <tr><td class="num">13.</td><td>Pekerjaan</td><td>:</td><td class="val" colspan="3">{{ $case->pekerjaan ?? '' }}</td></tr>
        <tr><td class="num">14.</td><td>Alamat Tempat Kerja</td><td>:</td><td class="val" colspan="3">{{ $case->tempat_kerja_sekolah ?? '' }}</td></tr>
        <tr><td class="num">15.</td><td class="top">Orang tua/Wali/Saudara dekat yang dapat dihubungi</td><td class="top">:</td><td class="val" colspan="3">{{ $case->nama_orang_tua ?? '' }}</td></tr>
        <tr><td class="num">16.</td><td>Alamat Lengkap Wali</td><td>:</td><td class="val" colspan="3">{{ $case->alamat_lengkap }}</td></tr>
        <tr><td class="num">21.</td><td>Nomor Telepon/HP Wali</td><td>:</td><td class="val" colspan="3">{{ $case->no_hp_orang_tua ?? '' }}</td></tr>
    </table>

    {{-- III. Riwayat Sakit --}}
    <div class="sec-title">III. Riwayat Sakit</div>
    <table class="lay">
        <tr><td class="num">1</td><td style="width:250px">Tanggal mulai sakit (sakit tenggorokan)</td><td>:</td><td class="val">{{ $fmt($case->tanggal_onset) }}</td></tr>
        <tr><td class="num">2</td><td class="top" colspan="2">Keluhan utama yang mendorong berobat</td><td class="val">{{ $keluhanUtama }}</td></tr>
        <tr><td class="num">3</td><td colspan="3">Gejala dan Tanda Sakit</td></tr>
    </table>
    <table class="lay" style="margin-left:26px;">
        <tr><td style="width:230px">{!! $cb($case->gejala_demam) !!} a) Demam</td><td>Tanggal :</td><td class="val" style="width:35%">{{ $fmt($case->tanggal_demam) }}</td></tr>
        <tr><td>{!! $cb($case->gejala_sakit_tenggorokan) !!} b) Sakit Tenggorokan</td><td>Tanggal :</td><td class="val">{{ $fmt($case->tanggal_sakit_tenggorokan) }}</td></tr>
        <tr><td>{!! $cb($case->gejala_leher_bengkak) !!} c) Leher Bengkak</td><td>Tanggal :</td><td class="val">{{ $fmt($case->tanggal_leher_bengkak) }}</td></tr>
        <tr><td>{!! $cb($case->gejala_sesak_napas) !!} d) Sesak nafas</td><td>Tanggal :</td><td class="val">{{ $fmt($case->tanggal_sesak_nafas) }}</td></tr>
        <tr><td>{!! $cb($case->gejala_pseudomembran) !!} e) Pseudomembran</td><td>Tanggal :</td><td class="val">{{ $fmt($case->tanggal_pseudomembran) }}</td></tr>
        <tr><td colspan="3">f) Gejala lain, sebutkan : <span class="val" style="display:inline-block; min-width:60%;">{{ $case->gejala_lainnya ?? '' }}</span></td></tr>
    </table>
</div>

{{-- ============ HALAMAN 2 ============ --}}
<div class="page">
    <div class="doc-code">Form DIF-1 &nbsp;&mdash;&nbsp; No. EPID: {{ $case->no_registrasi }}</div>

    <table class="lay" style="margin-top:6px;">
        <tr><td class="num">4</td><td colspan="3">Status imunisasi Difteri : &nbsp;
            {!! $cb($imBelum) !!} a. Belum Pernah &nbsp; {!! $cb($imPernah) !!} b. Pernah &nbsp; {!! $cb($imTdkTahu) !!} c. Tidak tahu</td></tr>
    </table>
    {{-- Bag E: slot antigen 1–4 (lihat form-section-e). --}}
    <table class="lay" style="margin-left:26px;">
        <tr><td colspan="4">Jika Pernah :</td></tr>
        <tr><td style="width:36px">1)</td><td style="width:230px">DPT-HB-Hib 1, 2 dan 3</td><td>Tanggal/tahun Pemberian :</td><td class="val">{{ $fmt(optional($imun->get(1))->tanggal_imunisasi) }}</td></tr>
        <tr><td>2)</td><td>DPT-HB-Hib Booster (18 bulan)</td><td>Tanggal/tahun Pemberian :</td><td class="val">{{ $fmt(optional($imun->get(2))->tanggal_imunisasi) }}</td></tr>
        <tr><td>3)</td><td>DT kelas 1</td><td>Tanggal/tahun Pemberian :</td><td class="val">{{ $fmt(optional($imun->get(3))->tanggal_imunisasi) }}</td></tr>
        <tr><td>4)</td><td>TD kelas 2 dan 5</td><td>Tanggal/tahun Pemberian :</td><td class="val">{{ $fmt(optional($imun->get(4))->tanggal_imunisasi ?? $case->tanggal_imunisasi_terakhir) }}</td></tr>
        <tr><td colspan="4">Sumber Informasi : &nbsp;
            {!! $cb(str_contains($sumberImun, 'kms')) !!} a. KMS &nbsp;
            {!! $cb(str_contains($sumberImun, 'kia')) !!} b. Buku KIA &nbsp;
            {!! $cb(str_contains($sumberImun, 'ingatan') || str_contains($sumberImun, 'wawancara')) !!} c. Ingatan responden &nbsp;
            {!! $cb($sumberImun !== '' && !preg_match('/kms|kia|ingatan|wawancara/', $sumberImun)) !!} d. Lain-lain</td></tr>
    </table>

    <table class="lay" style="margin-top:4px;">
        {{-- Bag D2: status gizi (kini tersedia juga saat penyakit Difteri dipilih). --}}
        <tr><td class="num">5</td><td colspan="3">Status Gizi : &nbsp;
            {!! $cb($case->status_gizi === 'buruk') !!} a. Buruk &nbsp;
            {!! $cb($case->status_gizi === 'kurang') !!} b. Kurang &nbsp;
            {!! $cb(in_array($case->status_gizi, ['baik', 'lebih'], true)) !!} c. Baik</td></tr>
        {{-- Bag F: jenis & tanggal spesimen, plus no. kode spesimen dari lab. --}}
        <tr><td class="num">6</td><td colspan="3">Jenis Spesimen yang diambil : &nbsp;
            {!! $cb($spTenggorok && !$spKeduanya) !!} a. Tenggorokan &nbsp;
            {!! $cb($spHidung && !$spKeduanya) !!} b. Hidung &nbsp;
            {!! $cb($spKeduanya) !!} c. Keduanya</td></tr>
        <tr><td class="num">7</td><td style="width:230px">Tanggal pengambilan spesimen</td><td>:</td><td class="val">{{ $fmt($sp1?->tanggal_ambil_spesimen) }} &nbsp; No. Kode Spesimen: {{ $sp1?->no_kode_spesimen ?: '______' }}</td></tr>
        <tr><td class="num">8</td><td>Tanggal pengiriman spesimen</td><td>:</td><td class="val">{{ $fmt($sp1?->tanggal_kirim_sampel) }}</td></tr>
    </table>

    {{-- IV. Riwayat Pengobatan --}}
    <div class="sec-title">IV. Riwayat Pengobatan</div>
    <table class="lay">
        <tr><td class="num">1</td><td colspan="3">Penderita berobat ke :</td></tr>
    </table>
    {{-- Bag G: satu baris per jenis faskes yang benar-benar dikunjungi. --}}
    <table class="lay" style="margin-left:26px;">
        <tr>
            <td style="width:230px">{!! $cb((bool) $fRs) !!} a. Rumah Sakit</td>
            <td>Tanggal :</td><td class="val" style="width:22%">{{ $fmt(optional($fRs)->tanggal_berobat) }}</td>
            <td style="padding-left:8px;">Tracheostomi : {!! $cb($case->tracheostomi === 'ya') !!} Ya &nbsp; {!! $cb($case->tracheostomi === 'tidak') !!} Tidak</td>
        </tr>
        <tr><td>{!! $cb((bool) $fPuskesmas) !!} b. Puskesmas</td><td>Tanggal :</td><td class="val">{{ $fmt(optional($fPuskesmas)->tanggal_berobat) }}</td><td>{{ optional($fPuskesmas)->nama_faskes ?? optional($fRs)->nama_faskes ?? '' }}</td></tr>
        <tr><td>{!! $cb((bool) $fKlinik) !!} c. Dokter Praktek Swasta</td><td>Tanggal :</td><td class="val">{{ $fmt(optional($fKlinik)->tanggal_berobat) }}</td><td></td></tr>
        <tr><td>{!! $cb((bool) $fLainnya) !!} d. Perawat/Mantri/Bidan</td><td>Tanggal :</td><td class="val">{{ $fmt(optional($fLainnya)->tanggal_berobat) }}</td><td></td></tr>
        <tr><td>{!! $cb($tidakBerobat) !!} e. Tidak Berobat</td><td colspan="3"></td></tr>
    </table>
    <table class="lay" style="margin-top:2px;">
        <tr><td class="num">2</td><td style="width:250px">Diagnosis sebagai suspek difteri</td><td>:</td><td>{!! $cb($case->status_kasus !== 'discarded') !!} Ya &nbsp; {!! $cb($case->status_kasus === 'discarded') !!} Tidak</td></tr>
        {{-- Bag D3: antibiotik & ADS — dicentang bila isiannya ada. --}}
        <tr><td class="num">3</td><td>Pemberian antibiotik</td><td>:</td><td>{!! $cb((bool) $case->jenis_antibiotik) !!} Ya &nbsp; {!! $cb(!$case->jenis_antibiotik) !!} Tidak &nbsp; Jenis: <span class="val" style="display:inline-block; min-width:120px;">{{ $case->jenis_antibiotik ?? '' }}</span></td></tr>
        <tr><td class="num">4</td><td>Pemberian ADS</td><td>:</td><td>
            {!! $cb((bool) $case->dosis_ads) !!} Ya, Dosis (IU): <span class="val" style="display:inline-block; min-width:90px;">{{ $case->dosis_ads ?? '' }}</span> &nbsp;
            {!! $cb(!$case->dosis_ads) !!} Tidak, Alasan: ______</td></tr>
        <tr><td class="num">5</td><td>Obat lain</td><td>:</td><td class="val">{{ $case->obat_lainnya ?? '' }}</td></tr>
        <tr><td class="num">6</td><td class="top">Kondisi kasus saat ini</td><td class="top">:</td><td>
            {!! $cb($case->kondisi_akhir==='dalam_perawatan') !!} a. Masih sakit &nbsp;
            {!! $cb($case->kondisi_akhir==='sembuh') !!} b. Sembuh &nbsp;
            {!! $cb($case->kondisi_akhir==='meninggal') !!} c. Meninggal
            @if(in_array($case->kondisi_akhir, ['sembuh','meninggal'])) &nbsp; Tanggal: {{ $fmt($case->tanggal_kondisi_akhir) }} @endif
        </td></tr>
    </table>

    {{-- IV. Riwayat Kontak (penomoran ganda mengikuti formulir asli) --}}
    <div class="sec-title">IV. Riwayat Kontak</div>
    <table class="lay">
        {{-- Bag E: riwayat bepergian. --}}
        <tr><td class="num">1.</td><td class="top" colspan="3">Dalam 10 hari terakhir sebelum sakit sampai 2 hari setelah minum antibiotik, apakah penderita pernah bepergian?<br>
            {!! $cb($pernahBepergian) !!} [a] Pernah &nbsp; {!! $cb($tidakBepergian) !!} [b] Tidak pernah &nbsp; {!! $cb(!$pernahBepergian && !$tidakBepergian) !!} [c] Tidak jelas
            &nbsp; Jika pernah, daerah: <span class="val" style="display:inline-block; min-width:35%;">{{ $daerahBepergian ?? '' }}</span>
            &nbsp; Tanggal: <span class="val" style="display:inline-block; min-width:14%;">{{ $fmt($case->tanggal_bepergian) }}</span></td></tr>
        {{-- Bag E: kontak dengan kasus serupa. --}}
        <tr><td class="num">2.</td><td class="top" colspan="3">Pernah berkunjung ke rumah teman/saudara yang sehat atau sakit/meninggal dengan gejala yang sama?<br>
            {!! $cb((bool) $case->riwayat_kontak_kasus) !!} [a] Pernah &nbsp; {!! $cb(!$case->riwayat_kontak_kasus) !!} [b] Tidak pernah &nbsp; {!! $cb(false) !!} [c] Tidak jelas
            &nbsp; Jika pernah, nama &amp; alamat: <span class="val" style="display:inline-block; min-width:35%;">{{ $case->riwayat_perjalanan ?? '' }}</span></td></tr>
    </table>
</div>

{{-- ============ HALAMAN 3 ============ --}}
<div class="page">
    <div class="doc-code">Form DIF-1 &nbsp;&mdash;&nbsp; No. EPID: {{ $case->no_registrasi }}</div>
    <div class="sec-title">V. Kontak Kasus</div>
    <p style="font-size:8.5pt; text-align:justify; margin-bottom:6px;">
        Kontak kasus adalah mereka yang pernah kontak dengan penderita difteri sejak 10 hari sebelum timbul gejala sakit
        tenggorok sampai 2 hari setelah pengobatan (masa penularan): tinggal satu rumah/asrama, tetangga/kerabat/pengasuh,
        teman kelas/bermain/guru, teman kerja, petugas kesehatan yang merawat kasus.
    </p>
    <table class="grid" style="font-size:8.5pt;">
        <thead><tr style="font-weight:bold; text-align:center;">
            <td style="width:5%">No</td><td style="width:28%">Nama</td><td style="width:9%">Umur (thn)</td>
            <td style="width:26%">Alamat</td><td style="width:15%">Hub. dgn Kasus</td>
            <td>Berapa kali imunisasi Difteri (DPT-HB-HiB/DT/Td)</td>
        </tr></thead>
        <tbody>
            @php $rows = max(14, $kontakErat->count()); @endphp
            @for($r = 0; $r < $rows; $r++)
            @php $k = $kontakErat->get($r); @endphp
            <tr style="height:20px;">
                <td style="text-align:center;">{{ $r + 1 }}</td>
                <td>{{ $k->nama ?? '' }}</td>
                <td style="text-align:center;">{{ $k && $k->tanggal_lahir ? (int) $k->tanggal_lahir->diffInYears(now()) : '' }}</td>
                <td>{{ $k->alamat ?? '' }}</td>
                <td>{{ $k->hubungan ?? '' }}</td>
                {{-- Bag I: "Status Imunisasi <penyakit>" per kontak. --}}
                <td style="text-align:center;">{{ $k?->jumlah_imunisasi_campak_rubella }}</td>
            </tr>
            @endfor
        </tbody>
    </table>

    <div style="margin-top:12px; text-align:right; font-size:8pt; color:#555;">
        Dicetak: {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp; {{ $case->nama_pelapor ?? '' }}
    </div>
</div>
</body>
</html>
