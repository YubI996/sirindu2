{{-- Map Coordinate Picker - Leaflet with Esri Satellite --}}
<div class="row mt-3">
    <div class="col-md-12">
        <div class="form-group">
            <label><i class="fa fa-map-marker-alt"></i> Titik Koordinat Lokasi</label>
            <small class="form-text text-muted mb-2">Klik pada peta untuk menandai lokasi pasien dan mengisi Kecamatan/Kelurahan/RT otomatis. Marker dapat di-drag untuk menyesuaikan posisi.</small>

            <div id="mapPickerContainer" style="position: relative;">
                <div id="mapPicker" style="height: 350px; width: 100%; border: 1px solid #ced4da; border-radius: 4px;"></div>
                <button type="button" id="resetMarkerBtn" class="btn btn-sm btn-danger" style="position: absolute; top: 10px; right: 10px; z-index: 1000; display: none;">
                    <i class="fa fa-times"></i> Hapus Marker
                </button>
            </div>

            <div class="row mt-2">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text">Lat</span>
                        </div>
                        <input type="text" id="latDisplay" class="form-control form-control-sm" readonly placeholder="Belum ditandai">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text">Lng</span>
                        </div>
                        <input type="text" id="lngDisplay" class="form-control form-control-sm" readonly placeholder="Belum ditandai">
                    </div>
                </div>
            </div>

            <div id="mapLocationStatus" class="mt-2" style="display: none;">
                <small>
                    <i class="fa fa-spinner fa-spin"></i> <span id="mapLocationStatusText">Mencari wilayah...</span>
                </small>
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
        border: none;
        box-shadow: none;
        color: #fff;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 6px;
        border-radius: 3px;
        white-space: nowrap;
        letter-spacing: 0.5px;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
    }
    .kel-boundary-label::before {
        display: none;
    }
</style>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
$(document).ready(function() {
    var defaultLat = 0.1237;
    var defaultLng = 117.4907;
    var defaultZoom = 13;

    var existingLat = $('#inputLatitude').val();
    var existingLng = $('#inputLongitude').val();

    var centerLat = existingLat ? parseFloat(existingLat) : defaultLat;
    var centerLng = existingLng ? parseFloat(existingLng) : defaultLng;
    var initZoom = existingLat ? 16 : defaultZoom;

    var pickerMap = L.map('mapPicker').setView([centerLat, centerLng], initZoom);

    // Esri World Imagery (satellite) - free, no API key
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri &mdash; Esri, i-cubed, USDA, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
        maxZoom: 23,
        maxNativeZoom: 19
    }).addTo(pickerMap);

    // CartoDB Positron labels — nama jalan, tempat, POI
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://carto.com/">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 23,
        maxNativeZoom: 18,
        pane: 'overlayPane'
    }).addTo(pickerMap);

    // Batas kelurahan dari GeoJSON — garis saja, tanpa fill
    var kelBoundaryLayer = null;
    var kelLabelLayer = L.layerGroup().addTo(pickerMap);

    function renderKelLabels() {
        kelLabelLayer.clearLayers();
        if (!kelBoundaryLayer) return;

        kelBoundaryLayer.eachLayer(function(layer) {
            var name = layer.feature.properties.Name || layer.feature.properties.kel_desa || '';
            if (!name) return;

            var center = layer.getBounds().getCenter();
            var label = L.tooltip({
                permanent: true,
                direction: 'center',
                className: 'kel-boundary-label',
                interactive: false
            }).setLatLng(center).setContent(name);

            kelLabelLayer.addLayer(label);
        });
    }

    fetch('/geojson/Kota Bontang-KEL_DESA.geojson')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            kelBoundaryLayer = L.geoJSON(data, {
                style: {
                    color: '#ffffff',
                    weight: 2,
                    opacity: 0.8,
                    dashArray: '6 4',
                    fillOpacity: 0
                },
                interactive: false
            }).addTo(pickerMap);
            renderKelLabels();
        })
        .catch(function() {});

    // Re-render labels on zoom change for proper positioning
    pickerMap.on('zoomend', function() {
        renderKelLabels();
    });

    // RT boundaries layer from GeoJSON
    var rtGeoJsonData = null;
    var rtBoundaryLayer = null;

    fetch('/geojson/batas-rt-bontang.geojson')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            rtGeoJsonData = data;
            rtBoundaryLayer = L.geoJSON(data, {
                style: {
                    color: '#00e5ff',
                    weight: 1,
                    opacity: 0.5,
                    fillColor: '#00e5ff',
                    fillOpacity: 0.05
                },
                interactive: false
            }).addTo(pickerMap);
        })
        .catch(function() {});

    // Point-in-polygon using ray casting algorithm
    function pointInPolygon(lat, lng, polygon) {
        var inside = false;
        for (var i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            var xi = polygon[i][1], yi = polygon[i][0]; // [lng, lat] in geojson
            var xj = polygon[j][1], yj = polygon[j][0];
            var intersect = ((yi > lng) !== (yj > lng)) &&
                (lat < (xj - xi) * (lng - yi) / (yj - yi) + xi);
            if (intersect) inside = !inside;
        }
        return inside;
    }

    function findRtAtPoint(lat, lng) {
        if (!rtGeoJsonData) return null;
        for (var i = 0; i < rtGeoJsonData.features.length; i++) {
            var feature = rtGeoJsonData.features[i];
            var coords = feature.geometry.coordinates;
            // Polygon can have multiple rings, check outer ring (index 0)
            if (feature.geometry.type === 'Polygon') {
                if (pointInPolygon(lat, lng, coords[0])) return feature.properties;
            } else if (feature.geometry.type === 'MultiPolygon') {
                for (var p = 0; p < coords.length; p++) {
                    if (pointInPolygon(lat, lng, coords[p][0])) return feature.properties;
                }
            }
        }
        return null;
    }

    // Normalize text for comparison: lowercase, replace hyphens with spaces, trim
    function normalizeText(str) {
        return (str || '').toLowerCase().replace(/-/g, ' ').replace(/\s+/g, ' ').trim();
    }

    // Track pending AJAX requests so we can abort on re-click
    var pendingKelXhr = null;
    var pendingRtXhr = null;

    function showLocationStatus(text, type) {
        var el = $('#mapLocationStatus');
        var textEl = $('#mapLocationStatusText');
        if (type === 'loading') {
            el.show().removeClass('text-success text-danger text-warning').addClass('text-info');
            textEl.html('<i class="fa fa-spinner fa-spin"></i> ' + text);
        } else if (type === 'success') {
            el.show().removeClass('text-info text-danger text-warning').addClass('text-success');
            textEl.html('<i class="fa fa-check-circle"></i> ' + text);
        } else if (type === 'warning') {
            el.show().removeClass('text-info text-success text-danger').addClass('text-warning');
            textEl.html('<i class="fa fa-exclamation-triangle"></i> ' + text);
        }
    }

    function hideLocationStatus() {
        $('#mapLocationStatus').hide();
    }

    // Auto-fill kecamatan → kelurahan → RT dropdowns from geojson properties
    function autoFillLocation(properties) {
        // Abort any pending requests from previous click
        if (pendingKelXhr) { pendingKelXhr.abort(); pendingKelXhr = null; }
        if (pendingRtXhr) { pendingRtXhr.abort(); pendingRtXhr = null; }

        if (!properties) {
            showLocationStatus('Titik di luar wilayah RT yang diketahui', 'warning');
            return;
        }

        showLocationStatus('Mengisi Kecamatan / Kelurahan / RT...', 'loading');

        var geoKec = normalizeText(properties.Kecamatan);
        var geoKel = normalizeText(properties.Kelurahan);
        var geoRt = parseInt(properties.RT, 10); // "021" → 21

        // Step 1: Find and set Kecamatan
        var kecSelect = $('#kec');
        var kecMatched = false;
        kecSelect.find('option').each(function() {
            if (normalizeText($(this).text()) === geoKec) {
                kecSelect.val($(this).val());
                kecMatched = true;
                return false;
            }
        });

        if (!kecMatched) {
            showLocationStatus('Kecamatan tidak ditemukan di dropdown', 'warning');
            return;
        }

        // Step 2: Load kelurahan → set kelurahan → load RT → set RT
        var id_kec = kecSelect.val();
        $('#kel').empty().append('<option value="">== Pilih Kelurahan ==</option>');
        $('#rt').empty().append('<option value="">== Pilih RT ==</option>');

        pendingKelXhr = $.ajax({
            url: '{{ route("admin.epidemiologi.getKelurahan", "") }}/' + id_kec,
            type: "GET",
            dataType: "json",
            success: function(data) {
                pendingKelXhr = null;
                var kelSelect = $('#kel');
                kelSelect.empty().append('<option value="">== Pilih Kelurahan ==</option>');
                $.each(data, function(key, value) {
                    kelSelect.append('<option value="' + key + '">' + value + '</option>');
                });

                // Step 3: Find and set Kelurahan
                var kelMatched = false;
                kelSelect.find('option').each(function() {
                    if (normalizeText($(this).text()) === geoKel) {
                        kelSelect.val($(this).val());
                        kelMatched = true;
                        return false;
                    }
                });

                if (!kelMatched) {
                    showLocationStatus('Kelurahan tidak ditemukan di dropdown', 'warning');
                    return;
                }

                // Step 4: Load RT → then set RT
                var id_kel = kelSelect.val();
                pendingRtXhr = $.ajax({
                    url: '{{ route("admin.epidemiologi.getRt", "") }}/' + id_kel,
                    type: "GET",
                    dataType: "json",
                    success: function(rtData) {
                        pendingRtXhr = null;
                        var rtSelect = $('#rt');
                        rtSelect.empty().append('<option value="">== Pilih RT ==</option>');
                        $.each(rtData, function(key, value) {
                            rtSelect.append('<option value="' + key + '">' + value + '</option>');
                        });

                        // Step 5: Find and set RT by matching the number prefix
                        var rtMatched = false;
                        rtSelect.find('option').each(function() {
                            var optText = $(this).text();
                            var optNum = parseInt(optText, 10);
                            if (!isNaN(optNum) && optNum === geoRt) {
                                rtSelect.val($(this).val());
                                rtMatched = true;
                                return false;
                            }
                        });

                        if (rtMatched) {
                            showLocationStatus('Kec. ' + properties.Kecamatan + ' / Kel. ' + properties.Kelurahan + ' / RT ' + geoRt, 'success');
                        } else {
                            showLocationStatus('RT ' + geoRt + ' tidak ditemukan di dropdown', 'warning');
                        }
                    }
                });
            }
        });
    }

    var marker = null;

    function setMarker(lat, lng) {
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], { draggable: true }).addTo(pickerMap);

            marker.on('dragend', function(e) {
                var pos = e.target.getLatLng();
                updateCoordinates(pos.lat, pos.lng);
                autoFillLocation(findRtAtPoint(pos.lat, pos.lng));
            });
        }
        updateCoordinates(lat, lng);
        $('#resetMarkerBtn').show();
    }

    function updateCoordinates(lat, lng) {
        var latRounded = parseFloat(lat).toFixed(8);
        var lngRounded = parseFloat(lng).toFixed(8);
        $('#inputLatitude').val(latRounded);
        $('#inputLongitude').val(lngRounded);
        $('#latDisplay').val(latRounded);
        $('#lngDisplay').val(lngRounded);
    }

    function clearMarker() {
        if (pendingKelXhr) { pendingKelXhr.abort(); pendingKelXhr = null; }
        if (pendingRtXhr) { pendingRtXhr.abort(); pendingRtXhr = null; }
        if (marker) {
            pickerMap.removeLayer(marker);
            marker = null;
        }
        $('#inputLatitude').val('');
        $('#inputLongitude').val('');
        $('#latDisplay').val('');
        $('#lngDisplay').val('');
        $('#resetMarkerBtn').hide();
        hideLocationStatus();
    }

    // Place existing marker on edit
    if (existingLat && existingLng) {
        setMarker(parseFloat(existingLat), parseFloat(existingLng));
    }

    // Click on map to place marker
    pickerMap.on('click', function(e) {
        setMarker(e.latlng.lat, e.latlng.lng);
        autoFillLocation(findRtAtPoint(e.latlng.lat, e.latlng.lng));
    });

    // Reset button
    $('#resetMarkerBtn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        clearMarker();
    });

    // Handle accordion/tab visibility - invalidateSize when map becomes visible
    // Listen for Bootstrap collapse/tab events
    $(document).on('shown.bs.collapse shown.bs.tab', function() {
        setTimeout(function() {
            pickerMap.invalidateSize();
        }, 200);
    });

    // Also invalidate after a short delay on load (for accordion initially open)
    setTimeout(function() {
        pickerMap.invalidateSize();
    }, 500);
});
</script>
@endpush
