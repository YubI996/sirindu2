<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIRINDU — Sistem Informasi Anak Rindu</title>
    <link rel="icon" href="{{ asset('logo/icon-sirindu.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block">
    <link rel="stylesheet" href="{{ asset('admin/vendors/fonts/barlow/barlow.css') }}">
    <style>
    /* Reset ringan + shell publik (bukan layout admin) */
    *{ box-sizing:border-box; }
    body{ margin:0; background:oklch(0.985 0.004 145); }
    .material-symbols-outlined{ font-family:'Material Symbols Outlined'; font-weight:normal; font-style:normal;
        line-height:1; letter-spacing:normal; text-transform:none; display:inline-block; white-space:nowrap;
        word-wrap:normal; direction:ltr; vertical-align:middle; }
    .lp-shell{ max-width:1200px; margin:0 auto; padding:0 clamp(16px,4vw,40px); }

    /* Top bar */
    .lp-topbar{ display:flex; align-items:center; justify-content:space-between; padding:18px 0; gap:16px; }
    .lp-brand{ display:flex; align-items:center; gap:10px; }
    .lp-brand .lp-logo{ height:30px; width:auto; display:block; }
    .lp-wordmark{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700;
        font-size:1.45rem; letter-spacing:.01em; line-height:1; color:oklch(0.30 0.05 145); }
    .lp-btn-masuk{ display:inline-flex; align-items:center; gap:8px; height:42px; padding:0 20px;
        border-radius:10px; background:oklch(0.48 0.14 145); color:#fff; text-decoration:none;
        font-family:'Barlow',sans-serif; font-weight:700; font-size:.9rem;
        transition:background .16s, transform .16s; }
    .lp-btn-masuk:hover{ background:oklch(0.40 0.13 145); transform:translateY(-1px); }
    .lp-btn-masuk .material-symbols-outlined{ font-size:18px; }

    /* Hero — dua kolom: teks kiri, ilustrasi kanan */
    .lp-hero{ display:grid; grid-template-columns:1.05fr .95fr; gap:clamp(24px,5vw,64px);
        align-items:center; padding:clamp(20px,4vw,56px) 0 clamp(28px,4vw,48px); }
    .lp-hero__text{ max-width:47ch; }
    .lp-hero h1{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700;
        font-size:clamp(2.4rem,5vw,4rem); line-height:.98; letter-spacing:-.01em;
        margin:0 0 18px; color:oklch(0.24 0.03 145); }
    .lp-hero h1 em{ font-style:normal; color:oklch(0.48 0.14 145); }
    .lp-hero p{ font-family:'Barlow',sans-serif; font-size:clamp(1rem,1.6vw,1.15rem);
        line-height:1.55; color:oklch(0.42 0.012 145); margin:0 0 18px; }
    .lp-tags{ display:flex; flex-wrap:wrap; gap:8px; margin:0 0 24px; }
    .lp-tag{ display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:999px;
        background:#fff; border:1px solid oklch(0.88 0.03 145); font-family:'Barlow',sans-serif;
        font-size:.8rem; font-weight:600; color:oklch(0.34 0.05 145); }
    .lp-tag .material-symbols-outlined{ font-size:16px; color:oklch(0.50 0.13 145); }
    .lp-hero-cta{ display:flex; align-items:center; gap:18px; flex-wrap:wrap; }
    .lp-hero-cta .lp-btn-masuk{ height:48px; padding:0 26px; font-size:.98rem; }
    .lp-scrolllink{ font-family:'Barlow',sans-serif; font-weight:600; font-size:.9rem;
        color:oklch(0.46 0.06 145); text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
    .lp-scrolllink:hover{ color:oklch(0.40 0.13 145); }

    /* Ilustrasi hero — blob hijau organik + mark ibu-anak (motif logo), tanpa aset eksternal */
    .lp-hero__art{ display:flex; justify-content:center; }
    .lp-art{ position:relative; width:100%; max-width:440px; aspect-ratio:1/1;
        border-radius:32px; background:oklch(0.965 0.03 145);
        display:grid; place-items:center; overflow:hidden;
        box-shadow:0 24px 60px oklch(0.45 0.08 145 / .16); }
    .lp-art__blob{ position:absolute; width:74%; height:74%; background:oklch(0.60 0.15 145);
        border-radius:46% 54% 52% 48% / 50% 46% 54% 50%; transform:rotate(-8deg);
        animation:lp-drift 15s ease-in-out infinite alternate; }
    .lp-art__blob2{ position:absolute; width:64%; height:64%; left:13%; top:17%;
        background:oklch(0.84 0.09 145); border-radius:52% 48% 46% 54% / 48% 56% 44% 52%; opacity:.7;
        animation:lp-drift2 12s ease-in-out infinite alternate; }
    .lp-art__mark{ position:relative; width:38%; height:auto;
        filter:brightness(0) invert(1) drop-shadow(0 6px 14px oklch(0.30 0.10 145 / .35));
        animation:lp-bob 4.5s ease-in-out infinite alternate; }
    .lp-art__dot{ position:absolute; border-radius:50%; }
    .lp-art__dot--1{ width:18px; height:18px; background:oklch(0.72 0.13 145); top:15%; right:17%;
        animation:lp-float 5s ease-in-out infinite alternate; }
    .lp-art__dot--2{ width:10px; height:10px; background:#fff; bottom:22%; left:19%; opacity:.85;
        animation:lp-float 6.5s ease-in-out infinite alternate .4s; }
    .lp-art__dot--3{ width:26px; height:26px; border:3px solid oklch(0.72 0.13 145 / .7); bottom:15%; right:21%;
        animation:lp-pulse 3.6s ease-in-out infinite alternate; }

    @keyframes lp-drift{ from{ transform:rotate(-11deg) scale(1); } to{ transform:rotate(-3deg) scale(1.04); } }
    @keyframes lp-drift2{ from{ transform:translate(0,0) rotate(4deg); } to{ transform:translate(-3%,2%) rotate(-4deg); } }
    @keyframes lp-bob{ from{ transform:translateY(-6px); } to{ transform:translateY(6px); } }
    @keyframes lp-float{ from{ transform:translateY(0); } to{ transform:translateY(-12px); } }
    @keyframes lp-pulse{ from{ transform:scale(1); opacity:.7; } to{ transform:scale(1.25); opacity:.25; } }

    @media(max-width:860px){
        .lp-hero{ grid-template-columns:1fr; }
        .lp-art{ max-width:320px; }
    }

    /* Pita data + footer */
    .lp-data-intro{ font-family:'Barlow',sans-serif; font-size:.9rem; color:oklch(0.50 0.01 145);
        margin:0 0 20px; max-width:60ch; }
    .lp-footer{ border-top:1px solid oklch(0.90 0.02 145); margin-top:48px; padding:28px 0 40px;
        font-family:'Barlow',sans-serif; color:oklch(0.50 0.01 145); font-size:.85rem;
        display:flex; flex-wrap:wrap; gap:12px 28px; align-items:center; justify-content:space-between; }
    .lp-footer a{ color:oklch(0.44 0.13 145); text-decoration:none; font-weight:700; }

    /* Entrance halus sekali saat load */
    @keyframes lp-rise{ from{ opacity:0; transform:translateY(14px); } to{ opacity:1; transform:none; } }
    @keyframes lp-pop{ from{ opacity:0; transform:scale(.94); } to{ opacity:1; transform:none; } }
    .lp-hero__text>*{ animation:lp-rise .5s cubic-bezier(.22,1,.36,1) both; }
    .lp-hero__text>*:nth-child(2){ animation-delay:.06s; }
    .lp-hero__text>*:nth-child(3){ animation-delay:.12s; }
    .lp-hero__text>*:nth-child(4){ animation-delay:.18s; }
    .lp-art{ animation:lp-pop .6s cubic-bezier(.22,1,.36,1) .1s both; }
    @media(prefers-reduced-motion:reduce){
        .lp-hero__text>*, .lp-art, .lp-art__blob, .lp-art__blob2, .lp-art__mark, .lp-art__dot{ animation:none !important; }
    }
    </style>

    <style>
    /* ================================================================
       Dashboard Gizi & Timbang — scoped to .tb-page (reuse view admin)
       ================================================================ */
    @import url('https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700&display=swap');

    .tb-page{
        --green:oklch(0.60 0.15 145); --green-d:oklch(0.48 0.14 145); --green-dk:oklch(0.38 0.13 145);
        --ink:oklch(0.24 0.02 145); --muted:oklch(0.50 0.015 145); --faint:oklch(0.62 0.012 145);
        --line:oklch(0.90 0.012 145); --line-soft:oklch(0.94 0.010 145);
        --bg:oklch(0.98 0.012 145); --card:#fff; --thead:oklch(0.96 0.016 145);
        --shadow:0 1px 3px oklch(0.30 0.03 145 / .06);
        --shadow-lg:0 10px 30px oklch(0.42 0.06 145 / .14);
        --danger:oklch(0.52 0.19 25); --danger-bg:oklch(0.95 0.045 25); --danger-ln:oklch(0.88 0.06 25);
        --warn:oklch(0.52 0.13 62);   --warn-bg:oklch(0.95 0.055 72);  --warn-ln:oklch(0.88 0.07 72);
        --info:oklch(0.52 0.10 235);  --info-bg:oklch(0.95 0.04 235);
        font-family:'Barlow',system-ui,sans-serif; color:var(--ink);
    }
    .tb-page *{ box-sizing:border-box; }
    .tb-num{ font-family:'Barlow Condensed','Barlow',sans-serif; font-variant-numeric:tabular-nums; letter-spacing:.005em; }

    /* Filter bar */
    .tb-filter {
        display:flex; align-items:center; gap:.65rem; flex-wrap:wrap;
        background:var(--card); border-radius:14px; padding:.9rem 1.15rem;
        border:1px solid var(--line);
        box-shadow:var(--shadow); margin-bottom:1.5rem;
    }
    .tb-filter label { font-size:.7rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--muted); }
    .tb-filter select {
        height:36px; padding:0 .7rem; border-radius:9px; font-size:.85rem;
        border:1px solid oklch(0.84 0.012 145); background:var(--bg);
        font-family:inherit; color:var(--ink); min-width:150px;
        transition:border-color .14s, box-shadow .14s;
    }
    .tb-filter select:focus { outline:none; border-color:var(--green); box-shadow:0 0 0 3px oklch(0.60 0.15 145 / .16); }
    .tb-filter-btn {
        display:inline-flex; align-items:center; gap:.4rem;
        padding:0 1rem; height:36px; border-radius:9px;
        background:var(--green-d); color:#fff;
        font-family:inherit; font-size:.83rem; font-weight:700;
        border:1px solid transparent; cursor:pointer; text-decoration:none;
        transition:background .14s, border-color .14s, color .14s;
    }
    .tb-filter-btn:hover { background:var(--green-dk); color:#fff; }
    .tb-filter-btn .material-symbols-outlined { font-size:16px; }
    .tb-filter-btn--ghost { background:transparent; border-color:var(--line); color:var(--muted); }
    .tb-filter-btn--ghost:hover { background:transparent; border-color:var(--faint); color:var(--ink); }

    /* Section label */
    .tb-section {
        font-size:.7rem; font-weight:800; letter-spacing:.11em;
        text-transform:uppercase; color:var(--green-d);
        margin:0 0 .9rem; display:flex; align-items:center; gap:.5rem;
    }
    .tb-section .material-symbols-outlined { font-size:16px; }
    .tb-section::after { content:''; flex:1; height:1px; background:linear-gradient(90deg,var(--line),transparent); }
    .tb-section small { font-weight:600; text-transform:none; letter-spacing:0; color:var(--faint); }

    /* KPI cards */
    .tb-kpi-grid {
        display:grid; grid-template-columns:repeat(4,1fr); gap:.9rem;
        margin-bottom:1.75rem;
    }
    .tb-kpi-grid--6 { grid-template-columns:repeat(3,1fr); }
    @media(max-width:900px){ .tb-kpi-grid,.tb-kpi-grid--6{ grid-template-columns:repeat(2,1fr); } }
    @media(max-width:480px){ .tb-kpi-grid,.tb-kpi-grid--6{ grid-template-columns:1fr; } }

    .tb-kpi {
        position:relative; background:var(--card); border-radius:14px; padding:1.1rem 1.15rem;
        border:1px solid var(--line);
        box-shadow:var(--shadow);
        display:flex; align-items:center; gap:.9rem;
    }
    .tb-kpi__icon {
        width:44px; height:44px; border-radius:12px; flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
    }
    .tb-kpi__icon .material-symbols-outlined { font-size:22px; }
    .tb-kpi__icon--green  { background:oklch(0.95 0.05 145); color:var(--green-d); }
    .tb-kpi__icon--amber  { background:var(--warn-bg); color:var(--warn); }
    .tb-kpi__icon--red    { background:var(--danger-bg); color:var(--danger); }
    .tb-kpi__icon--blue   { background:var(--info-bg); color:var(--info); }
    .tb-kpi__icon--orange { background:oklch(0.94 0.065 55); color:oklch(0.52 0.15 52); }
    .tb-kpi__val { font-size:2rem; font-weight:700; color:var(--ink); line-height:1.02; }
    .tb-kpi__pct { font-size:1.05rem; font-weight:600; color:var(--muted); margin-left:.35rem; }
    .tb-kpi__lbl { font-size:.82rem; font-weight:600; color:var(--muted); margin-top:.15rem; }
    .tb-kpi__sub { font-size:.7rem; color:var(--faint); margin-top:.05rem; }

    /* Entrance */
    @keyframes tb-rise { from{ opacity:0; transform:translateY(10px); } to{ opacity:1; transform:none; } }
    .tb-kpi-grid--6 .tb-kpi,
    .tb-stunting-row .tb-highlight { opacity:0; animation:tb-rise .5s cubic-bezier(.22,1,.36,1) forwards; }
    .tb-kpi-grid--6 .tb-kpi:nth-child(1){ animation-delay:.02s; }
    .tb-kpi-grid--6 .tb-kpi:nth-child(2){ animation-delay:.06s; }
    .tb-kpi-grid--6 .tb-kpi:nth-child(3){ animation-delay:.10s; }
    .tb-kpi-grid--6 .tb-kpi:nth-child(4){ animation-delay:.14s; }
    .tb-kpi-grid--6 .tb-kpi:nth-child(5){ animation-delay:.18s; }
    .tb-kpi-grid--6 .tb-kpi:nth-child(6){ animation-delay:.22s; }
    .tb-stunting-row .tb-highlight:nth-child(1){ animation-delay:.06s; }
    .tb-stunting-row .tb-highlight:nth-child(2){ animation-delay:.12s; }
    .tb-stunting-row .tb-highlight:nth-child(3){ animation-delay:.18s; }

    /* Chart cards */
    .tb-chart-grid { display:grid; gap:1rem; margin-bottom:1.5rem; }
    .tb-chart-grid--3 { grid-template-columns:repeat(3,1fr); }
    .tb-chart-grid--2 { grid-template-columns:repeat(2,1fr); }
    @media(max-width:960px){
        .tb-chart-grid--3,.tb-chart-grid--2 { grid-template-columns:1fr; }
    }

    .tb-card {
        background:var(--card); border-radius:14px; padding:1.25rem 1.35rem;
        border:1px solid var(--line);
        box-shadow:var(--shadow);
    }
    .tb-card__title {
        font-size:.95rem; font-weight:700; color:var(--ink);
        margin:0 0 .15rem; display:flex; align-items:center; gap:.5rem;
    }
    .tb-card__title .material-symbols-outlined { font-size:18px; color:var(--green-d); }
    .tb-card__sub { font-size:.8rem; color:var(--faint); margin:0 0 1rem; }
    .tb-card canvas { max-width:100%; }

    /* Stunting highlight */
    .tb-stunting-row { display:flex; gap:.8rem; margin-bottom:1.25rem; flex-wrap:wrap; }
    .tb-highlight {
        flex:1; min-width:150px; border-radius:12px; padding:1rem 1.1rem;
        display:flex; align-items:center; gap:.75rem; border:1px solid transparent;
    }
    .tb-highlight--danger  { background:var(--danger-bg); border-color:var(--danger-ln); }
    .tb-highlight--warning { background:var(--warn-bg);   border-color:var(--warn-ln); }
    .tb-highlight--green   { background:oklch(0.95 0.05 145); border-color:oklch(0.86 0.06 145); }
    .tb-highlight__pct { font-size:2.1rem; font-weight:700; line-height:.95; }
    .tb-highlight--danger  .tb-highlight__pct { color:var(--danger); }
    .tb-highlight--warning .tb-highlight__pct { color:oklch(0.48 0.13 62); }
    .tb-highlight--green   .tb-highlight__pct { color:var(--green-dk); }
    .tb-highlight__lbl { font-size:.82rem; font-weight:700; line-height:1.25; }
    .tb-highlight--danger  .tb-highlight__lbl { color:oklch(0.42 0.13 25); }
    .tb-highlight--warning .tb-highlight__lbl { color:oklch(0.42 0.10 62); }
    .tb-highlight--green   .tb-highlight__lbl { color:var(--green-dk); }
    .tb-highlight__lbl small { font-weight:500; opacity:.85; }

    /* Coverage table */
    .tb-cov-table { width:100%; border-collapse:collapse; font-size:.83rem; }
    .tb-cov-table thead th {
        background:var(--thead); padding:.6rem .8rem; text-align:left;
        font-size:.66rem; font-weight:800; letter-spacing:.06em;
        text-transform:uppercase; color:var(--muted);
        white-space:nowrap;
    }
    .tb-cov-table tbody td { padding:.65rem .8rem; border-top:1px solid var(--line-soft); vertical-align:middle; font-variant-numeric:tabular-nums; }
    .tb-cov-table tbody tr:hover { background:var(--bg); }
    .tb-bar-wrap { width:96px; background:oklch(0.92 0.03 145); border-radius:5px; height:8px; display:inline-block; vertical-align:middle; overflow:hidden; }
    .tb-bar { height:100%; border-radius:5px; background:var(--green-d); transition:width .5s cubic-bezier(.22,1,.36,1); }
    .tb-bar--amber { background:oklch(0.62 0.12 62); }

    /* ASI bulan chart bar */
    .tb-asi-bar { display:flex; flex-direction:column; gap:.5rem; }
    .tb-asi-row { display:flex; align-items:center; gap:.65rem; font-size:.8rem; }
    .tb-asi-row .tb-asi-lbl { width:66px; color:var(--muted); font-weight:700; flex-shrink:0; }
    .tb-asi-row .tb-asi-track { flex:1; background:oklch(0.92 0.03 145); border-radius:5px; height:12px; overflow:hidden; }
    .tb-asi-row .tb-asi-fill { height:100%; border-radius:5px; background:var(--green-d); transition:width .5s cubic-bezier(.22,1,.36,1); }
    .tb-asi-row .tb-asi-pct { width:42px; text-align:right; font-weight:700; color:var(--ink); font-variant-numeric:tabular-nums; }

    /* Pitting edema */
    .tb-pe-list { display:flex; flex-direction:column; gap:.5rem; }
    .tb-pe-row { display:flex; align-items:center; gap:.6rem; font-size:.82rem; }
    .tb-pe-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
    .tb-pe-row .tb-pe-lbl { flex:1; color:var(--muted); }
    .tb-pe-row .tb-pe-cnt { font-weight:700; color:var(--ink); font-variant-numeric:tabular-nums; }

    /* Loading overlay */
    .tb-loading { text-align:center; padding:2rem; color:var(--faint); }
    .tb-spin { animation:tb-spin .8s linear infinite; display:inline-block; font-size:26px; color:oklch(0.60 0.15 145 / .6); }
    @keyframes tb-spin{ to{ transform:rotate(360deg); } }

    @media(prefers-reduced-motion:reduce){
        .tb-kpi-grid--6 .tb-kpi,
        .tb-stunting-row .tb-highlight { animation:none !important; opacity:1 !important; }
    }
    </style>
</head>
<body>
    <div class="lp-shell">
        <header class="lp-topbar">
            <div class="lp-brand">
                <img class="lp-logo" src="{{ asset('logo/icon-sirindu.png') }}" alt="">
                <span class="lp-wordmark">SIRINDU</span>
            </div>
            <a class="lp-btn-masuk" href="{{ route('login') }}">
                <span class="material-symbols-outlined">login</span>Masuk
            </a>
        </header>

        <section class="lp-hero">
            <div class="lp-hero__text">
                <h1><em>SIRINDU</em>.</h1>
                {{-- <p>Sistem Informasi Anak Rindu menyatukan gizi &amp; Operasi Timbang, imunisasi, pemantauan tumbuh kembang, dan surveilans penyakit balita — satu tempat kerja untuk Posyandu, Puskesmas, dan Dinas Kesehatan.</p> --}}
                <div class="lp-tags">
                    <span class="lp-tag"><span class="material-symbols-outlined">monitor_weight</span>Gizi &amp; Timbang</span>
                    <span class="lp-tag"><span class="material-symbols-outlined">vaccines</span>Imunisasi</span>
                    <span class="lp-tag"><span class="material-symbols-outlined">monitoring</span>Tumbuh Kembang</span>
                    <span class="lp-tag"><span class="material-symbols-outlined">health_and_safety</span>Surveilans PD3I</span>
                </div>
                <div class="lp-hero-cta">
                    <a class="lp-btn-masuk" href="{{ route('login') }}">
                        <span class="material-symbols-outlined">login</span>Masuk sebagai Petugas
                    </a>
                    @if($publikasiAktif)
                    <a class="lp-scrolllink" href="#data">
                        Lihat ringkasan data<span class="material-symbols-outlined" style="font-size:18px;">arrow_downward</span>
                    </a>
                    @endif
                </div>
            </div>
            <div class="lp-hero__art" aria-hidden="true">
                <div class="lp-art">
                    <span class="lp-art__blob"></span>
                    <span class="lp-art__blob2"></span>
                    <img class="lp-art__mark" src="{{ asset('logo/icon-sirindu.png') }}" alt="">
                    <span class="lp-art__dot lp-art__dot--1"></span>
                    <span class="lp-art__dot lp-art__dot--2"></span>
                    <span class="lp-art__dot lp-art__dot--3"></span>
                </div>
            </div>
        </section>

        {{-- Publikasi ringkasan OT dikendalikan Dinkes lewat toggle di dashboard admin.
             Saat mati: blok data hilang dan endpoint agregat publik ikut ditolak (403). --}}
        @if($publikasiAktif)
        <div id="data" class="tb-page">
            <p class="lp-data-intro">Ringkasan agregat hasil Operasi Timbang balita. Angka dapat disaring per tahun dan wilayah. Data rinci per anak hanya untuk petugas.</p>

            {{-- ── Filter bar (publik: tahun / kecamatan / kelurahan) ──── --}}
            <div class="tb-filter">
                <span class="material-symbols-outlined" style="font-size:20px;color:oklch(0.48 0.14 145);">filter_alt</span>
                <label for="f-tahun">Tahun</label>
                <select id="f-tahun">
                    <option value="">Semua Tahun</option>
                    @foreach($tahunList as $th)
                    <option value="{{ $th }}">{{ $th }}</option>
                    @endforeach
                </select>
                <label for="f-kec">Kecamatan</label>
                <select id="f-kec">
                    <option value="">Semua Kecamatan</option>
                    @foreach($kecamatanList as $kc)
                    <option value="{{ $kc->id }}">{{ $kc->name }}</option>
                    @endforeach
                </select>
                <label for="f-kel">Kelurahan</label>
                <select id="f-kel">
                    <option value="">Semua Kelurahan</option>
                    @foreach($kelurahanList as $kel)
                    <option value="{{ $kel->id }}" data-kec="{{ $kel->id_kecamatan }}">{{ $kel->name }}</option>
                    @endforeach
                </select>
                <button class="tb-filter-btn" id="btn-apply">
                    <span class="material-symbols-outlined">search</span>Terapkan
                </button>
                <button class="tb-filter-btn tb-filter-btn--ghost" id="btn-reset">
                    <span class="material-symbols-outlined">restart_alt</span>Reset
                </button>
            </div>

            {{-- ── KPI Ringkasan (publik: tidak bisa diklik) ──────────── --}}
            <p class="tb-section"><span class="material-symbols-outlined">monitoring</span>Ringkasan Operasi Timbang</p>
            <div class="tb-kpi-grid tb-kpi-grid--6" id="kpi-grid">
                <div class="tb-kpi">
                    <div class="tb-kpi__icon tb-kpi__icon--green"><span class="material-symbols-outlined">groups</span></div>
                    <div><div class="tb-kpi__val tb-num" id="kpi-sasaran">—</div><div class="tb-kpi__lbl">Balita Sasaran</div><div class="tb-kpi__sub">total terdaftar (filter)</div></div>
                </div>
                <div class="tb-kpi">
                    <div class="tb-kpi__icon tb-kpi__icon--blue"><span class="material-symbols-outlined">event_available</span></div>
                    <div><div class="tb-kpi__val tb-num" id="kpi-hadir">—</div><div class="tb-kpi__lbl">Hadir (Ditimbang)</div><div class="tb-kpi__sub" id="kpi-coverage">coverage —</div></div>
                </div>
                <div class="tb-kpi">
                    <div class="tb-kpi__icon tb-kpi__icon--red"><span class="material-symbols-outlined">height</span></div>
                    <div><div class="tb-kpi__val tb-num"><span id="kpi-stunting">—</span><span class="tb-kpi__pct tb-num" id="pct-stunting"></span></div><div class="tb-kpi__lbl">Stunting</div><div class="tb-kpi__sub">TB/U &lt; -2SD</div></div>
                </div>
                <div class="tb-kpi">
                    <div class="tb-kpi__icon tb-kpi__icon--amber"><span class="material-symbols-outlined">monitor_weight</span></div>
                    <div><div class="tb-kpi__val tb-num"><span id="kpi-wasting">—</span><span class="tb-kpi__pct tb-num" id="pct-wasting"></span></div><div class="tb-kpi__lbl">Wasting</div><div class="tb-kpi__sub">BB/TB &lt;= -2SD</div></div>
                </div>
                <div class="tb-kpi">
                    <div class="tb-kpi__icon tb-kpi__icon--red"><span class="material-symbols-outlined">emergency</span></div>
                    <div><div class="tb-kpi__val tb-num"><span id="kpi-gizi-buruk">—</span><span class="tb-kpi__pct tb-num" id="pct-gizi-buruk"></span></div><div class="tb-kpi__lbl">Gizi Buruk</div><div class="tb-kpi__sub">BB/TB &lt; -3SD</div></div>
                </div>
                <div class="tb-kpi">
                    <div class="tb-kpi__icon tb-kpi__icon--orange"><span class="material-symbols-outlined">trending_down</span></div>
                    <div><div class="tb-kpi__val tb-num"><span id="kpi-underweight">—</span><span class="tb-kpi__pct tb-num" id="pct-underweight"></span></div><div class="tb-kpi__lbl">Underweight</div><div class="tb-kpi__sub">BB/U &lt; -2SD</div></div>
                </div>
            </div>

            {{-- ── STATUS GIZI ────────────────────────────────────────── --}}
            <p class="tb-section"><span class="material-symbols-outlined" style="font-size:16px;">emergency</span>Status Gizi Balita</p>

            <div class="tb-chart-grid tb-chart-grid--3" style="margin-bottom:28px;">
                <div class="tb-card">
                    <p class="tb-card__title"><span class="material-symbols-outlined">height</span>Status TB/U (Stunting)</p>
                    <p class="tb-card__sub">Distribusi tinggi badan per usia — kunjungan terakhir</p>
                    <canvas id="chart-tbu" height="220" role="img" aria-label="Distribusi status TB/U (stunting)"></canvas>
                </div>
                <div class="tb-card">
                    <p class="tb-card__title"><span class="material-symbols-outlined">monitor_weight</span>Status BB/U (Gizi)</p>
                    <p class="tb-card__sub">Distribusi berat badan per usia — kunjungan terakhir</p>
                    <canvas id="chart-bbu" height="220" role="img" aria-label="Distribusi status BB/U (gizi)"></canvas>
                </div>
                <div class="tb-card">
                    <p class="tb-card__title"><span class="material-symbols-outlined">calculate</span>Status BB/TB</p>
                    <p class="tb-card__sub">Berat badan menurut tinggi badan</p>
                    <canvas id="chart-bbtb" height="220" role="img" aria-label="Distribusi status BB/TB"></canvas>
                </div>
            </div>

            {{-- ── TREN ───────────────────────────────────────────────── --}}
            <p class="tb-section"><span class="material-symbols-outlined" style="font-size:16px;">trending_up</span>Tren Perkembangan</p>
            <div class="tb-chart-grid tb-chart-grid--2" style="margin-bottom:28px;">
                <div class="tb-card">
                    <p class="tb-card__title"><span class="material-symbols-outlined">event</span>Kunjungan Timbang <span id="kunjungan-range">(12 Bulan Terakhir)</span></p>
                    <p class="tb-card__sub">Jumlah kunjungan per bulan</p>
                    <canvas id="chart-kunjungan" height="200" role="img" aria-label="Tren jumlah kunjungan timbang per bulan"></canvas>
                </div>
                <div class="tb-card">
                    <p class="tb-card__title"><span class="material-symbols-outlined">show_chart</span>Rata-rata BB & TB per Usia</p>
                    <p class="tb-card__sub">Bulan usia 0–60 (balita)</p>
                    <canvas id="chart-growth" height="200" role="img" aria-label="Rata-rata berat dan tinggi badan per bulan usia"></canvas>
                </div>
            </div>

            {{-- ── COVERAGE WILAYAH ───────────────────────────────────── --}}
            <p class="tb-section"><span class="material-symbols-outlined" style="font-size:16px;">location_on</span>Ketercapaian per Wilayah</p>
            <div class="tb-chart-grid tb-chart-grid--2" style="margin-bottom:28px;">
                <div class="tb-card">
                    <p class="tb-card__title"><span class="material-symbols-outlined">bar_chart</span>Coverage Timbang per Kelurahan</p>
                    <p class="tb-card__sub">% anak pernah ditimbang dari total terdaftar</p>
                    <canvas id="chart-cov-kel" height="220" role="img" aria-label="Coverage timbang dan vitamin A per kelurahan"></canvas>
                </div>
                <div class="tb-card" style="overflow-x:auto;">
                    <p class="tb-card__title"><span class="material-symbols-outlined">table_chart</span>Detail Coverage per Kelurahan</p>
                    <p class="tb-card__sub">Terurut dari coverage tertinggi</p>
                    <div id="cov-table-wrap"><div class="tb-loading"><span class="material-symbols-outlined tb-spin">sync</span></div></div>
                </div>
            </div>

            {{-- ── PROGRAM TIMBANG ────────────────────────────────────── --}}
            <p class="tb-section"><span class="material-symbols-outlined" style="font-size:16px;">fact_check</span>Indikator Program Operasi Timbang</p>
            <div class="tb-chart-grid tb-chart-grid--2" style="margin-bottom:24px;">
                <div class="tb-card">
                    <p class="tb-card__title"><span class="material-symbols-outlined">baby_changing_station</span>Cakupan ASI Eksklusif (Bulan 0–6)</p>
                    <p class="tb-card__sub">% anak yang mendapat ASI pada masing-masing bulan usia</p>
                    <div class="tb-asi-bar" id="asi-bar">
                        <div class="tb-loading"><span class="material-symbols-outlined tb-spin">sync</span></div>
                    </div>
                </div>
                <div class="tb-card">
                    <p class="tb-card__title"><span class="material-symbols-outlined">medical_information</span>Pitting Edema & Cara Ukur</p>
                    <p class="tb-card__sub">Distribusi tingkat edema dan metode pengukuran</p>
                    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">
                        <div style="flex:1;min-width:140px;">
                            <div style="font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:oklch(0.44 0.010 145);margin-bottom:10px;">Pitting Edema</div>
                            <div class="tb-pe-list" id="pe-list">
                                <div class="tb-loading"><span class="material-symbols-outlined tb-spin">sync</span></div>
                            </div>
                        </div>
                        <div style="flex:1;min-width:140px;">
                            <div style="font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:oklch(0.44 0.010 145);margin-bottom:10px;">Cara Ukur</div>
                            <canvas id="chart-cara" height="130" role="img" aria-label="Distribusi cara ukur"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>{{-- /tb-page --}}
        @endif

        <footer class="lp-footer">
            <span>© {{ date('Y') }} SIRINDU — Dinas Kesehatan.</span>
            <span>Data rinci per anak memerlukan <a href="{{ route('login') }}">login petugas</a>.</span>
        </footer>
    </div>

    @if($publikasiAktif)
    <script src="{{ asset('admin/vendors/scripts/core.js') }}"></script>{{-- jQuery 3.2.1 --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
    (function(){
    'use strict';

    var API_RINGKASAN = '{{ route("public.timbang.ringkasan") }}';
    var API_GIZI      = '{{ route("public.timbang.gizi") }}';
    var API_TREN      = '{{ route("public.timbang.tren") }}';
    var API_COVERAGE  = '{{ route("public.timbang.coverage") }}';
    var API_PROGRAM   = '{{ route("public.timbang.program") }}';

    var charts = {};

    function val(id){ var el = document.getElementById(id); return el ? el.value : ''; }

    function getParams(){
        var p = [];
        if(val('f-tahun')) p.push('tahun='+val('f-tahun'));
        if(val('f-kec'))   p.push('kecamatan='+val('f-kec'));
        if(val('f-kel'))   p.push('kelurahan='+val('f-kel'));
        return p.length ? '?'+p.join('&') : '';
    }

    // Cascade Kecamatan→Kelurahan sepenuhnya di klien (tanpa endpoint admin).
    var KEL_OPTS = Array.prototype.slice.call(document.querySelectorAll('#f-kel option[data-kec]'))
        .map(function(o){ return { id:o.value, kec:o.getAttribute('data-kec'), name:o.textContent }; });

    function refreshKelurahan(){
        var kec = val('f-kec');
        var sel = document.getElementById('f-kel');
        sel.innerHTML = '<option value="">Semua Kelurahan</option>';
        KEL_OPTS.filter(function(o){ return !kec || o.kec === kec; })
            .forEach(function(o){
                var opt = new Option(o.name, o.id);
                opt.setAttribute('data-kec', o.kec);
                sel.appendChild(opt);
            });
    }
    document.getElementById('f-kec').addEventListener('change', refreshKelurahan);
    document.getElementById('btn-apply').addEventListener('click', loadAll);
    document.getElementById('btn-reset').addEventListener('click', function(){
        document.getElementById('f-tahun').value = '';
        document.getElementById('f-kec').value = '';
        refreshKelurahan();
        loadAll();
    });

    // ── Utils ─────────────────────────────────────────────────────
    function pct(v){ return (v !== null && v !== undefined) ? v+'%' : '—%'; }
    function num(v){ return (v !== null && v !== undefined) ? Number(v).toLocaleString('id') : '—'; }

    function fail(label){
        return function(xhr){
            console.error('[Timbang] gagal memuat '+label+':', xhr && xhr.status, xhr && xhr.responseText);
        };
    }
    function showError(id, label){
        var el = document.getElementById(id);
        if(el){
            el.innerHTML = '<div style="text-align:center;padding:20px;color:#dc2626;font-size:.8rem;">'
                +'<span class="material-symbols-outlined" style="vertical-align:middle;font-size:18px;">error</span> '
                +'Gagal memuat '+label+'</div>';
        }
    }
    function kpiFail(){
        ['kpi-sasaran','kpi-hadir','kpi-stunting','kpi-wasting','kpi-gizi-buruk','kpi-underweight'].forEach(function(id){
            var el = document.getElementById(id);
            if(el) el.textContent = '!';
        });
    }

    function destroyChart(id){
        if(charts[id]){ charts[id].destroy(); delete charts[id]; }
    }

    function escHtml(s){
        return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Chart.js palette (rgb/rgba — Chart.js tidak bisa parse oklch) ──
    var GREEN   = 'rgb(0,133,64)';
    var GREEN_A = 'rgba(0,166,81,0.72)';
    var RED     = '#dc2626';
    var AMBER   = '#d97706';
    var BLUE    = '#0891b2';
    var VIOLET  = '#7c3aed';
    var SLATE   = '#64748b';

    // ── RINGKASAN ─────────────────────────────────────────────────
    function loadRingkasan(){
        $.getJSON(API_RINGKASAN+getParams(), function(d){
            document.getElementById('kpi-sasaran').textContent = num(d.total_anak);
            document.getElementById('kpi-hadir').textContent   = num(d.total_ditimbang);
            document.getElementById('kpi-coverage').innerHTML  = 'coverage <strong style="color:oklch(0.38 0.13 145)">'+pct(d.coverage)+'</strong>';
        }).fail(function(xhr){ kpiFail(); fail('ringkasan')(xhr); });
    }

    // ── GIZI ──────────────────────────────────────────────────────
    function loadGizi(){
        $.getJSON(API_GIZI+getParams(), function(d){
            function setPct(id, v){ var el=document.getElementById(id); if(el) el.textContent = (v!==null && v!==undefined) ? '· '+v+'%' : ''; }
            setPct('pct-stunting', d.stunting_pct);
            setPct('pct-wasting',     d.wasting_pct);
            setPct('pct-gizi-buruk',  d.total>0 ? Math.round(d.gizi_buruk/d.total*1000)/10 : null);
            setPct('pct-underweight', d.underweight_pct);

            document.getElementById('kpi-stunting').textContent     = num(d.stunting);
            document.getElementById('kpi-wasting').textContent      = num(d.wasting);
            document.getElementById('kpi-gizi-buruk').textContent   = num(d.gizi_buruk);
            document.getElementById('kpi-underweight').textContent  = num(d.underweight);

            destroyChart('chart-tbu');
            var tbu = d.tb_u;
            charts['chart-tbu'] = new Chart(document.getElementById('chart-tbu'), {
                type:'doughnut',
                data:{
                    labels:['Normal','Pendek','Sangat Pendek','Tinggi'],
                    datasets:[{ data:[tbu.normal,tbu.pendek,tbu.sangat_pendek,tbu.tinggi],
                        backgroundColor:[GREEN_A,AMBER,RED,BLUE], borderWidth:2, borderColor:'#fff' }]
                },
                options:{ plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } }, cutout:'65%' }
            });

            destroyChart('chart-bbu');
            var bbu = d.bb_u;
            charts['chart-bbu'] = new Chart(document.getElementById('chart-bbu'), {
                type:'doughnut',
                data:{
                    labels:['Normal','Kurang','Sangat Kurang','Lebih'],
                    datasets:[{ data:[bbu.normal,bbu.kurang,bbu.sangat_kurang,bbu.lebih],
                        backgroundColor:[GREEN_A,AMBER,RED,BLUE], borderWidth:2, borderColor:'#fff' }]
                },
                options:{ plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } }, cutout:'65%' }
            });

            destroyChart('chart-bbtb');
            var bbtb = d.bb_tb;
            charts['chart-bbtb'] = new Chart(document.getElementById('chart-bbtb'), {
                type:'doughnut',
                data:{
                    labels:['Normal','Kurang','Buruk','Lebih','Obesitas'],
                    datasets:[{ data:[bbtb.normal,bbtb.kurang,bbtb.buruk,bbtb.lebih,bbtb.obesitas],
                        backgroundColor:[GREEN_A,AMBER,RED,BLUE,VIOLET], borderWidth:2, borderColor:'#fff' }]
                },
                options:{ plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } }, cutout:'65%' }
            });
        }).fail(fail('status gizi'));
    }

    // ── TREN ──────────────────────────────────────────────────────
    function loadTren(){
        var rangeEl = document.getElementById('kunjungan-range');
        if(rangeEl){
            var ty = document.getElementById('f-tahun').value;
            rangeEl.textContent = ty ? '(Tahun '+ty+')' : '(12 Bulan Terakhir)';
        }
        $.getJSON(API_TREN+getParams(), function(d){
            destroyChart('chart-kunjungan');
            var kj = d.kunjungan;
            charts['chart-kunjungan'] = new Chart(document.getElementById('chart-kunjungan'), {
                type:'bar',
                data:{
                    labels: kj.map(function(r){ return r.bulan; }),
                    datasets:[{ label:'Kunjungan', data:kj.map(function(r){ return r.total; }),
                        backgroundColor:GREEN_A, borderColor:GREEN, borderWidth:1.5, borderRadius:5, maxBarThickness:56 }]
                },
                options:{ plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true } } }
            });

            destroyChart('chart-growth');
            var gr = d.growth;
            charts['chart-growth'] = new Chart(document.getElementById('chart-growth'), {
                type:'line',
                data:{
                    labels: gr.map(function(r){ return 'Bln '+r.bln; }),
                    datasets:[
                        { label:'Rata-rata BB (kg)', data:gr.map(function(r){ return r.avg_bb; }),
                          borderColor:BLUE, backgroundColor:'transparent', tension:.3, pointRadius:2 },
                        { label:'Rata-rata TB (cm)', data:gr.map(function(r){ return r.avg_tb; }),
                          borderColor:GREEN, backgroundColor:'transparent', tension:.3, pointRadius:2 }
                    ]
                },
                options:{ plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } },
                    scales:{ y:{ beginAtZero:false } } }
            });
        }).fail(fail('tren'));
    }

    // ── COVERAGE ──────────────────────────────────────────────────
    function loadCoverage(){
        $.getJSON(API_COVERAGE+getParams(), function(rows){
            var top = rows.slice(0,15);

            destroyChart('chart-cov-kel');
            charts['chart-cov-kel'] = new Chart(document.getElementById('chart-cov-kel'), {
                type:'bar',
                data:{
                    labels: top.map(function(r){ return r.nama; }),
                    datasets:[
                        { label:'Coverage Timbang (%)', data:top.map(function(r){ return r.coverage_pct; }),
                          backgroundColor:GREEN_A, borderColor:GREEN, borderWidth:1.5, borderRadius:4 },
                        { label:'Coverage Vit A (%)', data:top.map(function(r){ return r.vit_a_pct; }),
                          backgroundColor:'rgba(217,119,6,0.65)', borderColor:AMBER, borderWidth:1.5, borderRadius:4 }
                    ]
                },
                options:{
                    indexAxis:'y',
                    plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } },
                    scales:{ x:{ beginAtZero:true, max:100 } }
                }
            });

            var html = '<table class="tb-cov-table"><thead><tr><th>Kelurahan</th><th>Total</th><th>Ditimbang</th><th>Coverage</th><th>Vit A</th></tr></thead><tbody>';
            rows.forEach(function(r){
                var cw = Math.round(r.coverage_pct);
                var vw = Math.round(r.vit_a_pct);
                html += '<tr>'
                    +'<td>'+escHtml(r.nama)+'</td>'
                    +'<td>'+num(r.total_anak)+'</td>'
                    +'<td>'+num(r.ditimbang)+'</td>'
                    +'<td style="white-space:nowrap;">'
                      +'<div class="tb-bar-wrap"><div class="tb-bar" style="width:'+cw+'%"></div></div>'
                      +' <strong>'+r.coverage_pct+'%</strong>'
                    +'</td>'
                    +'<td style="white-space:nowrap;">'
                      +'<div class="tb-bar-wrap"><div class="tb-bar tb-bar--amber" style="width:'+vw+'%"></div></div>'
                      +' '+r.vit_a_pct+'%'
                    +'</td>'
                    +'</tr>';
            });
            html += '</tbody></table>';
            document.getElementById('cov-table-wrap').innerHTML = rows.length ? html
                : '<div style="text-align:center;padding:24px;color:var(--faint);">Belum ada data</div>';
        }).fail(function(xhr){ showError('cov-table-wrap', 'coverage'); fail('coverage')(xhr); });
    }

    // ── PROGRAM ───────────────────────────────────────────────────
    var PE_LABELS = {0:'Tidak Ada',1:'Ringan (1)',2:'Sedang (2)',3:'Berat (3)'};
    var PE_COLORS = {0:'#94a3b8',1:'#f59e0b',2:'#f97316',3:'#dc2626'};

    function loadProgram(){
        $.getJSON(API_PROGRAM+getParams(), function(d){
            var asiRows = d.asi_per_bulan || [];
            var asiAda = asiRows.some(function(row){ return row.pct !== null; });
            if(!asiAda){
                document.getElementById('asi-bar').innerHTML =
                    '<div style="padding:1.4rem 0;text-align:center;color:var(--faint);font-size:.85rem;">Data ASI eksklusif belum tercatat pada periode ini.</div>';
            } else {
                var asiHtml = '';
                asiRows.forEach(function(row){
                    var p = row.pct !== null ? row.pct : 0;
                    asiHtml += '<div class="tb-asi-row">'
                        +'<div class="tb-asi-lbl">Bulan '+row.bulan+'</div>'
                        +'<div class="tb-asi-track"><div class="tb-asi-fill" style="width:'+p+'%"></div></div>'
                        +'<div class="tb-asi-pct">'+(row.pct !== null ? row.pct+'%' : '—')+'</div>'
                        +'</div>';
                });
                document.getElementById('asi-bar').innerHTML = asiHtml;
            }

            var peHtml = '';
            var peTotal = 0;
            (d.pitting_edema||[]).forEach(function(r){ peTotal += r.total; });
            (d.pitting_edema||[]).forEach(function(r){
                var lbl = PE_LABELS[r.level] || ('Level '+r.level);
                var col = PE_COLORS[r.level] || '#64748b';
                var p = peTotal>0 ? Math.round(r.total/peTotal*100) : 0;
                peHtml += '<div class="tb-pe-row">'
                    +'<div class="tb-pe-dot" style="background:'+col+'"></div>'
                    +'<div class="tb-pe-lbl">'+lbl+' ('+p+'%)</div>'
                    +'<div class="tb-pe-cnt">'+num(r.total)+'</div>'
                    +'</div>';
            });
            document.getElementById('pe-list').innerHTML = peHtml || '<div style="color:var(--faint);font-size:.8rem;">—</div>';

            destroyChart('chart-cara');
            var cu = d.cara_ukur||[];
            if(cu.length){
                charts['chart-cara'] = new Chart(document.getElementById('chart-cara'),{
                    type:'doughnut',
                    data:{
                        labels:cu.map(function(r){ return r.cara||'—'; }),
                        datasets:[{ data:cu.map(function(r){ return r.total; }),
                            backgroundColor:[GREEN_A,BLUE,AMBER,SLATE], borderWidth:2, borderColor:'#fff' }]
                    },
                    options:{ plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } }, cutout:'60%' }
                });
            }
        }).fail(function(xhr){ showError('asi-bar','data ASI'); showError('pe-list','data edema'); fail('program')(xhr); });
    }

    function loadAll(){
        loadRingkasan();
        loadGizi();
        loadTren();
        loadCoverage();
        loadProgram();
    }

    loadAll();

    })();
    </script>
    @endif
</body>
</html>
