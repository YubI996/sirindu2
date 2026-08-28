{{-- Section A: Patient Identity (expanded with Google Form fields) --}}
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>No. Epid</label>
            {{-- Boleh diisi saat menambah kasus (Dinkes maupun faskes) — mis. menomori
                 sesuai register resmi. Pada kasus yang sudah bernomor, hanya Super Admin
                 yang boleh mengubahnya. --}}
            @php($bolehIsiNoEpid = !isset($case) || !$case->no_registrasi || Auth::user()->isSuperAdmin())
            @if($bolehIsiNoEpid)
                <input type="text" name="no_registrasi"
                       class="form-control @error('no_registrasi') is-invalid @enderror"
                       value="{{ old('no_registrasi', $case->no_registrasi ?? '') }}"
                       placeholder="Kosongkan untuk generate otomatis">
                @error('no_registrasi')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Kosongkan untuk generate otomatis</small>
            @else
                <input type="text" class="form-control" value="{{ $case->no_registrasi }}" readonly>
                <small class="form-text text-muted">Nomor epidemiologi otomatis</small>
            @endif
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>NIK <span class="text-danger">*</span></label>
            <input type="text" name="nik" id="nik" class="form-control"
                   value="{{ old('nik', $case->nik ?? '') }}"
                   maxlength="16" pattern="[0-9]{16}" required>
            <small class="form-text text-muted">16 digit — biodata terisi otomatis bila NIK sudah terdata</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Nama Pasien <span class="text-danger">*</span></label>
            <input type="text" name="nama_lengkap" class="form-control"
                   value="{{ old('nama_lengkap', $case->nama_lengkap ?? '') }}" required>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>Tanggal Lahir <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_lahir" id="tanggal_lahir" class="form-control"
                   value="{{ old('tanggal_lahir', isset($case) ? $case->tanggal_lahir->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}" required>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Umur</label>
            <input type="text" id="umur_display" class="form-control" readonly>
            <small class="form-text text-muted">Otomatis</small>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Kategori Umur <span class="text-danger">*</span></label>
            <select name="kategori_umur" id="kategori_umur" class="form-control" required>
                <option value="">Pilih</option>
                <option value="bayi" {{ old('kategori_umur', $case->kategori_umur ?? '') == 'bayi' ? 'selected' : '' }}>Bayi (<1 thn)</option>
                <option value="balita" {{ old('kategori_umur', $case->kategori_umur ?? '') == 'balita' ? 'selected' : '' }}>Balita (1-4)</option>
                <option value="anak" {{ old('kategori_umur', $case->kategori_umur ?? '') == 'anak' ? 'selected' : '' }}>Anak (5-11)</option>
                <option value="remaja" {{ old('kategori_umur', $case->kategori_umur ?? '') == 'remaja' ? 'selected' : '' }}>Remaja (12-17)</option>
                <option value="dewasa" {{ old('kategori_umur', $case->kategori_umur ?? '') == 'dewasa' ? 'selected' : '' }}>Dewasa (18-59)</option>
                <option value="lansia" {{ old('kategori_umur', $case->kategori_umur ?? '') == 'lansia' ? 'selected' : '' }}>Lansia (≥60)</option>
            </select>
            <small class="form-text text-muted">Otomatis dari tgl lahir</small>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Jenis Kelamin <span class="text-danger">*</span></label>
            <select name="jenis_kelamin" class="form-control" required>
                <option value="">Pilih</option>
                <option value="L" {{ old('jenis_kelamin', $case->jenis_kelamin ?? '') == 'L' ? 'selected' : '' }}>Laki-laki</option>
                <option value="P" {{ old('jenis_kelamin', $case->jenis_kelamin ?? '') == 'P' ? 'selected' : '' }}>Perempuan</option>
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>No. Telepon</label>
            <input type="text" name="no_telepon" class="form-control"
                   value="{{ old('no_telepon', $case->no_telepon ?? '') }}">
        </div>
    </div>
</div>

{{-- Google Form additions --}} 
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Tempat Kerja / Sekolah / PAUD / TPA</label>
            <select name="tempat_kerja_sekolah" id="tempat_kerja_sekolah" class="form-control" style="width:100%">
                @if(old('tempat_kerja_sekolah', $case->tempat_kerja_sekolah ?? ''))
                    <option value="{{ old('tempat_kerja_sekolah', $case->tempat_kerja_sekolah ?? '') }}" selected>
                        {{ old('tempat_kerja_sekolah', $case->tempat_kerja_sekolah ?? '') }}
                    </option>
                @else
                    <option value="">-- Cari atau pilih lokasi --</option>
                @endif
            </select>
            <small class="form-text text-muted">Ketik untuk mencari lokasi</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Pekerjaan</label>
            <input type="text" name="pekerjaan" class="form-control"
                   value="{{ old('pekerjaan', $case->pekerjaan ?? '') }}"
                   placeholder="Pelajar, karyawan, dll">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Nama Orang Tua</label>
            <input type="text" name="nama_orang_tua" class="form-control"
                   value="{{ old('nama_orang_tua', $case->nama_orang_tua ?? '') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>No HP Orang Tua</label>
            <input type="text" name="no_hp_orang_tua" class="form-control"
                   value="{{ old('no_hp_orang_tua', $case->no_hp_orang_tua ?? '') }}" maxlength="20">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <label>Alamat Domisili <span class="text-danger">*</span></label>
            <textarea name="alamat_lengkap" class="form-control" rows="2" required>{{ old('alamat_lengkap', $case->alamat_lengkap ?? '') }}</textarea>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>Provinsi</label>
            <input type="text" name="provinsi" class="form-control"
                   value="{{ old('provinsi', $case->provinsi ?? 'Kalimantan Timur') }}">
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Kab/Kota</label>
            <input type="text" name="kab_kota" class="form-control"
                   value="{{ old('kab_kota', $case->kab_kota ?? 'Kota Bontang') }}">
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Kecamatan <span class="text-danger">*</span></label>
            <select id="kec" name="id_kec" class="form-control" required>
                <option value="">Kecamatan</option>
                @foreach ($kecamatanList as $kec)
                    <option value="{{ $kec->id }}" {{ old('id_kec', $case->id_kec ?? '') == $kec->id ? 'selected' : '' }}>
                        {{ $kec->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Kelurahan <span class="text-danger">*</span></label>
            <select id="kel" name="id_kel" class="form-control" required>
                <option value="">Kelurahan</option>
                @if(isset($kelurahanList))
                    @foreach ($kelurahanList as $kel)
                        <option value="{{ $kel->id }}" {{ old('id_kel', $case->id_kel ?? '') == $kel->id ? 'selected' : '' }}>
                            {{ $kel->name }}
                        </option>
                    @endforeach
                @endif
            </select>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>RT <span class="text-danger">*</span></label>
            <select id="rt" name="id_rt" class="form-control" required>
                <option value="">RT</option>
                @if(isset($rtList))
                    @foreach ($rtList as $rt)
                        <option value="{{ $rt->id }}" {{ old('id_rt', $case->id_rt ?? '') == $rt->id ? 'selected' : '' }}>
                            {{ $rt->name }}
                        </option>
                    @endforeach
                @endif
            </select>
        </div>
    </div>
</div>

@include('admin.epidemiologi.components.form-map-picker')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Select2 for tempat_kerja_sekolah (lokasi penularan master)
    $('#tempat_kerja_sekolah').select2({
        ajax: {
            url: '{{ route("admin.epidemiologi.getLokasiPenularan") }}',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) { return data; },
            cache: true
        },
        placeholder: '-- Cari atau pilih lokasi --',
        allowClear: true,
        minimumInputLength: 0,
        tags: true,
        width: '100%'
    });

    // Cascading select: Kecamatan -> Kelurahan
    $('#kec').on('change', function() {
        var id_kec = $(this).val();
        $('#kel').empty().append('<option value="">== Pilih Kelurahan ==</option>');
        $('#rt').empty().append('<option value="">== Pilih RT ==</option>');

        if (id_kec) {
            $.ajax({
                url: '{{ url("admin/epidemiologi/get-kelurahan") }}/' + id_kec,
                type: "GET",
                dataType: "json",
                success: function(data) {
                    $.each(data, function(key, value) {
                        $('#kel').append('<option value="' + key + '">' + value + '</option>');
                    });
                }
            });
        }
    });

    // Pemetaan kelurahan → wilker puskesmas (uppercase nama kelurahan)
    var WILKER_MAP = {
        'API-API':              'Bontang Utara 1',
        'BONTANG BARU':         'Bontang Utara 1',
        'GUNUNG ELAI':          'Bontang Utara 1',
        'BONTANG KUALA':        'Bontang Utara 1',
        'GUNTUNG':              'Bontang Utara 2',
        'LOK TUAN':             'Bontang Utara 2',
        'BELIMBING':            'Bontang Barat',
        'KANAAN':               'Bontang Barat',
        'GUNUNG TELIHAN':       'Bontang Barat',
        'BONTANG LESTARI':      'Bontang Lestari',
        'TANJUNG LAUT':         'Bontang Selatan 1',
        'TANJUNG LAUT INDAH':   'Bontang Selatan 1',
        'SATIMPO':              'Bontang Selatan 1',
        'BERBAS PANTAI':        'Bontang Selatan 2',
        'BEREBAS TENGAH':       'Bontang Selatan 2',
    };

    function updateWilker() {
        var kelText = $('#kel option:selected').text().trim().toUpperCase();
        var wilker = WILKER_MAP[kelText] || '';
        $('#wilker_puskesmas').val(wilker);
    }

    // Cascading select: Kelurahan -> RT + autofill wilker
    $('#kel').on('change', function() {
        var id_kel = $(this).val();
        $('#rt').empty().append('<option value="">== Pilih RT ==</option>');

        if (id_kel) {
            $.ajax({
                url: '{{ url("admin/epidemiologi/get-rt") }}/' + id_kel,
                type: "GET",
                dataType: "json",
                success: function(data) {
                    $.each(data, function(key, value) {
                        $('#rt').append('<option value="' + key + '">' + value + '</option>');
                    });
                }
            });
        }

        updateWilker();
    });

    // Trigger wilker update saat halaman edit load (kelurahan sudah terpilih)
    if ($('#kel').val()) {
        updateWilker();
    }

    // Auto-calculate age and category from birth date
    $('#tanggal_lahir').on('change', function() {
        var birthDate = new Date($(this).val());
        var today = new Date();
        var ageYears = today.getFullYear() - birthDate.getFullYear();
        var monthDiff = today.getMonth() - birthDate.getMonth();

        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            ageYears--;
        }

        $('#umur_display').val(ageYears + ' tahun');

        var category = '';
        if (ageYears < 1) category = 'bayi';
        else if (ageYears >= 1 && ageYears < 5) category = 'balita';
        else if (ageYears >= 5 && ageYears < 12) category = 'anak';
        else if (ageYears >= 12 && ageYears < 18) category = 'remaja';
        else if (ageYears >= 18 && ageYears < 60) category = 'dewasa';
        else category = 'lansia';

        $('#kategori_umur').val(category);
    });

    if ($('#tanggal_lahir').val()) {
        $('#tanggal_lahir').trigger('change');
    }

    // Set kecamatan → kelurahan → RT secara berantai (endpoint AJAX yang sama
    // dipakai cascading select), lalu segarkan wilker. Nilai di-set langsung
    // tanpa memicu event 'change' #kec agar tidak saling menghapus pilihan.
    function setGeoBiodata(id_kec, id_kel, id_rt) {
        $('#kel').empty().append('<option value="">== Pilih Kelurahan ==</option>');
        $('#rt').empty().append('<option value="">== Pilih RT ==</option>');

        if (!id_kec) { $('#kec').val(''); updateWilker(); return; }
        $('#kec').val(String(id_kec));

        $.ajax({
            url: '{{ url("admin/epidemiologi/get-kelurahan") }}/' + id_kec,
            type: 'GET', dataType: 'json',
            success: function(data) {
                $.each(data, function(key, value) {
                    $('#kel').append('<option value="' + key + '">' + value + '</option>');
                });
                if (!id_kel) { updateWilker(); return; }
                $('#kel').val(String(id_kel));

                $.ajax({
                    url: '{{ url("admin/epidemiologi/get-rt") }}/' + id_kel,
                    type: 'GET', dataType: 'json',
                    success: function(rtData) {
                        $.each(rtData, function(key, value) {
                            $('#rt').append('<option value="' + key + '">' + value + '</option>');
                        });
                        if (id_rt) { $('#rt').val(String(id_rt)); }
                        updateWilker();
                    }
                });
            }
        });
    }

    // Autofill biodata dari NIK. Menimpa semua field biodata dengan data sumber
    // (kasus surveilans terbaru → tabel anak). Diam-diam, tanpa notifikasi.
    var nikTimeout;
    $('#nik').on('input', function() {
        var nik = $(this).val();
        clearTimeout(nikTimeout);
        if (nik.length !== 16) return;

        nikTimeout = setTimeout(function() {
            $.ajax({
                url: '{{ url("admin/epidemiologi/lookup-nik") }}/' + nik,
                type: 'GET', dataType: 'json',
                success: function(res) {
                    if (!res || !res.found || !res.data) return;
                    var d = res.data;

                    $('input[name="nama_lengkap"]').val(d.nama_lengkap || '');
                    $('select[name="jenis_kelamin"]').val(d.jenis_kelamin || '');
                    $('input[name="no_telepon"]').val(d.no_telepon || '');
                    $('textarea[name="alamat_lengkap"]').val(d.alamat_lengkap || '');
                    $('input[name="nama_orang_tua"]').val(d.nama_orang_tua || '');
                    $('input[name="no_hp_orang_tua"]').val(d.no_hp_orang_tua || '');

                    // Tanggal lahir → picu change agar umur & kategori umur dihitung ulang.
                    if (d.tanggal_lahir) {
                        $('#tanggal_lahir').val(d.tanggal_lahir).trigger('change');
                    }

                    setGeoBiodata(d.id_kec, d.id_kel, d.id_rt);
                }
            });
        }, 400);
    });
});
</script>
@endpush
