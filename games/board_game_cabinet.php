<!-- 
CATEGORY: Games
DESCRIPTION: The Board Game Cabinet is a sophisticated hub for hosting a private collection of digital board games. It features a premium wooden aesthetic and dynamically organizes available titles from a local directory into a polished, immersive gaming environment.
TAGS: Chess, Strategy, Classics, Tabletop, Premium
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Board Game Cabinet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=Lora:wght@400;600&display=swap');

        :root {
            --cabinet-dark: #2c1810;
            --cabinet-wood: #4a2c1d;
            --cabinet-accent: #d4af37; /* Brass/Gold */
            --cabinet-leather: #5d3a1a;
            --felt-green: #1b4d3e;
            /* ICON SIZE SETTING */
            --icon-size: 32px;
        }

        body {
            font-family: 'Lora', serif;
            background-color: #1a0f0a;
            color: #e5e5e5;
            overflow: hidden;
        }

        .cabinet-header {
            font-family: 'Playfair Display', serif;
            background: linear-gradient(to bottom, #3e2418, #2c1810);
            border-bottom: 2px solid var(--cabinet-accent);
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        }

        .wood-panel {
            background: linear-gradient(90deg, #3e2418 0%, #4a2c1d 50%, #3e2418 100%);
            box-shadow: inset 0 0 50px rgba(0,0,0,0.4);
        }

        .game-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            background: rgba(44, 24, 16, 0.4);
        }

        .game-card:hover {
            transform: scale(1.02);
            background-color: var(--cabinet-leather);
            border-color: var(--cabinet-accent);
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.2);
        }

        .game-card.active {
            background-color: var(--cabinet-leather);
            border-left: 6px solid var(--cabinet-accent);
            box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
        }

        .game-icon {
            width: var(--icon-size);
            height: var(--icon-size);
            object-fit: contain;
            flex-shrink: 0;
        }

        .main-stage {
            background-color: var(--felt-green);
            background-image: radial-gradient(circle, rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 30px 30px;
            box-shadow: inset 0 0 100px rgba(0,0,0,0.8);
        }

        .brass-ornament {
            color: var(--cabinet-accent);
            filter: drop-shadow(0 2px 2px rgba(0,0,0,0.5));
        }

        .custom-scroll::-webkit-scrollbar {
            width: 8px;
        }
        .custom-scroll::-webkit-scrollbar-track {
            background: #2c1810;
        }
        .custom-scroll::-webkit-scrollbar-thumb {
            background: var(--cabinet-accent);
            border-radius: 4px;
            border: 2px solid #2c1810;
        }

        .leather-texture {
            background-image: url('https://www.transparenttextures.com/patterns/leather.png');
        }
    </style>
</head>
<body class="h-screen flex flex-col">

    <!-- Header -->
    <header class="cabinet-header p-4 flex justify-between items-center z-30">
        <div class="flex items-center space-x-6">
            <div class="flex items-center brass-ornament">
                <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71L12 2z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-3xl tracking-tight italic">The Board Game Cabinet</h1>
                <p class="text-[10px] uppercase tracking-[0.4em] text-yellow-600 font-bold">Exquisite Digital Amusements</p>
            </div>
        </div>
        <div class="flex flex-col items-end opacity-60 italic text-sm">
            <span id="cabinet-date"></span>
            <span id="cabinet-clock" class="font-mono"></span>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar: Game Collection -->
        <aside class="w-80 wood-panel flex flex-col z-20 border-r border-black/40">
            <div class="p-6 border-b border-black/20 leather-texture">
                <h2 class="text-xs font-bold uppercase tracking-widest text-yellow-700/80">Inventory</h2>
                <p class="text-xl font-bold text-yellow-100/90 italic">Game Library</p>
            </div>
            
            <nav id="game-list" class="flex-1 overflow-y-auto p-3 space-y-1 custom-scroll">
                <?php
                $directory = 'boardgames';
                $foundGames = [];
                $allowedExtensions = ['html', 'htm', 'php'];
                $imageExtensions = ['png', 'ico', 'webp'];

                if (is_dir($directory)) {
                    $files = scandir($directory);
                    foreach ($files as $file) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (in_array($ext, $allowedExtensions)) {
                            $baseName = pathinfo($file, PATHINFO_FILENAME);
                            
                            // Check for corresponding icon
                            $iconPath = null;
                            foreach ($imageExtensions as $imgExt) {
                                if (file_exists("$directory/$baseName.$imgExt")) {
                                    $iconPath = "$directory/$baseName.$imgExt";
                                    break;
                                }
                            }

                            // Format Display Name
                            $displayName = str_replace(['_', '-'], ' ', $baseName);
                            $displayName = preg_replace('/([a-z])([A-Z])/', '$1 $2', $displayName);
                            $displayName = ucwords(trim($displayName));

                            $foundGames[] = [
                                'file' => $file,
                                'name' => $displayName,
                                'icon' => $iconPath
                            ];
                        }
                    }
                    
                    usort($foundGames, function($a, $b) { 
                        return strcasecmp($a['name'], $b['name']); 
                    });

                    if (empty($foundGames)) {
                        echo '<div class="p-4 text-center text-yellow-800/50 italic text-sm">The cabinet is currently empty.</div>';
                    } else {
                        echo '<h3 class="px-4 py-1 text-[10px] font-black uppercase tracking-[0.3em] text-yellow-700/60 mt-2 mb-1">Available Collection</h3>';
                        foreach ($foundGames as $game) {
                            $iconHtml = $game['icon'] ? '<img src="'.htmlspecialchars($game['icon']).'" class="game-icon rounded shadow-sm" alt="">' : '';
                            
                            echo '<button class="game-card w-full text-left px-3 py-2 rounded flex items-center justify-between group" 
                                          onclick="loadGame(\'' . htmlspecialchars($game['file']) . '\', this)">
                                    <div class="flex items-center space-x-3 overflow-hidden">
                                        ' . $iconHtml . '
                                        <span class="text-sm font-semibold text-yellow-100/90 group-hover:text-yellow-400 transition-colors italic truncate">' . htmlspecialchars($game['name']) . '</span>
                                    </div>
                                    <svg class="w-3 h-3 text-yellow-900 flex-shrink-0 group-hover:text-yellow-500 transition-all transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                  </button>';
                        }
                    }
                } else {
                    echo '<div class="p-4 text-center text-red-900/70 italic text-sm">Directory "boardgames" not found.</div>';
                }
                ?>
            </nav>
        </aside>

        <!-- Main Stage -->
        <main class="flex-1 relative main-stage">
            <div id="welcome-screen" class="absolute inset-0 flex flex-col items-center justify-center text-center p-10 transition-opacity duration-700">
                <div class="max-w-md">
                    <div class="mb-8 brass-ornament animate-pulse">
                        <svg class="w-24 h-24 mx-auto" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                            <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <h2 class="text-4xl font-serif italic text-yellow-100/80">Prepare the Table</h2>
                    <p class="mt-6 text-yellow-200/50 leading-relaxed font-light">
                        Welcome to the private collection. Please select a title from the cabinet on the left to begin your session.
                    </p>
                    <div class="mt-10 h-px w-32 bg-yellow-600/30 mx-auto"></div>
                </div>
            </div>

            <iframe id="game-viewport" class="w-full h-full border-none hidden opacity-0 transition-opacity duration-1000" allow="autoplay; focus;"></iframe>
            
            <div id="loader" class="absolute inset-0 bg-black/60 flex items-center justify-center hidden z-40">
                <div class="flex flex-col items-center">
                    <div class="relative w-20 h-20">
                        <div class="absolute inset-0 border-4 border-yellow-900 rounded-full"></div>
                        <div class="absolute inset-0 border-4 border-yellow-500 border-t-transparent rounded-full animate-spin"></div>
                    </div>
                    <p class="mt-6 font-serif italic text-yellow-500 text-xl animate-pulse">Arranging Pieces...</p>
                </div>
            </div>
        </main>
    </div>

    <script>
        const viewport = document.getElementById('game-viewport');
        const welcomeScreen = document.getElementById('welcome-screen');
        const loader = document.getElementById('loader');

        function loadGame(filename, element) {
            document.querySelectorAll('.game-card').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            
            loader.classList.remove('hidden');
            welcomeScreen.classList.add('opacity-0');
            viewport.classList.add('opacity-0');
            
            setTimeout(() => {
                viewport.src = `boardgames/${filename}`;
                viewport.onload = () => {
                    loader.classList.add('hidden');
                    welcomeScreen.classList.add('hidden');
                    viewport.classList.remove('hidden');
                    setTimeout(() => viewport.classList.remove('opacity-0'), 100);
                };
            }, 600);
        }

        function updateClock() {
            const now = new Date();
            const dateOptions = { weekday: 'long', month: 'long', day: 'numeric' };
            document.getElementById('cabinet-date').innerText = now.toLocaleDateString('en-US', dateOptions);
            document.getElementById('cabinet-clock').innerText = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'});
        }

        window.onload = () => {
            updateClock();
            setInterval(updateClock, 1000);
        };
    </script>
    <a href="http://web-mage.ca/games/"><img src="WebMage-sm.webp" style="position: fixed; right: 16px; bottom: 16px; height: 50px; z-index: 9999; pointer-events: auto;"></a>
</body>
</html>