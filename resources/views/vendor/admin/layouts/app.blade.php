<!DOCTYPE html>
<html>
@section('htmlheader')
@include('admin::layouts.partials.htmlheader')
@show

<body>
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
                                <h4>@yield('title-content')</h4>
                            </div>
                            @include('admin::layouts.partials.breadcrumb')
                        </div>
                    </div>
                </div>
                <div class="pd-20 bg-white border-radius-4 box-shadow mb-30">
                    @yield('content')
                </div>
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

    <style>
        /* ===== Sidebar Enhancements ===== */
        .left-side-bar {
            background: linear-gradient(180deg, #001a33 0%, #003366 100%) !important;
        }
        .left-side-bar .brand-logo {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        /* Section group toggle — prominent label */
        .left-side-bar .sidebar-menu li.section-group > a.section-toggle {
            font-size: 13px !important;
            font-weight: 600 !important;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.92) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .left-side-bar .sidebar-menu li.section-group > a.section-toggle .micon {
            font-size: 16px;
            color: #4da6ff;
        }
        .left-side-bar .sidebar-menu li.section-group.show > a.section-toggle {
            background: rgba(0, 102, 204, 0.15) !important;
            color: #fff !important;
            border-bottom-color: transparent;
        }
        .left-side-bar .sidebar-menu li.section-group > a.section-toggle:hover {
            background: rgba(255, 255, 255, 0.05) !important;
        }

        /* Submenu group label */
        .left-side-bar .sidebar-menu ul.submenu > li.submenu-label {
            padding: 14px 15px 4px 60px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.3);
            cursor: default;
            list-style: none;
        }
        .left-side-bar .sidebar-menu ul.submenu > li.submenu-label:first-child {
            padding-top: 8px;
        }

        /* Submenu items inside section groups */
        .left-side-bar .sidebar-menu li.section-group ul.submenu > li > a {
            color: rgba(255, 255, 255, 0.6) !important;
            font-size: 14px;
            padding-top: 12px;
            padding-bottom: 12px;
            transition: all 0.2s ease;
        }
        .left-side-bar .sidebar-menu li.section-group ul.submenu > li > a:hover {
            color: #fff !important;
            background: rgba(0, 102, 204, 0.18) !important;
        }
        .left-side-bar .sidebar-menu li.section-group ul.submenu > li > a.active {
            color: #fff !important;
            background: rgba(0, 102, 204, 0.22) !important;
            border-left: 3px solid #4da6ff;
        }

        /* ===== Header Enhancements ===== */
        .header {
            background: #fff !important;
            border-bottom: 1px solid #e5e7eb !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
        }

        /* ===== Footer ===== */
        .sirindu-footer {
            margin: 0 0 20px;
            padding: 16px 24px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            border-top: 3px solid transparent;
            border-image: linear-gradient(90deg, #0066cc, #047857) 1;
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
            color: #004d99;
            font-size: 15px;
        }
        .footer-icon {
            color: #0066cc;
            font-size: 16px;
        }
        .footer-info {
            font-size: 13px;
            color: #6b7280;
        }
        @media (max-width: 576px) {
            .footer-content {
                flex-direction: column;
                gap: 6px;
                text-align: center;
            }
        }

        /* ===== Page Header Enhancement ===== */
        .page-header .title h4 {
            color: #004d99;
            font-weight: 600;
        }
    </style>

    @section('scripts')
    @include('admin::layouts.partials.scripts')
    @show
</body>

</html>
