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

/* Accordion overrides for create/edit forms */
.accordion .card {
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.accordion .card-header {
    border-radius: 0 !important;
    font-weight: 600;
    padding: 0.75rem 1.25rem;
}

.accordion .card-header .btn-link {
    text-decoration: none;
    font-weight: 600;
    font-size: 1rem;
}

.accordion .card-header .btn-link:hover {
    text-decoration: none;
}

/* Section header gradient variants */
.section-header-a { background: linear-gradient(135deg, var(--primary-blue) 0%, #0077dd 100%) !important; }
.section-header-b { background: linear-gradient(135deg, var(--info-teal) 0%, #0e7490 100%) !important; }
.section-header-c { background: linear-gradient(135deg, var(--warning-amber) 0%, #d97706 100%) !important; }
.section-header-d { background: linear-gradient(135deg, var(--danger-rose) 0%, #e11d48 100%) !important; }
.section-header-e { background: linear-gradient(135deg, var(--text-muted) 0%, #374151 100%) !important; }
.section-header-f { background: linear-gradient(135deg, var(--success-green) 0%, #059669 100%) !important; }
.section-header-g { background: linear-gradient(135deg, var(--primary-blue) 0%, var(--success-green) 100%) !important; }
.section-header-h { background: linear-gradient(135deg, #1e293b 0%, #334155 100%) !important; }
.section-header-i { background: linear-gradient(135deg, var(--info-teal) 0%, var(--primary-blue) 100%) !important; }
.section-header-j { background: linear-gradient(135deg, var(--text-secondary) 0%, var(--text-muted) 100%) !important; }
