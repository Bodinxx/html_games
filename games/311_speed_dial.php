<!-- 
CATEGORY: 311 & Customer Access
DESCRIPTION: A modular training platform for Edmonton 311 agents to master municipal knowledge through rapid-fire trivia. Reinforce critical information and workflows across various categories to ensure service excellence.
TAGS: Trivia, 311, Edmonton, Training
-->
<?php
/**
 * 311 SPEED-DIAL: DYNAMIC CATEGORY SCANNER
 * This section scans the ./311_speed_dial directory for any .json files,
 * extracts their metadata, and prepares them for the selection screen.
 */
// Set timezone to Edmonton (MDT/MST)
date_default_timezone_set('America/Edmonton');

$categories = [];
$directory = './311_speed_dial/';

if (is_dir($directory)) {
    if ($handle = @opendir($directory)) {
        while (false !== ($file = readdir($handle))) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $filePath = $directory . $file;
                $jsonContent = @file_get_contents($filePath);
                if ($jsonContent) {
                    $data = json_decode($jsonContent, true);
                    if ($data && isset($data['categoryName'])) {
                        // Get the last modified time from the server filesystem
                        $lastModifiedTimestamp = filemtime($filePath);
                        // Format the date in Mountain Time
                        $formattedDate = date('Y-m-d H:i T', $lastModifiedTimestamp);
                        
                        $categories[] = [
                            'filename' => $file,
                            'name' => $data['categoryName'],
                            'description' => $data['description'] ?? 'No description provided.',
                            'updated' => $formattedDate
                        ];
                    }
                }
            }
        }
        closedir($handle);
    }
}
?>
<!DOCTYPE html>
<html lang="en-CA" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>311 Speed-Dial: Training Suite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; transition: background-color 0.3s ease; }
        .transition-all { transition: all 0.3s ease; }
        .progress-bar { transition: width 0.1s linear; }
        .knowledge-item { border-left: 4px solid #e5e7eb; }
        .knowledge-item.correct { border-left-color: #10b981; }
        .knowledge-item.incorrect { border-left-color: #f43f5e; }
        
        /* Custom scrollbars */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .dark ::-webkit-scrollbar-thumb { background: #4b5563; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gray-100 dark:bg-gray-950 text-gray-900 dark:text-gray-100">

    <div id="game-container" class="max-w-4xl w-full bg-white dark:bg-gray-900 rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-gray-800 my-8 relative">
        
        <!-- Theme Toggle -->
        <button onclick="toggleTheme()" class="absolute top-6 right-6 text-white/80 hover:text-white transition-colors z-10" title="Toggle Theme">
            <svg id="sun-icon" class="w-6 h-6 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M16.243 17.657l.707.707M7.757 6.343l.707.707M12 8a4 4 0 100 8 4 4 0 000-8z"></path></svg>
            <svg id="moon-icon" class="w-6 h-6 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path></svg>
        </button>

        <!-- Header -->
        <div class="bg-blue-600 dark:bg-blue-700 p-4 text-white flex items-center gap-6">
            <img src="./311_speed_dial/311_speed_dial_sm.webp" alt="311 Speed-Dial Logo" class="w-[90px] h-[90px] rounded-xl shadow-lg border border-white/10 flex-shrink-0" onerror="this.style.display='none'">
            <div class="flex flex-col text-left">
                <h1 class="text-3xl font-800 tracking-tight italic uppercase leading-none">311 Speed-Dial: Training</h1>
                <p class="text-blue-100 text-sm mt-1 uppercase font-semibold">Dynamic Edmonton 311 Training Suite</p>
            </div>
        </div>

        <!-- Category Selection Screen -->
        <div id="category-screen" class="p-8">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 text-center">Select Category</h2>
            <div id="category-list" class="space-y-4 max-h-[32rem] overflow-y-auto pr-2">
                <?php if (empty($categories)): ?>
                    <div class="text-center py-8 px-4 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-2xl">
                        <p class="text-gray-500 dark:text-gray-400 italic">No category files found in ./311_speed_dial/</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <button onclick="loadCategory('<?php echo htmlspecialchars($cat['filename']); ?>')" class="w-full text-left p-5 rounded-2xl bg-gray-50 dark:bg-gray-800 border-2 border-gray-100 dark:border-gray-700 hover:border-blue-500 dark:hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-all flex items-center justify-between group">
                            <div class="flex-grow pr-4">
                                <h3 class="font-bold text-gray-800 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                    <span class="mx-2 text-gray-300 dark:text-gray-600 font-normal">::</span>
                                    <em class="text-xs font-normal text-gray-400 dark:text-gray-500 italic">Updated: <?php echo $cat['updated']; ?></em>
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 whitespace-normal"><?php echo htmlspecialchars($cat['description']); ?></p>
                            </div>
                            <svg class="w-6 h-6 text-gray-300 dark:text-gray-600 group-hover:text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="text-center mt-6 text-xs text-gray-400 uppercase tracking-widest font-semibold">End of available modules</div>
        </div>

        <!-- Password Screen -->
        <div id="password-screen" class="hidden p-8 text-center">
            <div class="mb-6 flex justify-center">
                <div class="bg-amber-100 dark:bg-amber-900/30 p-4 rounded-full">
                    <svg class="w-12 h-12 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-2 text-center">Restricted Access</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 text-center">Enter the department password to access this training module.</p>
            <div class="space-y-4 max-w-sm mx-auto">
                <input type="password" id="quiz-password" class="w-full p-4 rounded-xl border-2 border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 text-center font-bold tracking-widest focus:border-blue-500 focus:outline-none transition-all dark:text-white" placeholder="••••••••">
                <p id="password-error" class="text-rose-500 text-sm font-semibold hidden">Incorrect password. Please try again.</p>
                <button onclick="verifyPassword()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-8 rounded-xl transition-all shadow-md">
                    VERIFY ACCESS
                </button>
                <button onclick="backToCategories()" class="inline-block text-sm text-gray-400 font-bold uppercase hover:text-gray-600 dark:hover:text-gray-200">Cancel</button>
            </div>
        </div>

        <!-- Start Screen -->
        <div id="start-screen" class="hidden p-8 text-center">
            <div class="mb-6 flex justify-center">
                <div class="bg-blue-100 dark:bg-blue-900/50 p-4 rounded-full text-blue-600 dark:text-blue-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
            </div>
            <h2 id="selected-category-title" class="text-2xl font-bold text-gray-800 dark:text-white mb-2 text-center">Category Name</h2>
            <p id="selected-category-desc" class="text-gray-600 dark:text-gray-300 mb-8 leading-relaxed max-w-2xl mx-auto text-center">Description goes here.</p>
            <button onclick="startGame()" class="w-full max-w-sm mx-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-8 rounded-xl transition-all transform hover:scale-105 shadow-lg block">
                BEGIN CHALLENGE
            </button><br>
            <button onclick="backToCategories()" class="inline-block mt-4 text-sm text-gray-400 font-bold uppercase hover:text-gray-600 dark:hover:text-gray-200">Change Category</button>
        </div>

        <!-- Quiz Screen -->
        <div id="quiz-screen" class="hidden p-8">
            <div class="flex justify-between items-center mb-6">
                <span id="question-counter" class="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-xs font-bold px-3 py-1 rounded-full uppercase">Question 1 of 15</span>
                <span id="score-display" class="text-blue-600 dark:text-blue-400 font-bold">Score: 0</span>
            </div>

            <div class="w-full bg-gray-200 dark:bg-gray-800 h-2 rounded-full mb-8 overflow-hidden">
                <div id="timer-bar" class="progress-bar bg-blue-500 h-full w-full"></div>
            </div>

            <div id="question-box" class="min-h-[140px] flex items-center justify-center text-center px-4">
                <p id="question-text" class="text-xl font-bold text-gray-800 dark:text-white leading-snug"></p>
            </div>

            <div class="grid grid-cols-2 gap-4 mt-8 max-w-xl mx-auto">
                <button onclick="handleAnswer(true)" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-6 px-4 rounded-2xl transition-all shadow-md transform active:scale-95 uppercase">True</button>
                <button onclick="handleAnswer(false)" class="bg-rose-500 hover:bg-rose-600 text-white font-bold py-6 px-4 rounded-2xl transition-all shadow-md transform active:scale-95 uppercase">False</button>
            </div>
        </div>

        <!-- Feedback Overlay -->
        <div id="feedback" class="hidden fixed inset-0 flex items-center justify-center bg-black/40 dark:bg-black/60 z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 max-w-md w-full text-center shadow-2xl border border-gray-100 dark:border-gray-700">
                <div id="feedback-icon" class="mb-4 flex justify-center"></div>
                <h3 id="feedback-title" class="text-xl font-bold mb-2 text-center"></h3>
                <p id="feedback-msg" class="text-gray-600 dark:text-gray-300 mb-6 text-center"></p>
                <button onclick="closeFeedback()" class="w-full bg-gray-800 dark:bg-gray-700 text-white py-3 rounded-xl font-bold hover:bg-gray-700 dark:hover:bg-gray-600 transition-colors uppercase">Continue</button>
            </div>
        </div>

        <!-- Result Screen -->
        <div id="result-screen" class="hidden p-8">
            <div class="text-center mb-10">
                <h2 class="text-3xl font-extrabold text-gray-800 dark:text-white mb-2 text-center">Review Complete</h2>
                <div class="text-6xl font-black text-blue-600 dark:text-blue-400 mb-2" id="final-score">0%</div>
                <p id="final-rank" class="text-gray-500 dark:text-gray-400 uppercase tracking-widest font-bold mb-4 italic">Calculating...</p>
                <button onclick="location.reload()" class="bg-blue-600 text-white font-bold py-3 px-8 rounded-xl transition-all hover:bg-blue-700 uppercase">Retake Training</button>
            </div>

            <hr class="mb-8 border-gray-100 dark:border-gray-800">
            
            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4 flex items-center justify-center gap-2">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10a7.969 7.969 0 013.5-.804c1.17 0 2.3.269 3.3.754V4.804zM11 4.804a7.968 7.968 0 013.5-.804c1.255 0 2.443.29 3.5.804v10a7.969 7.969 0 00-3.5-.804 7.96 7.96 0 00-3.3.754V4.804z"></path></svg>
                Knowledge Review
            </h3>
            <div id="knowledge-review" class="space-y-4 max-h-[400px] overflow-y-auto pr-2 pb-4">
                <!-- Detailed results injected here -->
            </div>
        </div>
    </div>

    <script>
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        let currentQuestionsData = [];
        let sessionQuestions = [];
        let questionIndex = 0;
        let score = 0;
        let timer;
        let timeLeft = 100;
        let userResponses = [];
        let activeQuizData = null;
        const SESSION_LIMIT = 15;

        function toggleTheme() {
            document.documentElement.classList.toggle('dark');
        }

        function playSound(type) {
            try {
                if (audioCtx.state === 'suspended') audioCtx.resume();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                if (type === 'correct') {
                    osc.frequency.setValueAtTime(523, audioCtx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.1);
                    gain.gain.setValueAtTime(0.05, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.3);
                    osc.start(); osc.stop(audioCtx.currentTime + 0.3);
                } else {
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(220, audioCtx.currentTime);
                    osc.frequency.linearRampToValueAtTime(110, audioCtx.currentTime + 0.2);
                    gain.gain.setValueAtTime(0.05, audioCtx.currentTime);
                    gain.gain.linearRampToValueAtTime(0.01, audioCtx.currentTime + 0.2);
                    osc.start(); osc.stop(audioCtx.currentTime + 0.2);
                }
            } catch(e) {}
        }

        async function loadCategory(filename) {
            const filePath = `./311_speed_dial/${filename}`;
            try {
                const response = await fetch(filePath);
                if (!response.ok) throw new Error('Not found');
                activeQuizData = await response.json();
                
                // Transition to password screen
                document.getElementById('category-screen').classList.add('hidden');
                document.getElementById('password-screen').classList.remove('hidden');
                document.getElementById('quiz-password').value = '';
                document.getElementById('password-error').classList.add('hidden');
                document.getElementById('quiz-password').focus();
            } catch (error) {
                console.error(error);
                alert("Error loading category file.");
            }
        }

        function verifyPassword() {
            const input = document.getElementById('quiz-password').value;
            if (activeQuizData && input === activeQuizData.password) {
                currentQuestionsData = activeQuizData.questions;
                document.getElementById('selected-category-title').innerText = activeQuizData.categoryName;
                document.getElementById('selected-category-desc').innerText = activeQuizData.description;
                
                document.getElementById('password-screen').classList.add('hidden');
                document.getElementById('start-screen').classList.remove('hidden');
            } else {
                document.getElementById('password-error').classList.remove('hidden');
            }
        }

        function backToCategories() {
            document.getElementById('start-screen').classList.add('hidden');
            document.getElementById('password-screen').classList.add('hidden');
            document.getElementById('category-screen').classList.remove('hidden');
            activeQuizData = null;
        }

        function startGame() {
            sessionQuestions = [...currentQuestionsData]
                .sort(() => 0.5 - Math.random())
                .slice(0, Math.min(SESSION_LIMIT, currentQuestionsData.length));
            document.getElementById('start-screen').classList.add('hidden');
            document.getElementById('quiz-screen').classList.remove('hidden');
            showQuestion();
        }

        function showQuestion() {
            if (questionIndex >= sessionQuestions.length) {
                endGame();
                return;
            }
            const q = sessionQuestions[questionIndex];
            document.getElementById('question-text').innerText = q.q;
            document.getElementById('question-counter').innerText = `Question ${questionIndex + 1} of ${sessionQuestions.length}`;
            timeLeft = 100;
            updateTimerBar();
            clearInterval(timer);
            timer = setInterval(() => {
                timeLeft -= 1.25; 
                updateTimerBar();
                if (timeLeft <= 0) handleAnswer(null);
            }, 100);
        }

        function updateTimerBar() {
            document.getElementById('timer-bar').style.width = Math.max(0, timeLeft) + '%';
        }

        function handleAnswer(choice) {
            clearInterval(timer);
            const q = sessionQuestions[questionIndex];
            const isCorrect = (choice === q.a);
            if (isCorrect) { score++; playSound('correct'); } else { playSound('incorrect'); }
            userResponses.push({
                question: q.q,
                correct: isCorrect,
                userAnswer: choice === null ? "Timed Out" : (choice ? "True" : "False"),
                actualAnswer: q.a ? "True" : "False",
                reason: q.info
            });
            document.getElementById('score-display').innerText = `Score: ${score}`;
            showFeedback(isCorrect, q.info);
        }

        function showFeedback(correct, info) {
            const overlay = document.getElementById('feedback');
            const icon = document.getElementById('feedback-icon');
            const title = document.getElementById('feedback-title');
            const msg = document.getElementById('feedback-msg');
            if (correct) {
                icon.innerHTML = '<div class="bg-emerald-100 dark:bg-emerald-900/40 p-4 rounded-full text-emerald-600 dark:text-emerald-400"><svg class="w-12 h-12" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg></div>';
                title.innerText = "Correct!";
                title.className = "text-xl font-bold mb-2 text-emerald-600 dark:text-emerald-400 text-center";
            } else {
                icon.innerHTML = '<div class="bg-rose-100 dark:bg-rose-900/40 p-4 rounded-full text-rose-600 dark:text-rose-400"><svg class="w-12 h-12" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg></div>';
                title.innerText = "Incorrect";
                title.className = "text-xl font-bold mb-2 text-rose-600 dark:text-rose-400 text-center";
            }
            msg.innerText = info;
            overlay.classList.remove('hidden');
        }

        function closeFeedback() {
            document.getElementById('feedback').classList.add('hidden');
            questionIndex++;
            showQuestion();
        }

        function endGame() {
            document.getElementById('quiz-screen').classList.add('hidden');
            document.getElementById('result-screen').classList.remove('hidden');
            const total = sessionQuestions.length;
            const percent = Math.round((score / total) * 100);
            document.getElementById('final-score').innerText = percent + "%";
            let rank = "Apprentice";
            if (percent >= 90) rank = "Master Agent";
            else if (percent >= 70) rank = "Senior Specialist";
            else if (percent >= 50) rank = "Associate";
            document.getElementById('final-rank').innerText = rank;
            const reviewContainer = document.getElementById('knowledge-review');
            reviewContainer.innerHTML = '';
            userResponses.forEach(resp => {
                const div = document.createElement('div');
                div.className = `knowledge-item p-4 rounded-r-xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 ${resp.correct ? 'correct' : 'incorrect'}`;
                div.innerHTML = `
                    <p class="font-bold text-gray-800 dark:text-white mb-1">${resp.question}</p>
                    <div class="flex gap-4 text-[10px] uppercase font-bold mb-2">
                        <span class="text-gray-400">Your Answer: <b class="${resp.correct ? 'text-emerald-600' : 'text-rose-600'}">${resp.userAnswer}</b></span>
                        <span class="text-gray-400">Correct: <b class="text-blue-600 dark:text-blue-400">${resp.actualAnswer}</b></span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed italic">${resp.reason}</p>
                `;
                reviewContainer.appendChild(div);
            });
        }
        
        // Listen for Enter key on password input
        document.getElementById('quiz-password').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                verifyPassword();
            }
        });
    </script>
    <a href="http://web-mage.ca/games/"><img src="WebMage-sm.webp" alt="WebMage Logo" style="position: fixed; right: 16px; bottom: 16px; height: 50px; z-index: 9999; pointer-events: auto;"></a>
</body>
</html>