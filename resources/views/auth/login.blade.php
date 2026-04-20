@extends('admin::layouts.login')

@section('title', 'Si Rindu — Sistem Informasi Anak Rindu')

@section('content')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Condensed:wght@500;600;700&display=swap" rel="stylesheet">
<style>
/* ── Design Tokens ─────────────────────────────────────────────── */
:root {
  /* Kemenkes green — darker shade used for interactive elements to
     ensure ≥ 4.5:1 contrast with white (WCAG AA) */
  --green:          oklch(0.48 0.14 145);   /* #006E35 approx — button bg, links */
  --green-hover:    oklch(0.40 0.13 145);   /* darker for hover */
  --green-brand:    oklch(0.60 0.15 145);   /* #00A651 — decorative accents only */
  --green-dim:      oklch(0.91 0.045 145);  /* badge background */
  --green-subtle:   oklch(0.965 0.016 145); /* page background */
  --green-ring:     oklch(0.48 0.14 145 / 0.18); /* focus ring */

  --text:           oklch(0.19 0.014 145);  /* near-black, green-tinted */
  --text-2:         oklch(0.41 0.011 145);  /* secondary */
  --text-3:         oklch(0.60 0.008 145);  /* muted */
  --surface:        oklch(0.985 0.005 145); /* input background */
  --white:          oklch(0.99 0.003 145);  /* tinted white for cards/header */
  --border:         oklch(0.87 0.011 145);
  --border-focus:   var(--green);

  --error:          oklch(0.53 0.18 25);
  --error-bg:       oklch(0.975 0.020 25);
  --error-border:   oklch(0.53 0.18 25 / 0.20);
}

/* ── Reset ─────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

/* ── Body ──────────────────────────────────────────────────────── */
body.login-page {
  font-family: 'Barlow', sans-serif;
  background: var(--green-subtle);
  margin: 0;
}

/* ── Wrap ──────────────────────────────────────────────────────── */
.srd-wrap {
  min-height: 100vh;
  display: flex;
  align-items: center;
  padding: 40px 16px;
  overflow-y: auto;
}
.srd-wrap .container { max-width: 1060px; }

/* ── Hero ──────────────────────────────────────────────────────── */
.srd-hero {
  padding-right: 56px;
  animation: fadeUp 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
}
.srd-badge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: var(--green-dim);
  color: var(--green);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  padding: 5px 12px;
  border-radius: 6px;
  margin-bottom: 24px;
}
.srd-badge-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--green-brand);
  animation: pulse 2.2s ease-in-out infinite;
  flex-shrink: 0;
}
.srd-hero-title {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: clamp(26px, 3vw, 38px);
  font-weight: 700;
  color: var(--text);
  line-height: 1.15;
  margin: 0 0 14px;
}
.srd-hero-desc {
  font-size: 15px;
  line-height: 1.65;
  color: var(--text-2);
  margin: 0 0 32px;
  max-width: 42ch;
}
.srd-stats {
  display: flex;
  gap: 28px;
  padding-top: 8px;
}
.srd-stat-val {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 24px;
  font-weight: 700;
  color: var(--green-brand);
  line-height: 1;
  display: block;
}
.srd-stat-lbl {
  font-size: 12px;
  color: var(--text-3);
  margin-top: 4px;
  display: block;
}
.srd-divider {
  width: 1px;
  height: 34px;
  background: var(--border);
  align-self: center;
  flex-shrink: 0;
}
.srd-hero-img {
  width: 100%;
  max-width: 300px;
  display: block;
  margin-top: 40px;
  opacity: 0.85;
}

/* ── Card ──────────────────────────────────────────────────────── */
.srd-card {
  background: var(--white);
  border-radius: 18px;
  box-shadow:
    0 2px 8px oklch(0.20 0.015 145 / 0.06),
    0 8px 32px oklch(0.20 0.015 145 / 0.08);
  padding: 40px 36px;
  animation: fadeUp 0.55s 0.08s cubic-bezier(0.22, 1, 0.36, 1) both;
}
.srd-greeting {
  font-size: 13px;
  color: var(--text-3);
  margin: 0 0 4px;
  line-height: 1.4;
}
/* h1 on all viewports — hero h2 is supplementary on desktop */
.srd-card-title {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 28px;
  font-weight: 700;
  color: var(--text);
  margin: 0 0 28px;
  line-height: 1.2;
}
.srd-card-title em {
  font-style: normal;
  color: var(--green);
}

/* ── Error block ───────────────────────────────────────────────── */
.srd-error {
  background: var(--error-bg);
  border: 1px solid var(--error-border);
  border-radius: 10px;
  padding: 12px 14px;
  margin-bottom: 20px;
  display: flex;
  gap: 10px;
  align-items: flex-start;
  animation: fadeDown 0.25s cubic-bezier(0.22, 1, 0.36, 1) both;
}
.srd-error svg { flex-shrink: 0; margin-top: 1px; }
.srd-error ul  { margin: 0; padding: 0; list-style: none; }
.srd-error li  { font-size: 13px; color: var(--error); line-height: 1.5; }

/* ── Fields ────────────────────────────────────────────────────── */
.srd-field { margin-bottom: 16px; }

.srd-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-2);
  margin-bottom: 7px;
}

.srd-input-wrap { position: relative; }

.srd-input {
  width: 100%;
  /* right padding accounts for the icon touch area (44px) */
  padding: 11px 48px 11px 16px;
  font-family: 'Barlow', sans-serif;
  font-size: 15px;
  color: var(--text);
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: 10px;
  outline: none;
  transition: border-color 0.16s ease-out, box-shadow 0.16s ease-out, background 0.16s ease-out;
  -webkit-appearance: none;
  appearance: none;
}
.srd-input:focus {
  border-color: var(--border-focus);
  background: var(--white);
  box-shadow: 0 0 0 3px var(--green-ring);
}
.srd-input.is-invalid              { border-color: var(--error); }
.srd-input.is-invalid:focus        { box-shadow: 0 0 0 3px oklch(0.53 0.18 25 / 0.14); }

/* icon slot — always 44×44 tap target, centered visually */
.srd-icon {
  position: absolute;
  right: 0;
  top: 0;
  width: 44px;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-3);
  font-size: 15px;
  pointer-events: none;
  transition: color 0.16s ease-out;
}
.srd-input:focus ~ .srd-icon { color: var(--green); }

/* toggle extends the tap target to 44×44px */
.srd-toggle {
  cursor: pointer;
  pointer-events: all;
  -webkit-user-select: none;
  user-select: none;
  border-radius: 0 10px 10px 0; /* match input radius on right side */
}
.srd-toggle:focus-visible {
  outline: 2px solid var(--green);
  outline-offset: -2px;
  border-radius: 0 10px 10px 0;
  color: var(--green);
}

/* ── Submit button ─────────────────────────────────────────────── */
.srd-btn {
  width: 100%;
  padding: 13px 20px;
  font-family: 'Barlow', sans-serif;
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.01em;
  color: var(--white);
  background: var(--green);           /* L=0.48 → ~5.3:1 contrast with white ✓ */
  border: none;
  border-radius: 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 24px;
  position: relative;
  overflow: hidden;
  min-height: 48px;                   /* touch target */
  transition: background 0.16s ease-out, transform 0.1s ease-out, box-shadow 0.18s ease-out;
}
.srd-btn:hover:not(:disabled) {
  background: var(--green-hover);
  box-shadow: 0 4px 18px oklch(0.48 0.14 145 / 0.32);
  transform: translateY(-1px);
}
.srd-btn:active:not(:disabled) {
  transform: translateY(1px);
  box-shadow: none;
}
.srd-btn:focus-visible {
  outline: 2px solid var(--green);
  outline-offset: 3px;
}
.srd-btn:disabled {
  opacity: 0.72;
  cursor: not-allowed;
  transform: none;
}

/* spinner — absolutely centered, animation uses compound transform */
.srd-spinner {
  display: none;
  width: 17px;
  height: 17px;
  border: 2.5px solid oklch(1 0 0 / 0.35);
  border-top-color: var(--white);
  border-radius: 50%;
  position: absolute;
  top: 50%;
  left: 50%;
  animation: spin 0.65s linear infinite;
}
.srd-btn-text {
  transition: opacity 0.12s ease-out;
}
.srd-btn.is-loading .srd-btn-text { opacity: 0; }
.srd-btn.is-loading .srd-spinner  { display: block; }

/* ── Keyframes ─────────────────────────────────────────────────── */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(14px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeDown {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes spin {
  /* compound transform avoids conflict with absolute positioning */
  from { transform: translate(-50%, -50%) rotate(0deg); }
  to   { transform: translate(-50%, -50%) rotate(360deg); }
}
@keyframes pulse {
  0%, 100% { opacity: 1;    transform: scale(1); }
  50%       { opacity: 0.5; transform: scale(0.82); }
}

/* ── Mobile ────────────────────────────────────────────────────── */
@media (max-width: 767px) {
  .srd-wrap { padding: 24px 12px; align-items: flex-start; }
  .srd-card { padding: 28px 20px; border-radius: 14px; }
  .srd-card-title { font-size: 24px; }
}

/* ── Reduced motion ────────────────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.001ms !important;
    transition-duration: 0.001ms !important;
  }
}
</style>

{{-- Main --}}
<main class="srd-wrap">
  <div class="container">
    <div class="row align-items-center">

      {{-- Left: Hero (desktop only) --}}
      <div class="col-md-6 col-lg-7 d-none d-md-block">
        <div class="srd-hero">

          <span class="srd-badge" aria-hidden="true">
            <span class="srd-badge-dot"></span>
            Sistem Aktif
          </span>

          {{-- Supplementary heading; card h1 is the primary heading for all viewports --}}
          <h2 class="srd-hero-title">
            Sistem Informasi<br>Kesehatan Anak Rindu
          </h2>

          <p class="srd-hero-desc">
            Platform terpadu pencatatan tumbuh kembang anak, imunisasi,
            dan analisis status gizi berbasis standar WHO untuk petugas
            kesehatan di seluruh wilayah.
          </p>

          <div class="srd-stats" aria-label="Fitur unggulan">
            <div>
              <span class="srd-stat-val">WHO</span>
              <span class="srd-stat-lbl">Standar Z-score</span>
            </div>
            <div class="srd-divider" aria-hidden="true"></div>
            <div>
              <span class="srd-stat-val">4</span>
              <span class="srd-stat-lbl">Indikator gizi</span>
            </div>
            <div class="srd-divider" aria-hidden="true"></div>
            <div>
              <span class="srd-stat-val">Live</span>
              <span class="srd-stat-lbl">Data terkini</span>
            </div>
          </div>

          <img
            class="srd-hero-img"
            src="{{ asset('admin/vendors/images/medicine.svg') }}"
            alt=""
            aria-hidden="true"
          >
        </div>
      </div>

      {{-- Right: Form Card --}}
      <div class="col-md-6 col-lg-5">
        <div class="srd-card">

          <p class="srd-greeting" id="js-greeting">Selamat datang kembali</p>

          {{-- h1 on every viewport — hero h2 is supplementary on desktop only --}}
          <h1 class="srd-card-title">Masuk ke <em>Si Rindu</em></h1>

          @if ($errors->any())
          <div class="srd-error" role="alert" aria-live="assertive">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true">
              <path d="M7.5 1.5L13.5 13.5H1.5L7.5 1.5Z" stroke="var(--error)" stroke-width="1.5" stroke-linejoin="round"/>
              <path d="M7.5 6V9" stroke="var(--error)" stroke-width="1.5" stroke-linecap="round"/>
              <circle cx="7.5" cy="11" r="0.75" fill="var(--error)"/>
            </svg>
            <ul>
              @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
          @endif

          <form
            method="POST"
            action="{{ route('login') }}"
            id="login-form"
            aria-label="Form masuk ke Si Rindu"
            novalidate
          >
            @csrf

            {{-- Email --}}
            <div class="srd-field">
              <label class="srd-label" for="email">Email</label>
              <div class="srd-input-wrap">
                <input
                  id="email"
                  type="text"
                  name="email"
                  class="srd-input @error('email') is-invalid @enderror"
                  value="{{ old('email') }}"
                  placeholder="nama@contoh.com"
                  autocomplete="email"
                  autofocus
                  aria-required="true"
                  @if($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif
                >
                <span class="srd-icon" aria-hidden="true">
                  <i class="dw dw-user1"></i>
                </span>
              </div>
            </div>

            {{-- Password --}}
            <div class="srd-field">
              <label class="srd-label" for="password">Kata Sandi</label>
              <div class="srd-input-wrap">
                <input
                  id="password"
                  type="password"
                  name="password"
                  class="srd-input @error('password') is-invalid @enderror"
                  placeholder="••••••••"
                  autocomplete="current-password"
                  aria-required="true"
                  @if($errors->has('password')) aria-invalid="true" @endif
                >
                <span
                  class="srd-icon srd-toggle"
                  id="toggle-pwd"
                  role="button"
                  tabindex="0"
                  aria-pressed="false"
                  aria-label="Tampilkan kata sandi"
                >
                  <i class="fa fa-eye" id="toggle-pwd-icon" aria-hidden="true"></i>
                </span>
              </div>
            </div>

            <button
              type="submit"
              class="srd-btn"
              id="btn-submit"
              aria-label="Masuk ke Si Rindu"
            >
              <span class="srd-btn-text">Masuk</span>
              <span class="srd-spinner" aria-hidden="true"></span>
            </button>

          </form>
        </div>
      </div>

    </div>
  </div>
</main>

@endsection

@section('scripts')
@parent
<script>
(function () {
  'use strict';

  // ── Time-based greeting ────────────────────────────────────────
  var hour  = new Date().getHours();
  var greet = hour < 11 ? 'Selamat pagi'
            : hour < 15 ? 'Selamat siang'
            : hour < 19 ? 'Selamat sore'
            :              'Selamat malam';
  var greetEl = document.getElementById('js-greeting');
  if (greetEl) greetEl.textContent = greet + ', Petugas';

  // ── Password toggle ────────────────────────────────────────────
  var toggleBtn  = document.getElementById('toggle-pwd');
  var toggleIcon = document.getElementById('toggle-pwd-icon');
  var pwdInput   = document.getElementById('password');

  function setPasswordVisible(visible) {
    pwdInput.type = visible ? 'text' : 'password';
    toggleIcon.className = visible ? 'fa fa-eye-slash' : 'fa fa-eye';
    toggleBtn.setAttribute('aria-pressed', String(visible));
    toggleBtn.setAttribute('aria-label', visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
  }

  toggleBtn.addEventListener('click', function () {
    setPasswordVisible(pwdInput.type === 'password');
  });
  toggleBtn.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      setPasswordVisible(pwdInput.type === 'password');
    }
  });

  // ── Loading state on submit ────────────────────────────────────
  document.getElementById('login-form').addEventListener('submit', function () {
    var btn = document.getElementById('btn-submit');
    btn.classList.add('is-loading');
    btn.disabled = true;
  });
})();
</script>
@endsection
