# Laporan Audit Aplikasi -- SIRINDU — modul Import Data Kesmas (branch feat/import-kesmas)

**Tanggal audit**: 2026-10-15
**Fase selesai**: static, dinamis
**Status gate**: ✅ Gate lolos -- tidak ada temuan Kritis.
**Catatan Fase 2**: Dijalankan di app lokal http://sirindu.test (branch feat/import-kesmas, DB dev) lewat Claude in Chrome: tab/fokus/keyboard/aria-live, unggah CSV ditolak, unggah dua berkas Excel uji (anak fiktif & header salah) diproses queue:work lalu log & berkas uji dihapus. Tidak ada payload eksploitasi. Playwright MCP gagal tersambung; tidak diuji reflow 320px.

## Ringkasan Eksekutif

Total temuan: **52** dari 4 track.

| Track | 🔴 Kritis | 🟠 Tinggi | 🟡 Sedang | 🔵 Rendah | ⚪ Info | Total |
|---|---|---|---|---|---|---|
| Aksesibilitas | 0 | 2 | 6 | 5 | 2 | 15 |
| Keamanan | 0 | 0 | 2 | 3 | 7 | 12 |
| UI/UX | 0 | 3 | 7 | 7 | 3 | 20 |
| Persyaratan (Requirements Conformance) | 0 | 0 | 0 | 3 | 2 | 5 |
| **Total** | **0** | **5** | **15** | **18** | **14** | **52** |

## Detail Temuan

## 🟠 Severity: Tinggi

### [A11Y-001] Fokus keyboard tidak terlihat pada tab, tombol filter, tombol Upload, dan tautan template
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:50 (juga :95, :165, :198, :211, :266; sumber: public/admin/vendors/styles/style.css)`
- **Deskripsi**: Stylesheet global admin (public/admin/vendors/styles/style.css) memuat `:focus{outline:0}` dan `a,button,input{outline:0!important}`. Halaman Import tidak menambahkan pengganti `:focus-visible` (berbeda dengan dasbor .im-* dan .km-* yang sudah memasangnya), sehingga tab 'Data Kesmas', tombol filter 'Kesmas', tombol 'Upload & Import', 'Unduh Template Excel', tombol refresh, serta tombol Error/Ulang/Hapus tidak menunjukkan posisi fokus sama sekali. Hanya zona unggah yang punya penanda lewat `:focus-within`. Masalah ini sudah ada di halaman; panel Kesmas mewarisinya.
- **Rekomendasi**: Tambahkan di blok <style> halaman: `.imp-page :is(a,button,select,input,[tabindex]):focus-visible{outline:3px solid oklch(0.36 0.13 145)!important;outline-offset:2px}` (perlu !important karena aturan global memakai !important), mengikuti pola dasbor-base.css baris 30. Untuk .imp-tab-btn.is-active yang berlatar hijau, outline gelap + offset agar tetap kontras >=3:1 terhadap latar bilah tab.
- **Referensi**: WCAG 2.1 SC 2.4.7
- **Kepastian**: Eksplisit

### [A11Y-002] Galat validasi unggahan (format/ukuran berkas) tidak pernah ditampilkan
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:306-318; app/Http/Controllers/ImportCsvController.php:119-130`
- **Deskripsi**: handleUpload() memakai $request->validate() dengan pesan Indonesia yang baik ('File harus berformat Excel (.xlsx/.xls).', 'Ukuran file maksimal 20 MB.'), tetapi view Import dan layout admin tidak merender $errors/@error di mana pun. Bila validasi gagal, petugas dikembalikan ke halaman tanpa pesan apa pun, dan tab aktif kembali ke 'Data Anak'. Panel Kesmas paling rentan karena hanya menerima Excel (CSV ditolak) sementara kebanyakan tab lain menerima CSV — petugas yang menyimpan berkas Kesmas sebagai CSV mengira unggahan hilang tanpa jejak. Masalah dasar sudah ada sebelumnya; panel Kesmas memperbesar peluangnya.
- **Rekomendasi**: Render `$errors->all()` di blok flash (role="alert") di atas tab, dan pertahankan tab asal (hidden input `tab` atau hash URL) supaya setelah redirect panel Kesmas yang terbuka dan pesan terlihat di dekat form. Idealnya tautkan pesan ke input berkas via aria-describedby + aria-invalid.
- **Referensi**: WCAG 2.1 SC 3.3.1, 3.3.3
- **Kepastian**: Eksplisit

### [DYN-UX-001] Import Kesmas 1–2 baris memakan 40–54 detik, juga saat tidak ada yang tersimpan
- **Track**: UI/UX (dinamis)
- **Lokasi**: `app/Jobs/ImportKesmasJob.php:38-39`
- **Deskripsi**: Diuji di app lokal (sirindu.test, DB dev 2.850 anak): berkas berisi satu anak fiktif butuh 54 detik dan berkas dengan header salah (0 baris diproses) 40 detik. Penyebabnya ImportKesmasJob selalu memanggil PrioritasGiziService::refreshAll() se-kota setelah import, walau tak ada kunjungan yang ditulis. Di prod (QUEUE_CONNECTION=sync, ±10 rb anak) job berjalan di dalam request upload, sehingga petugas menatap spinner 'Mengunggah…' satu menit lebih — dan berisiko kena batas waktu web server ('This page isn't working' tanpa jejak, pola yang sudah terjadi pada export).
- **Rekomendasi**: Jalankan refreshAll() hanya bila import menulis kunjungan (atau refresh hanya id anak yang tersentuh, pakai refreshAnak per id). Verifikasi: unggah berkas header salah, ukur durasi job (queue:work) — harus < 2 detik.
- **Referensi**: Nielsen #1 - Visibility of System Status; terkonfirmasi dinamis
- **Kepastian**: Terverifikasi manual

### [UX-001] Berkas dengan header salah tampil sebagai 'Selesai', Berhasil 0, Gagal 0
- **Track**: UI/UX (static)
- **Lokasi**: `app/Imports/KesmasImport.php:125-130; app/Jobs/ImportKesmasJob.php:42-48; resources/views/admin/import/index.blade.php:740-755`
- **Deskripsi**: Bila kolom wajib (nama_anak, jk, tgl_lahir, nama_ortu) tidak ada di header, bacaHeader() mencatat pesan [ERROR] dan menghentikan import tanpa menaikkan $gagal. Job lalu menulis status 'done' dengan success_count 0 dan failure_count 0, sehingga riwayat menampilkan badge hijau 'Selesai' dan angka Gagal '0' (tidak merah). Satu-satunya tanda bahwa tidak ada yang diproses adalah tombol 'Error' yang harus diklik. Ini paling mungkin terjadi pada petugas yang memakai berkas sendiri atau mengganti judul kolom dengan label yang lebih mudah dibaca.
- **Rekomendasi**: Untuk header yang tidak sah, tandai log sebagai 'failed' (lempar exception yang ditangkap job, atau kembalikan flag dari getResults()) supaya badge berwarna merah 'Gagal', atau setidaknya hitung semua baris data sebagai gagal. Tulis pesan yang langsung bisa ditindaklanjuti, misalnya: 'Judul kolom tidak sesuai template. Unduh ulang Template Excel dan salin data Anda ke sheet Data.'
- **Referensi**: Nielsen #1 Visibility of System Status; #9 Help Users Recognize, Diagnose, and Recover from Errors
- **Kepastian**: Eksplisit

### [UX-002] Galat validasi unggahan (bukan Excel, lebih dari 20 MB) tidak pernah tampil
- **Track**: UI/UX (static)
- **Lokasi**: `app/Http/Controllers/ImportCsvController.php:119-131; resources/views/admin/import/index.blade.php:306-318, 842-854`
- **Deskripsi**: handleUpload() memakai $request->validate() dengan pesan Bahasa Indonesia ('File harus berformat Excel (.xlsx/.xls).'). Namun halaman import dan layout admin tidak merender $errors sama sekali; yang dirender hanya flash 'import_queued' dan 'error'. Berkas CSV yang diseret ke zona Kesmas lolos dari atribut accept, karena handler drop memasang inp.files = files tanpa memeriksa ekstensi. Akibatnya halaman hanya memuat ulang tanpa pesan, padahal tab lain menerima CSV dan bertuliskan 'Unduh Template CSV', sehingga petugas wajar mengira CSV juga diterima di sini.
- **Rekomendasi**: Render $errors->all() di blok flash halaman import (role=alert). Saat drop, periksa ekstensi terhadap atribut accept dan langsung beri tahu petugas, misalnya 'Import Data Kesmas hanya menerima Excel (.xlsx/.xls)'. Pertimbangkan juga membuka kembali tab asal setelah redirect (lihat UX-013).
- **Referensi**: Nielsen #1 Visibility of System Status; #5 Error Prevention
- **Kepastian**: Eksplisit


## 🟡 Severity: Sedang

### [A11Y-003] Pola tab ARIA tidak lengkap: tidak ada navigasi panah dan roving tabindex
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:810-821 (markup :332-368)`
- **Deskripsi**: Tab bar sudah memakai role=tablist/tab/tabpanel, aria-selected, dan aria-controls (tab Kesmas benar: id=tab-kesmas <-> panel-kesmas, aria-labelledby cocok). Namun skrip hanya menangani klik: tidak ada handler panah kiri/kanan/Home/End dan semua tab tetap di urutan Tab, padahal pembaca layar mengumumkan 'tab' sehingga pengguna mengharapkan panah. Dengan tab ke-7 (Kesmas), pengguna keyboard menekan Tab 7 kali untuk melewati bilah tab. Ini berbeda dari keputusan audit dasbor di CLAUDE.md ('Tab = pola ARIA ... panah kiri/kanan'). Sudah ada sebelumnya.
- **Rekomendasi**: Terapkan roving tabindex (tab aktif tabindex=0, lainnya -1) dan keydown ArrowLeft/ArrowRight/Home/End yang memindahkan fokus + mengaktifkan tab; boleh pakai ulang perilaku di public/js/dasbor-a11y.js. Tambahkan tabindex="0" pada .imp-panel.
- **Referensi**: WCAG 2.1 SC 4.1.2, 2.1.1 (ARIA APG Tabs)
- **Kepastian**: Eksplisit

### [A11Y-004] Perubahan status import (Menunggu -> Diproses -> Selesai/Gagal) tidak diumumkan
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:711, 953-968, 1017`
- **Deskripsi**: Riwayat import diperbarui lewat polling tiap 5 detik dengan mengganti innerHTML #imp-log-container, tetapi wadah itu tidak punya aria-live/role=status. Pengguna pembaca layar tidak tahu import Kesmas (berjalan di antrean) sudah selesai atau gagal, maupun jumlah berhasil/gagal. Kegagalan fetch juga diabaikan diam-diam. Sudah ada sebelumnya; berlaku sama untuk tipe Kesmas.
- **Rekomendasi**: Tambahkan region terpisah `<div class="sr-only" role="status" aria-live="polite">` dan isi kalimat ringkas hanya saat status sebuah log berubah (mis. 'Import Kesmas file X selesai: 120 berhasil, 3 gagal'). Jangan jadikan seluruh tabel live region (terlalu bising).
- **Referensi**: WCAG 2.1 SC 4.1.3
- **Kepastian**: Eksplisit

### [A11Y-005] Polling membangun ulang tabel: fokus hilang dan panel error yang dibuka tertutup sendiri
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:986-1020 (bandingkan :760-781)`
- **Deskripsi**: Selama ada import pending/processing (kondisi normal setelah mengunggah Kesmas), renderLogs() mengganti seluruh tabel tiap 5 detik. Tombol yang sedang difokus (Error/Ulang/Hapus) dihancurkan sehingga fokus keyboard terlempar ke awal dokumen, dan baris .imp-err-row yang sedang dibaca ikut hilang. Baris hasil render JS juga kehilangan aria-label/title tombol Hapus dan Error yang ada di versi Blade. Sudah ada sebelumnya.
- **Rekomendasi**: Perbarui sel per baris berdasarkan data-log-id (status, angka, aksi) alih-alih mengganti tabel; pertahankan baris error yang terbuka dan elemen fokus. Samakan atribut aksesibilitas tombol di renderLogs() dengan versi Blade (aria-label 'Hapus log import', aria-expanded pada tombol Error).
- **Referensi**: WCAG 2.1 SC 2.4.3, 3.2.2
- **Kepastian**: Inferensi

### [A11Y-006] Tombol filter riwayat (termasuk 'Kesmas') tidak mengumumkan status terpilih; pembeda visual hampir hanya warna teks
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:697-703, 206-209, 868-876`
- **Deskripsi**: Tombol filter tidak punya aria-pressed, jadi pembaca layar tidak tahu filter mana aktif. Secara visual, status aktif hanya mengubah warna teks (#4f544f -> #157123) dengan latar yang praktis sama (rasio latar aktif vs tidak aktif 1,01:1); gaya :hover identik dengan .is-active sehingga tombol yang di-hover tampak terpilih. Tombol 'Kesmas' baru mewarisi pola ini. Tidak ada filter untuk tipe 'pj' meski log PJ dirender.
- **Rekomendasi**: Tambah aria-pressed="true|false" (diperbarui di handler klik) dan bungkus dalam role="group" aria-label="Saring riwayat menurut tipe". Bedakan status aktif secara non-warna: latar hijau solid + teks putih (seperti tab) atau ikon centang; pisahkan gaya :hover dari .is-active.
- **Referensi**: WCAG 2.1 SC 4.1.2, 1.4.1
- **Kepastian**: Eksplisit

### [A11Y-007] Ikon Material Symbols terbaca sebagai teks Inggris di nama aksesibel
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:365, 659, 668, 679, 704-706, 997-998`
- **Deskripsi**: Ikon memakai ligatur teks (<span class="material-symbols-outlined">health_and_safety</span>) tanpa aria-hidden, sehingga nama tab dibaca 'health_and_safety Data Kesmas', zona unggah 'cloud_upload Klik atau seret file Excel ke sini ...', tombol 'upload Upload & Import', tautan 'download Unduh Template Excel'. Tombol refresh hanya berisi ikon + title sehingga namanya 'refresh'; tombol hapus hasil render JS namanya 'delete'. Panel Kesmas mengulang pola yang sudah ada.
- **Rekomendasi**: Tambahkan aria-hidden="true" pada setiap span ikon dekoratif. Beri tombol refresh aria-label="Muat ulang riwayat import" dan tombol hapus versi JS aria-label="Hapus log import".
- **Referensi**: WCAG 2.1 SC 1.1.1, 4.1.2
- **Kepastian**: Eksplisit

### [A11Y-008] Template Excel: sheet 'Data' hanya berisi kode kolom teknis; label, satuan, dan status wajib tersembunyi di sheet lain
- **Track**: Aksesibilitas (static)
- **Lokasi**: `app/Exports/TemplateKesmas.php:71-90, 91-105`
- **Deskripsi**: Baris judul sheet 'Data' berisi kode seperti nik_anak, tgl_kunjungan, zscore_bb_pb, tb_cm, dan kode kolom Kesmas (snake_case), tanpa penanda kolom wajib. Label ('Tinggi/panjang badan (cm)'), status Wajib/Opsional, dan satuan (gram vs kg) hanya ada di sheet 'Petunjuk', jadi petugas harus bolak-balik dua sheet untuk tiap kolom; risiko nyata BB diisi gram/kg terbalik atau kolom wajib terlewat. Dropdown tidak memakai input message, sehingga tak ada petunjuk saat sel dipilih. Baru dibawa fitur Kesmas.
- **Rekomendasi**: Tanpa mengubah baris judul yang dibaca importer: tambahkan Comment/Note di tiap sel judul berisi label + keterangan ($data->getComment(...)), warnai latar judul kolom wajib dan beri legenda di Petunjuk, serta aktifkan setShowInputMessage(true)+setPrompt() pada kolom berpilihan dan kolom bb ('gram bila < 2 bulan, selain itu kg'). Aktifkan wrap text pada kolom Keterangan di Petunjuk.
- **Referensi**: Praktik UX literasi digital (terkait WCAG 2.1 SC 3.3.2)
- **Kepastian**: Eksplisit

### [SEC-001] Penautan lewat NIK menerima anak lain hanya karena tanggal lahir sama (nama, jk, nama ortu tak dicek) — tanpa pesan apa pun
- **Track**: Keamanan (static)
- **Lokasi**: `app/Services/PenautanAnakKesmas.php:42-46`
- **Deskripsi**: Di jalur NIK, `$cocok = $lahirAda === $tglLahir || mirip(nama)` lalu baris langsung ditautkan ke pemilik NIK dengan `pesan => []`. Bila NIK di berkas (salah ketik/tertukar) kebetulan milik anak lain yang lahir di hari yang sama, dan anak yang sebenarnya BELUM ada di DB (sehingga `adaSaudaraLebihCocok()` tak menemukan pembanding), data Kesmas dan kunjungan baru (BB/TB) menempel ke anak yang salah walau nama, jenis kelamin, dan nama ortu berbeda total — tanpa INFO/PERINGATAN di riwayat. Ini risiko integritas data kesehatan (salah pasien senyap), bukan bypass akses.
- **Rekomendasi**: Pada jalur NIK, syaratkan minimal jk sama DAN (nama mirip ATAU nama ortu cocok) selain tgl lahir; bila hanya tgl lahir yang cocok, perlakukan sebagai NIK milik anak lain (jatuh ke pencocok nama) atau setidaknya catat PERINGATAN berisi nama di berkas vs nama tertaut. Verifikasi manual: baca ulang cabang `if ($nikSah)` dan pastikan tak ada jalur `return ['anak'=>$ada,'pesan'=>[]]` yang hanya bergantung pada tgl lahir; tambah test: anak X (NIK n, lahir d, 'BUDI', L) di DB, berkas berisi NIK n + 'SITI' P lahir d → harus ERROR/peringatan, bukan tertaut diam-diam.
- **Referensi**: OWASP A04:2021 Insecure Design / A08:2021 Software and Data Integrity Failures
- **Kepastian**: Inferensi

### [SEC-002] Dengan QUEUE_CONNECTION=sync (prod), import berjalan di dalam request HTTP: timeout meninggalkan data setengah jalan dan log macet 'processing'
- **Track**: Keamanan (static)
- **Lokasi**: `app/Http/Controllers/ImportCsvController.php:146-161; app/Jobs/ImportKesmasJob.php:38-39`
- **Deskripsi**: `handleUpload()` memanggil `ImportKesmasJob::dispatch()`; pada driver sync (default `.env.example` dan tercatat di memori proyek sebagai konfigurasi prod) job — termasuk `PrioritasGiziService::refreshAll()` se-kota — dieksekusi sinkron dalam request web, sehingga `$timeout = 600` job tidak berlaku dan yang berlaku `max_execution_time`/timeout proxy. Tiap baris ditulis dalam transaksi sendiri, jadi bila proses dibunuh di tengah, sebagian baris sudah tersimpan, `refreshAll()` tak jalan, dan ImportLog tetap 'processing' sehingga `destroyLog()` menolaknya (422) selamanya. Berkas Excel 20 MB (ribuan baris; pembacaan chunk mem-parse ulang XML) memperbesar peluangnya; pemicunya hanya superadmin, jadi dampaknya ketersediaan/integritas, bukan eksploitasi eksternal.
- **Rekomendasi**: Jalankan worker queue database/redis di prod (rencana otomasi worker sudah ada) atau batasi jumlah baris per berkas untuk mode sync; sediakan cara menandai log 'processing' yang basi sebagai gagal (mis. berdasar started_at > 1 jam). Verifikasi manual: cek `QUEUE_CONNECTION` di .env server yang melayani petugas, `php -i | grep max_execution_time`, dan `SELECT id,status,started_at FROM import_logs WHERE status IN ('pending','processing')` untuk log macet.
- **Referensi**: OWASP A04:2021 Insecure Design (availability); A05:2021 Security Misconfiguration
- **Kepastian**: Inferensi

### [UX-003] Peringatan dan info disajikan sebagai 'Error' / 'Baris bermasalah'
- **Track**: UI/UX (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:759-764, 914, 988-991; app/Models/ImportLog.php:30-52`
- **Deskripsi**: Importer Kesmas banyak menghasilkan [PERINGATAN] (kolom dilewati, ukuran lama dipertahankan, kolom tak dikenal) dan [INFO] (dicocokkan lewat nama) untuk baris yang tetap tersimpan. Semuanya muncul di balik tombol berlabel 'Error' berikon warning, di panel merah berjudul 'Baris bermasalah:'. Tombol itu tetap muncul walau Gagal = 0. Petugas bisa mengira import gagal lalu mengunggah ulang, atau justru terbiasa mengabaikan panel itu sehingga ERROR yang sungguhan ikut terlewat.
- **Rekomendasi**: Beri label tombol netral ('Detail' atau 'Catatan (n)'), kelompokkan isi panel per tingkat dengan subjudul dan jumlahnya ('Gagal disimpan: 3', 'Tersimpan dengan catatan: 12', 'Info: 40'), dan beri warna berbeda per tingkat. Gunakan warna merah hanya bila ada ERROR.
- **Referensi**: Nielsen #2 Match Between System and the Real World; #1 Visibility of System Status
- **Kepastian**: Eksplisit

### [UX-004] Berhasil + Gagal tidak sama dengan jumlah baris; baris 'tidak disimpan' tak terhitung
- **Track**: UI/UX (static)
- **Lokasi**: `app/Imports/KesmasImport.php:231-235; resources/views/admin/import/index.blade.php:719-727, 1019`
- **Deskripsi**: Baris yang tidak menulis apa pun (peringatan 'tidak ada isian Kesmas atau kunjungan — tidak ada yang disimpan') tidak dihitung sebagai Berhasil maupun Gagal. Baris yang tersimpan tetapi sebagian kolomnya dilewati tetap dihitung Berhasil. Tabel riwayat hanya punya kolom Berhasil/Gagal, sehingga petugas yang mencocokkan dengan jumlah baris di Excel menemukan selisih tanpa penjelasan. Ini pasti terjadi pada setiap 'Ulang' atas berkas yang sama.
- **Rekomendasi**: Tambahkan hitungan ketiga ('Tanpa perubahan' atau 'Dilewati') ke hasil dan tampilkan di riwayat, atau tampilkan ringkasan di baris pertama failures, misalnya 'Dibaca 250 baris: 230 tersimpan (12 dengan catatan), 8 tanpa perubahan, 12 gagal'.
- **Referensi**: Nielsen #1 Visibility of System Status
- **Kepastian**: Eksplisit

### [UX-005] Satuan berat tidak konsisten di template yang sama: bb gram (<2 bln) vs bbl kg
- **Track**: UI/UX (static)
- **Lokasi**: `app/Exports/TemplateKesmas.php:31, 51; app/Services/KolomKesmasImport.php:35; resources/views/admin/import/index.blade.php:654-657; app/Imports/KesmasImport.php:341-350`
- **Deskripsi**: Di satu template, kolom bb wajib dalam GRAM untuk bayi di bawah 2 bulan, sedangkan bbl (berat lahir, kolom di ujung kanan) wajib dalam KG dan menolak gram. Keduanya berat bayi baru lahir yang di Buku KIA tercatat dalam gram. Petugas juga harus menghitung sendiri umur anak pada tanggal kunjungan untuk memilih satuan. Pesan penolakannya sudah jelas, tetapi beban kognitifnya tinggi dan baris kunjungan baru dibatalkan seluruhnya bila bb salah satuan. Panduan di panel hanya menyebut aturan bb, tidak menyebut bbl.
- **Rekomendasi**: Karena rentang gram (300–8000) dan kg (1–150) tidak beririsan, pertimbangkan dua kolom eksplisit (bb_gram / bb_kg), atau setidaknya beri judul kolom yang memuat satuannya. Tambahkan catatan bbl = kg di panel panduan, dan beri komentar sel (cell note) di header bb dan bbl pada sheet Data.
- **Referensi**: Nielsen #5 Error Prevention; #4 Consistency and Standards
- **Kepastian**: Eksplisit

### [UX-006] Header sheet Data hanya kode teknis; arti kolom harus dicari di sheet lain
- **Track**: UI/UX (static)
- **Lokasi**: `app/Exports/TemplateKesmas.php:71-89, 43-59`
- **Deskripsi**: Baris 1 sheet Data berisi kunci teknis (nik_anak, tb_cm, tgl_hbig, skrining_shak, usia_kehamilan_lahir, dan sebagainya), sementara label manusiawi dan tanda wajib hanya ada di sheet Petunjuk. Dengan puluhan kolom, petugas harus bolak-balik antar-sheet untuk tahu arti tiap kolom dan kolom mana yang wajib. Kolom per anak (riwayat lahir) diletakkan paling kanan, setelah semua kolom layanan kunjungan, sehingga baris 'hanya data per anak' perlu menggulir jauh.
- **Rekomendasi**: Pasang komentar sel (label, satuan, wajib) pada tiap header, atau beri warna header per kelompok (Identitas–wajib, Ukur kunjungan, Layanan kunjungan, Data per anak). Header tetap kunci teknis karena importer membacanya. Pertimbangkan juga menaruh kelompok 'Data per anak' sebelum layanan kunjungan.
- **Referensi**: Nielsen #6 Recognition Rather Than Recall
- **Kepastian**: Eksplisit

### [UX-007] Tanggal lahir salah format dilaporkan sebagai 'kosong atau tidak terbaca' tanpa nilai dan sebab
- **Track**: UI/UX (static)
- **Lokasi**: `app/Imports/KesmasImport.php:186-195`
- **Deskripsi**: Bila tgl_lahir gagal dibaca, misalnya petugas memilih Bulan/Hari/Tahun padahal berkas berisi 25/01/2023, pesannya 'kolom wajib kosong atau tidak terbaca: tgl_lahir — baris dilewati'. Pesan ini tidak menyebut nilai yang dibaca dan tidak menyebut pilihan format tanggal. Sebaliknya, tgl_kunjungan yang gagal dibaca menampilkan nilainya. Petugas sulit membedakan sel yang memang kosong dari pilihan format yang salah.
- **Rekomendasi**: Pisahkan 'kosong' dari 'tidak terbaca', lalu sertakan nilainya dan saran perbaikannya, misalnya: "tgl_lahir '25/01/2023' tidak terbaca dengan format Bulan/Hari/Tahun — periksa pilihan Format tanggal saat mengunggah".
- **Referensi**: Nielsen #9 Help Users Recognize, Diagnose, and Recover from Errors
- **Kepastian**: Eksplisit

### [UX-008] Pesan format tanggal ambigu menyuruh 'Ulangi import', tetapi tombol 'Ulang' memakai format yang sama
- **Track**: UI/UX (static)
- **Lokasi**: `app/Exceptions/FormatTanggalAmbigu.php:17-19; app/Http/Controllers/ImportCsvController.php:198-199; resources/views/admin/import/index.blade.php:766-774`
- **Deskripsi**: FormatTanggalAmbigu meminta petugas 'Ulangi import dan pilih Hari/Bulan/Tahun atau Bulan/Hari/Tahun'. Tombol 'Ulang' di riwayat menyalin format_tanggal dari log lama (Otomatis) dan tidak menyediakan pilihan format, sehingga hasilnya pasti gagal lagi dengan pesan yang sama. Pesan ini juga tampil diawali 'Import gagal: ' di job Kesmas.
- **Rekomendasi**: Ubah kalimatnya menjadi 'Unggah ulang berkas di tab Data Kesmas dan pilih ...'. Alternatifnya, untuk log yang gagal karena ambigu, sediakan pilihan format pada aksi Ulang.
- **Referensi**: Nielsen #9 Help Users Recognize, Diagnose, and Recover from Errors; #3 User Control and Freedom
- **Kepastian**: Eksplisit

### [UX-009] Kegagalan tingkat job menampilkan pesan teknis mentah
- **Track**: UI/UX (static)
- **Lokasi**: `app/Jobs/ImportKesmasJob.php:34, 51-56, 62-65`
- **Deskripsi**: Bila import gagal total, failures berisi 'Import gagal: ' ditambah $e->getMessage() mentah, misalnya pesan PhpSpreadsheet/SQL berbahasa Inggris atau 'File tidak ditemukan: imports/kesmas/xxxx.xlsx' yang memuat path storage, dan 'Job gagal dieksekusi: …'. Galat per baris sudah disaring lewat ImportError::message, tetapi galat tingkat job tidak.
- **Rekomendasi**: Lewatkan pesan job melalui ImportError::message (atau pemetaan serupa) dan pertahankan pesan yang memang ditulis untuk petugas, seperti FormatTanggalAmbigu. Ganti 'Job gagal dieksekusi' dengan bahasa awam, misalnya 'Import terhenti karena kesalahan sistem — hubungi admin; berkas tidak berubah.'
- **Referensi**: Nielsen #9 Help Users Recognize, Diagnose, and Recover from Errors; #2 Match Between System and the Real World
- **Kepastian**: Eksplisit


## 🔵 Severity: Rendah

### [A11Y-009] Teks panduan panel Kesmas memakai jargon dan singkatan tanpa penjelasan
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:645-657, 680`
- **Deskripsi**: Panel memakai 'Field Kesmas', 'nama ortu', 'kandidat', singkatan KN, MTBM/MTBS, CKG, MBG tanpa kepanjangan, dan tombol 'Upload & Import' (Inggris, sudah ada di tab lain). Panduan menyebut anak tak ketemu 'dilaporkan' tetapi tidak menyebut di mana (tombol 'Error' di Riwayat Import). Pengguna halaman ini superadmin (handleUpload membatasi), sehingga dampaknya lebih kecil, tetapi petugas non-teknis tetap terbantu.
- **Rekomendasi**: Ganti 'Field Kesmas anak' -> 'Data Kesmas per anak', 'nama ortu' -> 'nama ibu/orang tua', 'lebih dari satu kandidat' -> 'cocok dengan lebih dari satu anak'; tulis kepanjangan singkatan atau rujuk ke sheet Petunjuk; tambahkan 'Hasilnya, termasuk baris yang dilewati, bisa dilihat di Riwayat Import di bawah'. Tombol: 'Unggah & Impor'.
- **Referensi**: Praktik UX literasi digital
- **Kepastian**: Eksplisit

### [A11Y-010] Tombol 'Error' juga dipakai untuk daftar PERINGATAN import Kesmas
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:760-763, 897-917, 990-991; app/Imports/KesmasImport.php:181-183`
- **Deskripsi**: KesmasImport mencatat [ERROR] dan [PERINGATAN] (mis. 'kunjungan ... sudah ada — ukuran tidak diubah') ke daftar yang sama; UI menampilkannya lewat tombol berlabel 'Error' dan judul 'Baris bermasalah:'. Import yang berhasil dengan catatan informatif tampak seperti gagal bagi pengguna awam. Tombol juga tak punya aria-expanded untuk panel yang di-toggle (sudah ada sebelumnya).
- **Rekomendasi**: Ganti label menjadi 'Lihat catatan', judul panel 'Catatan per baris (ERROR = baris dilewati, PERINGATAN = sebagian disimpan)', dan tambahkan aria-expanded + aria-controls pada tombol.
- **Referensi**: Praktik UX literasi digital; WCAG 2.1 SC 4.1.2
- **Kepastian**: Eksplisit

### [A11Y-011] Target sentuh kecil pada filter, refresh, dan tombol aksi baris
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:198-219, 266-273`
- **Deskripsi**: Tombol filter dan refresh tingginya 32px, tombol aksi baris (Error/Ulang/Hapus) sekitar 22-24px dengan ikon 13px, dan tombol Hapus hanya ikon. Di bawah anjuran ~44x44px untuk pengguna dengan presisi jari terbatas. WCAG 2.1 AA tidak mewajibkan ukuran target (2.5.5 adalah AAA). Sudah ada sebelumnya; tombol filter 'Kesmas' mewarisinya.
- **Rekomendasi**: Naikkan min-height ke 40-44px untuk @media (pointer:coarse), beri jarak >=8px antar tombol aksi, dan tambahkan label teks 'Hapus' pada tombol hapus.
- **Referensi**: Praktik UX literasi digital (WCAG 2.1 SC 2.5.5, AAA)
- **Kepastian**: Eksplisit

### [A11Y-012] Teks bantuan format tanggal tidak tertaut ke select
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/partials/pilihan-format-tanggal.blade.php:14-22`
- **Deskripsi**: Label 'Format tanggal di berkas' terhubung benar via for/id (id unik format-tanggal-kesmas), tetapi teks bantuan di <small> tidak dihubungkan dengan aria-describedby, sehingga pembaca layar tidak membacakannya saat select difokus. Kalimat 'Pilih tegas bila ...' dan 'sel tanggal Excel' kurang akrab bagi pengguna awam. Ukuran 0,8rem (12,8px) kecil; kontras #6c757d di putih 4,69:1 (lolos).
- **Rekomendasi**: Beri <small id="{{ $id }}-bantuan"> dan aria-describedby pada <select>. Sederhanakan: 'Biarkan Otomatis, kecuali tanggal di berkas ditulis seperti 05/02/2025 — untuk itu pilih urutannya (Hari/Bulan/Tahun atau Bulan/Hari/Tahun).'
- **Referensi**: WCAG 2.1 SC 1.3.1; Praktik UX literasi digital
- **Kepastian**: Eksplisit

### [A11Y-013] Tab aktif selalu kembali ke 'Data Anak' setelah unggah; struktur heading panel lemah
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:326, 333-334, 373, 644; resources/views/vendor/admin/layouts/app.blade.php:393`
- **Deskripsi**: Kelas is-active dan aria-selected=true dikodekan statis pada tab 'Data Anak', sehingga setelah redirect unggahan Kesmas petugas melihat panel Data Anak (pesan antrean tampil, tapi konteks tab hilang). Selain itu halaman punya dua <h1> 'Import Data' (layout .page-title dan .imp-header), dan judul 'Data yang diimpor' tiap panel berupa <p>, bukan heading. Sudah ada sebelumnya.
- **Rekomendasi**: Simpan tab aktif (hash URL atau session flash 'tab') dan pulihkan saat load. Ubah h1 di .imp-header menjadi elemen non-heading atau hapus duplikatnya, dan jadikan 'Data yang diimpor' <h2> di tiap panel.
- **Referensi**: WCAG 2.1 SC 1.3.1, 2.4.6; Praktik UX literasi digital
- **Kepastian**: Eksplisit

### [SEC-003] Pesan riwayat import menyimpan permanen NIK, nama, dan tanggal lahir anak LAIN
- **Track**: Keamanan (static)
- **Lokasi**: `app/Services/PenautanAnakKesmas.php:48-50, 58-59`
- **Deskripsi**: Saat NIK berkas tercatat untuk anak lain, pesan berbunyi "NIK {nik} tercatat untuk anak lain ({nama}, lahir {tgl})"; pesan INFO juga menyebut NIK anak tertaut. Pesan disimpan di `import_logs.failures` tanpa batas waktu dan dikirim ulang lewat endpoint `importStatus`. Pembacanya hanya superadmin (berakses se-kota), jadi tidak ada kebocoran lintas peran; risikonya minimisasi data (UU PDP) — data anak lain tersalin ke tabel log yang tak ikut terhapus bila anak dihapus/digabung.
- **Rekomendasi**: Sebut identitas minimal (mis. id anak atau nama tersamar + 4 digit akhir NIK) dan/atau terapkan retensi/pembersihan import_logs lama. Verifikasi manual: `SELECT failures FROM import_logs WHERE type='kesmas'` setelah import uji di lokal, pastikan tidak ada NIK 16 digit utuh milik anak lain.
- **Referensi**: OWASP A04:2021 (data minimization); UU 27/2022 PDP
- **Kepastian**: Eksplisit

### [SEC-004] Pesan exception mentah (bisa berisi SQL + nilai kesehatan) masuk laravel.log dan riwayat import
- **Track**: Keamanan (static)
- **Lokasi**: `app/Imports/KesmasImport.php:226; app/Jobs/ImportKesmasJob.php:51-55, 62-66`
- **Deskripsi**: Per baris, `Log::warning("KesmasImport baris {$n}: ".$e->getMessage())` menulis pesan QueryException lengkap — termasuk SQL dan binding berisi nilai Kesmas (penyakit penyerta, komplikasi, dll.) — ke laravel.log; pesan untuk pengguna sudah disaring `ImportError::message()`. Di level job, `'Import gagal: '.$e->getMessage()` disimpan mentah ke `failures` dan ditampilkan ke superadmin (dapat memuat SQL/path server).
- **Rekomendasi**: Log cukup kelas exception + SQLSTATE + nama kolom (pola `ImportError`), dan saring juga pesan level job sebelum disimpan ke `failures`. Verifikasi manual: baca kembali kedua lokasi dan pastikan tidak ada `getMessage()` QueryException yang dicatat utuh.
- **Referensi**: OWASP A09:2021 Security Logging and Monitoring Failures
- **Kepastian**: Eksplisit

### [SEC-005] reimport/destroyLog tanpa cek pemilik log, reimport tanpa cek status; destroyLog menghapus berkas yang masih dirujuk log hasil reimport
- **Track**: Keamanan (static)
- **Lokasi**: `app/Http/Controllers/ImportCsvController.php:183-222, 256-267`
- **Deskripsi**: `index`/`importStatus` hanya menampilkan log milik `auth()->id()`, tetapi `reimport` dan `destroyLog` memakai route-model binding tanpa membandingkan `user_id`, sehingga superadmin mana pun bisa mengulang/menghapus log superadmin lain lewat ID (tetap superadmin-only, bukan IDOR lintas peran). `reimport` tidak menolak log pending/processing (UI menyembunyikan tombolnya, server tidak) sehingga import yang sama bisa berjalan ganda; bila tak ada indeks unik (id_anak, tgl_kunjungan), worker paralel berpotensi membuat kunjungan ganda. Log hasil reimport berbagi `file_path` yang sama, jadi menghapus satu log menghapus berkas yang masih dirujuk log lain.
- **Rekomendasi**: Tambah `abort_if($log->user_id !== auth()->id(), 403)` (atau keputusan eksplisit bahwa semua superadmin boleh), tolak reimport bila status pending/processing, dan hapus berkas hanya bila tak ada log lain dengan file_path sama. Verifikasi manual: baca ulang kedua method; cek skema `data_anak` untuk indeks unik (id_anak, tgl_kunjungan).
- **Referensi**: OWASP A01:2021 Broken Access Control; A04:2021
- **Kepastian**: Eksplisit

### [REQ-001] Header tanpa kolom identitas wajib berakhir berstatus 'Selesai' 0/0, bukan import gagal
- **Track**: Persyaratan (Requirements Conformance) (static)
- **Lokasi**: `app/Imports/KesmasImport.php:96-99,125-131; app/Jobs/ImportKesmasJob.php:40-48`
- **Deskripsi**: SPEC §2 menyatakan kolom identitas wajib yang hilang dari header membuat 'import gagal dengan [ERROR]'. KesmasImport::bacaHeader() hanya menambah pesan '[ERROR] Header: ...' lalu menghentikan pembacaan tanpa menaikkan $gagal, sehingga ImportKesmasJob menyimpan status 'done' dengan success_count 0 dan failure_count 0. Riwayat menampilkan lencana 'Selesai' dan petugas harus membuka rincian untuk tahu berkasnya ditolak. Perilaku ini sama dengan importer lain (AnakImport/CapilImport/ImunisasiImport juga hanya mencatat pesan header), jadi konsisten dengan pola proyek walau berbeda dari kalimat spec.
- **Rekomendasi**: Lempar exception khusus (mis. HeaderImportTidakSah) dari bacaHeader agar job menandai log 'failed', atau setidaknya naikkan failure_count. Bila sengaja disamakan dengan importer lain, ubah kalimat SPEC §2.
- **Referensi**: R11/R13 — SPEC §2 'Pembacaan header'
- **Kepastian**: Eksplisit

### [REQ-002] Sel ukur tak sah pada kunjungan yang sudah ada dibuang tanpa PERINGATAN
- **Track**: Persyaratan (Requirements Conformance) (static)
- **Lokasi**: `app/Imports/KesmasImport.php:299-305 (bandingkan 279-281)`
- **Deskripsi**: SPEC §1.5/§3 meminta sel tak sah dilaporkan dengan [PERINGATAN] yang menyebut kolom dan nilainya. Di cabang kunjungan yang sudah ada, tulisKunjungan() hanya melaporkan $salah (field Kesmas) dan perbedaan ukur yang sah (bedaUkur). $ukur['salah'] (mis. bb 'abc', bb gram di luar rentang, posisi tak dikenal, tb <= 0) tidak pernah dicatat. Tidak ada data yang salah tertulis karena ukuran memang tidak disentuh, tetapi petugas tidak diberi tahu bahwa isian ukurnya keliru, berbeda dari cabang kunjungan baru yang melaporkannya.
- **Rekomendasi**: Di cabang kunjungan yang sudah ada, catat juga $ukur['salah'] sebagai PERINGATAN (mis. '...; tidak dibandingkan').
- **Referensi**: R5, R9 — SPEC §1.5, §3, §5.2
- **Kepastian**: Eksplisit

### [REQ-003] NIK berkas lebih dari 16 digit dipotong lalu dianggap sah
- **Track**: Persyaratan (Requirements Conformance) (static)
- **Lokasi**: `app/Services/PenautanAnakKesmas.php:166-167`
- **Deskripsi**: SPEC §4 langkah 2 hanya mempercayai nik_anak 'valid (15–16 digit)'. PenautanAnakKesmas::cari() menjalankan substr($nik, 0, 16) sebelum memeriksa panjang, sehingga NIK 17+ digit (salah ketik atau digit tambahan) dipotong diam-diam dan dipakai mencari anak.nik. Risikonya kecil karena kecocokan tetap mensyaratkan tgl lahir sama atau nama mirip, tetapi perilakunya menyimpang dari aturan 'valid 15–16 digit' dan tidak ada pesan apa pun.
- **Rekomendasi**: Anggap NIK dengan panjang di luar 15–16 digit tidak sah (nikSah=false) tanpa memotong, dan opsional beri [PERINGATAN] 'NIK tidak sah, dicocokkan lewat nama'.
- **Referensi**: R3 — SPEC §4 langkah 2
- **Kepastian**: Eksplisit

### [UX-010] Pesan 'ukuran tidak diubah' memakai nama kolom DB, kode posisi, dan kg hasil konversi
- **Track**: UI/UX (static)
- **Lokasi**: `app/Imports/KesmasImport.php:362-379`
- **Deskripsi**: bedaUkur() menulis '{kolom} di berkas X, tersimpan Y' dengan nama kolom data_anak (tb, lk, lla), bukan nama kolom berkas (tb_cm, lk_cm, lila_cm). Posisi tampil sebagai kode 'H'/'L', bukan 'Berdiri'/'Terlentang'. Untuk bayi di bawah 2 bulan, bb berkas tampil dalam kg hasil konversi ('3,25'), padahal petugas mengetik '3250'. Petugas sulit mencocokkan pesan dengan isi berkasnya.
- **Rekomendasi**: Pakai nama kolom berkas (array_flip(self::UKUR)), tampilkan posisi sebagai label, dan tampilkan bb dengan satuan yang diketik beserta satuannya ('bb di berkas 3250 g, tersimpan 3,2 kg').
- **Referensi**: Nielsen #2 Match Between System and the Real World; #4 Consistency and Standards
- **Kepastian**: Eksplisit

### [UX-011] Peringatan 'tidak ada isian Kesmas atau kunjungan' juga muncul untuk baris yang berisi ukuran kunjungan
- **Track**: UI/UX (static)
- **Lokasi**: `app/Imports/KesmasImport.php:302-307, 231-235`
- **Deskripsi**: Bila kunjungan sudah ada dan baris hanya berisi ukuran (tanpa kolom layanan atau per anak), tulisKunjungan() mengembalikan false. Baris itu lalu mendapat peringatan 'tidak ada isian Kesmas atau kunjungan', padahal isian kunjungannya ada dan sengaja tidak ditimpa. Petugas bisa mengira datanya tidak terbaca.
- **Rekomendasi**: Bedakan pesannya, misalnya 'kunjungan {tgl} sudah tercatat; ukurannya tidak ditimpa dan tidak ada isian layanan baru — tidak ada perubahan'.
- **Referensi**: Nielsen #2 Match Between System and the Real World
- **Kepastian**: Eksplisit

### [UX-012] Pesan ambigu menyarankan 'isi nik_anak' padahal NIK sudah diisi
- **Track**: UI/UX (static)
- **Lokasi**: `app/Services/PenautanAnakKesmas.php:48-51, 64-66`
- **Deskripsi**: Bila NIK di berkas milik anak lain atau tidak dipercaya, penautan jatuh ke nama + tgl lahir. Bila hasilnya ambigu, pesannya 'isi nik_anak supaya pasti' walaupun kolom itu sudah terisi. Pesan 'anak tidak ditemukan' juga tidak memberi tahu apakah NIK di berkas tidak dikenal. Penyebutan kriterianya pun beda antar-pesan: '(nama + tgl lahir + jk)' vs '(… + nama ortu)'.
- **Rekomendasi**: Sesuaikan saran dengan kondisi: bila NIK sudah diisi tetapi tidak cocok, minta petugas memeriksa NIK atau nama di berkas. Bila NIK diisi tetapi tidak ada di SIRINDU, sebutkan 'NIK {x} tidak terdaftar'. Seragamkan juga penyebutan kriteria pencocokan.
- **Referensi**: Nielsen #9 Help Users Recognize, Diagnose, and Recover from Errors
- **Kepastian**: Eksplisit

### [UX-013] Setelah unggah, halaman kembali ke tab 'Data Anak'
- **Track**: UI/UX (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:333-336, 373, 810-821`
- **Deskripsi**: Tab aktif ditanam statis pada 'anak' (is-active) dan tidak ada penanganan hash/old input. Setelah unggah Kesmas, redirect back() membuka tab Data Anak, sehingga petugas yang hendak mengunggah berkas Kesmas berikutnya harus memilih tab lagi. Flash memang menyebut '(Data Kesmas)', tetapi panel yang terlihat tidak sesuai.
- **Rekomendasi**: Simpan tab aktif di URL hash atau session flash ('tab' => $type) dan aktifkan tab itu saat halaman dimuat.
- **Referensi**: Nielsen #3 User Control and Freedom; #6 Recognition Rather Than Recall
- **Kepastian**: Eksplisit

### [UX-014] Kegagalan polling status diabaikan diam-diam
- **Track**: UI/UX (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:953-961`
- **Deskripsi**: fetchLogs() mengosongkan handler .fail(). Bila sesi habis atau server galat saat polling, badge 'Menunggu'/'Diproses' terus tampil tanpa tanda bahwa status tidak lagi diperbarui. Pola ini sama untuk semua tab, tetapi import Kesmas memanggil refreshAll() prioritas gizi sehingga prosesnya bisa lama.
- **Rekomendasi**: Tampilkan teks kecil 'Status tidak dapat diperbarui — klik muat ulang' saat gagal, dan lanjutkan polling dengan jeda yang makin panjang.
- **Referensi**: Nielsen #1 Visibility of System Status
- **Kepastian**: Eksplisit

### [UX-015] Teks panduan memakai jargon dan singkatan tanpa penjelasan
- **Track**: UI/UX (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:645, 327`
- **Deskripsi**: Panel Kesmas dibuka dengan 'Field Kesmas anak … (KN, MTBM/MTBS, CKG, MBG, rujukan, dll.)'. Kata 'Field' adalah istilah teknis, dan singkatannya tidak dijelaskan di panel. Deskripsi halaman di atas tab juga masih hanya menyebut Anak, Pengukuran Berkala, dan Imunisasi, belum termasuk Data Kesmas.
- **Rekomendasi**: Ganti 'Field' dengan 'Data'. Biarkan singkatan karena akrab bagi petugas kesehatan, tetapi rujuk ke sheet Petunjuk untuk daftar lengkapnya. Perbarui juga deskripsi halaman agar mencakup semua tipe import.
- **Referensi**: Nielsen #2 Match Between System and the Real World
- **Kepastian**: Eksplisit

### [UX-016] INFO penautan menampilkan 'anak ber-NIK ' kosong bila anak di DB belum punya NIK
- **Track**: UI/UX (static)
- **Lokasi**: `app/Services/PenautanAnakKesmas.php:58-59`
- **Deskripsi**: Pesan INFO menyisipkan {$t['anak']->nik} tanpa fallback. Untuk anak tanpa NIK (umum pada data import lama), kalimatnya menjadi 'dengan anak ber-NIK ; NIK berkas … tidak dipakai'.
- **Rekomendasi**: Gunakan fallback, misalnya 'anak tanpa NIK' atau nama dan tanggal lahir anak yang ditautkan.
- **Referensi**: Nielsen #8 Aesthetic and Minimalist Design
- **Kepastian**: Inferensi


## ⚪ Severity: Info

### [A11Y-014] Kontras teks halaman Import dan chip Kesmas lolos AA
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:263; app/Exports/TemplateKesmas.php:81`
- **Deskripsi**: Dihitung dari nilai oklch (konversi OKLab->sRGB, alfa dicampur ke latar): chip .imp-chip-kesmas #00543f di #e5fbf5 = 8,34:1; tab tak aktif 6,72:1; tab aktif putih di #157123 = 6,16:1; label panduan 5,51:1; tautan template 5,14:1; petunjuk zona unggah 6,00:1; badge Menunggu 5,21:1; filter aktif 5,31:1. Nilai rendah hanya garis putus-putus zona unggah (1,61:1) dan ikon awan (1,87:1), tetapi zona teridentifikasi oleh teksnya dan penanda :focus-within (6,16:1), jadi tidak dianggap pelanggaran 1.4.11. Dropdown template memakai setShowDropDown(true) yang oleh writer PhpSpreadsheet dibalik menjadi showDropDown=0 — panah daftar tampil (benar).
- **Rekomendasi**: Tidak perlu tindakan. Bila ingin border zona unggah >=3:1, naikkan alfa border-nya.
- **Referensi**: WCAG 2.1 SC 1.4.3, 1.4.11
- **Kepastian**: Inferensi

### [DYN-A11Y-001] Konfirmasi dinamis A11Y-001, A11Y-003, A11Y-004, A11Y-007
- **Track**: Aksesibilitas (dinamis)
- **Lokasi**: `resources/views/admin/import/index.blade.php; public/admin/vendors/styles/style.css`
- **Deskripsi**: Di Chrome: tombol 'Upload & Import' panel Kesmas cocok :focus-visible tetapi outline 'none 0px' dan box-shadow transparan — fokus tak terlihat (A11Y-001). Panah kanan pada tab 'Penanggung Jawab' tidak memindahkan fokus; semua tab tanpa atribut tabindex/roving (A11Y-003). Tidak ada satu pun region aria-live di halaman (A11Y-004). Nama aksesibel tab terbaca 'health_and_safety Data Kesmas' karena teks ikon ikut (A11Y-007).
- **Rekomendasi**: Lihat rekomendasi temuan statis terkait.
- **Referensi**: WCAG 2.1 SC 2.4.7, 2.1.1, 4.1.3, 4.1.2 — terkonfirmasi dinamis
- **Kepastian**: Terverifikasi manual

### [DYN-SEC-001] Akses tamu ke halaman Import dialihkan ke login
- **Track**: Keamanan (dinamis)
- **Lokasi**: `routes/web.php:91; app/Http/Controllers/ImportCsvController.php`
- **Deskripsi**: Membuka /admin/import-csv tanpa sesi berakhir di /login (cek non-destruktif). Upload .csv ke rute kesmas ditolak validasi tanpa membuat log; upload Excel sah hanya membuat import_log, dan baris anak fiktif ditolak tanpa menulis data anak.
- **Rekomendasi**: Tidak ada tindakan.
- **Referensi**: OWASP A01:2021 - Broken Access Control (negatif); terkonfirmasi dinamis
- **Kepastian**: Terverifikasi manual

### [DYN-UX-002] Konfirmasi dinamis temuan statis UX-001, UX-002, UX-013
- **Track**: UI/UX (dinamis)
- **Lokasi**: `resources/views/admin/import/index.blade.php; app/Jobs/ImportKesmasJob.php`
- **Deskripsi**: Berkas header salah tampil di riwayat sebagai badge hijau 'SELESAI', Berhasil 0, Gagal 0 (UX-001). Unggahan .csv ke tab Kesmas ditolak validasi tetapi halaman kembali ke tab 'Data Anak' tanpa pesan apa pun dan tanpa log (UX-002, UX-013). Panel 'Baris bermasalah' menampilkan pesan header dan pesan baris dengan jelas.
- **Rekomendasi**: Lihat rekomendasi temuan statis terkait.
- **Referensi**: Terkonfirmasi dinamis 15 Okt 2026
- **Kepastian**: Terverifikasi manual

### [SEC-006] Escaping atribut data-failures di render AJAX tidak lengkap (bukan XSS, tapi bisa merusak tampilan)
- **Track**: Keamanan (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:989-990 (escHtml di 907, 1007)`
- **Deskripsi**: Pesan riwayat memuat teks dari sel berkas (nama anak, nilai sel). Semua jalur output sudah di-escape: Blade `{{ }}` untuk filename & `data-failures`, dan `escHtml()` sebelum `<li>`. Namun di `renderLogs()` JSON hanya di-`replace(/"/g,'&quot;')` tanpa meng-escape `&`; sel berisi teks literal `&quot;` akan terdekode jadi tanda kutip di dalam JSON, `.data('failures')` mengembalikan string, dan `failures.map` melempar TypeError sehingga panel error tidak muncul. Tidak ada jalur keluar dari atribut (entity tidak bisa menutup atribut), jadi tidak ada XSS.
- **Rekomendasi**: Pakai `escHtml(JSON.stringify(log.failures))` untuk atribut. Verifikasi manual di browser lokal: import berkas uji dengan nama_anak berisi `&quot;` dan `<b>x</b>`, klik tombol Error setelah polling AJAX — panel harus tampil dengan teks literal.
- **Referensi**: OWASP A03:2021 Injection (XSS) — negatif, hardening
- **Kepastian**: Terverifikasi manual

### [SEC-007] Template sementara: tempnam() meninggalkan berkas kosong dan nama .xlsx tidak dibuat atomik
- **Track**: Keamanan (static)
- **Lokasi**: `app/Exports/TemplateKesmas.php:108-111; app/Http/Controllers/ImportCsvController.php:284-288`
- **Deskripsi**: `tempnam(sys_get_temp_dir(),'tpl_kesmas').'.xlsx'` membuat berkas dasar tanpa ekstensi lalu menulis ke nama lain; `deleteFileAfterSend()` hanya menghapus yang `.xlsx`, sehingga tiap unduhan meninggalkan satu berkas kosong di temp. Nama `.xlsx` juga tidak dibuat eksklusif (teoretis rawan symlink race di /tmp bersama). Isi template tak memuat data pribadi.
- **Rekomendasi**: Simpan ke `storage/app/tmp` dengan `Str::uuid().'.xlsx'`, atau `unlink()` berkas dasar tempnam setelah save. Verifikasi manual: hitung `tpl_kesmas*` di direktori temp sebelum/sesudah satu unduhan di lokal.
- **Referensi**: CWE-377 Insecure Temporary File; CWE-459 Incomplete Cleanup
- **Kepastian**: Eksplisit

### [SEC-008] Mass assignment: $guarded=[] pada Anak/DataAnak, tetapi kolom dari berkas sudah dibatasi whitelist (positif)
- **Track**: Keamanan (static)
- **Lokasi**: `app/Imports/KesmasImport.php:133-137, 286-294, 309, 401; app/Services/KolomKesmasImport.php:38-41`
- **Deskripsi**: `bacaHeader()` memotong peta kolom dengan `array_intersect_key(..., kolomDikenal())`, dan `fill()`/`create()` hanya menerima kunci dari `KesmasRules::anak()/kunjungan()` (+ peta UKUR dan kunci id_anak/tgl_kunjungan/bln/id_user yang diisi server). Header asing seperti `nik`, `status`, `id_kel`, `sasaran_balita_kesmas` hanya menghasilkan PERINGATAN dan diabaikan. Risiko tersisa: bila kelak kolom sensitif ditambahkan ke `KesmasRules::anak()`, ia otomatis bisa diimpor massal.
- **Rekomendasi**: Tambah test yang mengunci bahwa header `nik`/`status`/`sasaran_balita_kesmas`/`id_kel` diabaikan. Verifikasi manual: baca `kolomDikenal()` setiap kali KesmasRules berubah.
- **Referensi**: OWASP A08:2021 Software and Data Integrity Failures (mass assignment)
- **Kepastian**: Terverifikasi manual

### [SEC-009] Otorisasi & validasi upload sudah memadai (positif)
- **Track**: Keamanan (static)
- **Lokasi**: `routes/web.php:91,135-150; app/Http/Controllers/ImportCsvController.php:56,115-135,185,230,258,282`
- **Deskripsi**: Semua rute `admin/import-csv/*` ada di grup `['auth','is_admin']` dan setiap method controller (index, upload, reimport, importStatus, destroyLog, downloadTemplate — cabang kesmas diletakkan SETELAH `abort_if`) memanggil `isSuperAdmin()`, sehingga peran Kesmas terbatas tidak lolos walau melewati IsAdmin. Upload: `required|file|mimes:xlsx,xls|max:20480`, disimpan lewat `store()` (nama acak) ke disk local non-publik `storage/app/imports/kesmas`; nama asli hanya ditampilkan ter-escape. Form memakai @csrf, DELETE AJAX mengirim token.
- **Rekomendasi**: Pertahankan; tambahkan rute POST `admin.importCsv.kesmas` dan GET template kesmas ke matriks `tests/Feature/AkunKesmas` untuk tiap peran terbatas (harus 403). Verifikasi manual: `php artisan route:list --path=import-csv` menampilkan middleware auth,is_admin.
- **Referensi**: OWASP A01:2021 Broken Access Control — negatif
- **Kepastian**: Terverifikasi manual

### [SEC-010] Sel rumus Excel tersimpan sebagai teks '=...' di field teks bebas Kesmas
- **Track**: Keamanan (static)
- **Lokasi**: `app/Imports/KesmasImport.php:30; app/Services/KolomKesmasImport.php:135`
- **Deskripsi**: Importer tidak memakai `WithCalculatedFormulas`, sehingga sel rumus terbaca sebagai teks rumus mentah dan lolos validasi `nullable|string` ke kolom seperti `penyakit_penyerta`, `catatan_pengukuran`, `intervensi`. `KesmasExport`/`AnakExport` menulis StringCell literal, jadi tidak tereksekusi di sana; risiko hanya bila ada ekspor lain (Maatwebsite/CSV) yang membaca kolom ini.
- **Rekomendasi**: Beri PERINGATAN/tolak nilai teks berawalan `=`, `+`, `-`, `@` pada kolom teks bebas, atau pastikan semua ekspor memperlakukannya literal. Verifikasi manual: `grep -rn "penyakit_penyerta\|catatan_pengukuran" app/Exports` dan cek tiap ekspor memakai sel teks eksplisit.
- **Referensi**: OWASP CSV/Formula Injection; A03:2021
- **Kepastian**: Inferensi

### [SEC-011] Tidak ada jejak per-field atas nilai Kesmas anak yang ditimpa import
- **Track**: Keamanan (static)
- **Lokasi**: `app/Imports/KesmasImport.php:309, 401`
- **Deskripsi**: Sel berisi menimpa nilai lama `anak`/`data_anak` via `fill()->save()`; yang tercatat hanya ImportLog (berkas + pengunggah) dan `id_user` pada kunjungan baru. Nilai lama yang tertimpa tidak tersimpan, sehingga salah tautan (lihat SEC-001) atau berkas keliru tidak bisa ditelusuri/dibatalkan per anak.
- **Rekomendasi**: Pertimbangkan tabel log perubahan (anak_id, kolom, lama, baru, import_log_id) seperti pola `sasaran_kesmas_log`/`epid_renumber_log`. Verifikasi manual: cek apakah ada observer/paket audit yang sudah merekam perubahan model Anak.
- **Referensi**: OWASP A09:2021 Security Logging and Monitoring Failures
- **Kepastian**: Eksplisit

### [REQ-004] Perilaku tambahan di luar spec: rentang berat lahir, penjaga kembar di jalur NIK, observer dibisukan lalu refreshAll
- **Track**: Persyaratan (Requirements Conformance) (static)
- **Lokasi**: `app/Services/KolomKesmasImport.php:31-35,77-80; app/Services/PenautanAnakKesmas.php:176-183,226-233; app/Jobs/ImportKesmasJob.php:38-39`
- **Deskripsi**: (1) KolomKesmasImport menambah rentang bbl 0,3–8 kg yang tidak ada di KesmasRules maupun SPEC §3 (yang menyebut validasi memakai KesmasRules, 'sumber sama dengan form'), jadi impor lebih ketat daripada form. (2) PenautanAnakKesmas menambah jalan pintas nama-identik dan adaSaudaraLebihCocok() di jalur NIK. Keduanya lebih ketat daripada aturan 'tgl lahir sama ATAU nama mirip ≥87%' dan dibuat untuk kasus kembar. (3) SPEC §5.5 menyebut observer prioritas gizi 'tetap berjalan'. Job membungkus import dengan duringMutedImport lalu memanggil refreshAll(), jadi hasil akhirnya setara dan polanya sama dengan ImportPengukuranJob. Ketiganya wajar, tetapi SPEC belum mencatatnya.
- **Rekomendasi**: Perbarui SPEC §3/§4/§5 (atau catatan CLAUDE.md) agar ketiga aturan ini tercatat sebagai keputusan, termasuk bahwa form Kesmas tidak menerapkan rentang bbl yang sama.
- **Referensi**: R9, R3, R16 — SPEC §3, §4, §5
- **Kepastian**: Eksplisit

### [REQ-005] Ringkasan cakupan: R1–R18 terpenuhi kecuali penyimpangan kecil di atas
- **Track**: Persyaratan (Requirements Conformance) (static)
- **Lokasi**: `app/Imports/KesmasImport.php; app/Http/Controllers/ImportCsvController.php; tests/Feature/ImportKesmas/*`
- **Deskripsi**: Terverifikasi statis: R1 (tipe kesmas, mimes xlsx,xls, max 20480, tab UI dengan format tanggal dan tautan template; ImportCsvController.php:107-127, index.blade.php:638-685), R2/R18 (hanya penautan baca, saran NIK tautkan() diabaikan, tidak ada create Anak), R3 (PenautanAnakKesmas + PenautanAnakImport::tautkan dengan nama ortu), R4/R6/R7 (KesmasImport.php:239-312), R8 (SatuanBeratBadan untuk/keKg/dalamRentang), R9/R14 (KolomKesmasImport::ubah dengan Validator + KesmasRules, kolom dari array_keys), R10 (sasaran_balita_kesmas tidak ada di daftar kolom), R11 (format pesan dan nomor baris Excel = awal+i+1 dengan header di potongan pertama; pengurutan oleh ImportLog::urutkanPesan), R12 (format_tanggal disimpan dan diteruskan saat reimport, kunciFormatTanggal di potongan pertama), R13 (sheets() [0=>this], TemplateKesmas dua sheet, dropdown, tanpa baris contoh; writer PhpSpreadsheet membalik showDropDown sehingga panah dropdown tampil), R15 (abort_if isSuperAdmin pada upload/reimport/template), R16 (DB::transaction per baris, Eloquent create/save), R17 (tanpa logika prioritas sumber). Celah tes kecil: tidak ada tes nomor baris lintas potongan (>200 baris) atau tes urutan ERROR→PERINGATAN→INFO khusus Kesmas seperti yang disebut SPEC §8 'Pesan'.
- **Rekomendasi**: Opsional: tambah tes berkas >200 baris yang meng-assert 'Baris 202', dan satu tes urutan tingkat pesan pada log Kesmas.
- **Referensi**: Baseline R1–R18; SPEC §8
- **Kepastian**: Inferensi

### [UX-017] Inkonsistensi antar-tab yang sudah ada sebelumnya: filter PJ tidak ada, label tipe beda antara server dan JS
- **Track**: UI/UX (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:697-703, 736, 971-972`
- **Deskripsi**: Filter riwayat tidak punya tombol 'PJ'. Peta label tipe di Blade punya 'hasil_lab' tetapi tidak punya 'pj', sedangkan peta di JS kebalikannya, sehingga baris PJ tampil 'pj' mentah saat render server dan 'PJ' setelah polling. Tab Kesmas sendiri sudah konsisten: chip, filter, dan label tersedia di keduanya.
- **Rekomendasi**: Satukan peta label tipe, misalnya dari ImportCsvController::VALID_TYPES yang dilempar ke JS, dan tambahkan filter PJ.
- **Referensi**: Nielsen #4 Consistency and Standards
- **Kepastian**: Eksplisit

### [UX-018] Dengan QUEUE_CONNECTION=sync, tombol 'Mengunggah…' menahan petugas selama seluruh import
- **Track**: UI/UX (static)
- **Lokasi**: `resources/views/admin/import/index.blade.php:858-865; app/Http/Controllers/ImportCsvController.php:173-176`
- **Deskripsi**: Menurut catatan proyek, prod memakai queue sync. Dalam mode itu ImportKesmasJob, termasuk refreshAll() prioritas gizi, berjalan di dalam request unggah, sehingga petugas menatap spinner 'Mengunggah…' lama dan flash sesudahnya menyebut 'sedang diproses di latar belakang' padahal sudah selesai. Bila melewati batas waktu PHP, halaman bisa error tanpa log tersimpan.
- **Rekomendasi**: Pastikan worker antrean berjalan di prod. Ubah juga teks tombol menjadi 'Mengunggah & memproses — jangan tutup halaman' agar ekspektasi petugas sesuai.
- **Referensi**: Nielsen #1 Visibility of System Status
- **Kepastian**: Inferensi


---
*Laporan dihasilkan otomatis oleh skill `audit-aplikasi-swarm`. Temuan dengan kepastian "Kandidat" atau "Inferensi" tetap perlu ditinjau manual sebelum ditindaklanjuti.*
