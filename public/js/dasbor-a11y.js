/* Perilaku aksesibilitas dasbor (.im-*): tombol "?" penjelasan & tab ARIA.
   Tanpa dependensi; aman dimuat di halaman mana pun yang memakai markup terkait. */
(function () {
    'use strict';

    // ── Tombol "?" (disclosure) ────────────────────────────────────────────
    function setHelp(btn, open) {
        var body = document.getElementById(btn.getAttribute('aria-controls'));
        if (!body) { return; }
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        body.hidden = !open;
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.im-help-btn');
        if (!btn) { return; }
        setHelp(btn, btn.getAttribute('aria-expanded') !== 'true');
    });

    // Esc menutup penjelasan yang fokusnya ada di tombol atau di dalam bodinya.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        var btn = e.target.closest('.im-help-btn');
        if (!btn) {
            var body = e.target.closest('.im-help-body');
            if (body) { btn = document.querySelector('.im-help-btn[aria-controls="' + body.id + '"]'); }
        }
        if (btn && btn.getAttribute('aria-expanded') === 'true') {
            setHelp(btn, false);
            btn.focus();
        }
    });

    // ── Tab ARIA ([role=tablist] > [role=tab], panel via aria-controls) ────
    document.querySelectorAll('[role="tablist"]').forEach(function (list) {
        var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));

        function select(tab, focus) {
            tabs.forEach(function (t) {
                var on = t === tab;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.tabIndex = on ? 0 : -1;
                var panel = document.getElementById(t.getAttribute('aria-controls'));
                if (panel) { panel.hidden = !on; }
            });
            if (focus) { tab.focus(); }
        }

        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function () { select(tab, false); });
            tab.addEventListener('keydown', function (e) {
                var to = null;
                if (e.key === 'ArrowRight') { to = tabs[(i + 1) % tabs.length]; }
                else if (e.key === 'ArrowLeft') { to = tabs[(i - 1 + tabs.length) % tabs.length]; }
                else if (e.key === 'Home') { to = tabs[0]; }
                else if (e.key === 'End') { to = tabs[tabs.length - 1]; }
                if (to) { e.preventDefault(); select(to, true); }
            });
        });
    });
})();
