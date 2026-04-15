<?php 
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
?>
<html lang="fr" class="h-full bg-[#f4f7f6]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TranspoBot V2 - Gestion de Transport</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Inter & Outfit for headers -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        primary: '#064e3b', // Deep Forest
                        accent: '#f59e0b',  // Amber
                        stone: '#f4f7f6',
                    }
                }
            }
        }
    </script>
    <style>
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .sidebar-item-active {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border-left: 4px solid #f59e0b;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        #sidebar { transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        #sidebar.collapsed { width: 5.5rem; }
        #sidebar.collapsed .nav-text, 
        #sidebar.collapsed .brand-text, 
        #sidebar.collapsed .status-block { display: none; }
        #sidebar.collapsed .nav-icon { margin-right: 0; margin: 0 auto; }
        #sidebar.collapsed .nav-link { justify-content: center; padding-left: 0; padding-right: 0; text-align: center; }
        
        /* Mobile Sidebar Animation */
        #mobile-sidebar { transition: transform 0.3s ease-in-out; }
        #mobile-sidebar.hidden-mobile { transform: translateX(-100%); }
        #mobile-overlay { transition: opacity 0.3s ease-in-out; }
        #mobile-overlay.hidden-mobile { opacity: 0; pointer-events: none; }
    </style>
</head>
<body class="h-full font-sans text-slate-700 overflow-hidden bg-[#f4f7f6]">
    <!-- Mobile Sidebar Overlay -->
    <div id="mobile-overlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] md:hidden hidden-mobile" onclick="toggleMobileMenu()"></div>
    <aside id="mobile-sidebar" class="fixed inset-y-0 left-0 w-72 bg-[#064e3b] z-[70] md:hidden flex flex-col hidden-mobile shadow-2xl">
        <div class="p-6 border-b border-white/10 flex items-center justify-between">
            <span class="text-xl font-display font-black text-white tracking-tight">Transpo<span class="text-emerald-400">Bot</span></span>
            <button onclick="toggleMobileMenu()" class="text-emerald-100 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
            <a href="index.php" class="flex items-center px-4 py-3.5 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-white/10 text-white border-l-4 border-amber-400 pl-3' : 'text-emerald-100/60 hover:bg-white/5 hover:text-white'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>Tableau de bord</span>
            </a>
            <a href="flotte.php" class="flex items-center px-4 py-3.5 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'flotte.php' ? 'bg-white/10 text-white border-l-4 border-amber-400 pl-3' : 'text-emerald-100/60 hover:bg-white/5 hover:text-white'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                </svg>
                <span>Gestion Flotte</span>
            </a>
            <a href="tracking.php" class="flex items-center px-4 py-3.5 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'tracking.php' ? 'bg-white/10 text-white border-l-4 border-amber-400 pl-3' : 'text-emerald-100/60 hover:bg-white/5 hover:text-white'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Tracking GPS</span>
            </a>
            <a href="chauffeurs.php" class="flex items-center px-4 py-3.5 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'chauffeurs.php' ? 'bg-white/10 text-white border-l-4 border-amber-400 pl-3' : 'text-emerald-100/60 hover:bg-white/5 hover:text-white'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>Gestion Personnel</span>
            </a>
            <a href="historique.php" class="flex items-center px-4 py-3.5 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'historique.php' ? 'bg-white/10 text-white border-l-4 border-amber-400 pl-3' : 'text-emerald-100/60 hover:bg-white/5 hover:text-white'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Historique Trajets</span>
            </a>
            <a href="incidents.php" class="flex items-center px-4 py-3.5 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'incidents.php' ? 'bg-white/10 text-white border-l-4 border-amber-400 pl-3' : 'text-emerald-100/60 hover:bg-white/5 hover:text-white'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>Rapports Incidents</span>
            </a>
        </nav>
    </aside>

    <div class="h-full flex overflow-hidden w-full">
        <!-- Sidebar -->
        <aside class="w-72 bg-[#064e3b] flex-shrink-0 hidden md:flex flex-col relative overflow-hidden" id="sidebar">
            <!-- Decorative Background Element -->
            <div class="absolute -top-24 -left-24 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl"></div>
            
            <div class="p-8 relative z-10 flex items-center justify-center">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 bg-gradient-to-br from-emerald-400 to-green-600 rounded-2xl flex items-center justify-center text-white shadow-xl shadow-emerald-900/20 flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <span class="brand-text text-2xl font-display font-black text-white tracking-tight">Transpo<span class="text-emerald-400">Bot</span></span>
                </div>
            </div>
            
            <nav class="flex-1 px-4 space-y-1.5 mt-4 relative z-10">
                <a href="index.php" class="nav-link flex items-center px-4 py-3.5 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-white/10 text-white border-l-4 border-amber-400 pl-3' : 'text-emerald-100/60 hover:bg-white/5 hover:text-white'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="nav-icon h-5 w-5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="nav-text">Tableau de bord</span>
                </a>
                <a href="flotte.php" class="nav-link flex items-center px-4 py-3.5 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'flotte.php' ? 'bg-white/10 text-white border-l-4 border-amber-400 pl-3' : 'text-emerald-100/60 hover:bg-white/5 hover:text-white'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="nav-icon h-5 w-5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                    <span class="nav-text">Gestion Flotte</span>
                </a>
                <a href="tracking.php" class="nav-link flex items-center px-4 py-3.5 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'tracking.php' ? 'bg-white/10 text-white border-l-4 border-amber-400 pl-3' : 'text-emerald-100/60 hover:bg-white/5 hover:text-white'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="nav-icon h-5 w-5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="nav-text">Tracking GPS</span>
                    <span class="nav-text ml-auto bg-emerald-400 text-emerald-950 text-[8px] px-1.5 py-0.5 rounded-full font-black animate-pulse">LIVE</span>
                </a>
                <a href="chauffeurs.php" class="nav-link flex items-center px-4 py-3.5 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'chauffeurs.php' ? 'bg-white/10 text-white border-l-4 border-amber-400 pl-3' : 'text-emerald-100/60 hover:bg-white/5 hover:text-white'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="nav-icon h-5 w-5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span class="nav-text">Gestion Personnel</span>
                </a>
                <a href="historique.php" class="nav-link flex items-center px-4 py-3.5 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'historique.php' ? 'bg-white/10 text-white border-l-4 border-amber-400 pl-3' : 'text-emerald-100/60 hover:bg-white/5 hover:text-white'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="nav-icon h-5 w-5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="nav-text">Historique Trajets</span>
                </a>
                <a href="incidents.php" class="nav-link flex items-center px-4 py-3.5 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'incidents.php' ? 'bg-white/10 text-white border-l-4 border-amber-400 pl-3' : 'text-emerald-100/60 hover:bg-white/5 hover:text-white'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="nav-icon h-5 w-5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span class="nav-text">Rapports Incidents</span>
                    <?php 
                        $unresolved_count = $pdo->query("SELECT COUNT(*) FROM incidents WHERE resolu = 0")->fetchColumn();
                        if ($unresolved_count > 0): 
                    ?>
                        <span class="nav-text ml-auto bg-amber-400 text-emerald-900 text-[10px] px-1.5 py-0.5 rounded-full font-black"><?php echo $unresolved_count; ?></span>
                    <?php endif; ?>
                </a>
            </nav>

            <div class="p-4 mt-auto">
                <div class="status-block bg-slate-900 rounded-2xl p-4 text-white">
                    <p class="text-xs text-slate-400 font-medium mb-1">Status Serveur AI</p>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full ai-status-dot bg-slate-400"></div>
                        <span class="text-sm font-semibold ai-status-label text-slate-400">Vérification...</span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-[#f4f7f6]">
            <!-- Header -->
            <header class="h-20 bg-white/70 backdrop-blur-md border-b border-slate-200/60 flex items-center justify-between px-4 md:px-8 flex-shrink-0 sticky top-0 z-40">
                <div class="flex items-center gap-2 md:gap-4">
                    <button id="menu-toggle-btn" class="w-10 h-10 flex items-center justify-center text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-xl transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div>
                        <h1 class="text-lg md:text-xl font-display font-black text-emerald-950 tracking-tight leading-tight">
                            <?php 
                                $page = basename($_SERVER['PHP_SELF']);
                                if($page == 'index.php') echo 'Tableau de bord';
                                else if($page == 'flotte.php') echo 'Flotte de Véhicules';
                                else if($page == 'chauffeurs.php') echo 'Personnel Navigant';
                                else if($page == 'historique.php') echo 'Historique d\'Activités';
                                else if($page == 'incidents.php') echo 'Rapports d\'Incidents';
                                else if($page == 'tracking.php') echo 'Tracking GPS en Direct';
                            ?>
                        </h1>
                        <p class="hidden sm:block text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">MS Management</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2 md:gap-6">
                    <button class="hidden sm:flex w-10 h-10 items-center justify-center text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-xl transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </button>
                    <div class="flex items-center gap-2 md:gap-3 pl-2 md:pl-6 border-l border-slate-200">
                        <div class="text-right hidden sm:block">
                            <p class="text-xs font-bold text-slate-800">Admin</p>
                            <p class="text-[10px] text-emerald-600 font-black tracking-widest uppercase italic leading-tight">Master</p>
                        </div>
                        <div class="w-8 h-8 md:w-10 md:h-10 rounded-xl bg-gradient-to-tr from-slate-200 to-slate-300 border-2 border-white shadow-sm overflow-hidden flex-shrink-0">
                            <img src="https://ui-avatars.com/api/?name=Admin&background=064e3b&color=fff" alt="Avatar">
                        </div>
                    </div>
                </div>
            </header>

            <script>
                function toggleMobileMenu() {
                    const overlay = document.getElementById('mobile-overlay');
                    const sidebar = document.getElementById('mobile-sidebar');
                    overlay.classList.toggle('hidden-mobile');
                    sidebar.classList.toggle('hidden-mobile');
                }

                document.getElementById('menu-toggle-btn').addEventListener('click', function() {
                    if (window.innerWidth < 768) {
                        toggleMobileMenu();
                    } else {
                        document.getElementById('sidebar').classList.toggle('collapsed');
                    }
                });
            </script>

            <div class="flex-1 overflow-x-hidden overflow-y-auto p-4 md:p-6 flex flex-col min-h-0 relative pb-24 md:pb-6">
