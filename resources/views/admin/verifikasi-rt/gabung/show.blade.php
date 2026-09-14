@extends('admin::layouts.app')

@section('title') Gabungkan Data Anak — SIRINDU @endsection
@section('title-content') Gabungkan Data Anak @endsection
@section('item') Penggabungan @endsection
@section('item-active') Pilih kolom @endsection

@section('content')
@php
    $labelKolom = ['nik' => 'NIK', 'no_kk' => 'No KK', 'nama' => 'Nama', 'nama_ibu' => 'Nama ibu', 'nama_ayah' => 'Nama ayah', 'jk' => 'JK',
        'tempat_lahir' => 'Tempat lahir', 'tgl_lahir' => 'Tgl lahir', 'alamat_ktp' => 'Alamat KTP', 'alamat' => 'Alamat domisili',
        'id_kec' => 'Kecamatan (id)', 'id_kel' => 'Kelurahan (id)', 'id_rt' => 'RT (id)', 'id_posyandu' => 'Posyandu (id)',
        'id_puskesmas' => 'Puskesmas (id)', 'golda' => 'Gol. darah', 'anak' => 'Anak ke-', 'catatan' => 'Catatan'];
    $nilai = fn ($anak, $k) => $anak->$k === null || $anak->$k === '' ? '—' : $anak->$k;
@endphp
<div class="page-header"><div class="row"><div class="col-md-12"><div class="title">
    <h4>Pilih nilai per kolom</h4>
    <p class="text-muted mb-0">
        Baris yang <b>dipertahankan</b>: <b>{{ strtoupper($dipertahankan) }}</b>
        @if($kunci_dipertahankan) — terkunci karena berasal dari <b>Operasi Timbang</b> (baris OT tidak pernah dihapus). @endif
        Seluruh pengukuran, imunisasi, intervensi, dan verifikasi dari baris lain akan dipindahkan ke baris ini.
    </p>
</div></div></div></div>

<form method="POST" action="{{ route('admin.gabung.store', $tautan) }}" class="card-box">
    @csrf
    @if($boleh_pilih_baris)
    <div class="form-group">
        <label class="mr-3">Baris yang dipertahankan:</label>
        <label class="mr-3"><input type="radio" name="dipertahankan" value="a" {{ $dipertahankan === 'a' ? 'checked' : '' }}> A (#{{ $a->id }}, {{ $a->sumber }})</label>
        <label><input type="radio" name="dipertahankan" value="b" {{ $dipertahankan === 'b' ? 'checked' : '' }}> B (#{{ $b->id }}, {{ $b->sumber }})</label>
    </div>
    @endif
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th style="width:160px">Kolom</th><th>A — #{{ $a->id }} <span class="badge badge-light">{{ $a->sumber }}</span></th><th>B — #{{ $b->id }} <span class="badge badge-light">{{ $b->sumber }}</span></th></tr></thead>
            <tbody>
            @foreach($kolom as $k)
                @php $beda = trim((string) $a->$k) !== trim((string) $b->$k); @endphp
                <tr class="{{ $beda ? 'table-warning' : '' }}">
                    <th>{{ $labelKolom[$k] ?? $k }}</th>
                    <td><label class="mb-0 d-block"><input type="radio" name="pilihan[{{ $k }}]" value="a" {{ $default[$k] === 'a' ? 'checked' : '' }}> {{ $nilai($a, $k) }}</label></td>
                    <td><label class="mb-0 d-block"><input type="radio" name="pilihan[{{ $k }}]" value="b" {{ $default[$k] === 'b' ? 'checked' : '' }}> {{ $nilai($b, $k) }}</label></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-muted"><small>Kolom berlatar kuning berisi nilai berbeda. Pilihan awal: identitas dari Capil, domisili dari data non-Capil. <code>sumber</code> baris yang dipertahankan tidak berubah; asal-usul kedua baris disimpan di <code>sumber_gabungan</code>.</small></p>
    <button class="btn btn-primary" onclick="return confirm('Gabungkan kedua baris? Tindakan ini tercatat dan bisa dibatalkan dari riwayat.');">Gabungkan sekarang</button>
    <a href="{{ route('admin.gabung.index') }}" class="btn btn-secondary ml-2">Batal</a>
</form>
@endsection
