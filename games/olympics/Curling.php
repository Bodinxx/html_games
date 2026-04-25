<!-- WINTER -->
<?php
/**
 * Olympic Curling - World Championship
 * This file scans the 'flags' directory for any .webp or .png files 
 * named in the format 'Country_Name_CODE.ext'.
 */

$flagsDir = 'flags/';
$nations = [
    ["name" => "Australia", "code" => "AUS"],
    ["name" => "Canada", "code" => "CAN"],
    ["name" => "China", "code" => "CHN"],
    ["name" => "France", "code" => "FRA"],
    ["name" => "Great Britain", "code" => "GBR"],
    ["name" => "Italy", "code" => "ITA"],
    ["name" => "Japan", "code" => "JPN"],
    ["name" => "Norway", "code" => "NOR"],
    ["name" => "South Korea", "code" => "KOR"],
    ["name" => "Sweden", "code" => "SWE"],
    ["name" => "Switzerland", "code" => "SUI"],
    ["name" => "United States", "code" => "USA"]
];

if (is_dir($flagsDir)) {
    $files = scandir($flagsDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        // Extract info from "Country_Name_CODE.ext"
        $pathInfo = pathinfo($file);
        if (in_array(strtolower($pathInfo['extension']), ['webp', 'png'])) {
            $filename = $pathInfo['filename'];
            $parts = explode('_', $filename);
            
            if (count($parts) >= 2) {
                $code = array_pop($parts);
                $name = str_replace('_', ' ', implode(' ', $parts));
                
                // Add if not already in defaults
                $exists = false;
                foreach ($nations as $n) {
                    if ($n['code'] === $code) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $nations[] = ["name" => $name, "code" => $code];
                }
            }
        }
    }
}

// Final alphabetical sort
usort($nations, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

$nationsJson = json_encode($nations);
?>
<!-- WINTER -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Olympic Curling: World Championship</title>
    <style>
        :root {
            --ice: #f0f8ff;
            --blue: #0d47a1;
            --red: #b71c1c;
            --gold: #ffd700;
            --wood: #5d4037;
            --glass: rgba(255, 255, 255, 0.8);
        }

        body {
            margin: 0; padding: 0; background-color: #1a1a1a; color: white;
            font-family: 'Segoe UI', sans-serif; display: flex;
            justify-content: center; align-items: center; height: 100vh;
            overflow: hidden; touch-action: none;
        }

        #game-wrapper { display: flex; gap: 20px; align-items: flex-start; }

        /* Selection Screen */
        #selection-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.95); z-index: 100;
            display: flex; flex-direction: column; justify-content: center;
            align-items: center; text-align: center;
        }

        .difficulty-container {
            margin-top: 15px; background: #222; padding: 10px 25px;
            border-radius: 30px; border: 1px solid #444; display: flex;
            align-items: center; gap: 15px;
        }

        #difficulty-slider { cursor: pointer; accent-color: var(--gold); width: 150px; }
        #difficulty-label { min-width: 80px; font-weight: bold; color: var(--gold); text-transform: uppercase; font-size: 14px; }

        .selection-grid {
            display: grid; grid-template-columns: repeat(10, 1fr);
            gap: 10px; margin-top: 20px; width: 95vw; max-width: 1100px;
            max-height: 65vh; overflow-y: auto; padding: 20px;
            scrollbar-width: thin; scrollbar-color: var(--gold) #333;
        }

        .nation-card {
            background: #222; padding: 8px; border-radius: 6px; cursor: pointer;
            transition: all 0.2s; border: 2px solid #444; display: flex;
            flex-direction: column; align-items: center; justify-content: center;
            width: 90px; height: 110px; box-sizing: border-box; text-align: center;
        }

        .nation-card:hover { transform: translateY(-3px); background: #333; border-color: var(--gold); }

        .nation-card .flag-container {
            width: 40px; height: 28px; display: flex; align-items: center;
            justify-content: center; background: #444; margin-bottom: 6px;
            overflow: hidden; border-radius: 2px; flex-shrink: 0;
        }

        .nation-name-label {
            font-size: 10px; line-height: 1.1; font-weight: 600; word-wrap: break-word;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden; width: 100%;
        }

        .flag-img { width: 100%; height: 100%; object-fit: cover; }

        /* Scoreboard Styling */
        #scoreboard-container { display: flex; flex-direction: column; gap: 10px; height: 700px; }

        #scoreboard {
            background: #eee; color: #333; border: 4px solid var(--wood);
            padding: 10px; display: flex; flex-direction: column;
            width: 380px; box-shadow: 10px 10px 0 rgba(0,0,0,0.5);
        }

        .board-row { display: grid; grid-template-columns: 80px 50px repeat(10, 1fr); border: 1px solid #999; }
        .board-header { background: #333; color: white; padding: 5px; font-weight: bold; font-size: 9px; text-align: center; display: flex; align-items: center; justify-content: center; }
        .board-cell { height: 48px; border-right: 1px solid #ccc; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px; position: relative; }

        .team-red { background: var(--red); color: white; }
        .team-blue { background: var(--blue); color: white; }
        .team-yellow { background: var(--gold); color: black; }

        .flag-icon-small { width: 34px; height: 24px; border-radius: 2px; overflow: hidden; background: #444; }

        /* Inventory */
        #stone-inventory { background: rgba(0,0,0,0.7); padding: 15px; border-radius: 8px; border: 1px solid #444; display: flex; flex-direction: column; gap: 10px; }
        .inventory-row { display: flex; align-items: center; justify-content: space-between; font-weight: bold; }
        .stone-dots { display: flex; gap: 4px; }
        .stone-dot { width: 12px; height: 12px; border-radius: 50%; background: #555; border: 1px solid rgba(255,255,255,0.2); }
        .stone-dot.active-red { background: var(--red); box-shadow: 0 0 5px var(--red); }
        .stone-dot.active-yellow { background: var(--gold); box-shadow: 0 0 5px var(--gold); }

        .back-link-container { margin-top: auto; padding-bottom: 10px; }
        .back-link { display: block; text-align: center; color: #bbb; text-decoration: none; font-size: 14px; padding: 10px; border: 1px solid #444; border-radius: 5px; transition: all 0.2s; }
        .back-link:hover { color: white; background: #333; border-color: var(--gold); }

        #game-container { position: relative; box-shadow: 0 0 50px rgba(0,0,0,0.8); border: 8px solid #333; background: var(--ice); display: flex; }

        canvas { display: block; cursor: crosshair; }
        #minimap-canvas { background: #d1e9ff; border-left: 2px solid #999; cursor: pointer; }

        #ui-overlay { position: absolute; top: 10px; left: 10px; pointer-events: none; width: calc(360px - 20px); }
        .turn-indicator { background: rgba(0,0,0,0.8); padding: 10px; border-radius: 5px; text-align: center; font-weight: bold; border-bottom: 3px solid var(--gold); }

        #message-area { position: absolute; bottom: 60px; width: 360px; text-align: center; font-size: 1.2rem; font-weight: bold; color: var(--blue); text-transform: uppercase; pointer-events: none; }

        .power-container { position: absolute; bottom: 20px; left: 180px; transform: translateX(-50%); width: 200px; height: 24px; background: rgba(0,0,0,0.4); border-radius: 12px; overflow: hidden; display: none; border: 2px solid white; }
        #power-bar { height: 100%; background: linear-gradient(to right, #4caf50, #ffeb3b, #f44336); width: 0%; }
        #sweet-spot { position: absolute; height: 100%; background: rgba(255, 255, 255, 0.4); width: 12%; left: 71%; border-left: 2px solid white; border-right: 2px solid white; box-sizing: border-box; z-index: 2; }

        #next-btn { position: absolute; top: 55%; left: 180px; transform: translate(-50%, -50%); padding: 15px 30px; font-size: 18px; font-weight: bold; color: white; background: var(--blue); border: 2px solid white; border-radius: 10px; cursor: pointer; z-index: 10; display: none; box-shadow: 0 5px 15px rgba(0,0,0,0.5); }

        .hidden { display: none !important; }
    </style>
</head>
<body>

    <div id="selection-overlay">
        <h1 style="color: var(--gold); margin-bottom: 0;">OLYMPIC CURLING</h1>
        <p style="margin-bottom: 5px;">Select Your Nation and Difficulty (10 End Match)</p>
        
        <div class="difficulty-container">
            <label style="font-size: 12px; font-weight: bold; opacity: 0.8;">DIFFICULTY:</label>
            <input type="range" id="difficulty-slider" min="0" max="3" value="2" step="1">
            <span id="difficulty-label">Normal</span>
        </div>

        <div class="selection-grid" id="selection-grid"></div>
    </div>

    <div id="game-wrapper" class="hidden">
        <div id="scoreboard-container">
            <div id="scoreboard">
                <div style="text-align:center; font-weight:bold; margin-bottom:5px;" id="sheet-title">SHEET B - END 1</div>
                
                <div class="board-row" id="red-score-row">
                    <div class="board-cell team-red" id="player-flag-cell"></div>
                    <div class="board-cell" id="red-blank-cell"></div>
                    <div class="board-cell"></div><div class="board-cell"></div><div class="board-cell"></div><div class="board-cell"></div><div class="board-cell"></div>
                    <div class="board-cell"></div><div class="board-cell"></div><div class="board-cell"></div><div class="board-cell"></div><div class="board-cell"></div>
                </div>

                <div class="board-row">
                    <div class="board-header">POINTS</div>
                    <div class="board-header" style="background: var(--blue)">BLANK</div>
                    <div class="board-cell team-blue">1</div>
                    <div class="board-cell team-blue">2</div>
                    <div class="board-cell team-blue">3</div>
                    <div class="board-cell team-blue">4</div>
                    <div class="board-cell team-blue">5</div>
                    <div class="board-cell team-blue">6</div>
                    <div class="board-cell team-blue">7</div>
                    <div class="board-cell team-blue">8</div>
                    <div class="board-cell team-blue">9</div>
                    <div class="board-cell team-blue">10</div>
                </div>

                <div class="board-row" id="yellow-score-row">
                    <div class="board-cell team-yellow" id="bot-flag-cell"></div>
                    <div class="board-cell" id="yellow-blank-cell"></div>
                    <div class="board-cell"></div><div class="board-cell"></div><div class="board-cell"></div><div class="board-cell"></div><div class="board-cell"></div>
                    <div class="board-cell"></div><div class="board-cell"></div><div class="board-cell"></div><div class="board-cell"></div><div class="board-cell"></div>
                </div>
            </div>

            <div id="stone-inventory">
                <div class="inventory-row">
                    <span id="player-inv-name">YOU</span>
                    <div class="stone-dots" id="player-stones-dots"></div>
                </div>
                <div class="inventory-row">
                    <span id="bot-inv-name">BOT</span>
                    <div class="stone-dots" id="bot-stones-dots"></div>
                </div>
            </div>

            <div class="back-link-container">
                <a href="../olympics.php" class="back-link">Back to Event Selection</a>
            </div>
        </div>

        <div id="game-container">
            <canvas id="gameCanvas"></canvas>
            <canvas id="minimap-canvas"></canvas>
            <div id="ui-overlay"><div class="turn-indicator" id="turn-info">---</div></div>
            <div id="message-area">Your Turn</div>
            <button id="next-btn">NEXT END</button>
            <div class="power-container" id="power-ui"><div id="sweet-spot"></div><div id="power-bar"></div></div>
        </div>
    </div>

    <script>
        const canvas = document.getElementById('gameCanvas');
        const ctx = canvas.getContext('2d');
        const miniCanvas = document.getElementById('minimap-canvas');
        const miniCtx = miniCanvas.getContext('2d');
        const turnInfo = document.getElementById('turn-info');
        const messageEl = document.getElementById('message-area');
        const powerUI = document.getElementById('power-ui');
        const powerBar = document.getElementById('power-bar');
        const selectionOverlay = document.getElementById('selection-overlay');
        const selectionGrid = document.getElementById('selection-grid');
        const gameWrapper = document.getElementById('game-wrapper');
        const nextBtn = document.getElementById('next-btn');
        const sheetTitle = document.getElementById('sheet-title');
        const difficultySlider = document.getElementById('difficulty-slider');
        const difficultyLabel = document.getElementById('difficulty-label');

        // Nations provided by PHP scan
        const NATIONS = <?php echo $nationsJson; ?>;

        const WIDTH = 360, VIEW_HEIGHT = 700, RINK_HEIGHT = 4200, MINI_WIDTH = 100;
        canvas.width = WIDTH; canvas.height = VIEW_HEIGHT;
        miniCanvas.width = MINI_WIDTH; miniCanvas.height = VIEW_HEIGHT;

        const HOUSE_Y = 300, START_Y = RINK_HEIGHT - 350, BACKBOARD_Y = HOUSE_Y - 300, STONE_RADIUS = 13;
        const FRICTION_NORMAL = 0.9935, FRICTION_SWEEPING = 0.9982, POWER_MULTIPLIER = 30.0;

        const DIFF_SPEEDS = [1.0, 1.6, 2.2, 3.2];
        const DIFF_NAMES = ["Beginner", "Easy", "Normal", "Hard"];
        let currentPowerSpeed = 2.2;

        let playerNation, botNation, gameState = 'START', currentEnd = 1;
        let totalRedScore = 0, totalYellowScore = 0, stones = [];
        let playerStonesThrown = 0, botStonesThrown = 0, activeStone = null;
        let cameraY = START_Y - VIEW_HEIGHT + 150, targetCameraY = cameraY;
        let power = 0, powerDir = 1, isSweeping = false, mouseDown = false, currentAimAngle = -Math.PI / 2;

        difficultySlider.oninput = () => {
            difficultyLabel.innerText = DIFF_NAMES[difficultySlider.value];
            currentPowerSpeed = DIFF_SPEEDS[difficultySlider.value];
        };

        class Stone {
            constructor(x, y, team) {
                this.x = x; this.y = y; this.vx = 0; this.vy = 0;
                this.team = team; this.stopped = false; this.outOfPlay = false;
            }
            update() {
                if (this.stopped || this.outOfPlay) return;
                const friction = (gameState === 'MOVING' && isSweeping && this === activeStone) ? FRICTION_SWEEPING : FRICTION_NORMAL;
                this.vx *= friction; this.vy *= friction;
                this.x += this.vx; this.y += this.vy;
                if (this.x < STONE_RADIUS || this.x > WIDTH - STONE_RADIUS) this.outOfPlay = true;
                if (this.y < BACKBOARD_Y + STONE_RADIUS) this.outOfPlay = true;
                if (Math.abs(this.vx) < 0.05 && Math.abs(this.vy) < 0.05) { this.vx = 0; this.vy = 0; this.stopped = true; }
            }
            draw(context, offsetX, offsetY, scaleX, scaleY, radius) {
                if (this.outOfPlay) return;
                const drawX = this.x * scaleX + offsetX;
                const drawY = this.y * scaleY + offsetY;
                context.fillStyle = 'rgba(0,0,0,0.2)';
                context.beginPath(); context.arc(drawX, drawY + 2, radius, 0, Math.PI*2); context.fill();
                context.fillStyle = '#78909c';
                context.beginPath(); context.arc(drawX, drawY, radius, 0, Math.PI*2); context.fill();
                context.strokeStyle = '#333'; context.lineWidth = 1; context.stroke();
                context.fillStyle = (this.team === 'PLAYER') ? '#b71c1c' : '#fbc02d';
                context.beginPath(); context.arc(drawX, drawY, radius * 0.6, 0, Math.PI*2); context.fill();
            }
        }

        function getFlagHTML(nation, isSmall = false) {
            const baseName = nation.name.replace(/\s/g, '_');
            const fileName = `${baseName}_${nation.code}`;
            const className = isSmall ? 'flag-icon-small' : 'flag-container';
            // We use webp primary, png fallback in the img onerror
            return `<div class="${className}"><img class="flag-img" src="flags/${fileName}.webp" onerror="this.onerror=null; this.src='flags/${fileName}.png'; this.nextElementSibling ? this.nextElementSibling.style.display='block' : null; if(!this.complete) this.style.display='none';" alt="${nation.code}"><span style="display:none; font-size:10px;">${nation.code}</span></div>`;
        }

        function buildSelectionScreen() {
            selectionGrid.innerHTML = '';
            NATIONS.forEach((n, idx) => {
                const card = document.createElement('div');
                card.className = 'nation-card';
                card.innerHTML = `${getFlagHTML(n)}<span class="nation-name-label">${n.name}</span>`;
                card.onclick = () => selectNation(idx);
                selectionGrid.appendChild(card);
            });
        }

        function selectNation(idx) {
            playerNation = NATIONS[idx];
            let remaining = NATIONS.filter(n => n.code !== playerNation.code);
            botNation = remaining[Math.floor(Math.random() * remaining.length)];
            document.getElementById('player-flag-cell').innerHTML = getFlagHTML(playerNation, true);
            document.getElementById('bot-flag-cell').innerHTML = getFlagHTML(botNation, true);
            document.getElementById('player-inv-name').innerText = playerNation.code;
            document.getElementById('bot-inv-name').innerText = botNation.code;
            selectionOverlay.classList.add('hidden'); gameWrapper.classList.remove('hidden');
            totalRedScore = 0; totalYellowScore = 0; currentEnd = 1;
            document.querySelectorAll('.board-cell:not(:first-child)').forEach(c => c.innerText = "");
            resetEnd(); requestAnimationFrame(gameLoop);
        }

        function resetEnd() {
            stones = []; playerStonesThrown = 0; botStonesThrown = 0; activeStone = null;
            gameState = 'AIMING'; nextBtn.style.display = 'none';
            sheetTitle.innerText = `SHEET B - END ${currentEnd}`;
            updateStonesUI(); startTurn();
        }

        function startTurn() {
            if (playerStonesThrown + botStonesThrown >= 16) {
                gameState = 'END_OVER';
                processEndScore();
                if (currentEnd < 10) { nextBtn.innerText = "NEXT END"; nextBtn.style.display = 'block'; }
                else { nextBtn.innerText = "NEW GAME"; nextBtn.style.display = 'block'; messageEl.innerText = "MATCH COMPLETE!"; }
                return;
            }
            const turn = (playerStonesThrown + botStonesThrown) % 2 === 0 ? 'PLAYER' : 'BOT';
            activeStone = new Stone(WIDTH/2, START_Y, turn);
            gameState = 'AIMING'; targetCameraY = START_Y - VIEW_HEIGHT + 150;
            if (turn === 'PLAYER') {
                turnInfo.innerText = `${playerNation.code} TURN`;
                messageEl.innerText = "Aim Minimap & Hold to Shoot";
                currentAimAngle = -Math.PI / 2;
            } else {
                turnInfo.innerText = `${botNation.code} IS THINKING...`; messageEl.innerText = "";
                setTimeout(botLogic, 1200);
            }
        }

        function botLogic() {
            if (gameState !== 'AIMING') return;
            const targetX = WIDTH/2 + (Math.random() - 0.5) * 80;
            const targetPowerPerc = 72 + (Math.random() * 8);
            const targetPower = (targetPowerPerc / 100) * POWER_MULTIPLIER;
            const angle = Math.atan2(HOUSE_Y - START_Y, targetX - WIDTH/2);
            activeStone.vx = Math.cos(angle) * targetPower; activeStone.vy = Math.sin(angle) * targetPower;
            gameState = 'MOVING'; botStonesThrown++; updateStonesUI();
        }

        function processEndScore() {
            const hx = WIDTH/2, hy = HOUSE_Y;
            let houseStones = stones.map(s => ({ team: s.team, dist: Math.hypot(s.x - hx, s.y - hy) })).filter(s => s.dist < 140);
            
            if (houseStones.length === 0) {
                const redBlank = document.getElementById('red-blank-cell');
                const yellowBlank = document.getElementById('yellow-blank-cell');
                redBlank.innerText += (redBlank.innerText ? "," : "") + currentEnd;
                yellowBlank.innerText += (yellowBlank.innerText ? "," : "") + currentEnd;
                messageEl.innerText = "BLANK END";
                return;
            }

            // Scoring Logic: count winner's stones before opponent's closest stone
            houseStones.sort((a, b) => a.dist - b.dist);
            const winner = houseStones[0].team;
            let endPoints = 0;
            const opponent = winner === 'PLAYER' ? 'BOT' : 'PLAYER';
            const opponentClosest = houseStones.find(s => s.team === opponent)?.dist || 140;
            for (let s of houseStones) { if (s.team === winner && s.dist < opponentClosest) endPoints++; else break; }

            if (winner === 'PLAYER') {
                totalRedScore += endPoints;
                const colIdx = Math.min(totalRedScore, 10);
                const cells = document.querySelectorAll('#red-score-row .board-cell');
                if (colIdx > 0) cells[colIdx + 1].innerText = currentEnd;
                messageEl.innerText = `END ${currentEnd}: ${playerNation.code} SCORED ${endPoints}`;
            } else {
                totalYellowScore += endPoints;
                const colIdx = Math.min(totalYellowScore, 10);
                const cells = document.querySelectorAll('#yellow-score-row .board-cell');
                if (colIdx > 0) cells[colIdx + 1].innerText = currentEnd;
                messageEl.innerText = `END ${currentEnd}: ${botNation.code} SCORED ${endPoints}`;
            }
        }

        function resolveCollision(s1, s2) {
            const dx = s2.x - s1.x, dy = s2.y - s1.y, distance = Math.hypot(dx, dy);
            if (distance < STONE_RADIUS * 2) {
                const overlap = STONE_RADIUS * 2 - distance, nx = dx / distance, ny = dy / distance;
                s1.x -= nx * overlap / 2; s1.y -= ny * overlap / 2;
                s2.x += nx * overlap / 2; s2.y += ny * overlap / 2;
                const v1n = s1.vx * nx + s1.vy * ny, v2n = s2.vx * nx + s2.vy * ny;
                s1.vx += (v2n - v1n) * nx; s1.vy += (v2n - v1n) * ny;
                s2.vx += (v1n - v2n) * nx; s2.vy += (v1n - v2n) * ny;
                s1.stopped = false; s2.stopped = false;
            }
        }

        function handleAim(e, isMini) {
            const rect = isMini ? miniCanvas.getBoundingClientRect() : canvas.getBoundingClientRect();
            const mouseX = e.clientX - rect.left, mouseY = e.clientY - rect.top;
            let worldX, worldY;
            if (isMini) { worldX = mouseX / (MINI_WIDTH / WIDTH); worldY = mouseY / (VIEW_HEIGHT / RINK_HEIGHT); }
            else { worldX = mouseX; worldY = mouseY + cameraY; }
            currentAimAngle = Math.atan2(worldY - START_Y, worldX - WIDTH/2);
        }

        canvas.addEventListener('mousedown', (e) => {
            if (gameState === 'AIMING' && (playerStonesThrown + botStonesThrown) % 2 === 0) {
                handleAim(e, false); gameState = 'POWER'; power = 0; powerUI.style.display = 'block';
            }
            mouseDown = true;
        });

        miniCanvas.addEventListener('mousedown', (e) => {
            if (gameState === 'AIMING' && (playerStonesThrown + botStonesThrown) % 2 === 0) {
                handleAim(e, true); gameState = 'POWER'; power = 0; powerUI.style.display = 'block';
            }
            mouseDown = true;
        });

        window.addEventListener('mousemove', (e) => {
            if (gameState === 'AIMING') {
                const cRect = canvas.getBoundingClientRect(), mRect = miniCanvas.getBoundingClientRect();
                if (e.clientX >= cRect.left && e.clientX <= cRect.right && e.clientY >= cRect.top && e.clientY <= cRect.bottom) handleAim(e, false);
                else if (e.clientX >= mRect.left && e.clientX <= mRect.right && e.clientY >= mRect.top && e.clientY <= mRect.bottom) handleAim(e, true);
            }
        });

        window.addEventListener('mouseup', () => {
            if (gameState === 'POWER') {
                const finalPower = (power / 100) * POWER_MULTIPLIER;
                activeStone.vx = Math.cos(currentAimAngle) * finalPower; activeStone.vy = Math.sin(currentAimAngle) * finalPower;
                gameState = 'MOVING'; powerUI.style.display = 'none'; messageEl.innerText = "SWEEP TO HELP!";
                playerStonesThrown++; updateStonesUI();
            }
            mouseDown = false;
        });

        nextBtn.addEventListener('click', () => {
            if (currentEnd < 10) { currentEnd++; resetEnd(); }
            else { selectionOverlay.classList.remove('hidden'); gameWrapper.classList.add('hidden'); buildSelectionScreen(); }
        });

        function drawOlympicRings(ctxTarget, x, y, scale = 1) {
            const ringRadius = 25 * scale, colors = ['#0081C8', '#000000', '#EE334E', '#FCB131', '#00A651'];
            ctxTarget.lineWidth = 5 * scale; ctxTarget.globalAlpha = 0.2;
            const spacingX = 60 * scale, spacingY = 25 * scale;
            const drawRing = (cx, cy, color) => { ctxTarget.strokeStyle = color; ctxTarget.beginPath(); ctxTarget.arc(cx, cy, ringRadius, 0, Math.PI * 2); ctxTarget.stroke(); };
            drawRing(x - spacingX, y - spacingY, colors[0]); drawRing(x, y - spacingY, colors[1]); drawRing(x + spacingX, y - spacingY, colors[2]);
            drawRing(x - spacingX / 2, y + spacingY / 4, colors[3]); drawRing(x + spacingX / 2, y + spacingY / 4, colors[4]);
            ctxTarget.globalAlpha = 1.0;
        }

        function drawIce(ctxTarget, viewY, viewHeight) {
            ctxTarget.fillStyle = '#f0f8ff'; ctxTarget.fillRect(0, 0, WIDTH, viewHeight);
            drawOlympicRings(ctxTarget, WIDTH / 2, (RINK_HEIGHT / 2) - viewY, 1);
            const drawHouse = (y) => {
                const dy = y - viewY;
                const rings = [{r: 140, c: '#1e88e5'}, {r: 100, c: '#f0f8ff'}, {r: 60, c: '#e53935'}, {r: 20, c: '#ffffff'}];
                rings.forEach(ring => { ctxTarget.fillStyle = ring.c; ctxTarget.beginPath(); ctxTarget.arc(WIDTH/2, dy, ring.r, 0, Math.PI*2); ctxTarget.fill(); });
            };
            drawHouse(HOUSE_Y);
            const drawLine = (y, color) => { const dy = y - viewY; ctxTarget.strokeStyle = color; ctxTarget.lineWidth = 4; ctxTarget.beginPath(); ctxTarget.moveTo(0, dy); ctxTarget.lineTo(WIDTH, dy); ctxTarget.stroke(); };
            drawLine(HOUSE_Y + 600, '#d32f2f'); drawLine(START_Y - 300, '#0d47a1');
            ctxTarget.fillStyle = '#333'; ctxTarget.fillRect(0, BACKBOARD_Y - viewY - 10, WIDTH, 10);
        }

        function drawMinimap() {
            miniCtx.fillStyle = '#d1e9ff'; miniCtx.fillRect(0, 0, MINI_WIDTH, VIEW_HEIGHT);
            const scaleX = MINI_WIDTH / WIDTH, scaleY = VIEW_HEIGHT / RINK_HEIGHT;
            drawOlympicRings(miniCtx, MINI_WIDTH / 2, (RINK_HEIGHT / 2) * scaleY, 0.4);
            const rings = [{r: 140, c: '#1e88e5'}, {r: 100, c: '#f0f8ff'}, {r: 60, c: '#e53935'}, {r: 20, c: '#ffffff'}];
            rings.forEach(ring => { miniCtx.fillStyle = ring.c; miniCtx.beginPath(); miniCtx.arc(MINI_WIDTH / 2, HOUSE_Y * scaleY, ring.r * scaleX, 0, Math.PI * 2); miniCtx.fill(); });
            miniCtx.fillStyle = '#333'; miniCtx.fillRect(0, BACKBOARD_Y * scaleY, MINI_WIDTH, 2);
            if (gameState === 'AIMING' || gameState === 'POWER') {
                miniCtx.setLineDash([2, 2]); miniCtx.strokeStyle = 'rgba(0,0,0,0.5)'; miniCtx.beginPath(); miniCtx.moveTo(MINI_WIDTH / 2, START_Y * scaleY);
                const lineLen = 3500 * scaleY; miniCtx.lineTo(MINI_WIDTH/2 + Math.cos(currentAimAngle)*lineLen*(1/scaleY)*scaleX, START_Y*scaleY + Math.sin(currentAimAngle)*lineLen);
                miniCtx.stroke(); miniCtx.setLineDash([]);
            }
            stones.forEach(s => s.draw(miniCtx, 0, 0, scaleX, scaleY, STONE_RADIUS * scaleX));
            if (activeStone) activeStone.draw(miniCtx, 0, 0, scaleX, scaleY, STONE_RADIUS * scaleX);
            miniCtx.strokeStyle = 'rgba(0,0,0,0.4)'; miniCtx.strokeRect(0, cameraY * scaleY, MINI_WIDTH, VIEW_HEIGHT * scaleY);
        }

        function gameLoop() {
            if (gameState === 'START') return;
            ctx.clearRect(0,0,WIDTH,VIEW_HEIGHT);
            cameraY += (targetCameraY - cameraY) * 0.05;
            drawIce(ctx, cameraY, VIEW_HEIGHT);
            if (gameState === 'MOVING') {
                isSweeping = mouseDown; activeStone.update(); stones.forEach(s => s.update());
                stones.forEach(s => resolveCollision(activeStone, s));
                for (let i = 0; i < stones.length; i++) { for (let j = i + 1; j < stones.length; j++) resolveCollision(stones[i], stones[j]); }
                stones = stones.filter(s => !s.outOfPlay);
                targetCameraY = Math.max(0, Math.min(activeStone.y - VIEW_HEIGHT/2, RINK_HEIGHT - VIEW_HEIGHT));
                if (isSweeping && (playerStonesThrown + botStonesThrown - 1) % 2 === 0) {
                    ctx.fillStyle = "rgba(180, 180, 180, 0.7)"; 
                    for(let i=0; i<6; i++) ctx.fillRect(activeStone.x + (Math.random()-0.5)*50, (activeStone.y - cameraY) - 15 - Math.random()*20, 10, 2);
                }
                const allStopped = activeStone.stopped && stones.every(s => s.stopped);
                if (activeStone.outOfPlay || allStopped) { if (!activeStone.outOfPlay) stones.push(activeStone); startTurn(); }
            }
            stones.forEach(s => s.draw(ctx, 0, -cameraY, 1, 1, STONE_RADIUS));
            if (gameState === 'AIMING' || gameState === 'POWER' || gameState === 'MOVING') {
                activeStone.draw(ctx, 0, -cameraY, 1, 1, STONE_RADIUS);
                if ((gameState === 'AIMING' || gameState === 'POWER')) {
                    ctx.setLineDash([5,5]); ctx.strokeStyle = 'rgba(0,0,0,0.2)'; ctx.beginPath(); ctx.moveTo(WIDTH/2, START_Y - cameraY);
                    ctx.lineTo(WIDTH/2 + Math.cos(currentAimAngle) * 800, START_Y - cameraY + Math.sin(currentAimAngle) * 800); 
                    ctx.stroke(); ctx.setLineDash([]);
                }
                if (gameState === 'POWER') { power += currentPowerSpeed * powerDir; if (power > 100 || power < 0) powerDir *= -1; powerBar.style.width = power + '%'; }
            }
            drawMinimap(); requestAnimationFrame(gameLoop);
        }

        function updateStonesUI() {
            const pDots = document.getElementById('player-stones-dots'), bDots = document.getElementById('bot-stones-dots');
            pDots.innerHTML = ''; bDots.innerHTML = '';
            for(let i = 0; i < 8; i++) {
                const pDot = document.createElement('div'); pDot.className = 'stone-dot' + (i >= playerStonesThrown ? ' active-red' : ''); pDots.appendChild(pDot);
                const bDot = document.createElement('div'); bDot.className = 'stone-dot' + (i >= botStonesThrown ? ' active-yellow' : ''); bDots.appendChild(bDot);
            }
        }

        buildSelectionScreen();
    </script>
</body>
</html>