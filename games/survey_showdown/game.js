document.addEventListener('DOMContentLoaded', () => {
    // State Variables
    let currentQuestionId = null;
    let usedQuestionIds = [];
    let roundActive = false;
    let buzzerLocked = false;
    let currentTurn = null; 
    let strikes = 0;
    let bank = 0;
    let revealedCount = 0;
    let totalAnswers = 0;
    let timerInterval = null; 
    
    // Team Names & Settings
    let teamNames = { 1: "Team 1", 2: "Team 2" };
    // Player Rosters
    let teamPlayers = { 1: [], 2: [] };
    // Track rotation index for Face-Offs (Who faces who at start of round)
    let faceOffIndices = { 1: 0, 2: 0 };
    // Track rotation index for Main Play (Who answers during the round)
    let lineIndices = { 1: 0, 2: 0 };
    
    let controlMode = 'keyboard'; // 'keyboard' or 'touch'
    let soundEnabled = true; // Sound state
    
    // Game Options (Defaults)
    let gameSettings = {
        answerTime: 10,
        stealTime: 20,
        turnTransitionTime: 5,
        roundCount: 4,
        gameSeries: "",
        saveScores: true
    };
    
    // Round State
    let currentRound = 1;
    let maxRounds = 4;
    let multiplier = 1;

    // Steal & Face-Off State
    let isStealPhase = false;
    let originalController = null;
    let faceOffActive = false;
    let faceOffPoints = null; 
    let faceOffLeader = null; 
    let faceOffStrikes = 0; 
    
    // Scores
    let scores = { 1: 0, 2: 0 };

    // Constant HTML for the Strike Overlay (to prevent corruption)
    const STRIKE_CONTENT_HTML = `
        <span class="big-x">X</span>
        <span class="big-x hidden" id="strike-2">X</span>
        <span class="big-x hidden" id="strike-3">X</span>
    `;

    // --- AUDIO SYSTEM (OPTIMIZED) ---
    const sounds = {
        correct: new Audio('survey_showdown/ffCorrect.mp3'),
        wrong: new Audio('survey_showdown/ffStrike.wav'),
        duplicate: new Audio('survey_showdown/ffduplicate.mp3'),
        round_end: new Audio('survey_showdown/Clapping.wav')
    };

    // DOM Elements
    const els = {
        questionText: document.getElementById('question-text'),
        board: document.getElementById('answers-board'),
        boardContainer: document.getElementById('main-board-container'),
        inputArea: document.getElementById('input-area'),
        input: document.getElementById('answer-input'),
        submitBtn: document.getElementById('submit-btn'),
        timerDisplay: document.getElementById('timer-display'), 
        buzzerOverlay: document.getElementById('buzzer-overlay'),
        buzzerWinner: document.getElementById('buzzer-winner'),
        strikeOverlay: document.getElementById('strike-overlay'),
        msg: document.getElementById('game-message'),
        bank: document.getElementById('bank-score'),
        score1: document.getElementById('score-1'),
        score2: document.getElementById('score-2'),
        name1Display: document.getElementById('name-1'),
        name2Display: document.getElementById('name-2'),
        hint1: document.getElementById('hint-1'),
        hint2: document.getElementById('hint-2'),
        team1: document.querySelector('.team-1'),
        team2: document.querySelector('.team-2'),
        startBtn: document.getElementById('start-btn'),
        resetBtn: document.getElementById('reset-btn'),
        roundIndicator: document.getElementById('round-indicator'),
        multiplierDisplay: document.getElementById('multiplier-display'),
        
        // Touch Controls
        touchControls: document.getElementById('touch-controls'),
        touchBtn1: document.getElementById('touch-btn-1'),
        touchBtn2: document.getElementById('touch-btn-2'),

        // Modals
        startModal: document.getElementById('start-modal'),
        inputName1: document.getElementById('input-name-1'),
        inputName2: document.getElementById('input-name-2'),
        inputTeam1Players: document.getElementById('input-team1-players'),
        inputTeam2Players: document.getElementById('input-team2-players'),
        
        // Control Mode Buttons
        btnModeKeyboard: document.getElementById('btn-mode-keyboard'),
        btnModeTouch: document.getElementById('btn-mode-touch'),
        
        confirmStartBtn: document.getElementById('confirm-start-btn'),
        
        // Rules Modal
        viewRulesBtn: document.getElementById('view-rules-btn'),
        rulesModal: document.getElementById('rules-modal'),
        closeRulesBtn: document.getElementById('close-rules-btn'),
        
        // Options Modal
        optionsBtn: document.getElementById('options-btn'),
        optionsModal: document.getElementById('options-modal'),
        saveOptionsBtn: document.getElementById('save-options-btn'),
        optGameSeries: document.getElementById('opt-game-series'),
        optSaveScores: document.getElementById('opt-save-scores'),
        optAnswerTime: document.getElementById('opt-answer-time'),
        optStealTime: document.getElementById('opt-steal-time'),
        optTransitionTime: document.getElementById('opt-transition-time'),
        optRoundCount: document.getElementById('opt-round-count'),
        
        // Slider Displays
        dispAnswerTime: document.getElementById('disp-answer-time'),
        dispStealTime: document.getElementById('disp-steal-time'),
        dispTransitionTime: document.getElementById('disp-transition-time'),
        dispRoundCount: document.getElementById('disp-round-count'),
        
        scoresModal: document.getElementById('scores-modal'),
        showScoresBtn: document.getElementById('show-scores-btn'),
        closeScoresBtn: document.getElementById('close-scores-btn'),
        scoresTableBody: document.querySelector('#scores-table tbody'),
        
        // Sound Toggle
        toggleSoundBtn: document.getElementById('toggle-sound-btn')
    };

    // --- Load Settings on Init ---
    loadSettingsLocal();

    function loadSettingsLocal() {
        const stored = localStorage.getItem('surveyShowdownSettings');
        if (stored) {
            try {
                const parsed = JSON.parse(stored);
                gameSettings = { ...gameSettings, ...parsed };
                maxRounds = gameSettings.roundCount;
            } catch (e) {
                console.error("Error loading settings", e);
            }
        }
    }

    function saveSettingsLocal() {
        localStorage.setItem('surveyShowdownSettings', JSON.stringify(gameSettings));
    }

    function playSound(type) {
        if (!soundEnabled || !sounds[type]) return;
        const soundClone = sounds[type].cloneNode(true);
        soundClone.volume = 1.0; 
        soundClone.play().catch(e => { console.log("Audio play failed", e); });
        console.log(`Sound: ${type}`); 
    }

    // --- Helper: Get Current Player Name ---
    function getCurrentPlayerName(teamId, isFaceOff = false) {
        const players = teamPlayers[teamId];
        if (!players || players.length === 0) return teamNames[teamId]; 
        const index = isFaceOff ? faceOffIndices[teamId] : lineIndices[teamId];
        const safeIndex = index % players.length;
        return `${teamNames[teamId]}: ${players[safeIndex]}`;
    }

    function advanceLineIndex(teamId) {
        if (teamPlayers[teamId].length > 0) {
            lineIndices[teamId]++;
        }
    }

    // --- Sound Toggle Logic ---
    if (els.toggleSoundBtn) {
        els.toggleSoundBtn.addEventListener('click', () => {
            soundEnabled = !soundEnabled;
            els.toggleSoundBtn.textContent = `Sound: ${soundEnabled ? 'ON' : 'OFF'}`;
            els.toggleSoundBtn.style.opacity = soundEnabled ? '1' : '0.6';
        });
    }

    // --- Control Mode Selection Logic ---
    if (els.btnModeKeyboard && els.btnModeTouch) {
        els.btnModeKeyboard.addEventListener('click', () => setControlMode('keyboard'));
        els.btnModeTouch.addEventListener('click', () => setControlMode('touch'));
    }

    function setControlMode(mode) {
        controlMode = mode;
        if (mode === 'keyboard') {
            els.btnModeKeyboard.classList.add('selected');
            els.btnModeTouch.classList.remove('selected');
        } else {
            els.btnModeTouch.classList.add('selected');
            els.btnModeKeyboard.classList.remove('selected');
        }
    }

    // --- Rules Modal Logic ---
    if (els.viewRulesBtn) {
        els.viewRulesBtn.addEventListener('click', () => {
            els.startModal.classList.add('hidden');
            els.rulesModal.classList.remove('hidden');
        });
    }
    
    if (els.closeRulesBtn) {
        els.closeRulesBtn.addEventListener('click', () => {
            els.rulesModal.classList.add('hidden');
            els.startModal.classList.remove('hidden');
        });
    }

    // --- Options Modal Logic ---
    if (els.optionsBtn) {
        els.optionsBtn.addEventListener('click', () => {
            els.startModal.classList.add('hidden');
            els.optionsModal.classList.remove('hidden');
            
            els.optAnswerTime.value = gameSettings.answerTime;
            els.dispAnswerTime.textContent = gameSettings.answerTime + 's';
            els.optStealTime.value = gameSettings.stealTime;
            els.dispStealTime.textContent = gameSettings.stealTime + 's';
            els.optTransitionTime.value = gameSettings.turnTransitionTime;
            els.dispTransitionTime.textContent = gameSettings.turnTransitionTime + 's';
            els.optRoundCount.value = gameSettings.roundCount;
            els.dispRoundCount.textContent = gameSettings.roundCount;
            els.optGameSeries.value = gameSettings.gameSeries || "";
            els.optSaveScores.checked = gameSettings.saveScores;
        });
    }

    if(els.optAnswerTime) els.optAnswerTime.addEventListener('input', (e) => els.dispAnswerTime.textContent = e.target.value + 's');
    if(els.optStealTime) els.optStealTime.addEventListener('input', (e) => els.dispStealTime.textContent = e.target.value + 's');
    if(els.optTransitionTime) els.optTransitionTime.addEventListener('input', (e) => els.dispTransitionTime.textContent = e.target.value + 's');
    if(els.optRoundCount) els.optRoundCount.addEventListener('input', (e) => els.dispRoundCount.textContent = e.target.value);

    if (els.saveOptionsBtn) {
        els.saveOptionsBtn.addEventListener('click', () => {
            gameSettings.answerTime = parseInt(els.optAnswerTime.value) || 10;
            gameSettings.stealTime = parseInt(els.optStealTime.value) || 20;
            gameSettings.turnTransitionTime = parseInt(els.optTransitionTime.value) || 5;
            gameSettings.roundCount = parseInt(els.optRoundCount.value) || 4;
            gameSettings.gameSeries = els.optGameSeries.value.trim().substring(0, 25);
            gameSettings.saveScores = els.optSaveScores.checked;
            
            maxRounds = gameSettings.roundCount;
            saveSettingsLocal();
            
            els.optionsModal.classList.add('hidden');
            els.startModal.classList.remove('hidden');
        });
    }

    // --- Start Game Logic ---
    if (els.confirmStartBtn) {
        els.confirmStartBtn.addEventListener('click', () => {
            const n1 = els.inputName1.value.trim() || "Team 1";
            const n2 = els.inputName2.value.trim() || "Team 2";
            
            teamNames[1] = n1;
            teamNames[2] = n2;
            
            const p1Text = els.inputTeam1Players ? els.inputTeam1Players.value : "";
            const p2Text = els.inputTeam2Players ? els.inputTeam2Players.value : "";
            
            teamPlayers[1] = p1Text.split(',').map(s => s.trim()).filter(s => s !== "");
            teamPlayers[2] = p2Text.split(',').map(s => s.trim()).filter(s => s !== "");
            
            faceOffIndices = { 1: 0, 2: 0 };
            lineIndices = { 1: 0, 2: 0 };
            
            els.name1Display.textContent = n1;
            els.name2Display.textContent = n2;
            
            maxRounds = gameSettings.roundCount;
            
            if (controlMode === 'touch') {
                els.touchControls.classList.remove('hidden');
                els.hint1.classList.add('hidden');
                els.hint2.classList.add('hidden');
                els.touchBtn1.innerHTML = `${n1}<br>BUZZER`;
                els.touchBtn2.innerHTML = `${n2}<br>BUZZER`;
                document.querySelector('.game-container').style.paddingBottom = '100px';
            } else {
                els.touchControls.classList.add('hidden');
                els.hint1.classList.remove('hidden');
                els.hint2.classList.remove('hidden');
                document.querySelector('.game-container').style.paddingBottom = '0';
            }

            els.startModal.classList.add('hidden');
            els.roundIndicator.classList.remove('hidden');
            els.startBtn.classList.remove('hidden');
            
            startRound();
        });
    }

    // --- High Scores Logic ---
    if (els.showScoresBtn) {
        els.showScoresBtn.addEventListener('click', loadScores);
        els.closeScoresBtn.addEventListener('click', () => els.scoresModal.classList.add('hidden'));
    }

    async function loadScores() {
        els.scoresModal.classList.remove('hidden');
        els.scoresTableBody.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';
        
        try {
            const res = await fetch('survey_showdown/api.php?action=get_scores');
            const data = await res.json();
            
            els.scoresTableBody.innerHTML = '';
            if (data.length === 0) {
                els.scoresTableBody.innerHTML = '<tr><td colspan="6">No scores recorded yet.</td></tr>';
                return;
            }

            data.forEach(row => {
                const tr = document.createElement('tr');
                const seriesDisplay = row.series ? row.series : '-';
                tr.innerHTML = `
                    <td style="color: #aaa; font-size: 0.8rem;">${seriesDisplay}</td>
                    <td>${row.date}</td>
                    <td style="color: #ffcc00; font-weight: bold;">${row.winner}</td>
                    <td>${row.winner === row.team1 ? row.score1 : row.score2}</td>
                    <td>${row.winner === row.team1 ? row.team2 : row.team1}</td>
                    <td>${row.winner === row.team1 ? row.score2 : row.score1}</td>
                `;
                els.scoresTableBody.appendChild(tr);
            });
        } catch (e) {
            els.scoresTableBody.innerHTML = '<tr><td colspan="6">Error loading scores.</td></tr>';
        }
    }

    async function saveGameScore(winnerName) {
        if (!gameSettings.saveScores) {
            console.log("Score saving disabled in options.");
            return;
        }
        try {
            await fetch('survey_showdown/api.php?action=save_score', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    team1: teamNames[1],
                    score1: scores[1],
                    team2: teamNames[2],
                    score2: scores[2],
                    winner: winnerName,
                    gameSeries: gameSettings.gameSeries
                })
            });
        } catch (e) {
            console.error("Failed to save score", e);
        }
    }

    // --- Face-Off Overlay Logic ---
    function showFaceOffOverlay(p1FullName, p2FullName, callback) {
        const contentDiv = els.strikeOverlay.querySelector('.strike-content');
        // Removed capturing originalContent to prevent corruption
        
        // Extract names
        const p1 = p1FullName.split(':')[1] ? p1FullName.split(':')[1].trim() : p1FullName;
        const p2 = p2FullName.split(':')[1] ? p2FullName.split(':')[1].trim() : p2FullName;

        contentDiv.innerHTML = `
            <div style="text-align: center; color: #ffcc00; font-family: 'Anton', sans-serif;">
                <div style="font-size: 5rem; color: #fff; margin-bottom: 20px; text-shadow: 2px 2px 5px #000;">FACE-OFF</div>
                <div style="font-size: 3rem; text-shadow: 2px 2px 4px #000;">${p1}</div>
                <div style="font-size: 2rem; color: #aaa; margin: 10px 0;">VS</div>
                <div style="font-size: 3rem; margin-bottom: 30px; text-shadow: 2px 2px 4px #000;">${p2}</div>
                <div style="font-size: 1.5rem; color: #fff; animation: pulse 1s infinite;">PRESS ANY KEY TO START</div>
            </div>
        `;
        
        els.strikeOverlay.classList.remove('hidden');
        
        function onProceed(e) {
            if(e.type === 'keydown' || e.type === 'click' || e.type === 'touchstart') {
                if(e.type !== 'keydown') e.preventDefault();
                
                document.removeEventListener('keydown', onProceed);
                els.strikeOverlay.removeEventListener('click', onProceed);
                els.strikeOverlay.removeEventListener('touchstart', onProceed);
                
                els.strikeOverlay.classList.add('hidden');
                // Always restore to clean Xs
                contentDiv.innerHTML = STRIKE_CONTENT_HTML;
                callback();
            }
        }

        setTimeout(() => {
            document.addEventListener('keydown', onProceed);
            els.strikeOverlay.addEventListener('click', onProceed);
            els.strikeOverlay.addEventListener('touchstart', onProceed);
        }, 500);
    }

    // --- Round Logic ---

    async function startRound() {
        if (currentRound > maxRounds) return;

        if (currentRound === 3) multiplier = 2;
        else if (currentRound >= 4) multiplier = 3;
        else multiplier = 1;

        els.roundIndicator.textContent = `ROUND ${currentRound} / ${maxRounds}`;
        if (multiplier > 1) {
            els.multiplierDisplay.textContent = multiplier === 2 ? "DOUBLED POINTS" : "TRIPLED POINTS";
            els.multiplierDisplay.classList.remove('hidden');
        } else {
            els.multiplierDisplay.classList.add('hidden');
        }

        roundActive = false;
        buzzerLocked = true;
        currentTurn = null;
        strikes = 0;
        bank = 0;
        revealedCount = 0;
        isStealPhase = false;
        originalController = null;
        
        faceOffActive = true;
        faceOffPoints = null;
        faceOffLeader = null;
        faceOffStrikes = 0;
        
        if (currentRound > 1) {
            faceOffIndices[1]++;
            faceOffIndices[2]++;
        }
        
        lineIndices[1] = faceOffIndices[1];
        lineIndices[2] = faceOffIndices[2];
        
        stopTimer();
        highlightTurn(null); 
        
        els.startBtn.textContent = "Next Round";
        els.bank.textContent = '0';
        els.inputArea.classList.add('hidden');
        els.buzzerOverlay.classList.add('hidden');
        
        const p1Name = getCurrentPlayerName(1, true);
        const p2Name = getCurrentPlayerName(2, true);
        
        els.questionText.textContent = "Get Ready...";
        els.board.innerHTML = '';
        els.input.value = '';

        showFaceOffOverlay(p1Name, p2Name, async () => {
            roundActive = true;
            buzzerLocked = false;
            
            const faceOffMsg = controlMode === 'touch' ? 
                `FACE-OFF! Tap Buzzer!` : 
                `FACE-OFF! (Z) vs (M)`;
                
            els.msg.textContent = `${faceOffMsg}`;
            els.questionText.textContent = "Loading Question...";

            try {
                const excludeStr = usedQuestionIds.join(',');
                const response = await fetch(`survey_showdown/api.php?action=get_question&exclude=${excludeStr}`);
                
                if (!response.ok) throw new Error(`Server Error: ${response.status}`);
                const data = await response.json();
                
                if (data.error) {
                    els.questionText.textContent = `Error: ${data.error}`;
                    return;
                }

                currentQuestionId = data.id;
                usedQuestionIds.push(currentQuestionId); 
                
                els.questionText.textContent = data.question;
                totalAnswers = data.count;

                for (let i = 0; i < totalAnswers; i++) {
                    const card = document.createElement('div');
                    card.className = 'answer-card';
                    card.id = `ans-${i}`;
                    card.innerHTML = `
                        <div class="card-cover">${i + 1}</div>
                        <div class="card-content">
                            <span class="ans-text"></span>
                            <span class="ans-points"></span>
                        </div>
                    `;
                    els.board.appendChild(card);
                }
            } catch (e) {
                console.error(e);
                els.questionText.textContent = "Connection Error. Check console.";
            }
        });
    }

    function handleBuzzer(player) {
        if (!roundActive || buzzerLocked) return;
        
        buzzerLocked = true;
        currentTurn = player;
        highlightTurn(player);

        const isFaceOff = faceOffActive;
        const name = getCurrentPlayerName(player, isFaceOff);
        
        const displayName = name.includes(':') ? name.split(':')[1].trim() : name;

        els.buzzerWinner.textContent = `${displayName.toUpperCase()} BUZZED!`;
        els.buzzerOverlay.classList.remove('hidden');
        els.msg.textContent = `${name} has control!`;

        setTimeout(() => {
            els.buzzerOverlay.classList.add('hidden');
            els.inputArea.classList.remove('hidden');
            els.input.focus();
            startTimer(gameSettings.answerTime);
        }, 1500);
    }

    function highlightTurn(player) {
        els.team1.style.opacity = '1';
        els.team2.style.opacity = '1';
        els.team1.style.border = 'none';
        els.team2.style.border = 'none';
        els.team1.style.padding = '0';
        els.team2.style.padding = '0';

        if (player === 1) {
            els.team2.style.opacity = '0.5';
            els.team1.style.border = '3px solid #ffcc00';
            els.team1.style.borderRadius = '10px';
            els.team1.style.padding = '5px';
        } else if (player === 2) {
            els.team1.style.opacity = '0.5';
            els.team2.style.border = '3px solid #ffcc00';
            els.team2.style.borderRadius = '10px';
            els.team2.style.padding = '5px';
        }
    }

    function startTimer(seconds = 10) {
        stopTimer();
        let timeLeft = seconds;
        let phaseText = isStealPhase ? "STEALING" : "guessing";
        if (faceOffActive && faceOffPoints !== null) phaseText = "trying to beat score";
        
        const isFaceOff = faceOffActive;
        let display = "";
        if (isStealPhase) display = `${teamNames[currentTurn]} (Group)`;
        else display = getCurrentPlayerName(currentTurn, isFaceOff);

        els.msg.textContent = `${display} ${phaseText}...`; 
        
        if (els.timerDisplay) {
            els.timerDisplay.textContent = timeLeft;
            els.timerDisplay.classList.remove('urgent');
        }

        timerInterval = setInterval(() => {
            timeLeft--;
            if (els.timerDisplay) {
                els.timerDisplay.textContent = timeLeft;
                if (timeLeft <= 3) els.timerDisplay.classList.add('urgent');
            }
            if (timeLeft <= 0) {
                stopTimer();
                els.msg.textContent = "TIME'S UP!";
                handleStrike();
            }
        }, 1000);
    }

    function stopTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }

    function showTextOverlay(text, callback) {
        const contentDiv = els.strikeOverlay.querySelector('.strike-content');
        
        contentDiv.innerHTML = `<div class="overlay-message">${text}</div>`;
        els.strikeOverlay.classList.remove('hidden');
        
        setTimeout(() => {
            els.strikeOverlay.classList.add('hidden');
            contentDiv.innerHTML = STRIKE_CONTENT_HTML; // FORCE RESET TO XS
            if (callback) callback();
        }, 2000);
    }

    function transitionToNextTurn() {
        if (isStealPhase) return; 
        
        if (faceOffActive) {
            setTimeout(() => startTimer(gameSettings.answerTime), 1000);
            return;
        }

        advanceLineIndex(currentTurn);

        stopTimer();
        els.inputArea.classList.add('hidden'); 

        const popupDuration = 2000; 
        const totalDuration = gameSettings.turnTransitionTime * 1000;
        let preDelay = totalDuration - popupDuration;
        if (preDelay < 0) preDelay = 0;

        setTimeout(() => {
            const contentDiv = els.strikeOverlay.querySelector('.strike-content');
            
            const pName = getCurrentPlayerName(currentTurn, false); 
            let nameOnly = pName.split(':')[1] || pName; 
            
            contentDiv.innerHTML = `
                <div style="text-align: center; color: #ffcc00; font-family: 'Anton', sans-serif;">
                    <div style="font-size: 2rem; color: #fff;">${teamNames[currentTurn]}</div>
                    <div style="font-size: 4rem; line-height: 1.2;">${nameOnly}</div>
                    <div style="font-size: 3rem; color: #fff;">GET READY</div>
                </div>
            `;
            
            els.strikeOverlay.classList.remove('hidden');

            setTimeout(() => {
                els.strikeOverlay.classList.add('hidden');
                contentDiv.innerHTML = STRIKE_CONTENT_HTML; // FORCE RESET TO XS
                
                els.input.value = '';
                els.inputArea.classList.remove('hidden');
                els.input.focus();
                startTimer(gameSettings.answerTime);
            }, popupDuration); 
        }, preDelay); 
    }

    async function submitAnswer() {
        const text = els.input.value.trim();
        if (!text) return;

        stopTimer();
        els.input.value = '';
        
        try {
            const response = await fetch('survey_showdown/api.php?action=check_answer', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    question_id: currentQuestionId,
                    answer: text
                })
            });

            const result = await response.json();

            if (result.correct) {
                const card = document.getElementById(`ans-${result.rank}`);
                
                // --- FIX: Logic to handle duplicate answer ---
                if (card && card.classList.contains('revealed')) {
                    stopTimer();
                    playSound('duplicate'); 
                    showTextOverlay(`DUPLICATE ANSWER:<br>${result.text}`, () => {
                         els.input.focus();
                         if (!isStealPhase) startTimer(gameSettings.answerTime);
                         else handleStrike(); 
                    });
                    return;
                }

                playSound('correct');
                const points = result.points * multiplier;
                revealCard(card, result.text, points, false);
                bank += points;
                els.bank.textContent = bank;

                els.msg.textContent = `CORRECT! ${getCurrentPlayerName(currentTurn, faceOffActive)} gets points!`;

                if (faceOffActive) {
                    if (faceOffPoints === null) {
                        if (faceOffStrikes > 0) {
                            faceOffActive = false;
                            els.msg.textContent = "CORRECT! You win control!";
                            transitionToNextTurn();
                            return;
                        }

                        if (result.rank === 0) {
                            faceOffActive = false;
                            els.msg.textContent = "NUMBER 1 ANSWER! You keep control!";
                            transitionToNextTurn(); 
                        } else {
                            faceOffPoints = points;
                            faceOffLeader = currentTurn;
                            currentTurn = currentTurn === 1 ? 2 : 1;
                            highlightTurn(currentTurn);
                            els.msg.textContent = `Not #1! ${getCurrentPlayerName(currentTurn, true)}, beat answer to steal!`;
                            els.input.focus();
                            startTimer(gameSettings.answerTime);
                            return; 
                        }
                    } else {
                        faceOffActive = false;
                        if (points > faceOffPoints) {
                            els.msg.textContent = "HIGHER POINTS! You stole control!";
                        } else {
                            els.msg.textContent = "POINTS NOT HIGHER! Control returns.";
                            currentTurn = faceOffLeader; 
                            highlightTurn(currentTurn);
                        }
                        transitionToNextTurn(); 
                    }
                    return; 
                }

                if (isStealPhase) {
                    els.msg.textContent = "STEAL SUCCESSFUL!";
                    endRound(currentTurn); 
                    return;
                }

                revealedCount++;
                if (revealedCount === totalAnswers) {
                    endRound(currentTurn);
                } else {
                    transitionToNextTurn(); 
                }
            } else {
                handleStrike();
            }
        } catch (e) {
            console.error(e);
        }
    }

    function revealCard(cardElement, text, points, isMissed = false) {
        cardElement.querySelector('.ans-text').textContent = text;
        cardElement.querySelector('.ans-points').textContent = points;
        cardElement.classList.add('revealed');
        if (isMissed) cardElement.classList.add('grey-reveal');
    }

    function initiateSteal() {
        stopTimer();
        isStealPhase = true;
        originalController = currentTurn;
        
        const stealingPlayer = currentTurn === 1 ? 2 : 1;
        currentTurn = stealingPlayer;
        
        const contentDiv = els.strikeOverlay.querySelector('.strike-content');
        // RESET CONTENT FIRST
        contentDiv.innerHTML = '<span class="big-x" style="font-size: 8rem;">STEAL!</span>';
        
        els.strikeOverlay.classList.remove('hidden');
        playSound('wrong');

        els.inputArea.classList.add('hidden');

        setTimeout(() => {
            els.strikeOverlay.classList.add('hidden');
            contentDiv.innerHTML = STRIKE_CONTENT_HTML; // FORCE RESET TO XS
            
            els.msg.textContent = `${teamNames[currentTurn]} to STEAL!`;
            highlightTurn(currentTurn);
            els.input.value = '';
            els.inputArea.classList.remove('hidden');
            els.input.focus();
            startTimer(gameSettings.stealTime);
        }, 1500);
    }

    function handleStrike() {
        stopTimer();
        playSound('wrong');
        
        if (faceOffActive) {
            if (faceOffPoints === null) {
                faceOffStrikes++;
                if (faceOffStrikes >= 8) {
                    els.msg.textContent = "8 Strikes! Getting new question...";
                    faceOffActive = false; 
                    setTimeout(() => startRound(), 3000); 
                } else {
                    currentTurn = currentTurn === 1 ? 2 : 1;
                    els.msg.textContent = "Missed! Opponent gets a try!";
                    highlightTurn(currentTurn);
                    showStrikeAnimation(() => {
                        els.input.focus();
                        setTimeout(() => startTimer(gameSettings.answerTime), 1000); 
                    });
                }
            } else {
                faceOffActive = false;
                els.msg.textContent = "Missed! Control returns to original player.";
                currentTurn = faceOffLeader;
                highlightTurn(currentTurn);
                showStrikeAnimation(() => {
                    els.input.focus();
                    transitionToNextTurn(); 
                });
            }
            return;
        }

        strikes++;

        showStrikeAnimation(() => {
            if (isStealPhase) {
                els.msg.textContent = "STEAL FAILED! Original player wins points.";
                endRound(originalController);
                return;
            }
            if (strikes >= 3) {
                initiateSteal();
            } else {
                els.msg.textContent = `${strikes} STRIKE(S)!`;
                transitionToNextTurn(); 
            }
        });
    }

    function showStrikeAnimation(callback) {
        const contentDiv = els.strikeOverlay.querySelector('.strike-content');
        // CRITICAL FIX: Reset the content to Xs every time a strike is shown
        contentDiv.innerHTML = STRIKE_CONTENT_HTML;
        
        els.strikeOverlay.classList.remove('hidden');
        
        // Re-select because we just overwrote the HTML
        const xs = contentDiv.querySelectorAll('.big-x');
        const displayStrikes = isStealPhase || faceOffActive ? 1 : strikes;
        
        xs.forEach((x, i) => {
            if (i < displayStrikes) x.classList.remove('hidden');
            else x.classList.add('hidden');
        });

        setTimeout(() => {
            els.strikeOverlay.classList.add('hidden');
            if (callback) callback();
        }, 1500);
    }

    async function endRound(winningPlayer) {
        roundActive = false;
        buzzerLocked = true;
        stopTimer();
        highlightTurn(null); 
        els.inputArea.classList.add('hidden');
        
        if (winningPlayer) {
            scores[winningPlayer] += bank;
            els.msg.textContent = `Round Over! Points go to ${teamNames[winningPlayer]}!`;
            playSound('round_end'); 
        }

        els.score1.textContent = scores[1];
        els.score2.textContent = scores[2];

        try {
            const response = await fetch(`survey_showdown/api.php?action=reveal_round&question_id=${currentQuestionId}`);
            const data = await response.json();

            if (data.answers) {
                const answers = data.answers;
                let delay = 0;
                for (let i = totalAnswers - 1; i >= 0; i--) {
                    const card = document.getElementById(`ans-${i}`);
                    if (card && !card.classList.contains('revealed')) {
                        setTimeout(() => {
                            const ansData = answers[i];
                            revealCard(card, ansData.text, ansData.points * multiplier, true);
                        }, delay);
                        delay += 2000; 
                    }
                }
                delay += 1000;
                setTimeout(checkGameEnd, delay);
            }
        } catch (e) {
            console.error("Failed to fetch round answers", e);
            setTimeout(checkGameEnd, 2000);
        }
    }

    function checkGameEnd() {
        if (currentRound < maxRounds) {
            currentRound++;
            els.startBtn.textContent = `Start Round ${currentRound}`;
            els.msg.textContent = "Round Complete. Press Start for next round.";
        } else {
            let winnerName = teamNames[1];
            if (scores[2] > scores[1]) winnerName = teamNames[2];
            else if (scores[1] === scores[2]) winnerName = "Tie Game";

            saveGameScore(winnerName);

            els.boardContainer.innerHTML = `
                <div class="winner-modal">
                    <div class="winner-title">GAME OVER</div>
                    <div class="winner-score">${teamNames[1]}: ${scores[1]}</div>
                    <div class="winner-score">${teamNames[2]}: ${scores[2]}</div>
                    <div class="winner-title" style="font-size: 3rem; margin-top: 20px;">WINNER: ${winnerName}</div>
                    <div style="margin-top:20px;">
                        <button onclick="location.reload()" style="padding: 15px 30px; font-size: 1.5rem; margin-right:10px; cursor: pointer;">Play Again</button>
                    </div>
                </div>
            `;
            els.msg.textContent = "GAME OVER";
            els.startBtn.classList.add('hidden');
        }
    }

    // --- Global Event Listeners ---
    document.addEventListener('keydown', (e) => {
        if (!roundActive || buzzerLocked) return;
        if (e.key.toLowerCase() === 'z') handleBuzzer(1);
        else if (e.key.toLowerCase() === 'm') handleBuzzer(2);
    });

    if (els.touchBtn1 && els.touchBtn2) {
        els.touchBtn1.addEventListener('touchstart', (e) => { e.preventDefault(); handleBuzzer(1); });
        els.touchBtn1.addEventListener('click', (e) => handleBuzzer(1));
        
        els.touchBtn2.addEventListener('touchstart', (e) => { e.preventDefault(); handleBuzzer(2); });
        els.touchBtn2.addEventListener('click', (e) => handleBuzzer(2));
    }

    if (els.submitBtn) els.submitBtn.addEventListener('click', submitAnswer);
    if (els.startBtn) els.startBtn.addEventListener('click', startRound);
    if (els.input) els.input.addEventListener('keypress', (e) => { if (e.key === 'Enter') submitAnswer(); });
    if (els.resetBtn) els.resetBtn.addEventListener('click', () => { if(confirm("Reset game?")) location.reload(); });
});