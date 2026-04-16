{{-- Section G2: Informasi Dokter --}}
<h6 class="section-subtitle"><i class="fa fa-user-md"></i> Informasi Dokter</h6>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Nama Dokter</label>
            <input type="text" name="nama_dokter" class="form-control"
                   value="{{ old('nama_dokter', $case->nama_dokter ?? '') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Nomor Telp / HP Dokter</label>
            <input type="text" name="no_telp_dokter" class="form-control"
                   value="{{ old('no_telp_dokter', $case->no_telp_dokter ?? '') }}" maxlength="20">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Diagnosis Dokter</label>
            <input type="text" name="diagnosis_dokter" class="form-control"
                   value="{{ old('diagnosis_dokter', $case->diagnosis_dokter ?? '') }}">
        </div>
    </div>
</div>
