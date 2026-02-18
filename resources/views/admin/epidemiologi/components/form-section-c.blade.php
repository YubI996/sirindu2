{{-- Section C: Data Kasus --}}
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Jenis Kasus <span class="text-danger">*</span></label>
            <select name="id_jenis_kasus" class="form-control @error('id_jenis_kasus') is-invalid @enderror" required>
                <option value="">Pilih Jenis Kasus</option>
                @foreach($diseases as $d)
                <option value="{{ $d->id }}" {{ old('id_jenis_kasus', $case->id_jenis_kasus ?? '') == $d->id ? 'selected' : '' }}>
                    [{{ $d->kode_penyakit }}] {{ $d->nama_penyakit }}
                </option>
                @endforeach
            </select>
            @error('id_jenis_kasus') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Diagnosa Awal</label>
            <input type="text" name="diagnosa_awal" class="form-control" value="{{ old('diagnosa_awal', $case->diagnosa_awal ?? '') }}">
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>Tanggal Onset <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_onset" class="form-control @error('tanggal_onset') is-invalid @enderror"
                value="{{ old('tanggal_onset', isset($case) ? $case->tanggal_onset?->format('Y-m-d') : '') }}" required>
            @error('tanggal_onset') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Tanggal Konsultasi</label>
            <input type="date" name="tanggal_konsultasi" class="form-control"
                value="{{ old('tanggal_konsultasi', isset($case) ? $case->tanggal_konsultasi?->format('Y-m-d') : '') }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Tanggal Lapor <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_lapor" class="form-control @error('tanggal_lapor') is-invalid @enderror"
                value="{{ old('tanggal_lapor', isset($case) ? $case->tanggal_lapor?->format('Y-m-d') : date('Y-m-d')) }}" required>
            @error('tanggal_lapor') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Tempat Berobat</label>
            <input type="text" name="tempat_berobat" class="form-control" value="{{ old('tempat_berobat', $case->tempat_berobat ?? '') }}">
        </div>
    </div>
</div>
<div class="form-group">
    <label>Catatan Kasus</label>
    <textarea name="catatan_kasus" class="form-control" rows="2">{{ old('catatan_kasus', $case->catatan_kasus ?? '') }}</textarea>
</div>
