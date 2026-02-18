@extends('admin::layouts.app')
@section('title') Peta Sebaran Epidemiologi - Si Rindu @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Epidemiologi @endsection
@section('item-active') Peta Sebaran @endsection

@section('content')
<div class="row mb-3">
    <div class="col-md-3">
        <select id="map-disease" class="form-control form-control-sm">
            <option value="">Semua Penyakit</option>
            @foreach($diseases as $d)
            <option value="{{ $d->id }}">{{ $d->nama_penyakit }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select id="map-status" class="form-control form-control-sm">
            <option value="">Semua Status</option>
            <option value="suspek">Suspek</option>
            <option value="probable">Probable</option>
            <option value="konfirmasi">Konfirmasi</option>
            <option value="discarded">Discarded</option>
        </select>
    </div>
    <div class="col-md-2">
        <input type="date" id="map-start" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
        <input type="date" id="map-end" class="form-control form-control-sm">
    </div>
    <div class="col-md-1">
        <button id="btn-map-filter" class="btn btn-primary btn-sm btn-block">Tampilkan</button>
    </div>
    <div class="col-md-2">
        <div class="card p-2" style="font-size:0.75rem;">
            <strong>Legenda:</strong>
            <span class="badge" style="background:#e74a3b;">&nbsp;</span> &gt;50 &nbsp;
            <span class="badge" style="background:#f0ad4e;">&nbsp;</span> &gt;20 &nbsp;
            <span class="badge" style="background:#f6c23e;">&nbsp;</span> &gt;10 &nbsp;
            <span class="badge" style="background:#36b9cc;">&nbsp;</span> &gt;0 &nbsp;
            <span class="badge" style="background:#ccc;">&nbsp;</span> 0
        </div>
    </div>
</div>

<div class="card shadow">
    <div class="card-body p-0">
        <div id="epi-map" style="height:580px; width:100%; border-radius:0 0 4px 4px;"></div>
    </div>
</div>
@endsection

@section('custom_scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var map = L.map('epi-map').setView([-0.1322, 117.4989], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap'
}).addTo(map);

var geojsonLayer = null;
var caseData     = {};

function getColor(n) {
    return n > 50 ? '#e74a3b' : n > 20 ? '#f0ad4e' : n > 10 ? '#f6c23e' : n > 0 ? '#36b9cc' : '#ccc';
}

function loadMapData() {
    var params = {
        id_jenis_kasus: $('#map-disease').val(),
        status_kasus:   $('#map-status').val(),
        start_date:     $('#map-start').val(),
        end_date:       $('#map-end').val(),
    };
    $.get('{{ route("admin.epidemiologi.mapData") }}', params, function(data) {
        caseData = {};
        data.forEach(function(d) { caseData[d.nama_kel] = d; });
        if (geojsonLayer) loadGeoJSON();
    });
}

function loadGeoJSON() {
    fetch('/geojson/Kota Bontang-KEL_DESA.geojson')
        .then(r => r.json())
        .then(function(geo) {
            if (geojsonLayer) { geojsonLayer.remove(); }
            geojsonLayer = L.geoJSON(geo, {
                style: function(feature) {
                    var name  = feature.properties.DESA || feature.properties.NAMOBJ || '';
                    var match = Object.keys(caseData).find(k => name.toLowerCase().includes(k.toLowerCase()) || k.toLowerCase().includes(name.toLowerCase()));
                    var n     = match ? caseData[match].total : 0;
                    return { fillColor: getColor(n), weight: 1, color: '#555', fillOpacity: 0.7 };
                },
                onEachFeature: function(feature, layer) {
                    var name  = feature.properties.DESA || feature.properties.NAMOBJ || 'Unknown';
                    var match = Object.keys(caseData).find(k => name.toLowerCase().includes(k.toLowerCase()) || k.toLowerCase().includes(name.toLowerCase()));
                    var info  = match ? caseData[match] : null;

                    var popup = '<strong>' + name + '</strong><br>Total kasus: <b>' + (info ? info.total : 0) + '</b>';
                    if (info && info.by_disease.length > 0) {
                        popup += '<br><small>';
                        info.by_disease.forEach(function(d) { popup += d.nama + ': ' + d.total + '<br>'; });
                        popup += '</small>';
                    }
                    if (info && info.recent.length > 0) {
                        popup += '<br><small><em>Terbaru:</em><br>';
                        info.recent.forEach(function(r) { popup += '• ' + r.nama + ' (' + r.tanggal_onset + ')<br>'; });
                        popup += '</small>';
                    }

                    layer.bindPopup(popup);
                    layer.on('mouseover', function() { this.setStyle({ weight: 3, fillOpacity: 0.9 }); });
                    layer.on('mouseout',  function() { geojsonLayer.resetStyle(this); });
                }
            }).addTo(map);
        })
        .catch(function() {
            console.warn('GeoJSON tidak ditemukan, menampilkan data tanpa peta.');
        });
}

// Initial load
$.get('{{ route("admin.epidemiologi.mapData") }}', {}, function(data) {
    caseData = {};
    data.forEach(function(d) { caseData[d.nama_kel] = d; });
    loadGeoJSON();
});

$('#btn-map-filter').on('click', loadMapData);
</script>
@endsection
