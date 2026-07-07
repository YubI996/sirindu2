{{-- Map Coordinate Picker - Leaflet with Esri Satellite --}}
<div class="row mt-3">
    <div class="col-md-12">
        <div class="form-group">
            <label><i class="fa fa-map-marker-alt"></i> Titik Koordinat Lokasi Kejadian</label>
            <small class="form-text text-muted mb-2">
                Klik peta, seret marker, atau gunakan GPS untuk menentukan lokasi kejadian.
                Kecamatan/Kelurahan/RT akan terisi otomatis dari titik yang dipilih.
            </small>

            {{-- Toolbar GPS + Sesuaikan --}}
            <div class="d-flex align-items-center mb-2 flex-wrap gap-2">
                <button type="button" id="btnGpsLocate" class="btn btn-sm btn-outline-primary mr-2">
                    <i class="fa fa-crosshairs"></i> Titik Lokasi Ini
                </button>
                <div class="input-group input-group-sm mr-2" style="width:180px;">
                    <div class="input-group-prepend"><span class="input-group-text">Lat</span></div>
                    <input type="text" id="latDisplay" class="form-control form-control-sm" placeholder="Latitude">
                </div>
                <div class="input-group input-group-sm mr-2" style="width:195px;">
                    <div class="input-group-prepend"><span class="input-group-text">Lng</span></div>
                    <input type="text" id="lngDisplay" class="form-control form-control-sm" placeholder="Longitude">
                </div>
                <button type="button" id="btnSesuaikan" class="btn btn-sm btn-outline-secondary mr-2">
                    <i class="fa fa-search-location"></i> Sesuaikan
                </button>
                <button type="button" id="resetMarkerBtn" class="btn btn-sm btn-outline-danger" style="display:none;">
                    <i class="fa fa-times"></i> Hapus
                </button>
                <span id="gpsStatus" class="text-muted small ml-2"></span>
            </div>

            <div id="mapPickerContainer" style="position: relative; isolation: isolate;">
                <div id="mapPicker" style="height: 380px; width: 100%; border: 1px solid #ced4da; border-radius: 4px;"></div>
            </div>

            <input type="hidden" name="latitude" id="inputLatitude" value="{{ old('latitude', $case->latitude ?? '') }}">
            <input type="hidden" name="longitude" id="inputLongitude" value="{{ old('longitude', $case->longitude ?? '') }}">
        </div>
    </div>
</div>

@push('js')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .kel-boundary-label {
        background: rgba(0,0,0,0.55);
        border: none; box-shadow: none;
        color: #fff; font-weight: 600;
        padding: 2px 6px; border-radius: 3px;
        white-space: nowrap; letter-spacing: 0.5px;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
        pointer-events: none;
    }
    .kel-boundary-label::before { display: none; }
    .rt-label {
        background: rgba(0,180,255,0.75);
        border: none; box-shadow: none;
        color: #fff; font-weight: 700;
        padding: 1px 4px; border-radius: 2px;
        white-space: nowrap;
        pointer-events: none;
        transition: font-size 0.1s;
    }
    .rt-label::before { display: none; }
</style>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
$(document).ready(function() {
    var defaultLat = 0.1237, defaultLng = 117.4907, defaultZoom = 13;
    var existingLat = $('#inputLatitude').val();
    var existingLng = $('#inputLongitude').val();
    var centerLat = existingLat ? parseFloat(existingLat) : defaultLat;
    var centerLng = existingLng ? parseFloat(existingLng) : defaultLng;
    var initZoom  = existingLat ? 16 : defaultZoom;

    var pickerMap = L.map('mapPicker').setView([centerLat, centerLng], initZoom);

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri', maxZoom: 23, maxNativeZoom: 19
    }).addTo(pickerMap);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; CARTO', subdomains: 'abcd', maxZoom: 23, maxNativeZoom: 18, pane: 'overlayPane'
    }).addTo(pickerMap);

    // ===== Batas kelurahan =====
    var kelBoundaryLayer = null;
    var kelLabelLayer = L.layerGroup().addTo(pickerMap);

    function renderKelLabels() {
        kelLabelLayer.clearLayers();
        if (!kelBoundaryLayer) return;
        kelBoundaryLayer.eachLayer(function(layer) {
            var name = layer.feature.properties.Name || layer.feature.properties.kel_desa || '';
            if (!name) return;
            var center = layer.getBounds().getCenter();
            kelLabelLayer.addLayer(
                L.tooltip({ permanent: true, direction: 'center', className: 'kel-boundary-label', interactive: false })
                 .setLatLng(center).setContent(name)
            );
        });
    }

    fetch('/geojson/Kota Bontang-KEL_DESA.geojson')
        .then(function(r){ return r.json(); })
        .then(function(data) {
            kelBoundaryLayer = L.geoJSON(data, {
                style: { color: '#ffffff', weight: 2, opacity: 0.8, dashArray: '6 4', fillOpacity: 0 },
                interactive: false
            }).addTo(pickerMap);
            renderKelLabels();
        }).catch(function(){});

    // ===== Batas RT + Label RT responsif zoom =====
    var rtGeoJsonData = null;
    var rtBoundaryLayer = null;
    var rtLabelLayer = L.layerGroup().addTo(pickerMap);

    function getRtLabelStyle(zoom) {
        if (zoom < 14) return null;           // sembunyikan
        if (zoom <= 15) return '8px';
        if (zoom <= 17) return '10px';
        return '12px';
    }

    function renderRtLabels() {
        rtLabelLayer.clearLayers();
        if (!rtGeoJsonData) return;
        var zoom = pickerMap.getZoom();
        var fontSize = getRtLabelStyle(zoom);
        if (!fontSize) return;

        rtGeoJsonData.features.forEach(function(feature) {
            var rtName = feature.properties.RT || '';
            if (!rtName) return;
            // Hitung centroid sederhana dari polygon
            var coords = feature.geometry.type === 'Polygon'
                ? feature.geometry.coordinates[0]
                : feature.geometry.coordinates[0][0];
            var sumLat = 0, sumLng = 0;
            coords.forEach(function(c) { sumLng += c[0]; sumLat += c[1]; });
            var centerLat2 = sumLat / coords.length;
            var centerLng2 = sumLng / coords.length;

            var label = L.tooltip({
                permanent: true, direction: 'center',
                className: 'rt-label', interactive: false
            }).setLatLng([centerLat2, centerLng2])
              .setContent('<span style="font-size:' + fontSize + '">' + rtName + '</span>');
            rtLabelLayer.addLayer(label);
        });
    }

    fetch('/geojson/batas-rt-bontang.geojson')
        .then(function(r){ return r.json(); })
        .then(function(data) {
            rtGeoJsonData = data;
            rtBoundaryLayer = L.geoJSON(data, {
                style: { color: '#00e5ff', weight: 1, opacity: 0.5, fillColor: '#00e5ff', fillOpacity: 0.05 },
                interactive: false
            }).addTo(pickerMap);
            renderRtLabels();
        }).catch(function(){});

    pickerMap.on('zoomend', function() {
        renderKelLabels();
        renderRtLabels();
    });

    // ===== Point-in-polygon (ray-casting) =====
    function pointInPolygon(lat, lng, coords) {
        var x = lng, y = lat, inside = false;
        for (var i = 0, j = coords.length - 1; i < coords.length; j = i++) {
            var xi = coords[i][0], yi = coords[i][1];
            var xj = coords[j][0], yj = coords[j][1];
            if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) {
                inside = !inside;
            }
        }
        return inside;
    }

    function findRtFromLatLng(lat, lng) {
        if (!rtGeoJsonData) return null;
        for (var i = 0; i < rtGeoJsonData.features.length; i++) {
            var feature = rtGeoJsonData.features[i];
            var geom = feature.geometry;
            var rings = geom.type === 'Polygon' ? [geom.coordinates[0]] : geom.coordinates.map(function(r){ return r[0]; });
            for (var r = 0; r < rings.length; r++) {
                if (pointInPolygon(lat, lng, rings[r])) {
                    return feature.properties;
                }
            }
        }
        return null;
    }

    // ===== Normalisasi nama untuk matching dropdown =====
    function normName(str) {
        return (str || '').replace(/[^a-z0-9]/gi, '').toLowerCase();
    }

    // ===== Autofill Kec/Kel/RT dari koordinat =====
    function autofillFromProps(props) {
        if (!props) return;
        var geoKec = normName(props.Kecamatan);
        var geoKel = normName(props.Kelurahan);
        var geoRtNum = parseInt(props.RT, 10); // "021" → 21

        // Cari id_kec dari dropdown yang sudah ada di DOM
        var idKec = null;
        $('#kec option').each(function() {
            if (normName($(this).text()) === geoKec) {
                idKec = $(this).val();
                return false;
            }
        });
        if (!idKec) return;

        $('#kec').val(idKec);

        // Load kelurahan via AJAX (sama persis seperti event change #kec)
        $.ajax({
            url: '{{ url("admin/epidemiologi/get-kelurahan") }}/' + idKec,
            type: 'GET', dataType: 'json',
            success: function(kelData) {
                $('#kel').empty().append('<option value="">== Pilih Kelurahan ==</option>');
                var idKel = null;
                $.each(kelData, function(id, name) {
                    $('#kel').append('<option value="' + id + '">' + name + '</option>');
                    if (normName(name) === geoKel) idKel = id;
                });
                if (!idKel) return;
                $('#kel').val(idKel);

                // Trigger wilker update (dari form-section-a)
                if (typeof updateWilker === 'function') updateWilker();

                // Load RT via AJAX
                $.ajax({
                    url: '{{ url("admin/epidemiologi/get-rt") }}/' + idKel,
                    type: 'GET', dataType: 'json',
                    success: function(rtData) {
                        $('#rt').empty().append('<option value="">== Pilih RT ==</option>');
                        var idRt = null;
                        $.each(rtData, function(id, name) {
                            $('#rt').append('<option value="' + id + '">' + name + '</option>');
                            // Cocokkan angka depan nama RT (e.g. "21AA" → 21 === geoRtNum)
                            if (parseInt(name, 10) === geoRtNum) idRt = id;
                        });
                        if (idRt) $('#rt').val(idRt);
                    }
                });
            }
        });
    }

    // ===== Marker & koordinat =====
    var marker = null;
    var prevLat = null, prevLng = null;

    function updateCoordinates(lat, lng) {
        var latR = parseFloat(lat).toFixed(8);
        var lngR = parseFloat(lng).toFixed(8);
        $('#inputLatitude').val(latR);
        $('#inputLongitude').val(lngR);
        $('#latDisplay').val(latR);
        $('#lngDisplay').val(lngR);
    }

    function setMarker(lat, lng) {
        if (marker) { marker.setLatLng([lat, lng]); }
        else {
            marker = L.marker([lat, lng], { draggable: true }).addTo(pickerMap);
            marker.on('dragend', function(e) {
                var pos = e.target.getLatLng();
                onMarkerMoved(pos.lat, pos.lng);
            });
        }
        updateCoordinates(lat, lng);
        $('#resetMarkerBtn').show();
    }

    function clearMarker() {
        if (marker) { pickerMap.removeLayer(marker); marker = null; }
        prevLat = null; prevLng = null;
        $('#inputLatitude, #inputLongitude, #latDisplay, #lngDisplay').val('');
        $('#resetMarkerBtn').hide();
    }

    // Dipanggil setiap kali titik berpindah (click/drag/GPS/sesuaikan)
    function onMarkerMoved(lat, lng) {
        var props = findRtFromLatLng(lat, lng);
        if (!props) {
            // Di luar batas — kembalikan marker ke posisi sebelumnya
            if (prevLat !== null) {
                setMarker(prevLat, prevLng);
                alert('Titik di luar wilayah yang didukung. Marker dikembalikan ke posisi sebelumnya.');
            } else {
                clearMarker();
                alert('Titik di luar wilayah yang didukung.');
            }
            return;
        }
        prevLat = lat; prevLng = lng;
        setMarker(lat, lng);
        autofillFromProps(props);
    }

    // Pasang marker existing saat edit
    if (existingLat && existingLng) {
        var eLat = parseFloat(existingLat), eLng = parseFloat(existingLng);
        prevLat = eLat; prevLng = eLng;
        setMarker(eLat, eLng);
    }

    // Klik peta
    pickerMap.on('click', function(e) {
        onMarkerMoved(e.latlng.lat, e.latlng.lng);
    });

    // ===== T014 — GPS "Titik Lokasi Ini" =====
    $('#btnGpsLocate').on('click', function() {
        if (!navigator.geolocation) {
            alert('Browser tidak mendukung GPS.');
            return;
        }
        $('#gpsStatus').text('Mencari lokasi...');
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                $('#gpsStatus').text('');
                var lat = pos.coords.latitude, lng = pos.coords.longitude;
                pickerMap.setView([lat, lng], 18);
                onMarkerMoved(lat, lng);
            },
            function(err) {
                $('#gpsStatus').text('');
                alert('Akses GPS ditolak. Isi koordinat secara manual.');
            },
            { timeout: 10000, enableHighAccuracy: true }
        );
    });

    // ===== T018 — Tombol "Sesuaikan" (input manual lat/lng) =====
    $('#btnSesuaikan').on('click', function() {
        var lat = parseFloat($('#latDisplay').val());
        var lng = parseFloat($('#lngDisplay').val());
        if (isNaN(lat) || isNaN(lng)) {
            alert('Masukkan nilai Latitude dan Longitude yang valid.');
            return;
        }
        // Validasi range Indonesia kasar
        if (lat < -11 || lat > 6 || lng < 95 || lng > 141) {
            alert('Koordinat di luar wilayah Indonesia.');
            return;
        }
        var currentZoom = pickerMap.getZoom();
        var targetZoom  = Math.min(currentZoom + 2, 19);
        pickerMap.setView([lat, lng], targetZoom);
        onMarkerMoved(lat, lng);
    });

    // Reset marker
    $('#resetMarkerBtn').on('click', function(e) {
        e.preventDefault(); e.stopPropagation();
        clearMarker();
    });

    // Invalidate size saat tab/accordion dibuka
    $(document).on('shown.bs.collapse shown.bs.tab', function() {
        setTimeout(function() { pickerMap.invalidateSize(); }, 200);
    });
    setTimeout(function() { pickerMap.invalidateSize(); }, 500);
});
</script>
@endpush
