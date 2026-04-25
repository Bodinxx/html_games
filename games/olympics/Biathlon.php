<!-- WINTER -->
<?php
/**
 * PHP Logic: Scanning the 'flags' folder
 * Filename format: COUNTRY_NAME_ABR.ext
 */
$flagsFolder = 'flags';
$flagData = [];

if (is_dir($flagsFolder)) {
    $files = scandir($flagsFolder);
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'png' || $ext === 'webp') {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $abr = substr($filename, -3);
            $name = str_replace('_', ' ', substr($filename, 0, -3));
            
            $flagData[] = [
                'src' => $flagsFolder . '/' . $file,
                'name' => trim($name),
                'abr' => strtoupper($abr)
            ];
        }
    }
}

// Fallback data for preview environment
$fallbackData = [
    ['src' => 'https://flagcdn.com/w160/no.png', 'name' => 'Norway', 'abr' => 'NOR'],
    ['src' => 'https://flagcdn.com/w160/se.png', 'name' => 'Sweden', 'abr' => 'SWE'],
    ['src' => 'https://flagcdn.com/w160/fi.png', 'name' => 'Finland', 'abr' => 'FIN'],
    ['src' => 'https://flagcdn.com/w160/us.png', 'name' => 'USA', 'abr' => 'USA'],
    ['src' => 'https://flagcdn.com/w160/ca.png', 'name' => 'Canada', 'abr' => 'CAN'],
    ['src' => 'https://flagcdn.com/w160/de.png', 'name' => 'Germany', 'abr' => 'GER'],
    ['src' => 'https://flagcdn.com/w160/at.png', 'name' => 'Austria', 'abr' => 'AUT'],
    ['src' => 'https://flagcdn.com/w160/ch.png', 'name' => 'Switzerland', 'abr' => 'SUI'],
    ['src' => 'https://flagcdn.com/w160/it.png', 'name' => 'Italy', 'abr' => 'ITA'],
    ['src' => 'https://flagcdn.com/w160/fr.png', 'name' => 'France', 'abr' => 'FRA']
];

$finalData = !empty($flagData) ? $flagData : $fallbackData;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Biathlon Pursuit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Bungee&family=Inter:wght@400;700&display=swap');
        
        body {
            margin: 0;
            overflow: hidden;
            background: #0f172a;
            font-family: 'Inter', sans-serif;
            touch-action: none;
        }

        #game-container {
            position: relative;
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: #e2e8f0;
        }

        canvas {
            display: block;
            width: 100%;
            height: 100%;
            background: #ffffff;
        }

        .bungee { font-family: 'Bungee', cursive; }

        .screen-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.9);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 50;
            padding: 20px;
            text-align: center;
        }

        .country-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
            width: 100%;
            max-width: 600px;
            max-height: 40vh;
            overflow-y: auto;
            padding: 10px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
        }

        .country-card {
            background: rgba(255,255,255,0.05);
            border: 2px solid transparent;
            border-radius: 8px;
            padding: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .country-card:hover { background: rgba(255,255,255,0.15); }
        .country-card.selected { border-color: #3b82f6; background: rgba(59, 130, 246, 0.2); }

        .btn-primary {
            background: #3b82f6;
            color: white;
            padding: 12px 32px;
            border-radius: 9999px;
            font-weight: bold;
            font-size: 1.25rem;
            margin-top: 20px;
            transition: transform 0.1s;
        }
        .btn-primary:active { transform: scale(0.95); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

        #hud {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            pointer-events: none;
            display: flex;
            justify-content: space-between;
            z-index: 10;
        }

        .stat-badge {
            background: rgba(0,0,0,0.7);
            padding: 8px 16px;
            border-radius: 8px;
            color: white;
            border-left: 4px solid #3b82f6;
            min-width: 120px;
        }

        .shooting-indicator {
            position: absolute;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            background: rgba(0,0,0,0.5);
            padding: 15px;
            border-radius: 20px;
        }

        .target-dot {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid white;
            background: #334155;
            transition: all 0.2s;
        }
        .target-dot.hit { background: #22c55e; border-color: #22c55e; box-shadow: 0 0 10px #22c55e; }
        .target-dot.miss { background: #ef4444; border-color: #ef4444; box-shadow: 0 0 10px #ef4444; }

        #lap-indicator {
            position: absolute;
            top: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: #3b82f6;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.8rem;
            z-index: 5;
        }
    </style>
</head>
<body>

    <div id="php-data" style="display: none;"><?php echo json_encode($finalData); ?></div>

    <div id="game-container">
        <!-- HUD -->
        <div id="hud" style="display: none;">
            <div class="stat-badge">
                <div class="text-xs uppercase opacity-70">Total Time</div>
                <div id="timer-val" class="text-xl font-bold bungee">0.00s</div>
            </div>
            <div class="flex flex-col items-center">
                <div id="phase-label" class="text-white bungee text-xl tracking-widest bg-black/50 px-4 py-1 rounded">SKIING</div>
                <div id="lap-val" class="text-blue-400 font-bold text-sm mt-1">LAP 1 / 3</div>
            </div>
            <div class="stat-badge text-right">
                <div class="text-xs uppercase opacity-70">Penalty</div>
                <div id="penalty-val" class="text-xl font-bold bungee text-red-400">+0s</div>
            </div>
        </div>

        <!-- Start Screen -->
        <div id="start-screen" class="screen-overlay">
            <h1 class="text-5xl bungee mb-2 text-blue-400">BIATHLON PURSUIT</h1>
            <p class="mb-6 opacity-80">3 Laps • 2 Shooting Rounds • 5 Targets Each</p>
            
            <div id="country-grid" class="country-grid mb-4"></div>
            
            <button id="start-btn" class="btn-primary shadow-lg" disabled>CHOOSE COUNTRY</button>
            
            <div class="mt-8 grid grid-cols-2 gap-8 text-sm opacity-70 max-w-lg">
                <div>
                    <div class="font-bold text-white uppercase mb-1">The Course</div>
                    <p>Stay in the blue tracks for speed. You'll enter the range after Lap 1 and Lap 2.</p>
                </div>
                <div>
                    <div class="font-bold text-white uppercase mb-1">The Range</div>
                    <p>Aim with your mouse/touch and click to shoot. The rifle drifts, so keep it steady!</p>
                </div>
            </div>
        </div>

        <!-- Shooting UI -->
        <div id="shooting-ui" class="shooting-indicator" style="display: none;">
            <div class="target-dot" id="td-0"></div>
            <div class="target-dot" id="td-1"></div>
            <div class="target-dot" id="td-2"></div>
            <div class="target-dot" id="td-3"></div>
            <div class="target-dot" id="td-4"></div>
        </div>

        <!-- Results Screen -->
        <div id="results-screen" class="screen-overlay" style="display: none;">
            <h1 class="text-4xl bungee mb-2">FINAL STANDINGS</h1>
            <div id="results-list" class="w-full max-w-md bg-white/10 rounded-xl overflow-hidden mb-6"></div>
            <button id="restart-btn" class="btn-primary">NEW COMPETITION</button>
        </div>

        <canvas id="gameCanvas"></canvas>
    </div>

    <script>
        const getCountryData = () => {
            const dataEl = document.getElementById('php-data');
            try { return JSON.parse(dataEl.textContent); } 
            catch(e) { return <?php echo json_encode($fallbackData); ?>; }
        };

        const flags = getCountryData();
        const canvas = document.getElementById('gameCanvas');
        const ctx = canvas.getContext('2d');
        const startScreen = document.getElementById('start-screen');
        const resultsScreen = document.getElementById('results-screen');
        const hud = document.getElementById('hud');
        const shootingUi = document.getElementById('shooting-ui');
        
        // Game State
        let gameState = 'START'; // START, SKIING, SHOOTING, FINISHED
        let selectedCountry = null;
        let raceTime = 0;
        let penaltyTime = 0;
        let startTime = 0;
        let mouseX = 0;
        let mouseY = 0;
        
        // Aiming Reticle (Smoothing & Sway)
        let reticleX = 0;
        let reticleY = 0;
        const AIM_SMOOTHING = 0.06; // Lower = Slower/Heavier

        // Progress tracking
        let currentLap = 1;
        const TOTAL_LAPS = 3;
        let distance = 0;
        const LAP_DISTANCE = 2500; // Distance per lap
        
        // Skiing Variables
        let playerX = 0;
        let speed = 0;
        let tracks = [];

        // Shooting Variables
        let targets = [];
        let currentTargetIndex = 0;
        let currentShootRound = 1;

        function init() {
            const grid = document.getElementById('country-grid');
            flags.forEach(f => {
                const card = document.createElement('div');
                card.className = 'country-card flex flex-col items-center gap-2';
                card.innerHTML = `<img src="${f.src}" class="w-12 h-8 object-cover border border-white/20"> <span class="text-[10px] font-bold truncate w-full text-center">${f.name}</span>`;
                card.onclick = () => {
                    document.querySelectorAll('.country-card').forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    selectedCountry = f;
                    document.getElementById('start-btn').disabled = false;
                    document.getElementById('start-btn').innerText = `RACE AS ${f.abr}`;
                };
                grid.appendChild(card);
            });

            window.addEventListener('resize', resize);
            resize();
            loop();
        }

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            playerX = canvas.width / 2;
            reticleX = canvas.width / 2;
            reticleY = canvas.height / 2;
        }

        function startRace() {
            gameState = 'SKIING';
            startScreen.style.display = 'none';
            hud.style.display = 'flex';
            startTime = Date.now();
            distance = 0;
            currentLap = 1;
            currentShootRound = 1;
            penaltyTime = 0;
            updateHud();
            
            tracks = [];
            for(let i=0; i<20; i++) spawnTrack(i * 100);
        }

        function updateHud() {
            document.getElementById('lap-val').innerText = `LAP ${currentLap} / ${TOTAL_LAPS}`;
            document.getElementById('phase-label').innerText = gameState;
            document.getElementById('penalty-val').innerText = `+${penaltyTime}s`;
        }

        function spawnTrack(yOffset = 0) {
            tracks.push({
                y: yOffset - 100,
                x: canvas.width / 2 + (Math.sin(distance / 500) * (canvas.width * 0.2)),
                width: 120
            });
        }

        function updateSkiing() {
            const now = Date.now();
            raceTime = (now - startTime) / 1000;
            
            // Movement (Standard lerp for skiing)
            const lerp = 0.1;
            playerX += (mouseX - playerX) * lerp;

            // Track collision
            let onTrack = false;
            tracks.forEach(t => {
                t.y += speed;
                if (t.y > canvas.height - 150 && t.y < canvas.height - 50) {
                    if (playerX > t.x - t.width/2 && playerX < t.x + t.width/2) onTrack = true;
                }
            });

            // Reduced speeds to slow down the portion
            speed = onTrack ? 10 : 4;
            distance += speed;

            tracks = tracks.filter(t => t.y < canvas.height + 200);
            if (tracks.length < 25) {
                const lastY = tracks.length > 0 ? tracks[tracks.length-1].y : 0;
                spawnTrack(lastY - 80);
            }

            // Lap check
            if (distance >= LAP_DISTANCE) {
                distance = 0;
                if (currentLap < TOTAL_LAPS) {
                    gameState = 'SHOOTING';
                    initShooting();
                } else {
                    finishRace();
                }
            }
        }

        function initShooting() {
            shootingUi.style.display = 'flex';
            // Reset reticle to center
            reticleX = mouseX;
            reticleY = mouseY;

            for(let i=0; i<5; i++) {
                const dot = document.getElementById(`td-${i}`);
                dot.className = 'target-dot';
            }
            
            updateHud();
            targets = [];
            for(let i=0; i<5; i++) {
                targets.push({
                    id: i,
                    x: (canvas.width / 6) * (i + 1),
                    y: canvas.height * 0.4,
                    r: 25,
                    active: i === 0,
                    status: 'pending'
                });
            }
            currentTargetIndex = 0;
        }

        function drawSkiing() {
            ctx.fillStyle = '#f1f5f9';
            ctx.fillRect(0,0, canvas.width, canvas.height);

            // Draw Tracks
            ctx.fillStyle = '#d1d5db';
            tracks.forEach(t => {
                ctx.beginPath();
                ctx.roundRect(t.x - t.width/2, t.y, t.width, 60, 10);
                ctx.fill();
                ctx.strokeStyle = '#3b82f6';
                ctx.lineWidth = 4;
                ctx.beginPath();
                ctx.moveTo(t.x - 30, t.y); ctx.lineTo(t.x - 30, t.y + 60);
                ctx.moveTo(t.x + 30, t.y); ctx.lineTo(t.x + 30, t.y + 60);
                ctx.stroke();
            });

            // Skier
            ctx.save();
            ctx.translate(playerX, canvas.height - 100);
            ctx.fillStyle = 'rgba(0,0,0,0.1)';
            ctx.beginPath(); ctx.ellipse(0, 5, 20, 10, 0, 0, Math.PI*2); ctx.fill();
            ctx.strokeStyle = '#1e293b';
            ctx.lineWidth = 3;
            ctx.beginPath(); ctx.moveTo(-15, -20); ctx.lineTo(-15, 20); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(15, -20); ctx.lineTo(15, 20); ctx.stroke();
            ctx.fillStyle = '#3b82f6';
            ctx.beginPath(); ctx.roundRect(-10, -10, 20, 20, 5); ctx.fill();
            ctx.fillStyle = '#1e293b';
            ctx.beginPath(); ctx.arc(0, -15, 6, 0, Math.PI*2); ctx.fill();
            ctx.restore();

            // Lap progress bar
            ctx.fillStyle = 'rgba(0,0,0,0.1)';
            ctx.fillRect(0, canvas.height - 5, canvas.width, 5);
            ctx.fillStyle = '#3b82f6';
            ctx.fillRect(0, canvas.height - 5, (distance / LAP_DISTANCE) * canvas.width, 5);
        }

        function updateReticle() {
            // Sway calculation based on time to simulate instability
            const time = Date.now() / 1000;
            const swayX = Math.sin(time * 2.1) * 35 + Math.cos(time * 1.4) * 15;
            const swayY = Math.cos(time * 2.4) * 25 + Math.sin(time * 1.1) * 12;

            // Reticle trails behind mouse + sway offset
            reticleX += (mouseX + swayX - reticleX) * AIM_SMOOTHING;
            reticleY += (mouseY + swayY - reticleY) * AIM_SMOOTHING;
        }

        function drawShooting() {
            ctx.fillStyle = '#cbd5e1';
            ctx.fillRect(0,0, canvas.width, canvas.height);
            ctx.fillStyle = '#94a3b8';
            ctx.fillRect(0, canvas.height * 0.3, canvas.width, canvas.height * 0.2);

            targets.forEach((t) => {
                ctx.fillStyle = '#334155';
                ctx.beginPath(); ctx.arc(t.x, t.y, t.r + 10, 0, Math.PI*2); ctx.fill();

                if (t.status === 'hit') ctx.fillStyle = '#ffffff';
                else if (t.status === 'miss') ctx.fillStyle = '#ef4444';
                else ctx.fillStyle = '#000000';
                
                ctx.beginPath(); ctx.arc(t.x, t.y, t.r, 0, Math.PI*2); ctx.fill();

                if (t.active) {
                    ctx.strokeStyle = '#fbbf24';
                    ctx.lineWidth = 4;
                    ctx.setLineDash([5, 5]);
                    ctx.beginPath(); ctx.arc(t.x, t.y, t.r + 15, 0, Math.PI*2); ctx.stroke();
                    ctx.setLineDash([]);
                }
            });

            // Heavy Crosshair (centered on smoothed/swaying reticle)
            ctx.strokeStyle = '#ef4444';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(reticleX - 25, reticleY); ctx.lineTo(reticleX + 25, reticleY);
            ctx.moveTo(reticleX, reticleY - 25); ctx.lineTo(reticleX, reticleY + 25);
            ctx.stroke();
            
            ctx.lineWidth = 1;
            ctx.beginPath(); ctx.arc(reticleX, reticleY, 15, 0, Math.PI * 2); ctx.stroke();
            ctx.beginPath(); ctx.arc(reticleX, reticleY, 2, 0, Math.PI * 2); ctx.fill();
        }

        function handleShot() {
            if (gameState !== 'SHOOTING') return;
            const t = targets[currentTargetIndex];
            
            // Dist calculated from swaying reticle position
            const dist = Math.sqrt(Math.pow(reticleX - t.x, 2) + Math.pow(reticleY - t.y, 2));
            
            const dot = document.getElementById(`td-${currentTargetIndex}`);
            if (dist < t.r + 5) {
                t.status = 'hit';
                dot.classList.add('hit');
            } else {
                t.status = 'miss';
                penaltyTime += 10;
                dot.classList.add('miss');
                document.getElementById('penalty-val').innerText = `+${penaltyTime}s`;
            }

            t.active = false;
            currentTargetIndex++;
            if (currentTargetIndex < targets.length) {
                targets[currentTargetIndex].active = true;
            } else {
                setTimeout(() => {
                    shootingUi.style.display = 'none';
                    currentLap++;
                    currentShootRound++;
                    gameState = 'SKIING';
                    updateHud();
                }, 500);
            }
        }

        function finishRace() {
            gameState = 'FINISHED';
            shootingUi.style.display = 'none';
            resultsScreen.style.display = 'flex';
            
            const total = raceTime + penaltyTime;
            const list = document.getElementById('results-list');
            list.innerHTML = '';

            let leaderboard = flags.map(f => {
                const isPlayer = f.abr === selectedCountry.abr;
                const time = isPlayer ? total : (110 + Math.random() * 60 + (Math.floor(Math.random() * 5) * 10));
                return { ...f, time, isPlayer };
            }).sort((a,b) => a.time - b.time);

            leaderboard.forEach((entry, idx) => {
                const row = document.createElement('div');
                row.className = `flex items-center justify-between p-3 border-b border-white/10 ${entry.isPlayer ? 'bg-blue-600/50' : ''}`;
                row.innerHTML = `
                    <div class="flex items-center gap-3">
                        <span class="font-bold w-6 text-sm opacity-50">#${idx+1}</span>
                        <img src="${entry.src}" class="w-8 h-5 object-cover">
                        <span class="font-bold text-sm">${entry.name}</span>
                    </div>
                    <span class="font-mono text-sm">${entry.time.toFixed(2)}s</span>
                `;
                list.appendChild(row);
            });
        }

        function loop() {
            if (gameState === 'SKIING') {
                updateSkiing();
                drawSkiing();
                document.getElementById('timer-val').innerText = raceTime.toFixed(2) + 's';
            } else if (gameState === 'SHOOTING') {
                updateReticle();
                drawShooting();
                const now = Date.now();
                raceTime = (now - startTime) / 1000;
                document.getElementById('timer-val').innerText = raceTime.toFixed(2) + 's';
            }
            requestAnimationFrame(loop);
        }

        const updateInput = (e) => {
            const x = e.touches ? e.touches[0].clientX : e.clientX;
            const y = e.touches ? e.touches[0].clientY : e.clientY;
            mouseX = x;
            mouseY = y;
        };
        window.addEventListener('mousemove', updateInput);
        window.addEventListener('touchstart', updateInput);
        window.addEventListener('touchmove', updateInput);
        window.addEventListener('mousedown', handleShot);

        document.getElementById('start-btn').onclick = startRace;
        document.getElementById('restart-btn').onclick = () => location.reload();

        init();
    </script>
</body>
</html>