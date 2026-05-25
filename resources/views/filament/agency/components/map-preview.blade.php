<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<div x-data="agencyMapPicker()" x-init="init()" style="display: flex; flex-direction: column; gap: 12px;">
    <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
        <button type="button"
            @click="getMyLocation"
            :disabled="loading"
            style="display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; background: #16A34A; color: white; font-weight: 600; font-size: 13px; border-radius: 6px; border: none; cursor: pointer;">
            <svg x-show="!loading" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <svg x-show="loading" width="14" height="14" fill="none" viewBox="0 0 24 24" style="flex-shrink:0; animation: spin 1s linear infinite;">
                <circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.3)" stroke-width="4" fill="none"/>
                <path d="M4 12a8 8 0 018-8" stroke="white" stroke-width="4" fill="none" stroke-linecap="round"/>
            </svg>
            <span x-text="loading ? 'Localisation...' : 'Utiliser ma position GPS'"></span>
        </button>

        <span x-show="error" x-text="error" style="font-size: 12px; color: #DC2626;"></span>
        <span x-show="successMsg" style="font-size: 12px; color: #16A34A; font-weight: 600;">✓ <span x-text="successMsg"></span></span>
    </div>

    <p style="font-size: 12px; color: #6B7280; margin: 0;">
        💡 Cliquez sur la carte pour positionner le marqueur, ou faites-le glisser pour ajuster.
    </p>

    <div wire:ignore style="height: 420px; width: 100%; border-radius: 8px; overflow: hidden; border: 1px solid #E5E7EB; z-index: 1;"><div id="agency-leaflet-map" style="height: 100%; width: 100%;"></div></div>

    <div x-show="hasCoords()" style="font-size: 12px; color: #4B5563; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; background: #F9FAFB; padding: 8px 12px; border-radius: 6px;">
        <span>📍 <span x-text="latVal" style="font-family: monospace;"></span>, <span x-text="lngVal" style="font-family: monospace;"></span></span>
        <a :href="googleMapsUrl()" target="_blank" rel="noopener" style="color: #16A34A; font-weight: 600; text-decoration: none;">
            Voir dans Google Maps →
        </a>
    </div>
</div>

<style>
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .agency-pin-icon { background: transparent !important; border: none !important; }
    .leaflet-container { font-family: inherit; }
</style>

<script>
function agencyMapPicker() {
    return {
        map: null,
        marker: null,
        loading: false,
        error: '',
        successMsg: '',
        detectedCity: '',
        latVal: '',
        lngVal: '',
        smallIcon: null,

        init() {
            this.readInputs();
            this.$nextTick(() => {
                setTimeout(() => this.initMap(), 200);
                this.attachInputListeners();
            });
        },

        attachInputListeners() {
            const latInput = document.getElementById('agency-lat-field');
            const lngInput = document.getElementById('agency-lng-field');
            if (latInput) {
                latInput.addEventListener('change', () => { this.readInputs(); this.refreshMarker(); });
                latInput.addEventListener('input', () => { this.readInputs(); });
            }
            if (lngInput) {
                lngInput.addEventListener('change', () => { this.readInputs(); this.refreshMarker(); });
                lngInput.addEventListener('input', () => { this.readInputs(); });
            }
        },

        initMap() {
            if (this.map) return;
            const el = document.getElementById('agency-leaflet-map');
            if (!el) { setTimeout(() => this.initMap(), 200); return; }

            this.smallIcon = L.divIcon({
                html: '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="38" viewBox="0 0 30 38"><path d="M15 0C6.7 0 0 6.7 0 15c0 11 15 23 15 23s15-12 15-23c0-8.3-6.7-15-15-15z" fill="#16A34A" stroke="white" stroke-width="2"/><circle cx="15" cy="15" r="5" fill="white"/></svg>',
                iconSize: [30, 38],
                iconAnchor: [15, 38],
                className: 'agency-pin-icon',
            });

            const initLat = parseFloat(this.latVal) || 18.0858;
            const initLng = parseFloat(this.lngVal) || -15.9785;

            this.map = L.map(el, {
                scrollWheelZoom: false,
                zoomControl: true,
            }).setView([initLat, initLng], this.hasCoords() ? 16 : 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
                maxZoom: 19,
            }).addTo(this.map);

            this.map.on('mouseover', () => this.map.scrollWheelZoom.enable());
            this.map.on('mouseout', () => this.map.scrollWheelZoom.disable());

            if (this.hasCoords()) {
                this.placeMarker(initLat, initLng);
            }

            this.map.on('click', (e) => {
                const lat = e.latlng.lat.toFixed(6);
                const lng = e.latlng.lng.toFixed(6);
                this.updateCoords(lat, lng);
                this.successMsg = 'Position définie';
                setTimeout(() => this.successMsg = '', 2500);
            });
        },

        placeMarker(lat, lng) {
            if (this.marker) this.map.removeLayer(this.marker);
            this.marker = L.marker([lat, lng], { icon: this.smallIcon, draggable: true }).addTo(this.map);
            this.marker.on('dragend', (e) => {
                const ll = e.target.getLatLng();
                this.updateCoords(ll.lat.toFixed(6), ll.lng.toFixed(6));
                this.successMsg = 'Position ajustée';
                setTimeout(() => this.successMsg = '', 2500);
            });
        },

        refreshMarker() {
            if (!this.map || !this.hasCoords()) return;
            const lat = parseFloat(this.latVal);
            const lng = parseFloat(this.lngVal);
            this.placeMarker(lat, lng);
            this.map.setView([lat, lng], 16);
        },

        readInputs() {
            const latInput = document.getElementById('agency-lat-field');
            const lngInput = document.getElementById('agency-lng-field');
            this.latVal = latInput?.value || '';
            this.lngVal = lngInput?.value || '';
        },

        updateCoords(lat, lng) {
            const latInput = document.getElementById('agency-lat-field');
            const lngInput = document.getElementById('agency-lng-field');

            if (latInput) {
                latInput.value = lat;
                latInput.dispatchEvent(new Event('input', { bubbles: true }));
                latInput.dispatchEvent(new Event('change', { bubbles: true }));
                latInput.dispatchEvent(new Event('blur', { bubbles: true }));
            }
            if (lngInput) {
                lngInput.value = lng;
                lngInput.dispatchEvent(new Event('input', { bubbles: true }));
                lngInput.dispatchEvent(new Event('change', { bubbles: true }));
                lngInput.dispatchEvent(new Event('blur', { bubbles: true }));
            }

            this.latVal = lat;
            this.lngVal = lng;
            this.placeMarker(parseFloat(lat), parseFloat(lng));

            // Reverse geocoding Nominatim
            this.reverseGeocode(lat, lng);
        },

        async reverseGeocode(lat, lng) {
            try {
                const r = await fetch('/api/v1/geocode/reverse?lat=' + lat + '&lng=' + lng + '&lang=fr');
                if (!r.ok) return;
                const data = await r.json();
                if (!data.success) return;

                // 1. Remplir l'adresse
                if (data.address) {
                    const addressInput = document.querySelector('input[wire\\:model="data.address"]');
                    if (addressInput) {
                        addressInput.value = data.address;
                        addressInput.dispatchEvent(new Event('input', { bubbles: true }));
                        addressInput.dispatchEvent(new Event('change', { bubbles: true }));
                        addressInput.dispatchEvent(new Event('blur', { bubbles: true }));
                    }
                }

                // Remplir la ville (Select Filament via Livewire $wire)
                if (data.city_id) {
                    console.log('[city] Tentative set city_id =', data.city_id);
                    try {
                        const addrInput = document.querySelector('[wire\\:model="data.address"]');
                        console.log('[city] addrInput trouvé ?', !!addrInput, addrInput);
                        const root = addrInput?.closest('[wire\\:id]');
                        console.log('[city] root wire:id ?', !!root, root?.getAttribute('wire:id'));
                        if (root && window.Livewire) {
                            const wireId = root.getAttribute('wire:id');
                            const comp = window.Livewire.find(wireId);
                            console.log('[city] composant trouvé ?', !!comp, 'a $wire ?', !!comp?.$wire);
                            if (comp && comp.$wire) {
                                console.log('[city] data avant set:', JSON.stringify(comp.data || comp.$wire));
                                await comp.set('data.city_id', data.city_id, false);
                                console.log('[city] data après set:', JSON.stringify(comp.data || comp.$wire));
                                console.log('[city] ✓ Set OK');
                            } else {
                                console.warn('[city] Pas de composant ou pas de $wire');
                            }
                        } else {
                            console.warn('[city] Pas de root ou pas de Livewire global');
                        }
                    } catch (e) {
                        console.error('[city] Erreur:', e);
                    }
                }

                this.successMsg = data.city_name
                    ? 'Adresse + ville (' + data.city_name + ') mises à jour'
                    : 'Adresse mise à jour';
                setTimeout(() => this.successMsg = '', 3500);
            } catch (e) {
                console.warn('Reverse geocoding error', e);
            }
        },

        hasCoords() {
            return this.latVal && this.lngVal && !isNaN(parseFloat(this.latVal)) && !isNaN(parseFloat(this.lngVal));
        },

        googleMapsUrl() {
            if (!this.hasCoords()) return '#';
            return `https://www.google.com/maps?q=${this.latVal},${this.lngVal}`;
        },

        getMyLocation() {
            this.error = '';
            this.successMsg = '';
            if (!navigator.geolocation) {
                this.error = "Géolocalisation non supportée.";
                return;
            }
            this.loading = true;
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude.toFixed(6);
                    const lng = position.coords.longitude.toFixed(6);
                    this.updateCoords(lat, lng);
                    if (this.map) this.map.setView([parseFloat(lat), parseFloat(lng)], 17);
                    this.loading = false;
                    this.successMsg = 'Position GPS récupérée';
                    setTimeout(() => this.successMsg = '', 3000);
                },
                (err) => {
                    this.loading = false;
                    if (err.code === 1) this.error = "Permission refusée.";
                    else if (err.code === 2) this.error = "Position indisponible.";
                    else if (err.code === 3) this.error = "Timeout.";
                    else this.error = err.message;
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        },
    };
}
</script>
