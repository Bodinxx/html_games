<!-- 
CATEGORY: Games
DESCRIPTION: Hangman is a high-stakes word puzzle where you must guess hidden phrases across 60 levels of increasing difficulty to prevent the gallows from being completed. You can choose between Timer Mode for a high-pressure, rapid-fire experience or Relaxed Mode to solve linguistic mysteries at your own pace.
TAGS: Word, Puzzle, Educational, Strategy, Classic
-->
<?php require_once 'Hangman_words.inc'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapid Fire Hangman</title>
    <style>
        :root {
            --bg-color: #2c1e3d; /* Darker purple-orange sunset base */
            --panel-color: #8d4e2a; /* Brownish-orange panel */
            --accent-color: #c98d26; /* Gold/Yellow accent */
            --text-color: #f0e6d2; /* Light cream text */
            --highlight: #ffcc00; /* Bright yellow highlight */
            --success: #4ade80;
            --danger: #f87171;
            --font-main: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        * { box-sizing: border-box; user-select: none; }

        body {
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            background-image: linear-gradient(to bottom, #2c1e3d 0%, #a33c17 40%, #c98d26 65%, #8d4e2a 85%, #5a3a22 100%); /* 80s sunset gradient */
            color: white;
            font-family: var(--font-main);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        /* Header & Stats */
        header {
            width: 100%;
            padding: 15px;
            background-color: var(--panel-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            border-bottom: 3px solid var(--accent-color);
        }

        .level-badge {
            background-color: var(--accent-color);
            color: var(--bg-color);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .difficulty-indicator {
            color: var(--highlight);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        /* Game Container */
        .game-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            max-width: 600px;
            padding: 20px;
            flex-grow: 1;
        }

        /* Canvas Area */
        canvas {
            background-color: #e0c097; /* Desert ground color */
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(163, 60, 23, 0.4);
            margin-bottom: 20px;
            border: 3px solid var(--accent-color);
        }

        /* Timer Bar */
        .timer-container {
            width: 100%;
            height: 10px;
            background-color: #333;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 20px;
            position: relative;
            visibility: visible; /* Toggled by JS */
        }

        .timer-bar {
            height: 100%;
            width: 100%;
            background-color: var(--highlight);
            transition: width 0.1s linear;
        }

        .timer-label {
            position: absolute;
            top: -20px;
            right: 0;
            font-size: 0.8rem;
            color: var(--text-color);
        }

        /* Word Display */
        .word-display {
            font-size: 2rem;
            letter-spacing: 0.5rem;
            margin-bottom: 30px;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            text-align: center;
            min-height: 40px;
            color: var(--highlight);
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        /* Keyboard */
        .keyboard {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            width: 100%;
        }

        .key {
            background-color: var(--panel-color);
            border: 2px solid var(--accent-color);
            color: var(--text-color);
            padding: 10px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
            min-width: 40px;
            text-align: center;
            box-shadow: 2px 2px 0px rgba(0,0,0,0.3);
        }

        .key:hover:not(:disabled) {
            background-color: var(--highlight);
            color: var(--bg-color);
            transform: translateY(-2px);
        }

        .key:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .key.correct {
            background-color: var(--success);
            border-color: var(--success);
            color: white;
        }

        .key.wrong {
            background-color: var(--danger);
            border-color: var(--danger);
            color: white;
        }

        /* Controls */
        .controls {
            margin-top: auto;
            padding: 20px;
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover { background-color: #d63d3d; }

        /* Overlay */
        .overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 100;
            text-align: center;
        }

        .overlay h2 {
            font-size: 3rem;
            margin-bottom: 10px;
        }

        .overlay p {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: white;
        }

        .start-btn {
            background-color: var(--highlight);
            color: var(--bg-color);
            font-size: 1.2rem;
            padding: 12px 30px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .start-btn:hover {
            box-shadow: 0 0 15px var(--highlight);
        }

        /* Mode Selection Styles */
        .mode-selection {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            background: rgba(255,255,255,0.1);
            padding: 10px;
            border-radius: 10px;
        }

        .mode-btn {
            padding: 10px 20px;
            border: 2px solid transparent;
            background: rgba(0,0,0,0.3);
            color: #aaa;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
        }

        .mode-btn:hover {
            background: rgba(0,0,0,0.5);
            color: white;
        }

        .mode-btn.active {
            border-color: var(--highlight);
            background: var(--accent-color);
            color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }

        @media (max-width: 500px) {
            canvas { width: 250px; height: 180px; }
            .key { padding: 8px 10px; font-size: 0.9rem; }
            .word-display { font-size: 1.5rem; letter-spacing: 0.3rem; }
        }
    </style>
</head>
<body>

    <header>
        <div class="level-badge">Level <span id="level-display">1</span></div>
        <div class="difficulty-indicator" id="difficulty-display">Easy</div>
    </header>

    <div class="game-container">
        <canvas id="hangman-canvas" width="300" height="250"></canvas>

        <div class="timer-container" id="timer-container">
            <span class="timer-label">Autostrike: <span id="time-left">10.0</span>s</span>
            <div class="timer-bar" id="timer-bar"></div>
        </div>

        <div class="word-display" id="word-display">READY?</div>

        <div class="keyboard" id="keyboard">
            <!-- Keys generated by JS -->
        </div>

        <div class="controls">
            <button class="btn btn-danger" onclick="game.resetProgress()">Reset Save Data</button>
        </div>
    </div>

    <!-- Start Screen Overlay -->
    <div class="overlay" id="start-overlay" style="display: flex;">
        <h2 style="font-size: 3.5rem; color: var(--highlight); text-shadow: 3px 3px 0 #000; margin-bottom: 1rem;">Hangman</h2>
        <p style="font-size: 1.2rem; margin-bottom: 2rem; max-width: 400px;">
            Survive the hangman's noose.<br>
            Select your game mode below.
        </p>
        
        <div class="mode-selection">
            <button class="mode-btn active" id="btn-mode-timer" onclick="game.selectMode('timer')">⏱️ Timer Mode</button>
            <button class="mode-btn" id="btn-mode-relaxed" onclick="game.selectMode('relaxed')">☕ Relaxed Mode</button>
        </div>

        <div id="mode-desc" style="color: #aaa; font-style: italic; margin-bottom: 15px; height: 20px;">
            Guess fast or take an automatic strike!
        </div>

        <button class="start-btn" onclick="game.beginGame()">ENTER TOWN</button>
    </div>

    <!-- Game Over / Level Up Modal -->
    <div class="overlay" id="overlay">
        <h2 id="modal-title">Game Over</h2>
        <p id="modal-msg">The word was: HOUSE</p>
        <button class="start-btn" id="modal-btn" onclick="game.handleModalClick()">Try Again</button>
    </div>

    <script>
        /**
         * Word Database organized by difficulty - Injected from PHP
         */
        const wordList = <?php echo json_encode($wordList); ?>;

        /**
         * Sound Controller using Web Audio API
         */
        class SoundController {
            constructor() {
                this.ctx = null;
                this.initialized = false;
            }

            init() {
                if (!this.initialized) {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    this.ctx = new AudioContext();
                    this.initialized = true;
                }
                if (this.ctx && this.ctx.state === 'suspended') {
                    this.ctx.resume();
                }
            }

            playTone(freq, type, duration, startTime = 0) {
                if (!this.ctx) return;
                
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();

                osc.type = type;
                osc.frequency.setValueAtTime(freq, this.ctx.currentTime + startTime);
                
                gain.gain.setValueAtTime(0.1, this.ctx.currentTime + startTime);
                gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + startTime + duration);

                osc.connect(gain);
                gain.connect(this.ctx.destination);

                osc.start(this.ctx.currentTime + startTime);
                osc.stop(this.ctx.currentTime + startTime + duration);
            }

            playCorrect() {
                this.playTone(880, 'sine', 0.1); // High ping
                this.playTone(1760, 'sine', 0.1, 0.1); // Higher ping
            }

            playStrike() {
                // Low buzz
                if (!this.ctx) return;
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();

                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(150, this.ctx.currentTime);
                osc.frequency.linearRampToValueAtTime(50, this.ctx.currentTime + 0.3);

                gain.gain.setValueAtTime(0.1, this.ctx.currentTime);
                gain.gain.linearRampToValueAtTime(0, this.ctx.currentTime + 0.3);

                osc.connect(gain);
                gain.connect(this.ctx.destination);

                osc.start();
                osc.stop(this.ctx.currentTime + 0.3);
            }

            playWin() {
                // Major triad arpeggio
                this.playTone(523.25, 'triangle', 0.1, 0); // C5
                this.playTone(659.25, 'triangle', 0.1, 0.1); // E5
                this.playTone(783.99, 'triangle', 0.1, 0.2); // G5
                this.playTone(1046.50, 'triangle', 0.4, 0.3); // C6
            }

            playLoss() {
                // Sad descending tones
                this.playTone(440, 'square', 0.2, 0); 
                this.playTone(415, 'square', 0.2, 0.2); 
                this.playTone(392, 'square', 0.2, 0.4); 
                this.playTone(370, 'square', 0.8, 0.6); 
            }
        }

        class HangmanGame {
            constructor() {
                this.canvas = document.getElementById('hangman-canvas');
                this.ctx = this.canvas.getContext('2d');
                this.currentLevel = 1;
                this.strikes = 0;
                this.maxStrikes = 10; // 4 for gallows, 6 for body
                this.currentWord = "";
                this.guessedLetters = new Set();
                this.timer = null;
                // Time remaining will be set in startLevel based on difficulty
                this.timeRemaining = 10.0; 
                this.gameActive = false;
                this.mode = 'timer'; // 'timer' or 'relaxed'
                
                this.sound = new SoundController();

                this.loadProgress();
                this.initKeyboard();
                // Initialize view but don't start yet
                this.clearCanvas();
                this.drawGallowsBase();
            }

            selectMode(mode) {
                this.mode = mode;
                
                // Visual update
                document.querySelectorAll('.mode-btn').forEach(btn => btn.classList.remove('active'));
                document.getElementById(`btn-mode-${mode}`).classList.add('active');

                // Description Update
                const desc = document.getElementById('mode-desc');
                if (mode === 'timer') {
                    desc.textContent = "Guess fast or take an automatic strike!";
                } else {
                    desc.textContent = "Take your time. No time limits.";
                }
            }

            beginGame() {
                document.getElementById('start-overlay').style.display = 'none';
                this.sound.init(); // Unlock AudioContext on user interaction
                this.startLevel();
            }

            // --- Persistence ---
            saveProgress() {
                localStorage.setItem('hangman_level', this.currentLevel);
            }

            loadProgress() {
                const saved = localStorage.getItem('hangman_level');
                if (saved) {
                    this.currentLevel = parseInt(saved, 10);
                } else {
                    this.currentLevel = 1;
                }
                this.updateLevelUI();
            }

            resetProgress() {
                if(confirm("Are you sure? This will return you to Level 1.")) {
                    localStorage.removeItem('hangman_level');
                    this.currentLevel = 1;
                    // If game hasn't started (overlay visible), update UI but don't start
                    if(document.getElementById('start-overlay').style.display !== 'none') {
                        this.updateLevelUI();
                    } else {
                        this.startLevel();
                    }
                }
            }

            // --- Difficulty Logic ---
            getDifficulty() {
                // Increase difficulty every 20 levels
                if (this.currentLevel <= 20) return 'easy';
                if (this.currentLevel <= 40) return 'medium';
                if (this.currentLevel <= 60) return 'hard';
                return 'expert';
            }

            getMaxTime() {
                const diff = this.getDifficulty();
                switch(diff) {
                    case 'easy': return 10.0;
                    case 'medium': return 8.0;
                    case 'hard': return 5.0;
                    case 'expert': return 3.0;
                    default: return 5.0;
                }
            }

            getRandomWord() {
                const diff = this.getDifficulty();
                const list = wordList[diff];
                return list[Math.floor(Math.random() * list.length)];
            }

            // --- Game Flow ---
            startLevel() {
                this.gameActive = true;
                this.strikes = 0;
                this.guessedLetters.clear();
                this.currentWord = this.getRandomWord();
                this.timeRemaining = this.getMaxTime();
                
                // UI Updates
                this.updateLevelUI();
                this.renderWord();
                this.resetKeyboard();
                this.clearCanvas();
                this.drawGallowsBase(); // Draw just the floor to start
                document.getElementById('overlay').style.display = 'none';

                // Timer vs Relaxed Logic
                const timerContainer = document.getElementById('timer-container');
                
                if (this.mode === 'timer') {
                    timerContainer.style.visibility = 'visible';
                    this.startTimer();
                } else {
                    timerContainer.style.visibility = 'hidden';
                    clearInterval(this.timer);
                }
            }

            handleGuess(letter) {
                if (!this.gameActive || this.guessedLetters.has(letter)) return;

                this.guessedLetters.add(letter);
                this.resetTimer(); // Reset timer on action

                // Update Key UI
                const btn = document.querySelector(`button[data-key="${letter}"]`);
                if(btn) btn.disabled = true;

                if (this.currentWord.includes(letter)) {
                    if(btn) btn.classList.add('correct');
                    this.sound.playCorrect();
                    this.renderWord();
                    this.checkWin();
                } else {
                    if(btn) btn.classList.add('wrong');
                    this.addStrike();
                }
            }

            addStrike() {
                this.strikes++;
                this.sound.playStrike();
                this.drawHangmanPart(this.strikes);
                
                if (this.strikes >= this.maxStrikes) {
                    this.gameOver(false);
                }
            }

            checkWin() {
                const isWon = this.currentWord.split('').every(l => this.guessedLetters.has(l));
                if (isWon) {
                    this.gameOver(true);
                }
            }

            gameOver(win) {
                this.gameActive = false;
                clearInterval(this.timer);
                
                const overlay = document.getElementById('overlay');
                const title = document.getElementById('modal-title');
                const msg = document.getElementById('modal-msg');
                const btn = document.getElementById('modal-btn');

                overlay.style.display = 'flex';

                if (win) {
                    this.sound.playWin();
                    title.textContent = "Level Complete!";
                    title.style.color = "#4ade80";
                    msg.textContent = `You survived Level ${this.currentLevel}`;
                    btn.textContent = "Next Level";
                    btn.onclick = () => {
                        this.currentLevel++;
                        this.saveProgress();
                        this.startLevel();
                    };
                } else {
                    this.sound.playLoss();
                    title.textContent = "GAME OVER";
                    title.style.color = "#f87171";
                    msg.textContent = `The word was: ${this.currentWord}`;
                    btn.textContent = "Retry Level";
                    btn.onclick = () => {
                        // We don't increment level, just retry
                        this.startLevel();
                    };
                }
            }

            handleModalClick() {
                // Handled in gameOver function assignment
            }

            // --- Timer Logic ---
            startTimer() {
                clearInterval(this.timer);
                
                // Double check we are in timer mode before running
                if (this.mode !== 'timer') return;

                this.timeRemaining = this.getMaxTime();
                this.updateTimerUI();

                this.timer = setInterval(() => {
                    if (!this.gameActive) {
                        clearInterval(this.timer);
                        return;
                    }

                    this.timeRemaining -= 0.1;
                    if (this.timeRemaining <= 0) {
                        this.timeRemaining = this.getMaxTime();
                        // Time out = Strike
                        this.addStrike();
                    }
                    this.updateTimerUI();
                }, 100);
            }

            resetTimer() {
                if (this.mode !== 'timer') return;
                this.timeRemaining = this.getMaxTime();
                this.updateTimerUI();
            }

            updateTimerUI() {
                const bar = document.getElementById('timer-bar');
                const text = document.getElementById('time-left');
                const maxTime = this.getMaxTime();
                
                // Percentage logic
                const pct = (this.timeRemaining / maxTime) * 100;
                bar.style.width = `${pct}%`;
                text.textContent = Math.max(0, this.timeRemaining.toFixed(1));

                // Color change based on urgency
                if (pct > 60) bar.style.backgroundColor = '#4cc9f0'; // Blue
                else if (pct > 30) bar.style.backgroundColor = '#facc15'; // Yellow
                else bar.style.backgroundColor = '#f87171'; // Red
            }

            // --- Rendering ---
            updateLevelUI() {
                document.getElementById('level-display').textContent = this.currentLevel;
                document.getElementById('difficulty-display').textContent = this.getDifficulty();
            }

            renderWord() {
                const display = this.currentWord.split('').map(char => 
                    this.guessedLetters.has(char) ? char : '_'
                ).join(' ');
                document.getElementById('word-display').textContent = display;
            }
            
            initKeyboard() {
                const container = document.getElementById('keyboard');
                const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
                container.innerHTML = '';
                
                alphabet.split('').forEach(letter => {
                    const btn = document.createElement('button');
                    btn.textContent = letter;
                    btn.classList.add('key');
                    btn.dataset.key = letter;
                    btn.onclick = () => this.handleGuess(letter);
                    container.appendChild(btn);
                });

                // Physical keyboard listener
                document.onkeydown = (e) => {
                    const letter = e.key.toUpperCase();
                    if (/[A-Z]/.test(letter) && letter.length === 1) {
                        this.handleGuess(letter);
                    }
                };
            }

            resetKeyboard() {
                const keys = document.querySelectorAll('.key');
                keys.forEach(k => {
                    k.disabled = false;
                    k.className = 'key';
                });
            }

            // --- Canvas Drawing ---
            clearCanvas() {
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.ctx.strokeStyle = '#333';
                this.ctx.lineWidth = 4;
                this.ctx.lineCap = 'round';
            }

            drawGallowsBase() {
                // Just the floor to start
                this.ctx.beginPath();
                this.ctx.strokeStyle = '#8B4513'; // SaddleBrown color for wood
                this.ctx.moveTo(20, 230);
                this.ctx.lineTo(280, 230);
                this.ctx.stroke();
            }

            drawHangmanPart(step) {
                this.ctx.strokeStyle = '#000000'; // Black color for the person
                if (step <= 4) this.ctx.strokeStyle = '#8B4513'; // SaddleBrown color for gallows

                this.ctx.beginPath();
                switch(step) {
                    case 1: // Vertical Pole
                        this.ctx.moveTo(50, 230);
                        this.ctx.lineTo(50, 20);
                        break;
                    case 2: // Top Bar
                        this.ctx.moveTo(50, 20);
                        this.ctx.lineTo(150, 20);
                        break;
                    case 3: // Support beam
                        this.ctx.moveTo(50, 50);
                        this.ctx.lineTo(80, 20);
                        break;
                    case 4: // Rope
                        this.ctx.moveTo(150, 20);
                        this.ctx.lineTo(150, 50);
                        break;
                    case 5: // Head
                        this.ctx.arc(150, 70, 20, 0, Math.PI * 2);
                        break;
                    case 6: // Body
                        this.ctx.moveTo(150, 90);
                        this.ctx.lineTo(150, 170);
                        break;
                    case 7: // Left Arm
                        this.ctx.moveTo(150, 110);
                        this.ctx.lineTo(110, 140);
                        break;
                    case 8: // Right Arm
                        this.ctx.moveTo(150, 110);
                        this.ctx.lineTo(190, 140);
                        break;
                    case 9: // Left Leg
                        this.ctx.moveTo(150, 170);
                        this.ctx.lineTo(120, 210);
                        break;
                    case 10: // Right Leg
                        this.ctx.moveTo(150, 170);
                        this.ctx.lineTo(180, 210);
                        break;
                }
                this.ctx.stroke();
            }
        }

        // Initialize Game
        const game = new HangmanGame();

    </script>
    <a href="http://web-mage.ca/games/"><img src="WebMage-sm.webp" style="position: fixed; right: 16px; bottom: 16px; height: 50px; z-index: 9999; pointer-events: auto;"></a>
</body>
</html>