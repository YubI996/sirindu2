{{-- Section G: Management + Tempat Berobat MoD --}}
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Status Perawatan <span class="text-danger">*</span></label>
            <select name="status_rawat" id="status_rawat" class="form-control" required>
                <option value="">== Pilih Status ==</option>
                <option value="rawat_jalan"     {{ old('status_rawat', $case->status_rawat ?? '') == 'rawat_jalan'     ? 'selected' : '' }}>Rawat Jalan</option>
                <option value="rawat_inap"      {{ old('status_rawat', $case->status_rawat ?? '') == 'rawat_inap'      ? 'selected' : '' }}>Rawat Inap</option>
                <option value="isolasi_mandiri" {{ old('status_rawat', $case->status_rawat ?? '') == 'isolasi_mandiri' ? 'selected' : '' }}>Isolasi Mandiri</option>
                <option value="rujuk"           {{ old('status_rawat', $case->status_rawat ?? '') == 'rujuk'           ? 'selected' : '' }}>Rujuk</option>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Nama Fasilitas Kesehatan <span class="text-danger">*</span></label>
            <input type="text" name="nama_faskes_rawat" class="form-control"
                   value="{{ old('nama_faskes_rawat', $case->nama_faskes_rawat ?? '') }}" required>
            <small class="form-text text-muted">Puskesmas/RS/Klinik tempat perawatan utama</small>
        </div>
    </div>
</div>

<div class="row" id="rawat_dates" style="display: none;">
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Masuk Rawat</label>
            <input type="date" name="tanggal_masuk_rawat" id="tanggal_masuk_rawat" class="form-control"
                   value="{{ old('tanggal_masuk_rawat', isset($case->tanggal_masuk_rawat) ? $case->tanggal_masuk_rawat->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Keluar Rawat</label>
            <input type="date" name="tanggal_keluar_rawat" id="tanggal_keluar_rawat" class="form-control"
                   value="{{ old('tanggal_keluar_rawat', isset($case->tanggal_keluar_rawat) ? $case->tanggal_keluar_rawat->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Lama Rawat (Hari)</label>
            <input type="text" id="lama_rawat_display" class="form-control" readonly>
            <small class="form-text text-muted">Dihitung otomatis</small>
        </div>
    </div>
</div>

<hr>

{{-- Tempat Berobat MoD --}}
<h6 class="section-subtitle"><i class="fa fa-hospital-alt"></i> Riwayat Tempat Berobat</h6>
<p class="text-muted mb-3"><i class="fa fa-info-circle"></i> Tambahkan semua fasilitas kesehatan yang pernah dikunjungi pasien.</p>

{{-- Template tersembunyi --}}
<template id="faskesTpl">
    <div class="faskes-berobat-row card card-body mb-2 py-2 px-3 bg-light">
        <div class="row align-items-center">
            <div class="col-md-2">
                <label class="small mb-1">Jenis Faskes</label>
                <select name="faskes_berobat[__IDX__][jenis_faskes]" class="form-control form-control-sm">
                    <option value="rs">RS</option>
                    <option value="puskesmas">Puskesmas</option>
                    <option value="klinik">Klinik</option>
                    <option value="pengobatan_tradisional">Pengobatan Tradisional</option>
                    <option value="lainnya">Lainnya</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="small mb-1">Nama Faskes</label>
                <input type="text" name="faskes_berobat[__IDX__][nama_faskes]" class="form-control form-control-sm" placeholder="Nama faskes">
            </div>
            <div class="col-md-2">
                <label class="small mb-1">Tgl Berobat</label>
                <input type="date" name="faskes_berobat[__IDX__][tanggal_berobat]" class="form-control form-control-sm" max="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-2">
                <label class="small mb-1">Jenis Perawatan</label>
                <select name="faskes_berobat[__IDX__][jenis_perawatan]" class="form-control form-control-sm">
                    <option value="">-- Pilih --</option>
                    <option value="jalan">Rawat Jalan</option>
                    <option value="inap">Rawat Inap</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small mb-1">Tgl Keluar</label>
                <input type="date" name="faskes_berobat[__IDX__][tanggal_keluar]" class="form-control form-control-sm" max="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-1 d-flex align-items-end pb-1">
                <button type="button" class="btn btn-sm btn-outline-danger remove-faskes-row w-100">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<div id="faskesBerobatList">
    @php
        $faskesRows = old('faskes_berobat', []);
        $faskesExisting = $case->faskesBerobat ?? collect();
        if (empty($faskesRows)) {
            $faskesRows = $faskesExisting->map(fn($f) => [
                'jenis_faskes'    => $f->jenis_faskes,
                'nama_faskes'     => $f->nama_faskes,
                'tanggal_berobat' => $f->tanggal_berobat?->format('Y-m-d'),
                'jenis_perawatan' => $f->jenis_perawatan,
                'tanggal_keluar'  => $f->tanggal_keluar?->format('Y-m-d'),
            ])->toArray();
        }
    @endphp

    @foreach ($faskesRows as $idx => $fb)
    <div class="faskes-berobat-row card card-body mb-2 py-2 px-3 bg-light">
        <div class="row align-items-center">
            <div class="col-md-2">
                <label class="small mb-1">Jenis Faskes</label>
                <select name="faskes_berobat[{{ $idx }}][jenis_faskes]" class="form-control form-control-sm">
                    @foreach(['rs' => 'RS', 'puskesmas' => 'Puskesmas', 'klinik' => 'Klinik', 'pengobatan_tradisional' => 'Pengobatan Tradisional', 'lainnya' => 'Lainnya'] as $val => $lbl)
                    <option value="{{ $val }}" {{ ($fb['jenis_faskes'] ?? '') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="small mb-1">Nama Faskes</label>
                <input type="text" name="faskes_berobat[{{ $idx }}][nama_faskes]" class="form-control form-control-sm"
                       value="{{ $fb['nama_faskes'] ?? '' }}" placeholder="Nama faskes">
            </div>
            <div class="col-md-2">
                <label class="small mb-1">Tgl Berobat</label>
                <input type="date" name="faskes_berobat[{{ $idx }}][tanggal_berobat]" class="form-control form-control-sm"
                       value="{{ $fb['tanggal_berobat'] ?? '' }}" max="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-2">
                <label class="small mb-1">Jenis Perawatan</label>
                <select name="faskes_berobat[{{ $idx }}][jenis_perawatan]" class="form-control form-control-sm">
                    <option value="">-- Pilih --</option>
                    <option value="jalan" {{ ($fb['jenis_perawatan'] ?? '') === 'jalan' ? 'selected' : '' }}>Rawat Jalan</option>
                    <option value="inap"  {{ ($fb['jenis_perawatan'] ?? '') === 'inap'  ? 'selected' : '' }}>Rawat Inap</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small mb-1">Tgl Keluar</label>
                <input type="date" name="faskes_berobat[{{ $idx }}][tanggal_keluar]" class="form-control form-control-sm"
                       value="{{ $fb['tanggal_keluar'] ?? '' }}" max="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-1 d-flex align-items-end pb-1">
                <button type="button" class="btn btn-sm btn-outline-danger remove-faskes-row w-100">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    @endforeach
</div>

<button type="button" id="addFaskesBerobat" class="btn btn-sm btn-outline-primary mt-1">
    <i class="fa fa-plus"></i> Tambah Tempat Berobat
</button>

@push('js')
<script>
$(document).ready(function() {
    // Toggle rawat dates
    function toggleRawatDates() {
        var status = $('#status_rawat').val();
        if (status === 'rawat_inap' || status === 'isolasi_mandiri') {
            $('#rawat_dates').show();
        } else {
            $('#rawat_dates').hide();
        }
    }
    $('#status_rawat').on('change', toggleRawatDates);
    toggleRawatDates();

    // Auto-calculate lama rawat
    function calculateLamaRawat() {
        var masuk = new Date($('#tanggal_masuk_rawat').val());
        var keluar = new Date($('#tanggal_keluar_rawat').val());
        if (masuk && keluar && keluar >= masuk) {
            var diff = Math.floor((keluar - masuk) / (1000 * 60 * 60 * 24)) + 1;
            $('#lama_rawat_display').val(diff + ' hari');
        } else {
            $('#lama_rawat_display').val('');
        }
    }
    $('#tanggal_masuk_rawat, #tanggal_keluar_rawat').on('change', function() {
        calculateLamaRawat();
        var masuk = new Date($('#tanggal_masuk_rawat').val());
        var keluar = new Date($('#tanggal_keluar_rawat').val());
        if (keluar && keluar < masuk) {
            alert('Tanggal keluar rawat tidak boleh sebelum tanggal masuk rawat');
            $('#tanggal_keluar_rawat').val('');
        }
    });
    if ($('#tanggal_masuk_rawat').val() && $('#tanggal_keluar_rawat').val()) {
        calculateLamaRawat();
    }

    // MoD: Tambah Tempat Berobat
    var faskesIdx = {{ count($faskesRows ?? []) }};
    $('#addFaskesBerobat').on('click', function() {
        var tpl = document.querySelector('#faskesTpl').innerHTML;
        tpl = tpl.replace(/__IDX__/g, faskesIdx);
        faskesIdx++;
        $('#faskesBerobatList').append(tpl);
    });

    // MoD: Hapus baris
    $(document).on('click', '.remove-faskes-row', function() {
        $(this).closest('.faskes-berobat-row').remove();
    });
});
</script>
@endpush
