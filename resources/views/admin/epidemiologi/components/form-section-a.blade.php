{{-- Section A: Identitas Penderita --}}
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>No. Registrasi <span class="text-danger">*</span></label>
            <input type="text" name="no_registrasi" id="no_registrasi" class="form-control @error('no_registrasi') is-invalid @enderror"
                value="{{ old('no_registrasi', $suggestedRegNumber ?? '') }}" required>
            @error('no_registrasi') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-8">
        <div class="form-group">
            <label>Nama Lengkap <span class="text-danger">*</span></label>
            <input type="text" name="nama_lengkap" class="form-control @error('nama_lengkap') is-invalid @enderror"
                value="{{ old('nama_lengkap', $case->nama_lengkap ?? '') }}" required>
            @error('nama_lengkap') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>NIK</label>
            <input type="text" name="nik" id="nik" class="form-control @error('nik') is-invalid @enderror"
                value="{{ old('nik', $case->nik ?? '') }}" maxlength="16" placeholder="16 digit NIK">
            <div id="nik-warning" class="text-warning small d-none"><i class="fa fa-exclamation-triangle"></i> NIK sudah terdaftar sebelumnya.</div>
            @error('nik') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Tanggal Lahir <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_lahir" id="tanggal_lahir" class="form-control @error('tanggal_lahir') is-invalid @enderror"
                value="{{ old('tanggal_lahir', isset($case) ? $case->tanggal_lahir?->format('Y-m-d') : '') }}" required>
            @error('tanggal_lahir') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Umur</label>
            <div class="form-control-plaintext font-weight-bold" id="display-umur">-</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Jenis Kelamin <span class="text-danger">*</span></label>
            <select name="jenis_kelamin" class="form-control @error('jenis_kelamin') is-invalid @enderror" required>
                <option value="">Pilih</option>
                <option value="L" {{ old('jenis_kelamin', $case->jenis_kelamin ?? '') == 'L' ? 'selected' : '' }}>Laki-laki</option>
                <option value="P" {{ old('jenis_kelamin', $case->jenis_kelamin ?? '') == 'P' ? 'selected' : '' }}>Perempuan</option>
            </select>
            @error('jenis_kelamin') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</div>
<div class="form-group">
    <label>Alamat Lengkap <span class="text-danger">*</span></label>
    <textarea name="alamat_lengkap" class="form-control @error('alamat_lengkap') is-invalid @enderror" rows="2" required>{{ old('alamat_lengkap', $case->alamat_lengkap ?? '') }}</textarea>
    @error('alamat_lengkap') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Kecamatan</label>
            <select name="id_kec" id="id_kec" class="form-control">
                <option value="">Pilih Kecamatan</option>
                @foreach($kecamatanList as $kec)
                <option value="{{ $kec->id }}" {{ old('id_kec', $case->id_kec ?? '') == $kec->id ? 'selected' : '' }}>
                    {{ $kec->nama_kecamatan }}
                </option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Kelurahan</label>
            <select name="id_kel" id="id_kel" class="form-control">
                <option value="">Pilih Kelurahan</option>
                @if(isset($kelurahanList))
                    @foreach($kelurahanList as $kel)
                    <option value="{{ $kel->id }}" {{ old('id_kel', $case->id_kel ?? '') == $kel->id ? 'selected' : '' }}>{{ $kel->nama_kelurahan }}</option>
                    @endforeach
                @endif
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>RT</label>
            <select name="id_rt" id="id_rt" class="form-control">
                <option value="">Pilih RT</option>
                @if(isset($rtList))
                    @foreach($rtList as $rt)
                    <option value="{{ $rt->id }}" {{ old('id_rt', $case->id_rt ?? '') == $rt->id ? 'selected' : '' }}>RT {{ $rt->no_rt }}</option>
                    @endforeach
                @endif
            </select>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Nama Orang Tua/Wali <small class="text-muted">(untuk bayi/anak)</small></label>
            <input type="text" name="nama_orang_tua" class="form-control" value="{{ old('nama_orang_tua', $case->nama_orang_tua ?? '') }}">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Pekerjaan</label>
            <input type="text" name="pekerjaan" class="form-control" value="{{ old('pekerjaan', $case->pekerjaan ?? '') }}">
        </div>
    </div>
</div>
