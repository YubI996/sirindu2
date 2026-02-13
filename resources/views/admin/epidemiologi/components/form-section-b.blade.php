{{-- Section B: Reporter Identity (4 fields) --}}
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Nama Pelapor <span class="text-danger">*</span></label>
            <input type="text" name="nama_pelapor" class="form-control"
                   value="{{ old('nama_pelapor', $case->nama_pelapor ?? '') }}" required>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Jabatan Pelapor</label>
            <input type="text" name="jabatan_pelapor" class="form-control"
                   value="{{ old('jabatan_pelapor', $case->jabatan_pelapor ?? '') }}">
            <small class="form-text text-muted">Contoh: Dokter, Perawat, Bidan, Epidemiolog</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Instansi Pelapor</label>
            <input type="text" name="instansi_pelapor" class="form-control"
                   value="{{ old('instansi_pelapor', $case->instansi_pelapor ?? '') }}">
            <small class="form-text text-muted">Contoh: Puskesmas, Rumah Sakit, Dinkes</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Telepon Pelapor</label>
            <input type="text" name="telepon_pelapor" class="form-control"
                   value="{{ old('telepon_pelapor', $case->telepon_pelapor ?? '') }}">
        </div>
    </div>
</div>
