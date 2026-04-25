<!-- WINTER -->
<?php
/**
 * Dynamic Country Scanner
 * Scans the 'flags' directory for .webp and .png files
 * Format: Country Name_ABR.ext
 */
$flagsDir = 'flags';
$foundCountries = [];

if (is_dir($flagsDir)) {
    // Search for webp and png files
    $files = glob($flagsDir . '/*.{webp,png}', GLOB_BRACE);
    
    foreach ($files as $file) {
        $filename = pathinfo($file, PATHINFO_FILENAME);
        // Find the last underscore to separate Name from Abbreviation
        $lastUnderscore = strrpos($filename, '_');
        
        if ($lastUnderscore !== false) {
            $name = substr($filename, 0, $lastUnderscore);
            $code = substr($filename, $lastUnderscore + 1);
            
            $foundCountries[] = [
                'name' => str_replace('_', ' ', $name), // Handle underscores in names if used
                'code' => strtoupper($code),
                'file' => basename($file)
            ];
        }
    }
}

// Alphabetical Sort
usort($foundCountries, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Fallback list if directory is empty or scanning fails
if (empty($foundCountries)) {
    $fallbackList = ["Canada", "China", "France", "Germany", "Great Britain", "Italy", "Japan", "Norway", "South Korea", "Sweden", "Switzerland", "United States"];
    foreach ($fallbackList as $name) {
        $foundCountries[] = ['name' => $name, 'code' => substr(strtoupper($name), 0, 3), 'file' => ''];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Olympic Downhill Skiing</title>
    <style>
        :root {
            --olympic-blue: #0081C8;
            --snow-white: #f0f4f8;
            --ice-blue: #e0eafc;
        }

        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--ice-blue);
        }

        #game-container {
            position: relative;
            width: 100vw;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        canvas {
            display: block;
            background: linear-gradient(to bottom, #ffffff, #e6f0ff);
            cursor: none;
            touch-action: none;
        }

        .overlay {
            position: absolute;
            pointer-events: none;
            color: #2c3e50;
            z-index: 10;
        }

        #hud {
            top: 20px;
            left: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(5px);
            padding: 10px 20px;
            border-radius: 12px;
            border-left: 5px solid var(--olympic-blue);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .stat-info { display: flex; flex-direction: column; }
        .stat-label { font-size: 10px; text-transform: uppercase; font-weight: bold; opacity: 0.7; }
        .stat-value { font-size: 20px; font-weight: 800; }

        .flag-wrapper {
            width: 40px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }

        .hud-flag { width: 100%; height: 100%; object-fit: cover; }
        .flag-fallback-text { font-size: 10px; font-weight: 800; color: var(--olympic-blue); text-transform: uppercase; }

        #menu, #game-over {
            position: absolute;
            background: rgba(255, 255, 255, 0.98);
            padding: 25px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            pointer-events: all;
            max-width: 850px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
        }

        h1 { margin-top: 0; color: var(--olympic-blue); font-size: 26px; }
        h2 { font-size: 14px; margin-bottom: 10px; color: #555; text-transform: uppercase; letter-spacing: 1px; }
        
        .country-grid {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 8px;
            margin: 15px 0;
            padding: 5px;
        }

        @media (max-width: 768px) { .country-grid { grid-template-columns: repeat(5, 1fr); } }

        .country-option {
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            border: 2px solid transparent;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            height: 65px;
            box-sizing: border-box;
        }

        .country-option:hover { background: #f0f7ff; border-color: var(--olympic-blue); }
        .country-option.selected { background: #e1efff; border-color: var(--olympic-blue); }

        .grid-flag-wrapper {
            width: 40px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            border-radius: 2px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }

        .grid-flag-wrapper img { width: 100%; height: 100%; object-fit: cover; }

        .country-name {
            font-size: 8px;
            font-weight: 700;
            text-align: center;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }

        .btn {
            background: var(--olympic-blue);
            color: white;
            border: none;
            padding: 12px 35px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 50px;
            cursor: pointer;
            transition: transform 0.2s, background 0.2s;
            margin-top: 10px;
        }

        .btn:hover:not(:disabled) { transform: scale(1.05); background: #006da8; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }

        .hidden { display: none !important; }
        #menu::-webkit-scrollbar { width: 6px; }
        #menu::-webkit-scrollbar-thumb { background: #ddd; border-radius: 10px; }
    </style>
</head>
<body>

    <div id="game-container">
        <canvas id="skiCanvas"></canvas>

        <div id="hud" class="overlay hidden">
            <div class="stat-card">
                <div class="flag-wrapper">
                    <img id="hud-country-flag" class="hud-flag" src="" alt="">
                    <span id="hud-flag-fallback" class="flag-fallback-text hidden"></span>
                </div>
                <div class="stat-info">
                    <div class="stat-label" id="hud-country-name">Country</div>
                    <div class="stat-value">Athlete</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="stat-label">Speed</div>
                    <div class="stat-value"><span id="speed-val">0</span> km/h</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="stat-label">Distance</div>
                    <div class="stat-value"><span id="dist-val">0</span> m</div>
                </div>
            </div>
        </div>

        <div id="menu" class="overlay">
            <h1>Olympic Downhill</h1>
            <p>Tuck your skis and fly down the mountain!</p>
            <h2>Select Your Nation</h2>
            <div id="country-list" class="country-grid"></div>
            <button id="start-btn" class="btn" onclick="startGame()" disabled>Select a Country</button>
        </div>

        <div id="game-over" class="overlay hidden">
            <h1>CRASH!</h1>
            <div class="stat-card" style="margin-bottom: 20px; justify-content: center; border-left: none;">
                <div class="stat-info" style="align-items: center;">
                    <div class="stat-label">Final Distance</div>
                    <div class="stat-value"><span id="final-dist">0</span> m</div>
                </div>
            </div>
            <button class="btn" onclick="startGame()">Try Again</button>
        </div>
    </div>

    <script>
        // Inject PHP data into JavaScript
        const countries = <?php echo json_encode($foundCountries); ?>;
        
        const canvas = document.getElementById('skiCanvas');
        const ctx = canvas.getContext('2d');
        const speedEl = document.getElementById('speed-val');
        const distEl = document.getElementById('dist-val');
        const finalDistEl = document.getElementById('final-dist');
        const menu = document.getElementById('menu');
        const hud = document.getElementById('hud');
        const gameOverScreen = document.getElementById('game-over');
        const countryListContainer = document.getElementById('country-list');
        const startBtn = document.getElementById('start-btn');
        const hudFlag = document.getElementById('hud-country-flag');
        const hudFlagFallback = document.getElementById('hud-flag-fallback');
        const hudCountryName = document.getElementById('hud-country-name');

        let gameActive = false;
        let distance = 0;
        let speed = 0;
        let selectedCountry = null;
        const maxSpeed = 135;
        const baseSpeed = 30;
        
        let player = { x: 0, y: 0, targetX: 0, tilt: 0 };
        let obstacles = [], particles = [], lastTime = 0, obstacleTimer = 0;

        function initCountrySelector() {
            countries.forEach(country => {
                const div = document.createElement('div');
                div.className = 'country-option';
                const flagUrl = country.file ? `flags/${country.file}` : '';
                
                div.innerHTML = `
                    <div class="grid-flag-wrapper">
                        ${flagUrl ? `<img src="${flagUrl}" alt="" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">` : ''}
                        <span class="flag-fallback-text ${flagUrl ? 'hidden' : ''}">${country.code}</span>
                    </div>
                    <div class="country-name">${country.name}</div>
                `;
                
                div.onclick = () => selectCountry(country, div, flagUrl);
                countryListContainer.appendChild(div);
            });
        }

        function selectCountry(country, element, flagUrl) {
            document.querySelectorAll('.country-option').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            selectedCountry = country;
            startBtn.disabled = false;
            startBtn.innerText = `Start as ${country.name}`;
            
            if (flagUrl) {
                hudFlag.src = flagUrl;
                hudFlag.classList.remove('hidden');
                hudFlagFallback.classList.add('hidden');
            } else {
                hudFlag.classList.add('hidden');
                hudFlagFallback.innerText = country.code;
                hudFlagFallback.classList.remove('hidden');
            }
            hudCountryName.innerText = country.name;
        }

        function drawSkier(ctx, x, y, tilt) {
            ctx.save();
            ctx.translate(x, y);
            ctx.rotate(Math.PI); 
            ctx.rotate(-tilt * 0.25);
            ctx.strokeStyle = '#111'; ctx.lineWidth = 3; ctx.lineCap = 'round';
            // Left Ski
            ctx.beginPath(); ctx.moveTo(-10, -28); ctx.quadraticCurveTo(-10, -32, -12, -34); ctx.moveTo(-10, -28); ctx.lineTo(-10, 20); ctx.stroke();
            // Right Ski
            ctx.beginPath(); ctx.moveTo(10, -28); ctx.quadraticCurveTo(10, -32, 12, -34); ctx.moveTo(10, -28); ctx.lineTo(10, 20); ctx.stroke();
            // Poles
            ctx.strokeStyle = '#555'; ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.moveTo(-12, -5); ctx.lineTo(-18, 30); ctx.moveTo(12, -5); ctx.lineTo(18, 30); ctx.stroke();
            // Body
            ctx.fillStyle = '#c0392b'; ctx.beginPath(); ctx.moveTo(-8, 5); ctx.lineTo(8, 5); ctx.lineTo(6, 18); ctx.lineTo(-6, 18); ctx.closePath(); ctx.fill();
            ctx.fillStyle = '#e74c3c'; ctx.beginPath(); ctx.ellipse(0, -5, 12, 16, 0, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(0, -18, 8, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = '#222'; ctx.beginPath(); ctx.roundRect(-6, -20, 12, 5, 2); ctx.fill();
            ctx.restore();
        }

        function drawTree(ctx, x, y, size) {
            ctx.fillStyle = '#1e3d1a'; ctx.beginPath(); ctx.moveTo(x, y - size); ctx.lineTo(x - size/2, y + size/2); ctx.lineTo(x + size/2, y + size/2); ctx.fill();
            ctx.fillStyle = '#3e2723'; ctx.fillRect(x - size/10, y + size/2, size/5, size/4);
        }

        function drawRock(ctx, x, y, size) {
            ctx.fillStyle = '#616161'; ctx.beginPath(); ctx.moveTo(x - size/2, y + size/2); ctx.lineTo(x - size/4, y - size/4); ctx.lineTo(x + size/4, y - size/3); ctx.lineTo(x + size/2, y + size/2); ctx.closePath(); ctx.fill();
        }

        window.addEventListener('resize', resize);
        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            player.x = canvas.width / 2;
            player.targetX = canvas.width / 2;
            player.y = canvas.height * 0.2; 
        }

        window.addEventListener('mousemove', (e) => { if (gameActive) player.targetX = e.clientX; });
        window.addEventListener('touchstart', (e) => { if (gameActive) player.targetX = e.touches[0].clientX; });
        window.addEventListener('touchmove', (e) => { if (gameActive) player.targetX = e.touches[0].clientX; });
        window.addEventListener('keydown', (e) => {
            if (!gameActive) return;
            if (e.key === 'ArrowLeft' || e.key === 'a') player.targetX -= 50;
            if (e.key === 'ArrowRight' || e.key === 'd') player.targetX += 50;
        });

        function startGame() {
            if (!selectedCountry) return;
            resize();
            gameActive = true;
            distance = 0; speed = baseSpeed; obstacles = []; particles = [];
            menu.classList.add('hidden'); gameOverScreen.classList.add('hidden'); hud.classList.remove('hidden');
            lastTime = performance.now();
            requestAnimationFrame(gameLoop);
        }

        function endGame() {
            gameActive = false;
            finalDistEl.innerText = Math.floor(distance);
            gameOverScreen.classList.remove('hidden'); hud.classList.add('hidden');
        }

        function gameLoop(timestamp) {
            if (!gameActive) return;
            const deltaTime = timestamp - lastTime;
            lastTime = timestamp;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            if (speed < maxSpeed) speed += 0.04;
            distance += (speed / 120);
            speedEl.innerText = Math.floor(speed); distEl.innerText = Math.floor(distance);
            const dx = player.targetX - player.x;
            player.x += dx * 0.12; player.tilt = dx * 0.025;
            if (player.x < 25) player.x = 25; if (player.x > canvas.width - 25) player.x = canvas.width - 25;
            obstacleTimer -= deltaTime;
            if (obstacleTimer <= 0) {
                obstacles.push({ x: Math.random() * canvas.width, y: canvas.height + 100, size: 35 + Math.random() * 45, type: Math.random() > 0.4 ? 'tree' : 'rock' });
                obstacleTimer = Math.max(180, 950 - (speed * 4.5)); 
            }
            if (Math.random() > 0.15) particles.push({ x: player.x + (Math.random() - 0.5) * 15, y: player.y + 18, life: 1.0 });
            ctx.fillStyle = 'white';
            for (let i = particles.length - 1; i >= 0; i--) {
                const p = particles[i]; p.y -= speed * 0.22; p.life -= 0.025;
                if (p.life <= 0 || p.y < -100) { particles.splice(i, 1); } 
                else { ctx.globalAlpha = p.life * 0.6; ctx.beginPath(); ctx.arc(p.x, p.y, 4 * p.life, 0, Math.PI * 2); ctx.fill(); }
            }
            ctx.globalAlpha = 1.0;
            for (let i = obstacles.length - 1; i >= 0; i--) {
                const o = obstacles[i]; o.y -= speed * 0.22;
                if (o.type === 'tree') drawTree(ctx, o.x, o.y, o.size); else drawRock(ctx, o.x, o.y, o.size);
                if (Math.hypot(player.x - o.x, player.y - o.y) < (o.size * 0.35 + 8)) { endGame(); return; }
                if (o.y < -100) obstacles.splice(i, 1);
            }
            drawSkier(ctx, player.x, player.y, player.tilt);
            requestAnimationFrame(gameLoop);
        }
        resize();
        initCountrySelector();
    </script>
</body>
</html>