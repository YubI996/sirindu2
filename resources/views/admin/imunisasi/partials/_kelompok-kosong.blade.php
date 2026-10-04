{{-- Peringatan master vaksin: grup IDL/IBL kosong membuat capaian tampil 0 tanpa error.
     Butuh $kelompokKosong dari ImunisasiStatusService::getKelompokKosong(). --}}
@if(!empty($kelompokKosong))
<div class="alert alert-warning" role="alert">
    <strong>Grup vaksin belum diisi.</strong>
    Grup {{ implode(' dan ', $kelompokKosong) }} tidak ada atau belum memiliki vaksin di master data,
    sehingga capaian IDL/IBL di halaman ini tampil 0 untuk semua anak — bukan karena belum ada
    yang diimunisasi. Minta pengelola server menjalankan
    <code>php artisan db:seed --class=KelompokVaksinSeeder --force</code>.
</div>
@endif
