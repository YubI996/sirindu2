{{-- Peta Sebaran Kasus Surveillance — partial dipakai bersama oleh dasbor PD3I
     (city-wide) dan halaman Peta Sebaran mandiri.
     Prasyarat host: Leaflet 1.9.x + jQuery sudah dimuat.
     Param: $cityWide (bool) — true => minta data semua wilayah (city_wide=1). --}}
@php($psCityWide = ($cityWide ?? false) ? 1 : 0)

<div class="ps-map-wrap">
    {{-- Filter --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row">
                <div class="col-md-3">
                    <label class="small mb-1">Jenis Penyakit</label>
                    <select id="ps-disease" class="form-control form-control-sm">
                        <option value="">Semua Penyakit</option>
                        @foreach(($diseases ?? []) as $disease)
                            <option value="{{ $disease->id }}">{{ $disease->nama_penyakit }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="small mb-1">Status Kasus</label>
                    <select id="ps-status" class="form-control form-control-sm">
                        <option value="">Semua Status</option>
                        <option value="suspected">Suspected</option>
                        <option value="probable">Probable</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="discarded">Discarded</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="small mb-1">Tanggal Mulai</label>
                    <input type="date" id="ps-start" class="form-control form-control-sm" value="{{ date('Y-m-01') }}">
                </div>
                <div class="col-md-3">
                    <label class="small mb-1">Tanggal Akhir</label>
                    <input type="date" id="ps-end" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                </div>
            </div>
            <div class="mt-2">
                <button id="ps-apply" class="btn btn-sm btn-primary"><i class="fa fa-search"></i> Terapkan</button>
                <button id="ps-reset" class="btn btn-sm btn-secondary"><i class="fa fa-redo"></i> Reset</button>
                <span class="ml-2 small text-muted">Total ditampilkan: <strong id="ps-total">0</strong></span>
            </div>
        </div>
    </div>

    {{-- Toolbar: layer + toggle titik + toggle outline --}}
    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
            <div class="btn-group btn-group-sm" role="group" aria-label="Tingkat wilayah">
                <button type="button" class="btn btn-outline-primary" data-ps-layer="kecamatan"><i class="fa fa-city mr-1"></i> Kecamatan</button>
                <button type="button" class="btn btn-primary" data-ps-layer="kelurahan"><i class="fa fa-map mr-1"></i> Kelurahan</button>
                <button type="button" class="btn btn-outline-primary" data-ps-layer="rt"><i class="fa fa-home mr-1"></i> RT</button>
            </div>
            <div class="d-flex align-items-center flex-wrap" style="gap:1rem;">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="ps-markers" checked>
                    <label class="custom-control-label" for="ps-markers" style="font-size:.8rem;">Titik lokasi kasus</label>
                </div>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="ps-outline">
                    <label class="custom-control-label" for="ps-outline" style="font-size:.8rem;">Batas wilayah (outline)</label>
                </div>
            </div>
        </div>
        <div class="card-body p-0" style="position:relative; isolation:isolate;">
            <div id="ps-map" style="height:520px; width:100%;"></div>
            <div id="ps-legend" class="ps-legend">
                <div class="d-flex align-items-center flex-wrap" style="gap:.75rem; font-size:.72rem;">
                    <span><span class="ps-swatch" style="background:#be123c;"></span> &gt;50</span>
                    <span><span class="ps-swatch" style="background:#f59e0b;"></span> 21–50</span>
                    <span><span class="ps-swatch" style="background:#fbbf24;"></span> 11–20</span>
                    <span><span class="ps-swatch" style="background:#0891b2;"></span> 1–10</span>
                    <span><span class="ps-swatch" style="background:#e5e7eb;"></span> 0</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Panel kasus RT tidak terdefinisi --}}
    <div id="ps-undefined-panel" class="alert alert-warning mt-2 mb-0" style="display:none;">
        <i class="fa fa-exclamation-triangle"></i>
        <strong>Kasus RT Tidak Terdefinisi:</strong>
        <span id="ps-undefined-count">0</span> kasus tak tampil di peta RT karena data RT tak tercatat
        (tetap terhitung di layer Kelurahan/Kecamatan).
        <button type="button" class="btn btn-sm btn-warning ml-2" id="ps-undefined-show"><i class="fa fa-list"></i> Lihat</button>
    </div>
</div>

{{-- Modal daftar RT tidak terdefinisi --}}
<div class="modal fade" id="ps-undefined-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fa fa-exclamation-triangle"></i> Kasus RT Tidak Terdefinisi</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr><th>No. Reg</th><th>Nama</th><th>Kelurahan</th><th>Penyakit</th><th>Status</th><th>Onset</th></tr>
                        </thead>
                        <tbody id="ps-undefined-tbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<style>
    .ps-legend { position:absolute; bottom:10px; left:10px; z-index:600; background:rgba(255,255,255,.92);
        padding:6px 10px; border-radius:6px; box-shadow:0 1px 4px rgba(0,0,0,.2); }
    .ps-swatch { display:inline-block; width:16px; height:12px; border-radius:2px; vertical-align:middle; margin-right:2px; }
</style>

<script>
(function () {
    // Idempoten: cegah inisialisasi ganda bila partial ter-include lebih dari sekali.
    if (window.PetaSebaran) return;

    const CITY_WIDE = {{ $psCityWide }};
    const MAP_DATA_URL = '{{ route('admin.epidemiologi.mapData') }}';

    let map = null, currentLayer = 'kelurahan', mapData = {}, caseMarkersLayer = null;
    let boundaryOnly = false, loadedOnce = false, mapping = null;
    const geoJsonLayers = { kecamatan: null, kelurahan: null, rt: null };
    const geoJsonData   = { kecamatan: null, kelurahan: null, rt: null };
    const geoJsonFiles  = {
        kecamatan: '/geojson/Kota Bontang-KECAMATAN.geojson',
        kelurahan: '/geojson/Kota Bontang-KEL_DESA.geojson',
        rt:        '/geojson/batas-rt-bontang.geojson',
    };

    // ── Name resolution (mapping.json) ──
    function resolveKelurahanName(name) {
        if (!name || !mapping) return name;
        let n = name;
        if (mapping.normalisasi) {
            if (mapping.normalisasi[n]) n = mapping.normalisasi[n];
            else { const l = n.toLowerCase(); for (const k in mapping.normalisasi) if (k.toLowerCase() === l) { n = mapping.normalisasi[k]; break; } }
        }
        if (mapping.kelurahan) {
            if (mapping.kelurahan[n]) return mapping.kelurahan[n];
            const l = n.toLowerCase(); for (const k in mapping.kelurahan) if (k.toLowerCase() === l) return mapping.kelurahan[k];
        }
        return n;
    }
    function normalizeKelurahan(name) {
        if (!name || !mapping) return name;
        let n = name;
        if (mapping.normalisasi && mapping.normalisasi[n]) n = mapping.normalisasi[n];
        if (mapping.rt && mapping.rt.kelurahan_suffix) {
            if (mapping.rt.kelurahan_suffix[n]) return n;
            const l = n.toLowerCase(); for (const k in mapping.rt.kelurahan_suffix) if (k.toLowerCase() === l) return k;
        }
        return n;
    }
    function convertRTName(rtNumber, kelurahan) {
        if (!rtNumber || !kelurahan) return null;
        const rtNum = parseInt(rtNumber, 10);
        if (isNaN(rtNum)) return null;
        const nk = normalizeKelurahan(kelurahan);
        let suffix = (mapping && mapping.rt && mapping.rt.kelurahan_suffix && nk) ? mapping.rt.kelurahan_suffix[nk] : null;
        if (!suffix) { suffix = kelurahan.toUpperCase().replace(/[^A-Z]/g, ''); if (suffix.length > 10) suffix = suffix.substring(0, 2); }
        return rtNum + suffix;
    }
    function getPropertyValue(props, keys) {
        if (!props) return null;
        for (const k of keys) if (props[k] !== undefined && props[k] !== null) return props[k];
        return null;
    }
    function getRtLookup(feature) {
        const props = feature && feature.properties ? feature.properties : null;
        const rtNumber = getPropertyValue(props, ['RT','Rt','rt','NO_RT','No_RT','no_rt','nomor_rt','rt_no','nama_rt','Nama_RT','Name','NAME']);
        const kelurahan = getPropertyValue(props, ['Kelurahan','KELURAHAN','kelurahan','kel_desa','KEL_DESA','Kel','kel']);
        let dbRTName = null, displayName = null;
        if (rtNumber && kelurahan) {
            dbRTName = convertRTName(rtNumber, kelurahan);
            const p = parseInt(rtNumber, 10);
            displayName = 'RT ' + (isNaN(p) ? rtNumber : p) + ' ' + kelurahan;
        } else if (rtNumber) { dbRTName = String(rtNumber).trim(); displayName = 'RT ' + rtNumber; }
        return { dbRTName, displayName };
    }
    function findCaseData(featureName, layerType, feature) {
        if (!mapData) return null;
        let group;
        if (layerType === 'kecamatan') group = mapData.casesByKecamatan || {};
        else if (layerType === 'rt')   group = mapData.casesByRT || {};
        else                           group = mapData.casesByKelurahan || {};
        if (layerType === 'rt') {
            const info = getRtLookup(feature);
            const dbName = info && info.dbRTName ? info.dbRTName : featureName;
            return Object.values(group).find(i => i.name === dbName || i.name.toLowerCase() === dbName.toLowerCase()) || null;
        }
        if (layerType === 'kelurahan') {
            const m = resolveKelurahanName(featureName);
            return Object.values(group).find(i =>
                i.name.toLowerCase().includes(m.toLowerCase()) || m.toLowerCase().includes(i.name.toLowerCase()) ||
                i.name.toLowerCase().includes(featureName.toLowerCase()) || featureName.toLowerCase().includes(i.name.toLowerCase())) || null;
        }
        const clean = featureName.replace('Kecamatan ', '');
        return Object.values(group).find(i => i.name.toLowerCase().includes(clean.toLowerCase()) || clean.toLowerCase().includes(i.name.toLowerCase())) || null;
    }

    // ── Styling & popups ──
    function getColorByCount(c) { return c > 50 ? '#be123c' : c > 20 ? '#f59e0b' : c > 10 ? '#fbbf24' : c > 0 ? '#0891b2' : '#e5e7eb'; }
    function getFeatureName(feature, layerType) {
        const p = feature.properties;
        if (layerType === 'rt') { const i = getRtLookup(feature); return i && i.displayName ? i.displayName : (p.Name || p.RT || p.nama_rt || 'Unknown'); }
        return p.Name || p.nama || p.kel_desa || 'Unknown';
    }
    function styleFeature(feature, layerType) {
        if (boundaryOnly) {
            return { fillColor: '#000', fillOpacity: 0, weight: layerType === 'kecamatan' ? 3 : 2, color: '#374151', opacity: 1 };
        }
        const caseData = findCaseData(feature.properties.Name || feature.properties.nama || feature.properties.RT || '', layerType, feature);
        const count = caseData ? caseData.count : 0;
        return {
            fillColor: getColorByCount(count),
            weight: layerType === 'kecamatan' ? 3 : (layerType === 'rt' ? 1 : 2),
            opacity: 1, color: layerType === 'kecamatan' ? '#0066cc' : '#ffffff',
            fillOpacity: layerType === 'rt' ? 0.6 : 0.7,
        };
    }
    function buildPopupContent(feature, layerType) {
        const rawName = feature.properties.Name || feature.properties.nama || feature.properties.RT || feature.properties.nama_rt || '';
        const displayName = getFeatureName(feature, layerType);
        const caseData = findCaseData(rawName, layerType, feature);
        let html = '<div style="min-width:200px;"><h6 class="mb-2"><strong>' + displayName + '</strong></h6>';
        if (caseData && caseData.count > 0) {
            html += '<p class="mb-1"><strong>Total Kasus: ' + caseData.count + '</strong></p>';
            const byDisease = {};
            caseData.cases.forEach(c => { byDisease[c.disease] = (byDisease[c.disease] || 0) + 1; });
            html += '<p class="mb-1"><strong>Per Penyakit:</strong></p><ul class="mb-2">';
            for (const [d, n] of Object.entries(byDisease)) html += '<li>' + d + ': ' + n + '</li>';
            html += '</ul>';
            const recent = caseData.cases.slice(0, 3);
            if (recent.length) {
                html += '<p class="mb-1"><strong>Kasus Terbaru:</strong></p><ul class="mb-0">';
                recent.forEach(c => {
                    const b = c.status === 'confirmed' ? 'danger' : (c.status === 'suspected' ? 'warning' : 'secondary');
                    html += '<li><small>' + c.nama + ' - ' + c.disease + '<br><span class="badge badge-' + b + '">' + c.status + '</span> ' + c.tanggal_onset + '</small></li>';
                });
                html += '</ul>';
            }
        } else { html += '<p class="text-muted">Tidak ada kasus</p>'; }
        return html + '</div>';
    }

    // ── Render layer ──
    function renderCurrentLayer() {
        ['kecamatan','kelurahan','rt'].forEach(t => { if (geoJsonLayers[t]) { map.removeLayer(geoJsonLayers[t]); geoJsonLayers[t] = null; } });
        const type = currentLayer, data = geoJsonData[type];
        if (!data) {
            $('#ps-overlay').length && $('#ps-overlay').show();
            setTimeout(() => { if (geoJsonData[type]) renderCurrentLayer(); else if (type !== 'kelurahan') switchLayer('kelurahan'); }, 1200);
            return;
        }
        geoJsonLayers[type] = L.geoJSON(data, {
            style: f => styleFeature(f, type),
            onEachFeature: (feature, layer) => {
                layer.bindPopup(buildPopupContent(feature, type));
                layer.on({
                    mouseover: e => { e.target.setStyle(boundaryOnly ? { weight: 4, color: '#111827' } : { weight: 4, color: '#666', fillOpacity: 0.9 }); e.target.bringToFront(); },
                    mouseout:  e => { if (geoJsonLayers[type]) geoJsonLayers[type].resetStyle(e.target); },
                });
            },
        }).addTo(map);
        try { map.fitBounds(geoJsonLayers[type].getBounds()); } catch (e) {}
        const legend = document.getElementById('ps-legend');
        if (legend) legend.style.display = boundaryOnly ? 'none' : 'block';
    }

    function switchLayer(type) {
        currentLayer = type;
        document.querySelectorAll('[data-ps-layer]').forEach(b => {
            const active = b.dataset.psLayer === type;
            b.classList.toggle('btn-primary', active);
            b.classList.toggle('btn-outline-primary', !active);
        });
        const panel = document.getElementById('ps-undefined-panel');
        if (type === 'rt' && mapData && (mapData.undefinedRtCount || 0) > 0) {
            document.getElementById('ps-undefined-count').textContent = mapData.undefinedRtCount;
            panel.style.display = 'block';
        } else { panel.style.display = 'none'; }
        renderCurrentLayer();
    }

    function showUndefinedRtCases() {
        if (!mapData || !mapData.casesByRT) return;
        const tbody = document.getElementById('ps-undefined-tbody');
        tbody.innerHTML = '';
        Object.values(mapData.casesByRT).forEach(g => {
            if (!g.undefined) return;
            (g.cases || []).forEach(c => {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td>' + (c.no_registrasi || '-') + '</td><td>' + (c.nama || '-') + '</td><td>' + (c.kelurahan || '-') +
                    '</td><td>' + (c.disease || '-') + '</td><td><span class="badge badge-secondary">' + (c.status || '-') + '</span></td><td>' + (c.tanggal_onset || '-') + '</td>';
                tbody.appendChild(tr);
            });
        });
        if (!tbody.children.length) tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Tidak ada data</td></tr>';
        $('#ps-undefined-modal').modal('show');
    }

    // ── Case markers (kasus tak menyimpan koordinat → biasanya kosong) ──
    function getMarkerColor(s) { return s === 'confirmed' ? '#dc3545' : s === 'probable' ? '#ffc107' : s === 'suspected' ? '#007bff' : '#6c757d'; }
    function renderCaseMarkers() {
        if (caseMarkersLayer) { map.removeLayer(caseMarkersLayer); caseMarkersLayer = null; }
        if (!mapData.caseMarkers || !mapData.caseMarkers.length) return;
        if (!document.getElementById('ps-markers').checked) return;
        caseMarkersLayer = L.layerGroup();
        mapData.caseMarkers.forEach(c => {
            const circle = L.circleMarker([c.lat, c.lng], { radius: 7, fillColor: getMarkerColor(c.status), color: '#fff', weight: 2, opacity: 1, fillOpacity: 0.85 });
            const label = c.status ? c.status.charAt(0).toUpperCase() + c.status.slice(1) : '-';
            const b = c.status === 'confirmed' ? 'danger' : (c.status === 'probable' ? 'warning' : (c.status === 'suspected' ? 'primary' : 'secondary'));
            circle.bindPopup('<div style="min-width:180px;"><strong>' + c.nama + '</strong><br><small>Penyakit: ' + c.disease + '</small><br><small>Status: <span class="badge badge-' + b + '">' + label + '</span></small><br><small>Onset: ' + c.tanggal_onset + '</small></div>');
            caseMarkersLayer.addLayer(circle);
        });
        caseMarkersLayer.addTo(map);
    }

    // ── Data load ──
    function loadMapData() {
        $.ajax({
            url: MAP_DATA_URL, type: 'GET',
            data: {
                disease_id: $('#ps-disease').val(), status: $('#ps-status').val(),
                start_date: $('#ps-start').val(), end_date: $('#ps-end').val(),
                city_wide: CITY_WIDE,
            },
            success: function (res) {
                mapData = res || {};
                document.getElementById('ps-total').textContent = res.totalCases || 0;
                renderCurrentLayer();
                renderCaseMarkers();
            },
            error: function () { console.error('Gagal memuat data peta.'); },
        });
    }

    function initMap() {
        if (map) return;
        map = L.map('ps-map').setView([0.1236, 117.4753], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 18 }).addTo(map);

        fetch('/geojson/mapping.json').then(r => r.json()).then(d => { mapping = d; }).catch(() => { mapping = {}; });
        Object.keys(geoJsonFiles).forEach(t => {
            fetch(geoJsonFiles[t]).then(r => { if (!r.ok) throw new Error('nf'); return r.json(); })
                .then(d => { geoJsonData[t] = d; }).catch(() => { console.log('GeoJSON ' + t + ' tidak tersedia'); });
        });

        document.querySelectorAll('[data-ps-layer]').forEach(b => b.addEventListener('click', () => switchLayer(b.dataset.psLayer)));
        document.getElementById('ps-apply').addEventListener('click', loadMapData);
        document.getElementById('ps-reset').addEventListener('click', () => {
            $('#ps-disease').val(''); $('#ps-status').val('');
            $('#ps-start').val('{{ date('Y-m-01') }}'); $('#ps-end').val('{{ date('Y-m-d') }}');
            loadMapData();
        });
        document.getElementById('ps-markers').addEventListener('change', function () {
            if (this.checked) renderCaseMarkers();
            else if (caseMarkersLayer) { map.removeLayer(caseMarkersLayer); caseMarkersLayer = null; }
        });
        document.getElementById('ps-outline').addEventListener('change', function () { boundaryOnly = this.checked; renderCurrentLayer(); });
        document.getElementById('ps-undefined-show').addEventListener('click', showUndefinedRtCases);
    }

    // Aktivasi: dipanggil host saat peta terlihat (tab dibuka / halaman siap).
    function activate() {
        initMap();
        map.invalidateSize();
        if (!loadedOnce) { loadedOnce = true; loadMapData(); }
    }

    window.PetaSebaran = { activate, invalidate: () => { if (map) map.invalidateSize(); } };
})();
</script>
