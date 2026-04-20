<!DOCTYPE html>
<html lang="id">

@push('styles')
<style>
    /* ── Skip link ──────────────────────────────────────────────── */
    .srd-skip-link {
        position: absolute;
        top: 0;
        left: 16px;
        z-index: 9999;
        padding: 8px 16px;
        background: var(--srd-green);
        color: var(--srd-on-dark);
        font-size: 14px;
        font-weight: 600;
        border-radius: 0 0 8px 8px;
        text-decoration: none;
        transform: translateY(-100%);
        transition: transform 0.15s ease-out;
    }
    .srd-skip-link:focus {
        transform: translateY(0);
        outline: 2px solid #fff;
        outline-offset: 2px;
    }

    /* ── Design Tokens ──────────────────────────────────────────── */
    :root {
        --srd-green:          oklch(0.48 0.14 145);   /* primary — passes 4.5:1 on white */
        --srd-green-brand:    oklch(0.60 0.15 145);   /* Kemenkes #00A651 — decorative */
        --srd-green-icon:     oklch(0.72 0.12 145);   /* light green for icons on dark bg */
        --srd-green-active:   oklch(0.60 0.15 145 / 0.22);
        --srd-green-hover:    oklch(0.60 0.15 145 / 0.10);
        --srd-green-section:  oklch(0.60 0.15 145 / 0.14);
        --srd-green-border:   oklch(0.60 0.15 145 / 0.28);
        --srd-sidebar-bg:     oklch(0.17 0.04 145);   /* dark near-black green */
        --srd-sidebar-hover:  oklch(0.22 0.04 145);
        --srd-border:         oklch(0.87 0.012 145);
        --srd-text-2:         oklch(0.44 0.010 145);  /* secondary text */
        --srd-on-dark:        #fff;
        --srd-surface:        #fff;
        --srd-surface-subtle: oklch(0.96 0.018 145);  /* quicklink bg */
        --srd-surface-hover:  oklch(0.91 0.045 145);  /* quicklink hover bg */
    }

    /* ── Sidebar ────────────────────────────────────────────────── */
    .left-side-bar {
        background: var(--srd-sidebar-bg) !important;
    }
    .left-side-bar .brand-logo {
        border-bottom: 1px solid oklch(1 0 0 / 0.07);
    }

    /* Section group toggle */
    .left-side-bar .sidebar-menu li.section-group > a.section-toggle {
        font-size: 13px !important;
        font-weight: 600 !important;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        color: oklch(1 0 0 / 0.85) !important;
        border-bottom: 1px solid oklch(1 0 0 / 0.05);
    }
    .left-side-bar .sidebar-menu li.section-group > a.section-toggle .micon {
        font-size: 16px;
        color: var(--srd-green-icon);
    }
    .left-side-bar .sidebar-menu li.section-group.show > a.section-toggle {
        background: var(--srd-green-section) !important;
        color: var(--srd-on-dark) !important;
        border-bottom-color: transparent;
    }
    .left-side-bar .sidebar-menu li.section-group > a.section-toggle:hover {
        background: var(--srd-sidebar-hover) !important;
    }

    /* Submenu group label */
    .left-side-bar .sidebar-menu ul.submenu > li.submenu-label {
        padding: 14px 15px 4px 60px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: oklch(1 0 0 / 0.40);
        cursor: default;
        list-style: none;
    }
    .left-side-bar .sidebar-menu ul.submenu > li.submenu-label:first-child {
        padding-top: 8px;
    }

    /* Submenu items */
    .left-side-bar .sidebar-menu li.section-group ul.submenu > li > a {
        color: oklch(1 0 0 / 0.58) !important;
        font-size: 14px;
        padding-top: 12px;
        padding-bottom: 12px;
        transition: background 0.15s ease-out, color 0.15s ease-out;
    }
    .left-side-bar .sidebar-menu li.section-group ul.submenu > li > a:hover {
        color: var(--srd-on-dark) !important;
        background: var(--srd-green-hover) !important;
    }
    /* Active: background tint + full white text — no border-left stripe */
    .left-side-bar .sidebar-menu li.section-group ul.submenu > li > a.active {
        color: var(--srd-on-dark) !important;
        background: var(--srd-green-active) !important;
        font-weight: 600;
    }

    /* ── Header ─────────────────────────────────────────────────── */
    .header {
        background: var(--srd-surface) !important;
        border-bottom: 1px solid var(--srd-border) !important;
    }

    /* ── Footer ─────────────────────────────────────────────────── */
    .sirindu-footer {
        margin: 0 0 20px;
        padding: 16px 24px;
        background: var(--srd-surface);
        border-radius: 10px;
        box-shadow: 0 1px 3px oklch(0 0 0 / 0.06);
        border-top: 2px solid var(--srd-green-border);
    }
    .footer-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .footer-brand {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        color: var(--srd-green);
        font-size: 15px;
    }
    .footer-icon {
        color: var(--srd-green-brand);
        font-size: 16px;
    }
    .footer-info {
        font-size: 13px;
        color: var(--srd-text-2);
    }
    @media (max-width: 576px) {
        .footer-content { flex-direction: column; gap: 6px; text-align: center; }
    }

    /* ── Page header title ──────────────────────────────────────── */
    .page-header .title .page-title {
        color: var(--srd-green);
        font-weight: 600;
        font-size: 1.1rem;
        margin: 0;
    }

    /* ── Sidebar animation ──────────────────────────────────────── */
    /*
     * GPU-accelerated transform-based slide. Asymmetric timing:
     * slow ease-out entrance, fast ease-in exit.
     * will-change is managed via JS — added pre-transition, removed post-transition.
     */
    @media (max-width: 1300px) {
        /* Base (closed / exit state) */
        .left-side-bar {
            left: 0 !important;                /* neutralize vendor left: -281px */
            transform: translateX(-105%);      /* 105% agar box-shadow ikut tersembunyi */
            transition:
                transform 0.22s cubic-bezier(0.55, 0, 1, 0.45),
                box-shadow 0.18s ease-in !important;
            box-shadow: none !important;
        }

        /* Open (entrance state) */
        .left-side-bar.open {
            transform: translateX(0);
            transition:
                transform 0.32s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.32s ease-out !important;
            box-shadow: 12px 0 40px oklch(0 0 0 / 0.20) !important;
        }

        /* Overlay — smooth fade */
        .mobile-menu-overlay {
            transition:
                opacity 0.28s cubic-bezier(0.22, 1, 0.36, 1),
                visibility 0.28s linear !important;
        }

        /* Nav items — stagger entrance saat sidebar terbuka */
        .left-side-bar.open .sidebar-menu > ul > li {
            animation: srd-nav-in 0.38s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .left-side-bar.open .sidebar-menu > ul > li:nth-child(1) { animation-delay: 0.07s; }
        .left-side-bar.open .sidebar-menu > ul > li:nth-child(2) { animation-delay: 0.10s; }
        .left-side-bar.open .sidebar-menu > ul > li:nth-child(3) { animation-delay: 0.13s; }
        .left-side-bar.open .sidebar-menu > ul > li:nth-child(4) { animation-delay: 0.16s; }
        .left-side-bar.open .sidebar-menu > ul > li:nth-child(5) { animation-delay: 0.19s; }
        .left-side-bar.open .sidebar-menu > ul > li:nth-child(6) { animation-delay: 0.22s; }
        .left-side-bar.open .sidebar-menu > ul > li:nth-child(n+7) { animation-delay: 0.24s; }

        @keyframes srd-nav-in {
            from { opacity: 0; transform: translateX(-10px); }
            to   { opacity: 1; transform: translateX(0); }
        }
    }

    /* Close button (×) — rotate on hover */
    .left-side-bar .close-sidebar {
        transition: transform 0.2s cubic-bezier(0.22, 1, 0.36, 1),
                    color 0.15s ease-out;
    }
    .left-side-bar .close-sidebar:hover {
        transform: rotate(90deg) scale(1.1);
        color: var(--srd-green-icon) !important;
    }

    /* Hamburger icon — subtle scale on press */
    .menu-icon {
        transition: transform 0.12s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .menu-icon:active {
        transform: scale(0.88);
    }

    /* ── Reduced motion ─────────────────────────────────────────── */
    @media (prefers-reduced-motion: reduce) {
        .left-side-bar,
        .left-side-bar.open,
        .left-side-bar.sidebar-expanded,
        .mobile-menu-overlay,
        .left-side-bar .close-sidebar,
        .menu-icon,
        .left-side-bar.open .sidebar-menu > ul > li {
            transition-duration: 0.01ms !important;
            animation-duration: 0.01ms !important;
            animation-delay: 0ms !important;
        }
    }

    /* ── Desktop auto-hide sidebar ──────────────────────────────── */
    @media (min-width: 1301px) {
        /* Collapsed by default — overlays content, no layout shift */
        .left-side-bar {
            transform: translateX(-100%);
            transition:
                transform 0.28s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.28s ease-out !important;
            box-shadow: none !important;
            z-index: 1050;
        }
        .left-side-bar.sidebar-expanded {
            transform: translateX(0);
            box-shadow: 8px 0 40px oklch(0 0 0 / 0.22) !important;
        }

        /* Main area and header reclaim full width */
        .main-container {
            margin-left: 0 !important;
        }
        .header {
            left: 0 !important;
            width: 100% !important;
        }

        /* Thin hover-trigger strip anchored to left edge */
        .sidebar-hover-trigger {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 10px;
            z-index: 1049;
        }

        /* Nav-item stagger entrance when expanded on desktop */
        .left-side-bar.sidebar-expanded .sidebar-menu > ul > li {
            animation: srd-nav-in 0.35s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .left-side-bar.sidebar-expanded .sidebar-menu > ul > li:nth-child(1) { animation-delay: 0.05s; }
        .left-side-bar.sidebar-expanded .sidebar-menu > ul > li:nth-child(2) { animation-delay: 0.08s; }
        .left-side-bar.sidebar-expanded .sidebar-menu > ul > li:nth-child(3) { animation-delay: 0.11s; }
        .left-side-bar.sidebar-expanded .sidebar-menu > ul > li:nth-child(4) { animation-delay: 0.14s; }
        .left-side-bar.sidebar-expanded .sidebar-menu > ul > li:nth-child(5) { animation-delay: 0.17s; }
        .left-side-bar.sidebar-expanded .sidebar-menu > ul > li:nth-child(n+6) { animation-delay: 0.20s; }
    }
</style>
@endpush

@section('htmlheader')
@include('admin::layouts.partials.htmlheader')
@show

<body>
    <a class="srd-skip-link" href="#main-content">Lompat ke konten utama</a>
    @include('admin::layouts.partials.mainheader')
    @include('admin::layouts.partials.rightsidebar')
    @include('admin::layouts.partials.leftsidebar')

    <div class="main-container">
        <div class="pd-ltr-20 xs-pd-20-10">
            <div class="min-height-200px">
                <div class="page-header">
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="title">
                                <p class="page-title">@yield('title-content')</p>
                            </div>
                            @include('admin::layouts.partials.breadcrumb')
                        </div>
                    </div>
                </div>
                <main id="main-content" class="pd-20 bg-white border-radius-4 box-shadow mb-30">
                    @yield('content')
                </main>
            </div>
            <footer class="sirindu-footer">
                <div class="footer-content">
                    <div class="footer-brand">
                        <i class="fa fa-heartbeat footer-icon"></i>
                        <span>Si Rindu</span>
                    </div>
                    <div class="footer-info">
                        &copy; {{ date('Y') }} Diskominfo Kota Bontang
                    </div>
                </div>
            </footer>
        </div>
    </div>

    @section('scripts')
    @include('admin::layouts.partials.scripts')
    @show
    <script>
    /* Sidebar will-change: applied just before animation, removed after ── */
    (function () {
        var sidebar = document.querySelector('.left-side-bar');
        if (!sidebar) return;
        var observer = new MutationObserver(function () {
            sidebar.style.willChange = 'transform';
            sidebar.addEventListener('transitionend', function () {
                sidebar.style.willChange = '';
            }, { once: true });
        });
        var mq = window.matchMedia('(max-width: 1299px)');
        function sync(e) {
            if (e.matches) {
                observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
            } else {
                observer.disconnect();
                sidebar.style.willChange = '';
            }
        }
        mq.addEventListener('change', sync);
        sync(mq);
    })();

    /* Desktop auto-hide sidebar — show on hover ─────────────── */
    (function () {
        var sidebar = document.querySelector('.left-side-bar');
        if (!sidebar) return;

        /* Thin trigger strip anchored to the left edge */
        var trigger = document.createElement('div');
        trigger.className = 'sidebar-hover-trigger';
        document.body.appendChild(trigger);

        var hideTimer = null;
        var mqDesktop = window.matchMedia('(min-width: 1301px)');

        function show() {
            clearTimeout(hideTimer);
            sidebar.classList.add('sidebar-expanded');
        }

        function scheduleHide() {
            clearTimeout(hideTimer);
            hideTimer = setTimeout(function () {
                sidebar.classList.remove('sidebar-expanded');
            }, 180);
        }

        function attachDesktop() {
            trigger.addEventListener('mouseenter', show);
            sidebar.addEventListener('mouseenter', show);
            trigger.addEventListener('mouseleave', scheduleHide);
            sidebar.addEventListener('mouseleave', scheduleHide);
            trigger.style.display = '';
        }

        function detachDesktop() {
            clearTimeout(hideTimer);
            sidebar.classList.remove('sidebar-expanded');
            trigger.style.display = 'none';
        }

        function onBreakpoint(e) {
            if (e.matches) { attachDesktop(); } else { detachDesktop(); }
        }

        mqDesktop.addEventListener('change', onBreakpoint);
        onBreakpoint(mqDesktop);
    })();
    </script>
</body>

</html>
