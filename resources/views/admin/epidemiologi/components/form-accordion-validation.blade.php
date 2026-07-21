{{--
    Menjembatani validasi HTML5 dengan accordion.

    Masalah yang diselesaikan: panel accordion yang tertutup ber-`display:none`, dan
    browser TIDAK BISA mem-fokus kontrol yang tersembunyi. Bila ada field `required`
    kosong di panel tertutup (mis. id_jenis_kasus & tanggal_onset di section C),
    browser membatalkan submit tanpa pesan apa pun — petugas menekan "Simpan" dan
    tidak terjadi apa-apa. Lihat CLAUDE.md.

    Solusi: matikan gelembung bawaan browser, lalu tangani sendiri — buka panel yang
    memuat field bermasalah, gulirkan, fokuskan, baru tampilkan pesannya.
    Validasi server tetap gerbang sebenarnya.
--}}
<script>
(function () {
    var form = document.getElementById('surveillanceForm');
    if (!form) return;

    form.setAttribute('novalidate', 'novalidate');

    // Field milik penyakit lain sedang disembunyikan → bukan urusan petugas saat ini.
    function terjangkau(el) {
        var card = $(el).closest('.disease-card');
        return card.length === 0 || card.is(':visible');
    }

    function invalidPertama() {
        var kandidat = form.querySelectorAll('input:invalid, select:invalid, textarea:invalid');
        for (var i = 0; i < kandidat.length; i++) {
            if (terjangkau(kandidat[i])) return kandidat[i];
        }
        return null;
    }

    // Buka panel yang memuat elemen, lalu jalankan callback SESUDAH panel terbuka
    // (fokus ke elemen yang masih display:none tidak berefek).
    function bukaPanel(el, selesai) {
        var panel = $(el).closest('.collapse');
        if (!panel.length || panel.hasClass('show')) { selesai(); return; }

        var sudah = false;
        var sekali = function () { if (!sudah) { sudah = true; selesai(); } };

        panel.one('shown.bs.collapse', sekali);
        if (typeof panel.collapse === 'function') {
            panel.collapse('show');
        } else {
            panel.addClass('show');
        }
        setTimeout(sekali, 400); // jaring bila plugin collapse tak tersedia
    }

    function sorot(el) {
        bukaPanel(el, function () {
            if (el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.focus();
            if (el.reportValidity) el.reportValidity();
        });
    }

    form.addEventListener('submit', function (e) {
        var el = invalidPertama();
        if (!el) return;
        e.preventDefault();
        sorot(el);
    });

    // Validasi server: pesan error yang mendarat di panel tertutup sama tak terlihatnya.
    // Cukup buka panelnya — scroll ke ringkasan .alert-danger sudah ditangani halaman,
    // dua smooth-scroll bersamaan justru saling rebut.
    $(function () {
        var errEl = form.querySelector('.is-invalid, .invalid-feedback');
        if (errEl) bukaPanel(errEl, function () {});
    });
})();
</script>
