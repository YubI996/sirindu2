@extends('admin::layouts.app')
@section('title') Laporan Kasus PD3I @endsection
@section('title-content') Export Data @endsection
@section('item') Export Data @endsection
@section('item-active') Laporan Kasus PD3I @endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i class="fa fa-file-excel mr-2"></i> Laporan Kasus PD3I — List Kasus Individu</h2>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fa fa-download"></i> Unduh List Kasus Individu (Excel)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Pilih penyakit dan tahun, lalu unduh daftar kasus individu (.xlsx). Setiap file memuat
                        judul, kota, tanggal, dan waktu pembuatan. Kolom menyesuaikan format resmi per penyakit;
                        field yang tidak tersimpan di sistem akan kosong.
                    </p>

                    <form method="GET" action="{{ route('admin.export.pd3i.download') }}" target="_blank">
                        <div class="form-group">
                            <label for="jenis_kasus_id">Jenis Penyakit</label>
                            <select name="jenis_kasus_id" id="jenis_kasus_id" class="form-control" required>
                                <option value="">-- Pilih Penyakit --</option>
                                @foreach($diseases as $disease)
                                    <option value="{{ $disease->id }}">{{ $disease->nama_penyakit }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="tahun">Tahun</label>
                            <select name="tahun" id="tahun" class="form-control" required>
                                @foreach($tahunTersedia as $th)
                                    <option value="{{ $th }}" {{ $th == now()->year ? 'selected' : '' }}>{{ $th }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-file-excel"></i> Unduh Excel
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
