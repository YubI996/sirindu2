{{-- Section G2: Dokter & Tempat Berobat --}}
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

<hr>

<h6 class="section-subtitle"><i class="fa fa-hospital-alt"></i> Tempat Berobat</h6>
<p class="text-muted mb-3"><i class="fa fa-info-circle"></i> Centang sesuai riwayat kunjungan berobat pasien.</p>

<div class="row">
    <div class="col-md-12 mb-3">
        <div class="d-flex flex-wrap gap-3">
            @php
            $tempatBerobatOptions = ['RS', 'Puskesmas/FKTP', 'Klinik', 'Praktek Dokter', 'Pengobatan Tradisional', 'Lainnya'];
            $savedTempatBerobat = old('tempat_berobat', isset($case) ? json_decode($case->tempat_berobat, true) : []) ?? [];
            @endphp
            @foreach ($tempatBerobatOptions as $opt)
            <div class="custom-control custom-checkbox mr-3">
                <input type="checkbox" name="tempat_berobat[]" value="{{ $opt }}"
                       class="custom-control-input" id="tempat_berobat_{{ Str::slug($opt) }}"
                       {{ in_array($opt, $savedTempatBerobat) ? 'checked' : '' }}>
                <label class="custom-control-label" for="tempat_berobat_{{ Str::slug($opt) }}">{{ $opt }}</label>
            </div>
            @endforeach
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Nama RS</label>
            <input type="text" name="nama_rs" class="form-control"
                   value="{{ old('nama_rs', $case->nama_rs ?? '') }}"
                   placeholder="Diisi sesuai pilihan">
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Tanggal Kunjungan RS</label>
            <input type="date" name="tanggal_kunjungan_rs" class="form-control"
                   value="{{ old('tanggal_kunjungan_rs', isset($case) && $case->tanggal_kunjungan_rs ? $case->tanggal_kunjungan_rs->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Nama FKTP (Puskesmas / Klinik / Praktek Dokter)</label>
            <input type="text" name="nama_fktp" class="form-control"
                   value="{{ old('nama_fktp', $case->nama_fktp ?? '') }}">
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Tanggal Kunjungan FKTP</label>
            <input type="date" name="tanggal_kunjungan_fktp" class="form-control"
                   value="{{ old('tanggal_kunjungan_fktp', isset($case) && $case->tanggal_kunjungan_fktp ? $case->tanggal_kunjungan_fktp->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Nama Pengobatan Tradisional</label>
            <input type="text" name="nama_pengobatan_tradisional" class="form-control"
                   value="{{ old('nama_pengobatan_tradisional', $case->nama_pengobatan_tradisional ?? '') }}">
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Tanggal Kunjungan</label>
            <input type="date" name="tanggal_kunjungan_tradisional" class="form-control"
                   value="{{ old('tanggal_kunjungan_tradisional', isset($case) && $case->tanggal_kunjungan_tradisional ? $case->tanggal_kunjungan_tradisional->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
</div>
