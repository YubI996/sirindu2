<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verifikasi Warga — {{ $rt->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
<style>
:root{ --ink:oklch(0.24 0.02 145); --muted:oklch(0.50 0.015 145); --faint:oklch(0.62 0.012 145);
  --line:oklch(0.90 0.012 145); --bg:oklch(0.98 0.012 145); --card:#fff; --green:oklch(0.48 0.14 145);
  --green-soft:oklch(0.95 0.04 145); --amber:#b45309; --amber-soft:#fef3c7; --red:#b91c1c; --red-soft:#fee2e2; --blue:#1d4ed8; --blue-soft:#dbeafe; }
*{ box-sizing:border-box; }
body{ margin:0; font-family:Barlow,system-ui,sans-serif; color:var(--ink); background:var(--bg); }
.rt-shell{ max-width:1200px; margin:0 auto; padding:16px; }
.rt-top{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:12px 0 18px; }
.rt-brand{ display:flex; align-items:center; gap:10px; font-weight:800; letter-spacing:.06em; }
.rt-brand img{ width:34px; height:34px; }
.rt-who{ font-size:.9rem; color:var(--muted); }
.rt-who b{ color:var(--ink); }
.rt-logout{ background:transparent; border:1px solid var(--line); border-radius:8px; padding:6px 12px; font:inherit; cursor:pointer; color:var(--muted); }
.rt-prog{ background:var(--card); border:1px solid var(--line); border-radius:12px; padding:14px 16px; margin-bottom:14px; }
.rt-prog__bar{ height:8px; background:var(--line); border-radius:99px; overflow:hidden; margin-top:8px; }
.rt-prog__fill{ height:100%; background:var(--green); width:0; transition:width .3s; }
.rt-tabs{ display:flex; gap:6px; border-bottom:1px solid var(--line); margin-bottom:12px; }
.rt-tab{ background:transparent; border:0; border-bottom:2px solid transparent; padding:10px 14px; font:inherit; font-weight:700; color:var(--muted); cursor:pointer; }
.rt-tab.active{ color:var(--green); border-bottom-color:var(--green); }
.rt-tab .n{ display:inline-block; min-width:22px; padding:0 6px; border-radius:99px; background:var(--line); font-size:.75rem; margin-left:6px; }
.rt-tools{ display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px; }
.rt-tools input,.rt-tools select{ font:inherit; padding:8px 10px; border:1px solid var(--line); border-radius:8px; background:var(--card); }
.rt-tools input{ flex:1; min-width:220px; }
.rt-hint{ font-size:.85rem; color:var(--muted); margin:0 0 10px; }
.rt-wrap{ background:var(--card); border:1px solid var(--line); border-radius:12px; overflow:auto; }
table.rt-dt{ width:100%; border-collapse:collapse; font-size:.85rem; }
.rt-dt th{ position:sticky; top:0; background:oklch(0.96 0.016 145); text-align:left; padding:.55rem .7rem; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); white-space:nowrap; }
.rt-dt td{ padding:.55rem .7rem; border-top:1px solid var(--line); vertical-align:top; }
.badge{ display:inline-block; padding:.1rem .45rem; border-radius:6px; font-size:.72rem; font-weight:700; }
.badge--ot{ background:var(--green-soft); color:var(--green); } .badge--capil{ background:var(--blue-soft); color:var(--blue); }
.badge--manual{ background:var(--amber-soft); color:var(--amber); } .badge--lain{ background:var(--line); color:var(--muted); }
.st{ display:inline-block; padding:.15rem .5rem; border-radius:6px; font-size:.75rem; font-weight:700; }
.st--berdomisili{ background:var(--green-soft); color:var(--green); } .st--pindah,.st--tidak_dikenal{ background:var(--amber-soft); color:var(--amber); }
.st--meninggal{ background:var(--red-soft); color:var(--red); } .st--kosong{ color:var(--faint); font-style:italic; }
.reviu{ display:block; font-size:.68rem; color:var(--faint); margin-top:2px; }
.aksi{ display:flex; gap:4px; flex-wrap:wrap; }
.aksi button{ font:inherit; font-size:.75rem; font-weight:700; padding:4px 8px; border-radius:6px; border:1px solid var(--line); background:var(--card); cursor:pointer; }
.aksi button:hover{ border-color:var(--green); color:var(--green); }
.aksi button.aktif{ background:var(--green); border-color:var(--green); color:#fff; }
.rt-empty{ text-align:center; padding:28px; color:var(--faint); }
.rt-toast{ position:fixed; left:50%; bottom:20px; transform:translateX(-50%); background:var(--ink); color:#fff; padding:10px 16px; border-radius:10px; font-size:.85rem; display:none; }
/* Tab "Kemungkinan sama": satu kartu per pasangan, kolom berbeda disorot kuning */
.pair{ border:1px solid var(--line); border-radius:12px; background:var(--card); margin-bottom:12px; overflow:hidden; }
.pair__head{ display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; padding:10px 14px; background:oklch(0.96 0.016 145); font-size:.8rem; color:var(--muted); }
.pair__head b{ color:var(--ink); }
.pair table{ width:100%; border-collapse:collapse; font-size:.85rem; }
.pair th{ text-align:left; width:140px; padding:.45rem .8rem; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); border-top:1px solid var(--line); }
.pair td{ padding:.45rem .8rem; border-top:1px solid var(--line); width:calc((100% - 140px)/2); }
.pair tr.beda td{ background:var(--amber-soft); font-weight:700; }
.pair__foot{ display:flex; gap:8px; padding:10px 14px; justify-content:flex-end; }
.pair__foot button{ font:inherit; font-weight:800; padding:8px 16px; border-radius:8px; border:1px solid var(--line); background:var(--card); cursor:pointer; }
.pair__foot button[data-keputusan="sama"]{ background:var(--green); border-color:var(--green); color:#fff; }
</style>
</head>
<body>
<div class="rt-shell">
  <header class="rt-top">
    <div class="rt-brand"><img src="{{ asset('logo/icon-sirindu.png') }}" alt="">SIRINDU · Verifikasi Warga</div>
    <div class="rt-who"><b>{{ $rt->name }}</b> · Kel. {{ $rt->kelurahan?->name ?? '-' }} · {{ auth()->user()->name }}</div>
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="rt-logout" type="submit">Keluar</button></form>
  </header>

  <section class="rt-prog">
    <div><b id="prog-teks">{{ $progres['diverifikasi'] }} dari {{ $progres['total'] }}</b> warga sudah diverifikasi</div>
    <div class="rt-prog__bar"><div class="rt-prog__fill" id="prog-fill" style="width:{{ $progres['total'] ? round($progres['diverifikasi'] / $progres['total'] * 100) : 0 }}%"></div></div>
  </section>

  <nav class="rt-tabs">
    <button class="rt-tab active" data-tab="warga">Warga RT <span class="n" id="n-warga">–</span></button>
    <button class="rt-tab" data-tab="tanpa">Belum ber-RT (sekelurahan) <span class="n" id="n-tanpa">–</span></button>
    <button class="rt-tab" data-tab="kandidat">Kemungkinan sama <span class="n" id="n-kandidat">–</span></button>
  </nav>

  <p class="rt-hint" id="hint-warga">Tandai setiap anak: masih berdomisili di RT ini, sudah pindah, meninggal, atau tidak dikenal. Keputusan Anda ditinjau puskesmas.</p>
  <p class="rt-hint" id="hint-tanpa" style="display:none">Anak di kelurahan ini yang belum diketahui RT-nya. Klik <b>Warga RT saya</b> bila ia tinggal di RT Anda, atau <b>Bukan</b> bila tidak.</p>
  <p class="rt-hint" id="hint-kandidat" style="display:none">Dua baris data yang mungkin adalah <b>anak yang sama</b> (mis. dari Operasi Timbang dan Capil). Bandingkan lalu putuskan <b>Sama</b> atau <b>Beda</b>. Kolom berlatar kuning = isinya berbeda.</p>

  <div class="rt-tools">
    <input type="search" id="cari" placeholder="Cari nama / NIK / orang tua / alamat…">
    <select id="f-status">
      <option value="">Semua status</option>
      <option value="kosong">Belum diverifikasi</option>
      <option value="berdomisili">Berdomisili</option>
      <option value="pindah">Pindah</option>
      <option value="meninggal">Meninggal</option>
      <option value="tidak_dikenal">Tidak dikenal</option>
    </select>
  </div>

  <div class="rt-wrap" id="tabel"><div class="rt-empty">Memuat…</div></div>
</div>
<div class="rt-toast" id="toast"></div>

{{-- Tombol aksi per tab; JS mengklon template ini dan mengisi data-id --}}
<template id="tpl-aksi-warga">
  <div class="aksi">
    <button data-status="berdomisili">Berdomisili</button>
    <button data-status="pindah">Pindah</button>
    <button data-status="meninggal">Meninggal</button>
    <button data-status="tidak_dikenal">Tidak dikenal</button>
  </div>
</template>
<template id="tpl-aksi-tanpa">
  <div class="aksi">
    <button data-status="berdomisili">Warga RT saya</button>
    <button data-status="bukan_rt_ini">Bukan</button>
  </div>
</template>
<template id="tpl-aksi-pair">
  <div class="pair__foot">
    <button data-keputusan="beda">Beda orang</button>
    <button data-keputusan="sama">Sama — satu anak</button>
  </div>
</template>

<script>
var API_WARGA   = '{{ route("rt.api.warga", request()->only("rt")) }}';
var API_TANPA   = '{{ route("rt.api.tanpaRt", request()->only("rt")) }}';
var API_USULKAN = '{{ route('rt.api.usulkan', ['anak' => '__ID__'] + request()->only('rt')) }}';
var CSRF        = '{{ csrf_token() }}';
var API_KANDIDAT = '{{ route("rt.api.kandidat", request()->only("rt")) }}';
var API_PUTUSKAN = '{{ route("rt.api.putuskan", request()->only("rt")) }}';
var FIELD_LABEL = { sumber_label:'Sumber', nik:'NIK', nama:'Nama', jk:'JK', tgl_lahir:'Tgl lahir', nama_ibu:'Ibu', nama_ayah:'Ayah', no_kk:'No KK', alamat:'Alamat domisili', alamat_ktp:'Alamat KTP', posyandu:'Posyandu' };
var LABEL = { berdomisili:'Berdomisili', pindah:'Pindah', meninggal:'Meninggal', tidak_dikenal:'Tidak dikenal', bukan_rt_ini:'Bukan warga RT ini' };
var data = { warga:[], tanpa:[], kandidat:[] };
var tab = 'warga';

function esc(s){ return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function toast(msg){ var t = document.getElementById('toast'); t.textContent = msg; t.style.display = 'block'; clearTimeout(t._h); t._h = setTimeout(function(){ t.style.display = 'none'; }, 2600); }
function badgeSumber(r){
  var cls = r.sumber === 'operasi_timbang' ? 'ot' : r.sumber === 'capil' ? 'capil' : r.sumber === 'manual' ? 'manual' : 'lain';
  return '<span class="badge badge--'+cls+'">'+esc(r.sumber_label)+'</span>';
}
function statusCell(r){
  if(!r.verif_status) return '<span class="st st--kosong">belum diverifikasi</span>';
  var reviu = r.verif_reviu === 'disetujui' ? 'disetujui' : r.verif_reviu === 'ditolak' ? 'ditolak peninjau' : 'menunggu reviu';
  return '<span class="st st--'+esc(r.verif_status)+'">'+esc(r.verif_label)+'</span><span class="reviu">'+reviu+(r.verif_at ? ' · '+esc(r.verif_at) : '')+'</span>';
}
function aksiCell(r){
  var tpl = document.getElementById(tab === 'tanpa' ? 'tpl-aksi-tanpa' : 'tpl-aksi-warga').content.cloneNode(true);
  tpl.querySelectorAll('button').forEach(function(b){
    b.setAttribute('data-id', r.id);
    if(tab !== 'tanpa' && r.verif_status === b.getAttribute('data-status')) b.classList.add('aktif');
  });
  var box = document.createElement('div'); box.appendChild(tpl);
  return box.innerHTML;
}
function renderKandidat(){
  var rows = data.kandidat;
  var q = document.getElementById('cari').value.trim().toLowerCase();
  if(q){ rows = rows.filter(function(p){ return [p.a.nama,p.a.nik,p.b.nama,p.b.nik,p.a.nama_ibu,p.b.nama_ibu].join(' ').toLowerCase().indexOf(q) >= 0; }); }
  document.getElementById('n-kandidat').textContent = data.kandidat.length;
  if(!rows.length){ document.getElementById('tabel').innerHTML = '<div class="rt-empty">Tidak ada pasangan yang perlu diputuskan</div>'; return; }
  var h = '';
  rows.forEach(function(p){
    h += '<div class="pair" data-a="'+esc(p.a.id)+'" data-b="'+esc(p.b.id)+'"><div class="pair__head"><span>Alasan: <b>'+esc(p.via_label)+'</b></span><span>'+p.beda.length+' kolom berbeda</span></div><table>';
    Object.keys(FIELD_LABEL).forEach(function(f){
      var beda = p.beda.indexOf(f) >= 0;
      var va = f === 'sumber_label' ? badgeSumber(p.a) : esc(p.a[f] || '-');
      var vb = f === 'sumber_label' ? badgeSumber(p.b) : esc(p.b[f] || '-');
      h += '<tr class="'+(beda ? 'beda' : '')+'"><th>'+FIELD_LABEL[f]+'</th><td>'+va+'</td><td>'+vb+'</td></tr>';
    });
    var foot = document.getElementById('tpl-aksi-pair').content.cloneNode(true);
    var box = document.createElement('div'); box.appendChild(foot);
    h += '</table>'+box.innerHTML+'</div>';
  });
  document.getElementById('tabel').innerHTML = h;
}
function render(){
  if(tab === 'kandidat'){ renderKandidat(); return; }
  var rows = data[tab];
  var q = document.getElementById('cari').value.trim().toLowerCase();
  var fs = document.getElementById('f-status').value;
  if(q){ rows = rows.filter(function(r){ return [r.nama,r.nik,r.nama_ibu,r.nama_ayah,r.alamat,r.alamat_ktp,r.no_kk].join(' ').toLowerCase().indexOf(q) >= 0; }); }
  if(fs === 'kosong'){ rows = rows.filter(function(r){ return !r.verif_status; }); }
  else if(fs){ rows = rows.filter(function(r){ return r.verif_status === fs; }); }
  document.getElementById('n-warga').textContent = data.warga.length;
  document.getElementById('n-tanpa').textContent = data.tanpa.length;
  document.getElementById('n-kandidat').textContent = data.kandidat.length;
  if(!rows.length){ document.getElementById('tabel').innerHTML = '<div class="rt-empty">Tidak ada data</div>'; return; }
  var h = '<table class="rt-dt"><thead><tr><th>No</th><th>Sumber</th><th>NIK</th><th>Nama</th><th>JK</th><th>Tgl Lahir</th><th>Ibu</th><th>Ayah</th><th>No KK</th><th>Alamat Domisili</th><th>Alamat KTP</th><th>Posyandu</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
  rows.forEach(function(r, i){
    h += '<tr data-row="'+esc(r.id)+'"><td>'+(i+1)+'</td><td>'+badgeSumber(r)+'</td><td>'+esc(r.nik)+'</td><td><b>'+esc(r.nama)+'</b></td><td>'+esc(r.jk)+'</td><td>'+esc(r.tgl_lahir)+'</td>'
      +'<td>'+esc(r.nama_ibu)+'</td><td>'+esc(r.nama_ayah)+'</td><td>'+esc(r.no_kk)+'</td><td>'+esc(r.alamat || '-')+'</td><td>'+esc(r.alamat_ktp || '-')+'</td><td>'+esc(r.posyandu || '-')+'</td>'
      +'<td class="c-status">'+statusCell(r)+'</td><td>'+aksiCell(r)+'</td></tr>';
  });
  document.getElementById('tabel').innerHTML = h + '</tbody></table>';
}
function setProgres(p){
  if(!p) return;
  document.getElementById('prog-teks').textContent = p.diverifikasi+' dari '+p.total;
  document.getElementById('prog-fill').style.width = (p.total ? Math.round(p.diverifikasi / p.total * 100) : 0)+'%';
}
function muat(){
  document.getElementById('tabel').innerHTML = '<div class="rt-empty">Memuat…</div>';
  Promise.all([fetch(API_WARGA, {headers:{Accept:'application/json'}}).then(function(r){ return r.json(); }),
               fetch(API_TANPA, {headers:{Accept:'application/json'}}).then(function(r){ return r.json(); }),
               fetch(API_KANDIDAT, {headers:{Accept:'application/json'}}).then(function(r){ return r.json(); })])
    .then(function(res){ data.warga = res[0].rows || []; data.tanpa = res[1].rows || []; data.kandidat = res[2].rows || []; setProgres(res[0].progres); render(); })
    .catch(function(){ document.getElementById('tabel').innerHTML = '<div class="rt-empty" style="color:#b91c1c">Gagal memuat data</div>'; });
}
function usulkan(id, status, btn){
  var catatan = null;
  if(status === 'pindah' || status === 'tidak_dikenal'){ catatan = window.prompt('Catatan (opsional), mis. pindah ke mana:', ''); if(catatan === null) return; }
  btn.disabled = true;
  fetch(API_USULKAN.replace('__ID__', encodeURIComponent(id)), {
    method:'POST', headers:{ 'X-CSRF-TOKEN':CSRF, 'Content-Type':'application/json', Accept:'application/json' },
    body: JSON.stringify({ status:status, catatan:catatan })
  })
  .then(function(r){ return r.json().then(function(j){ if(!r.ok) throw new Error(j.message || 'HTTP '+r.status); return j; }); })
  .then(function(j){
    var list = data[tab];
    var idx = list.findIndex(function(r){ return r.id === id; });
    if(j.hilang){ if(idx >= 0) list.splice(idx, 1); }
    else if(idx >= 0){ list[idx].verif_status = j.verif_status; list[idx].verif_label = j.verif_label; list[idx].verif_reviu = j.verif_reviu; list[idx].verif_at = j.verif_at; }
    setProgres(j.progres); render(); toast('Tersimpan — menunggu reviu puskesmas');
  })
  .catch(function(e){ btn.disabled = false; toast('Gagal: '+e.message); });
}
function putuskan(card, keputusan, btn){
  var catatan = window.prompt(keputusan === 'sama' ? 'Catatan (opsional), mis. "NIK lama salah ketik":' : 'Catatan (opsional), mis. "kembar / kakak-adik":', '');
  if(catatan === null) return;
  btn.disabled = true;
  var idA = card.getAttribute('data-a'), idB = card.getAttribute('data-b');
  fetch(API_PUTUSKAN, {
    method:'POST', headers:{ 'X-CSRF-TOKEN':CSRF, 'Content-Type':'application/json', Accept:'application/json' },
    body: JSON.stringify({ a: idA, b: idB, keputusan: keputusan, catatan: catatan })
  })
  .then(function(r){ return r.json().then(function(j){ if(!r.ok) throw new Error(j.message || 'HTTP '+r.status); return j; }); })
  .then(function(){
    data.kandidat = data.kandidat.filter(function(p){ return !(p.a.id === idA && p.b.id === idB); });
    render(); toast(keputusan === 'sama' ? 'Ditandai satu anak — menunggu reviu puskesmas' : 'Ditandai beda orang — menunggu reviu puskesmas');
  })
  .catch(function(e){ btn.disabled = false; toast('Gagal: '+e.message); });
}
document.querySelectorAll('.rt-tab').forEach(function(b){
  b.addEventListener('click', function(){
    tab = b.getAttribute('data-tab');
    document.querySelectorAll('.rt-tab').forEach(function(x){ x.classList.toggle('active', x === b); });
    document.getElementById('hint-warga').style.display = tab === 'warga' ? '' : 'none';
    document.getElementById('hint-tanpa').style.display = tab === 'tanpa' ? '' : 'none';
    document.getElementById('hint-kandidat').style.display = tab === 'kandidat' ? '' : 'none';
    document.getElementById('f-status').style.display = tab === 'kandidat' ? 'none' : '';
    render();
  });
});
document.getElementById('cari').addEventListener('input', render);
document.getElementById('f-status').addEventListener('change', render);
document.getElementById('tabel').addEventListener('click', function(e){
  var kb = e.target.closest('button[data-keputusan]');
  if(kb){ putuskan(kb.closest('.pair'), kb.getAttribute('data-keputusan'), kb); return; }
  var btn = e.target.closest('button[data-status]');
  if(btn) usulkan(btn.getAttribute('data-id'), btn.getAttribute('data-status'), btn);
});
muat();
</script>
</body>
</html>
