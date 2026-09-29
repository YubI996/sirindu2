{{--
    Pilihan format penanggalan berkas import.

    05/02/2020 tak bisa dibaca tanpa tahu urutan berkasnya. "Otomatis" menyimpulkannya
    dari isi berkas (ketemu 25/02 → hari di depan) dan menolak jalan kalau memang tak
    bisa dipastikan — lebih baik berhenti daripada menyimpan tanggal salah diam-diam.

    Param: $id — id unik per form.
--}}
<div style="margin:10px 0;">
    <label for="{{ $id }}" style="display:block;font-size:.9rem;font-weight:600;margin-bottom:4px;">
        Format tanggal di berkas
    </label>
    <select name="format_tanggal" id="{{ $id }}" class="form-control form-control-sm" style="font-size:.9rem;">
        @foreach (\App\Support\TanggalBerkas::pilihan() as $nilai => $label)
            <option value="{{ $nilai }}">{{ $label }}</option>
        @endforeach
    </select>
    <small style="display:block;margin-top:4px;font-size:.8rem;color:#6c757d;">
        Biarkan Otomatis bila tanggal berupa sel tanggal Excel atau ditulis 2025-12-31.
        Pilih tegas bila ditulis 05/02/2025 &mdash; urutannya tak bisa ditebak dari nilainya sendiri.
    </small>
</div>
