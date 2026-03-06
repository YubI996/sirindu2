{{-- Section B: Reporter Identity & Investigation (expanded) --}}
@php
    $user = auth()->user();
    $defaultNamaPelapor = $case->nama_pelapor ?? $user->name;
    $defaultInstansi = $case->instansi_pelapor ?? optional($user->puskesmas)->name ?? optional($user->rs)->name ?? '';
@endphp
<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>Nama Petugas / Pelapor <span class="text-danger">*</span></label>
            <input type="text" name="nama_pelapor" class="form-control" readonly
                   value="{{ old('nama_pelapor', $defaultNamaPelapor) }}" required>
            <small class="form-text text-muted">Otomatis dari akun login</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Jabatan Pelapor</label>
            <input type="text" name="jabatan_pelapor" class="form-control"
                   value="{{ old('jabatan_pelapor', $case->jabatan_pelapor ?? '') }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Nama Faskes Pelapor</label>
            <input type="text" name="instansi_pelapor" class="form-control" readonly
                   value="{{ old('instansi_pelapor', $defaultInstansi) }}">
            <small class="form-text text-muted">Otomatis dari akun login</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Telepon Pelapor</label>
            <input type="text" name="telepon_pelapor" class="form-control"
                   value="{{ old('telepon_pelapor', $case->telepon_pelapor ?? '') }}" maxlength="20">
        </div>
    </div>
</div>

{{-- Google Form additions --}}
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Wilker Puskesmas</label>
            <input type="text" name="wilker_puskesmas" class="form-control"
                   value="{{ old('wilker_puskesmas', $case->wilker_puskesmas ?? '') }}"
                   placeholder="Sesuai lokasi kelurahan kasus">
            <small class="form-text text-muted">Wilayah kerja Puskesmas sesuai lokasi kelurahan kasus</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Terima Laporan</label>
            <input type="date" name="tanggal_terima_laporan" class="form-control"
                   value="{{ old('tanggal_terima_laporan', isset($case) && $case->tanggal_terima_laporan ? $case->tanggal_terima_laporan->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Penyidikan</label>
            <input type="date" name="tanggal_penyidikan" class="form-control"
                   value="{{ old('tanggal_penyidikan', isset($case) && $case->tanggal_penyidikan ? $case->tanggal_penyidikan->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
</div>
