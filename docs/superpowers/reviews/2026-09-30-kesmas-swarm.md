# Pengujian swarm Kesmas — 30 September 2026

**Status akhir: seluruh delapan temuan KSM-01–KSM-08 sudah diperbaiki dan diverifikasi.**
Hasil terbaru setelah penyelesaian: **190 tes PHP dan 22 skenario browser lulus**;
total **212 skenario unik**, tanpa failure/error tersisa. Migrasi presisi sudah diterapkan
di database pengembangan lokal. Bukti ini dicatat sebelum integrasi ke `main`;
deploy ke produksi mengikuti langkah rilis pada handover.
Rincian perbaikan, bukti run, dan langkah rilis:
[handover Dasbor Kesmas](../plans/2026-09-30-handover-dasbor-kesmas.md).

Bagian berikut mempertahankan **hasil audit awal sebelum perbaikan**, termasuk angka
kegagalan, lokasi kode saat audit, dan langkah reproduksinya.

Status saat audit awal: **pengujian selesai; 8 temuan terkonfirmasi masih terbuka**. Branch
`feat/dasbor-kesmas`, HEAD `7c2a740`, termasuk implementasi lanjutan di working tree.
Sesi ini menambah tes dan dokumentasi; tidak memperbaiki kode aplikasi, melakukan commit,
push, atau deploy.

## Hasil

| Kelompok | Lulus | Gagal | Total |
|---|---:|---:|---:|
| PHPUnit existing Kesmas dan regresi terkait | 161 | 0 | 161 |
| PHPUnit swarm agregat | 7 | 2 | 9 |
| PHPUnit swarm akses dan validasi request | 4 | 0 | 4 |
| PHPUnit swarm data dan export | 9 | 4 | 13 |
| Browser existing | 3 | 0 | 3 |
| Browser swarm | 16 | 3 | 19 |
| **Total skenario unik** | **200** | **9** | **209** |

PHPUnit: **187 tes, 1.341 assertion, 6 failure, 0 error**. Browser Chromium:
**22 skenario unik, 3 failure**. Dua failure Excel berasal dari satu masalah binder
yang terjadi pada dua sheet; karena itu sembilan failure mewakili delapan temuan.
Ada **45 tes/skenario baru** (26 PHPUnit dan 19 browser).

Empat agen bekerja: root menjalankan seluruh PHPUnit secara berurutan dan menguji
otorisasi; tiga subagen menangani agregat, data/export, dan browser. PHPUnit memakai
`sirindu_testing`. Browser memakai sesi superadmin lokal, tidak mengirim form perubahan
anak/kunjungan, dan tidak mengubah data dev.

Hasil browser adalah hasil terbaru per skenario, bukan satu run gabungan. Run awal
15/17 lulus; dua kendala harness (timeout registri dan locator tombol export) diperbaiki.
Run terarah berikutnya menguji ulang keduanya serta dua skenario baru: registri lulus,
tiga bug aplikasi gagal. Tiga skenario existing juga lulus. Tidak ada skenario hijau
yang dihitung dua kali.

## Temuan terkonfirmasi

### KSM-01 · P2 · Angka K4 berbeda dari daftar perhatian setelah koreksi setanggal

- Reproduksi: buat kunjungan anak pada 30 September 2025 dengan `ntob=T`, BB/U `-2.5`,
  lalu entri koreksi pada tanggal sama dengan `ntob=N`, BB/U `0` dan ID lebih besar.
  Buka periode triwulan III 2025.
- Aktual: kartu K4 menghitung **1** anak; registri perhatian menghitung **0**.
  Ekspektasi: keduanya memakai entri terakhir yang sama, sesuai kontrak drilldown spec §3.1.
- Penyebab: `app/Services/KesmasDashboardService.php:196` menggabungkan semua kunjungan
  pada tanggal maksimum, sedangkan registri di `:476` memecahkan tanggal sama dengan ID terbesar.
- Bukti: `tests/Feature/Kesmas/KesmasSwarmAgregatTest.php:76`, termasuk request HTTP kedua endpoint.
- Arah perbaikan: samakan pemilihan kunjungan terakhir, termasuk pemecah seri tanggal.

### KSM-02 · P2 · Filter wilayah hilang ketika membuka Export Data

- Reproduksi lokal: buka dashboard dengan `id_kecamatan=2&id_kelurahan=10&id_puskesmas=1&id_posyandu=59`,
  lalu klik Export Data di kepala dashboard.
- Aktual: query URL diteruskan, tetapi empat select form export kosong. Mengunduh tanpa
  memilih ulang wilayah akan memakai lingkup yang lebih luas. Ekspektasi: pilihan wilayah terisi.
- Penyebab: `app/Http/Controllers/ExportKesmasController.php:30` tidak memuat pilihan dari query;
  `resources/views/admin/export/kesmas.blade.php:33` hanya memakai `old()`, dan dropdown turunan
  baru diisi setelah perubahan manual.
- Bukti: `e2e/kesmas-swarm.spec.ts:179`; screenshot
  `.playwright-mcp/kesmas-swarm-targeted/kesmas-swarm-Export-Data-m-40512-shboard-sampai-form-unduhan-chromium/test-failed-1.png`.
- Arah perbaikan: validasi/pulihkan query ke form dan isi dropdown turunan saat halaman pertama dimuat.

### KSM-03 · P2 · Respons RT lama menimpa pilihan kelurahan terbaru

- Reproduksi browser: tahan respons GET RT kelurahan A, pilih kelurahan B, tunggu RT B
  tampil, kemudian lepaskan respons A.
- Aktual: kelurahan masih B, tetapi dropdown berisi RT A. Ekspektasi: tetap RT B.
- Penyebab: callback `resources/views/admin/kesmas/dashboard.blade.php:152` tidak
  membatalkan permintaan lama atau memeriksa kelurahan aktif.
- Bukti: `e2e/kesmas-swarm.spec.ts:293`; respons GET dummy di browser, tanpa penulisan DB.
  Screenshot: `.playwright-mcp/kesmas-swarm-targeted/kesmas-swarm-cascade-RT-me-0a8c5-telah-pilihan-diganti-cepat-chromium/test-failed-1.png`.
- Arah perbaikan: abaikan respons yang bukan milik pilihan terbaru dan batalkan permintaan sebelumnya.

### KSM-04 · P2 · Berat lahir dua desimal dibulatkan saat disimpan

- Reproduksi: kirim form anak dengan `bbl=2.49` kg. Request diterima tanpa galat validasi.
- Aktual di DB: **2.5**. Ekspektasi berdasarkan input `step="0.01"`: **2.49**.
- Sumber: `resources/views/admin/anak/partials/form-riwayat-lahir.blade.php:28` menerima dua
  desimal, sedangkan `database/migrations/2026_04_08_023341_add_kohort_fields_to_anak_table.php:18`
  mendefinisikan `DECIMAL(6,1)`.
- Bukti: `tests/Feature/Kesmas/KesmasSwarmDataExportTest.php:145`, POST lalu pembacaan nilai DB.
- Arah perbaikan: pertahankan presisi dua desimal melalui migrasi baru. Nilai yang sudah
  dibulatkan tidak dapat dipulihkan hanya dengan memperbesar presisi kolom.
- Temuan ini tentang kehilangan presisi; tidak menyatakan ada perubahan angka BBLR dashboard.

### KSM-05 · P2 · Usia nol bulan menjadi sel kosong pada Excel

- Reproduksi: export kunjungan dengan `bln=0`, lalu baca XLSX yang dihasilkan.
- Aktual: `Per Kunjungan!D2` bernilai `null`; ekspektasi angka **0** dengan tipe numerik.
- Sumber: `app/Exports/KesmasKunjunganSheet.php:20` belum menerapkan `WithStrictNullComparison`;
  `config/excel.php:34` menetapkan perbandingan ketat ke false.
- Bukti: `tests/Feature/Kesmas/KesmasSwarmDataExportTest.php:275`.
- Arah perbaikan: gunakan perbandingan null ketat pada export yang harus membedakan angka nol dan kosong.

### KSM-06 · P2 · Teks input berubah menjadi formula Excel

- Reproduksi: isi `no_id_epus` atau `catatan_pengukuran` dengan teks lokal sederhana `=1+1`,
  lalu export dan baca tipe sel XLSX.
- Aktual: `Per Anak!J2` dan `Per Kunjungan!T2` bertipe formula (`f`), bukan string (`s`).
  Ekspektasi: teks petugas tetap literal.
- Penyebab: binder `app/Exports/KesmasAnakSheet.php:75` dan
  `app/Exports/KesmasKunjunganSheet.php:75` hanya memaksa NIK menjadi string;
  kolom teks lainnya diteruskan ke binder bawaan.
- Bukti: `tests/Feature/Kesmas/KesmasSwarmDataExportTest.php:288` dan `:301`.
  Tidak ada formula eksternal atau percobaan eksekusi berbahaya dalam tes.
- Arah perbaikan: beri tipe string eksplisit pada kolom teks, sambil mempertahankan tipe angka
  pada kolom pengukuran. Ini satu temuan dengan dua tes gagal.

### KSM-07 · P2 · Kepanjangan CKG pada form tidak sesuai dashboard

- Aktual: form menampilkan “Tanggal penanda CKG (cek kesehatan gigi)”.
  Ekspektasi sesuai metrik dashboard: **Cek Kesehatan Gratis**. Pemeriksaan gigi memiliki field tersendiri.
- Sumber: `resources/views/admin/anak/partials/form-layanan-kesmas.blade.php:37`.
- Bukti: `e2e/kesmas-swarm.spec.ts:284`; screenshot
  `.playwright-mcp/kesmas-swarm-targeted/kesmas-swarm-Form-kunjunga-dcf6c-ebagai-Cek-Kesehatan-Gratis-chromium/test-failed-1.png`.
- Arah perbaikan: selaraskan label agar petugas memasukkan tanggal kegiatan yang dimaksud.

### KSM-08 · P3 · Filter puskesmas melewati batas jumlah query

- Reproduksi: kosongkan cache pemetaan wilayah, pilih satu puskesmas, lalu panggil keenam agregat
  dengan sasaran berisi satu anak.
- Aktual: **23 query**; batas spec dashboard §4.4: **maksimal 20**.
  Pengujian existing tanpa filter puskesmas tetap lulus.
- Penyebab: `app/Support/FilterWilayahAnak.php:44` berulang kali mencari nama puskesmas
  ketika membentuk query sasaran.
- Bukti: `tests/Feature/Kesmas/KesmasSwarmAgregatTest.php:247` dengan hitungan query runtime.
- Arah perbaikan: resolusi ID puskesmas ke wilayah sekali per request/operasi, tanpa mengubah
  kontrak AND seluruh filter. Temuan ini tidak membuktikan ambang waktu produksi terlewati.

## Cakupan yang telah diuji

- Seluruh suite Feature Kesmas existing: penyimpanan, presenter, export, dashboard/controller,
  service agregat/registri, Blade, dan memori; unit periode, presenter, wilayah, serta kohort.
- Batas usia 72/73/83/84 bulan dan lahir di masa depan; tujuh periode; prorata; semester SPM
  dibanding triwulan SDIDTK; tanggal penanda CKG dibanding tanggal kunjungan; IDL/IBL menurut tahun.
- Agregat layanan dan sanitasi, sembilan layanan dengan nilai NULL/0/1, batas z-score,
  semua filter wilayah dengan AND, dan catchment puskesmas.
- Seluruh 23 field anak dan 18 field kunjungan: simpan, ubah, kosongkan, tidak dikirim,
  dua cabang edit wilayah, isolasi antar kunjungan, opsi legacy, dan penolakan input invalid.
- Excel dua sheet: filter wilayah, tanggal inklusif/start-only/end-only, label NULL/0/1,
  tipe sel, NIK, dan unduhan XLSX nyata dari browser.
- Matriks delapan variasi peran pada dashboard, registri, form export, dan endpoint unduhan:
  superadmin legacy, admin legacy, role superadmin, imunisasi_faskes, surveilans_puskesmas,
  surveilans_rs, RT, serta pengguna biasa. Tamu, input array tidak valid, dan filter kosong diuji.
- Browser: cascade normal dan nilai setelah Terapkan/Reset; lima chip usia; enam status gizi;
  pencarian nama/NIK/ibu/ayah; paginasi; K4; respons pencarian terlambat; galat transport dan retry;
  escaping HTML; tautan imunisasi; panel create/detail/edit dan kunjungan; lebar 1440 dan 375 px.
- Regresi integrasi imunisasi: service, controller, tahun, dan memori. Export semua data tetap
  diuji batas memorinya. Fixture Kesmas 2.000 anak/12.000 kunjungan lulus kenaikan puncak <16 MB.

## Artefak dan cara mengulang

Tes baru:

- `tests/Feature/Kesmas/KesmasSwarmAgregatTest.php`
- `tests/Feature/Kesmas/KesmasSwarmAksesTest.php`
- `tests/Feature/Kesmas/KesmasSwarmDataExportTest.php`
- `e2e/kesmas-swarm.spec.ts`

Jalankan PHPUnit dalam **satu proses** karena RefreshDatabase menggunakan DB tes bersama:

```powershell
php artisan test tests/Feature/Kesmas/KesmasSwarmAgregatTest.php tests/Feature/Kesmas/KesmasSwarmAksesTest.php tests/Feature/Kesmas/KesmasSwarmDataExportTest.php --compact
```

Browser memerlukan aplikasi lokal aktif dan sesi superadmin valid di
`e2e/.auth/superadmin.json`. Untuk lingkungan tanpa sesi, jalankan setup autentikasi proyek.
Skenario integrasi memakai data dev yang memiliki lebih dari 20 anak dan pilihan wilayah terisi.

```powershell
npx.cmd playwright test e2e/kesmas-dashboard.spec.ts e2e/kesmas-swarm.spec.ts --project=chromium --no-deps --workers=1
```

Bukti lokal (log dan screenshot diabaikan Git):

- `storage/logs/kesmas-swarm-baseline.log` dan `.xml`: 161/161, 987 assertion, 299,29 detik.
- `storage/logs/kesmas-swarm-tambahan.log` dan `.xml`: 20/26, 354 assertion, 117,22 detik.
- `storage/logs/kesmas-swarm-browser-targeted.log`: registri lulus dan tiga temuan browser gagal.
- `storage/logs/kesmas-swarm-browser-existing.log`: ketiga tes existing lulus, 12 detik.
- `.playwright-mcp/kesmas-swarm-targeted/`: screenshot dan konteks kegagalan browser.

Pada sesi audit, tes gagal dipertahankan sebagai reproduksi. Sesi penyelesaian berikutnya
memperbaiki aplikasi dan menjalankan ulang tes tersebut hingga lulus; lihat status akhir di atas.

## Batas verifikasi dan catatan kontrak

- Ini pengujian dev dan sintetis, bukan bukti seluruh kombinasi input bebas cacat.
  Target performa produksi 10.000 anak/100.000 kunjungan belum diukur; gerbang verifikasi
  angka kohort pada salinan produksi tetap berlaku.
- Browser hanya Chromium; Safari/iOS dan browser lain belum diuji. Submit form diuji melalui
  Feature test di DB tes, bukan dengan perubahan data dev melalui browser.
- Ada pesan CSP vendor lama (`core.js` meminta `jquery.mousewheel` melalui CDN HTTP), juga
  muncul pada dashboard imunisasi. Ini bukan temuan baru Kesmas; tidak menyatakan seluruh console bersih.
- Satu bagian spec Data Kesmas menyebut berat lahir dalam gram, tetapi rencana implementasi,
  UI, dan export memakai kg. Temuan KSM-04 mengikuti kontrak UI dua desimal tersebut.
  Pada sesi penyelesaian, salah tulis dalam spec diperbaiki menjadi kg dengan dua desimal.
- Export memakai `anak.id_puskesmas`; dashboard memakai catchment kelurahan. Masing-masing
  sesuai kontrak modulnya saat ini. Penyamaan semantik lintas modul memerlukan keputusan terpisah
  dan tidak dihitung sebagai bug kesembilan.
- Akses se-kota `imunisasi_faskes` sesuai spec yang berlaku; pengujian tidak mengubah kebijakan peran.

Handover implementasi: [Dasbor Kesmas](../plans/2026-09-30-handover-dasbor-kesmas.md).
