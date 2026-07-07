@extends('admin::layouts.app')
@section('title') Peta Sebaran Kasus Surveillance @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Surveillance @endsection
@section('item-active') Peta Sebaran @endsection

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">
            <i class="fa fa-map-marked-alt mr-2"></i>
            Peta Sebaran Kasus Surveillance
        </h2>
        <div>
            <a href="{{ route('admin.epidemiologi.dashboard') }}" class="btn btn-info">
                <i class="fa fa-chart-line"></i> Dashboard Analytics
            </a>
            <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-primary">
                <i class="fa fa-list"></i> Daftar Kasus
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fa fa-filter"></i> Filter Peta</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Jenis Penyakit</label>
                        <select id="disease_filter" class="form-control">
                            <option value="">Semua Penyakit</option>
                            @foreach($diseases as $disease)
                                <option value="{{ $disease->id }}">{{ $disease->nama_penyakit }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Status Kasus</label>
                        <select id="status_filter" class="form-control">
                            <option value="">Semua Status</option>
                            <option value="suspected">Suspected</option>
                            <option value="probable">Probable</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="discarded">Discarded</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Tanggal Mulai</label>
                        <input type="date" id="start_date" class="form-control" value="{{ date('Y-m-01') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Tanggal Akhir</label>
                        <input type="date" id="end_date" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <button id="apply_filter" class="btn btn-primary">
                        <i class="fa fa-search"></i> Terapkan Filter
                    </button>
                    <button id="reset_filter" class="btn btn-secondary">
                        <i class="fa fa-redo"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Legend & Stats -->
    <div class="row mb-3">
        <div class="col-md-9">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0"><i class="fa fa-info-circle"></i> <span id="legend_title">Legenda Kepadatan Kasus per Kelurahan</span></h6>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnKecamatan" onclick="switchLayer('kecamatan')">
                                <i class="fa fa-city mr-1"></i> Kecamatan
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" id="btnKelurahan" onclick="switchLayer('kelurahan')">
                                <i class="fa fa-map mr-1"></i> Kelurahan
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnRT" onclick="switchLayer('rt')">
                                <i class="fa fa-home mr-1"></i> RT
                            </button>
                        </div>
                    </div>
                    <div class="d-flex align-items-center" id="legend_colors">
                        <div class="mr-4">
                            <span class="badge" style="background-color: #be123c; width: 30px;">&nbsp;</span> >50 kasus
                        </div>
                        <div class="mr-4">
                            <span class="badge" style="background-color: #f59e0b; width: 30px;">&nbsp;</span> 21-50 kasus
                        </div>
                        <div class="mr-4">
                            <span class="badge" style="background-color: #fbbf24; width: 30px;">&nbsp;</span> 11-20 kasus
                        </div>
                        <div class="mr-4">
                            <span class="badge" style="background-color: #0891b2; width: 30px;">&nbsp;</span> 1-10 kasus
                        </div>
                        <div>
                            <span class="badge" style="background-color: #e5e7eb; width: 30px;">&nbsp;</span> 0 kasus
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex align-items-center">
                        <div class="custom-control custom-checkbox mr-4">
                            <input type="checkbox" class="custom-control-input" id="toggleCaseMarkers" checked>
                            <label class="custom-control-label" for="toggleCaseMarkers">Tampilkan titik lokasi kasus</label>
                        </div>
                        <div class="mr-3">
                            <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#dc3545;"></span> Confirmed
                        </div>
                        <div class="mr-3">
                            <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#ffc107;"></span> Probable
                        </div>
                        <div class="mr-3">
                            <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#007bff;"></span> Suspected
                        </div>
                        <div>
                            <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#6c757d;"></span> Lainnya
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5>Total Kasus Ditampilkan</h5>
                    <h2 id="total_cases">0</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Container -->
    <div class="card shadow">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fa fa-globe"></i> Peta Interaktif Sebaran Kasus</h5>
            <span class="badge badge-light" id="layer_label">Layer: Kelurahan</span>
        </div>
        <div class="card-body p-0" style="position: relative; isolation: isolate;">
            <div id="map" style="height: 600px; width: 100%;"></div>
            <div id="loadingOverlay" style="display:none; position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(255,255,255,0.8); z-index:1000; display:none; align-items:center; justify-content:center;">
                <div class="text-center">
                    <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Memuat data...</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Panel: Kasus RT Tidak Terdefinisi — hanya muncul saat layer RT aktif --}}
    <div id="undefined-rt-panel" class="alert alert-warning mt-3" style="display:none;">
        <i class="fa fa-exclamation-triangle"></i>
        <strong>Kasus dengan RT Tidak Terdefinisi:</strong>
        <span id="undefined-rt-count">0</span> kasus tidak dapat ditampilkan di peta RT karena data RT tidak tercatat.
        Kasus-kasus ini tetap terhitung di layer <strong>Kelurahan</strong> dan <strong>Kecamatan</strong>.
        <button type="button" class="btn btn-sm btn-warning ml-2" id="btn-show-undefined-rt"
                onclick="showUndefinedRtCases()">
            <i class="fa fa-list"></i> Lihat Daftar
        </button>
    </div>

    {{-- Modal: Daftar kasus RT tidak terdefinisi --}}
    <div class="modal fade" id="undefinedRtModal" tabindex="-1" role="dialog" aria-labelledby="undefinedRtModalLabel" aria-modal="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="undefinedRtModalLabel">
                        <i class="fa fa-exclamation-triangle"></i> Kasus dengan RT Tidak Terdefinisi
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">
                        Kasus berikut diimpor dari Excel namun data RT tidak ditemukan di master data atau tidak diisi.
                        Kasus ini tetap tercatat dalam sistem dan terhitung di laporan per kelurahan/kecamatan.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="undefined-rt-table">
                            <thead class="thead-light">
                                <tr>
                                    <th>No. Reg</th>
                                    <th>Nama</th>
                                    <th>Kelurahan</th>
                                    <th>Penyakit</th>
                                    <th>Status</th>
                                    <th>Tgl. Onset</th>
                                </tr>
                            </thead>
                            <tbody id="undefined-rt-tbody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Instructions -->
    <div class="alert alert-info mt-3">
        <i class="fa fa-lightbulb"></i> <strong>Cara Penggunaan:</strong>
        <ul class="mb-0">
            <li>Gunakan tombol <strong>Kecamatan / Kelurahan / RT</strong> untuk mengganti layer peta</li>
            <li>Klik pada wilayah untuk melihat detail kasus</li>
            <li>Gunakan filter untuk menyaring data berdasarkan penyakit, status, atau periode waktu</li>
            <li>Warna wilayah menunjukkan kepadatan kasus (semakin merah = semakin banyak kasus)</li>
            <li>Saat layer <strong>RT</strong> aktif, kasus tanpa data RT ditampilkan dalam panel peringatan di bawah peta</li>
        </ul>
    </div>
</div>
@endsection

@section('scripts')
@parent
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let map;
let currentLayer = 'kelurahan';
let mapData = {};
let caseMarkersLayer = null;

// GeoJSON layers cache
let geoJsonLayers = {
    kecamatan: null,
    kelurahan: null,
    rt: null
};

// GeoJSON data cache
let geoJsonData = {
    kecamatan: null,
    kelurahan: null,
    rt: null
};

// GeoJSON files
const geoJsonFiles = {
    kecamatan: '/geojson/Kota Bontang-KECAMATAN.geojson',
    kelurahan: '/geojson/Kota Bontang-KEL_DESA.geojson',
    rt: '/geojson/batas-rt-bontang.geojson'
};

// Name mapping (loaded from mapping.json)
let mapping = null;

$(document).ready(function() {
    map = L.map('map').setView([0.1236, 117.4753], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 18
    }).addTo(map);

    // Load mapping.json for name resolution
    fetch('/geojson/mapping.json')
        .then(r => r.json())
        .then(data => { mapping = data; })
        .catch(() => { mapping = {}; });

    // Pre-load all GeoJSON files
    Object.keys(geoJsonFiles).forEach(type => {
        fetch(geoJsonFiles[type])
            .then(r => {
                if (!r.ok) throw new Error('Not found');
                return r.json();
            })
            .then(data => { geoJsonData[type] = data; })
            .catch(err => { console.log('GeoJSON ' + type + ' not available:', err); });
    });

    loadMapData();

    $('#apply_filter').on('click', loadMapData);

    $('#reset_filter').on('click', function() {
        $('#disease_filter').val('');
        $('#status_filter').val('');
        $('#start_date').val('{{ date("Y-m-01") }}');
        $('#end_date').val('{{ date("Y-m-d") }}');
        loadMapData();
    });
});

function loadMapData() {
    showLoading();

    $.ajax({
        url: '{{ route("admin.epidemiologi.mapData") }}',
        type: 'GET',
        data: {
            disease_id: $('#disease_filter').val(),
            status: $('#status_filter').val(),
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val()
        },
        success: function(response) {
            mapData = response;
            $('#total_cases').text(response.totalCases);
            renderCurrentLayer();
            renderCaseMarkers();
            hideLoading();
        },
        error: function() {
            alert('Gagal memuat data peta');
            hideLoading();
        }
    });
}

function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

// --- Name resolution helpers ---

function resolveKelurahanName(name) {
    if (!name || !mapping) return name;

    let normalized = name;

    if (mapping.normalisasi) {
        if (mapping.normalisasi[normalized]) {
            normalized = mapping.normalisasi[normalized];
        } else {
            const lowerName = normalized.toLowerCase();
            for (const key in mapping.normalisasi) {
                if (key.toLowerCase() === lowerName) {
                    normalized = mapping.normalisasi[key];
                    break;
                }
            }
        }
    }

    if (mapping.kelurahan) {
        if (mapping.kelurahan[normalized]) return mapping.kelurahan[normalized];
        const lowerName = normalized.toLowerCase();
        for (const key in mapping.kelurahan) {
            if (key.toLowerCase() === lowerName) return mapping.kelurahan[key];
        }
    }

    return normalized;
}

function normalizeKelurahan(name) {
    if (!name || !mapping) return name;

    let normalized = name;
    if (mapping.normalisasi && mapping.normalisasi[normalized]) {
        normalized = mapping.normalisasi[normalized];
    }

    if (mapping.rt && mapping.rt.kelurahan_suffix) {
        if (mapping.rt.kelurahan_suffix[normalized]) return normalized;
        const lowerName = normalized.toLowerCase();
        for (const key in mapping.rt.kelurahan_suffix) {
            if (key.toLowerCase() === lowerName) return key;
        }
    }

    return normalized;
}

function convertRTName(rtNumber, kelurahan) {
    if (!rtNumber || !kelurahan) return null;

    const rtNum = parseInt(rtNumber, 10);
    if (isNaN(rtNum)) return null;

    const normalizedKel = normalizeKelurahan(kelurahan);

    let kelSuffix = null;
    if (mapping && mapping.rt && mapping.rt.kelurahan_suffix && normalizedKel) {
        kelSuffix = mapping.rt.kelurahan_suffix[normalizedKel];
    }

    if (!kelSuffix) {
        kelSuffix = kelurahan.toUpperCase().replace(/[^A-Z]/g, '');
        if (kelSuffix.length > 10) kelSuffix = kelSuffix.substring(0, 2);
    }

    return rtNum + kelSuffix;
}

function getPropertyValue(props, keys) {
    if (!props) return null;
    for (const key of keys) {
        if (props[key] !== undefined && props[key] !== null) return props[key];
    }
    return null;
}

function getRtLookup(feature) {
    const props = feature && feature.properties ? feature.properties : null;
    const rtNumber = getPropertyValue(props, ['RT', 'Rt', 'rt', 'NO_RT', 'No_RT', 'no_rt', 'nomor_rt', 'rt_no', 'nama_rt', 'Nama_RT', 'Name', 'NAME']);
    const kelurahan = getPropertyValue(props, ['Kelurahan', 'KELURAHAN', 'kelurahan', 'kel_desa', 'KEL_DESA', 'Kel', 'kel']);

    let dbRTName = null;
    let displayName = null;

    if (rtNumber && kelurahan) {
        dbRTName = convertRTName(rtNumber, kelurahan);
        const parsedRt = parseInt(rtNumber, 10);
        displayName = 'RT ' + (isNaN(parsedRt) ? rtNumber : parsedRt) + ' ' + kelurahan;
    } else if (rtNumber) {
        dbRTName = String(rtNumber).trim();
        displayName = 'RT ' + rtNumber;
    }

    return { dbRTName, displayName };
}

// --- Find case data for a given feature/layer type ---

function findCaseData(featureName, layerType, feature) {
    if (!mapData) return null;

    let casesGroup;
    if (layerType === 'kecamatan') {
        casesGroup = mapData.casesByKecamatan || {};
    } else if (layerType === 'rt') {
        casesGroup = mapData.casesByRT || {};
    } else {
        casesGroup = mapData.casesByKelurahan || {};
    }

    if (layerType === 'rt') {
        const rtInfo = getRtLookup(feature);
        const dbName = rtInfo && rtInfo.dbRTName ? rtInfo.dbRTName : featureName;
        return Object.values(casesGroup).find(item =>
            item.name === dbName || item.name.toLowerCase() === dbName.toLowerCase()
        ) || null;
    }

    if (layerType === 'kelurahan') {
        const mappedName = resolveKelurahanName(featureName);
        return Object.values(casesGroup).find(item =>
            item.name.toLowerCase().includes(mappedName.toLowerCase()) ||
            mappedName.toLowerCase().includes(item.name.toLowerCase()) ||
            item.name.toLowerCase().includes(featureName.toLowerCase()) ||
            featureName.toLowerCase().includes(item.name.toLowerCase())
        ) || null;
    }

    if (layerType === 'kecamatan') {
        const cleanName = featureName.replace('Kecamatan ', '');
        return Object.values(casesGroup).find(item =>
            item.name.toLowerCase().includes(cleanName.toLowerCase()) ||
            cleanName.toLowerCase().includes(item.name.toLowerCase())
        ) || null;
    }

    return null;
}

// --- Styling & Popups ---

function getColorByCount(count) {
    return count > 50 ? '#be123c' :
           count > 20 ? '#f59e0b' :
           count > 10 ? '#fbbf24' :
           count > 0  ? '#0891b2' : '#e5e7eb';
}

function getFeatureName(feature, layerType) {
    const props = feature.properties;
    if (layerType === 'rt') {
        const rtInfo = getRtLookup(feature);
        return rtInfo && rtInfo.displayName ? rtInfo.displayName : (props.Name || props.RT || props.nama_rt || 'Unknown');
    }
    return props.Name || props.nama || props.kel_desa || 'Unknown';
}

function styleFeature(feature, layerType) {
    const name = getFeatureName(feature, layerType);
    const caseData = findCaseData(feature.properties.Name || feature.properties.nama || feature.properties.RT || '', layerType, feature);
    const count = caseData ? caseData.count : 0;

    return {
        fillColor: getColorByCount(count),
        weight: layerType === 'kecamatan' ? 3 : (layerType === 'rt' ? 1 : 2),
        opacity: 1,
        color: layerType === 'kecamatan' ? '#0066cc' : '#ffffff',
        fillOpacity: layerType === 'rt' ? 0.6 : 0.7
    };
}

function buildPopupContent(feature, layerType) {
    const rawName = feature.properties.Name || feature.properties.nama || feature.properties.RT || feature.properties.nama_rt || '';
    const displayName = getFeatureName(feature, layerType);
    const caseData = findCaseData(rawName, layerType, feature);

    let popupContent = '<div style="min-width: 200px;">';
    popupContent += '<h6 class="mb-2"><strong>' + displayName + '</strong></h6>';

    if (caseData && caseData.count > 0) {
        popupContent += '<p class="mb-1"><strong>Total Kasus: ' + caseData.count + '</strong></p>';

        // Group by disease
        const diseaseGroups = {};
        caseData.cases.forEach(c => {
            diseaseGroups[c.disease] = (diseaseGroups[c.disease] || 0) + 1;
        });

        popupContent += '<p class="mb-1"><strong>Per Penyakit:</strong></p><ul class="mb-2">';
        for (const [disease, count] of Object.entries(diseaseGroups)) {
            popupContent += '<li>' + disease + ': ' + count + '</li>';
        }
        popupContent += '</ul>';

        // Recent cases
        const recentCases = caseData.cases.slice(0, 3);
        if (recentCases.length > 0) {
            popupContent += '<p class="mb-1"><strong>Kasus Terbaru:</strong></p><ul class="mb-0">';
            recentCases.forEach(c => {
                const statusBadge = c.status === 'confirmed' ? 'danger' :
                                  (c.status === 'suspected' ? 'warning' : 'secondary');
                popupContent += '<li><small>' + c.nama + ' - ' + c.disease + '<br>' +
                    '<span class="badge badge-' + statusBadge + '">' + c.status + '</span> ' +
                    c.tanggal_onset + '</small></li>';
            });
            popupContent += '</ul>';
        }
    } else {
        popupContent += '<p class="text-muted">Tidak ada kasus</p>';
    }

    popupContent += '</div>';
    return popupContent;
}

// --- Render current layer ---

function renderCurrentLayer() {
    // Remove all existing layers
    ['kecamatan', 'kelurahan', 'rt'].forEach(type => {
        if (geoJsonLayers[type]) {
            map.removeLayer(geoJsonLayers[type]);
            geoJsonLayers[type] = null;
        }
    });

    const type = currentLayer;
    const data = geoJsonData[type];

    if (!data) {
        // GeoJSON not loaded yet, retry after a short delay
        if (!geoJsonData[type] && geoJsonData[type] !== false) {
            showLoading();
            setTimeout(() => {
                hideLoading();
                if (geoJsonData[type]) {
                    renderCurrentLayer();
                } else {
                    alert('Data GeoJSON untuk layer ' + type + ' tidak tersedia');
                    if (type !== 'kelurahan') {
                        switchLayer('kelurahan');
                    }
                }
            }, 1500);
        } else {
            alert('Data GeoJSON untuk layer ' + type + ' tidak tersedia');
            if (type !== 'kelurahan') {
                switchLayer('kelurahan');
            }
        }
        return;
    }

    geoJsonLayers[type] = L.geoJSON(data, {
        style: function(feature) {
            return styleFeature(feature, type);
        },
        onEachFeature: function(feature, layer) {
            layer.bindPopup(buildPopupContent(feature, type));

            layer.on({
                mouseover: function(e) {
                    e.target.setStyle({
                        weight: 4,
                        color: '#666',
                        fillOpacity: 0.9
                    });
                    e.target.bringToFront();
                },
                mouseout: function(e) {
                    if (geoJsonLayers[type]) {
                        geoJsonLayers[type].resetStyle(e.target);
                    }
                }
            });
        }
    }).addTo(map);

    map.fitBounds(geoJsonLayers[type].getBounds());
}

// --- Layer switching ---

function switchLayer(layerType) {
    currentLayer = layerType;

    // Update button states
    ['btnKecamatan', 'btnKelurahan', 'btnRT'].forEach(id => {
        document.getElementById(id).classList.remove('btn-primary');
        document.getElementById(id).classList.add('btn-outline-primary');
    });

    const btnId = layerType === 'rt' ? 'btnRT' : ('btn' + layerType.charAt(0).toUpperCase() + layerType.slice(1));
    document.getElementById(btnId).classList.remove('btn-outline-primary');
    document.getElementById(btnId).classList.add('btn-primary');

    // Update legend title
    const labels = { kecamatan: 'Kecamatan', kelurahan: 'Kelurahan', rt: 'RT' };
    document.getElementById('legend_title').textContent = 'Legenda Kepadatan Kasus per ' + labels[layerType];
    document.getElementById('layer_label').textContent = 'Layer: ' + labels[layerType];

    // Tampilkan/sembunyikan panel kasus RT tidak terdefinisi
    const rtPanel = document.getElementById('undefined-rt-panel');
    if (layerType === 'rt' && mapData && (mapData.undefinedRtCount || 0) > 0) {
        document.getElementById('undefined-rt-count').textContent = mapData.undefinedRtCount;
        rtPanel.style.display = 'block';
    } else {
        rtPanel.style.display = 'none';
    }

    renderCurrentLayer();
}

function showUndefinedRtCases() {
    if (!mapData || !mapData.casesByRT) return;

    // Cari grup dengan undefined=true di casesByRT
    const tbody = document.getElementById('undefined-rt-tbody');
    tbody.innerHTML = '';

    Object.values(mapData.casesByRT).forEach(function(group) {
        if (!group.undefined) return;
        (group.cases || []).forEach(function(c) {
            const row = document.createElement('tr');
            row.innerHTML =
                '<td>' + (c.no_registrasi || '-') + '</td>' +
                '<td>' + (c.nama || '-') + '</td>' +
                '<td>' + (c.kelurahan || '-') + '</td>' +
                '<td>' + (c.disease || '-') + '</td>' +
                '<td><span class="badge badge-secondary">' + (c.status || '-') + '</span></td>' +
                '<td>' + (c.tanggal_onset || '-') + '</td>';
            tbody.appendChild(row);
        });
    });

    if (tbody.children.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Tidak ada data</td></tr>';
    }

    $('#undefinedRtModal').modal('show');
}

// --- Case markers (individual coordinate points) ---

function getMarkerColor(status) {
    switch (status) {
        case 'confirmed': return '#dc3545';
        case 'probable':  return '#ffc107';
        case 'suspected': return '#007bff';
        default:          return '#6c757d';
    }
}

function renderCaseMarkers() {
    // Remove existing markers layer
    if (caseMarkersLayer) {
        map.removeLayer(caseMarkersLayer);
        caseMarkersLayer = null;
    }

    if (!mapData.caseMarkers || mapData.caseMarkers.length === 0) return;
    if (!$('#toggleCaseMarkers').is(':checked')) return;

    caseMarkersLayer = L.layerGroup();

    mapData.caseMarkers.forEach(function(c) {
        var color = getMarkerColor(c.status);
        var circle = L.circleMarker([c.lat, c.lng], {
            radius: 7,
            fillColor: color,
            color: '#fff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.85
        });

        var statusLabel = c.status ? c.status.charAt(0).toUpperCase() + c.status.slice(1) : '-';
        circle.bindPopup(
            '<div style="min-width:180px;">' +
            '<strong>' + c.nama + '</strong><br>' +
            '<small>Penyakit: ' + c.disease + '</small><br>' +
            '<small>Status: <span class="badge badge-' +
                (c.status === 'confirmed' ? 'danger' : (c.status === 'probable' ? 'warning' : (c.status === 'suspected' ? 'primary' : 'secondary'))) +
            '">' + statusLabel + '</span></small><br>' +
            '<small>Onset: ' + c.tanggal_onset + '</small>' +
            '</div>'
        );

        caseMarkersLayer.addLayer(circle);
    });

    caseMarkersLayer.addTo(map);
}

$('#toggleCaseMarkers').on('change', function() {
    if ($(this).is(':checked')) {
        renderCaseMarkers();
    } else if (caseMarkersLayer) {
        map.removeLayer(caseMarkersLayer);
        caseMarkersLayer = null;
    }
});

// Expose to global scope
window.switchLayer = switchLayer;
</script>
@endsection
