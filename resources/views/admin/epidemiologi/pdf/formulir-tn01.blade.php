<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>TN-01 - {{ $case->no_registrasi }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 8.5pt; color: #000; line-height: 1.25; }
        .page { padding: 16px 20px; }
        .page + .page { page-break-before: always; }
        .doc-code { font-weight: bold; font-size: 9pt; }
        .doc-title { text-align: center; font-weight: bold; font-size: 10.5pt; margin: 8px 0 10px; }

        table { width: 100%; border-collapse: collapse; }
        .t { border: 1px solid #000; }
        .t td, .t th { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
        .lbl { font-weight: bold; }
        .no { width: 5%; text-align: center; }
        .q  { width: 42%; }
        .sh { font-weight: bold; text-align: center; padding: 3px; border: 1px solid #000; }
        .sh-orange { background: #f4b084; } .sh-blue { background: #bdd7ee; }
        .sh-green  { background: #c6efce; } .sh-yellow { background: #ffe699; }

        .rb { display: inline-block; width: 10px; height: 10px; border: 1px solid #000; border-radius: 50%; vertical-align: middle; margin-right: 2px; }
        .rb-on { background: #000; }
        .muted { color: #888; }
        .stop { font-weight: bold; }
    </style>
</head>
<body>
@php
    $rb = fn($v) => $v ? '<span class="rb rb-on"></span>' : '<span class="rb"></span>';
    $fmt = fn($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d-M-Y') : '';
    $jk = strtoupper(substr((string) $case->jenis_kelamin, 0, 1));
    $isRawat = ($case->status_rawat === 'rawat_inap') || (bool) $case->nama_faskes_rawat;
    $meninggal = $case->kondisi_akhir === 'meninggal';
    $sembuh = $case->kondisi_akhir === 'sembuh';
    $sumber = (str_starts_with(strtoupper($case->instansi_pelapor ?? ''), 'RS') || $case->petugasInput?->faskes_type === 'rs') ? 'RS' : 'Puskesmas';
    // Nilai tersimpan lebih dipercaya daripada hitungan tanggal.
    $umurMeninggalHari = $case->umur_bayi_meninggal_hari
        ?? (($meninggal && $case->tanggal_lahir && $case->tanggal_kondisi_akhir)
            ? (int) \Illuminate\Support\Carbon::parse($case->tanggal_lahir)->diffInDays($case->tanggal_kondisi_akhir)
            : null);

    // Beberapa isian Bagian TN dulu berupa teks bebas sebelum diubah jadi pilihan,
    // jadi pencocokan radio dilakukan per kata kunci — data lama tetap terbaca.
    $cocok = function (?string $nilai, array $kunci): bool {
        if (!$nilai) return false;
        $n = strtolower($nilai);
        foreach ($kunci as $k) {
            if (str_contains($n, $k)) return true;
        }
        return false;
    };

    // Petugas pelaksana = pelapor kasus (Bag B), bukan user yang membuka export.
    $petugas = $case->nama_pelapor ?: ($case->petugasInput->name ?? '');
@endphp

{{-- ============ HALAMAN 1 ============ --}}
<div class="page">
    <div class="doc-code">Form TN-01</div>
    <div class="doc-title">FORM PELACAKAN KASUS SUSPEK TETANUS NEONATORUM</div>

    <table class="t">
        <tr>
            <td class="lbl" style="width:16%">Provinsi</td><td style="width:30%">{{ $case->provinsi ?? 'Kalimantan Timur' }}</td>
            <td class="lbl" style="width:16%">Nomor EPID</td><td>{{ $case->no_registrasi }}</td>
        </tr>
        <tr>
            <td class="lbl">Kabupaten</td><td>{{ $case->kab_kota ?? 'Bontang' }}</td>
            <td class="lbl">Nama Unit Pelapor</td><td>{{ $case->instansi_pelapor ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">Sumber Laporan</td><td>{{ $sumber }}</td>
            <td class="lbl">Tanggal Terima Laporan</td><td>{{ $fmt($case->tanggal_lapor) }}</td>
        </tr>
        <tr>
            {{-- Bag B "Tanggal Penyelidikan" — kolomnya bernama tanggal_penyidikan. --}}
            <td class="lbl">Tanggal Pelacakan</td><td>{{ $fmt($case->tanggal_penyidikan) }}</td>
            <td colspan="2"></td>
        </tr>
    </table>

    {{-- IDENTITAS BAYI DAN IBU --}}
    <table class="t" style="margin-top:-1px;">
        <tr><td colspan="4" class="sh sh-orange">IDENTITAS BAYI DAN IBU</td></tr>
        <tr>
            <td class="lbl" style="width:16%">Nama Bayi</td><td style="width:34%">{{ $case->nama_lengkap }}</td>
            {{-- "Anak ke-" belum punya kolom di aplikasi: dikosongkan untuk diisi manual. --}}
            <td class="lbl" style="width:16%">Jenis Kelamin / Anak ke-</td><td>{{ $jk }} &nbsp;|&nbsp; </td>
        </tr>
        <tr>
            {{-- Bag A "Nama Orang Tua". Usia/pekerjaan/pendidikan ibu belum ada kolomnya. --}}
            <td class="lbl">Nama Ibu</td><td>{{ $case->nama_orang_tua ?? '' }}</td>
            <td class="lbl">Usia Ibu / Pekerjaan / Pendidikan</td><td></td>
        </tr>
        <tr><td class="lbl">Alamat</td><td colspan="3">{{ $case->alamat_lengkap }}</td></tr>
        <tr>
            <td class="lbl">Desa/Kelurahan</td><td>{{ $case->kelurahan->name ?? '' }}</td>
            <td class="lbl">Kecamatan</td><td>{{ $case->kecamatan->name ?? '' }}</td>
        </tr>
        <tr><td class="lbl">Sudah berapa lama Ibu tinggal di desa ini?</td><td colspan="3">{{ $case->lama_tinggal_desa ?? '' }}</td></tr>
    </table>

    {{-- INFORMASI KELAHIRAN BAYI --}}
    <table class="t" style="margin-top:-1px;">
        <tr><td colspan="3" class="sh sh-blue">INFORMASI KELAHIRAN BAYI</td></tr>
        <tr><td class="no">1</td><td class="q">Apakah bayi lahir hidup?</td><td>{!! $rb($case->bayi_lahir_hidup === 'ya') !!} a. Ya &nbsp; {!! $rb($case->bayi_lahir_hidup === 'tidak') !!} b. Tidak &nbsp; <span class="stop">&raquo; bila tidak, Stop Pelacakan</span></td></tr>
        <tr><td class="no">2</td><td class="q">Tanggal lahir bayi / Tanggal mulai sakit</td><td>Lahir: {{ $fmt($case->tanggal_lahir) }} &nbsp;|&nbsp; Mulai sakit: {{ $fmt($case->tanggal_onset) }}</td></tr>
        <tr><td class="no">3</td><td class="q">Bila bayi meninggal, tanggal meninggal / umur (hari)</td><td>{{ $meninggal ? $fmt($case->tanggal_kondisi_akhir) : '' }} @if($umurMeninggalHari !== null) &nbsp;|&nbsp; {{ $umurMeninggalHari }} hari @endif</td></tr>
        @foreach([
            ['no' => 4, 'q' => 'Waktu lahir apakah bayi menangis?', 'v' => $case->bayi_menangis_lahir, 'stop' => ''],
            ['no' => 5, 'q' => 'Bila no.4 tidak tahu, apakah terlihat tanda kelahiran hidup (mis. gerakan)?', 'v' => $case->tanda_kelahiran_hidup, 'stop' => ''],
            ['no' => 6, 'q' => 'Setelah lahir apakah bayi bisa menyusu/minum dengan baik?', 'v' => $case->bayi_bisa_menyusu, 'stop' => '&raquo; bila tidak, Stop'],
            ['no' => 7, 'q' => 'Apakah 3 hari kemudian tiba-tiba mulut bayi mencucu dan tidak bisa menyusu?', 'v' => $case->bayi_mulut_mencucu, 'stop' => '&raquo; bila tidak, Stop'],
            ['no' => 8, 'q' => 'Apakah bayi mudah kejang jika disentuh/terkena sinar atau mendengar bunyi?', 'v' => $case->bayi_mudah_kejang, 'stop' => ''],
        ] as $row)
        <tr><td class="no">{{ $row['no'] }}</td><td class="q">{{ $row['q'] }}</td>
            <td>{!! $rb($row['v'] === 'ya') !!} a. Ya &nbsp; {!! $rb($row['v'] === 'tidak') !!} b. Tidak &nbsp; {!! $rb($row['v'] === 'tidak_tahu') !!} c. Tidak Tahu
                @if($row['stop']) &nbsp; <span class="stop">{!! $row['stop'] !!}</span> @endif</td></tr>
        @endforeach
        <tr><td class="no">9</td><td class="q">Apakah bayi dirawat? (tempat &amp; tanggal)</td><td>{!! $rb($isRawat) !!} a. Ya &nbsp; {!! $rb(!$isRawat) !!} b. Tidak &nbsp; Tempat: {{ $case->nama_faskes_rawat ?? '' }} &nbsp; Tgl: {{ $fmt($case->tanggal_masuk_rawat) }}</td></tr>
        <tr><td class="no">10</td><td class="q">Keadaan bayi setelah dirawat</td><td>{!! $rb($sembuh) !!} a. Sembuh &nbsp; {!! $rb($meninggal) !!} b. Meninggal</td></tr>
    </table>
</div>

{{-- ============ HALAMAN 2 ============ --}}
<div class="page">
    <div class="doc-code">Form TN-01 &nbsp;&mdash;&nbsp; No. EPID: {{ $case->no_registrasi }}</div>

    <table class="t" style="margin-top:6px;">
        <tr><td colspan="3" class="sh sh-yellow">RIWAYAT PEMERIKSAAN KEHAMILAN IBU</td></tr>
        <tr><td class="no">11</td><td class="q">Berapa kali kunjungan ibu hamil (antenatal care)?</td>
            <td>{{ $case->jumlah_kunjungan_anc !== null ? $case->jumlah_kunjungan_anc.' kali' : '…… kali' }}</td></tr>
        <tr><td class="no">12</td><td class="q">Tempat pemeriksaan Ibu Hamil</td>
            <td>{{ $case->tempat_pemeriksaan_hamil ?: 'RS/Puskesmas ……' }}</td></tr>
        <tr><td class="no">13</td><td class="q">Pemeriksaan kehamilan oleh</td><td>
            {!! $rb($cocok($case->pemeriksa_kehamilan, ['dokter'])) !!} a. Dokter &nbsp;
            {!! $rb($cocok($case->pemeriksa_kehamilan, ['bidan', 'perawat'])) !!} b. Bidan/Perawat &nbsp;
            {!! $rb($cocok($case->pemeriksa_kehamilan, ['lain'])) !!} c. Lainnya</td></tr>
    </table>

    <table class="t" style="margin-top:-1px;">
        <tr><td colspan="3" class="sh sh-blue">RIWAYAT PERSALINAN</td></tr>
        <tr><td class="no">14</td><td class="q">Tempat persalinan</td><td>
            {!! $rb($cocok($case->tempat_persalinan, ['rs', 'rumah sakit'])) !!} RS &nbsp;
            {!! $rb($cocok($case->tempat_persalinan, ['puskesmas'])) !!} Puskesmas &nbsp;
            {!! $rb($cocok($case->tempat_persalinan, ['lain', 'rumah', 'klinik'])) !!} Lainnya</td></tr>
        <tr><td class="no">15</td><td class="q">Usia kehamilan ibu saat persalinan</td>
            <td>{{ $case->usia_kehamilan_bulan !== null ? $case->usia_kehamilan_bulan.' bulan' : '' }}</td></tr>
        <tr><td class="no">16</td><td class="q">Penolong persalinan</td><td>
            {!! $rb($cocok($case->penolong_persalinan, ['dokter'])) !!} a. Dokter &nbsp;
            {!! $rb($cocok($case->penolong_persalinan, ['bidan', 'perawat'])) !!} b. Bidan/Perawat &nbsp;
            {!! $rb($cocok($case->penolong_persalinan, ['lain', 'dukun'])) !!} c. Lainnya</td></tr>
        <tr><td class="no">17</td><td class="q">Alat potong tali pusat</td><td>
            {!! $rb($cocok($case->alat_potong_tali_pusat, ['gunting'])) !!} a. Gunting &nbsp;
            {!! $rb($cocok($case->alat_potong_tali_pusat, ['silet'])) !!} b. Silet &nbsp;
            {!! $rb($cocok($case->alat_potong_tali_pusat, ['pisau'])) !!} c. Pisau &nbsp;
            {!! $rb($cocok($case->alat_potong_tali_pusat, ['sembilu'])) !!} d. Sembilu &nbsp;
            {!! $rb($cocok($case->alat_potong_tali_pusat, ['tidak_tahu', 'tidak tahu'])) !!} e. Tidak tahu &nbsp;
            {!! $rb($cocok($case->alat_potong_tali_pusat, ['lain'])) !!} f. Lainnya</td></tr>
        <tr><td class="no">18</td><td class="q">Perawatan tali pusat</td><td>
            {!! $rb($cocok($case->perawatan_tali_pusat, ['alkohol'])) !!} a. Alkohol &nbsp;
            {!! $rb($cocok($case->perawatan_tali_pusat, ['betadine', 'yodium', 'iodium'])) !!} b. Betadine/Yodium &nbsp;
            {!! $rb($cocok($case->perawatan_tali_pusat, ['ramuan', 'tradisional'])) !!} c. Ramuan tradisional</td></tr>
        <tr><td class="no">19</td><td class="q">Keadaan ibu saat ini</td><td>
            {!! $rb($cocok($case->keadaan_ibu_saat_ini, ['hidup', 'sehat'])) !!} a. Hidup &nbsp;
            {!! $rb($cocok($case->keadaan_ibu_saat_ini, ['meninggal', 'wafat'])) !!} b. Meninggal</td></tr>
    </table>

    <table class="t" style="margin-top:-1px;">
        <tr><td colspan="3" class="sh sh-green">RIWAYAT IMUNISASI IBU</td></tr>
        <tr><td class="no">20</td><td class="q">Sumber informasi</td><td>{!! $rb(false) !!} a. Buku KIA/imunisasi &nbsp; {!! $rb(false) !!} b. Ingatan responden</td></tr>
        <tr><td class="no">21</td><td class="q">Ibu mendapat imunisasi Td pada kehamilan ini? (berapa kali, tanggal)</td><td>{!! $rb(false) !!} a. Ya &nbsp; {!! $rb(false) !!} b. Tidak &nbsp; <span class="muted">…… kali</span></td></tr>
        <tr><td class="no">22</td><td class="q">Ibu mendapat imunisasi Td pada kehamilan sebelumnya?</td><td>{!! $rb(false) !!} a. Ya &nbsp; {!! $rb(false) !!} b. Tidak</td></tr>
        <tr><td class="no">23</td><td class="q">Ibu mendapat imunisasi Td calon pengantin? (tanggal)</td><td>{!! $rb(false) !!} a. Ya &nbsp; {!! $rb(false) !!} b. Tidak</td></tr>
        <tr><td class="no">24</td><td class="q">Riwayat imunisasi sebelumnya (DPT-HB-HiB 1–4, DT kls 1, Td kls 2 &amp; 5)</td><td><span class="muted">Tahun/tanggal pemberian ……</span></td></tr>
        <tr><td class="no">25</td><td class="q">Status T ibu hamil saat ini</td><td>{!! $rb(false) !!} T1 &nbsp; {!! $rb(false) !!} T2 &nbsp; {!! $rb(false) !!} T3 &nbsp; {!! $rb(false) !!} T4 &nbsp; {!! $rb(false) !!} T5</td></tr>
    </table>
</div>

{{-- ============ HALAMAN 3 ============ --}}
<div class="page">
    <div class="doc-code">Form TN-01 &nbsp;&mdash;&nbsp; No. EPID: {{ $case->no_registrasi }}</div>

    <table class="t" style="margin-top:6px;">
        <tr><td colspan="3" class="sh sh-yellow">RESPON KASUS</td></tr>
        <tr><td class="no">26</td><td class="q">Ibu mendapatkan vaksin Td pada saat investigasi kasus?</td><td>{!! $rb(false) !!} a. Ya &nbsp; {!! $rb(false) !!} b. Tidak &nbsp; {!! $rb(false) !!} c. Tidak perlu/sudah protected &nbsp; {!! $rb(false) !!} d. Tidak Tahu</td></tr>
        <tr><td class="no">27</td><td class="q">Tanggal pemberian vaksin</td><td></td></tr>
    </table>

    <table class="t" style="margin-top:-1px;">
        <tr><td colspan="3" class="sh sh-green">INFORMASI LAIN</td></tr>
        <tr><td class="no">28</td><td class="q">Cakupan imunisasi Td di desa/Puskesmas kasus TN (DPT-HB-Hib 1/2/3, DT kls 1, Td kls 2/5, TT2+)</td><td><span class="muted">…… %</span></td></tr>
        <tr><td class="no">29</td><td class="q">Cakupan persalinan di Fasilitas Kesehatan</td><td></td></tr>
        <tr><td class="no">30</td><td class="q">Cakupan kunjungan neonatus (KN1, KN2, KN3)</td><td><span class="muted">…… %</span></td></tr>
        <tr><td class="no">31</td><td class="q">Apakah desa kasus TN mudah dijangkau dari fasilitas kesehatan? Jelaskan</td><td></td></tr>
        <tr><td class="no">32</td><td class="q">Faktor lain yang berpengaruh terhadap pelaksanaan imunisasi? Jelaskan</td><td></td></tr>
        <tr><td class="no">33</td><td class="q">Faktor lain yang berpengaruh terhadap proses pertolongan persalinan? Jelaskan</td><td></td></tr>
    </table>

    <div style="margin-top:26px; text-align:center;">
        <div style="font-weight:bold;">Petugas Pelaksana Investigasi</div>
        <div style="margin-top:44px;">( {{ $petugas ?: '________________' }} )</div>
        <div>No. Kontak : {{ $case->telepon_pelapor ?? '' }}</div>
    </div>

    <div style="margin-top:14px; text-align:right; font-size:8pt; color:#555;">
        Dicetak: {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
</body>
</html>
