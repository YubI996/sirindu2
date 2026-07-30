# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Sirindu is a Laravel 12 web application for managing child health data (Sistem Informasi Anak Rindu). It tracks children's growth metrics, immunization records, and calculates Z-score nutritional status indicators based on WHO standards.

## Architecture

### Authentication and Roles
Two user types with middleware protection:
- `super-admin`: User management, routes prefixed with `/super-admin/`
- `admin`: Child data management, routes prefixed with `/admin/`
- `IsAdmin` middleware allows both admin types
- `UserAccess` middleware for role-specific access

### Z-Score Calculation
`app/Helpers/helpers.php` contains the `z_score()` function that calculates:
- IMT/U (BMI for Age)
- BB/U (Weight for Age)
- TB/U (Height for Age)
- BB/TB (Weight for Height)

These are calculated against WHO reference data stored in the `z_score` database table.

### Checkbox Boolean: baca nilainya, bukan keberadaannya

Form di aplikasi ini (mis. `form-section-d`, `form-section-e`) memakai pola hidden+checkbox:

```html
<input type="hidden"   name="gejala_demam" value="0">
<input type="checkbox" name="gejala_demam" value="1">
```

Konsekuensinya field itu **selalu** ada di request. Maka:

- **Pakai `$request->boolean($field)`.** JANGAN `$request->has($field)` / `filled()` / `isset()` — semuanya bernilai true walau checkbox tidak dicentang, sehingga seluruh field tersimpan `1`.
- Berlaku untuk `BOOLEAN_FIELDS` di `SurveillanceRepository` (23 gejala + 8 komplikasi + `riwayat_kontak_kasus`).
- `$request->has()` tetap sah untuk parameter filter/query (pola `has($x) && $x != ''`), bukan untuk checkbox.

Test checkbox **wajib mengirim payload seperti form aslinya** — string `'0'` untuk yang tak dicentang, bukan menghilangkan field-nya. Test yang menghilangkan field hanya memverifikasi skenario yang tak pernah terjadi dan akan lolos walau kodenya salah. Lihat `EpidemiologiControllerTest::test_store_keeps_unchecked_checkboxes_false`.

Latar: bug 2026-03-06 — backend menulis `has()` (benar saat itu, form belum punya hidden input), lalu redesign form 2 menit kemudian menambahkan hidden input dan diam-diam membatalkan asumsinya. Dua perubahan yang masing-masing benar, digabung jadi salah. Ditemukan client 2026-07-21.

### `required` HTML5 + Accordion = Submit Mati Senyap

Form surveilans (`create`/`edit`) adalah accordion single-open (`data-parent`), hanya
section A terbuka default. Panel tertutup ber-`display:none`, dan **browser tidak bisa
mem-fokus kontrol tersembunyi** — bila ada field `required` kosong di panel tertutup,
submit dibatalkan tanpa pesan apa pun. Gejalanya: klik "Simpan", tidak terjadi apa-apa.
Terverifikasi di Chrome: `An invalid form control with name='id_jenis_kasus' is not focusable.`

Penanganannya di `components/form-accordion-validation.blade.php` (di-include kedua form):
`novalidate` + handler submit sendiri yang membuka panel → tunggu `shown.bs.collapse` →
scroll → fokus → `reportValidity()`. **Jangan lepas partial ini** selama masih ada
`required` di panel yang bisa tertutup, dan jangan fokus ke elemen sebelum panelnya terbuka.

Kalau menambah field `required` baru, pastikan ia berada di form yang meng-include partial
tersebut. Test PHPUnit hanya mengunci keberadaan `novalidate` — perilaku fokus/scroll cuma
bisa diuji di browser.
