{{-- Section H: Status Akhir --}}
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Kondisi Akhir <span class="text-danger">*</span></label>
            <select name="kondisi_akhir" id="kondisi_akhir" class="form-control @error('kondisi_akhir') is-invalid @enderror">
                @foreach(['dalam_perawatan'=>'Dalam Perawatan','sembuh'=>'Sembuh','meninggal'=>'Meninggal','tidak_diketahui'=>'Tidak Diketahui'] as $val => $lbl)
                <option value="{{ $val }}" {{ old('kondisi_akhir', $case->kondisi_akhir ?? 'dalam_perawatan') == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
            @error('kondisi_akhir') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Kondisi Akhir</label>
            <input type="date" name="tanggal_kondisi_akhir" class="form-control"
                value="{{ old('tanggal_kondisi_akhir', isset($case) ? $case->tanggal_kondisi_akhir?->format('Y-m-d') : '') }}">
        </div>
    </div>
    <div class="col-md-4" id="row-penyebab-kematian">
        <div class="form-group">
            <label>Penyebab Kematian <span class="text-danger">*</span></label>
            <input type="text" name="penyebab_kematian" class="form-control @error('penyebab_kematian') is-invalid @enderror"
                value="{{ old('penyebab_kematian', $case->penyebab_kematian ?? '') }}">
            @error('penyebab_kematian') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</div>
