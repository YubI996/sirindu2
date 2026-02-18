{{-- Section B: Data Pelapor --}}
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Nama Pelapor</label>
            <input type="text" name="nama_pelapor" class="form-control" value="{{ old('nama_pelapor', $case->nama_pelapor ?? '') }}">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Jabatan</label>
            <input type="text" name="jabatan_pelapor" class="form-control" value="{{ old('jabatan_pelapor', $case->jabatan_pelapor ?? '') }}">
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-8">
        <div class="form-group">
            <label>Instansi/Faskes</label>
            <input type="text" name="instansi_pelapor" class="form-control" value="{{ old('instansi_pelapor', $case->instansi_pelapor ?? '') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Telepon</label>
            <input type="text" name="telp_pelapor" class="form-control" value="{{ old('telp_pelapor', $case->telp_pelapor ?? '') }}">
        </div>
    </div>
</div>
