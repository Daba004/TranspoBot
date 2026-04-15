/**
 * TranspoBot — Real-Time Fleet Tracking Map
 * Uses Leaflet.js + SSE for live vehicle tracking
 */
(function() {
    'use strict';

    const API_URL = window.location.origin;
    
    // =========================================================
    // MAP INITIALIZATION
    // =========================================================
    const map = L.map('map', {
        zoomControl: false
    }).setView([14.7167, -17.4677], 12);

    // Zoom control in bottom-left
    L.control.zoom({ position: 'bottomleft' }).addTo(map);

    // Premium tile layer (CartoDB Voyager — clean, modern)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 19
    }).addTo(map);

    // =========================================================
    // STATE
    // =========================================================
    let markers = {};          // vehicleId -> L.marker
    let routePolylines = {};   // lineCode -> L.polyline
    let eventSource = null;
    let simulationActive = false;
    let lastIncidentIds = new Set();
    
    // Vehicle type icons
    const VEHICLE_ICONS = {
        bus: '🚌',
        minibus: '🚐',
        taxi: '🚕'
    };

    // =========================================================
    // CUSTOM MARKER CREATION
    // =========================================================
    function createVehicleIcon(vehicle) {
        const isMoving = vehicle.trajet_statut === 'en_cours';
        const statusClass = isMoving ? 'en_transit' : vehicle.statut;
        const emoji = VEHICLE_ICONS[vehicle.type] || '🚗';
        
        return L.divIcon({
            className: 'vehicle-marker',
            html: `<div class="marker-icon ${statusClass}" title="${vehicle.immatriculation}">${emoji}</div>`,
            iconSize: [40, 40],
            iconAnchor: [20, 20],
            popupAnchor: [0, -25]
        });
    }

    function createPopupContent(v) {
        const statusColor = v.statut === 'actif' ? 'bg-emerald-100 text-emerald-700' 
                          : v.statut === 'maintenance' ? 'bg-amber-100 text-amber-700'
                          : 'bg-rose-100 text-rose-700';
        
        const fuelColor = v.carburant > 50 ? '#059669' : v.carburant > 20 ? '#d97706' : '#dc2626';
        const fuelClass = v.carburant > 50 ? 'fuel-high' : v.carburant > 20 ? 'fuel-medium' : 'fuel-low';
        
        const chauffeur = v.chauffeur_prenom && v.chauffeur_nom 
            ? `${v.chauffeur_prenom} ${v.chauffeur_nom}` 
            : '<span style="color:#94a3b8;font-style:italic">Non assigné</span>';
        
        const ligne = v.ligne_nom || '<span style="color:#94a3b8;font-style:italic">Aucune ligne</span>';
        const trajetStatus = v.trajet_statut === 'en_cours' 
            ? '<span class="popup-badge" style="background:#dcfce7;color:#15803d">EN TRANSIT</span>'
            : v.trajet_statut === 'planifie' 
            ? '<span class="popup-badge" style="background:#fef3c7;color:#92400e">PLANIFIÉ</span>'
            : '<span class="popup-badge" style="background:#f1f5f9;color:#64748b">AU DÉPÔT</span>';
        
        return `
            <div style="min-width:220px">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                    <span class="popup-title">${v.immatriculation}</span>
                    ${trajetStatus}
                </div>
                <div style="font-size:11px;color:#64748b;margin-bottom:10px">
                    <div style="margin-bottom:4px"><strong style="color:#334155">Chauffeur:</strong> ${chauffeur}</div>
                    <div style="margin-bottom:4px"><strong style="color:#334155">Ligne:</strong> ${ligne}</div>
                    <div><strong style="color:#334155">Vitesse:</strong> ${v.vitesse ? Math.round(v.vitesse) + ' km/h' : '0 km/h'}</div>
                </div>
                <div style="margin-top:8px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                        <span style="font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:0.1em;color:#94a3b8">Carburant</span>
                        <span style="font-size:11px;font-weight:900;color:${fuelColor}">${v.carburant || 0}%</span>
                    </div>
                    <div class="fuel-bar"><div class="fuel-bar-fill ${fuelClass}" style="width:${v.carburant || 0}%"></div></div>
                </div>
            </div>
        `;
    }

    // =========================================================
    // ROUTE DRAWING
    // =========================================================
    async function drawRoutes() {
        try {
            const response = await fetch(`${API_URL}/api/lines`);
            const data = await response.json();
            
            const colors = ['#059669', '#2563eb', '#7c3aed', '#dc2626', '#d97706'];
            
            data.lines.forEach((line, index) => {
                if (line.waypoints && line.waypoints.length > 0) {
                    const latlngs = line.waypoints.map(wp => [wp.lat, wp.lng]);
                    const color = colors[index % colors.length];
                    
                    const polyline = L.polyline(latlngs, {
                        color: color,
                        weight: 4,
                        opacity: 0.7,
                        dashArray: '8, 12',
                        lineCap: 'round'
                    }).addTo(map);
                    
                    polyline.bindTooltip(`<strong>${line.code}</strong> — ${line.nom}`, {
                        sticky: true,
                        className: 'route-tooltip'
                    });
                    
                    routePolylines[line.code] = polyline;
                    
                    // Add origin/destination markers
                    if (line.origine_lat && line.destination_lat) {
                        L.circleMarker([line.origine_lat, line.origine_lng], {
                            radius: 7, fillColor: color, color: 'white', weight: 2, fillOpacity: 1
                        }).addTo(map).bindTooltip(`<strong>${line.origine}</strong><br><span style="font-size:10px;color:#64748b">(Départ ${line.code})</span>`);
                        
                        L.circleMarker([line.destination_lat, line.destination_lng], {
                            radius: 7, fillColor: color, color: 'white', weight: 2, fillOpacity: 1
                        }).addTo(map).bindTooltip(`<strong>${line.destination}</strong><br><span style="font-size:10px;color:#64748b">(Arrivée ${line.code})</span>`);
                    }
                }
            });
        } catch (error) {
            console.error('[Routes]', error);
        }
    }

    // =========================================================
    // VEHICLE LIST PANEL
    // =========================================================
    function updateVehicleList(vehicles) {
        const container = document.getElementById('vehicle-list');
        if (!container) return;
        
        let html = '';
        let transitCount = 0;
        let activeCount = 0;
        
        vehicles.forEach(v => {
            const isTransit = v.trajet_statut === 'en_cours';
            const isPlanifie = v.trajet_statut === 'planifie';
            
            if (isTransit) transitCount++;
            if (v.statut === 'actif') activeCount++;
            
            const fuelClass = v.carburant > 50 ? 'fuel-high' : v.carburant > 20 ? 'fuel-medium' : 'fuel-low';
            const fuelPct = v.carburant || 0;
            
            const statusLabel = isTransit ? 'En Transit' : isPlanifie ? 'Planifié' : 'Au Dépôt';
            const statusClass = isTransit ? 'status-transit' : isPlanifie ? 'status-planifie' : 'status-depot';
            const statusEmoji = isTransit ? '🟢' : isPlanifie ? '🟡' : '⚪';
            const icon = VEHICLE_ICONS[v.type] || '🚗';
            
            html += `
                <div class="vehicle-card ${isTransit ? 'active-trip' : ''}" onclick="window.focusVehicle(${v.id})">
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="font-size:22px;flex-shrink:0">${icon}</div>
                        <div style="flex:1;min-width:0">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px">
                                <span style="font-family:'Outfit',sans-serif;font-weight:900;font-size:13px;color:#0f172a;letter-spacing:-0.02em">${v.immatriculation}</span>
                                <span class="status-badge-inline ${statusClass}">${statusLabel}</span>
                            </div>
                            <div style="font-size:10px;color:#94a3b8;font-weight:600;margin-bottom:4px">
                                ${v.ligne_code ? v.ligne_code + ' • ' : ''}${v.vitesse ? Math.round(v.vitesse) + ' km/h' : 'Arrêté'}
                            </div>
                            <div class="fuel-bar" style="width:80px">
                                <div class="fuel-bar-fill ${fuelClass}" style="width:${fuelPct}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html || '<div class="p-6 text-center text-slate-400 text-xs">Aucun véhicule trouvé</div>';
        
        // Update stats
        const statActive = document.getElementById('stat-active');
        const statTransit = document.getElementById('stat-transit');
        if (statActive) statActive.textContent = activeCount;
        if (statTransit) statTransit.textContent = transitCount;
    }

    // =========================================================
    // MARKER MANAGEMENT
    // =========================================================
    function updateMarkers(vehicles) {
        vehicles.forEach(v => {
            if (!v.latitude || !v.longitude) return;
            
            const lat = parseFloat(v.latitude);
            const lng = parseFloat(v.longitude);
            
            if (isNaN(lat) || isNaN(lng) || lat === 0) return;
            
            if (markers[v.id]) {
                // Smooth move existing marker
                const currentLatLng = markers[v.id].getLatLng();
                animateMarker(markers[v.id], [lat, lng], 2500);
                markers[v.id].setIcon(createVehicleIcon(v));
                markers[v.id].setPopupContent(createPopupContent(v));
            } else {
                // Create new marker
                const marker = L.marker([lat, lng], {
                    icon: createVehicleIcon(v)
                }).addTo(map);
                
                marker.bindPopup(createPopupContent(v), {
                    maxWidth: 280,
                    closeButton: true
                });
                
                markers[v.id] = marker;
            }
        });
    }

    function animateMarker(marker, targetLatLng, duration) {
        const start = marker.getLatLng();
        const startTime = performance.now();
        
        function step(currentTime) {
            const elapsed = currentTime - startTime;
            const t = Math.min(elapsed / duration, 1);
            
            // Ease-in-out
            const ease = t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
            
            const lat = start.lat + (targetLatLng[0] - start.lat) * ease;
            const lng = start.lng + (targetLatLng[1] - start.lng) * ease;
            
            marker.setLatLng([lat, lng]);
            
            if (t < 1) {
                requestAnimationFrame(step);
            }
        }
        
        requestAnimationFrame(step);
    }

    // =========================================================
    // FOCUS ON VEHICLE
    // =========================================================
    window.focusVehicle = function(vehicleId) {
        const marker = markers[vehicleId];
        if (marker) {
            map.flyTo(marker.getLatLng(), 13, { duration: 1.2 });
            marker.openPopup();
        }
    };

    // =========================================================
    // SSE CONNECTION
    // =========================================================
    function connectSSE() {
        if (eventSource) {
            eventSource.close();
        }
        
        eventSource = new EventSource(`${API_URL}/stream`);
        
        eventSource.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);
                
                if (data.error) {
                    console.error('[SSE Error]', data.error);
                    return;
                }
                
                // Update markers
                updateMarkers(data.vehicles);
                
                // Update vehicle list
                updateVehicleList(data.vehicles);
                
                // Update simulation status
                updateSimulationUI(data.simulation_active);
                
                // Update timestamp
                const lastUpdate = document.getElementById('last-update');
                if (lastUpdate) lastUpdate.textContent = data.timestamp || '--:--:--';
                
                // Handle new incidents
                if (data.new_incident) {
                    fetchNewIncidents();
                }
                
            } catch (e) {
                console.error('[SSE Parse Error]', e);
            }
        };
        
        eventSource.onerror = function() {
            console.warn('[SSE] Connection lost, reconnecting in 5s...');
            eventSource.close();
            setTimeout(connectSSE, 5000);
        };
    }

    // =========================================================
    // INCIDENT ALERTS
    // =========================================================
    async function fetchNewIncidents() {
        try {
            const response = await fetch(`${API_URL}/api/recent-incidents`);
            const data = await response.json();
            
            const container = document.getElementById('alert-container');
            if (!container) return;
            
            // Update incident count
            const statIncidents = document.getElementById('stat-incidents');
            if (statIncidents) statIncidents.textContent = data.incidents.length;
            
            data.incidents.forEach(inc => {
                if (lastIncidentIds.has(inc.id)) return;
                lastIncidentIds.add(inc.id);
                
                const gravColor = inc.gravite === 'grave' ? 'rgba(220,38,38,0.95)' 
                                : inc.gravite === 'moyen' ? 'rgba(217,119,6,0.95)' 
                                : 'rgba(100,116,139,0.95)';
                
                const toast = document.createElement('div');
                toast.className = 'alert-toast-item';
                toast.style.background = gravColor;
                toast.innerHTML = `
                    <span style="font-size:16px">⚠️</span>
                    <div>
                        <div style="font-weight:900;text-transform:uppercase;font-size:9px;letter-spacing:0.1em;opacity:0.8;margin-bottom:2px">${inc.type} — ${inc.gravite}</div>
                        <div>${inc.description}</div>
                        <div style="font-size:10px;opacity:0.7;margin-top:2px">${inc.immatriculation} • ${inc.ligne_nom}</div>
                    </div>
                `;
                container.appendChild(toast);
                
                // Auto-remove after 8s
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(-30px)';
                    toast.style.transition = 'all 0.5s';
                    setTimeout(() => toast.remove(), 500);
                }, 8000);
            });
            
        } catch (error) {
            console.error('[Incidents]', error);
        }
    }

    // =========================================================
    // SIMULATION CONTROLS
    // =========================================================
    window.toggleSimulation = async function() {
        try {
            const response = await fetch(`${API_URL}/api/simulation/toggle`, { method: 'POST' });
            const data = await response.json();
            updateSimulationUI(data.active);
        } catch (error) {
            console.error('[Simulation Toggle]', error);
        }
    };

    function updateSimulationUI(isActive) {
        simulationActive = isActive;
        const btn = document.getElementById('sim-toggle-btn');
        const text = document.getElementById('sim-toggle-text');
        const status = document.getElementById('sim-status');
        
        if (isActive) {
            btn.className = 'sim-btn sim-btn-stop';
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg><span id="sim-toggle-text">Arrêter</span>';
            status.className = 'sim-status-badge active';
            status.innerHTML = '🟢 Simulation active';
        } else {
            btn.className = 'sim-btn sim-btn-start';
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg><span id="sim-toggle-text">Démarrer</span>';
            status.className = 'sim-status-badge inactive';
            status.innerHTML = '⏸ Simulation inactive';
        }
    }

    // =========================================================
    // PANEL TOGGLE
    // =========================================================
    window.togglePanel = function() {
        const panel = document.getElementById('tracking-panel');
        const btn = document.getElementById('toggle-panel-btn');
        panel.classList.toggle('collapsed');
        btn.classList.toggle('panel-hidden');
    };

    // =========================================================
    // INITIAL LOAD
    // =========================================================
    async function init() {
        // Draw routes on map
        await drawRoutes();
        
        // Load initial vehicle positions
        try {
            const response = await fetch(`${API_URL}/api/vehicles/positions`);
            const data = await response.json();
            
            updateMarkers(data.vehicles);
            updateVehicleList(data.vehicles);
            updateSimulationUI(data.simulation_active);
            
            // Fit map to show all vehicles
            const positions = data.vehicles
                .filter(v => v.latitude && v.longitude)
                .map(v => [parseFloat(v.latitude), parseFloat(v.longitude)]);
            
            if (positions.length > 0) {
                const bounds = L.latLngBounds(positions);
                map.fitBounds(bounds.pad(0.3), { maxZoom: 13 });
            }
        } catch (error) {
            console.error('[Init]', error);
        }
        
        // Fetch initial incidents
        fetchNewIncidents();
        
        // Connect SSE for live updates
        connectSSE();
    }

    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
