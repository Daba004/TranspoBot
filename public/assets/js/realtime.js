/**
 * TranspoBot — Shared Real-Time Data Sync
 * Updates dashboard stats and tables via SSE
 */
(function() {
    'use strict';

    const API_URL = window.location.origin;
    let eventSource = null;

    function formatNumber(num) {
        return new Intl.NumberFormat('fr-FR').format(num);
    }

    // =========================================================
    // DOM UPDATERS
    // =========================================================
    function updateStats(stats) {
        const elements = {
            'stat-vehicules': stats.total_vehicles,
            'stat-chauffeurs': stats.available_chauffeurs,
            'stat-trajets': stats.finished_trips,
            'stat-recette': formatNumber(stats.total_recette),
            'stat-incidents': stats.active_incidents,
            'stat-inc-total': stats.total_incidents, 
            'stat-inc-current': stats.active_incidents,
            'stat-inc-grave': stats.grave_incidents,
        };

        for (const [id, value] of Object.entries(elements)) {
            const el = document.getElementById(id);
            if (el && value !== undefined) {
                if (el.textContent !== String(value)) {
                    el.textContent = value;
                    // Pulse animation for change
                    el.classList.add('animate-pulse');
                    setTimeout(() => el.classList.remove('animate-pulse'), 1000);
                }
            }
        }
    }

    function updateFleetTable(vehicles) {
        vehicles.forEach(v => {
            const row = document.getElementById(`vehicle-row-${v.id}`);
            if (!row) return;

            // Update Status Badge
            const statusCell = row.querySelector('.status-cell');
            if (statusCell) {
                const isTransit = v.trajet_statut === 'en_cours';
                const label = isTransit ? 'En Transit' : v.statut;
                const baseClass = "px-2.5 py-1 rounded-full text-[8px] font-black uppercase tracking-widest shadow-sm ";
                const statusClass = isTransit ? 'bg-emerald-100 text-emerald-700' : (v.statut === 'maintenance' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700');
                
                if (statusCell.textContent.trim().toLowerCase() !== label.toLowerCase()) {
                    statusCell.className = baseClass + statusClass + " status-cell";
                    statusCell.textContent = label;
                }
            }

            // Update Mileage/Fuel
            const fuelBar = row.querySelector('.fuel-bar-fill');
            if (fuelBar) {
                fuelBar.style.width = `${v.carburant}%`;
                if (v.carburant < 20) fuelBar.classList.replace('bg-emerald-500', 'bg-rose-500');
                else if (v.carburant < 50) fuelBar.classList.replace('bg-emerald-500', 'bg-amber-500');
            }

            // Update Speed (if column exists)
            const speedCell = row.querySelector('.speed-cell');
            if (speedCell) {
                speedCell.textContent = `${Math.round(v.vitesse || 0)} km/h`;
            }
        });
    }

    // =========================================================
    // SSE CONNECTION
    // =========================================================
    function connect() {
        if (eventSource) eventSource.close();
        
        eventSource = new EventSource(`${API_URL}/stream`);
        
        eventSource.onmessage = function(e) {
            try {
                const data = JSON.parse(e.data);
                if (data.stats) updateStats(data.stats);
                if (data.vehicles) updateFleetTable(data.vehicles);
                
                // If on dashboard, maybe refresh the "Recent Trips" table if something finished
                if (data.stats.finished_trips !== window._lastFinishedTrips) {
                    if (window.location.pathname.includes('index.php') || window.location.pathname === '/' || window.location.pathname.endsWith('transpobotv2/public/')) {
                        // For simplicity, we just reload the page or we could fetch the table via AJAX
                        // Given the complexity of the dashboard, a simple refresh or partial load is better
                        // But let's try to just update the existing rows in the dashboard table if possible
                    }
                    window._lastFinishedTrips = data.stats.finished_trips;
                }
            } catch (err) {
                console.error('[Realtime] Parse error', err);
            }
        };

        eventSource.onerror = function() {
            console.warn('[Realtime] Connection lost, retrying...');
            eventSource.close();
            setTimeout(connect, 5000);
        };
    }

    // Start
    connect();

})();
