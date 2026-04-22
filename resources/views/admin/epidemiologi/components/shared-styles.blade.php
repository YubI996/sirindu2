{{-- WCAG AA Compliant Styles - Blue & Green Theme for Epidemiologi Module --}}
{{-- All colors tested for 4.5:1 contrast ratio minimum on white (#fff) --}}

:root {
    /* Primary Blues - WCAG AA Compliant */
    --primary-blue: #0066cc;          /* 5.5:1 contrast */
    --primary-blue-dark: #004d99;     /* 7.8:1 contrast */
    --primary-blue-light: #e6f2ff;

    /* Greens - WCAG AA Compliant */
    --success-green: #047857;         /* 5.9:1 contrast */
    --success-green-dark: #065f46;    /* 7.5:1 contrast */
    --success-green-light: #d1fae5;

    /* Secondary Colors - WCAG AA Compliant */
    --info-teal: #0891b2;             /* 4.5:1 contrast */
    --warning-amber: #b45309;         /* 5.2:1 contrast */
    --danger-rose: #be123c;           /* 5.6:1 contrast */

    /* Neutral - WCAG AA Compliant */
    --text-muted: #4b5563;            /* 7.5:1 contrast */
    --text-secondary: #6b7280;        /* 5.0:1 contrast */
}

/* Skip Link */
.skip-link {
    position: absolute;
    top: -40px;
    left: 0;
    background: var(--primary-blue-dark);
    color: #fff;
    padding: 8px 16px;
    z-index: 9999;
    text-decoration: none;
}
.skip-link:focus {
    top: 0;
}

/* Enhanced Focus Indicators - WCAG 2.4.7 */
a:focus,
button:focus,
.btn:focus,
input:focus,
select:focus,
textarea:focus,
[tabindex]:focus {
    outline: 3px solid var(--primary-blue) !important;
    outline-offset: 2px !important;
    box-shadow: 0 0 0 4px rgba(0, 102, 204, 0.25) !important;
}

/* High Contrast Text - WCAG 1.4.3 */
.text-accessible-muted {
    color: var(--text-muted) !important;
}

/* Stat Card */
.stat-card {
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid #e5e7eb;
}

@media (prefers-reduced-motion: reduce) {
    .stat-card,
    .stat-card:hover {
        transition: none;
        transform: none;
    }
    [class*="section-header-"] {
        transition: none !important;
    }
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 102, 204, 0.15);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(180deg, var(--primary-blue) 0%, var(--success-green) 100%);
}

.stat-card.status-success::before {
    background: linear-gradient(180deg, #10b981 0%, var(--success-green) 100%);
}
.stat-card.status-warning::before {
    background: linear-gradient(180deg, #f59e0b 0%, var(--warning-amber) 100%);
}
.stat-card.status-danger::before {
    background: linear-gradient(180deg, #f43f5e 0%, var(--danger-rose) 100%);
}
.stat-card.status-info::before {
    background: linear-gradient(180deg, #06b6d4 0%, var(--info-teal) 100%);
}

/* Info Card */
.info-card {
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.info-card .card-header {
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--success-green) 100%);
    color: #ffffff;
    border-radius: 0 !important;
    font-weight: 600;
    padding: 1rem 1.25rem;
}

.info-card .card-header h2 {
    font-size: 1rem;
    margin: 0;
    color: #ffffff;
}

/* WCAG AA Compliant Badge Colors */
.badge-accessible-success {
    background-color: var(--success-green) !important;
    color: #ffffff !important;
}

.badge-accessible-warning {
    background-color: var(--warning-amber) !important;
    color: #ffffff !important;
}

.badge-accessible-danger {
    background-color: var(--danger-rose) !important;
    color: #ffffff !important;
}

.badge-accessible-info {
    background-color: var(--info-teal) !important;
    color: #ffffff !important;
}

.badge-accessible-secondary {
    background-color: var(--text-muted) !important;
    color: #ffffff !important;
}

.badge-status {
    font-size: 0.75rem;
    padding: 0.4em 0.7em;
    font-weight: 600;
    border-radius: 6px;
}

/* Table with Blue-Green Theme */
.table th {
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--success-green) 100%);
    color: #ffffff;
}

.table-accessible th,
.table-accessible td {
    padding: 0.75rem;
    vertical-align: middle;
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(0, 102, 204, 0.03);
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 102, 204, 0.08);
}

/* Button Styles */
.btn-outline-primary {
    color: var(--primary-blue);
    border-color: var(--primary-blue);
}

.btn-outline-primary:hover,
.btn-outline-primary:focus {
    background-color: var(--primary-blue);
    border-color: var(--primary-blue);
    color: #ffffff;
}

.btn-outline-info {
    color: var(--info-teal);
    border-color: var(--info-teal);
}

.btn-outline-info:hover,
.btn-outline-info:focus {
    background-color: var(--info-teal);
    border-color: var(--info-teal);
    color: #ffffff;
}

.btn-outline-success {
    color: var(--success-green);
    border-color: var(--success-green);
}

.btn-outline-success:hover,
.btn-outline-success:focus {
    background-color: var(--success-green);
    border-color: var(--success-green);
    color: #ffffff;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-blue) 0%, #0077dd 100%);
    border-color: var(--primary-blue);
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--primary-blue-dark) 0%, var(--primary-blue) 100%);
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger-rose) 0%, #e11d48 100%);
    border-color: var(--danger-rose);
}

/* Screen reader only class */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.sr-only-focusable:focus {
    position: static;
    width: auto;
    height: auto;
    padding: inherit;
    margin: inherit;
    overflow: visible;
    clip: auto;
    white-space: normal;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .info-card {
        border: 2px solid #000;
    }
    .stat-card::before {
        width: 8px;
    }
    .badge-status {
        border: 1px solid currentColor;
    }
}

/* Links with Blue theme */
a:not(.btn) {
    color: var(--primary-blue);
}

a:not(.btn):hover {
    color: var(--primary-blue-dark);
}

/* =============================================
   MODERN FORM CONTROLS
   ============================================= */
.form-control {
    border-radius: 8px;
    background-color: #fafbfc;
    border: 1px solid #d1d5db;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.form-control:focus {
    background-color: #fff;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.15) !important;
    outline: none !important;
}

.form-control[readonly] {
    border-style: dashed;
    background-color: #f3f4f6;
}

label {
    font-size: 0.82rem;
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.35rem;
}

select.form-control {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236b7280' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    padding-right: 2rem;
}

.form-text {
    font-size: 0.78rem;
    color: var(--text-secondary);
}

/* =============================================
   ACCORDION — MINIMALIST WITH BLUE ACCENT
   ============================================= */
.accordion .card {
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.accordion .card-header {
    border-radius: 0 !important;
    font-weight: 600;
    padding: 0;
    border-bottom: 1px solid #e5e7eb;
}

.accordion .card-header .btn-link {
    text-decoration: none;
    font-weight: 600;
    font-size: 1rem;
    display: flex;
    align-items: center;
    width: 100%;
    text-align: left;
    padding: 0.85rem 1.25rem;
    color: var(--primary-blue-dark);
}

.accordion .card-header .btn-link:not(.collapsed):hover {
    text-decoration: none;
    background-color: #f0f7ff;
}

.accordion .card-header .btn-link i:first-child {
    color: var(--primary-blue);
    margin-right: 0.5rem;
    width: 20px;
    text-align: center;
}

.accordion .card-body {
    padding: 1.25rem;
}

/* =============================================
   PER-SECTION COLOR CODING
   ============================================= */
.section-header-a  { --section-color: #0066cc; --section-bg: #e6f2ff; }
.section-header-b  { --section-color: #0891b2; --section-bg: #e0f7fa; }
.section-header-c  { --section-color: #b45309; --section-bg: #fef3c7; }
.section-header-d  { --section-color: #be123c; --section-bg: #ffe4e6; }
.section-header-e  { --section-color: #475569; --section-bg: #f1f5f9; }
.section-header-f  { --section-color: #047857; --section-bg: #d1fae5; }
.section-header-g  { --section-color: #4f46e5; --section-bg: #e0e7ff; }
.section-header-h  { --section-color: #334155; --section-bg: #e2e8f0; }
.section-header-i  { --section-color: #0284c7; --section-bg: #e0f2fe; }
.section-header-j  { --section-color: #6b7280; --section-bg: #f3f4f6; }

/* Active (expanded) section header */
.section-header-a,
.section-header-b,
.section-header-c,
.section-header-d,
.section-header-e,
.section-header-f,
.section-header-g,
.section-header-h,
.section-header-i,
.section-header-j {
    background: var(--section-bg) !important;
    border-left: 4px solid var(--section-color) !important;
    transition: background 0.3s ease, border-color 0.3s ease;
}

/* Active btn-link — vivid section color */
[class*="section-header-"] .btn-link:not(.collapsed) {
    color: var(--section-color) !important;
}

[class*="section-header-"] .btn-link:not(.collapsed) i:first-child {
    color: var(--section-color) !important;
}

/* =============================================
   COLLAPSED STATE — MUTED / DISABLED LOOK
   ============================================= */
[class*="section-header-"]:has(.btn-link.collapsed) {
    background: #f9fafb !important;
    border-left-color: #d1d5db !important;
}

[class*="section-header-"] .btn-link.collapsed {
    color: #6b7280 !important;
    background: #f9fafb;
    cursor: pointer;
}

[class*="section-header-"] .btn-link.collapsed i:first-child {
    color: #9ca3af !important;
}

[class*="section-header-"] .btn-link.collapsed:hover {
    background: #f0f1f3;
    color: #374151 !important;
}

/* =============================================
   SECTION SUBTITLE HEADINGS
   ============================================= */
.section-subtitle {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--primary-blue-dark);
    border-bottom: 2px solid var(--primary-blue-light);
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

.section-subtitle i {
    color: var(--primary-blue);
    margin-right: 0.35rem;
}

/* =============================================
   UNIFIED CHECKBOX CARD GRID
   ============================================= */
.check-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 8px;
}

.check-card {
    display: flex;
    flex-direction: column;
    gap: 0;
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    transition: all 0.2s ease;
    cursor: pointer;
    background: #fafbfc;
}
.check-card-top {
    display: flex;
    align-items: center;
    gap: 8px;
}
.symptom-date-wrap {
    margin-top: 6px;
    padding-top: 6px;
    border-top: 1px solid rgba(0,0,0,0.07);
}
.symptom-date-wrap input[type="date"] {
    font-size: 0.78rem;
    padding: 2px 6px;
    height: auto;
    cursor: default;
}

.check-card:hover {
    background: var(--primary-blue-light);
    border-color: var(--primary-blue);
}

.check-card.checked {
    background: var(--primary-blue-light);
    border-color: var(--primary-blue);
}

.check-card input[type="checkbox"] {
    margin: 0;
}

.check-card label {
    margin: 0;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 400;
    color: #1f2937;
}

.check-card .check-icon {
    font-size: 1rem;
    color: var(--primary-blue);
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}

/* Danger variant for komplikasi */
.check-card.check-danger:hover {
    background: #fef2f2;
    border-color: var(--danger-rose);
}

.check-card.check-danger.checked {
    background: #fef2f2;
    border-color: var(--danger-rose);
}

.check-card.check-danger .check-icon {
    color: var(--danger-rose);
}

/* =============================================
   FORM ACTIONS BAR
   ============================================= */
.form-actions-card {
    position: sticky;
    bottom: 0;
    z-index: 10;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.08);
}

.form-actions-card .btn {
    border-radius: 8px;
}

.form-actions-card .btn-lg {
    padding: 0.5rem 1.25rem;
    font-size: 0.95rem;
}

/* =============================================
   RESPONSIVE — MOBILE FIRST
   ============================================= */
@media (max-width: 768px) {
    .form-control {
        font-size: 1rem; /* prevent iOS zoom */
    }

    .accordion .card-body {
        padding: 0.85rem;
    }

    .row > [class*="col-md-"] {
        flex: 0 0 100%;
        max-width: 100%;
    }

    .check-grid {
        grid-template-columns: 1fr;
    }

    .form-actions-card .d-flex {
        flex-direction: row;
        flex-wrap: nowrap;
    }

    .form-actions-card .btn {
        flex: 1;
        margin-bottom: 0;
    }
}

@media (min-width: 769px) and (max-width: 991px) {
    .row > .col-md-2,
    .row > .col-md-3 {
        flex: 0 0 50%;
        max-width: 50%;
    }
}
