<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tidak punya akses — SIRINDU</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;800&display=swap" rel="stylesheet">
<style>
body{ margin:0; font-family:Barlow,system-ui,sans-serif; background:oklch(0.98 0.012 145); color:oklch(0.24 0.02 145); display:flex; min-height:100vh; align-items:center; justify-content:center; padding:24px; }
.box{ max-width:460px; background:#fff; border:1px solid oklch(0.90 0.012 145); border-radius:14px; padding:28px; }
h1{ font-size:1.3rem; margin:0 0 8px; }
p{ margin:0 0 14px; color:oklch(0.50 0.015 145); line-height:1.5; }
a.btn{ display:inline-block; background:oklch(0.48 0.14 145); color:#fff; text-decoration:none; padding:10px 18px; border-radius:8px; font-weight:700; }
code{ background:oklch(0.96 0.016 145); padding:2px 6px; border-radius:4px; }
</style>
</head>
<body>
<div class="box">
    <h1>Halaman ini bukan untuk akun Anda</h1>
    @auth
        <p>Akun <b>{{ auth()->user()->name }}</b> tidak punya akses ke halaman ini.
        @if(auth()->user()->isRt())
            Akun RT hanya bisa membuka halaman <b>Verifikasi Warga</b>.
        @endif
        </p>
        @if(!empty($exception) && $exception->getMessage() && $exception->getMessage() !== 'Unauthorized action.')
            <p><code>{{ $exception->getMessage() }}</code></p>
        @endif
        <a class="btn" href="{{ route(auth()->user()->berandaRoute()) }}">Kembali ke halaman utama</a>
    @else
        <p>Silakan masuk terlebih dahulu.</p>
        <a class="btn" href="{{ route('login') }}">Masuk</a>
    @endauth
</div>
</body>
</html>
