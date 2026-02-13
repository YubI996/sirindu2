@extends('admin::layouts.app')
@section('title') Admin @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Surveillance @endsection
@section('item-active') Detail Kasus @endsection

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">
            <i class="fa fa-file-medical mr-2"></i>
            Detail Kasus Surveillance
        </h2>
        <div>
            <a href="{{ route('admin.epidemiologi.edit', $case->id) }}" class="btn btn-warning">
                <i class="fa fa-edit"></i> Edit
            </a>
            <a href="{{ route('admin.epidemiologi.exportPdf', $case->id) }}" class="btn btn-danger" target="_blank">
                <i class="fa fa-file-pdf"></i> Print/PDF
            </a>
            <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <!-- Status Badges -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5>Status Kasus</h5>
                    <h3 class="text-uppercase">{{ $case->status_kasus }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-{{ $case->kondisi_akhir == 'sembuh' ? 'success' : ($case->kondisi_akhir == 'meninggal' ? 'danger' : 'warning') }} text-white">
                <div class="card-body text-center">
                    <h5>Kondisi Akhir</h5>
                    <h3 class="text-capitalize">{{ str_replace('_', ' ', $case->kondisi_akhir) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5>Status Lab</h5>
                    <h3 class="text-capitalize">{{ str_replace('_', ' ', $case->status_lab) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <h5>Status Rawat</h5>
                    <h3 class="text-capitalize">{{ str_replace('_', ' ', $case->status_rawat) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Section A: Patient Identity -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fa fa-user"></i> A. Identitas Pasien</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <dl class="row">
                        <dt class="col-sm-6">No. Registrasi:</dt>
                        <dd class="col-sm-6"><strong>{{ $case->no_registrasi }}</strong></dd>

                        <dt class="col-sm-6">NIK:</dt>
                        <dd class="col-sm-6">{{ $case->nik }}</dd>

                        <dt class="col-sm-6">Nama Lengkap:</dt>
                        <dd class="col-sm-6"><strong>{{ $case->nama_lengkap }}</strong></dd>
                    </dl>
                </div>
                <div class="col-md-4">
                    <dl class="row">
                        <dt class="col-sm-6">Tanggal Lahir:</dt>
                        <dd class="col-sm-6">{{ $case->tanggal_lahir->format('d/m/Y') }}</dd>

                        <dt class="col-sm-6">Umur:</dt>
                        <dd class="col-sm-6">{{ $case->umur }} tahun</dd>

                        <dt class="col-sm-6">Kategori Umur:</dt>
                        <dd class="col-sm-6"><span class="badge badge-info">{{ ucfirst($case->kategori_umur) }}</span></dd>

                        <dt class="col-sm-6">Jenis Kelamin:</dt>
                        <dd class="col-sm-6">{{ $case->jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan' }}</dd>
                    </dl>
                </div>
                <div class="col-md-4">
                    <dl class="row">
                        <dt class="col-sm-6">Kecamatan:</dt>
                        <dd class="col-sm-6">{{ $case->kecamatan->name ?? '-' }}</dd>

                        <dt class="col-sm-6">Kelurahan:</dt>
                        <dd class="col-sm-6">{{ $case->kelurahan->name ?? '-' }}</dd>

                        <dt class="col-sm-6">RT:</dt>
                        <dd class="col-sm-6">{{ $case->rt->name ?? '-' }}</dd>

                        <dt class="col-sm-6">No. Telepon:</dt>
                        <dd class="col-sm-6">{{ $case->no_telepon ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <dl class="row">
                        <dt class="col-sm-2">Alamat Lengkap:</dt>
                        <dd class="col-sm-10">{{ $case->alamat_lengkap }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Section B: Reporter Identity -->
    <div class="card mb-3">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fa fa-user-tie"></i> B. Identitas Pelapor</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <dl class="row">
                        <dt class="col-sm-4">Nama Pelapor:</dt>
                        <dd class="col-sm-8">{{ $case->nama_pelapor }}</dd>

                        <dt class="col-sm-4">Jabatan:</dt>
                        <dd class="col-sm-8">{{ $case->jabatan_pelapor ?? '-' }}</dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="row">
                        <dt class="col-sm-4">Instansi:</dt>
                        <dd class="col-sm-8">{{ $case->instansi_pelapor ?? '-' }}</dd>

                        <dt class="col-sm-4">Telepon:</dt>
                        <dd class="col-sm-8">{{ $case->telepon_pelapor ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Section C: Case Data -->
    <div class="card mb-3">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="fa fa-file-medical"></i> C. Data Kasus</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <dl class="row">
                        <dt class="col-sm-4">Jenis Penyakit:</dt>
                        <dd class="col-sm-8">
                            <strong>{{ $case->jenisKasus->nama_penyakit ?? '-' }}</strong>
                            <span class="badge badge-secondary">{{ $case->jenisKasus->kode_penyakit ?? '' }}</span>
                        </dd>

                        <dt class="col-sm-4">Kode ICD-10:</dt>
                        <dd class="col-sm-8">{{ $case->kode_icd10 ?? '-' }}</dd>

                        <dt class="col-sm-4">Tanggal Onset:</dt>
                        <dd class="col-sm-8"><strong>{{ $case->tanggal_onset->format('d/m/Y') }}</strong></dd>

                        <dt class="col-sm-4">Tanggal Konsultasi:</dt>
                        <dd class="col-sm-8">{{ $case->tanggal_konsultasi->format('d/m/Y') }}</dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="row">
                        <dt class="col-sm-4">Tanggal Lapor:</dt>
                        <dd class="col-sm-8">{{ $case->tanggal_lapor->format('d/m/Y') }}</dd>

                        <dt class="col-sm-4">Sumber Penularan:</dt>
                        <dd class="col-sm-8">
                            <span class="badge badge-{{ $case->sumber_penularan == 'lokal' ? 'primary' : ($case->sumber_penularan == 'import' ? 'warning' : 'secondary') }}">
                                {{ ucfirst($case->sumber_penularan) }}
                            </span>
                        </dd>

                        <dt class="col-sm-4">Lokasi Penularan:</dt>
                        <dd class="col-sm-8">{{ $case->lokasi_penularan ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Section D: Symptoms -->
    <div class="card mb-3">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">
                <i class="fa fa-thermometer-half"></i> D. Gejala Klinis
                <span class="badge badge-light text-dark">{{ $case->getSymptomCount() }} gejala</span>
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                @php
                    $symptoms = $case->getSymptoms();
                    $symptomLabels = [
                        'demam' => 'Demam',
                        'batuk' => 'Batuk',
                        'pilek' => 'Pilek',
                        'sakit_kepala' => 'Sakit Kepala',
                        'mual' => 'Mual',
                        'muntah' => 'Muntah',
                        'diare' => 'Diare',
                        'ruam' => 'Ruam/Bercak Merah',
                        'sesak_napas' => 'Sesak Napas',
                        'nyeri_otot' => 'Nyeri Otot',
                        'nyeri_sendi' => 'Nyeri Sendi',
                        'lemas' => 'Lemas',
                        'kehilangan_nafsu_makan' => 'Hilang Nafsu Makan',
                        'mata_merah' => 'Mata Merah',
                        'pembengkakan_kelenjar' => 'Pembengkakan Kelenjar',
                        'kejang' => 'Kejang',
                        'penurunan_kesadaran' => 'Penurunan Kesadaran',
                    ];
                @endphp

                @foreach($symptomLabels as $key => $label)
                    <div class="col-md-3 mb-2">
                        @if($symptoms[$key])
                            <i class="fa fa-check-circle text-danger"></i> <strong>{{ $label }}</strong>
                        @else
                            <i class="fa fa-circle text-muted"></i> <span class="text-muted">{{ $label }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Section E: History -->
    <div class="card mb-3">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fa fa-history"></i> E. Riwayat</h5>
        </div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Riwayat Perjalanan:</dt>
                <dd class="col-sm-9">{{ $case->riwayat_perjalanan ?? 'Tidak ada' }}</dd>

                <dt class="col-sm-3">Riwayat Kontak Kasus:</dt>
                <dd class="col-sm-9">
                    @if($case->riwayat_kontak_kasus)
                        <span class="badge badge-warning">Ya, Ada Kontak</span>
                    @else
                        <span class="badge badge-secondary">Tidak Ada</span>
                    @endif
                </dd>

                <dt class="col-sm-3">Status Imunisasi:</dt>
                <dd class="col-sm-9">
                    <span class="badge badge-info">{{ ucfirst(str_replace('_', ' ', $case->riwayat_imunisasi)) }}</span>
                </dd>

                <dt class="col-sm-3">Tanggal Imunisasi Terakhir:</dt>
                <dd class="col-sm-9">{{ $case->tanggal_imunisasi_terakhir ? $case->tanggal_imunisasi_terakhir->format('d/m/Y') : '-' }}</dd>
            </dl>
        </div>
    </div>

    <!-- Section F: Laboratory -->
    <div class="card mb-3">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fa fa-flask"></i> F. Pemeriksaan Laboratorium</h5>
        </div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Status Lab:</dt>
                <dd class="col-sm-9">
                    <span class="badge badge-{{ $case->status_lab == 'positif' ? 'danger' : ($case->status_lab == 'negatif' ? 'success' : 'secondary') }}">
                        {{ ucfirst(str_replace('_', ' ', $case->status_lab)) }}
                    </span>
                </dd>

                <dt class="col-sm-3">Tanggal Pengambilan Spesimen:</dt>
                <dd class="col-sm-9">{{ $case->tanggal_pengambilan_spesimen ? $case->tanggal_pengambilan_spesimen->format('d/m/Y') : '-' }}</dd>

                <dt class="col-sm-3">Jenis Spesimen:</dt>
                <dd class="col-sm-9">{{ $case->jenis_spesimen ?? '-' }}</dd>

                <dt class="col-sm-3">Tanggal Hasil Lab:</dt>
                <dd class="col-sm-9">{{ $case->tanggal_hasil_lab ? $case->tanggal_hasil_lab->format('d/m/Y') : '-' }}</dd>

                <dt class="col-sm-3">Hasil Laboratorium:</dt>
                <dd class="col-sm-9">{{ $case->hasil_lab ?? '-' }}</dd>
            </dl>
        </div>
    </div>

    <!-- Section G: Management -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fa fa-hospital"></i> G. Tatalaksana</h5>
        </div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Status Perawatan:</dt>
                <dd class="col-sm-9">
                    <span class="badge badge-info">{{ ucfirst(str_replace('_', ' ', $case->status_rawat)) }}</span>
                </dd>

                <dt class="col-sm-3">Nama Faskes:</dt>
                <dd class="col-sm-9"><strong>{{ $case->nama_faskes_rawat }}</strong></dd>

                <dt class="col-sm-3">Tanggal Masuk:</dt>
                <dd class="col-sm-9">{{ $case->tanggal_masuk_rawat ? $case->tanggal_masuk_rawat->format('d/m/Y') : '-' }}</dd>

                <dt class="col-sm-3">Tanggal Keluar:</dt>
                <dd class="col-sm-9">{{ $case->tanggal_keluar_rawat ? $case->tanggal_keluar_rawat->format('d/m/Y') : '-' }}</dd>

                <dt class="col-sm-3">Lama Rawat:</dt>
                <dd class="col-sm-9">{{ $case->lama_rawat ? $case->lama_rawat . ' hari' : '-' }}</dd>
            </dl>
        </div>
    </div>

    <!-- Section H: Final Status -->
    <div class="card mb-3">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fa fa-heartbeat"></i> H. Status Akhir</h5>
        </div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Kondisi Akhir:</dt>
                <dd class="col-sm-9">
                    <span class="badge badge-{{ $case->kondisi_akhir == 'sembuh' ? 'success' : ($case->kondisi_akhir == 'meninggal' ? 'danger' : 'warning') }} badge-lg">
                        {{ ucfirst(str_replace('_', ' ', $case->kondisi_akhir)) }}
                    </span>
                </dd>

                <dt class="col-sm-3">Tanggal Kondisi Akhir:</dt>
                <dd class="col-sm-9">{{ $case->tanggal_kondisi_akhir ? $case->tanggal_kondisi_akhir->format('d/m/Y') : '-' }}</dd>

                @if($case->kondisi_akhir == 'meninggal')
                    <dt class="col-sm-3">Penyebab Kematian:</dt>
                    <dd class="col-sm-9"><div class="alert alert-danger mb-0">{{ $case->penyebab_kematian }}</div></dd>
                @endif
            </dl>
        </div>
    </div>

    <!-- Section I: Contact Investigation -->
    <div class="card mb-3">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fa fa-users"></i> I. Investigasi Kontak</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 text-center">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h3 class="text-primary">{{ $case->jumlah_kontak_serumah }}</h3>
                            <p class="mb-0">Kontak Serumah</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h3 class="text-info">{{ $case->jumlah_kontak_diluar_rumah }}</h3>
                            <p class="mb-0">Kontak Diluar Rumah</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h3 class="text-danger">{{ $case->jumlah_kontak_bergejala }}</h3>
                            <p class="mb-0">Kontak Bergejala</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-12">
                    <dl class="row">
                        <dt class="col-sm-3">Tindak Lanjut Kontak:</dt>
                        <dd class="col-sm-9">{{ $case->tindak_lanjut_kontak ?? 'Tidak ada catatan' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Section J: Metadata -->
    <div class="card mb-3">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fa fa-info-circle"></i> J. Informasi Tambahan</h5>
        </div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Catatan Tambahan:</dt>
                <dd class="col-sm-9">{{ $case->catatan_tambahan ?? 'Tidak ada catatan' }}</dd>
            </dl>
        </div>
    </div>

    <!-- Audit Information -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fa fa-history"></i> Informasi Audit</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <dl class="row">
                        <dt class="col-sm-4">Petugas Input:</dt>
                        <dd class="col-sm-8">{{ $case->petugasInput->name ?? 'Unknown' }}</dd>

                        <dt class="col-sm-4">Dibuat Oleh:</dt>
                        <dd class="col-sm-8">{{ $case->creator->name ?? 'Unknown' }}</dd>

                        <dt class="col-sm-4">Dibuat Pada:</dt>
                        <dd class="col-sm-8">{{ $case->created_at->format('d/m/Y H:i:s') }}</dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="row">
                        <dt class="col-sm-4">Terakhir Diubah Oleh:</dt>
                        <dd class="col-sm-8">{{ $case->updater->name ?? 'Unknown' }}</dd>

                        <dt class="col-sm-4">Terakhir Diubah:</dt>
                        <dd class="col-sm-8">{{ $case->updated_at->format('d/m/Y H:i:s') }}</dd>

                        <dt class="col-sm-4">Faskes Pelapor:</dt>
                        <dd class="col-sm-8">{{ $case->id_faskes_pelapor ?? 'Tidak dicatat' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
