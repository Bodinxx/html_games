<!-- 
CATEGORY: Games
DESCRIPTION: Survey Showdown is a competitive, team-based trivia game where two groups face off to guess the most popular answers to survey questions. The gameplay faithfully recreates the mechanics of a TV game show, featuring face-off buzzer rounds, a three-strike rule for wrong answers, and the ability for the opposing team to steal points.
TAGS: Trivia, Multiplayer, Party, Old School, Game Show
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Survey Showdown</title>
    <link rel="stylesheet" href="survey_showdown/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* Force styles for mode buttons to override potential caching issues */
        .mode-toggle-container {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 5px;
        }
        .mode-btn {
            flex: 1;
            padding: 15px;
            background: #333;
            border: 2px solid #555;
            color: #aaa;
            cursor: pointer;
            border-radius: 5px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            transition: all 0.2s ease;
        }
        .mode-btn:hover {
            background: #444;
        }
        /* Specific selector to ensure it takes precedence */
        button.mode-btn.selected {
            background-color: #0044cc !important;
            border: 2px solid #ffcc00 !important;
            color: #fff !important;
            box-shadow: 0 0 15px rgba(255, 204, 0, 0.5);
            transform: scale(1.05);
        }
        .mode-icon {
            font-size: 1.5rem;
        }

        /* TIMER STYLES - Forced here to ensure appearance */
        .input-group {
            display: flex;
            align-items: center; /* Vertically align timer, input, and button */
            justify-content: center;
            gap: 10px;
        }
        .timer-display {
            font-family: 'Anton', sans-serif;
            font-size: 2.5rem;
            color: #fff;
            background-color: #d32f2f; /* Red base */
            border: 3px solid #fff;
            border-radius: 50%;
            width: 70px;
            height: 70px;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
            text-shadow: 2px 2px 0 #000;
            transition: transform 0.2s;
            flex-shrink: 0; /* Prevent shrinking on mobile */
        }
        .timer-display.urgent {
            background-color: #ff0000;
            animation: urgent-pulse 0.5s infinite alternate;
        }
        @keyframes urgent-pulse {
            from { transform: scale(1); box-shadow: 0 0 10px #ff0000; }
            to { transform: scale(1.1); box-shadow: 0 0 20px #ffcc00; }
        }
    </style>
</head>
<body>

    <div class="game-container">
        <!-- Header -->
        <header>
            <div class="header-top">
                <h1>SURVEY SHOWDOWN</h1>
                <div class="header-buttons">
                    <button id="toggle-sound-btn" class="secondary-btn">Sound: ON</button>
                    <button id="show-scores-btn" class="secondary-btn">High Scores</button>
                </div>
            </div>
            <div id="round-indicator" class="round-info hidden">ROUND 1 / 4</div>
        </header>

        <!-- Scoreboard -->
        <div class="scoreboard">
            <div class="team team-1">
                <div class="team-name" id="name-1">TEAM 1</div>
                <div class="score" id="score-1">0</div>
                <div class="buzzer-hint" id="hint-1">(Press 'Z')</div>
            </div>
            
            <div class="bank">
                <span>BANK:</span> <span id="bank-score">0</span>
                <div id="multiplier-display" class="multiplier-text hidden">DOUBLED</div>
            </div>

            <div class="team team-2">
                <div class="team-name" id="name-2">TEAM 2</div>
                <div class="score" id="score-2">0</div>
                <div class="buzzer-hint" id="hint-2">(Press 'M')</div>
            </div>
        </div>

        <!-- The Big Board -->
        <div class="board-container" id="main-board-container">
            <h2 id="question-text">Waiting for game start...</h2>
            <div id="answers-board" class="answers-grid"></div>
        </div>

        <!-- Controls / Input -->
        <div class="controls-area">
            <div id="buzzer-overlay" class="hidden">
                <span id="buzzer-winner">TEAM 1 BUZZED!</span>
            </div>
            
            <!-- Message Box Moved Here -->
            <div id="game-message" class="message-box">Welcome! Enter names to start.</div>
            
            <div class="input-group hidden" id="input-area">
                <!-- Timer -->
                <div id="timer-display" class="timer-display"></div>
                
                <input type="text" id="answer-input" placeholder="Type your answer..." autocomplete="off">
                <button id="submit-btn">Submit</button>
            </div>

            <div class="admin-controls">
                <button id="start-btn" class="hidden">Start Game</button>
                <button id="reset-btn">Reset Game</button>
            </div>
        </div>
    </div>

    <!-- Touch Controls (Mobile Buzzers) -->
    <div id="touch-controls" class="touch-buzzer-container hidden">
        <button id="touch-btn-1" class="touch-buzzer buzzer-left">TEAM 1<br>BUZZER</button>
        <button id="touch-btn-2" class="touch-buzzer buzzer-right">TEAM 2<br>BUZZER</button>
    </div>

    <!-- Strike Overlay -->
    <div id="strike-overlay" class="strike-container hidden">
        <div class="strike-content">
            <span class="big-x">X</span>
            <span class="big-x hidden" id="strike-2">X</span>
            <span class="big-x hidden" id="strike-3">X</span>
        </div>
    </div>

    <!-- START MODAL (Name Entry) -->
    <div id="start-modal" class="modal-overlay">
        <div class="modal-content">
            <h2>NEW GAME</h2>
            <div class="form-group">
                <label>Team 1 Name:</label>
                <input type="text" id="input-name-1" placeholder="Team 1" value="Team 1" maxlength="15">
            </div>
            <div class="form-group">
                <label>Team 1 Members:</label>
                <textarea id="input-team1-players" placeholder="Enter names separated by commas (e.g., John, Mary, Steve)" rows="2"></textarea>
            </div>
            
            <div class="form-group">
                <label>Team 2 Name:</label>
                <input type="text" id="input-name-2" placeholder="Team 2" value="Team 2" maxlength="15">
            </div>
            <div class="form-group">
                <label>Team 2 Members:</label>
                <textarea id="input-team2-players" placeholder="Enter names separated by commas (e.g., Bob, Alice, Mike)" rows="2"></textarea>
            </div>
            
            <div class="form-group">
                <label>Control Mode:</label>
                <div class="mode-toggle-container">
                    <button type="button" id="btn-mode-keyboard" class="mode-btn selected">
                        <span class="mode-icon">⌨️</span>
                        <span>Keyboard (Z / M)</span>
                    </button>
                    <button type="button" id="btn-mode-touch" class="mode-btn">
                        <span class="mode-icon">📱</span>
                        <span>Touch / Mobile</span>
                    </button>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button id="options-btn" class="secondary-btn" style="width: 48%; margin-bottom: 10px; margin-right: 2%;">Game Options</button>
                <button id="view-rules-btn" class="secondary-btn" style="width: 48%; margin-bottom: 10px;">View Rules</button>
                <button id="confirm-start-btn" class="primary-btn" style="width: 100%;">START GAME</button>
            </div>
        </div>
    </div>

    <!-- OPTIONS MODAL -->
    <div id="options-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <h2>GAME OPTIONS</h2>
            
            <div class="form-group">
                <label>Game Series ID:</label>
                <input type="text" id="opt-game-series" maxlength="25" placeholder="e.g. CSW2025 (Optional)">
                <small style="color: #aaa; display:block; margin-top:2px;">Used for grouping scores in the leaderboard.</small>
            </div>

            <div class="form-group checkbox-group">
                <label for="opt-save-scores" style="display:inline; margin-right: 10px;">Save Scores to Leaderboard?</label>
                <input type="checkbox" id="opt-save-scores" checked>
            </div>

            <hr style="border: 0; border-top: 1px solid #555; margin: 15px 0;">

            <div class="slider-group">
                <div class="slider-label">
                    <label>Number of Rounds:</label>
                    <span id="disp-round-count" class="slider-value">4</span>
                </div>
                <input type="range" id="opt-round-count" min="3" max="10" value="4">
            </div>

            <div class="slider-group">
                <div class="slider-label">
                    <label>Time to Answer:</label>
                    <span id="disp-answer-time" class="slider-value">10s</span>
                </div>
                <input type="range" id="opt-answer-time" min="5" max="60" value="10">
            </div>

            <div class="slider-group">
                <div class="slider-label">
                    <label>Time to Steal:</label>
                    <span id="disp-steal-time" class="slider-value">20s</span>
                </div>
                <input type="range" id="opt-steal-time" min="10" max="90" value="20">
            </div>

            <div class="slider-group">
                <div class="slider-label">
                    <label>Time Between Turns:</label>
                    <span id="disp-transition-time" class="slider-value">5s</span>
                </div>
                <input type="range" id="opt-transition-time" min="1" max="10" value="5">
            </div>

            <button id="save-options-btn" class="primary-btn" style="margin-top: 20px;">Save & Close</button>
        </div>
    </div>

    <!-- RULES MODAL -->
    <div id="rules-modal" class="modal-overlay hidden">
        <div class="modal-content large-modal">
            <h2>HOW TO PLAY</h2>
            <div class="rules-content">
                <h3>Game Flow</h3>
                <ul>
                    <li><strong>Face-Off:</strong> Buzz in first to guess the top answer. The winner decides to play or pass.</li>
                    <li><strong>The Round:</strong> The controlling team guesses answers. 3 Strikes and control passes to the other team.</li>
                    <li><strong>The Steal:</strong> If the controlling team strikes out, the opponents get ONE guess to steal all points.</li>
                    <li><strong>Winning:</strong> Highest score after the final round wins. (Later rounds have multipliers!)</li>
                </ul>
                
                <h3>Collaboration Rules</h3>
                <ul>
                    <li class="important-rule">During team play, if the team in control engages in any collaboration or receives assistance from other team members (beyond their turn to answer), it will result in a STRIKE.</li>
                    <li class="important-rule">Collaboration is ONLY allowed during the Steal Attempt phase.</li>
                    <li>The host and any observers are strictly prohibited from aiding the playing teams in any way.</li>
                </ul>
            </div>
            <button id="close-rules-btn" class="primary-btn" style="margin-top: 20px;">Back to Setup</button>
        </div>
    </div>

    <!-- HIGH SCORES MODAL -->
    <div id="scores-modal" class="modal-overlay hidden">
        <div class="modal-content large-modal">
            <h2>HALL OF FAME</h2>
            <div class="table-container">
                <table id="scores-table">
                    <thead>
                        <tr>
                            <th>Series</th>
                            <th>Date</th>
                            <th>Winner</th>
                            <th>Score</th>
                            <th>Opponent</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Rows injected by JS -->
                    </tbody>
                </table>
            </div>
            <button id="close-scores-btn" class="secondary-btn">Close</button>
        </div>
    </div>

    <script src="survey_showdown/game.js"></script>
    <a href="http://web-mage.ca/games/"><img src="WebMage-sm.webp" style="position: fixed; right: 16px; bottom: 16px; height: 50px; z-index: 9999; pointer-events: auto;"></a>
</body>
</html>