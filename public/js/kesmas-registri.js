(function () {
    'use strict';

    const registri = document.getElementById('registri');
    if (!registri) return;

    const endpoint = registri.dataset.endpoint;
    const base = JSON.parse(registri.dataset.filters);
    const body = document.getElementById('registriBody');
    const info = document.getElementById('registriInfo');
    const pag = document.getElementById('registriPag');
    const total = document.getElementById('registriTotal');
    const cari = document.getElementById('registriCari');
    const gizi = document.getElementById('registriGizi');
    const usiaHidden = document.getElementById('filterUsia');
    const state = { usia: usiaHidden.value, q: '', status_gizi: 'semua', page: 1 };
    let timer;
    let pending;
    let requestId = 0;

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);
    }
    function fmt(value) { return Number(value).toLocaleString('id-ID'); }
    function num(value) {
        return value == null ? '—' : Number(value).toLocaleString('id-ID', {
            minimumFractionDigits: 1, maximumFractionDigits: 1
        });
    }
    function tanggal(value) {
        if (!value) return '';
        const parts = String(value).split('-');
        return esc(parts.reverse().join('/'));
    }
    function badgeImun(status) {
        if (status === 'lengkap') return '<span class="km-badge km-badge--ok">Lengkap</span>';
        if (status === 'belum') return '<span class="km-badge km-badge--bad">Belum</span>';
        return '<span class="km-badge km-badge--na">Belum masuk usia</span>';
    }
    function baris(row) {
        const visit = row.kunjungan;
        const antro = visit
            ? '<span class="mono">BB ' + num(visit.bb) + ' kg · TB ' + num(visit.tb) + ' cm · LK ' + num(visit.lk) + ' cm</span>'
                + (visit.gizi ? ' <span class="km-badge km-badge--' + esc(visit.gizi.tone) + '">' + esc(visit.gizi.label) + '</span>' : '')
                + (visit.bb_tidak_naik ? ' <span class="km-badge km-badge--bad">BB tidak naik</span>' : '')
                + '<small>' + tanggal(visit.tgl) + '</small>'
            : '<span class="km-muted">— belum ada kunjungan dalam periode</span>';
        return '<tr>'
            + '<td class="mono">' + fmt(row.no) + '</td>'
            + '<td><b>' + esc(row.nama) + '</b><small>NIK ' + esc(row.nik || '—') + ' · ' + esc(row.jk) + ' · ' + fmt(row.umur_bln) + ' bln (' + tanggal(row.tgl_lahir) + ')</small></td>'
            + '<td>' + (row.nama_ibu ? 'Ibu: ' + esc(row.nama_ibu) : '') + (row.nama_ayah ? '<small>Ayah: ' + esc(row.nama_ayah) + '</small>' : '') + (!row.nama_ibu && !row.nama_ayah ? '—' : '') + '</td>'
            + '<td>' + esc(row.posyandu || '—') + '<small>' + (row.rt ? 'RT ' + esc(row.rt) + ' · ' : '') + esc(row.kelurahan || '—') + '</small></td>'
            + '<td>' + antro + '</td><td>' + badgeImun(row.idl) + '</td><td>' + badgeImun(row.ibl) + '</td>'
            + '<td>' + esc(row.catatan || '—') + '</td>'
            + '<td><a class="im-btn im-btn--ghost im-btn--sm" href="' + esc(row.url_detail) + '">Detail<span class="sr-only"> ' + esc(row.nama) + '</span></a></td>'
            + '</tr>';
    }

    function render(result) {
        body.setAttribute('aria-busy', 'false');
        total.textContent = 'Total: ' + fmt(result.total) + ' balita';
        if (!result.data.length) {
            body.innerHTML = '<tr><td colspan="9"><div class="km-empty">Tidak ada balita yang cocok dengan filter ini</div></td></tr>';
            info.textContent = '';
            pag.replaceChildren();
            return;
        }
        body.innerHTML = result.data.map(baris).join('');
        const first = (result.page - 1) * result.per_page + 1;
        info.textContent = 'Menampilkan ' + fmt(first) + '–' + fmt(first + result.data.length - 1) + ' dari ' + fmt(result.total) + ' balita';
        let html = '<button type="button" data-page="' + (result.page - 1) + '"' + (result.page <= 1 ? ' disabled' : '') + ' aria-label="Sebelumnya">‹</button>';
        const pages = [...new Set([1, result.page - 1, result.page, result.page + 1, result.last_page])]
            .filter((p) => p >= 1 && p <= result.last_page).sort((a, b) => a - b);
        pages.forEach((p, index) => {
            if (index > 0 && p - pages[index - 1] > 1) html += '<span class="km-pag-gap">…</span>';
            html += '<button type="button" data-page="' + p + '" aria-label="Halaman ' + p + '"'
                + (p === result.page ? ' aria-current="page"' : '') + '>' + fmt(p) + '</button>';
        });
        html += '<button type="button" data-page="' + (result.page + 1) + '"' + (result.page >= result.last_page ? ' disabled' : '') + ' aria-label="Berikutnya">›</button>';
        pag.innerHTML = html;
    }

    function loading() {
        body.setAttribute('aria-busy', 'true');
        body.innerHTML = '<tr class="km-skel"><td colspan="9"><span></span></td></tr>'.repeat(5);
        total.textContent = 'Memuat…';
        info.textContent = '';
        pag.replaceChildren();
    }

    // Abort dan nomor permintaan mencegah respons lama mengganti hasil filter terbaru.
    async function load() {
        clearTimeout(timer);
        state.q = cari.value.trim();
        if (pending) pending.abort();
        pending = new AbortController();
        const id = ++requestId;
        loading();
        try {
            const params = new URLSearchParams(Object.assign({}, base, state));
            const response = await fetch(endpoint + '?' + params.toString(), {
                headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: pending.signal
            });
            if (!response.ok) throw new Error(response.status);
            const result = await response.json();
            if (id !== requestId) return;
            render(result);
        } catch (error) {
            if (id !== requestId || error.name === 'AbortError') return;
            body.setAttribute('aria-busy', 'false');
            total.textContent = 'Belum dimuat';
            body.innerHTML = '<tr><td colspan="9" class="km-err">Gagal memuat registri — coba lagi <button type="button" class="im-btn im-btn--ghost im-btn--sm" id="registriUlang">Coba lagi</button></td></tr>';
            document.getElementById('registriUlang').addEventListener('click', load);
        }
    }

    document.querySelectorAll('.km-chip[data-usia]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.km-chip[data-usia]').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
            state.usia = button.dataset.usia;
            state.page = 1;
            usiaHidden.value = state.usia;
            const url = new URL(window.location.href);
            url.searchParams.set('usia', state.usia);
            history.replaceState(null, '', url.toString());
            document.querySelectorAll('#sdidtkRows .km-row').forEach((row) => row.classList.toggle('hl', row.dataset.usia === state.usia));
            load();
        });
    });
    cari.addEventListener('input', () => {
        clearTimeout(timer);
        // Tandai hasil lama kedaluwarsa segera, termasuk selama jeda debounce.
        if (pending) pending.abort();
        ++requestId;
        loading();
        state.page = 1;
        timer = setTimeout(load, 300);
    });
    gizi.addEventListener('change', () => {
        state.status_gizi = gizi.value;
        state.page = 1;
        load();
    });
    pag.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-page]');
        if (!button || button.disabled) return;
        state.page = Number(button.dataset.page);
        load();
        registri.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    });
    document.querySelectorAll('[data-registri-status]').forEach((link) => {
        link.addEventListener('click', () => {
            // K4 menghitung seluruh usia: pulihkan lingkup yang sama sebelum melihat rinciannya.
            document.querySelector('.km-chip[data-usia="semua"]').setAttribute('aria-pressed', 'true');
            document.querySelectorAll('.km-chip[data-usia]:not([data-usia="semua"])').forEach((b) => b.setAttribute('aria-pressed', 'false'));
            state.usia = 'semua';
            usiaHidden.value = 'semua';
            const url = new URL(window.location.href);
            url.searchParams.set('usia', 'semua');
            history.replaceState(null, '', url.toString());
            document.querySelectorAll('#sdidtkRows .km-row').forEach((row) => row.classList.remove('hl'));
            cari.value = '';
            gizi.value = link.dataset.registriStatus;
            gizi.dispatchEvent(new Event('change'));
        });
    });

    load();
})();
