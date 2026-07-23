<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kasus Surveillance - {{ $case->no_registrasi }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            padding: 15px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        .header h2 {
            font-size: 14px;
            font-weight: normal;
        }
        .header p {
            font-size: 10px;
            color: #666;
        }
        .section {
            margin-bottom: 12px;
            border: 1px solid #ddd;
        }
        .section-header {
            background: #f5f5f5;
            padding: 5px 10px;
            font-weight: bold;
            font-size: 11px;
            border-bottom: 1px solid #ddd;
        }
        .section-header.primary { background: #007bff; color: white; }
        .section-header.info { background: #17a2b8; color: white; }
        .section-header.warning { background: #ffc107; color: #333; }
        .section-header.danger { background: #dc3545; color: white; }
        .section-header.success { background: #28a745; color: white; }
        .section-header.dark { background: #343a40; color: white; }
        .section-header.secondary { background: #6c757d; color: white; }
        .section-body {
            padding: 8px 10px;
        }
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -5px;
        }
        .col-4 { width: 33.33%; padding: 0 5px; }
        .col-6 { width: 50%; padding: 0 5px; }
        .col-12 { width: 100%; padding: 0 5px; }
        .field {
            margin-bottom: 5px;
        }
        .field-label {
            font-weight: bold;
            color: #555;
            font-size: 10px;
        }
        .field-value {
            font-size: 11px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 9px;
            border-radius: 3px;
            color: white;
        }
        .badge-primary { background: #007bff; }
        .badge-success { background: #28a745; }
        .badge-danger { background: #dc3545; }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-info { background: #17a2b8; }
        .badge-secondary { background: #6c757d; }
        .symptoms-grid {
            display: flex;
            flex-wrap: wrap;
        }
        .symptom-item {
            width: 25%;
            padding: 2px 5px;
            font-size: 10px;
        }
        .symptom-item.active {
            font-weight: bold;
            color: #dc3545;
        }
        .symptom-item.inactive {
            color: #999;
        }
        .contact-stats {
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        .contact-stat {
            padding: 5px 15px;
        }
        .contact-stat-number {
            font-size: 18px;
            font-weight: bold;
        }
        .contact-stat-label {
            font-size: 9px;
            color: #666;
        }
        .status-box {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .status-item {
            text-align: center;
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            flex: 1;
            margin: 0 5px;
        }
        .status-item-label {
            font-size: 9px;
            color: #666;
        }
        .status-item-value {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            color: #666;
            display: flex;
            justify-content: space-between;
        }
        @media print {
            body { padding: 10px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 15px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer;">
            Cetak / Print
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; margin-left: 10px;">
            Tutup
        </button>
    </div>

    <div class="header">
        <h1>LAPORAN KASUS SURVEILLANCE EPIDEMIOLOGI</h1>
        <h2>Sistem Informasi Realtime Reporting Terpadu — Dinas Kesehatan</h2>
        <p>Dicetak pada: {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>

    <!-- Status Overview -->
    <div class="status-box">
        <div class="status-item">
            <div class="status-item-label">No. Epid</div>
            <div class="status-item-value">{{ $case->no_registrasi ?: '-' }}</div>
        </div>
        <div class="status-item">
            <div class="status-item-label">Status Kasus</div>
            <div class="status-item-value">{{ $case->status_kasus }}</div>
        </div>
        <div class="status-item">
            <div class="status-item-label">Status Lab</div>
            <div class="status-item-value">{{ str_replace('_', ' ', $case->status_lab) }}</div>
        </div>
        <div class="status-item">
            <div class="status-item-label">Kondisi Akhir</div>
            <div class="status-item-value">{{ str_replace('_', ' ', $case->kondisi_akhir) }}</div>
        </div>
    </div>

    <!-- Section A: Patient Identity -->
    <div class="section">
        <div class="section-header primary">A. IDENTITAS PASIEN</div>
        <div class="section-body">
            <div class="row">
                <div class="col-4">
                    <div class="field">
                        <div class="field-label">NIK</div>
                        <div class="field-value">{{ $case->nik }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Nama Lengkap</div>
                        <div class="field-value"><strong>{{ $case->nama_lengkap }}</strong></div>
                    </div>
                    <div class="field">
                        <div class="field-label">Tanggal Lahir</div>
                        <div class="field-value">{{ $case->tanggal_lahir->format('d/m/Y') }}</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="field">
                        <div class="field-label">Umur</div>
                        <div class="field-value">{{ $case->umur }} tahun ({{ ucfirst($case->kategori_umur) }})</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Jenis Kelamin</div>
                        <div class="field-value">{{ $case->jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan' }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">No. Telepon</div>
                        <div class="field-value">{{ $case->no_telepon ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="field">
                        <div class="field-label">Kecamatan</div>
                        <div class="field-value">{{ $case->kecamatan->name ?? '-' }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Kelurahan</div>
                        <div class="field-value">{{ $case->kelurahan->name ?? '-' }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">RT</div>
                        <div class="field-value">{{ $case->rt->name ?? '-' }}</div>
                    </div>
                </div>
            </div>
            <div class="field">
                <div class="field-label">Alamat Lengkap</div>
                <div class="field-value">{{ $case->alamat_lengkap }}</div>
            </div>
        </div>
    </div>

    <!-- Section B: Reporter Identity -->
    <div class="section">
        <div class="section-header info">B. IDENTITAS PELAPOR</div>
        <div class="section-body">
            <div class="row">
                <div class="col-6">
                    <div class="field">
                        <div class="field-label">Nama Pelapor</div>
                        <div class="field-value">{{ $case->nama_pelapor }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Jabatan</div>
                        <div class="field-value">{{ $case->jabatan_pelapor ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="field">
                        <div class="field-label">Instansi</div>
                        <div class="field-value">{{ $case->instansi_pelapor ?? '-' }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Telepon</div>
                        <div class="field-value">{{ $case->telepon_pelapor ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section C: Case Data -->
    <div class="section">
        <div class="section-header warning">C. DATA KASUS</div>
        <div class="section-body">
            <div class="row">
                <div class="col-6">
                    <div class="field">
                        <div class="field-label">Jenis Penyakit</div>
                        <div class="field-value"><strong>{{ $case->jenisKasus->nama_penyakit ?? '-' }}</strong> ({{ $case->jenisKasus->kode_penyakit ?? '' }})</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Kode ICD-10</div>
                        <div class="field-value">{{ $case->kode_icd10 ?? '-' }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Tanggal Onset</div>
                        <div class="field-value"><strong>{{ $case->tanggal_onset->format('d/m/Y') }}</strong></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="field">
                        <div class="field-label">Tanggal Lapor</div>
                        <div class="field-value">{{ $case->tanggal_lapor?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Sumber Penularan</div>
                        <div class="field-value">{{ ucfirst($case->sumber_penularan) }} - {{ $case->lokasi_penularan ?? 'Tidak diketahui' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section D: Symptoms -->
    <div class="section">
        <div class="section-header danger">D. GEJALA KLINIS ({{ $case->getSymptomCount() }} gejala)</div>
        <div class="section-body">
            @php
                $symptoms = $case->getSymptoms();
                $symptomLabels = [
                    'demam' => 'Demam', 'batuk' => 'Batuk', 'pilek' => 'Pilek',
                    'sakit_kepala' => 'Sakit Kepala', 'mual' => 'Mual', 'muntah' => 'Muntah',
                    'diare' => 'Diare', 'ruam' => 'Ruam', 'sesak_napas' => 'Sesak Napas',
                    'nyeri_otot' => 'Nyeri Otot', 'nyeri_sendi' => 'Nyeri Sendi', 'lemas' => 'Lemas',
                    'kehilangan_nafsu_makan' => 'Hilang Nafsu Makan', 'mata_merah' => 'Mata Merah',
                    'pembengkakan_kelenjar' => 'Bengkak Kelenjar', 'kejang' => 'Kejang',
                    'penurunan_kesadaran' => 'Penurunan Kesadaran',
                ];
            @endphp
            <div class="symptoms-grid">
                @foreach($symptomLabels as $key => $label)
                    <div class="symptom-item {{ $symptoms[$key] ? 'active' : 'inactive' }}">
                        {{ $symptoms[$key] ? '[X]' : '[ ]' }} {{ $label }}
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Section E: History -->
    <div class="section">
        <div class="section-header secondary">E. RIWAYAT</div>
        <div class="section-body">
            <div class="row">
                <div class="col-6">
                    <div class="field">
                        <div class="field-label">Riwayat Perjalanan</div>
                        <div class="field-value">{{ $case->riwayat_perjalanan ?? 'Tidak ada' }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Kontak dengan Kasus</div>
                        <div class="field-value">{{ $case->riwayat_kontak_kasus ? 'Ya' : 'Tidak' }}</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="field">
                        <div class="field-label">Status Imunisasi</div>
                        <div class="field-value">{{ ucfirst(str_replace('_', ' ', $case->riwayat_imunisasi)) }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Tanggal Imunisasi Terakhir</div>
                        <div class="field-value">{{ $case->tanggal_imunisasi_terakhir ? $case->tanggal_imunisasi_terakhir->format('d/m/Y') : '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section F: Laboratory -->
    <div class="section">
        <div class="section-header success">F. PEMERIKSAAN LABORATORIUM</div>
        <div class="section-body">
            <div class="row">
                <div class="col-6">
                    <div class="field">
                        <div class="field-label">Status Lab</div>
                        <div class="field-value"><span class="badge badge-{{ $case->status_lab == 'positif' ? 'danger' : ($case->status_lab == 'negatif' ? 'success' : 'secondary') }}">{{ ucfirst(str_replace('_', ' ', $case->status_lab)) }}</span></div>
                    </div>
                    <div class="field">
                        <div class="field-label">Tanggal Pengambilan Spesimen</div>
                        <div class="field-value">{{ $case->tanggal_pengambilan_spesimen ? $case->tanggal_pengambilan_spesimen->format('d/m/Y') : '-' }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Jenis Spesimen</div>
                        <div class="field-value">{{ $case->jenis_spesimen ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="field">
                        <div class="field-label">Tanggal Hasil Lab</div>
                        <div class="field-value">{{ $case->tanggal_hasil_lab ? $case->tanggal_hasil_lab->format('d/m/Y') : '-' }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Hasil Laboratorium</div>
                        <div class="field-value">{{ $case->hasil_lab ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section G: Management -->
    <div class="section">
        <div class="section-header primary">G. TATALAKSANA</div>
        <div class="section-body">
            <div class="row">
                <div class="col-6">
                    <div class="field">
                        <div class="field-label">Status Perawatan</div>
                        <div class="field-value"><span class="badge badge-info">{{ ucfirst(str_replace('_', ' ', $case->status_rawat)) }}</span></div>
                    </div>
                    <div class="field">
                        <div class="field-label">Nama Faskes</div>
                        <div class="field-value"><strong>{{ $case->nama_faskes_rawat }}</strong></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="field">
                        <div class="field-label">Tanggal Masuk - Keluar</div>
                        <div class="field-value">{{ $case->tanggal_masuk_rawat ? $case->tanggal_masuk_rawat->format('d/m/Y') : '-' }} s/d {{ $case->tanggal_keluar_rawat ? $case->tanggal_keluar_rawat->format('d/m/Y') : '-' }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Lama Rawat</div>
                        <div class="field-value">{{ $case->lama_rawat ? $case->lama_rawat . ' hari' : '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section H: Final Status -->
    <div class="section">
        <div class="section-header dark">H. STATUS AKHIR</div>
        <div class="section-body">
            <div class="row">
                <div class="col-6">
                    <div class="field">
                        <div class="field-label">Kondisi Akhir</div>
                        <div class="field-value"><span class="badge badge-{{ $case->kondisi_akhir == 'sembuh' ? 'success' : ($case->kondisi_akhir == 'meninggal' ? 'danger' : 'warning') }}">{{ ucfirst(str_replace('_', ' ', $case->kondisi_akhir)) }}</span></div>
                    </div>
                    <div class="field">
                        <div class="field-label">Tanggal Kondisi Akhir</div>
                        <div class="field-value">{{ $case->tanggal_kondisi_akhir ? $case->tanggal_kondisi_akhir->format('d/m/Y') : '-' }}</div>
                    </div>
                </div>
                <div class="col-6">
                    @if($case->kondisi_akhir == 'meninggal')
                    <div class="field">
                        <div class="field-label">Penyebab Kematian</div>
                        <div class="field-value">{{ $case->penyebab_kematian ?? '-' }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Section I: Contact Investigation -->
    <div class="section">
        <div class="section-header info">I. INVESTIGASI KONTAK</div>
        <div class="section-body">
            <div class="contact-stats">
                <div class="contact-stat">
                    <div class="contact-stat-number">{{ $case->jumlah_kontak_serumah }}</div>
                    <div class="contact-stat-label">Kontak Serumah</div>
                </div>
                <div class="contact-stat">
                    <div class="contact-stat-number">{{ $case->jumlah_kontak_diluar_rumah }}</div>
                    <div class="contact-stat-label">Kontak Luar Rumah</div>
                </div>
                <div class="contact-stat">
                    <div class="contact-stat-number">{{ $case->jumlah_kontak_bergejala }}</div>
                    <div class="contact-stat-label">Kontak Bergejala</div>
                </div>
            </div>
            <div class="field" style="margin-top: 10px;">
                <div class="field-label">Tindak Lanjut</div>
                <div class="field-value">{{ $case->tindak_lanjut_kontak ?? 'Tidak ada catatan' }}</div>
            </div>
        </div>
    </div>

    <!-- Section J: Notes -->
    <div class="section">
        <div class="section-header secondary">J. CATATAN TAMBAHAN</div>
        <div class="section-body">
            <div class="field-value">{{ $case->catatan_tambahan ?? 'Tidak ada catatan' }}</div>
        </div>
    </div>

    <div class="footer">
        <div>
            <strong>Petugas Input:</strong> {{ $case->petugasInput->name ?? 'Unknown' }}<br>
            <strong>Dibuat:</strong> {{ $case->created_at->format('d/m/Y H:i') }}
        </div>
        <div style="text-align: right;">
            <strong>Terakhir Diubah:</strong> {{ $case->updated_at->format('d/m/Y H:i') }}<br>
            <strong>Oleh:</strong> {{ $case->updater->name ?? 'Unknown' }}
        </div>
    </div>
</body>
</html>
