@extends('admin::layouts.app')
@section('title') Detail Kasus - {{ $case->no_registrasi }} @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Epidemiologi @endsection
@section('item-active') Detail Kasus @endsection

@section('content')
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    {{ session('success') }}<button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-secondary btn-sm">
        <i class="fa fa-arrow-left mr-1"></i>Kembali
    </a>
    <div>
        <a href="{{ route('admin.epidemiologi.edit', $case->id) }}" class="btn btn-warning btn-sm">
            <i class="fa fa-edit mr-1"></i>Edit
        </a>
    </div>
</div>

<!-- Status Cards -->
@php
$statusColors  = ['suspek'=>'warning','probable'=>'info','konfirmasi'=>'danger','discarded'=>'secondary'];
$kondisiColors = ['sembuh'=>'success','dalam_perawatan'=>'warning','meninggal'=>'danger','tidak_diketahui'=>'secondary'];
$labColors     = ['positif'=>'danger','negatif'=>'success','pending'=>'warning','belum'=>'secondary','tidak_dilakukan'=>'secondary'];
$rawatColors   = ['rawat_inap'=>'danger','rawat_jalan'=>'info','rujukan'=>'warning','tidak_berobat'=>'secondary'];
@endphp

<div class="row mb-4">
    <div class="col-md-3"><div class="card text-white bg-{{ $statusColors[$case->status_kasus] ?? 'secondary' }} text-center py-3">
        <h6 class="mb-1">Status Kasus</h6><h4>{{ ucfirst($case->status_kasus) }}</h4>
    </div></div>
    <div class="col-md-3"><div class="card text-white bg-{{ $kondisiColors[$case->kondisi_akhir] ?? 'secondary' }} text-center py-3">
        <h6 class="mb-1">Kondisi Akhir</h6><h4>{{ str_replace('_', ' ', ucfirst($case->kondisi_akhir)) }}</h4>
    </div></div>
    <div class="col-md-3"><div class="card text-white bg-{{ $labColors[$case->status_lab] ?? 'secondary' }} text-center py-3">
        <h6 class="mb-1">Lab</h6><h4>{{ ucfirst($case->status_lab) }}</h4>
    </div></div>
    <div class="col-md-3"><div class="card text-white bg-{{ $rawatColors[$case->status_rawat] ?? 'secondary' }} text-center py-3">
        <h6 class="mb-1">Status Rawat</h6><h4>{{ str_replace('_', ' ', ucfirst($case->status_rawat)) }}</h4>
    </div></div>
</div>

<!-- A: Identitas -->
<div class="card mb-3">
    <div class="card-header bg-primary text-white font-weight-bold"><i class="fa fa-user mr-2"></i>A. Identitas Penderita</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><dt>No. Registrasi</dt><dd>{{ $case->no_registrasi }}</dd></div>
            <div class="col-md-5"><dt>Nama Lengkap</dt><dd>{{ $case->nama_lengkap }}</dd></div>
            <div class="col-md-2"><dt>NIK</dt><dd>{{ $case->nik ?: '-' }}</dd></div>
            <div class="col-md-2"><dt>Jenis Kelamin</dt><dd>{{ $case->jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan' }}</dd></div>
        </div>
        <div class="row">
            <div class="col-md-3"><dt>Tanggal Lahir</dt><dd>{{ $case->tanggal_lahir?->format('d/m/Y') }} ({{ $case->umur_tahun }} th)</dd></div>
            <div class="col-md-3"><dt>Kategori Umur</dt><dd>{{ ucfirst($case->kategori_umur ?: '-') }}</dd></div>
            <div class="col-md-3"><dt>Pekerjaan</dt><dd>{{ $case->pekerjaan ?: '-' }}</dd></div>
            <div class="col-md-3"><dt>Nama Orang Tua</dt><dd>{{ $case->nama_orang_tua ?: '-' }}</dd></div>
        </div>
        <dl><dt>Alamat</dt><dd>{{ $case->alamat_lengkap }}<br>
            <small class="text-muted">
                {{ $case->kecamatan->nama_kecamatan ?? '' }}
                {{ $case->kelurahan ? '/ ' . $case->kelurahan->nama_kelurahan : '' }}
                {{ $case->rt ? '/ RT ' . $case->rt->no_rt : '' }}
            </small>
        </dd></dl>
    </div>
</div>

<!-- B & C -->
<div class="row mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-secondary text-white font-weight-bold"><i class="fa fa-id-card mr-2"></i>B. Pelapor</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Nama</dt><dd class="col-7">{{ $case->nama_pelapor ?: '-' }}</dd>
                    <dt class="col-5">Jabatan</dt><dd class="col-7">{{ $case->jabatan_pelapor ?: '-' }}</dd>
                    <dt class="col-5">Instansi</dt><dd class="col-7">{{ $case->instansi_pelapor ?: '-' }}</dd>
                    <dt class="col-5">Telepon</dt><dd class="col-7">{{ $case->telp_pelapor ?: '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-info text-white font-weight-bold"><i class="fa fa-clipboard mr-2"></i>C. Data Kasus</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Jenis Kasus</dt><dd class="col-7">{{ $case->jenisKasus->nama_penyakit ?? '-' }}</dd>
                    <dt class="col-5">Diagnosa Awal</dt><dd class="col-7">{{ $case->diagnosa_awal ?: '-' }}</dd>
                    <dt class="col-5">Tgl Onset</dt><dd class="col-7">{{ $case->tanggal_onset?->format('d/m/Y') }}</dd>
                    <dt class="col-5">Tgl Konsultasi</dt><dd class="col-7">{{ $case->tanggal_konsultasi?->format('d/m/Y') ?: '-' }}</dd>
                    <dt class="col-5">Tgl Lapor</dt><dd class="col-7">{{ $case->tanggal_lapor?->format('d/m/Y') }}</dd>
                    <dt class="col-5">Tempat Berobat</dt><dd class="col-7">{{ $case->tempat_berobat ?: '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<!-- D: Gejala -->
<div class="card mb-3">
    <div class="card-header bg-warning font-weight-bold"><i class="fa fa-thermometer mr-2"></i>D. Gejala Klinis</div>
    <div class="card-body">
        @php
        $gejalaAll = [
            'gejala_demam'=>'Demam','gejala_ruam'=>'Ruam','gejala_batuk'=>'Batuk','gejala_pilek'=>'Pilek',
            'gejala_konjungtivitis'=>'Konjungtivitis','gejala_sesak_napas'=>'Sesak Napas',
            'gejala_nyeri_tenggorokan'=>'Nyeri Tenggorokan','gejala_membran_tenggorokan'=>'Membran Tenggorokan',
            'gejala_kejang'=>'Kejang','gejala_lumpuh_layuh'=>'Lumpuh Layuh','gejala_kaku_rahang'=>'Kaku Rahang',
            'gejala_spasme'=>'Spasme','gejala_tali_pusat'=>'Tali Pusat','gejala_diare'=>'Diare',
            'gejala_muntah'=>'Muntah','gejala_pendarahan'=>'Pendarahan','gejala_nyeri_sendi'=>'Nyeri Sendi',
        ];
        @endphp
        <div class="row">
            @foreach($gejalaAll as $field => $label)
            <div class="col-md-3 col-6 mb-1">
                @if($case->$field)
                    <span class="text-success"><i class="fa fa-check-circle mr-1"></i>{{ $label }}</span>
                @else
                    <span class="text-muted"><i class="fa fa-times-circle mr-1"></i>{{ $label }}</span>
                @endif
            </div>
            @endforeach
        </div>
        @if($case->suhu_tubuh)
        <div class="mt-2"><strong>Suhu Tubuh:</strong> {{ $case->suhu_tubuh }}°C</div>
        @endif
        @if($case->gejala_lainnya)
        <div class="mt-1"><strong>Lainnya:</strong> {{ $case->gejala_lainnya }}</div>
        @endif
    </div>
</div>

<!-- E, F, G, H -->
<div class="row mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-success text-white font-weight-bold"><i class="fa fa-history mr-2"></i>E. Riwayat</div>
            <div class="card-body">
                <dl>
                    <dt>Riwayat Imunisasi</dt><dd>{{ $case->riwayat_imunisasi ?: '-' }}</dd>
                    <dt>Riwayat Perjalanan</dt><dd>{{ $case->riwayat_perjalanan ?: '-' }}</dd>
                    <dt>Riwayat Kontak</dt><dd>{{ $case->riwayat_kontak ?: '-' }}</dd>
                    <dt>Riwayat Penyakit</dt><dd>{{ $case->riwayat_penyakit_dahulu ?: '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-danger text-white font-weight-bold"><i class="fa fa-flask mr-2"></i>F. Laboratorium</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-6">Status Lab</dt><dd class="col-6">{{ ucfirst($case->status_lab) }}</dd>
                    <dt class="col-6">Jenis Pemeriksaan</dt><dd class="col-6">{{ $case->jenis_pemeriksaan_lab ?: '-' }}</dd>
                    <dt class="col-6">Tgl Ambil Sampel</dt><dd class="col-6">{{ $case->tanggal_pengambilan_sampel?->format('d/m/Y') ?: '-' }}</dd>
                    <dt class="col-6">Tgl Hasil</dt><dd class="col-6">{{ $case->tanggal_hasil_lab?->format('d/m/Y') ?: '-' }}</dd>
                    <dt class="col-12">Hasil</dt><dd class="col-12">{{ $case->hasil_lab ?: '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-dark text-white font-weight-bold"><i class="fa fa-hospital mr-2"></i>G. Tata Laksana</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Status Rawat</dt><dd class="col-7">{{ str_replace('_', ' ', ucfirst($case->status_rawat)) }}</dd>
                    <dt class="col-5">Faskes/RS</dt><dd class="col-7">{{ $case->nama_faskes ?: '-' }}</dd>
                    <dt class="col-5">Masuk RS</dt><dd class="col-7">{{ $case->tanggal_masuk_rs?->format('d/m/Y') ?: '-' }}</dd>
                    <dt class="col-5">Keluar RS</dt><dd class="col-7">{{ $case->tanggal_keluar_rs?->format('d/m/Y') ?: '-' }}</dd>
                    <dt class="col-5">Lama Rawat</dt><dd class="col-7">{{ $case->lama_rawat ? $case->lama_rawat . ' hari' : '-' }}</dd>
                    <dt class="col-12">Terapi</dt><dd class="col-12">{{ $case->terapi_pengobatan ?: '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header text-white font-weight-bold" style="background:#6f42c1;"><i class="fa fa-flag-checkered mr-2"></i>H. Status Akhir</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Kondisi Akhir</dt><dd class="col-7">{{ str_replace('_', ' ', ucfirst($case->kondisi_akhir)) }}</dd>
                    <dt class="col-5">Tgl Kondisi</dt><dd class="col-7">{{ $case->tanggal_kondisi_akhir?->format('d/m/Y') ?: '-' }}</dd>
                    <dt class="col-5">Penyebab Meninggal</dt><dd class="col-7">{{ $case->penyebab_kematian ?: '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<!-- I & J -->
<div class="row mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header text-white font-weight-bold" style="background:#20c997;"><i class="fa fa-people-arrows mr-2"></i>I. Kontak Erat</div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4"><h4 class="text-primary">{{ $case->jumlah_kontak_erat }}</h4><small>Jumlah Kontak</small></div>
                    <div class="col-4"><h4 class="text-warning">{{ $case->kontak_dipantau }}</h4><small>Dipantau</small></div>
                    <div class="col-4"><h4 class="text-danger">{{ $case->kontak_positif }}</h4><small>Positif</small></div>
                </div>
                @if($case->keterangan_kontak)
                <hr><small>{{ $case->keterangan_kontak }}</small>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header text-white font-weight-bold" style="background:#0d6efd;"><i class="fa fa-tag mr-2"></i>J. Klasifikasi Kasus</div>
            <div class="card-body text-center d-flex align-items-center justify-content-center">
                @php $c = $statusColors[$case->status_kasus] ?? 'secondary'; @endphp
                <span class="badge badge-{{ $c }}" style="font-size:1.5rem; padding:0.5rem 1.5rem;">
                    {{ ucfirst($case->status_kasus) }}
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Audit -->
<div class="card bg-light mb-3">
    <div class="card-body py-2">
        <small class="text-muted">
            Dibuat oleh: <strong>{{ $case->createdBy->name ?? '-' }}</strong> pada {{ $case->created_at?->format('d/m/Y H:i') }} &nbsp;|&nbsp;
            Terakhir diubah oleh: <strong>{{ $case->updatedBy->name ?? '-' }}</strong> pada {{ $case->updated_at?->format('d/m/Y H:i') }}
        </small>
    </div>
</div>
@endsection
