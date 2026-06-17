{{-- Quicklink beranda role-aware + kelola pilihan (Paket F).
     Membutuhkan $quicklinks: list of [key,label,icon,url,selected]. --}}
@php $qlVisible = collect($quicklinks ?? [])->where('selected', true); @endphp

<style>
.srd-ql-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 0 0 12px;
}
.srd-ql-head h2 {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--srd-text-2, #6b7280);
    margin: 0;
}
.srd-ql-manage {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: transparent;
    color: var(--srd-green, #1b7a43);
    border: 1px solid var(--srd-border, #d1d5db);
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.14s ease-out;
}
.srd-ql-manage:hover { background: var(--srd-surface-subtle, #f3f4f6); }
.srd-ql-empty {
    padding: 16px;
    border: 1px dashed var(--srd-border, #d1d5db);
    border-radius: 8px;
    color: var(--srd-text-2, #6b7280);
    font-size: 13px;
}
.srd-ql-check { display: flex; align-items: center; gap: 8px; padding: 6px 0; }
.srd-ql-check label { margin: 0; font-size: 14px; cursor: pointer; }
</style>

<div class="srd-ql-head">
    <h2>Akses cepat</h2>
    <button type="button" class="srd-ql-manage" data-toggle="modal" data-target="#modalKelolaQuicklink">
        <i class="fa fa-cog" aria-hidden="true"></i> Kelola
    </button>
</div>

@if($qlVisible->count() > 0)
<nav class="srd-quicklinks" aria-label="Akses cepat">
    @foreach($qlVisible as $ql)
    <a class="srd-ql" href="{{ $ql['url'] }}">
        <i class="fa {{ $ql['icon'] }}" aria-hidden="true"></i>{{ $ql['label'] }}
    </a>
    @endforeach
</nav>
@else
<div class="srd-ql-empty">Belum ada pintasan. Klik <strong>Kelola</strong> untuk menambah.</div>
@endif

{{-- Modal kelola quicklink --}}
<div class="modal fade" id="modalKelolaQuicklink" tabindex="-1" role="dialog" aria-labelledby="modalKelolaQuicklinkLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalKelolaQuicklinkLabel">Kelola Pintasan Beranda</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formKelolaQuicklink">
                <div class="modal-body">
                    <p class="text-muted" style="font-size:13px;">Centang pintasan yang ingin ditampilkan di beranda.</p>
                    @forelse($quicklinks ?? [] as $ql)
                    <div class="srd-ql-check">
                        <input type="checkbox" id="ql_{{ $ql['key'] }}" name="keys[]" value="{{ $ql['key'] }}" {{ $ql['selected'] ? 'checked' : '' }}>
                        <label for="ql_{{ $ql['key'] }}"><i class="fa {{ $ql['icon'] }} mr-1"></i>{{ $ql['label'] }}</label>
                    </div>
                    @empty
                    <p class="text-muted">Tidak ada pintasan yang tersedia untuk akun Anda.</p>
                    @endforelse
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@section('scripts')
@parent
<script>
(function () {
    var hour = new Date().getHours();
    var greet = hour < 11 ? 'Selamat pagi,'
              : hour < 15 ? 'Selamat siang,'
              : hour < 19 ? 'Selamat sore,'
              :              'Selamat malam,';
    var el = document.getElementById('js-home-greeting');
    if (el) el.textContent = greet;

    var form = document.getElementById('formKelolaQuicklink');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var keys = Array.prototype.slice
                .call(form.querySelectorAll('input[name="keys[]"]:checked'))
                .map(function (c) { return c.value; });

            $.ajax({
                url: '{{ route('admin.beranda.quicklinks') }}',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}', keys: keys },
                success: function () {
                    if (window.Swal) {
                        Swal.fire({ icon: 'success', title: 'Tersimpan', timer: 900, showConfirmButton: false })
                            .then(function () { location.reload(); });
                    } else {
                        location.reload();
                    }
                },
                error: function () {
                    if (window.Swal) Swal.fire({ icon: 'error', title: 'Gagal menyimpan' });
                    else alert('Gagal menyimpan pintasan.');
                }
            });
        });
    }
})();
</script>
@endsection
