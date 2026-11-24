/*
 * Peta pemilih lokasi (Leaflet + OpenStreetMap, gratis).
 * - Klik/ketuk peta atau geser penanda untuk memilih titik.
 * - Tombol "Lokasi saya" memakai GPS HP.
 * - Pencarian alamat lewat Nominatim (OpenStreetMap).
 * - Jika lokasi outlet diketahui, hitung jarak lewat jalan (OSRM),
 *   dengan cadangan jarak garis lurus bila layanan rute tidak tersedia.
 */
window.HSMapPicker = function (opts) {
    const DEFAULT_CENTER = [-6.2, 106.816]; // Jakarta, bila belum ada lokasi
    const el = document.getElementById(opts.mapId);
    if (!el || typeof L === 'undefined') {
        if (el) el.innerHTML = '<p class="hint" style="padding:12px">Peta tidak bisa dimuat. Periksa koneksi internet.</p>';
        return;
    }

    const latInput = document.getElementById(opts.latId);
    const lngInput = document.getElementById(opts.lngId);
    const distInput = opts.distanceId ? document.getElementById(opts.distanceId) : null;
    const label = opts.labelId ? document.getElementById(opts.labelId) : null;
    const outlet = opts.outlet || null;

    const start = latInput.value && lngInput.value
        ? [parseFloat(latInput.value), parseFloat(lngInput.value)]
        : (outlet || DEFAULT_CENTER);

    const map = L.map(el, { scrollWheelZoom: false }).setView(start, latInput.value ? 16 : (outlet ? 14 : 12));
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    if (outlet && opts.showOutlet !== false) {
        L.circleMarker(outlet, { radius: 8, color: '#1E3A8A', fillColor: '#38BDF8', fillOpacity: 1, weight: 3 })
            .addTo(map).bindTooltip('Outlet HS Laundry');
    }

    let marker = null;
    const haversine = (a, b) => {
        const R = 6371, toRad = (d) => d * Math.PI / 180;
        const dLat = toRad(b[0] - a[0]), dLng = toRad(b[1] - a[1]);
        const h = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a[0])) * Math.cos(toRad(b[0])) * Math.sin(dLng / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
    };
    const fmt = (km) => km.toLocaleString('id-ID', { maximumFractionDigits: 1 });
    const setLabel = (text) => { if (label) label.textContent = text; };

    let routeSeq = 0;
    function updateDistance(point) {
        if (!outlet) {
            setLabel(opts.noOutletText || 'Lokasi outlet belum diatur, jarak belum bisa dihitung.');
            return;
        }
        const straight = haversine(outlet, point);
        if (distInput) distInput.value = '';
        setLabel('± ' + fmt(straight) + ' km (garis lurus), menghitung jarak jalan…');

        const seq = ++routeSeq;
        const url = 'https://router.project-osrm.org/route/v1/driving/'
            + outlet[1] + ',' + outlet[0] + ';' + point[1] + ',' + point[0] + '?overview=false';
        fetch(url)
            .then((r) => r.ok ? r.json() : Promise.reject())
            .then((json) => {
                if (seq !== routeSeq) return;
                const km = json.routes && json.routes[0] ? json.routes[0].distance / 1000 : null;
                if (km === null) throw new Error('no route');
                if (distInput) distInput.value = km.toFixed(2);
                setLabel(fmt(km) + ' km lewat jalan dari outlet');
                if (opts.onDistance) opts.onDistance(km);
            })
            .catch(() => {
                if (seq !== routeSeq) return;
                setLabel('± ' + fmt(straight) + ' km (garis lurus; jarak jalan tidak tersedia)');
                if (opts.onDistance) opts.onDistance(straight);
            });
    }

    function setPoint(latlng, pan) {
        const point = [Number(latlng.lat.toFixed(7)), Number(latlng.lng.toFixed(7))];
        latInput.value = point[0];
        lngInput.value = point[1];
        if (!marker) {
            marker = L.marker(point, { draggable: true }).addTo(map);
            marker.on('dragend', () => setPoint(marker.getLatLng(), false));
        } else {
            marker.setLatLng(point);
        }
        if (pan) map.setView(point, Math.max(map.getZoom(), 16));
        updateDistance(point);
    }

    if (latInput.value && lngInput.value) {
        marker = L.marker(start, { draggable: true }).addTo(map);
        marker.on('dragend', () => setPoint(marker.getLatLng(), false));
        if (opts.recalcOnLoad) updateDistance(start);
    }

    map.on('click', (e) => setPoint(e.latlng, false));

    if (opts.locateId) {
        document.getElementById(opts.locateId).addEventListener('click', () => {
            if (!navigator.geolocation) return setLabel('Browser tidak mendukung GPS.');
            setLabel('Mengambil lokasi GPS…');
            navigator.geolocation.getCurrentPosition(
                (pos) => setPoint({ lat: pos.coords.latitude, lng: pos.coords.longitude }, true),
                () => setLabel('Lokasi GPS ditolak atau tidak tersedia.'),
                { enableHighAccuracy: true, timeout: 15000 }
            );
        });
    }

    if (opts.searchId && opts.searchBtnId) {
        const input = document.getElementById(opts.searchId);
        const run = () => {
            const q = input.value.trim();
            if (!q) return;
            setLabel('Mencari alamat…');
            fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=id&q=' + encodeURIComponent(q))
                .then((r) => r.json())
                .then((rows) => {
                    if (!rows.length) return setLabel('Alamat tidak ditemukan. Coba nama jalan/kelurahan, atau ketuk peta.');
                    setPoint({ lat: parseFloat(rows[0].lat), lng: parseFloat(rows[0].lon) }, true);
                })
                .catch(() => setLabel('Pencarian alamat gagal. Ketuk peta untuk memilih titik.'));
        };
        document.getElementById(opts.searchBtnId).addEventListener('click', run);
        input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); run(); } });
    }

    if (opts.clearId) {
        document.getElementById(opts.clearId).addEventListener('click', () => {
            latInput.value = '';
            lngInput.value = '';
            if (distInput) distInput.value = '';
            if (marker) { map.removeLayer(marker); marker = null; }
            setLabel('Lokasi dihapus.');
        });
    }

    // Peta di dalam elemen yang baru tampil perlu dihitung ulang ukurannya.
    setTimeout(() => map.invalidateSize(), 200);
    return map;
};
