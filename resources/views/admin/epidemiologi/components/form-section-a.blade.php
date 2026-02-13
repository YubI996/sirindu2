{{-- Section A: Patient Identity (12 fields) --}}
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>No. Registrasi <span class="text-danger">*</span></label>
            <input type="text" name="no_registrasi" class="form-control"
                   value="{{ old('no_registrasi', $suggestedRegNumber ?? '') }}" required>
            <small class="form-text text-muted">Nomor registrasi unik kasus</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>NIK <span class="text-danger">*</span></label>
            <input type="text" name="nik" id="nik" class="form-control"
                   value="{{ old('nik', $case->nik ?? '') }}"
                   maxlength="16" pattern="[0-9]{16}" required>
            <small class="form-text text-muted">16 digit</small>
            <small id="nikStatus" class="form-text"></small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Nama Lengkap <span class="text-danger">*</span></label>
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
    <div class="col-md-3">
        <div class="form-group">
            <label>Umur</label>
            <input type="text" id="umur_display" class="form-control" readonly>
            <small class="form-text text-muted">Dihitung otomatis</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Kategori Umur <span class="text-danger">*</span></label>
            <select name="kategori_umur" id="kategori_umur" class="form-control" required>
                <option value="">Pilih Kategori</option>
                <option value="bayi" {{ old('kategori_umur', $case->kategori_umur ?? '') == 'bayi' ? 'selected' : '' }}>Bayi (&lt;1 tahun)</option>
                <option value="balita" {{ old('kategori_umur', $case->kategori_umur ?? '') == 'balita' ? 'selected' : '' }}>Balita (1-4 tahun)</option>
                <option value="anak" {{ old('kategori_umur', $case->kategori_umur ?? '') == 'anak' ? 'selected' : '' }}>Anak (5-11 tahun)</option>
                <option value="remaja" {{ old('kategori_umur', $case->kategori_umur ?? '') == 'remaja' ? 'selected' : '' }}>Remaja (12-17 tahun)</option>
                <option value="dewasa" {{ old('kategori_umur', $case->kategori_umur ?? '') == 'dewasa' ? 'selected' : '' }}>Dewasa (18-59 tahun)</option>
                <option value="lansia" {{ old('kategori_umur', $case->kategori_umur ?? '') == 'lansia' ? 'selected' : '' }}>Lansia (≥60 tahun)</option>
            </select>
            <small class="form-text text-muted">Diset otomatis dari tanggal lahir</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Jenis Kelamin <span class="text-danger">*</span></label>
            <select name="jenis_kelamin" class="form-control" required>
                <option value="">Pilih</option>
                <option value="L" {{ old('jenis_kelamin', $case->jenis_kelamin ?? '') == 'L' ? 'selected' : '' }}>Laki-laki</option>
                <option value="P" {{ old('jenis_kelamin', $case->jenis_kelamin ?? '') == 'P' ? 'selected' : '' }}>Perempuan</option>
            </select>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <label>Alamat Lengkap <span class="text-danger">*</span></label>
            <textarea name="alamat_lengkap" class="form-control" rows="2" required>{{ old('alamat_lengkap', $case->alamat_lengkap ?? '') }}</textarea>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>Kecamatan <span class="text-danger">*</span></label>
            <select id="kec" name="id_kec" class="form-control" required>
                <option value="">== Pilih Kecamatan ==</option>
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
                <option value="">== Pilih Kelurahan ==</option>
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
    <div class="col-md-3">
        <div class="form-group">
            <label>RT <span class="text-danger">*</span></label>
            <select id="rt" name="id_rt" class="form-control" required>
                <option value="">== Pilih RT ==</option>
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
    <div class="col-md-3">
        <div class="form-group">
            <label>No. Telepon</label>
            <input type="text" name="no_telepon" class="form-control"
                   value="{{ old('no_telepon', $case->no_telepon ?? '') }}">
            <small class="form-text text-muted">Opsional</small>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Cascading select: Kecamatan -> Kelurahan
    $('#kec').on('change', function() {
        var id_kec = $(this).val();
        $('#kel').empty().append('<option value="">== Pilih Kelurahan ==</option>');
        $('#rt').empty().append('<option value="">== Pilih RT ==</option>');

        if (id_kec) {
            $.ajax({
                url: '/admin/epidemiologi/get-kelurahan/' + id_kec,
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

    // Cascading select: Kelurahan -> RT
    $('#kel').on('change', function() {
        var id_kel = $(this).val();
        $('#rt').empty().append('<option value="">== Pilih RT ==</option>');

        if (id_kel) {
            $.ajax({
                url: '/admin/epidemiologi/get-rt/' + id_kel,
                type: "GET",
                dataType: "json",
                success: function(data) {
                    $.each(data, function(key, value) {
                        $('#rt').append('<option value="' + key + '">' + value + '</option>');
                    });
                }
            });
        }
    });

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

        // Set category
        var category = '';
        if (ageYears < 1) category = 'bayi';
        else if (ageYears >= 1 && ageYears < 5) category = 'balita';
        else if (ageYears >= 5 && ageYears < 12) category = 'anak';
        else if (ageYears >= 12 && ageYears < 18) category = 'remaja';
        else if (ageYears >= 18 && ageYears < 60) category = 'dewasa';
        else category = 'lansia';

        $('#kategori_umur').val(category);
    });

    // Trigger age calculation if date already set
    if ($('#tanggal_lahir').val()) {
        $('#tanggal_lahir').trigger('change');
    }

    // NIK validation (check for duplicates)
    var nikTimeout;
    $('#nik').on('input', function() {
        var nik = $(this).val();

        clearTimeout(nikTimeout);

        if (nik.length === 16) {
            nikTimeout = setTimeout(function() {
                $.ajax({
                    url: '/admin/epidemiologi/check-nik/' + nik,
                    type: 'GET',
                    success: function(response) {
                        if (response.exists) {
                            $('#nikStatus').html('<span class="text-danger">⚠ NIK sudah terdaftar</span>');
                        } else {
                            $('#nikStatus').html('<span class="text-success">✓ NIK tersedia</span>');
                        }
                    }
                });
            }, 500);
        } else {
            $('#nikStatus').html('');
        }
    });
});
</script>
@endpush
