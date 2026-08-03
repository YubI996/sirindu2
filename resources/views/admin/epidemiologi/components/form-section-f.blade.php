{{-- Section F: Pemeriksaan Laboratorium — Spesimen MoD --}}
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Status PL (Pemeriksaan Lab)</label>
            <select name="status_lab" id="status_lab" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="diperiksa" {{ old('status_lab', $case->status_lab ?? '') == 'diperiksa' ? 'selected' : '' }}>Diperiksa</option>
                <option value="tidak"     {{ old('status_lab', $case->status_lab ?? '') == 'tidak'     ? 'selected' : '' }}>Tidak</option>
            </select>
            {{-- Peringatan saja, tidak menghalangi submit (lihat catatan di bawah). --}}
            <div id="labIncompleteWarning" class="alert alert-warning mt-2 mb-0 py-2 px-3" style="display:none;">
                <i class="fa fa-exclamation-triangle"></i>
                Hasil lab sudah final. Sebaiknya lengkapi juga baris <strong>Spesimen</strong> di bawah
                beserta tanggal pengambilannya. Data tetap bisa disimpan tanpa itu.
            </div>
        </div>
    </div>
</div>

<hr>

{{-- Spesimen MoD --}}
<h6 class="section-subtitle"><i class="fa fa-vial"></i> Spesimen Laboratorium</h6>
<p class="text-muted mb-3"><i class="fa fa-info-circle"></i> Tambahkan setiap jenis spesimen yang diambil dari pasien.</p>

@php
    $penyakitTerkonfirmasiOptions = [
        'Rubella', 'Campak', 'Polio', 'Difteri', 'Pertusis', 'TN', 'Negatif',
    ];
    $spesimenRows = old('spesimen', []);
    $spesimenExisting = $case->spesimen ?? collect();
    if (empty($spesimenRows)) {
        $spesimenRows = $spesimenExisting->map(fn($s) => [
            'jenis_spesimen'               => $s->jenis_spesimen,
            'tanggal_ambil_spesimen'       => $s->tanggal_ambil_spesimen?->format('Y-m-d'),
            'tanggal_kirim_sampel'         => $s->tanggal_kirim_sampel?->format('Y-m-d'),
            'tanggal_terima_lab'           => $s->tanggal_terima_lab?->format('Y-m-d'),
            'status_pemeriksaan'           => $s->status_pemeriksaan,
            'penyakit_terkonfirmasi'       => $s->penyakit_terkonfirmasi,
            'nama_variant_genotype'        => $s->nama_variant_genotype,
        ])->toArray();
    }
@endphp

{{-- Template tersembunyi --}}
<template id="spesimenTpl">
    <div class="spesimen-row card card-body mb-3 py-3 px-3 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="small text-secondary">Spesimen</strong>
            <button type="button" class="btn btn-sm btn-outline-danger remove-spesimen-row">
                <i class="fa fa-times"></i> Hapus
            </button>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group mb-2">
                    <label class="small mb-1">Jenis Spesimen <span class="text-danger">*</span></label>
                    <input type="text" name="spesimen[__IDX__][jenis_spesimen]" class="form-control form-control-sm" placeholder="darah, urine, swab, dll">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-2">
                    <label class="small mb-1">Tanggal Ambil Spesimen</label>
                    <input type="date" name="spesimen[__IDX__][tanggal_ambil_spesimen]" class="form-control form-control-sm" max="{{ date('Y-m-d') }}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-2">
                    <label class="small mb-1">Tanggal Kirim Sampel</label>
                    <input type="date" name="spesimen[__IDX__][tanggal_kirim_sampel]" class="form-control form-control-sm" max="{{ date('Y-m-d') }}">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group mb-2">
                    <label class="small mb-1">Tanggal Terima Lab</label>
                    <input type="date" name="spesimen[__IDX__][tanggal_terima_lab]" class="form-control form-control-sm" max="{{ date('Y-m-d') }}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-2">
                    <label class="small mb-1">Status Pemeriksaan</label>
                    <input type="text" name="spesimen[__IDX__][status_pemeriksaan]" class="form-control form-control-sm" placeholder="pending, selesai, dll">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-2">
                    <label class="small mb-1">Penyakit Terkonfirmasi</label>
                    <select name="spesimen[__IDX__][penyakit_terkonfirmasi]" class="form-control form-control-sm">
                        <option value="">-- Belum dikonfirmasi --</option>
                        @foreach($penyakitTerkonfirmasiOptions as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group mb-0">
                    <label class="small mb-1">Nama Variant / Genotype</label>
                    <input type="text" name="spesimen[__IDX__][nama_variant_genotype]" class="form-control form-control-sm" placeholder="Opsional">
                </div>
            </div>
        </div>
    </div>
</template>

<div id="spesimenList">
    @foreach ($spesimenRows as $idx => $sp)
    <div class="spesimen-row card card-body mb-3 py-3 px-3 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="small text-secondary">Spesimen {{ $idx + 1 }}</strong>
            <button type="button" class="btn btn-sm btn-outline-danger remove-spesimen-row">
                <i class="fa fa-times"></i> Hapus
            </button>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group mb-2">
                    <label class="small mb-1">Jenis Spesimen <span class="text-danger">*</span></label>
                    <input type="text" name="spesimen[{{ $idx }}][jenis_spesimen]" class="form-control form-control-sm"
                           value="{{ $sp['jenis_spesimen'] ?? '' }}" placeholder="darah, urine, swab, dll">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-2">
                    <label class="small mb-1">Tanggal Ambil Spesimen</label>
                    <input type="date" name="spesimen[{{ $idx }}][tanggal_ambil_spesimen]" class="form-control form-control-sm"
                           value="{{ $sp['tanggal_ambil_spesimen'] ?? '' }}" max="{{ date('Y-m-d') }}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-2">
                    <label class="small mb-1">Tanggal Kirim Sampel</label>
                    <input type="date" name="spesimen[{{ $idx }}][tanggal_kirim_sampel]" class="form-control form-control-sm"
                           value="{{ $sp['tanggal_kirim_sampel'] ?? '' }}" max="{{ date('Y-m-d') }}">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group mb-2">
                    <label class="small mb-1">Tanggal Terima Lab</label>
                    <input type="date" name="spesimen[{{ $idx }}][tanggal_terima_lab]" class="form-control form-control-sm"
                           value="{{ $sp['tanggal_terima_lab'] ?? '' }}" max="{{ date('Y-m-d') }}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-2">
                    <label class="small mb-1">Status Pemeriksaan</label>
                    <input type="text" name="spesimen[{{ $idx }}][status_pemeriksaan]" class="form-control form-control-sm"
                           value="{{ $sp['status_pemeriksaan'] ?? '' }}" placeholder="pending, selesai, dll">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-2">
                    <label class="small mb-1">Penyakit Terkonfirmasi</label>
                    <select name="spesimen[{{ $idx }}][penyakit_terkonfirmasi]" class="form-control form-control-sm">
                        <option value="">-- Belum dikonfirmasi --</option>
                        @foreach($penyakitTerkonfirmasiOptions as $opt)
                        <option value="{{ $opt }}" {{ ($sp['penyakit_terkonfirmasi'] ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group mb-0">
                    <label class="small mb-1">Nama Variant / Genotype</label>
                    <input type="text" name="spesimen[{{ $idx }}][nama_variant_genotype]" class="form-control form-control-sm"
                           value="{{ $sp['nama_variant_genotype'] ?? '' }}" placeholder="Opsional">
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<button type="button" id="addSpesimen" class="btn btn-sm btn-outline-primary mt-1">
    <i class="fa fa-plus"></i> Tambah Spesimen
</button>

@push('js')
<script>
$(document).ready(function() {
    var spesimenIdx = {{ count($spesimenRows ?? []) }};

    $('#addSpesimen').on('click', function() {
        var tpl = document.querySelector('#spesimenTpl').innerHTML;
        tpl = tpl.replace(/__IDX__/g, spesimenIdx);
        spesimenIdx++;
        $('#spesimenList').append(tpl);
    });

    $(document).on('click', '.remove-spesimen-row', function() {
        $(this).closest('.spesimen-row').remove();
        toggleLabWarning();
    });

    // Peringatan non-blocking: status lab final tapi belum ada spesimen bertanggal.
    // JANGAN diubah menjadi 'required' — field lab yang wajib pernah membuat submit
    // gagal senyap karena field acuannya sudah tidak ada di form (2026-07-23).
    function toggleLabWarning() {
        var status = $('#status_lab').val();
        var final  = (status === 'diperiksa');
        var adaTanggal = false;

        $('#spesimenList input[name$="[tanggal_ambil_spesimen]"]').each(function() {
            if ($(this).val()) { adaTanggal = true; }
        });

        $('#labIncompleteWarning').toggle(final && !adaTanggal);
    }

    $('#status_lab').on('change', toggleLabWarning);
    $(document).on('change', '#spesimenList input[name$="[tanggal_ambil_spesimen]"]', toggleLabWarning);
    toggleLabWarning();
});
</script>
@endpush
