@extends('admin::layouts.app')
@section('title')
Admin
@endsection
@section('title-content')
Data Imunisasi Lengkap
@endsection
@section('item')
Data Anak
@endsection
@section('item-active')
Imunisasi Lengkap - {{ $data->nama }}
@endsection
@section('content')
@if ($errors->any())
<div class="alert alert-danger">
    <ul>
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Informasi Anak</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>Nama:</strong> {{ $data->nama }}</p>
                        <p><strong>NIK:</strong> {{ $data->nik }}</p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Tanggal Lahir:</strong> {{ date('d-m-Y', strtotime($data->tgl_lahir)) }}</p>
                        <p><strong>Jenis Kelamin:</strong> {{ $data->jk == 'L' ? 'Laki-laki' : 'Perempuan' }}</p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Nama Ibu:</strong> {{ $data->nama_ibu }}</p>
                        <p><strong>Nama Ayah:</strong> {{ $data->nama_ayah }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Tambah Data Imunisasi</h5>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.storeImunisasiDetail') }}">
                    @csrf
                    <input type="hidden" name="id_anak_hash" value="{{ $data->hashid }}">
                    <div class="row">
                        <div class="col-md-3 col-sm-12">
                            <div class="form-group">
                                <label>Jenis Vaksin <span class="text-danger">*</span></label>
                                <select name="id_jenis_vaksin" class="form-control" required>
                                    <option value="">-- Pilih Vaksin --</option>
                                    @foreach($jenisVaksin as $vaksin)
                                    <option value="{{ $vaksin->id }}">{{ $vaksin->nama }} ({{ $vaksin->kategori }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-12">
                            <div class="form-group">
                                <label>Dosis</label>
                                <input type="number" name="dosis" class="form-control" value="1" min="1">
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-12">
                            <div class="form-group">
                                <label>Tanggal Pemberian <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_pemberian" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-12">
                            <div class="form-group">
                                <label>Tanggal Selanjutnya</label>
                                <input type="date" name="tanggal_selanjutnya" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-12">
                            <div class="form-group">
                                <label>Batch Number</label>
                                <input type="text" name="batch_number" class="form-control" placeholder="No. Batch Vaksin">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-12">
                            <div class="form-group">
                                <label>Lokasi Pemberian</label>
                                <input type="text" name="lokasi_pemberian" class="form-control" placeholder="Puskesmas/Posyandu">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-12">
                            <div class="form-group">
                                <label>Reaksi KIPI</label>
                                <input type="text" name="reaksi_kipi" class="form-control" placeholder="Jika ada reaksi">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-12">
                            <div class="form-group">
                                <label>Catatan</label>
                                <input type="text" name="catatan" class="form-control" placeholder="Catatan tambahan">
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-12 d-flex align-items-end">
                            <div class="form-group w-100">
                                <button type="submit" class="btn btn-success btn-block">Simpan</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Riwayat Imunisasi</h5>
                <a href="{{ route('admin.jadwalImunisasi', $data->hashid) }}" class="btn btn-light btn-sm">Lihat Jadwal Imunisasi</a>
            </div>
            <div class="card-body">
                @if($imunisasiList->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>No</th>
                                <th>Jenis Vaksin</th>
                                <th>Dosis</th>
                                <th>Tanggal Pemberian</th>
                                <th>Tanggal Selanjutnya</th>
                                <th>Batch Number</th>
                                <th>Lokasi</th>
                                <th>Petugas</th>
                                <th>Status</th>
                                <th>Reaksi KIPI</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($imunisasiList as $index => $imunisasi)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <strong>{{ $imunisasi->jenisVaksin->nama ?? '-' }}</strong>
                                    <br><small class="text-muted">{{ $imunisasi->jenisVaksin->kategori ?? '' }}</small>
                                </td>
                                <td>{{ $imunisasi->dosis }}</td>
                                <td>{{ $imunisasi->tanggal_pemberian ? date('d-m-Y', strtotime($imunisasi->tanggal_pemberian)) : '-' }}</td>
                                <td>{{ $imunisasi->tanggal_selanjutnya ? date('d-m-Y', strtotime($imunisasi->tanggal_selanjutnya)) : '-' }}</td>
                                <td>{{ $imunisasi->batch_number ?? '-' }}</td>
                                <td>{{ $imunisasi->lokasi_pemberian ?? '-' }}</td>
                                <td>{{ $imunisasi->petugas->name ?? '-' }}</td>
                                <td>
                                    @if($imunisasi->status == 'sudah')
                                    <span class="badge badge-success">Sudah</span>
                                    @elseif($imunisasi->status == 'terlambat')
                                    <span class="badge badge-warning">Terlambat</span>
                                    @else
                                    <span class="badge badge-secondary">Belum</span>
                                    @endif
                                </td>
                                <td>{{ $imunisasi->reaksi_kipi ?? '-' }}</td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editModal{{ $imunisasi->id }}">
                                        Edit
                                    </button>
                                    <form action="{{ route('admin.deleteImunisasiDetail', $imunisasi->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus data ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal{{ $imunisasi->id }}" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg" role="document">
                                    <div class="modal-content">
                                        <form method="post" action="{{ route('admin.updateImunisasiDetail', $imunisasi->id) }}">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Data Imunisasi</h5>
                                                <button type="button" class="close" data-dismiss="modal">
                                                    <span>&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Jenis Vaksin</label>
                                                            <select name="id_jenis_vaksin" class="form-control" required>
                                                                @foreach($jenisVaksin as $vaksin)
                                                                <option value="{{ $vaksin->id }}" {{ $imunisasi->id_jenis_vaksin == $vaksin->id ? 'selected' : '' }}>
                                                                    {{ $vaksin->nama }}
                                                                </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Dosis</label>
                                                            <input type="number" name="dosis" class="form-control" value="{{ $imunisasi->dosis }}" min="1">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Tanggal Pemberian</label>
                                                            <input type="date" name="tanggal_pemberian" class="form-control" value="{{ $imunisasi->tanggal_pemberian }}" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Tanggal Selanjutnya</label>
                                                            <input type="date" name="tanggal_selanjutnya" class="form-control" value="{{ $imunisasi->tanggal_selanjutnya }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Batch Number</label>
                                                            <input type="text" name="batch_number" class="form-control" value="{{ $imunisasi->batch_number }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Lokasi Pemberian</label>
                                                            <input type="text" name="lokasi_pemberian" class="form-control" value="{{ $imunisasi->lokasi_pemberian }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Status</label>
                                                            <select name="status" class="form-control">
                                                                <option value="sudah" {{ $imunisasi->status == 'sudah' ? 'selected' : '' }}>Sudah</option>
                                                                <option value="belum" {{ $imunisasi->status == 'belum' ? 'selected' : '' }}>Belum</option>
                                                                <option value="terlambat" {{ $imunisasi->status == 'terlambat' ? 'selected' : '' }}>Terlambat</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Reaksi KIPI</label>
                                                            <input type="text" name="reaksi_kipi" class="form-control" value="{{ $imunisasi->reaksi_kipi }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <div class="form-group">
                                                            <label>Catatan</label>
                                                            <textarea name="catatan" class="form-control" rows="2">{{ $imunisasi->catatan }}</textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="alert alert-info">
                    Belum ada data imunisasi untuk anak ini.
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-12">
        <a href="{{ route('admin.anak') }}" class="btn btn-secondary">Kembali ke Daftar Anak</a>
        <a href="{{ route('admin.dataImunisasi', $data->hashid) }}" class="btn btn-info">Imunisasi Dasar (Legacy)</a>
    </div>
</div>
@endsection
@section('custom_scripts')
@endsection
