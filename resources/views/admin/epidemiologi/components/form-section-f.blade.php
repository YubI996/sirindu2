{{-- Section F: Laboratorium --}}
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Status Laboratorium <span class="text-danger">*</span></label>
            <select name="status_lab" id="status_lab" class="form-control @error('status_lab') is-invalid @enderror">
                @foreach(['belum'=>'Belum dilakukan','pending'=>'Pending','positif'=>'Positif','negatif'=>'Negatif','tidak_dilakukan'=>'Tidak Dilakukan'] as $val => $lbl)
                <option value="{{ $val }}" {{ old('status_lab', $case->status_lab ?? 'belum') == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
            @error('status_lab') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-8">
        <div class="form-group">
            <label>Jenis Pemeriksaan</label>
            <input type="text" name="jenis_pemeriksaan_lab" class="form-control"
                value="{{ old('jenis_pemeriksaan_lab', $case->jenis_pemeriksaan_lab ?? '') }}"
                placeholder="cth: PCR, Serologi, Kultur">
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Tgl Pengambilan Sampel</label>
            <input type="date" name="tanggal_pengambilan_sampel" class="form-control"
                value="{{ old('tanggal_pengambilan_sampel', isset($case) ? $case->tanggal_pengambilan_sampel?->format('Y-m-d') : '') }}">
        </div>
    </div>
    <div class="col-md-4" id="row-tgl-hasil-lab">
        <div class="form-group">
            <label>Tgl Hasil Lab <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_hasil_lab" class="form-control @error('tanggal_hasil_lab') is-invalid @enderror"
                value="{{ old('tanggal_hasil_lab', isset($case) ? $case->tanggal_hasil_lab?->format('Y-m-d') : '') }}">
            @error('tanggal_hasil_lab') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</div>
<div class="form-group">
    <label>Hasil Laboratorium</label>
    <textarea name="hasil_lab" class="form-control" rows="3">{{ old('hasil_lab', $case->hasil_lab ?? '') }}</textarea>
</div>
