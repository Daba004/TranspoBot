<?php 
header('Content-Type: text/html; charset=UTF-8');
require_once 'includes/db.php';

// Fetch stats for the sidebar panel
$stats = [
    'total_vehicles' => $pdo->query("SELECT COUNT(*) FROM vehicules")->fetchColumn(),
    'active_vehicles' => $pdo->query("SELECT COUNT(*) FROM vehicules WHERE statut='actif'")->fetchColumn(),
    'en_cours' => $pdo->query("SELECT COUNT(*) FROM trajets WHERE statut='en_cours'")->fetchColumn(),
    'incidents_actifs' => $pdo->query("SELECT COUNT(*) FROM incidents WHERE resolu=0")->fetchColumn(),
];

include 'includes/header.php'; 
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
      crossorigin=""/>

<style>
    /* Override the main content padding for full-bleed map */
    #map-container { 
        position: relative; 
        flex: 1; 
        min-height: 0;
        border-radius: 1rem;
        overflow: hidden;
        border: 1px solid rgba(148,163,184,0.2);
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    }
    #map { 
        width: 100%; 
        height: 100%; 
        z-index: 1;
    }
    
    /* Vehicle markers */
    .vehicle-marker {
        background: none;
        border: none;
    }
    .marker-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 900;
        color: white;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        border: 3px solid white;
        transition: transform 0.3s ease;
        position: relative;
    }
    .marker-icon:hover { transform: scale(1.2); }
    .marker-icon.actif { background: linear-gradient(135deg, #059669, #047857); }
    .marker-icon.maintenance { background: linear-gradient(135deg, #d97706, #b45309); }
    .marker-icon.hors_service { background: linear-gradient(135deg, #dc2626, #b91c1c); }
    .marker-icon.en_transit { 
        background: linear-gradient(135deg, #059669, #047857);
        animation: pulse-marker 2s infinite;
    }
    
    @keyframes pulse-marker {
        0%, 100% { box-shadow: 0 4px 15px rgba(5,150,105,0.4); }
        50% { box-shadow: 0 4px 25px rgba(5,150,105,0.7), 0 0 0 8px rgba(5,150,105,0.15); }
    }
    
    /* Side panel overlay */
    .tracking-panel {
        position: absolute;
        top: 12px;
        right: 12px;
        bottom: 12px;
        width: 340px;
        z-index: 1000;
        background: rgba(255,255,255,0.92);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 1.25rem;
        border: 1px solid rgba(255,255,255,0.6);
        box-shadow: 0 20px 60px rgba(0,0,0,0.12);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: transform 0.4s cubic-bezier(0.4,0,0.2,1);
    }
    .tracking-panel.collapsed {
        transform: translateX(calc(100% + 20px));
    }
    
    /* Simulation control bar */
    .sim-control {
        position: absolute;
        top: 12px;
        left: 12px;
        z-index: 1000;
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .sim-btn {
        padding: 10px 20px;
        border-radius: 1rem;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(20px);
    }
    .sim-btn-start {
        background: rgba(6,78,59,0.95);
        color: white;
        box-shadow: 0 8px 25px rgba(6,78,59,0.3);
    }
    .sim-btn-start:hover {
        background: rgba(6,78,59,1);
        transform: translateY(-1px);
    }
    .sim-btn-stop {
        background: rgba(220,38,38,0.95);
        color: white;
        box-shadow: 0 8px 25px rgba(220,38,38,0.3);
    }
    .sim-btn-stop:hover {
        background: rgba(220,38,38,1);
        transform: translateY(-1px);
    }
    .sim-status-badge {
        padding: 8px 16px;
        border-radius: 1rem;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        backdrop-filter: blur(20px);
    }
    .sim-status-badge.active {
        background: rgba(5,150,105,0.15);
        color: #059669;
        border: 1px solid rgba(5,150,105,0.3);
    }
    .sim-status-badge.inactive {
        background: rgba(148,163,184,0.15);
        color: #64748b;
        border: 1px solid rgba(148,163,184,0.3);
    }
    
    /* Toggle panel button */
    .toggle-panel-btn {
        position: absolute;
        top: 12px;
        right: 364px;
        z-index: 1001;
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255,255,255,0.5);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s;
        color: #334155;
    }
    .toggle-panel-btn:hover { 
        background: white;
        transform: scale(1.05); 
    }
    .toggle-panel-btn.panel-hidden {
        right: 12px;
    }
    
    /* Alert toasts */
    .alert-toast {
        position: absolute;
        bottom: 16px;
        left: 16px;
        z-index: 1000;
        max-width: 380px;
    }
    .alert-toast-item {
        background: rgba(220,38,38,0.95);
        backdrop-filter: blur(20px);
        color: white;
        padding: 12px 16px;
        border-radius: 1rem;
        margin-top: 8px;
        font-size: 12px;
        font-weight: 700;
        box-shadow: 0 10px 30px rgba(220,38,38,0.3);
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideInLeft 0.5s ease;
    }
    @keyframes slideInLeft {
        from { opacity: 0; transform: translateX(-30px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    /* Vehicle list items */
    .vehicle-card {
        padding: 12px 16px;
        border-bottom: 1px solid rgba(148,163,184,0.1);
        cursor: pointer;
        transition: all 0.2s;
    }
    .vehicle-card:hover {
        background: rgba(5,150,105,0.05);
    }
    .vehicle-card.active-trip {
        border-left: 3px solid #059669;
    }
    
    /* Fuel gauge */
    .fuel-bar {
        height: 4px;
        border-radius: 2px;
        background: #e2e8f0;
        overflow: hidden;
        width: 100%;
    }
    .fuel-bar-fill {
        height: 100%;
        border-radius: 2px;
        transition: width 1s ease;
    }
    .fuel-high { background: linear-gradient(90deg, #059669, #10b981); }
    .fuel-medium { background: linear-gradient(90deg, #d97706, #f59e0b); }
    .fuel-low { background: linear-gradient(90deg, #dc2626, #ef4444); }
    
    /* Status badges for sidebar list */
    .status-badge-inline {
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 8px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .status-transit { background: #dcfce7; color: #15803d; }
    .status-planifie { background: #fef3c7; color: #92400e; }
    .status-depot { background: #f1f5f9; color: #64748b; }
    
    /* Leaflet popup custom styling */
    .leaflet-popup-content-wrapper {
        border-radius: 16px !important;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important;
        border: 1px solid rgba(148,163,184,0.2) !important;
    }
    .leaflet-popup-content {
        margin: 12px 16px !important;
        font-family: 'Inter', sans-serif !important;
    }
    .popup-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 900;
        font-size: 15px;
        color: #0f172a;
        letter-spacing: -0.02em;
    }
    .popup-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 9px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }
</style>

<div id="map-container">
    <div id="map"></div>
    
    <!-- Simulation Controls (Top-Left) -->
    <div class="sim-control">
        <button id="sim-toggle-btn" class="sim-btn sim-btn-start" onclick="toggleSimulation()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            <span id="sim-toggle-text">Démarrer</span>
        </button>
        <span id="sim-status" class="sim-status-badge inactive">⏸ Simulation inactive</span>
    </div>
    
    <!-- Toggle Panel Button -->
    <button class="toggle-panel-btn" id="toggle-panel-btn" onclick="togglePanel()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
    </button>
    
    <!-- Side Panel (Right) -->
    <div class="tracking-panel" id="tracking-panel">
        <div class="p-5 border-b border-slate-100/80 shrink-0 bg-white/50">
            <h3 class="font-display font-black text-slate-900 text-base tracking-tight">Flotte en Direct</h3>
            <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">Tracking temps reel</p>
            <div class="grid grid-cols-3 gap-2 mt-3">
                <div class="bg-emerald-50 rounded-xl p-2 text-center">
                    <p id="stat-active" class="font-display font-black text-emerald-700 text-lg"><?php echo $stats['active_vehicles']; ?></p>
                    <p class="text-[7px] font-black text-emerald-600 uppercase tracking-widest">Actifs</p>
                </div>
                <div class="bg-amber-50 rounded-xl p-2 text-center">
                    <p id="stat-transit" class="font-display font-black text-amber-700 text-lg"><?php echo $stats['en_cours']; ?></p>
                    <p class="text-[7px] font-black text-amber-600 uppercase tracking-widest">En Transit</p>
                </div>
                <div class="bg-rose-50 rounded-xl p-2 text-center">
                    <p id="stat-incidents" class="font-display font-black text-rose-700 text-lg"><?php echo $stats['incidents_actifs']; ?></p>
                    <p class="text-[7px] font-black text-rose-600 uppercase tracking-widest">Alertes</p>
                </div>
            </div>
        </div>
        
        <div class="flex-1 overflow-y-auto custom-scrollbar" id="vehicle-list">
            <!-- Vehicle cards will be populated by JS -->
            <div class="p-6 text-center text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto mb-3 text-slate-300 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <p class="text-xs font-bold">Chargement de la flotte...</p>
            </div>
        </div>
        
        <!-- Live clock -->
        <div class="p-3 bg-slate-900 text-center shrink-0">
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Derniere mise a jour</p>
            <p id="last-update" class="text-sm font-display font-black text-white tracking-tight">--:--:--</p>
        </div>
    </div>
    
    <!-- Alert Toasts (Bottom-Left) -->
    <div class="alert-toast" id="alert-container"></div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
        crossorigin=""></script>
<script src="assets/js/tracking.js"></script>

<?php 
include 'includes/chatbot.php';
include 'includes/footer.php'; 
?>
