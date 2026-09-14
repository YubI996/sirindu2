{{-- resources/views/rt/verifikasi.blade.php — dilengkapi di Task 6 --}}
<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Verifikasi Warga — {{ $rt->name }}</title></head>
<body>
<h1>{{ $rt->name }}</h1>
<script>var API_WARGA = '{{ route("rt.api.warga", request()->only("rt")) }}';</script>
</body></html>
