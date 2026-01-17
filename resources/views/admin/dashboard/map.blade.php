@extends('admin::layouts.app')
@section('title')
Peta Sebaran Anak - Si Rindu
@endsection
@section('title-content')
Peta Sebaran Anak
@endsection
@section('item')
Dashboard
@endsection
@section('item-active')
Peta
@endsection

@section('content')
<style>
    :root {
        --primary-blue: #0066cc;
        --success-green: #047857;
        --warning-amber: #b45309;
        --danger-rose: #be123c;
        --info-teal: #0891b2;
    }

    #map {
        height: 650px;
        width: 100%;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .map-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .stat-mini {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--info-teal) 100%);
        color: #fff;
        border-radius: 10px;
        padding: 1rem;
        text-align: center;
        margin-bottom: 1rem;
    }

    .stat-mini.success { background: linear-gradient(135deg, var(--success-green) 0%, #10b981 100%); }
    .stat-mini.warning { background: linear-gradient(135deg, var(--warning-amber) 0%, #f59e0b 100%); }
    .stat-mini.danger { background: linear-gradient(135deg, var(--danger-rose) 0%, #f43f5e 100%); }

    .stat-mini h3 { font-size: 1.5rem; font-weight: 700; margin: 0; }
    .stat-mini p { font-size: 0.75rem; margin: 0; opacity: 0.9; }

    .legend-box {
        background: #fff;
        border-radius: 8px;
        padding: 1rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        margin-bottom: 1rem;
    }

    .legend-item {
        display: flex;
        align-items: center;
        margin-bottom: 0.5rem;
        font-size: 0.85rem;
    }

    .legend-color {
        width: 18px;
        height: 18px;
        border-radius: 4px;
        margin-right: 8px;
        flex-shrink: 0;
    }

    .layer-toggle {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .layer-toggle .btn {
        border-radius: 20px;
        padding: 0.4rem 0.8rem;
        font-size: 0.8rem;
    }

    .layer-toggle .btn.active {
        background: var(--primary-blue);
        color: #fff;
        border-color: var(--primary-blue);
    }

    .info-panel {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 1000;
        background: #fff;
        padding: 1rem;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        min-width: 220px;
        max-width: 280px;
        display: none;
    }

    .info-panel.show { display: block; }

    .info-panel h5 {
        margin: 0 0 0.5rem 0;
        color: var(--primary-blue);
        font-weight: 600;
        font-size: 0.95rem;
    }

    .info-stat {
        display: flex;
        justify-content: space-between;
        padding: 0.25rem 0;
        border-bottom: 1px solid #f3f4f6;
        font-size: 0.85rem;
    }

    .info-stat:last-child { border-bottom: none; }

    .leaflet-popup-content-wrapper {
        border-radius: 10px;
    }

    .popup-content h6 {
        margin: 0 0 0.5rem 0;
        color: var(--primary-blue);
        font-weight: 600;
    }

    .popup-stat {
        display: flex;
        justify-content: space-between;
        padding: 0.25rem 0;
        font-size: 0.85rem;
    }

    .badge-count {
        background: var(--primary-blue);
        color: #fff;
        padding: 0.2rem 0.5rem;
        border-radius: 10px;
        font-size: 0.75rem;
    }

    .badge-stunting { background: var(--danger-rose); }
    .badge-wasting { background: var(--warning-amber); }

    .area-list {
        max-height: 400px;
        overflow-y: auto;
    }

    .area-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid #f3f4f6;
        font-size: 0.85rem;
    }

    .area-item:hover {
        background: #f9fafb;
        margin: 0 -0.5rem;
        padding: 0.5rem;
        border-radius: 4px;
    }

    .search-box {
        position: relative;
        margin-bottom: 1rem;
    }

    .search-box input {
        width: 100%;
        padding: 0.5rem 0.75rem 0.5rem 2rem;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-size: 0.85rem;
    }

    .search-box i {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
    }

    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255,255,255,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        border-radius: 12px;
    }
</style>

{{-- Summary Stats --}}
<div class="row">
    <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-mini">
            <h3>{{ number_format($totalAnak) }}</h3>
            <p>Total Anak</p>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-mini success">
            <h3>{{ $kelurahanWithData }}/{{ $totalKelurahan }}</h3>
            <p>Kelurahan</p>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-mini" style="background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);">
            <h3>{{ $rtWithData }}/{{ $totalRT }}</h3>
            <p>RT dengan Data</p>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-mini danger">
            <h3>{{ number_format($totalStunting) }}</h3>
            <p>Stunting</p>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-mini warning">
            <h3>{{ number_format($totalWasting) }}</h3>
            <p>Wasting</p>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-mini" style="background: linear-gradient(135deg, #059669 0%, #34d399 100%);">
            <h3>{{ number_format($totalAnak - $totalStunting - $totalWasting) }}</h3>
            <p>Normal/Lainnya</p>
        </div>
    </div>
</div>

{{-- Map Controls --}}
<div class="map-card">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
        <h5 class="mb-2"><i class="fa fa-map-marked-alt text-primary mr-2"></i> Peta Interaktif Kota Bontang</h5>
        <div class="layer-toggle">
            <button class="btn btn-outline-primary btn-sm" id="btnKecamatan" onclick="showLayer('kecamatan')">
                <i class="fa fa-city mr-1"></i> Kecamatan
            </button>
            <button class="btn btn-outline-primary btn-sm active" id="btnKelurahan" onclick="showLayer('kelurahan')">
                <i class="fa fa-map mr-1"></i> Kelurahan
            </button>
            <button class="btn btn-outline-primary btn-sm" id="btnRT" onclick="showLayer('rt')">
                <i class="fa fa-home mr-1"></i> RT
            </button>
            <span class="mx-2 text-muted">|</span>
            <button class="btn btn-outline-success btn-sm" id="btnCount" onclick="showMode('count')">
                <i class="fa fa-users mr-1"></i> Jumlah
            </button>
            <button class="btn btn-outline-danger btn-sm" id="btnStunting" onclick="showMode('stunting')">
                <i class="fa fa-child mr-1"></i> Stunting
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-9">
            <div style="position: relative;">
                <div id="map"></div>
                <div class="info-panel" id="infoPanel">
                    <h5 id="infoTitle">-</h5>
                    <div id="infoContent"></div>
                </div>
                <div class="loading-overlay" id="loadingOverlay" style="display: none;">
                    <div class="text-center">
                        <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                        <p class="mt-2 text-muted">Memuat data...</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="legend-box">
                <h6 class="mb-2"><i class="fa fa-palette mr-1"></i> Legenda</h6>
                <div id="legendContent">
                    <div class="legend-item"><div class="legend-color" style="background: #047857;"></div><span>>50 anak</span></div>
                    <div class="legend-item"><div class="legend-color" style="background: #0891b2;"></div><span>21-50 anak</span></div>
                    <div class="legend-item"><div class="legend-color" style="background: #f59e0b;"></div><span>6-20 anak</span></div>
                    <div class="legend-item"><div class="legend-color" style="background: #fbbf24;"></div><span>1-5 anak</span></div>
                    <div class="legend-item"><div class="legend-color" style="background: #e5e7eb;"></div><span>Tidak ada data</span></div>
                </div>
            </div>

            <div class="legend-box">
                <h6 class="mb-2"><i class="fa fa-list mr-1"></i> Daftar Wilayah</h6>
                <div class="search-box">
                    <i class="fa fa-search"></i>
                    <input type="text" id="searchArea" placeholder="Cari wilayah..." onkeyup="filterAreaList()">
                </div>
                <div class="area-list" id="areaList">
                    @foreach($kelurahanStats->sortByDesc('total_anak') as $name => $data)
                    <div class="area-item" data-name="{{ strtolower($name) }}">
                        <span>{{ $name }}</span>
                        <div>
                            <span class="badge-count">{{ $data->total_anak }}</span>
                            @if(isset($kelurahanZScore[$name]) && $kelurahanZScore[$name]['stunting'] > 0)
                            <span class="badge-count badge-stunting ml-1" title="Stunting">{{ $kelurahanZScore[$name]['stunting'] }}</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Statistics by Area --}}
<div class="row">
    <div class="col-lg-6">
        <div class="map-card">
            <h5 class="mb-3"><i class="fa fa-chart-bar text-primary mr-2"></i> Top 15 Kelurahan</h5>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Kelurahan</th>
                            <th class="text-center">Anak</th>
                            <th class="text-center">Stunting</th>
                            <th class="text-center">Wasting</th>
                            <th class="text-center">Normal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kelurahanStats->sortByDesc('total_anak')->take(15) as $name => $data)
                        <tr>
                            <td><strong>{{ $name }}</strong></td>
                            <td class="text-center">{{ $data->total_anak }}</td>
                            <td class="text-center">
                                @if(isset($kelurahanZScore[$name]))
                                <span class="text-danger">{{ $kelurahanZScore[$name]['stunting'] }}</span>
                                @else - @endif
                            </td>
                            <td class="text-center">
                                @if(isset($kelurahanZScore[$name]))
                                <span class="text-warning">{{ $kelurahanZScore[$name]['wasting'] }}</span>
                                @else - @endif
                            </td>
                            <td class="text-center">
                                @if(isset($kelurahanZScore[$name]))
                                <span class="text-success">{{ $kelurahanZScore[$name]['normal'] }}</span>
                                @else - @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="map-card">
            <h5 class="mb-3"><i class="fa fa-hospital text-success mr-2"></i> Top 15 Posyandu</h5>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Posyandu</th>
                            <th>Kelurahan</th>
                            <th class="text-center">Anak</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($posyanduStats->take(15) as $pos)
                        <tr>
                            <td><strong>{{ $pos->posyandu_name }}</strong></td>
                            <td>{{ $pos->kelurahan_name }}</td>
                            <td class="text-center"><span class="badge badge-primary">{{ $pos->total_anak }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@parent
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map centered on Bontang
    const map = L.map('map').setView([0.1236, 117.4753], 12);

    // Base tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    // Data from PHP
    const kelurahanStats = @json($kelurahanStats);
    const kecamatanStats = @json($kecamatanStats);
    const rtStats = @json($rtStats);
    const kelurahanZScore = @json($kelurahanZScore);
    const rtZScore = @json($rtZScore);

    // Mapping from GeoJSON names
    const mapping = @json(json_decode(file_get_contents(public_path('geojson/mapping.json')), true));

    // Function to normalize kelurahan name for lookup
    function normalizeKelurahan(kelurahan) {
        if (!kelurahan) return null;

        // Try direct lookup first
        if (mapping.rt && mapping.rt.kelurahan_suffix && mapping.rt.kelurahan_suffix[kelurahan]) {
            return kelurahan;
        }

        // Try case-insensitive match
        if (mapping.rt && mapping.rt.kelurahan_suffix) {
            const lowerKel = kelurahan.toLowerCase();
            for (const key in mapping.rt.kelurahan_suffix) {
                if (key.toLowerCase() === lowerKel) {
                    return key;
                }
            }

            // Try normalized match (e.g., "API-API" -> "Api-Api")
            const normalized = kelurahan.split(/[-\s]/).map(w =>
                w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()
            ).join('-');
            if (mapping.rt.kelurahan_suffix[normalized]) {
                return normalized;
            }
        }

        return kelurahan;
    }

    // Function to convert GeoJSON RT name to database format
    // GeoJSON: RT="021", Kelurahan="API-API" => Database: "21AA"
    function convertRTName(rtNumber, kelurahan) {
        if (!rtNumber || !kelurahan) return null;

        // Remove leading zeros from RT number
        const rtNum = parseInt(rtNumber, 10);
        if (isNaN(rtNum)) return null;

        // Normalize kelurahan name for lookup
        const normalizedKel = normalizeKelurahan(kelurahan);

        // Get kelurahan suffix from mapping
        let kelSuffix = null;
        if (mapping.rt && mapping.rt.kelurahan_suffix && normalizedKel) {
            kelSuffix = mapping.rt.kelurahan_suffix[normalizedKel];
        }

        // Fallback: generate suffix from kelurahan name
        if (!kelSuffix) {
            kelSuffix = kelurahan.toUpperCase().replace(/[^A-Z]/g, '');
            if (kelSuffix.length > 10) {
                kelSuffix = kelSuffix.substring(0, 2);
            }
        }

        return rtNum + kelSuffix;
    }

    // Layer groups
    let kelurahanLayer = null;
    let kecamatanLayer = null;
    let rtLayer = null;
    let rtLayerLoading = true;
    let rtLayerError = false;
    let currentLayer = 'kelurahan';
    let currentMode = 'count'; // 'count' or 'stunting'

    // Color functions for count mode
    function getColorByCount(count) {
        if (count > 50) return '#047857';
        if (count > 20) return '#0891b2';
        if (count > 5) return '#f59e0b';
        if (count >= 1) return '#fbbf24';
        return '#e5e7eb';
    }

    // Color functions for stunting mode
    function getColorByStunting(stats) {
        if (!stats || stats.total === 0) return '#e5e7eb';
        const rate = (stats.stunting / stats.total) * 100;
        if (rate > 30) return '#be123c';
        if (rate > 20) return '#e11d48';
        if (rate > 10) return '#f59e0b';
        if (rate > 0) return '#fbbf24';
        return '#047857';
    }

    // Style functions
    function getStyle(name, type, feature = null) {
        let stats, zScore, count = 0;

        if (type === 'kelurahan') {
            const mappedName = mapping.kelurahan && mapping.kelurahan[name] ? mapping.kelurahan[name] : name;
            stats = kelurahanStats[mappedName];
            zScore = kelurahanZScore[mappedName];
            count = stats ? stats.total_anak : 0;
        } else if (type === 'kecamatan') {
            const cleanName = name.replace('Kecamatan ', '');
            stats = kecamatanStats[cleanName];
            count = stats ? stats.total_anak : 0;
        } else if (type === 'rt') {
            // Convert GeoJSON RT format to database format
            let dbRTName = name;
            if (feature && feature.properties) {
                const rtNumber = feature.properties.RT;
                const kelurahan = feature.properties.Kelurahan;
                if (rtNumber && kelurahan) {
                    dbRTName = convertRTName(rtNumber, kelurahan);
                }
            }
            stats = rtStats[dbRTName];
            zScore = rtZScore[dbRTName];
            count = stats ? stats.total_anak : 0;
        }

        const color = currentMode === 'stunting' && zScore
            ? getColorByStunting(zScore)
            : getColorByCount(count);

        return {
            fillColor: color,
            weight: type === 'kecamatan' ? 3 : (type === 'rt' ? 1 : 2),
            opacity: 1,
            color: type === 'kecamatan' ? '#0066cc' : '#ffffff',
            fillOpacity: type === 'rt' ? 0.6 : 0.7
        };
    }

    function kelurahanStyle(feature) {
        const name = feature.properties.Name || feature.properties.kel_desa;
        return getStyle(name, 'kelurahan');
    }

    function kecamatanStyle(feature) {
        const name = feature.properties.Name || feature.properties.nama;
        return getStyle(name, 'kecamatan');
    }

    function rtStyle(feature) {
        const name = feature.properties.RT || feature.properties.Name || feature.properties.nama_rt;
        return getStyle(name, 'rt', feature);
    }

    // Popup content
    function getPopupContent(name, type, feature = null) {
        let content = '<div class="popup-content">';
        let displayName = name;

        let stats, zScore;
        if (type === 'kelurahan') {
            const mappedName = mapping.kelurahan && mapping.kelurahan[name] ? mapping.kelurahan[name] : name;
            stats = kelurahanStats[mappedName];
            zScore = kelurahanZScore[mappedName];
        } else if (type === 'kecamatan') {
            const cleanName = name.replace('Kecamatan ', '');
            stats = kecamatanStats[cleanName];
        } else if (type === 'rt') {
            // Convert GeoJSON RT format to database format for lookup
            let dbRTName = name;
            if (feature && feature.properties) {
                const rtNumber = feature.properties.RT;
                const kelurahan = feature.properties.Kelurahan;
                if (rtNumber && kelurahan) {
                    dbRTName = convertRTName(rtNumber, kelurahan);
                    displayName = 'RT ' + parseInt(rtNumber, 10) + ' ' + kelurahan;
                }
            }
            stats = rtStats[dbRTName];
            zScore = rtZScore[dbRTName];
        }

        content += '<h6>' + displayName + '</h6>';
        content += '<div class="popup-stat"><span>Jumlah Anak:</span><strong>' + (stats ? stats.total_anak : 0) + '</strong></div>';

        if (zScore) {
            content += '<div class="popup-stat"><span>Normal:</span><strong style="color:#047857">' + zScore.normal + '</strong></div>';
            content += '<div class="popup-stat"><span>Stunting:</span><strong style="color:#be123c">' + zScore.stunting + '</strong></div>';
            content += '<div class="popup-stat"><span>Wasting:</span><strong style="color:#f59e0b">' + zScore.wasting + '</strong></div>';
            content += '<div class="popup-stat"><span>Overweight:</span><strong style="color:#0891b2">' + zScore.overweight + '</strong></div>';
            if (zScore.total > 0) {
                const stuntingRate = ((zScore.stunting / zScore.total) * 100).toFixed(1);
                content += '<div class="popup-stat"><span>Prevalensi Stunting:</span><strong>' + stuntingRate + '%</strong></div>';
            }
        }

        content += '</div>';
        return content;
    }

    // Event handlers
    function onEachFeature(feature, layer, type) {
        let name = feature.properties.Name || feature.properties.kel_desa || feature.properties.nama || feature.properties.RT || feature.properties.nama_rt;
        let displayName = name;

        // For RT, create proper display name and lookup key
        let dbRTName = name;
        if (type === 'rt' && feature.properties) {
            const rtNumber = feature.properties.RT;
            const kelurahan = feature.properties.Kelurahan;
            if (rtNumber && kelurahan) {
                dbRTName = convertRTName(rtNumber, kelurahan);
                displayName = 'RT ' + parseInt(rtNumber, 10) + ' ' + kelurahan;
            }
        }

        layer.on({
            mouseover: function(e) {
                const layer = e.target;
                layer.setStyle({ weight: 4, color: '#0066cc', fillOpacity: 0.9 });
                layer.bringToFront();

                document.getElementById('infoTitle').textContent = displayName;
                document.getElementById('infoPanel').classList.add('show');

                let infoHtml = '';
                let stats, zScore;

                if (type === 'kelurahan') {
                    const mappedName = mapping.kelurahan && mapping.kelurahan[name] ? mapping.kelurahan[name] : name;
                    stats = kelurahanStats[mappedName];
                    zScore = kelurahanZScore[mappedName];
                } else if (type === 'kecamatan') {
                    const cleanName = name.replace('Kecamatan ', '');
                    stats = kecamatanStats[cleanName];
                } else if (type === 'rt') {
                    stats = rtStats[dbRTName];
                    zScore = rtZScore[dbRTName];
                }

                infoHtml += '<div class="info-stat"><span>Jumlah Anak</span><strong>' + (stats ? stats.total_anak : 0) + '</strong></div>';
                if (zScore) {
                    infoHtml += '<div class="info-stat"><span>Normal</span><strong class="text-success">' + zScore.normal + '</strong></div>';
                    infoHtml += '<div class="info-stat"><span>Stunting</span><strong class="text-danger">' + zScore.stunting + '</strong></div>';
                    infoHtml += '<div class="info-stat"><span>Wasting</span><strong class="text-warning">' + zScore.wasting + '</strong></div>';
                }
                document.getElementById('infoContent').innerHTML = infoHtml;
            },
            mouseout: function(e) {
                if (type === 'kelurahan' && kelurahanLayer) kelurahanLayer.resetStyle(e.target);
                else if (type === 'kecamatan' && kecamatanLayer) kecamatanLayer.resetStyle(e.target);
                else if (type === 'rt' && rtLayer) rtLayer.resetStyle(e.target);
                document.getElementById('infoPanel').classList.remove('show');
            },
            click: function(e) {
                map.fitBounds(e.target.getBounds());
            }
        });

        layer.bindPopup(getPopupContent(name, type, feature));
    }

    // Show loading
    function showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }

    function hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }

    // Load GeoJSON layers
    showLoading();

    fetch('/geojson/Kota Bontang-KEL_DESA.geojson')
        .then(response => response.json())
        .then(data => {
            kelurahanLayer = L.geoJSON(data, {
                style: kelurahanStyle,
                onEachFeature: (feature, layer) => onEachFeature(feature, layer, 'kelurahan')
            }).addTo(map);
            map.fitBounds(kelurahanLayer.getBounds());
            hideLoading();
        })
        .catch(err => { console.error('Error loading kelurahan:', err); hideLoading(); });

    fetch('/geojson/Kota Bontang-KECAMATAN.geojson')
        .then(response => response.json())
        .then(data => {
            kecamatanLayer = L.geoJSON(data, {
                style: kecamatanStyle,
                onEachFeature: (feature, layer) => onEachFeature(feature, layer, 'kecamatan')
            });
        });

    // Load RT GeoJSON if available
    fetch('/geojson/batas-rt-bontang.geojson')
        .then(response => {
            if (!response.ok) throw new Error('RT GeoJSON not found');
            return response.json();
        })
        .then(data => {
            rtLayer = L.geoJSON(data, {
                style: rtStyle,
                onEachFeature: (feature, layer) => onEachFeature(feature, layer, 'rt')
            });
            rtLayerLoading = false;
            console.log('RT GeoJSON loaded successfully, features:', data.features ? data.features.length : 0);
        })
        .catch(err => {
            console.log('RT GeoJSON not found or error:', err);
            rtLayerLoading = false;
            rtLayerError = true;
        });

    // Update legend
    function updateLegend() {
        const legendContent = document.getElementById('legendContent');
        if (currentMode === 'stunting') {
            legendContent.innerHTML = `
                <div class="legend-item"><div class="legend-color" style="background: #be123c;"></div><span>Stunting >30%</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #e11d48;"></div><span>Stunting 20-30%</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #f59e0b;"></div><span>Stunting 10-20%</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #fbbf24;"></div><span>Stunting <10%</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #047857;"></div><span>Tidak ada stunting</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #e5e7eb;"></div><span>Tidak ada data</span></div>
            `;
        } else {
            legendContent.innerHTML = `
                <div class="legend-item"><div class="legend-color" style="background: #047857;"></div><span>>50 anak</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #0891b2;"></div><span>21-50 anak</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #f59e0b;"></div><span>6-20 anak</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #fbbf24;"></div><span>1-5 anak</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #e5e7eb;"></div><span>Tidak ada data</span></div>
            `;
        }
    }

    // Refresh all layers with current style
    function refreshLayers() {
        if (kelurahanLayer) kelurahanLayer.setStyle(kelurahanStyle);
        if (kecamatanLayer) kecamatanLayer.setStyle(kecamatanStyle);
        if (rtLayer) rtLayer.setStyle(rtStyle);
    }

    // Layer toggle function
    window.showLayer = function(layerType) {
        currentLayer = layerType;

        document.querySelectorAll('.layer-toggle .btn').forEach(btn => {
            if (btn.id.startsWith('btn') && !btn.id.includes('Count') && !btn.id.includes('Stunting')) {
                btn.classList.remove('active');
            }
        });

        // Handle special case for RT (uppercase)
        let btnId = 'btn' + layerType.charAt(0).toUpperCase() + layerType.slice(1);
        if (layerType === 'rt') btnId = 'btnRT';

        const btn = document.getElementById(btnId);
        if (btn) btn.classList.add('active');

        // Remove all layers
        if (kelurahanLayer) map.removeLayer(kelurahanLayer);
        if (kecamatanLayer) map.removeLayer(kecamatanLayer);
        if (rtLayer) map.removeLayer(rtLayer);

        // Add selected layer
        if (layerType === 'kelurahan' && kelurahanLayer) {
            kelurahanLayer.addTo(map);
        } else if (layerType === 'kecamatan' && kecamatanLayer) {
            kecamatanLayer.addTo(map);
        } else if (layerType === 'rt') {
            if (rtLayerLoading) {
                showLoading();
                // Wait for RT layer to finish loading
                const checkRTLayer = setInterval(() => {
                    if (!rtLayerLoading) {
                        clearInterval(checkRTLayer);
                        hideLoading();
                        if (rtLayer) {
                            rtLayer.addTo(map);
                        } else if (rtLayerError) {
                            alert('Data RT GeoJSON tidak tersedia atau gagal dimuat');
                            showLayer('kelurahan');
                        }
                    }
                }, 100);
            } else if (rtLayer) {
                rtLayer.addTo(map);
            } else if (rtLayerError) {
                alert('Data RT GeoJSON tidak tersedia');
                showLayer('kelurahan');
            }
        }
    };

    // Mode toggle function
    window.showMode = function(mode) {
        currentMode = mode;

        const btnCount = document.getElementById('btnCount');
        const btnStunting = document.getElementById('btnStunting');
        if (btnCount) btnCount.classList.remove('active');
        if (btnStunting) btnStunting.classList.remove('active');

        const modeBtn = document.getElementById('btn' + mode.charAt(0).toUpperCase() + mode.slice(1));
        if (modeBtn) modeBtn.classList.add('active');

        updateLegend();
        refreshLayers();
    };

    // Search filter
    window.filterAreaList = function() {
        const search = document.getElementById('searchArea').value.toLowerCase();
        document.querySelectorAll('.area-item').forEach(item => {
            const name = item.dataset.name;
            item.style.display = name.includes(search) ? 'flex' : 'none';
        });
    };
});
</script>
@endsection
