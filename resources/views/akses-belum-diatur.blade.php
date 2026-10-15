<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses belum diatur · SIRINDU</title>
    <style>
        body { margin:0; font-family:'Barlow', system-ui, sans-serif; background:#f6f8f6; color:#1f2a1f; }
        main { max-width:32rem; margin:12vh auto; padding:2rem 1.5rem; background:#fff; border:1px solid #d9e2d9; border-radius:8px; }
        h1 { font-size:1.35rem; margin:0 0 1rem; }
        p { line-height:1.55; margin:.4rem 0; }
        .muted { color:#535a53; }
        button { margin-top:1.5rem; padding:.6rem 1.1rem; border:0; border-radius:6px; background:#1d6b3a; color:#fff; font:inherit; cursor:pointer; }
    </style>
</head>
<body>
<main>
    <img src="{{ asset('logo/Sirindu-allblack.png') }}" alt="SIRINDU" height="36">
    <h1>Akses akun Anda belum diatur. Hubungi Dinas Kesehatan.</h1>
    <p><strong>{{ $user->name }}</strong></p>
    @if ($user->nama_sarana)
        <p class="muted">{{ $user->nama_sarana }}</p>
    @endif
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Keluar</button>
    </form>
</main>
</body>
</html>
