{{-- Tombol "?" penjelasan untuk dasbor (.im-*). Pola disclosure: dibuka dengan klik/tap/Enter/Spasi,
     BUKAN tooltip hover (hover tak ada di layar sentuh & tak terjangkau keyboard — WCAG 1.4.13/2.1.1).
     Taruh DI DALAM .im-h supaya bodi penjelasan jatuh ke baris sendiri di bawah judul.
     Perilaku buka/tutup ada di public/js/dasbor-a11y.js. --}}
@props(['id', 'label'])
<button type="button" class="im-help-btn" aria-expanded="false" aria-controls="{{ $id }}"
        aria-label="Penjelasan: {{ $label }}"><span aria-hidden="true">?</span></button>
<div class="im-help-body" id="{{ $id }}" hidden>{{ $slot }}</div>
