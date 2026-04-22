<div class="modal fade bs-example-modal-lg" id="createUserModal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{route('super.admin.storeUser')}}">
                <div class="modal-header">
                    <h4 class="modal-title" id="myLargeModalLabel">Tambah User</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                </div>
                <div class="modal-body">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama</label>
                                <input name="name" class="form-control" type="text" placeholder="Nama Lengkap">
                                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input name="email" class="form-control" type="email" placeholder="email@contoh.com">
                                @error('email') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Role --}}
                    <div class="form-group">
                        <label>Role</label>
                        <select class="form-control" name="role" id="create_role">
                            <option value="">== Pilih Role ==</option>
                            <option value="superadmin">Super Admin (Dinkes – akses penuh)</option>
                            <option value="imunisasi_faskes">Faskes Imunisasi (Puskesmas / RS)</option>
                            <option value="surveilans_puskesmas">Surveilans PD3I – Puskesmas</option>
                            <option value="surveilans_rs">Surveilans PD3I – Rumah Sakit</option>
                        </select>
                        @error('role') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    {{-- Faskes Type (hanya untuk imunisasi_faskes) --}}
                    <div class="form-group d-none" id="create_faskes_type_group">
                        <label>Tipe Faskes</label>
                        <select class="form-control" name="faskes_type" id="create_faskes_type">
                            <option value="">== Pilih Tipe Faskes ==</option>
                            <option value="puskesmas">Puskesmas</option>
                            <option value="rs">Rumah Sakit</option>
                        </select>
                        @error('faskes_type') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    {{-- Puskesmas (surveilans_puskesmas atau imunisasi_faskes+puskesmas) --}}
                    <div class="form-group d-none" id="create_puskesmas_group">
                        <label>Puskesmas</label>
                        <select class="form-control" name="id_puskesmas">
                            <option value="">== Pilih Puskesmas ==</option>
                            @foreach($puskesmas as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        @error('id_puskesmas') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    {{-- Rumah Sakit (surveilans_rs atau imunisasi_faskes+rs) --}}
                    <div class="form-group d-none" id="create_rs_group">
                        <label>Rumah Sakit</label>
                        <select class="form-control" name="id_rs">
                            <option value="">== Pilih Rumah Sakit ==</option>
                            @foreach($rs as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                        @error('id_rs') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    <hr>
                    <small class="text-muted">Lokasi alamat (opsional)</small>

                    <div class="row mt-2">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Kecamatan</label>
                                <select id="create_kec" name="id_kec" class="form-control">
                                    <option value="">== Pilih Kecamatan ==</option>
                                    @foreach ($kec as $data)
                                    <option value="{{ $data->id }}">{{ $data->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Kelurahan</label>
                                <select id="create_kel" name="id_kel" class="form-control">
                                    <option value="">== Pilih Kelurahan ==</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    function updateCreateFaskesVisibility() {
        const role = document.getElementById('create_role').value;
        const faskesType = document.getElementById('create_faskes_type').value;

        document.getElementById('create_faskes_type_group').classList.add('d-none');
        document.getElementById('create_puskesmas_group').classList.add('d-none');
        document.getElementById('create_rs_group').classList.add('d-none');

        if (role === 'surveilans_puskesmas') {
            document.getElementById('create_puskesmas_group').classList.remove('d-none');
        } else if (role === 'surveilans_rs') {
            document.getElementById('create_rs_group').classList.remove('d-none');
        } else if (role === 'imunisasi_faskes') {
            document.getElementById('create_faskes_type_group').classList.remove('d-none');
            if (faskesType === 'puskesmas') {
                document.getElementById('create_puskesmas_group').classList.remove('d-none');
            } else if (faskesType === 'rs') {
                document.getElementById('create_rs_group').classList.remove('d-none');
            }
        }
    }

    document.getElementById('create_role').addEventListener('change', updateCreateFaskesVisibility);
    document.getElementById('create_faskes_type').addEventListener('change', updateCreateFaskesVisibility);

    $('#create_kec').on('change', function () {
        var id = $(this).val();
        $.ajax({
            url: '{{ url("admin/get-kel-dasar-anak") }}' + '/' + id,
            success: function (response) {
                $('#create_kel').empty().append('<option value="">== Pilih Kelurahan ==</option>');
                $.each(response, function (id, name) {
                    $('#create_kel').append(new Option(name, id));
                });
            }
        });
    });
})();
</script>
