@extends('admin::layouts.app')
@section('title') Detail Kasus - {{ $case->no_registrasi }} @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Surveillance @endsection
@section('item-active') Detail Kasus @endsection

@section('content')
{{-- Skip Link for Accessibility --}}
<a href="#main-content" class="sr-only sr-only-focusable skip-link">Langsung ke konten utama</a>

<style>
    @include('admin.epidemiologi.components.shared-styles')

    /* Show-page specific */
    .case-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--success-green) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 32px;
        font-weight: bold;
        box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
    }
    .symptom-item {
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        margin-bottom: 0.25rem;
    }
    .symptom-active {
        background-color: rgba(190, 18, 60, 0.08);
    }
    .symptom-inactive {
        background-color: rgba(107, 114, 128, 0.05);
    }
    .contact-stat-card {
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        transition: transform 0.2s ease;
    }
    .contact-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
</style>

<main id="main-content" role="main" aria-label="Detail kasus surveillance {{ $case->no_registrasi }}">
<div class="container-fluid">
    {{-- Header Section --}}
    <header class="row mb-4">
        <div class="col-md-8">
            <div class="d-flex align-items-center">
                <div class="case-avatar me-4 mr-4" role="img" aria-label="Avatar {{ $case->nama_lengkap }}">
                    <span aria-hidden="true">{{ strtoupper(substr($case->nama_lengkap, 0, 1)) }}</span>
                </div>
                <div>
                    <h1 class="h3 mb-1">{{ $case->nama_lengkap }}</h1>
                    <p class="text-accessible-muted mb-0">
                        <span class="sr-only">Nomor Registrasi:</span>
                        No. Reg: <strong>{{ $case->no_registrasi }}</strong>
                        <span aria-hidden="true" class="mx-1">•</span>
                        <span class="sr-only">NIK:</span>
                        NIK: {{ $case->nik }}
                    </p>
                    <div class="mt-2">
                        @php
                            $statusBadge = match($case->status_kasus) {
                                'confirmed' => 'badge-accessible-danger',
                                'suspected' => 'badge-accessible-warning',
                                'probable'  => 'badge-accessible-info',
                                default     => 'badge-accessible-secondary',
                            };
                            $kondisiBadge = match($case->kondisi_akhir) {
                                'sembuh'       => 'badge-accessible-success',
                                'meninggal'    => 'badge-accessible-danger',
                                'masih_sakit'  => 'badge-accessible-warning',
                                default        => 'badge-accessible-secondary',
                            };
                        @endphp
                        <span class="badge badge-status {{ $statusBadge }}">{{ ucfirst($case->status_kasus) }}</span>
                        <span class="badge badge-status {{ $kondisiBadge }}">{{ ucfirst(str_replace('_', ' ', $case->kondisi_akhir)) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 text-end text-right">
            <nav aria-label="Aksi kasus">
                <div class="btn-group" role="group" aria-label="Tombol aksi kasus">
                    <a href="{{ route('admin.epidemiologi.edit', $case->id) }}" class="btn btn-outline-primary btn-sm" aria-label="Edit kasus {{ $case->no_registrasi }}">
                        <i class="fa fa-edit" aria-hidden="true"></i> Edit
                    </a>
                    <a href="{{ route('admin.epidemiologi.exportPdf', $case->id) }}" class="btn btn-outline-info btn-sm" target="_blank" aria-label="Cetak PDF kasus {{ $case->no_registrasi }}">
                        <i class="fa fa-file-pdf" aria-hidden="true"></i> PDF
                    </a>
                    <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-arrow-left" aria-hidden="true"></i> Kembali
                    </a>
                </div>
            </nav>
        </div>
    </header>

    {{-- Status Summary Strip --}}
    <section aria-labelledby="status-strip-title" class="row mb-4">
        <h2 id="status-strip-title" class="sr-only">Ringkasan Status Kasus</h2>
        <div class="col-md-3 mb-3">
            <div class="card stat-card status-info h-100">
                <div class="card-body text-center py-3">
                    <h3 class="h6 text-accessible-muted mb-1">Status Kasus</h3>
                    <p class="h4 mb-0 text-uppercase">{{ $case->status_kasus }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            @php
                $kondisiStatus = match($case->kondisi_akhir) {
                    'sembuh' => 'success', 'meninggal' => 'danger', default => 'warning'
                };
            @endphp
            <div class="card stat-card status-{{ $kondisiStatus }} h-100">
                <div class="card-body text-center py-3">
                    <h3 class="h6 text-accessible-muted mb-1">Kondisi Akhir</h3>
                    <p class="h4 mb-0 text-capitalize">{{ str_replace('_', ' ', $case->kondisi_akhir) }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            @php
                $labStatus = match($case->status_lab) {
                    'positif' => 'danger', 'negatif' => 'success', default => 'info'
                };
            @endphp
            <div class="card stat-card status-{{ $labStatus }} h-100">
                <div class="card-body text-center py-3">
                    <h3 class="h6 text-accessible-muted mb-1">Status Lab</h3>
                    <p class="h4 mb-0 text-capitalize">{{ str_replace('_', ' ', $case->status_lab) }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center py-3">
                    <h3 class="h6 text-accessible-muted mb-1">Status Rawat</h3>
                    <p class="h4 mb-0 text-capitalize">{{ str_replace('_', ' ', $case->status_rawat) }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Section A: Patient Identity --}}
    <section aria-labelledby="section-a-title" class="row mb-4">
        <div class="col-12">
            <article class="card info-card">
                <div class="card-header section-header-a">
                    <h2 id="section-a-title">
                        <i class="fa fa-user mr-2" aria-hidden="true"></i> A. Identitas Pasien
                    </h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <dl class="row mb-0">
                                <dt class="col-sm-6 text-accessible-muted">No. Registrasi</dt>
                                <dd class="col-sm-6"><strong>{{ $case->no_registrasi }}</strong></dd>
                                <dt class="col-sm-6 text-accessible-muted">NIK</dt>
                                <dd class="col-sm-6">{{ $case->nik }}</dd>
                                <dt class="col-sm-6 text-accessible-muted">Nama Lengkap</dt>
                                <dd class="col-sm-6"><strong>{{ $case->nama_lengkap }}</strong></dd>
                            </dl>
                        </div>
                        <div class="col-md-4">
                            <dl class="row mb-0">
                                <dt class="col-sm-6 text-accessible-muted">Tanggal Lahir</dt>
                                <dd class="col-sm-6">
                                    <time datetime="{{ $case->tanggal_lahir->format('Y-m-d') }}">{{ $case->tanggal_lahir->format('d/m/Y') }}</time>
                                </dd>
                                <dt class="col-sm-6 text-accessible-muted">Umur</dt>
                                <dd class="col-sm-6">{{ $case->umur }} tahun</dd>
                                <dt class="col-sm-6 text-accessible-muted">Kategori Umur</dt>
                                <dd class="col-sm-6"><span class="badge badge-accessible-info badge-status">{{ ucfirst($case->kategori_umur) }}</span></dd>
                                <dt class="col-sm-6 text-accessible-muted">Jenis Kelamin</dt>
                                <dd class="col-sm-6">{{ $case->jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan' }}</dd>
                            </dl>
                        </div>
                        <div class="col-md-4">
                            <dl class="row mb-0">
                                <dt class="col-sm-6 text-accessible-muted">Kecamatan</dt>
                                <dd class="col-sm-6">{{ $case->kecamatan->name ?? '-' }}</dd>
                                <dt class="col-sm-6 text-accessible-muted">Kelurahan</dt>
                                <dd class="col-sm-6">{{ $case->kelurahan->name ?? '-' }}</dd>
                                <dt class="col-sm-6 text-accessible-muted">RT</dt>
                                <dd class="col-sm-6">{{ $case->rt->name ?? '-' }}</dd>
                                <dt class="col-sm-6 text-accessible-muted">No. Telepon</dt>
                                <dd class="col-sm-6">{{ $case->no_telepon ?? '-' }}</dd>
                            </dl>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-12">
                            <dl class="row mb-0">
                                <dt class="col-sm-2 text-accessible-muted">Alamat Lengkap</dt>
                                <dd class="col-sm-10">{{ $case->alamat_lengkap }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </section>

    {{-- Section B: Reporter Identity --}}
    <section aria-labelledby="section-b-title" class="row mb-4">
        <div class="col-12">
            <article class="card info-card">
                <div class="card-header section-header-b">
                    <h2 id="section-b-title">
                        <i class="fa fa-user-tie mr-2" aria-hidden="true"></i> B. Identitas Pelapor
                    </h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-4 text-accessible-muted">Nama Pelapor</dt>
                                <dd class="col-sm-8">{{ $case->nama_pelapor }}</dd>
                                <dt class="col-sm-4 text-accessible-muted">Jabatan</dt>
                                <dd class="col-sm-8">{{ $case->jabatan_pelapor ?? '-' }}</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-4 text-accessible-muted">Instansi</dt>
                                <dd class="col-sm-8">{{ $case->instansi_pelapor ?? '-' }}</dd>
                                <dt class="col-sm-4 text-accessible-muted">Telepon</dt>
                                <dd class="col-sm-8">{{ $case->telepon_pelapor ?? '-' }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </section>

    {{-- Section C: Case Data --}}
    <section aria-labelledby="section-c-title" class="row mb-4">
        <div class="col-12">
            <article class="card info-card">
                <div class="card-header section-header-c">
                    <h2 id="section-c-title">
                        <i class="fa fa-file-medical mr-2" aria-hidden="true"></i> C. Data Kasus
                    </h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-4 text-accessible-muted">Jenis Penyakit</dt>
                                <dd class="col-sm-8">
                                    <strong>{{ $case->jenisKasus->nama_penyakit ?? '-' }}</strong>
                                    <span class="badge badge-accessible-secondary badge-status">{{ $case->jenisKasus->kode_penyakit ?? '' }}</span>
                                </dd>
                                <dt class="col-sm-4 text-accessible-muted">Kode ICD-10</dt>
                                <dd class="col-sm-8">{{ $case->kode_icd10 ?? '-' }}</dd>
                                <dt class="col-sm-4 text-accessible-muted">Tanggal Onset</dt>
                                <dd class="col-sm-8">
                                    <strong><time datetime="{{ $case->tanggal_onset->format('Y-m-d') }}">{{ $case->tanggal_onset->format('d/m/Y') }}</time></strong>
                                </dd>
                                <dt class="col-sm-4 text-accessible-muted">Tanggal Konsultasi</dt>
                                <dd class="col-sm-8">
                                    <time datetime="{{ $case->tanggal_konsultasi->format('Y-m-d') }}">{{ $case->tanggal_konsultasi->format('d/m/Y') }}</time>
                                </dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-4 text-accessible-muted">Tanggal Lapor</dt>
                                <dd class="col-sm-8">
                                    <time datetime="{{ $case->tanggal_lapor->format('Y-m-d') }}">{{ $case->tanggal_lapor->format('d/m/Y') }}</time>
                                </dd>
                                <dt class="col-sm-4 text-accessible-muted">Sumber Penularan</dt>
                                <dd class="col-sm-8">
                                    @php
                                        $sumberBadge = match($case->sumber_penularan) {
                                            'lokal'  => 'badge-accessible-info',
                                            'import' => 'badge-accessible-warning',
                                            default  => 'badge-accessible-secondary',
                                        };
                                    @endphp
                                    <span class="badge badge-status {{ $sumberBadge }}">{{ ucfirst($case->sumber_penularan) }}</span>
                                </dd>
                                <dt class="col-sm-4 text-accessible-muted">Lokasi Penularan</dt>
                                <dd class="col-sm-8">{{ $case->lokasi_penularan ?? '-' }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </section>

    {{-- Section D: Symptoms --}}
    <section aria-labelledby="section-d-title" class="row mb-4">
        <div class="col-12">
            <article class="card info-card">
                <div class="card-header section-header-d">
                    <h2 id="section-d-title">
                        <i class="fa fa-thermometer-half mr-2" aria-hidden="true"></i> D. Gejala Klinis
                        <span class="badge badge-light text-dark ml-2" style="font-size: 0.75rem;">{{ $case->getSymptomCount() }} gejala</span>
                    </h2>
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
                                <div class="symptom-item {{ $symptoms[$key] ? 'symptom-active' : 'symptom-inactive' }}">
                                    @if($symptoms[$key])
                                        <i class="fa fa-check-circle" style="color: var(--danger-rose);" aria-hidden="true"></i>
                                        <strong>{{ $label }}</strong>
                                    @else
                                        <i class="fa fa-circle" style="color: #d1d5db;" aria-hidden="true"></i>
                                        <span class="text-accessible-muted">{{ $label }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </article>
        </div>
    </section>

    {{-- Section E: History --}}
    <section aria-labelledby="section-e-title" class="row mb-4">
        <div class="col-12">
            <article class="card info-card">
                <div class="card-header section-header-e">
                    <h2 id="section-e-title">
                        <i class="fa fa-history mr-2" aria-hidden="true"></i> E. Riwayat
                    </h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3 text-accessible-muted">Riwayat Perjalanan</dt>
                        <dd class="col-sm-9">{{ $case->riwayat_perjalanan ?? 'Tidak ada' }}</dd>

                        <dt class="col-sm-3 text-accessible-muted">Riwayat Kontak Kasus</dt>
                        <dd class="col-sm-9">
                            @if($case->riwayat_kontak_kasus)
                                <span class="badge badge-accessible-warning badge-status">Ya, Ada Kontak</span>
                            @else
                                <span class="badge badge-accessible-secondary badge-status">Tidak Ada</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3 text-accessible-muted">Status Imunisasi</dt>
                        <dd class="col-sm-9">
                            <span class="badge badge-accessible-info badge-status">{{ ucfirst(str_replace('_', ' ', $case->riwayat_imunisasi)) }}</span>
                        </dd>

                        <dt class="col-sm-3 text-accessible-muted">Tanggal Imunisasi Terakhir</dt>
                        <dd class="col-sm-9">{{ $case->tanggal_imunisasi_terakhir ? $case->tanggal_imunisasi_terakhir->format('d/m/Y') : '-' }}</dd>
                    </dl>
                </div>
            </article>
        </div>
    </section>

    {{-- Section F: Laboratory --}}
    <section aria-labelledby="section-f-title" class="row mb-4">
        <div class="col-12">
            <article class="card info-card">
                <div class="card-header section-header-f">
                    <h2 id="section-f-title">
                        <i class="fa fa-flask mr-2" aria-hidden="true"></i> F. Pemeriksaan Laboratorium
                    </h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3 text-accessible-muted">Status Lab</dt>
                        <dd class="col-sm-9">
                            @php
                                $labBadge = match($case->status_lab) {
                                    'positif'          => 'badge-accessible-danger',
                                    'negatif'          => 'badge-accessible-success',
                                    'belum_diperiksa'  => 'badge-accessible-secondary',
                                    default            => 'badge-accessible-info',
                                };
                            @endphp
                            <span class="badge badge-status {{ $labBadge }}">{{ ucfirst(str_replace('_', ' ', $case->status_lab)) }}</span>
                        </dd>

                        <dt class="col-sm-3 text-accessible-muted">Tanggal Pengambilan Spesimen</dt>
                        <dd class="col-sm-9">{{ $case->tanggal_pengambilan_spesimen ? $case->tanggal_pengambilan_spesimen->format('d/m/Y') : '-' }}</dd>

                        <dt class="col-sm-3 text-accessible-muted">Jenis Spesimen</dt>
                        <dd class="col-sm-9">{{ $case->jenis_spesimen ?? '-' }}</dd>

                        <dt class="col-sm-3 text-accessible-muted">Tanggal Hasil Lab</dt>
                        <dd class="col-sm-9">{{ $case->tanggal_hasil_lab ? $case->tanggal_hasil_lab->format('d/m/Y') : '-' }}</dd>

                        <dt class="col-sm-3 text-accessible-muted">Hasil Laboratorium</dt>
                        <dd class="col-sm-9">{{ $case->hasil_lab ?? '-' }}</dd>
                    </dl>
                </div>
            </article>
        </div>
    </section>

    {{-- Section G: Management --}}
    <section aria-labelledby="section-g-title" class="row mb-4">
        <div class="col-12">
            <article class="card info-card">
                <div class="card-header section-header-g">
                    <h2 id="section-g-title">
                        <i class="fa fa-hospital mr-2" aria-hidden="true"></i> G. Tatalaksana
                    </h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3 text-accessible-muted">Status Perawatan</dt>
                        <dd class="col-sm-9">
                            <span class="badge badge-accessible-info badge-status">{{ ucfirst(str_replace('_', ' ', $case->status_rawat)) }}</span>
                        </dd>

                        <dt class="col-sm-3 text-accessible-muted">Nama Faskes</dt>
                        <dd class="col-sm-9"><strong>{{ $case->nama_faskes_rawat }}</strong></dd>

                        <dt class="col-sm-3 text-accessible-muted">Tanggal Masuk</dt>
                        <dd class="col-sm-9">{{ $case->tanggal_masuk_rawat ? $case->tanggal_masuk_rawat->format('d/m/Y') : '-' }}</dd>

                        <dt class="col-sm-3 text-accessible-muted">Tanggal Keluar</dt>
                        <dd class="col-sm-9">{{ $case->tanggal_keluar_rawat ? $case->tanggal_keluar_rawat->format('d/m/Y') : '-' }}</dd>

                        <dt class="col-sm-3 text-accessible-muted">Lama Rawat</dt>
                        <dd class="col-sm-9">{{ $case->lama_rawat ? $case->lama_rawat . ' hari' : '-' }}</dd>
                    </dl>
                </div>
            </article>
        </div>
    </section>

    {{-- Section H: Final Status --}}
    <section aria-labelledby="section-h-title" class="row mb-4">
        <div class="col-12">
            <article class="card info-card">
                <div class="card-header section-header-h">
                    <h2 id="section-h-title">
                        <i class="fa fa-heartbeat mr-2" aria-hidden="true"></i> H. Status Akhir
                    </h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3 text-accessible-muted">Kondisi Akhir</dt>
                        <dd class="col-sm-9">
                            <span class="badge badge-status {{ $kondisiBadge }}" style="font-size: 0.9rem; padding: 0.5em 1em;">
                                {{ ucfirst(str_replace('_', ' ', $case->kondisi_akhir)) }}
                            </span>
                        </dd>

                        <dt class="col-sm-3 text-accessible-muted">Tanggal Kondisi Akhir</dt>
                        <dd class="col-sm-9">{{ $case->tanggal_kondisi_akhir ? $case->tanggal_kondisi_akhir->format('d/m/Y') : '-' }}</dd>

                        @if($case->kondisi_akhir == 'meninggal')
                            <dt class="col-sm-3 text-accessible-muted">Penyebab Kematian</dt>
                            <dd class="col-sm-9">
                                <div class="alert alert-danger mb-0" role="alert">
                                    <i class="fa fa-exclamation-triangle mr-1" aria-hidden="true"></i>
                                    {{ $case->penyebab_kematian }}
                                </div>
                            </dd>
                        @endif
                    </dl>
                </div>
            </article>
        </div>
    </section>

    {{-- Section I: Contact Investigation --}}
    <section aria-labelledby="section-i-title" class="row mb-4">
        <div class="col-12">
            <article class="card info-card">
                <div class="card-header section-header-i">
                    <h2 id="section-i-title">
                        <i class="fa fa-users mr-2" aria-hidden="true"></i> I. Investigasi Kontak
                    </h2>
                </div>
                <div class="card-body">
                    <div class="row mb-3" role="list" aria-label="Statistik kontak">
                        <div class="col-md-4" role="listitem">
                            <div class="card contact-stat-card bg-light text-center p-3">
                                <h3 class="h2 mb-1" style="color: var(--primary-blue);">{{ $case->jumlah_kontak_serumah }}</h3>
                                <p class="text-accessible-muted mb-0 small">Kontak Serumah</p>
                            </div>
                        </div>
                        <div class="col-md-4" role="listitem">
                            <div class="card contact-stat-card bg-light text-center p-3">
                                <h3 class="h2 mb-1" style="color: var(--info-teal);">{{ $case->jumlah_kontak_diluar_rumah }}</h3>
                                <p class="text-accessible-muted mb-0 small">Kontak Diluar Rumah</p>
                            </div>
                        </div>
                        <div class="col-md-4" role="listitem">
                            <div class="card contact-stat-card bg-light text-center p-3">
                                <h3 class="h2 mb-1" style="color: var(--danger-rose);">{{ $case->jumlah_kontak_bergejala }}</h3>
                                <p class="text-accessible-muted mb-0 small">Kontak Bergejala</p>
                            </div>
                        </div>
                    </div>
                    <dl class="row mb-0">
                        <dt class="col-sm-3 text-accessible-muted">Tindak Lanjut Kontak</dt>
                        <dd class="col-sm-9">{{ $case->tindak_lanjut_kontak ?? 'Tidak ada catatan' }}</dd>
                    </dl>
                </div>
            </article>
        </div>
    </section>

    {{-- Section J: Metadata --}}
    <section aria-labelledby="section-j-title" class="row mb-4">
        <div class="col-12">
            <article class="card info-card">
                <div class="card-header section-header-j">
                    <h2 id="section-j-title">
                        <i class="fa fa-info-circle mr-2" aria-hidden="true"></i> J. Informasi Tambahan
                    </h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3 text-accessible-muted">Catatan Tambahan</dt>
                        <dd class="col-sm-9">{{ $case->catatan_tambahan ?? 'Tidak ada catatan' }}</dd>
                    </dl>
                </div>
            </article>
        </div>
    </section>

    {{-- Audit Information --}}
    <section aria-labelledby="audit-title" class="row mb-4">
        <div class="col-12">
            <article class="card info-card">
                <div class="card-header" style="background: linear-gradient(135deg, #e5e7eb 0%, #f3f4f6 100%) !important; color: var(--text-muted) !important;">
                    <h2 id="audit-title" style="color: var(--text-muted) !important;">
                        <i class="fa fa-history mr-2" aria-hidden="true"></i> Informasi Audit
                    </h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-4 text-accessible-muted">Petugas Input</dt>
                                <dd class="col-sm-8">{{ $case->petugasInput->name ?? 'Unknown' }}</dd>
                                <dt class="col-sm-4 text-accessible-muted">Dibuat Oleh</dt>
                                <dd class="col-sm-8">{{ $case->creator->name ?? 'Unknown' }}</dd>
                                <dt class="col-sm-4 text-accessible-muted">Dibuat Pada</dt>
                                <dd class="col-sm-8">
                                    <time datetime="{{ $case->created_at->format('Y-m-d\TH:i:s') }}">
                                        {{ $case->created_at->format('d/m/Y H:i:s') }}
                                    </time>
                                </dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-4 text-accessible-muted">Terakhir Diubah Oleh</dt>
                                <dd class="col-sm-8">{{ $case->updater->name ?? 'Unknown' }}</dd>
                                <dt class="col-sm-4 text-accessible-muted">Terakhir Diubah</dt>
                                <dd class="col-sm-8">
                                    <time datetime="{{ $case->updated_at->format('Y-m-d\TH:i:s') }}">
                                        {{ $case->updated_at->format('d/m/Y H:i:s') }}
                                    </time>
                                </dd>
                                <dt class="col-sm-4 text-accessible-muted">Faskes Pelapor</dt>
                                <dd class="col-sm-8">{{ $case->id_faskes_pelapor ?? 'Tidak dicatat' }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </section>
</div>
</main>
@endsection
