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
<form method="post" action="{{route('admin.storeDataAnak')}}">
    @csrf
    <div class="row">
        <input type="hidden" name="id_anak_hash" value="{{$anak->hashid}}" class="form-control" require>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Tanggal Kunjungan <font color="red">*</font> </label>
                <input type="date" name="tgl_kunjungan" id="tgl_kunjungan" class="form-control" require>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Nama <font color="red">*</font></label>
                <input type="text" name="nama" value="{{$anak->nama}}" class="form-control" require readonly>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Umur (Bulan) <font color="red">*</font></label>
                <input type="text" name="bln" value="{{$bulanSekarang}}" class="form-control" require readonly>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Posisi<font color="red">*</font></label>
                <select name="posisi" class="form-control" require>
                    <option value="H">H</option>
                    <option value="L">L</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Tinggi Badan <font color="red">* gunakan titik (.) untuk angka desimal</font></label>
                <input type="number" step="any" name="tb" class="form-control" require>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Berat Badan <font color="red">* gunakan titik (.) untuk angka desimal</font></label>
                <input type="number" step="any" name="bb" class="form-control" require>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Lingkar Lengan Atas <font color="red">* gunakan titik (.) untuk angka desimal</font></label>
                <input type="number" step="any" name="lla" class="form-control" require>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Lingkar Kepala <font color="red">* gunakan titik (.) untuk angka desimal</font></label>
                <input type="number" step="any" name="lk" class="form-control" require>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Asi Ekslusif <font color="red">*</font></label>
                <select name="asi" class="form-control" require>
                    <option value="0">Tidak</option>
                    <option value="1">Ya</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Vitamin A <font color="red">*</font></label>
                <select name="vit_a" class="form-control" require>
                    <option value="0">Tidak</option>
                    <option value="1">Ya</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Obat Cacing <font color="red">*</font></label>
                <select name="obat_cacing" class="form-control" require>
                    <option value="0">Tidak</option>
                    <option value="1">Ya</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>DDTKA</label>
                <input type="text" step="any" name="ddtka" class="form-control" require>
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
