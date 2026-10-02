// public/js/satuan-bb.js
// Satuan input berat badan di form pengukuran (spec 2026-10-02 §5.5).
// Server yang memutuskan satuan (AturanBeratBadan); berkas ini hanya menyelaraskan label, step,
// dan nilai dengan tanggal kunjungan. Batas datang dari server (data-batas-gram), jadi tidak ada
// aritmetika bulan di JS yang bisa berbeda dari PHP — cukup perbandingan string ISO Y-m-d.
(function () {
    'use strict';

    function hariIni() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function satuanUntuk(tgl, batas) {
        return (tgl || hariIni()) < batas ? 'g' : 'kg';
    }

    function konversi(nilai, ke) {
        var n = Number(nilai);
        if (nilai === '' || !isFinite(n)) return nilai;
        return ke === 'g' ? String(Math.round(n * 1000)) : String(Math.round(n) / 1000);
    }

    function pasang(input) {
        var form = input.form;
        var batas = input.getAttribute('data-batas-gram');
        var tgl = form ? form.querySelector('[name="tgl_kunjungan"]') : null;
        if (!tgl || !batas) return;
        var label = form.querySelector('[data-satuan-label="' + input.id + '"]');
        var info = form.querySelector('[data-satuan-info="' + input.id + '"]');

        function terapkan(umumkan) {
            var baru = satuanUntuk(tgl.value, batas);
            var lama = input.getAttribute('data-satuan') || baru;
            if (baru !== lama && input.value !== '') {
                input.value = konversi(input.value, baru);
                if (umumkan && info) {
                    info.textContent = baru === 'g'
                        ? 'Umur di bawah 2 bulan pada tanggal ini — berat badan diubah ke gram.'
                        : 'Umur 2 bulan ke atas pada tanggal ini — berat badan diubah ke kg.';
                }
            }
            input.setAttribute('data-satuan', baru);
            input.step = baru === 'g' ? '1' : 'any';
            input.placeholder = baru === 'g' ? 'mis. 3250' : 'mis. 7.5';
            if (label) label.textContent = baru === 'g' ? '(gram)' : '(kg)';
        }

        terapkan(false);
        tgl.addEventListener('change', function () { terapkan(true); });
    }

    document.querySelectorAll('input[data-batas-gram]').forEach(pasang);
})();
