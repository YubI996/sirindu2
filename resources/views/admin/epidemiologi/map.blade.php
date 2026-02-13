@extends('admin::layouts.app')
@section('title') Admin @endsection
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
                    <h6><i class="fa fa-info-circle"></i> Legenda Kepadatan Kasus per Kelurahan</h6>
                    <div class="d-flex align-items-center">
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
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fa fa-globe"></i> Peta Interaktif Sebaran Kasus</h5>
        </div>
        <div class="card-body p-0">
            <div id="map" style="height: 600px; width: 100%;"></div>
        </div>
    </div>

    <!-- Instructions -->
    <div class="alert alert-info mt-3">
        <i class="fa fa-lightbulb"></i> <strong>Cara Penggunaan:</strong>
        <ul class="mb-0">
            <li>Klik pada wilayah kelurahan untuk melihat detail kasus</li>
            <li>Gunakan filter untuk menyaring data berdasarkan penyakit, status, atau periode waktu</li>
            <li>Warna wilayah menunjukkan kepadatan kasus (semakin merah = semakin banyak kasus)</li>
        </ul>
    </div>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let map;
let geoJsonLayer;

$(document).ready(function() {
    // Initialize map centered on Bontang
    map = L.map('map').setView([0.1236, 117.4753], 12);

    // Add OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 18
    }).addTo(map);

    // Load initial data
    loadMapData();

    // Filter buttons
    $('#apply_filter').on('click', loadMapData);

    $('#reset_filter').on('click', function() {
        $('#disease_filter').val('');
        $('#status_filter').val('');
        $('#start_date').val('{{ date('Y-m-01') }}');
        $('#end_date').val('{{ date('Y-m-d') }}');
        loadMapData();
    });
});

function loadMapData() {
    const disease = $('#disease_filter').val();
    const status = $('#status_filter').val();
    const startDate = $('#start_date').val();
    const endDate = $('#end_date').val();

    $.ajax({
        url: '{{ route("admin.epidemiologi.mapData") }}',
        type: 'GET',
        data: {
            disease_id: disease,
            status: status,
            start_date: startDate,
            end_date: endDate
        },
        success: function(response) {
            $('#total_cases').text(response.totalCases);
            renderMap(response.casesByKelurahan);
        },
        error: function() {
            alert('Gagal memuat data peta');
        }
    });
}

function renderMap(casesByKelurahan) {
    // Remove existing layer if any
    if (geoJsonLayer) {
        map.removeLayer(geoJsonLayer);
    }

    // Load GeoJSON file for Bontang
    fetch('/geojson/Kota Bontang-KEL_DESA.geojson')
        .then(response => response.json())
        .then(data => {
            geoJsonLayer = L.geoJSON(data, {
                style: function(feature) {
                    const kelName = feature.properties.Name;
                    const caseData = Object.values(casesByKelurahan).find(item =>
                        item.name.toLowerCase().includes(kelName.toLowerCase()) ||
                        kelName.toLowerCase().includes(item.name.toLowerCase())
                    );
                    const count = caseData ? caseData.count : 0;

                    return {
                        fillColor: getColorByCount(count),
                        weight: 2,
                        opacity: 1,
                        color: '#ffffff',
                        fillOpacity: 0.7
                    };
                },
                onEachFeature: function(feature, layer) {
                    const kelName = feature.properties.Name;
                    const caseData = Object.values(casesByKelurahan).find(item =>
                        item.name.toLowerCase().includes(kelName.toLowerCase()) ||
                        kelName.toLowerCase().includes(item.name.toLowerCase())
                    );

                    let popupContent = `<div style="min-width: 200px;">
                        <h6 class="mb-2"><strong>${kelName}</strong></h6>`;

                    if (caseData && caseData.count > 0) {
                        popupContent += `<p class="mb-1"><strong>Total Kasus: ${caseData.count}</strong></p>`;

                        // Group cases by disease
                        const diseaseGroups = {};
                        caseData.cases.forEach(c => {
                            if (!diseaseGroups[c.disease]) {
                                diseaseGroups[c.disease] = 0;
                            }
                            diseaseGroups[c.disease]++;
                        });

                        popupContent += '<p class="mb-1"><strong>Per Penyakit:</strong></p><ul class="mb-2">';
                        for (const [disease, count] of Object.entries(diseaseGroups)) {
                            popupContent += `<li>${disease}: ${count}</li>`;
                        }
                        popupContent += '</ul>';

                        // Show recent cases
                        const recentCases = caseData.cases.slice(0, 3);
                        if (recentCases.length > 0) {
                            popupContent += '<p class="mb-1"><strong>Kasus Terbaru:</strong></p><ul class="mb-0">';
                            recentCases.forEach(c => {
                                const statusBadge = c.status === 'confirmed' ? 'danger' :
                                                  (c.status === 'suspected' ? 'warning' : 'secondary');
                                popupContent += `<li><small>
                                    ${c.nama} - ${c.disease}<br>
                                    <span class="badge badge-${statusBadge}">${c.status}</span>
                                    ${c.tanggal_onset}
                                </small></li>`;
                            });
                            popupContent += '</ul>';
                        }
                    } else {
                        popupContent += '<p class="text-muted">Tidak ada kasus</p>';
                    }

                    popupContent += '</div>';

                    layer.bindPopup(popupContent);

                    // Highlight on hover
                    layer.on({
                        mouseover: function(e) {
                            const layer = e.target;
                            layer.setStyle({
                                weight: 4,
                                color: '#666',
                                fillOpacity: 0.9
                            });
                        },
                        mouseout: function(e) {
                            geoJsonLayer.resetStyle(e.target);
                        }
                    });
                }
            }).addTo(map);

            // Fit map to GeoJSON bounds
            map.fitBounds(geoJsonLayer.getBounds());
        })
        .catch(error => {
            console.error('Error loading GeoJSON:', error);
            // If GeoJSON file doesn't exist, show message
            const message = L.popup()
                .setLatLng([0.1236, 117.4753])
                .setContent('File GeoJSON tidak ditemukan. Upload file /public/geojson/Kota Bontang-KEL_DESA.geojson')
                .openOn(map);
        });
}

function getColorByCount(count) {
    return count > 50 ? '#be123c' :
           count > 20 ? '#f59e0b' :
           count > 10 ? '#fbbf24' :
           count > 0  ? '#0891b2' : '#e5e7eb';
}
</script>
@endsection
