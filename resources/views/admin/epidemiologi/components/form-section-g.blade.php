{{-- Section G: Tata Laksana --}}
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Status Rawat <span class="text-danger">*</span></label>
            <select name="status_rawat" id="status_rawat" class="form-control">
                @foreach(['rawat_jalan'=>'Rawat Jalan','rawat_inap'=>'Rawat Inap','rujukan'=>'Dirujuk','tidak_berobat'=>'Tidak Berobat'] as $val => $lbl)
                <option value="{{ $val }}" {{ old('status_rawat', $case->status_rawat ?? 'rawat_jalan') == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-8">
        <div class="form-group">
            <label>Nama Faskes/RS</label>
            <input type="text" name="nama_faskes" class="form-control" value="{{ old('nama_faskes', $case->nama_faskes ?? '') }}">
        </div>
    </div>
</div>
<div id="row-rawat-tanggal" class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Masuk RS <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_masuk_rs" id="tanggal_masuk_rs" class="form-control @error('tanggal_masuk_rs') is-invalid @enderror"
                value="{{ old('tanggal_masuk_rs', isset($case) ? $case->tanggal_masuk_rs?->format('Y-m-d') : '') }}">
            @error('tanggal_masuk_rs') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Keluar RS</label>
            <input type="date" name="tanggal_keluar_rs" id="tanggal_keluar_rs" class="form-control"
                value="{{ old('tanggal_keluar_rs', isset($case) ? $case->tanggal_keluar_rs?->format('Y-m-d') : '') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Lama Rawat</label>
            <div class="form-control-plaintext font-weight-bold" id="display-lama-rawat">
                {{ isset($case) && $case->lama_rawat ? $case->lama_rawat . ' hari' : '-' }}
            </div>
        </div>
    </div>
</div>
<div class="form-group">
    <label>Terapi/Pengobatan</label>
    <textarea name="terapi_pengobatan" class="form-control" rows="3">{{ old('terapi_pengobatan', $case->terapi_pengobatan ?? '') }}</textarea>
</div>
