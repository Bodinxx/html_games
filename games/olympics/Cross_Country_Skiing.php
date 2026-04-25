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
    ['src' => 'https://flagcdn.com/w160/fr.png', 'name' => 'France', 'abr' => 'FRA'],
    ['src' => 'https://flagcdn.com/w160/ee.png', 'name' => 'Estonia', 'abr' => 'EST']
];

$finalData = !empty($flagData) ? $flagData : $fallbackData;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nordic Sprint - Cross-Country Skiing</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Bungee&display=swap');
        
        body {
            margin: 0;
            overflow: hidden;
            background: #e2e8f0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            touch-action: manipulation;
        }

        #game-container {
            position: relative;
            width: 100vw;
            height: 100vh;
            background: linear-gradient(to bottom, #87CEEB 0%, #ffffff 100%);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        canvas {
            background: transparent;
            display: block;
            max-width: 100%;
            max-height: 100%;
            cursor: pointer;
        }

        .ui-overlay {
            position: absolute;
            top: 20px;
            left: 20px;
            pointer-events: none;
            color: #1e293b;
            z-index: 10;
        }

        .score-box {
            font-family: 'Bungee', cursive;
            font-size: 2rem;
            text-shadow: 2px 2px #fff;
        }

        .screen-overlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            flex-direction: column;
            justify-content: flex-start; /* Ensure scrolling works from the top */
            align-items: center;
            z-index: 20;
            text-align: center;
            padding: 40px 20px;
            overflow-y: auto; /* Enable vertical scrolling */
        }

        .btn {
            background: #2563eb;
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 20px;
            pointer-events: auto;
            flex-shrink: 0;
        }

        .btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: scale(1) !important;
        }

        .btn:not(:disabled):hover {
            transform: scale(1.05);
            background: #1d4ed8;
        }

        .country-card {
            width: 100px;
            height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
            padding: 8px;
        }

        .country-card:hover {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .country-card.selected {
            border-color: #2563eb;
            background: #dbeafe;
            box-shadow: 0 0 10px rgba(37, 99, 235, 0.3);
        }

        .country-card img, .result-row img {
            max-width: 40px;
            height: auto;
            border: 1px solid #ddd;
            flex-shrink: 0;
        }

        .country-card span {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            line-height: 1.2;
            margin-top: 8px;
            word-wrap: break-word;
            width: 100%;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .results-table {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            overflow: hidden;
            margin-top: 20px;
        }

        .result-row {
            display: grid;
            grid-template-columns: 50px 60px 1fr 100px;
            align-items: center;
            padding: 10px 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .result-row.player {
            background: #eff6ff;
            font-weight: bold;
            border-left: 4px solid #2563eb;
        }
    </style>
</head>
<body>

    <div id="php-data" style="display: none;"><?php echo json_encode($finalData); ?></div>

    <div id="game-container">
        <div class="ui-overlay">
            <div class="score-box">Score: <span id="score">0</span></div>
            <div id="player-country-display" class="text-lg font-bold text-blue-800"></div>
            <div id="current-collect-label" class="text-sm font-bold opacity-70"></div>
        </div>

        <!-- Selection Screen -->
        <div id="start-screen" class="screen-overlay">
            <h1 class="text-4xl font-black text-blue-900 mb-2" style="font-family: 'Bungee';">NORDIC SPRINT</h1>
            <p class="text-slate-600 mb-6">Choose your country to start the race!</p>
            <div id="country-grid" class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-10 gap-3 max-w-6xl mx-auto mb-8"></div>
            <button id="start-btn" class="btn shadow-lg" disabled>SELECT A COUNTRY</button>
            <p class="mt-4 text-xs text-slate-400 mb-10">Use <b>Space</b> or <b>Tap</b> to jump over obstacles.</p>
        </div>

        <!-- Results Screen -->
        <div id="results-screen" class="screen-overlay" style="display: none; justify-content: center;">
            <h1 class="text-4xl font-black text-red-600 mb-2" style="font-family: 'Bungee';">RACE FINISHED!</h1>
            <p id="player-finish-msg" class="text-slate-600 font-bold"></p>
            
            <div id="results-table" class="results-table">
                <!-- Leaderboard rows injected via JS -->
            </div>

            <button id="new-game-btn" class="btn shadow-lg">NEW RACE</button>
        </div>

        <canvas id="gameCanvas"></canvas>
    </div>

    <script>
        const getCountryData = () => {
            const dataEl = document.getElementById('php-data');
            const raw = dataEl ? dataEl.textContent : '';
            if (!raw || raw.trim().startsWith('<?php')) {
                return [
                    {src: 'https://flagcdn.com/w160/no.png', name: 'Norway', abr: 'NOR'},
                    {src: 'https://flagcdn.com/w160/se.png', name: 'Sweden', abr: 'SWE'},
                    {src: 'https://flagcdn.com/w160/fi.png', name: 'Finland', abr: 'FIN'},
                    {src: 'https://flagcdn.com/w160/us.png', name: 'USA', abr: 'USA'},
                    {src: 'https://flagcdn.com/w160/ca.png', name: 'Canada', abr: 'CAN'}
                ];
            }
            return JSON.parse(raw);
        };

        const countryFlags = getCountryData();
        const canvas = document.getElementById('gameCanvas');
        const ctx = canvas.getContext('2d');
        const scoreEl = document.getElementById('score');
        const startScreen = document.getElementById('start-screen');
        const resultsScreen = document.getElementById('results-screen');
        const startBtn = document.getElementById('start-btn');
        const newGameBtn = document.getElementById('new-game-btn');
        const countryGrid = document.getElementById('country-grid');
        const collectLabel = document.getElementById('current-collect-label');
        const playerDisplay = document.getElementById('player-country-display');

        let gameActive = false;
        let score = 0;
        let gameSpeed = 5;
        let animationFrameId;
        let selectedCountry = null;

        const GRAVITY = 0.6;
        const JUMP_FORCE = -12;
        const GROUND_Y_PERCENT = 0.8;

        function initGrid() {
            countryGrid.innerHTML = '';
            countryFlags.forEach(country => {
                const card = document.createElement('div');
                card.className = 'country-card';
                card.innerHTML = `
                    <img src="${country.src}" alt="${country.abr}" onerror="this.src='https://via.placeholder.com/40x30?text=Flag'">
                    <span>${country.name}</span>
                `;
                card.onclick = () => {
                    document.querySelectorAll('.country-card').forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    selectedCountry = country;
                    startBtn.disabled = false;
                    startBtn.innerText = `RACE FOR ${country.name.toUpperCase()}`;
                };
                countryGrid.appendChild(card);
            });
        }

        const player = {
            x: 100, y: 0, width: 50, height: 60, velocityY: 0, isJumping: false,
            draw() {
                ctx.save();
                ctx.translate(this.x + this.width/2, this.y + this.height/2);
                ctx.strokeStyle = '#1e293b'; ctx.lineWidth = 4; ctx.lineCap = 'round';
                ctx.beginPath(); ctx.moveTo(-30, 25); ctx.lineTo(30, 25); ctx.stroke();
                let wobble = Math.sin(Date.now() / 100) * 2;
                ctx.beginPath(); ctx.moveTo(0, 20); ctx.lineTo(0 + wobble, -10); ctx.stroke();
                ctx.fillStyle = '#1e293b'; ctx.beginPath(); ctx.arc(0 + wobble, -20, 8, 0, Math.PI * 2); ctx.fill();
                ctx.strokeStyle = '#64748b'; ctx.beginPath(); ctx.moveTo(wobble, -5); ctx.lineTo(-20, 20); ctx.stroke();
                if (selectedCountry) {
                    ctx.fillStyle = 'white'; ctx.fillRect(wobble - 10, -5, 20, 15);
                    ctx.strokeStyle = '#2563eb'; ctx.lineWidth = 1; ctx.strokeRect(wobble - 10, -5, 20, 15);
                    ctx.fillStyle = '#1e293b'; ctx.font = 'bold 8px Arial'; ctx.textAlign = 'center';
                    ctx.fillText(selectedCountry.abr, wobble, 6);
                }
                ctx.restore();
            },
            update() {
                this.velocityY += GRAVITY;
                this.y += this.velocityY;
                const ground = canvas.height * GROUND_Y_PERCENT - this.height;
                if (this.y > ground) { this.y = ground; this.velocityY = 0; this.isJumping = false; }
            },
            jump() { if (!this.isJumping) { this.velocityY = JUMP_FORCE; this.isJumping = true; } }
        };

        let obstacles = [], items = [];

        function spawnObstacle() {
            if (!gameActive) return;
            obstacles.push({ x: canvas.width, y: canvas.height * GROUND_Y_PERCENT - 30, width: 40, height: 30, color: '#475569' });
            setTimeout(spawnObstacle, 1500 + Math.random() * 2000);
        }

        function spawnItem() {
            if (!gameActive) return;
            const randomCountry = countryFlags[Math.floor(Math.random() * countryFlags.length)];
            const img = new Image(); img.src = randomCountry.src;
            items.push({ x: canvas.width, y: canvas.height * GROUND_Y_PERCENT - 120 - Math.random() * 100, width: 40, height: 30, data: randomCountry, img: img });
            setTimeout(spawnItem, 2000 + Math.random() * 3000);
        }

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            player.y = canvas.height * GROUND_Y_PERCENT - player.height;
        }

        function gameOver() {
            gameActive = false;
            cancelAnimationFrame(animationFrameId);
            showResults();
        }

        function showResults() {
            const resultsTable = document.getElementById('results-table');
            resultsTable.innerHTML = '';
            
            // Generate 7 other countries (simulated)
            let competitors = countryFlags
                .filter(c => c.abr !== selectedCountry.abr)
                .sort(() => 0.5 - Math.random())
                .slice(0, 7);

            // Add player to the mix
            let leaderboard = competitors.map(c => ({
                ...c,
                score: Math.floor(Math.max(0, score + (Math.random() * 100 - 50))),
                isPlayer: false
            }));
            
            leaderboard.push({ ...selectedCountry, score: score, isPlayer: true });
            
            // Sort by score
            leaderboard.sort((a, b) => b.score - a.score);

            document.getElementById('player-finish-msg').innerText = `${selectedCountry.name} finished with ${score} points!`;

            leaderboard.forEach((entry, idx) => {
                const row = document.createElement('div');
                row.className = `result-row ${entry.isPlayer ? 'player' : ''}`;
                row.innerHTML = `
                    <div class="font-black text-slate-400">#${idx + 1}</div>
                    <div><img src="${entry.src}" alt="${entry.abr}" style="max-width: 40px"></div>
                    <div class="truncate px-2">${entry.name}</div>
                    <div class="text-right font-mono">${entry.score}</div>
                `;
                resultsTable.appendChild(row);
            });

            resultsScreen.style.display = 'flex';
        }

        function update() {
            if (!gameActive) return;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#f8fafc'; ctx.fillRect(0, canvas.height * GROUND_Y_PERCENT, canvas.width, canvas.height);
            ctx.strokeStyle = '#cbd5e1'; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(0, canvas.height * GROUND_Y_PERCENT); ctx.lineTo(canvas.width, canvas.height * GROUND_Y_PERCENT); ctx.stroke();

            player.update();
            player.draw();

            for (let i = obstacles.length - 1; i >= 0; i--) {
                let obs = obstacles[i]; obs.x -= gameSpeed;
                ctx.fillStyle = obs.color; ctx.beginPath(); ctx.moveTo(obs.x, obs.y + obs.height); ctx.lineTo(obs.x + obs.width / 2, obs.y); ctx.lineTo(obs.x + obs.width, obs.y + obs.height); ctx.fill();
                if (player.x < obs.x + obs.width && player.x + player.width > obs.x && player.y < obs.y + obs.height && player.y + player.height > obs.y) gameOver();
                if (obs.x + obs.width < 0) obstacles.splice(i, 1);
            }

            for (let i = items.length - 1; i >= 0; i--) {
                let item = items[i]; item.x -= gameSpeed;
                if (item.img.complete) ctx.drawImage(item.img, item.x, item.y, item.width, item.height);
                ctx.strokeStyle = '#334155'; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(item.x, item.y); ctx.lineTo(item.x, item.y + 50); ctx.stroke();
                if (player.x < item.x + item.width && player.x + player.width > item.x && player.y < item.y + item.height && player.y + player.height > item.y) {
                    score += 10; scoreEl.innerText = score; collectLabel.innerText = `+10 from ${item.data.abr}`; items.splice(i, 1); gameSpeed += 0.1;
                }
                if (item.x + item.width < 0) items.splice(i, 1);
            }
            animationFrameId = requestAnimationFrame(update);
        }

        window.addEventListener('keydown', (e) => { if (e.code === 'Space') player.jump(); });
        canvas.addEventListener('mousedown', () => player.jump());
        canvas.addEventListener('touchstart', (e) => { e.preventDefault(); player.jump(); });

        startBtn.addEventListener('click', () => {
            startScreen.style.display = 'none';
            score = 0; scoreEl.innerText = '0';
            gameSpeed = 6; obstacles = []; items = [];
            playerDisplay.innerText = `${selectedCountry.name} (${selectedCountry.abr})`;
            gameActive = true;
            spawnObstacle(); spawnItem(); update();
        });

        newGameBtn.addEventListener('click', () => {
            resultsScreen.style.display = 'none';
            startScreen.style.display = 'flex';
            initGrid(); // Refresh grid
            startBtn.disabled = true;
            startBtn.innerText = "SELECT A COUNTRY";
            selectedCountry = null;
        });

        window.addEventListener('resize', resize);
        resize();
        initGrid();
    </script>
</body>
</html>