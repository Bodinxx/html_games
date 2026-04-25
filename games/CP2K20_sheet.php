<!-- 
CATEGORY: z_In Development
DESCRIPTION: A Single-File PHP Application for Cyberpunk 2020 Character Management
-->
<?php
// CP2K20_sheet.php

// Turn off display errors so they don't break JSON output
ini_set('display_errors', 0);
// Ensure we handle the output buffer to prevent whitespace issues
if (ob_get_level()) ob_end_clean();

$dataDir = 'CP2K20_sheet';

// --- VERSION CONTROL ---
$appVersion = '0.0.0'; // Fallback
if (file_exists("$dataDir/game_data.json")) {
    $metaData = json_decode(file_get_contents("$dataDir/game_data.json"), true);
    $appVersion = $metaData['version'] ?? 'Unknown';
}

// --- BACKEND: API HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start(); // Start buffer to catch unwanted output
    header('Content-Type: application/json');
    
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    $action = $input['action'] ?? '';

    // Ensure directory exists with correct permissions
    if (!is_dir($dataDir)) {
        if (!mkdir($dataDir, 0755, true)) {
            echo json_encode(['status'=>'error', 'msg'=>'Failed to create directory. Check permissions.']);
            exit;
        }
    }
    // Ensure images directory exists
    if (!is_dir("$dataDir/imgs")) {
        mkdir("$dataDir/imgs", 0755, true);
    }

    if ($action === 'save_character') {
        $charName = preg_replace('/[^a-zA-Z0-9_-]/', '', $input['filename']);
        if (!$charName) { echo json_encode(['status'=>'error', 'msg'=>'Invalid Filename']); exit; }
        $filePath = "$dataDir/$charName.json";
        
        // Save character data
        if (file_put_contents($filePath, json_encode($input['data'], JSON_PRETTY_PRINT))) {
            echo json_encode(['status'=>'success', 'msg'=>"Saved $charName"]);
        } else {
            echo json_encode(['status'=>'error', 'msg'=>'Write permission denied. Check folder permissions (755).']);
        }
        exit;
    }

    if ($action === 'load_character') {
        $charName = preg_replace('/[^a-zA-Z0-9_-]/', '', $input['filename']);
        $filePath = "$dataDir/$charName.json";
        if (file_exists($filePath)) {
            echo file_get_contents($filePath);
        } else {
            echo json_encode(['status'=>'error', 'msg'=>'File not found']);
        }
        exit;
    }
    
    if ($action === 'list_characters') {
        $files = glob("$dataDir/*.json");
        $chars = [];
        $ignored = ['weapons.json', 'armour.json', 'cyberware.json', 'gear.json', 'game_data.json', 'Roles.json', 'Skills.json'];
        
        if ($files) {
            foreach ($files as $f) {
                $base = basename($f);
                if (!in_array($base, $ignored)) {
                    $chars[] = str_replace('.json', '', $base);
                }
            }
        }
        echo json_encode(['characters' => $chars]);
        exit;
    }
    
    // If no matching action
    echo json_encode(['status'=>'error', 'msg'=>'Invalid Action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CP2020 DataTerm</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* CYBERPUNK AESTHETIC CSS */
        @import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap');
        
        :root {
            --term-bg: #050505;
            --term-scan: rgba(0, 255, 65, 0.05);
            --term-ui: #ff3333; /* Militech Red */
            --term-ui-dim: #551111;
            --term-accent: #00e5ff; /* Netwatch Blue */
            --term-text: #ff9999;
        }

        body {
            background-color: var(--term-bg);
            color: var(--term-text);
            font-family: 'Share Tech Mono', monospace;
            overflow-x: hidden;
            background-image: linear-gradient(0deg, transparent 24%, rgba(255, 50, 50, .05) 25%, rgba(255, 50, 50, .05) 26%, transparent 27%, transparent 74%, rgba(255, 50, 50, .05) 75%, rgba(255, 50, 50, .05) 76%, transparent 77%, transparent), linear-gradient(90deg, transparent 24%, rgba(255, 50, 50, .05) 25%, rgba(255, 50, 50, .05) 26%, transparent 27%, transparent 74%, rgba(255, 50, 50, .05) 75%, rgba(255, 50, 50, .05) 76%, transparent 77%, transparent);
            background-size: 50px 50px;
        }

        /* Scanline effect */
        body::before {
            content: " ";
            display: block;
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.06), rgba(0, 255, 0, 0.02), rgba(0, 0, 255, 0.06));
            z-index: 2;
            background-size: 100% 2px, 3px 100%;
            pointer-events: none;
        }

        /* UI Elements */
        .cp-panel {
            border: 1px solid var(--term-ui);
            background: rgba(10, 0, 0, 0.8);
            box-shadow: 0 0 10px var(--term-ui-dim);
            backdrop-filter: blur(4px);
        }
        
        .cp-header {
            background: var(--term-ui);
            color: black;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4px 8px;
            clip-path: polygon(0 0, 100% 0, 95% 100%, 0% 100%);
        }

        .cp-input {
            background: #1a0505;
            border: 1px solid var(--term-ui-dim);
            color: var(--term-accent);
            padding: 4px;
            width: 100%;
        }
        .cp-input:focus {
            outline: none;
            border-color: var(--term-accent);
            box-shadow: 0 0 5px var(--term-accent);
        }

        .cp-btn {
            background: var(--term-ui-dim);
            border: 1px solid var(--term-ui);
            color: var(--term-text);
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
        }
        .cp-btn:hover {
            background: var(--term-ui);
            color: black;
            box-shadow: 0 0 15px var(--term-ui);
        }
        
        .cp-stat-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 1px solid var(--term-ui-dim);
            padding: 5px;
        }
        .cp-stat-val {
            font-size: 1.5rem;
            color: var(--term-accent);
        }
        
        /* Shop Images */
        .shop-item-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border: 1px solid var(--term-ui-dim);
            background: #000;
        }
        
        /* Tree Menu */
        .tree-root { font-weight: bold; color: var(--term-ui); cursor: pointer; margin-top: 8px; }
        .tree-l1 { margin-left: 10px; color: #ccc; cursor: pointer; border-left: 1px solid #333; padding-left: 5px; }
        .tree-l1:hover { color: var(--term-accent); border-left-color: var(--term-accent); }
        .tree-l2 { margin-left: 20px; color: #888; font-size: 0.85em; cursor: pointer; }
        .tree-l2:hover { color: white; }
        .tree-active { color: var(--term-accent) !important; text-shadow: 0 0 5px var(--term-accent); }

        /* Startup Modal */
        #startup-modal {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Tab System */
        .tab-btn {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            opacity: 0.6;
        }
        .tab-btn.active {
            border-bottom: 2px solid var(--term-accent);
            opacity: 1;
            color: var(--term-accent);
            text-shadow: 0 0 8px var(--term-accent);
        }

        /* Scrollbars */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #111; }
        ::-webkit-scrollbar-thumb { background: var(--term-ui); }

        .hidden { display: none; }
        .glitch-text:hover {
            animation: glitch 0.3s cubic-bezier(.25, .46, .45, .94) both infinite;
        }
        @keyframes glitch {
            0% { transform: translate(0) }
            20% { transform: translate(-2px, 2px) }
            40% { transform: translate(-2px, -2px) }
            60% { transform: translate(2px, 2px) }
            80% { transform: translate(2px, -2px) }
            100% { transform: translate(0) }
        }
    </style>
</head>
<body class="h-screen flex flex-col p-4 max-w-7xl mx-auto">

    <!-- STARTUP MODAL -->
    <div id="startup-modal">
        <div class="cp-panel p-8 w-1/3 text-center border-2 border-red-600 shadow-2xl">
            <h1 class="text-4xl font-bold text-red-500 mb-2 glitch-text">NETWATCH</h1>
            <div class="text-sm text-gray-500 mb-8 tracking-widest">SECURE DATA TERMINAL LOGIN</div>
            
            <div id="resume-container" class="mb-4 hidden">
                <button onclick="resumeLastChar()" class="cp-btn w-full py-4 text-xl font-bold mb-2 border-cyan-500 text-cyan-400">
                    RESUME SESSION: <br>
                    <span id="last-char-name" class="text-white text-base">Unknown</span>
                </button>
            </div>

            <div class="space-y-3">
                <a href="CP2K20_sheet/character_builder.php" class="cp-btn w-full py-3 block text-center bg-red-900/20 hover:bg-red-900/50">
                    > INITIATE NEW SUBJECT
                </a>
                <button onclick="closeModal()" class="cp-btn w-full py-3 text-gray-400">
                    > ACCESS ARCHIVES (LOAD)
                </button>
            </div>
            
            <div class="mt-8 text-xs text-gray-600">
                v<?php echo $appVersion; ?> // CONNECTION SECURE
            </div>
        </div>
    </div>

    <!-- TOP BAR -->
    <header class="flex justify-between items-center mb-6 cp-panel p-4">
        <div>
            <h1 class="text-3xl font-bold tracking-widest text-red-500 glitch-text">CYBERPUNK <span class="text-cyan-400">2020</span></h1>
            <div class="text-xs text-gray-500">USER: <span id="user-display">UNREGISTERED</span> // NETWATCH PROTOCOL v<?php echo $appVersion; ?></div>
        </div>
        <div class="flex gap-4 items-center">
            <select id="char-select" class="cp-input w-48" onchange="loadCharacter(this.value)">
                <option value="">-- NEW FILE --</option>
            </select>
            <button onclick="saveCharacter()" class="cp-btn px-4 py-2">SAVE [DISK]</button>
            <div id="save-status" class="text-xs text-green-500"></div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <div class="flex-1 flex gap-4 overflow-hidden">
        
        <!-- LEFT COLUMN: STATS & INFO -->
        <div class="w-1/3 flex flex-col gap-4 overflow-y-auto pr-2">
            
            <!-- Identity -->
            <div class="cp-panel p-4">
                <div class="cp-header mb-2">IDENTITY</div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <label>HANDLE</label>
                        <input type="text" id="char-handle" class="cp-input" oninput="markDirty()">
                    </div>
                    <div>
                        <label>ROLE</label>
                        <select id="char-role" class="cp-input" onchange="applyRole(); markDirty()">
                            <option value="Solo">Solo</option>
                            <option value="Rockerboy">Rockerboy</option>
                            <option value="Netrunner">Netrunner</option>
                            <option value="Techie">Techie</option>
                            <option value="Medtech">Medtech</option>
                            <option value="Media">Media</option>
                            <option value="Cop">Cop</option>
                            <option value="Corporate">Corporate</option>
                            <option value="Fixer">Fixer</option>
                            <option value="Nomad">Nomad</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="cp-panel p-4">
                <div class="cp-header mb-2">STATISTICS</div>
                <div class="grid grid-cols-3 gap-2">
                    <!-- Stat Template -->
                    <div class="cp-stat-box">
                        <span class="text-xs text-gray-400">INT</span>
                        <input type="number" id="stat-int" value="5" class="cp-input text-center text-xl font-bold" onchange="recalcDerived(); markDirty()">
                    </div>
                    <div class="cp-stat-box">
                        <span class="text-xs text-gray-400">REF</span>
                        <input type="number" id="stat-ref" value="5" class="cp-input text-center text-xl font-bold" onchange="recalcDerived(); markDirty()">
                    </div>
                    <div class="cp-stat-box">
                        <span class="text-xs text-gray-400">TECH</span>
                        <input type="number" id="stat-tech" value="5" class="cp-input text-center text-xl font-bold" onchange="recalcDerived(); markDirty()">
                    </div>
                    <div class="cp-stat-box">
                        <span class="text-xs text-gray-400">COOL</span>
                        <input type="number" id="stat-cool" value="5" class="cp-input text-center text-xl font-bold" onchange="recalcDerived(); markDirty()">
                    </div>
                    <div class="cp-stat-box">
                        <span class="text-xs text-gray-400">ATTR</span>
                        <input type="number" id="stat-attr" value="5" class="cp-input text-center text-xl font-bold" onchange="recalcDerived(); markDirty()">
                    </div>
                    <div class="cp-stat-box">
                        <span class="text-xs text-gray-400">LUCK</span>
                        <input type="number" id="stat-luck" value="5" class="cp-input text-center text-xl font-bold" onchange="recalcDerived(); markDirty()">
                    </div>
                    <div class="cp-stat-box">
                        <span class="text-xs text-gray-400">MA</span>
                        <input type="number" id="stat-ma" value="5" class="cp-input text-center text-xl font-bold" onchange="recalcDerived(); markDirty()">
                    </div>
                    <div class="cp-stat-box">
                        <span class="text-xs text-gray-400">BODY</span>
                        <input type="number" id="stat-body" value="5" class="cp-input text-center text-xl font-bold" onchange="recalcDerived(); markDirty()">
                    </div>
                    <div class="cp-stat-box">
                        <span class="text-xs text-gray-400">EMP</span>
                        <input type="number" id="stat-emp" value="5" class="cp-input text-center text-xl font-bold" onchange="recalcDerived(); markDirty()">
                    </div>
                </div>
            </div>

            <!-- Derived Stats -->
            <div class="cp-panel p-4">
                <div class="cp-header mb-2">DERIVED DATA</div>
                <div class="grid grid-cols-2 gap-y-2 gap-x-4 text-sm">
                    <div class="flex justify-between"><span>RUN:</span> <span id="val-run" class="text-cyan-400">0</span></div>
                    <div class="flex justify-between"><span>LEAP:</span> <span id="val-leap" class="text-cyan-400">0</span></div>
                    <div class="flex justify-between"><span>LIFT (kg):</span> <span id="val-lift" class="text-cyan-400">0</span></div>
                    <div class="flex justify-between"><span>CARRY (kg):</span> <span id="val-carry" class="text-cyan-400">0</span></div>
                    <div class="flex justify-between border-t border-red-900 mt-2 pt-2"><span>BTM:</span> <span id="val-btm" class="text-red-500 font-bold">-0</span></div>
                    <div class="flex justify-between border-t border-red-900 mt-2 pt-2"><span>SAVE:</span> <span id="val-save" class="text-red-500 font-bold">5</span></div>
                </div>
            </div>

            <!-- Humanity & Cash -->
            <div class="cp-panel p-4">
                <div class="cp-header mb-2">STATUS</div>
                <div class="mb-4">
                    <div class="flex justify-between mb-1 text-xs">
                        <span>HUMANITY</span>
                        <span id="humanity-disp">50 / 50</span>
                    </div>
                    <div class="w-full bg-gray-900 h-2 border border-red-900">
                        <div id="humanity-bar" class="bg-cyan-500 h-full" style="width: 100%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center">
                        <span class="text-yellow-400 font-bold">EURODOLLARS</span>
                        <input type="number" id="char-cash" class="cp-input w-24 text-right text-yellow-400" value="1000" onchange="markDirty()">
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN: TABS (Skills, Gear, Cyber, Shop) -->
        <div class="w-2/3 flex flex-col cp-panel">
            
            <!-- Navigation -->
            <div class="flex border-b border-red-800 bg-black/50">
                <div class="tab-btn active" onclick="switchTab('skills')">SKILLS</div>
                <div class="tab-btn" onclick="switchTab('cyber')">CYBERWARE</div>
                <div class="tab-btn" onclick="switchTab('gear')">GEAR & WEAPONS</div>
                <div class="tab-btn" onclick="switchTab('shop')">DATASTORE (SHOP)</div>
            </div>

            <!-- Tab Content Area -->
            <div class="flex-1 overflow-y-auto p-4 relative">
                
                <!-- SKILLS TAB -->
                <div id="tab-skills">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl text-cyan-400">SKILL CHIP SOCKETS</h2>
                        <button onclick="addSkill()" class="cp-btn px-2 py-1 text-xs">+ ADD SKILL</button>
                    </div>
                    <div id="skills-list" class="space-y-2">
                        <!-- Skills injected via JS -->
                    </div>
                </div>

                <!-- CYBERWARE TAB -->
                <div id="tab-cyber" class="hidden">
                    <h2 class="text-xl text-cyan-400 mb-4">INSTALLED CYBERNETICS</h2>
                    <table class="w-full text-left text-sm">
                        <thead class="text-red-500 border-b border-red-900">
                            <tr><th>ITEM</th><th>HL COST</th><th>NOTES</th><th>ACTION</th></tr>
                        </thead>
                        <tbody id="cyber-list">
                            <!-- Injected JS -->
                        </tbody>
                    </table>
                    <div class="mt-4 text-xs text-gray-500 border-t border-gray-800 pt-2">
                        * Installing Cyberware automatically deducts Humanity Points (HL). If HL drops below limits, EMP is reduced.
                    </div>
                </div>

                <!-- GEAR TAB -->
                <div id="tab-gear" class="hidden">
                    <h2 class="text-xl text-cyan-400 mb-4">INVENTORY</h2>
                    
                    <div class="mb-4 p-2 bg-red-900/20 border border-red-900/50 flex justify-between text-sm">
                        <span>CURRENT LOAD: <span id="current-load" class="text-white">0</span> kg</span>
                        <span>ENCUMBRANCE PENALTY: <span id="enc-pen" class="text-red-500">0</span></span>
                    </div>

                    <h3 class="text-md text-white border-b border-gray-700 mt-4">WEAPONS</h3>
                    <div id="weapons-list" class="space-y-2 mt-2"></div>

                    <h3 class="text-md text-white border-b border-gray-700 mt-6">GEAR & ARMOUR</h3>
                    <div id="gear-list" class="space-y-2 mt-2"></div>
                </div>

                <!-- SHOP TAB -->
                <div id="tab-shop" class="hidden">
                    <div class="flex gap-4 h-full">
                        <!-- Dynamic Tree Menu -->
                        <div class="w-1/3 border-r border-red-900 pr-2 overflow-y-auto custom-scroll">
                            <h3 class="text-red-500 mb-2 border-b border-red-900 pb-1">CATALOG TREE</h3>
                            <div id="shop-nav" class="text-sm select-none">
                                <!-- Generated by JS -->
                            </div>
                        </div>
                        <!-- Items -->
                        <div class="w-2/3 overflow-y-auto custom-scroll">
                            <div id="shop-content" class="grid grid-cols-1 gap-2">
                                <div class="p-4 text-gray-500 text-center mt-10">SELECT A CATEGORY TO BROWSE</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- DICE ROLLER (Footer of Right Panel) -->
            <div class="border-t border-red-800 p-4 bg-black">
                <div class="flex justify-between items-center">
                    <div class="text-sm">
                        <span class="text-red-500">LAST ROLL:</span> 
                        <span id="roll-result" class="text-2xl font-bold ml-2 text-white">--</span>
                        <div id="roll-detail" class="text-xs text-gray-500">Waiting for input...</div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="rollDice(10)" class="cp-btn px-4 py-2">D10</button>
                        <button onclick="rollDice(6)" class="cp-btn px-4 py-2">D6</button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        // --- DATA STRUCTURES ---
        let character = {
            handle: "",
            role: "Solo",
            stats: { int:5, ref:5, tech:5, cool:5, attr:5, luck:5, ma:5, body:5, emp:5 },
            skills: [], 
            cyberware: [],
            inventory: [], 
            cash: 1000,
            humanityCurrent: 50
        };

        let gameData = {}; // Loaded from JSON
        let autoSaveTimer = null;

        // --- INITIALIZATION ---
        window.onload = async () => {
            // Check for last character in Cookies/LocalStorage
            checkLastChar();

            const categories = ['weapons', 'armour', 'cyberware', 'gear'];
            gameData = {}; 

            try {
                await Promise.all(categories.map(async cat => {
                    try {
                        const res = await fetch(`CP2K20_sheet/${cat}.json?v=` + new Date().getTime());
                        if (!res.ok) throw new Error(`HTTP ${res.status}`);
                        gameData[cat] = await res.json();
                    } catch (err) {
                        console.warn(`Could not load ${cat}.json:`, err);
                        gameData[cat] = {}; 
                    }
                }));
                
                // Build dynamic menu after data load
                buildShopMenu();

            } catch (e) {
                console.error("System Error loading data", e);
                alert("SYSTEM ERROR: Database Connection Failed.");
            }

            if(character.skills.length === 0) addDefaultSkills();
            refreshCharList();
            updateUI();
        };

        function checkLastChar() {
            const last = localStorage.getItem('cp2020_last_char');
            if (last) {
                document.getElementById('last-char-name').innerText = last;
                document.getElementById('resume-container').classList.remove('hidden');
            }
        }

        function resumeLastChar() {
            const last = localStorage.getItem('cp2020_last_char');
            if(last) {
                loadCharacter(last);
                closeModal();
            }
        }

        function closeModal() {
            document.getElementById('startup-modal').style.display = 'none';
        }

        function addDefaultSkills() {
            const defaults = [
                { name: "Athletics", stat: "ref", val: 0 },
                { name: "Brawling", stat: "ref", val: 0 },
                { name: "Handgun", stat: "ref", val: 0 },
                { name: "Notice/Awareness", stat: "int", val: 0 },
                { name: "Persuasion", stat: "cool", val: 0 },
                { name: "Stealth", stat: "ref", val: 0 },
            ];
            character.skills = defaults;
        }

        // --- CORE LOGIC ---

        function recalcDerived() {
            ['int','ref','tech','cool','attr','luck','ma','body','emp'].forEach(s => {
                const el = document.getElementById('stat-'+s);
                if(el) character.stats[s] = parseInt(el.value) || 0;
            });
            const s = character.stats;

            const run = s.ma * 3;
            const leap = Math.floor(run / 4);
            document.getElementById('val-run').innerText = run + "m";
            document.getElementById('val-leap').innerText = leap + "m";

            const carry = s.body * 10;
            const lift = s.body * 40;
            document.getElementById('val-carry').innerText = carry;
            document.getElementById('val-lift').innerText = lift;

            let btm = 0;
            if(s.body <= 2) btm = 0;
            else if(s.body <= 4) btm = -1;
            else if(s.body <= 7) btm = -2;
            else if(s.body <= 9) btm = -3;
            else btm = -4; 
            document.getElementById('val-btm').innerText = btm;
            document.getElementById('val-save').innerText = s.body;

            const maxHumanity = s.emp * 10;
            let totalHL = 0;
            character.cyberware.forEach(cw => totalHL += (cw.hl || 0));
            character.humanityCurrent = maxHumanity - totalHL;
            const effectiveEmp = Math.floor(character.humanityCurrent / 10);
            
            const per = Math.max(0, (character.humanityCurrent / maxHumanity) * 100);
            document.getElementById('humanity-bar').style.width = per + "%";
            document.getElementById('humanity-disp').innerText = `${character.humanityCurrent} / ${maxHumanity} (EFF: ${effectiveEmp})`;
            
            calcEncumbrance();
        }

        function calcEncumbrance() {
            let load = 0;
            character.inventory.forEach(i => {
                if(i.equipped || i.type === 'weapon') { 
                    load += (i.weight || 0);
                }
            });
            document.getElementById('current-load').innerText = load.toFixed(1);
            
            const carryMax = character.stats.body * 10;
            let pen = 0;
            if (load > carryMax) pen = -2; 
            if (load > carryMax * 2) pen = -4;
            document.getElementById('enc-pen').innerText = pen;
        }

        function updateUI() {
            Object.keys(character.stats).forEach(k => {
                document.getElementById('stat-'+k).value = character.stats[k];
            });
            document.getElementById('char-handle').value = character.handle;
            document.getElementById('char-role').value = character.role;
            document.getElementById('char-cash').value = character.cash;
            document.getElementById('user-display').innerText = character.handle || "UNREGISTERED";

            recalcDerived();
            renderSkills();
            renderInventory();
            renderCyberware();
        }

        // --- SKILLS ---
        function renderSkills() {
            const list = document.getElementById('skills-list');
            list.innerHTML = "";
            character.skills.forEach((sk, idx) => {
                const statVal = character.stats[sk.stat] || 0;
                const total = statVal + sk.val;
                
                list.innerHTML += `
                    <div class="flex items-center bg-gray-900/50 p-2 border border-gray-800">
                        <div class="w-1/3 font-bold text-gray-300 text-sm">${sk.name} <span class="text-xs text-gray-600">(${sk.stat.toUpperCase()})</span></div>
                        <div class="w-1/6">
                            <input type="number" class="cp-input w-12 text-center" value="${sk.val}" onchange="updateSkill(${idx}, this.value)">
                        </div>
                        <div class="w-1/6 text-center text-cyan-400 font-bold text-lg">
                            ${total}
                        </div>
                        <div class="w-1/3 text-right">
                            <button onclick="rollSkill('${sk.name}', ${total})" class="cp-btn px-3 py-1 text-xs">ROLL</button>
                            <button onclick="removeSkill(${idx})" class="text-red-900 hover:text-red-500 ml-2">X</button>
                        </div>
                    </div>
                `;
            });
        }

        function updateSkill(idx, val) {
            character.skills[idx].val = parseInt(val);
            renderSkills();
            markDirty();
        }

        function addSkill() {
            const name = prompt("Skill Name:");
            if(name) {
                character.skills.push({ name, stat: "ref", val: 0 });
                renderSkills();
                markDirty();
            }
        }

        function removeSkill(idx) {
            if(confirm("Delete skill?")) {
                character.skills.splice(idx, 1);
                renderSkills();
                markDirty();
            }
        }

        // --- DICE ROLLER ---
        function rollDice(sides = 10) {
            let res = Math.floor(Math.random() * sides) + 1;
            let output = res;
            let expl = "";

            if (sides === 10) {
                if (res === 10) {
                    let next = Math.floor(Math.random() * 10) + 1;
                    output += next;
                    expl = `CRIT! (10 + ${next})`;
                } else if (res === 1) {
                    expl = "FUMBLE CHECK!";
                }
            }
            displayRoll(output, `D${sides} Raw Roll ${expl ? '- ' + expl : ''}`);
        }

        function rollSkill(name, base) {
            let d10 = Math.floor(Math.random() * 10) + 1;
            let total = base + d10;
            let msg = `Base ${base} + Roll ${d10}`;

            if(d10 === 10) {
                let crit = Math.floor(Math.random() * 10) + 1;
                total += crit;
                msg += ` + Crit ${crit}`;
            } else if (d10 === 1) {
                msg += ` (FUMBLE!)`;
            }

            displayRoll(total, `${name} Check`);
        }

        function displayRoll(res, detail) {
            const rEl = document.getElementById('roll-result');
            const dEl = document.getElementById('roll-detail');
            rEl.innerText = Math.floor(Math.random()*20);
            setTimeout(() => rEl.innerText = res, 100);
            dEl.innerText = detail;
        }

        // --- SHOP & INVENTORY (REFACTORED FOR NESTED JSON) ---
        
        // 1. Build the Tree Menu
        function buildShopMenu() {
            const nav = document.getElementById('shop-nav');
            nav.innerHTML = "";
            
            Object.keys(gameData).forEach(fileKey => {
                // Top Level (The File) e.g., "Weapons"
                const cleanName = fileKey.toUpperCase();
                const div = document.createElement('div');
                div.innerHTML = `> ${cleanName}`;
                div.className = "tree-root";
                div.onclick = (e) => {
                     // Toggle or simply render ALL for this file
                     toggleActive(e.target);
                     renderShop(fileKey); 
                };
                nav.appendChild(div);

                // Level 1: Sub-Categories e.g., "Handguns"
                const fileData = gameData[fileKey];
                if(typeof fileData === 'object' && !Array.isArray(fileData)) {
                    Object.keys(fileData).forEach(subKey => {
                        const subDiv = document.createElement('div');
                        subDiv.innerText = subKey;
                        subDiv.className = "tree-l1";
                        subDiv.onclick = (e) => {
                            e.stopPropagation(); // prevent triggering parent
                            toggleActive(e.target);
                            renderShop(fileKey, subKey);
                        };
                        nav.appendChild(subDiv);

                        // Level 2: Sub-Sub e.g., "Light Pistols"
                        const subData = fileData[subKey];
                        if(typeof subData === 'object' && !Array.isArray(subData)) {
                            Object.keys(subData).forEach(leafKey => {
                                const leafDiv = document.createElement('div');
                                leafDiv.innerText = leafKey;
                                leafDiv.className = "tree-l2";
                                leafDiv.onclick = (e) => {
                                    e.stopPropagation();
                                    toggleActive(e.target);
                                    renderShop(fileKey, subKey, leafKey);
                                };
                                nav.appendChild(leafDiv);
                            });
                        }
                    });
                }
            });
        }

        function toggleActive(el) {
            document.querySelectorAll('.tree-active').forEach(e => e.classList.remove('tree-active'));
            el.classList.add('tree-active');
        }

        // 2. Render Shop Items Recursive
        function renderShop(file, sub1 = null, sub2 = null) {
            const container = document.getElementById('shop-content');
            container.innerHTML = "";
            
            let data = gameData[file];
            if(!data) return;

            // Header
            let title = file.toUpperCase();
            if(sub1) title += " > " + sub1;
            if(sub2) title += " > " + sub2;
            container.innerHTML += `<div class="text-xl text-cyan-500 border-b border-cyan-900 mb-4 pb-1">${title}</div>`;

            // Collect all items based on depth
            let itemsToRender = [];

            if (sub1 && sub2) {
                // Specific Leaf
                if(data[sub1] && data[sub1][sub2]) itemsToRender = data[sub1][sub2];
            } else if (sub1) {
                // Mid Level (Show all children)
                if(data[sub1]) {
                    Object.keys(data[sub1]).forEach(k => {
                        itemsToRender = itemsToRender.concat(data[sub1][k]);
                    });
                }
            } else {
                // Top Level (Show everything in file)
                Object.keys(data).forEach(k1 => {
                    Object.keys(data[k1]).forEach(k2 => {
                        itemsToRender = itemsToRender.concat(data[k1][k2]);
                    });
                });
            }

            if(itemsToRender.length === 0) {
                container.innerHTML += "<div class='text-gray-500'>No items found in this category.</div>";
                return;
            }

            // Render Items
            itemsToRender.forEach(item => {
                let details = "";
                if(file === 'weapons') details = `DMG: ${item.dmg} | ROF: ${item.rof} | WA: ${item.wa}`;
                else if(file === 'armour') details = `SP: ${item.sp} | Locations: ${item.loc}`;
                else if(file === 'cyberware') details = `HL: ${item.hlCost} | Surg: ${item.surgery}`;

                const imgPath = item.image ? `CP2K20_sheet/imgs/${item.image}` : null;
                const imgHTML = imgPath 
                    ? `<img src="${imgPath}" class="shop-item-img" onerror="this.style.display='none'">` 
                    : `<div class="shop-item-img flex items-center justify-center text-xs text-gray-700 bg-black">NO IMG</div>`;

                const descHTML = item.description 
                    ? `<div class="text-xs text-gray-400 italic mt-1 border-l-2 border-gray-800 pl-2">${item.description}</div>` 
                    : '';

                container.innerHTML += `
                    <div class="flex bg-gray-900 border border-gray-800 p-2 hover:border-cyan-500 transition-colors mb-2">
                        <div class="flex-shrink-0">${imgHTML}</div>
                        <div class="flex-grow ml-2">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-bold text-cyan-300">${item.name}</div>
                                    <div class="text-xs text-gray-500">${details}</div>
                                    <div class="text-xs text-gray-400">Wt: ${item.weight || 0}kg</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-yellow-400 font-mono">${item.cost}eb</div>
                                    <button onclick="buyItem('${file}', '${item.name.replace(/'/g, "\\'")}')" class="cp-btn px-2 py-1 text-xs mt-1">BUY</button>
                                </div>
                            </div>
                            ${descHTML}
                        </div>
                    </div>
                `;
            });
        }

        // 3. Find Item in Deep Structure for buying
        function buyItem(cat, itemName) {
            // Helper to find item in tree
            let item = null;
            const root = gameData[cat];
            
            // Traverse
            Object.keys(root).some(k1 => {
                return Object.keys(root[k1]).some(k2 => {
                    const found = root[k1][k2].find(i => i.name === itemName);
                    if(found) {
                        item = found;
                        return true;
                    }
                });
            });

            if (!item) return;

            if (character.cash >= item.cost) {
                character.cash -= item.cost;
                const newItem = { ...item, type: cat, equipped: false };
                
                if (cat === 'cyberware') {
                    if(confirm(`Install ${item.name} now? (Costs ${item.hlCost} Humanity)`)) {
                        character.cyberware.push({ ...newItem, hl: parseHL(item.hlCost) });
                    } else {
                        character.inventory.push(newItem);
                    }
                } else {
                    character.inventory.push(newItem);
                }

                updateUI();
                markDirty();
            } else {
                alert("INSUFFICIENT FUNDS");
            }
        }

        function parseHL(hl) {
            if (typeof hl === 'number') return hl;
            return parseInt(hl) || 0; 
        }

        function renderInventory() {
            // Weapons
            const wList = document.getElementById('weapons-list');
            wList.innerHTML = "";
            character.inventory.filter(i => i.type === 'weapons').forEach((item, idx) => {
                wList.innerHTML += `
                    <div class="flex justify-between bg-black border border-gray-700 p-2">
                        <div>
                            <span class="text-white font-bold">${item.name}</span>
                            <span class="text-xs text-gray-500 ml-2">${item.wa} WA | ${item.dmg} | ${item.shots} shots</span>
                        </div>
                        <button onclick="removeItem(${idx})" class="text-red-500 text-xs">[TRASH]</button>
                    </div>
                `;
            });

            // Gear
            const gList = document.getElementById('gear-list');
            gList.innerHTML = "";
            character.inventory.filter(i => i.type !== 'weapons').forEach((item, idx) => {
                 gList.innerHTML += `
                    <div class="flex justify-between bg-black border border-gray-700 p-2">
                        <div>
                            <span class="text-gray-300">${item.name}</span>
                            <span class="text-xs text-gray-500 ml-2">Wt: ${item.weight}</span>
                        </div>
                        <button onclick="removeItem(${idx})" class="text-red-500 text-xs">[TRASH]</button>
                    </div>
                `;
            });
        }

        function renderCyberware() {
            const cList = document.getElementById('cyber-list');
            cList.innerHTML = "";
            character.cyberware.forEach((cw, idx) => {
                cList.innerHTML += `
                    <tr class="border-b border-gray-800 hover:bg-white/5">
                        <td class="p-2 text-cyan-300">${cw.name}</td>
                        <td class="p-2 text-red-400">-${cw.hl}</td>
                        <td class="p-2 text-xs text-gray-500">${cw.surgery || 'N/A'}</td>
                        <td class="p-2"><button onclick="removeCyber(${idx})" class="text-red-500 text-xs">UNINSTALL</button></td>
                    </tr>
                `;
            });
        }

        function removeItem(idx) {
            character.inventory.splice(idx, 1);
            updateUI();
            markDirty();
        }

        function removeCyber(idx) {
            if(confirm("Removing Cyberware restores Humanity (Therapy required in lore, but we will refund). Proceed?")) {
                character.cyberware.splice(idx, 1);
                updateUI();
                markDirty();
            }
        }

        // --- BACKEND SYNC ---
        
        function markDirty() {
            const status = document.getElementById('save-status');
            status.innerText = "UNSAVED CHANGES...";
            status.classList.replace('text-green-500', 'text-yellow-500');
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(saveCharacter, 2000);
        }

        async function saveCharacter() {
            const handle = document.getElementById('char-handle').value || 'Unknown';
            character.handle = handle;
            
            // Update LocalStorage for next visit
            localStorage.setItem('cp2020_last_char', handle);

            const payload = { action: 'save_character', filename: handle, data: character };
            const status = document.getElementById('save-status');
            status.innerText = "SAVING...";
            try {
                const res = await fetch('?', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (e) { console.error("SERVER ERROR:", text); return; }

                if(json.status === 'success') {
                    status.innerText = "SAVED";
                    status.classList.replace('text-yellow-500', 'text-green-500');
                    refreshCharList();
                } else {
                    status.innerText = "ERROR: " + json.msg;
                }
            } catch (e) { console.error(e); status.innerText = "CONNECTION ERROR"; }
        }

        async function loadCharacter(name) {
            if(!name) return;
            const res = await fetch('?', { method: 'POST', body: JSON.stringify({ action: 'load_character', filename: name }) });
            const data = await res.json();
            if(data) { 
                character = data; 
                localStorage.setItem('cp2020_last_char', name); // Remember this one
                updateUI(); 
            }
        }

        async function refreshCharList() {
            const res = await fetch('?', { method: 'POST', body: JSON.stringify({ action: 'list_characters' }) });
            const data = await res.json();
            const sel = document.getElementById('char-select');
            const current = sel.value;
            sel.innerHTML = '<option value="">-- NEW FILE --</option>';
            if (data.characters) {
                data.characters.forEach(c => {
                    const isSelected = (c === character.handle || c === current) ? 'selected' : '';
                    sel.innerHTML += `<option value="${c}" ${isSelected}>${c}</option>`;
                });
            }
        }

        function switchTab(tab) {
            document.querySelectorAll('[id^="tab-"]').forEach(el => el.classList.add('hidden'));
            document.getElementById('tab-' + tab).classList.remove('hidden');
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            event.target.classList.add('active');
        }

        function applyRole() {
            character.role = document.getElementById('char-role').value;
        }
    </script>
    <a href="http://web-mage.ca/games/"><img src="WebMage-sm.webp" style="position: fixed; right: 16px; bottom: 16px; height: 50px; z-index: 9999; pointer-events: auto;"></a>
</body>
</html>