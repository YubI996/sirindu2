@extends('admin::layouts.app')
@section('title')
Admin - SiRindu
@endsection
@section('title-content')
Data
@endsection
@section('item')
Data
@endsection
@section('item-active')
Data Anak
@endsection
@section('content')
@if ($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif
<form method="post" action="{{route('admin.storeDataAnak')}}">
    @csrf
    <div class="row">
        <input type="hidden" name="id_anak_hash" value="{{$anak->hashid}}" class="form-control" required>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="tgl_kunjungan">Tanggal Kunjungan <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="date" name="tgl_kunjungan" id="tgl_kunjungan" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="nama_readonly">Nama <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="nama" id="nama_readonly" value="{{$anak->nama}}" class="form-control" required readonly>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="bln">Umur (Bulan) <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="bln" id="bln" value="{{$bulanSekarang}}" class="form-control" required readonly>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="posisi">Posisi <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="posisi" id="posisi" class="form-control" required>
                    <option value="H">H</option>
                    <option value="L">L</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="tb">Tinggi Badan <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="number" step="any" name="tb" id="tb" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk angka desimal.</small>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="bb">Berat Badan <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="number" step="any" name="bb" id="bb" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk angka desimal.</small>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="lla">Lingkar Lengan Atas <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="number" step="any" name="lla" id="lla" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk angka desimal.</small>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="lk">Lingkar Kepala <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="number" step="any" name="lk" id="lk" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk angka desimal.</small>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="asi">ASI Eksklusif <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="asi" id="asi" class="form-control" required>
                    <option value="0">Tidak</option>
                    <option value="1">Ya</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="vit_a">Vitamin A <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="vit_a" id="vit_a" class="form-control" required>
                    <option value="0">Tidak</option>
                    <option value="1">Ya</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="obat_cacing">Obat Cacing <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="obat_cacing" id="obat_cacing" class="form-control" required>
                    <option value="0">Tidak</option>
                    <option value="1">Ya</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>DDTKA</label>
                <input type="text" name="ddtka" class="form-control">
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="imunisasi_terakhir">Imunisasi Terakhir</label>
                <select name="imunisasi_terakhir" id="imunisasi_terakhir" class="form-control">
                    <option value="">-- Pilih Vaksin --</option>
                    @foreach($jenisVaksin as $vaksin)
                    <option value="{{ $vaksin->nama }}">{{ $vaksin->nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-8 col-sm-12">
            <div class="form-group">
                <label for="alasan_tidak_imunisasi">Alasan Tidak Menerima Imunisasi</label>
                <input type="text" name="alasan_tidak_imunisasi" id="alasan_tidak_imunisasi"
                       class="form-control" placeholder="Contoh: sakit, orang tua menolak, vaksin habis...">
            </div>
        </div>
    </div>

    {{-- Section Imunisasi (Opsional) --}}
    <hr>
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="toggleImunisasi">
                <label class="form-check-label" for="toggleImunisasi">
                    <strong>Tambah data imunisasi pada kunjungan ini</strong>
                </label>
            </div>
        </div>
    </div>

    <div id="sectionImunisasi" style="display: none;">
        <div class="card mb-3">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Data Imunisasi</h6>
                <button type="button" class="btn btn-light btn-sm" id="btnTambahVaksin">+ Tambah Vaksin Lagi</button>
            </div>
            <div class="card-body" id="imunisasiContainer">
                {{-- Baris imunisasi pertama --}}
                <div class="imunisasi-row border rounded p-3 mb-3" data-index="0">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Vaksin #1</strong>
                    </div>
                    <div class="row">
                        <div class="col-md-3 col-sm-12">
                            <div class="form-group">
                                <label>Jenis Vaksin <span class="text-danger">*</span></label>
                                <select name="imunisasi[0][id_jenis_vaksin]" class="form-control imunisasi-required">
                                    <option value="">-- Pilih Vaksin --</option>
                                    @foreach($jenisVaksin as $vaksin)
                                    <option value="{{ $vaksin->id }}">{{ $vaksin->nama }} ({{ $vaksin->kategori }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-12">
                            <div class="form-group">
                                <label>Dosis</label>
                                <input type="number" name="imunisasi[0][dosis]" class="form-control" value="1" min="1">
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-12">
                            <div class="form-group">
                                <label>Tanggal Pemberian <span class="text-danger">*</span></label>
                                <input type="date" name="imunisasi[0][tanggal_pemberian]" class="form-control imunisasi-required tgl-pemberian">
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-12">
                            <div class="form-group">
                                <label>Batch Number</label>
                                <input type="text" name="imunisasi[0][batch_number]" class="form-control" placeholder="No. Batch">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-12">
                            <div class="form-group">
                                <label>Lokasi Pemberian</label>
                                <select name="imunisasi[0][lokasi_pemberian]" class="form-control">
                                    <option value="">-- Pilih Lokasi --</option>
                                    <option value="Lengan Kiri">Lengan Kiri</option>
                                    <option value="Lengan Kanan">Lengan Kanan</option>
                                    <option value="Paha Kiri">Paha Kiri</option>
                                    <option value="Paha Kanan">Paha Kanan</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-12">
                            <div class="form-group">
                                <label>Reaksi KIPI</label>
                                <input type="text" name="imunisasi[0][reaksi_kipi]" class="form-control" placeholder="Jika ada reaksi">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-12">
                            <div class="form-group">
                                <label>Catatan</label>
                                <input type="text" name="imunisasi[0][catatan]" class="form-control" placeholder="Catatan tambahan">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12 col-sm-12">
            <button type="submit" class="btn btn-primary">Submit</button>
        </div>
    </div>
</form>

@endsection
@section('custom_scripts')
<script>
(function() {
    var imunisasiIndex = 1;
    var toggleCheckbox = document.getElementById('toggleImunisasi');
    var sectionImunisasi = document.getElementById('sectionImunisasi');
    var btnTambah = document.getElementById('btnTambahVaksin');
    var container = document.getElementById('imunisasiContainer');
    var tglKunjungan = document.getElementById('tgl_kunjungan');

    // Toggle visibility section imunisasi
    toggleCheckbox.addEventListener('change', function() {
        sectionImunisasi.style.display = this.checked ? 'block' : 'none';
        // Toggle required pada field imunisasi
        var requiredFields = sectionImunisasi.querySelectorAll('.imunisasi-required');
        requiredFields.forEach(function(field) {
            if (toggleCheckbox.checked) {
                field.setAttribute('required', 'required');
            } else {
                field.removeAttribute('required');
            }
        });
    });

    // Sync tanggal kunjungan ke tanggal pemberian imunisasi
    tglKunjungan.addEventListener('change', function() {
        var tglFields = document.querySelectorAll('.tgl-pemberian');
        tglFields.forEach(function(field) {
            if (!field.value) {
                field.value = tglKunjungan.value;
            }
        });
    });

    // Tambah baris vaksin baru
    btnTambah.addEventListener('click', function() {
        var idx = imunisasiIndex;
        var tglDefault = tglKunjungan.value || '';
        var vaksinOptions = document.querySelector('select[name="imunisasi[0][id_jenis_vaksin]"]').innerHTML;

        var html = '<div class="imunisasi-row border rounded p-3 mb-3" data-index="' + idx + '">'
            + '<div class="d-flex justify-content-between align-items-center mb-2">'
            + '<strong>Vaksin #' + (idx + 1) + '</strong>'
            + '<button type="button" class="btn btn-danger btn-sm btn-hapus-vaksin">Hapus</button>'
            + '</div>'
            + '<div class="row">'
            + '<div class="col-md-3 col-sm-12"><div class="form-group">'
            + '<label>Jenis Vaksin <span class="text-danger">*</span></label>'
            + '<select name="imunisasi[' + idx + '][id_jenis_vaksin]" class="form-control imunisasi-required" required>' + vaksinOptions + '</select>'
            + '</div></div>'
            + '<div class="col-md-2 col-sm-12"><div class="form-group">'
            + '<label>Dosis</label>'
            + '<input type="number" name="imunisasi[' + idx + '][dosis]" class="form-control" value="1" min="1">'
            + '</div></div>'
            + '<div class="col-md-2 col-sm-12"><div class="form-group">'
            + '<label>Tanggal Pemberian <span class="text-danger">*</span></label>'
            + '<input type="date" name="imunisasi[' + idx + '][tanggal_pemberian]" class="form-control imunisasi-required tgl-pemberian" value="' + tglDefault + '" required>'
            + '</div></div>'
            + '<div class="col-md-2 col-sm-12"><div class="form-group">'
            + '<label>Batch Number</label>'
            + '<input type="text" name="imunisasi[' + idx + '][batch_number]" class="form-control" placeholder="No. Batch">'
            + '</div></div>'
            + '<div class="col-md-3 col-sm-12"><div class="form-group">'
            + '<label>Lokasi Pemberian</label>'
            + '<select name="imunisasi[' + idx + '][lokasi_pemberian]" class="form-control">'
            + '<option value="">-- Pilih Lokasi --</option>'
            + '<option value="Lengan Kiri">Lengan Kiri</option>'
            + '<option value="Lengan Kanan">Lengan Kanan</option>'
            + '<option value="Paha Kiri">Paha Kiri</option>'
            + '<option value="Paha Kanan">Paha Kanan</option>'
            + '</select>'
            + '</div></div>'
            + '<div class="col-md-3 col-sm-12"><div class="form-group">'
            + '<label>Reaksi KIPI</label>'
            + '<input type="text" name="imunisasi[' + idx + '][reaksi_kipi]" class="form-control" placeholder="Jika ada reaksi">'
            + '</div></div>'
            + '<div class="col-md-4 col-sm-12"><div class="form-group">'
            + '<label>Catatan</label>'
            + '<input type="text" name="imunisasi[' + idx + '][catatan]" class="form-control" placeholder="Catatan tambahan">'
            + '</div></div>'
            + '</div></div>';

        container.insertAdjacentHTML('beforeend', html);
        imunisasiIndex++;
    });

    // Hapus baris vaksin
    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-hapus-vaksin')) {
            e.target.closest('.imunisasi-row').remove();
        }
    });
})();
</script>
@endsection
