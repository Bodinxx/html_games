<!-- 
CATEGORY: Games
DESCRIPTION: The Olympic Game System is a professional hub for hosting multi-disciplinary sports simulations. It dynamically organizes summer and winter events into a unified interface, allowing users to switch between athletics, aquatic races, and winter sports through a polished stadium view.
TAGS: Athletics, Swimming, Archery, Skiing, Combat
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Olympic Games Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Inter:wght@400;600;800&display=swap');

        :root {
            --olympic-blue: #0081C8;
            --olympic-yellow: #FCB131;
            --olympic-black: #000000;
            --olympic-green: #00A651;
            --olympic-red: #EE334E;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            overflow: hidden;
        }

        .olympic-header {
            font-family: 'Cinzel', serif;
            background: linear-gradient(135deg, var(--olympic-blue), var(--olympic-black));
        }

        .event-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 4px solid transparent;
        }

        .event-card:hover {
            transform: translateX(8px);
            background-color: #f8fafc;
            border-left-color: var(--olympic-blue);
        }

        .event-card.active {
            background-color: #eff6ff;
            border-left-color: var(--olympic-red);
            font-weight: 800;
        }

        .section-header {
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .stadium-shadow {
            box-shadow: inset 0 0 50px rgba(0,0,0,0.1);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
    </style>
</head>
<body class="h-screen flex flex-col">

    <!-- Header -->
    <header class="olympic-header text-white p-4 shadow-lg flex justify-between items-center z-10">
        <div class="flex items-center space-x-4">
            <div class="flex space-x-1">
                <div class="w-3 h-3 rounded-full bg-[#0081C8]"></div>
                <div class="w-3 h-3 rounded-full bg-[#FCB131]"></div>
                <div class="w-3 h-3 rounded-full bg-black"></div>
                <div class="w-3 h-3 rounded-full bg-[#00A651]"></div>
                <div class="w-3 h-3 rounded-full bg-[#EE334E]"></div>
            </div>
            <h1 class="text-2xl tracking-widest uppercase">Olympic Game System</h1>
        </div>
        <div id="clock" class="font-mono text-sm opacity-80"></div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar: Event List -->
        <aside class="w-80 bg-white shadow-xl flex flex-col z-20">
            <div class="p-6 border-b">
                <h2 class="text-xs font-bold uppercase tracking-tighter text-gray-400">Competition Schedule</h2>
                <p class="text-lg font-extrabold text-gray-800">Available Events</p>
            </div>
            
            <nav id="event-list" class="flex-1 overflow-y-auto p-2 space-y-4">
                <?php
                $directory = 'olympics';
                $summerEvents = [];
                $winterEvents = [];
                $allowedExtensions = ['html', 'htm', 'php'];

                if (is_dir($directory)) {
                    $files = scandir($directory);
                    foreach ($files as $file) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (in_array($ext, $allowedExtensions)) {
                            $filePath = $directory . '/' . $file;
                            
                            // Read first 1024 bytes to check for season tags
                            $content = file_get_contents($filePath, false, null, 0, 1024);
                            
                            $displayName = str_replace(['.html', '.htm', '.php', '_'], ['', '', '', ' '], $file);
                            $displayName = preg_replace('/([A-Z])/', ' $1', $displayName);
                            $displayName = trim($displayName);

                            $eventData = [
                                'file' => $file,
                                'name' => $displayName
                            ];

                            if (stripos($content, '<!-- WINTER -->') !== false) {
                                $winterEvents[] = $eventData;
                            } else {
                                // Default to Summer if not explicitly winter
                                $summerEvents[] = $eventData;
                            }
                        }
                    }

                    // Sort alphabetically
                    usort($summerEvents, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
                    usort($winterEvents, function($a, $b) { return strcasecmp($a['name'], $b['name']); });

                    function renderGroup($title, $events, $color) {
                        if (empty($events)) return;
                        echo '<div class="mb-4">';
                        echo '<h3 class="px-4 py-2 text-[10px] font-black uppercase tracking-[0.2em] text-'.$color.'-600 bg-'.$color.'-50/50 section-header">'.$title.' Games</h3>';
                        foreach ($events as $event) {
                            echo '<button class="event-card w-full text-left p-4 rounded-lg flex items-center justify-between group" 
                                          onclick="loadEvent(\'' . htmlspecialchars($event['file']) . '\', this)">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors">' . htmlspecialchars($event['name']) . '</span>
                                        <span class="text-[10px] text-gray-400 uppercase tracking-widest">Olympic Official Event</span>
                                    </div>
                                    <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-500 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                  </button>';
                        }
                        echo '</div>';
                    }

                    renderGroup('Summer', $summerEvents, 'orange');
                    renderGroup('Winter', $winterEvents, 'blue');

                    if (empty($summerEvents) && empty($winterEvents)) {
                        echo '<div class="p-4 text-center text-gray-400 italic text-sm">No events found in the olympics folder.</div>';
                    }
                } else {
                    echo '<div class="p-4 text-center text-red-400 italic text-sm">Folder "olympics" not found.</div>';
                }
                ?>
            </nav>

            <div class="p-4 bg-gray-50 border-t text-[10px] text-center text-gray-400 uppercase tracking-widest">
                © <?php echo date("Y"); ?> Olympic Digital Arena
            </div>
        </aside>

        <!-- Main Stage: Game View -->
        <main class="flex-1 relative bg-[#e5e7eb] stadium-shadow">
            <div id="welcome-screen" class="absolute inset-0 flex flex-col items-center justify-center text-center p-10 transition-opacity duration-500">
                <div class="max-w-md">
                    <svg class="w-24 h-24 mx-auto mb-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h2 class="text-3xl font-light text-gray-500">Welcome to the Arena</h2>
                    <p class="mt-4 text-gray-400">Select an event from the sidebar to begin the competition.</p>
                </div>
            </div>

            <iframe id="game-viewport" class="w-full h-full border-none hidden" allow="autoplay; padding: 0;"></iframe>
            
            <!-- Loading Indicator -->
            <div id="loader" class="absolute inset-0 bg-white/80 flex items-center justify-center hidden">
                <div class="flex flex-col items-center">
                    <div class="w-12 h-12 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                    <p class="mt-4 font-bold text-blue-600 animate-pulse">PREPARING ARENA...</p>
                </div>
            </div>
        </main>
    </div>

    <script>
        const viewport = document.getElementById('game-viewport');
        const welcomeScreen = document.getElementById('welcome-screen');
        const loader = document.getElementById('loader');

        function loadEvent(filename, element) {
            // UI Updates
            document.querySelectorAll('.event-card').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            
            // Show Loader
            loader.classList.remove('hidden');
            welcomeScreen.classList.add('opacity-0');
            
            // Navigate the iframe to the olympics subfolder
            setTimeout(() => {
                viewport.src = `olympics/${filename}`;
                viewport.classList.remove('hidden');
                welcomeScreen.classList.add('hidden');
                
                viewport.onload = () => {
                    loader.classList.add('hidden');
                };
            }, 600);
        }

        // Update Clock
        setInterval(() => {
            const now = new Date();
            document.getElementById('clock').innerText = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'});
        }, 1000);
    </script>
    <a href="http://web-mage.ca/games/"><img src="WebMage-sm.webp" style="position: fixed; right: 16px; bottom: 16px; height: 50px; z-index: 9999; pointer-events: auto;"></a>
</body>
</html>