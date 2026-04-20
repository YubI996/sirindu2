@extends('admin::layouts.app')
@section('title', 'Beranda — SIRINDU')
@section('title-content', 'Beranda')
@section('item', 'Super Admin')
@section('item-active', 'Beranda')

@section('content')
<style>
.srd-welcome {
    display: flex;
    align-items: center;
    gap: 32px;
    padding: 8px 0 24px;
}
.srd-welcome-img {
    width: 140px;
    flex-shrink: 0;
    opacity: 0.92;
}
.srd-welcome-greeting {
    font-size: 13px;
    color: oklch(0.54 0.008 145);
    margin: 0 0 4px;
}
.srd-welcome-name {
    font-size: 26px;
    font-weight: 700;
    color: var(--srd-green, oklch(0.48 0.14 145));
    margin: 0 0 8px;
    line-height: 1.2;
}
.srd-welcome-sub {
    font-size: 14px;
    color: oklch(0.44 0.010 145);
    margin: 0;
}
.srd-divider {
    height: 1px;
    background: oklch(0.87 0.012 145);
    margin: 0 0 24px;
}
.srd-quicklinks {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}
.srd-ql {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px;
    background: oklch(0.96 0.018 145);
    color: var(--srd-green, oklch(0.48 0.14 145));
    border: 1px solid oklch(0.88 0.025 145);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: background 0.14s ease-out, box-shadow 0.14s ease-out;
}
.srd-ql:hover {
    background: oklch(0.91 0.045 145);
    box-shadow: 0 2px 8px oklch(0.48 0.14 145 / 0.14);
    color: var(--srd-green, oklch(0.48 0.14 145));
    text-decoration: none;
}
.srd-ql i { font-size: 14px; color: oklch(0.60 0.15 145); }
@media (max-width: 576px) {
    .srd-welcome { flex-direction: column; text-align: center; gap: 16px; }
    .srd-welcome-img { width: 100px; }
}
</style>

<div class="srd-welcome">
    <img class="srd-welcome-img img-fluid" src="{{ asset('admin/vendors/images/banner-img.png') }}" alt="Ilustrasi SIRINDU">
    <div>
        <p class="srd-welcome-greeting" id="js-home-greeting">Selamat datang kembali,</p>
        <h1 class="srd-welcome-name">{{ Auth::user()->name }}</h1>
        <p class="srd-welcome-sub">Sistem Informasi Realtime Reporting Terpadu &mdash; Kota Bontang</p>
    </div>
</div>

<div class="srd-divider"></div>

<nav class="srd-quicklinks" aria-label="Akses cepat">
    <a class="srd-ql" href="{{ route('admin.analytics') }}">
        <i class="fa fa-chart-bar" aria-hidden="true"></i>Dashboard Imunisasi
    </a>
    <a class="srd-ql" href="{{ route('admin.map') }}">
        <i class="fa fa-map-marked-alt" aria-hidden="true"></i>Peta Statistik
    </a>
    <a class="srd-ql" href="{{ route('admin.earlyWarning') }}">
        <i class="fa fa-chart-line" aria-hidden="true"></i>Proyeksi
    </a>
    <a class="srd-ql" href="{{ route('admin.epidemiologi.dashboard') }}">
        <i class="fa fa-clipboard-list" aria-hidden="true"></i>Dashboard PD3I
    </a>
    <a class="srd-ql" href="{{ route('admin.anak') }}">
        <i class="fa fa-child" aria-hidden="true"></i>Data Anak
    </a>
    <a class="srd-ql" href="{{ route('admin.export.imunisasi.index') }}">
        <i class="fa fa-file-export" aria-hidden="true"></i>Export Data
    </a>
    <a class="srd-ql" href="{{ route('super.admin.user') }}">
        <i class="fa fa-user-shield" aria-hidden="true"></i>Pengguna
    </a>
</nav>
@endsection

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
})();
</script>
@endsection
