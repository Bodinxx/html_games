<?php
/**
 * ProTrack Dark - Multi-User Edition (FINAL DEPLOYMENT VERSION)
 * Features: 
 * - Secure Login/Registration (Bcrypt)
 * - Password Reset (Secure Token-based)
 * - Per-User JSON Isolation (MD5 Directory Hashing)
 * - Mobile Optimized (Ultra Compact Padding)
 * - Dynamic Project Selection & Notes
 * - CSV Export by Range/Day
 * - INDEFINITE Session Persistence (1 Year)
 * - Manual Entry Support
 */

// --- Session Configuration (Indefinite Persistence) ---
$session_lifetime = 31536000; // 1 Year in seconds
ini_set('session.gc_maxlifetime', $session_lifetime);
ini_set('session.cookie_lifetime', $session_lifetime);

// Robust session cookie parameters
session_set_cookie_params([
    'lifetime' => $session_lifetime,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

date_default_timezone_set('America/Edmonton');

// --- File & Directory Constants ---
$base_data_dir = 'data';
$users_db_file = 'users.json';

if (!is_dir($base_data_dir)) @mkdir($base_data_dir, 0777, true);
if (!file_exists($users_db_file)) file_put_contents($users_db_file, json_encode([]));

// --- Helper: Load Users ---
function getUsers($file) {
    return json_decode(file_get_contents($file), true) ?: [];
}

// --- Helper: Save Users ---
function saveUsers($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// --- Auth State & Routing ---
$auth_error = "";
$auth_success = "";
$is_logged_in = isset($_SESSION['user_email']);
$view = 'auth'; 

if (isset($_GET['action'])) {
    if ($_GET['action'] == 'logout') {
        session_destroy();
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    } elseif ($_GET['action'] == 'forgot') {
        $view = 'forgot';
    } elseif ($_GET['action'] == 'reset' && isset($_GET['token'])) {
        $view = 'reset';
    }
}

// --- Authentication & Reset Logic ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['auth_action'])) {
    $users = json_decode(file_get_contents($users_db_file), true) ?: [];
    $email = strtolower(trim($_POST['email'] ?? ''));

    if ($_POST['auth_action'] == 'register') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $auth_error = "Invalid email format.";
        } elseif (isset($users[$email])) {
            $auth_error = "Account already exists.";
        } else {
            $users[$email] = ['password' => password_hash($_POST['password'], PASSWORD_DEFAULT)];
            file_put_contents($users_db_file, json_encode($users, JSON_PRETTY_PRINT));
            $_SESSION['user_email'] = $email;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    } elseif ($_POST['auth_action'] == 'login') {
        if (isset($users[$email]) && password_verify($_POST['password'], $users[$email]['password'])) {
            $_SESSION['user_email'] = $email;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $auth_error = "Invalid email or password.";
        }
    } elseif ($_POST['auth_action'] == 'request_reset') {
        if (isset($users[$email])) {
            $token = bin2hex(random_bytes(32));
            $users[$email]['reset_token'] = $token;
            $users[$email]['reset_expires'] = time() + 3600;
            file_put_contents($users_db_file, json_encode($users, JSON_PRETTY_PRINT));
            $reset_link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]?action=reset&token=$token";
            
            // Email Config
            $headers = "From: ProTrack Admin <protrack@web-mage.ca>\r\nReply-To: protrack@web-mage.ca\r\n";
            @mail($email, "Password Reset - ProTrack", "Reset Link: " . $reset_link, $headers);
            $auth_success = "Reset instructions sent to your email. <br><small class='opacity-50'>(Link: <a href='$reset_link' class='underline'>Click Here</a>)</small>";
        } else { $auth_error = "Email not found."; }
    } elseif ($_POST['auth_action'] == 'do_reset') {
        $token = $_POST['token'];
        $found_email = null;
        foreach ($users as $u_mail => $data) {
            if (isset($data['reset_token']) && $data['reset_token'] === $token && time() < ($data['reset_expires'] ?? 0)) {
                $found_email = $u_mail; break;
            }
        }
        if ($found_email) {
            $users[$found_email]['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            unset($users[$found_email]['reset_token'], $users[$found_email]['reset_expires']);
            file_put_contents($users_db_file, json_encode($users, JSON_PRETTY_PRINT));
            $auth_success = "Password updated! Please login.";
            $view = 'auth';
        } else { $auth_error = "Token invalid/expired."; }
    }
}

// --- App Logic (Logged In) ---
if ($is_logged_in) {
    $user_hash = md5($_SESSION['user_email']);
    $user_dir = "$base_data_dir/$user_hash";
    if (!is_dir($user_dir)) @mkdir($user_dir, 0777, true);

    $tracking_file = "$user_dir/tracking_data.json";
    $config_file = "$user_dir/config.json";

    if (!file_exists($config_file)) {
        $default_config = [
            "categories" => ["Administrative", "Breaks", "Helping other Analysts", "Meeting", "Project Work", "Providing Training", "Research & Development", "System Maintenance"],
            "analysts" => ["Other", "Alex Rivera", "Casey Jones", "Jordan Smith", "Morgan Chen", "Riley Watson", "Skyler Vance", "Taylor Wong"],
            "projects" => ["Internal Audit", "Infrastructure Upgrade", "Client Onboarding"]
        ];
        file_put_contents($config_file, json_encode($default_config, JSON_PRETTY_PRINT));
    }
    if (!file_exists($tracking_file)) file_put_contents($tracking_file, json_encode([]));

    $config = json_decode(file_get_contents($config_file), true);
    $all_logs = json_decode(file_get_contents($tracking_file), true);
    
    if (!isset($config['projects'])) $config['projects'] = [];
    sort($config['categories']);
    sort($config['projects']);
    usort($config['analysts'], function($a, $b) {
        if ($a === "Other") return -1;
        if ($b === "Other") return 1;
        return strcasecmp($a, $b);
    });

    // CSV Export
    if (isset($_GET['export_range']) || isset($_GET['export_day'])) {
        $range = $_GET['export_range'] ?? null; $day = $_GET['export_day'] ?? null;
        $export = []; $min = null; $max = null;
        $cutoff = ($range == '7') ? strtotime('-7 days midnight') : (($range == '30') ? strtotime('-30 days midnight') : 0);

        foreach ($all_logs as $log) {
            if ($log['end_time'] === null) continue;
            $t = strtotime($log['start_time']);
            if (($day && date('Y-m-d', $t) == $day) || ($range == 'all') || ($range && $t >= $cutoff)) {
                if ($min === null || $t < $min) $min = $t;
                if ($max === null || $t > $max) $max = $t;
                $export[] = [
                    'Contact' => $log['user_name'], 'Category' => $log['category'], 'Project' => $log['project_name'] ?? '',
                    'Start' => $log['start_time'], 'End' => $log['end_time'], 'Duration' => formatDuration($log['duration_seconds']),
                    'Notes' => $log['notes'] ?? '', 'User' => $log['signed_in_user']
                ];
            }
        }
        if (!empty($export)) {
            $fn = $day ? "ProTrack Export $day.csv" : "ProTrack Export ".date('Y-m-d',$min)."__".date('Y-m-d',$max).".csv";
            header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="'.$fn.'"');
            $f = fopen('php://output', 'w'); fputcsv($f, array_keys($export[0]));
            foreach ($export as $r) fputcsv($f, $r);
            fclose($f); exit;
        }
    }

    // Handlers
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'start') {
            $all_logs[] = [
                "id" => uniqid(), "user_name" => $_POST['user_name'], "category" => $_POST['category'],
                "project_name" => (stripos($_POST['category'], 'Project') !== false) ? $_POST['project_name'] : null,
                "signed_in_user" => $_SESSION['user_email'], "notes" => substr($_POST['notes'], 0, 500),
                "start_time" => date('Y-m-d H:i:s'), "end_time" => null, "duration_seconds" => 0
            ];
        } elseif ($_POST['action'] == 'stop') {
            foreach ($all_logs as &$l) if ($l['id'] == $_POST['task_id'] && $l['end_time'] == null) {
                $l['end_time'] = date('Y-m-d H:i:s');
                $l['duration_seconds'] = strtotime($l['end_time']) - strtotime($l['start_time']);
                if (isset($_POST['notes'])) $l['notes'] = substr($_POST['notes'], 0, 500);
            }
        } elseif ($_POST['action'] == 'manual') {
            $start = $_POST['start_time'];
            $end = $_POST['end_time'];
            $cat = $_POST['category'];
            $all_logs[] = [
                "id" => uniqid(), "user_name" => $_POST['user_name'], "category" => $cat,
                "project_name" => (stripos($cat, 'Project') !== false) ? $_POST['project_name'] : null,
                "signed_in_user" => $_SESSION['user_email'], "notes" => substr($_POST['notes'], 0, 500),
                "start_time" => $start, "end_time" => $end,
                "duration_seconds" => strtotime($end) - strtotime($start)
            ];
        } elseif ($_POST['action'] == 'update') {
            foreach ($all_logs as &$l) if ($l['id'] == $_POST['task_id']) {
                $l['user_name'] = $_POST['user_name']; $l['category'] = $_POST['category'];
                $l['project_name'] = (stripos($_POST['category'], 'Project') !== false) ? $_POST['project_name'] : null;
                $l['notes'] = substr($_POST['notes'], 0, 500); $l['start_time'] = $_POST['start_time'];
                $l['end_time'] = $_POST['end_time']; 
                $l['duration_seconds'] = strtotime($l['end_time']) - strtotime($l['start_time']);
            }
        } elseif ($_POST['action'] == 'update_config') {
            $config['analysts'] = array_values(array_unique(array_filter(array_map('trim', explode("\n", $_POST['analysts_list'])))));
            $config['categories'] = array_values(array_unique(array_filter(array_map('trim', explode("\n", $_POST['categories_list'])))));
            $config['projects'] = array_values(array_unique(array_filter(array_map('trim', explode("\n", $_POST['projects_list'])))));
            file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
        }
        file_put_contents($tracking_file, json_encode($all_logs, JSON_PRETTY_PRINT));
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    if (isset($_GET['delete'])) {
        $all_logs = array_filter($all_logs, fn($l) => $l['id'] !== $_GET['delete']);
        file_put_contents($tracking_file, json_encode(array_values($all_logs), JSON_PRETTY_PRINT));
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    $active_task = null; $grouped_history = []; $cutoff = strtotime('-7 days midnight');
    foreach ($all_logs as $l) {
        if ($l['end_time'] === null) $active_task = $l;
        else if (strtotime($l['start_time']) >= $cutoff) $grouped_history[date('Y-m-d', strtotime($l['start_time']))][] = $l;
    }
    krsort($grouped_history);
    foreach ($grouped_history as &$day) usort($day, fn($a, $b) => strtotime($b['start_time']) - strtotime($a['start_time']));
}

function formatDuration($s) { return sprintf('%02d:%02d:%02d', floor($s/3600), floor(($s%3600)/60), $s%60); }
?>

<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>ProTrack | Dark Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0f172a; color: #f1f5f9; padding: 5px; }
        .glass { background: rgba(55, 65, 85, 0.85); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.12); padding: 5px; }
        .timer-glow { text-shadow: 0 0 20px rgba(56, 189, 248, 0.4); }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 50; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .history-card { background: rgba(55, 65, 85, 0.4); border: 1px solid rgba(255, 255, 255, 0.05); padding: 8px 5px; transition: all 0.2s; }
        .history-card:hover { background: rgba(55, 65, 85, 0.6); }
        input, select, textarea { background: rgba(30, 41, 59, 0.6) !important; color: white !important; border: 1px solid rgba(255,255,255,0.1) !important; padding: 5px !important; }
        .border-l-accent { border-left: 2px solid #38bdf8; }
    </style>
</head>
<body class="min-h-screen">

    <?php if (!$is_logged_in): ?>
        <div class="flex items-center justify-center min-h-screen">
            <div class="glass rounded-3xl p-8 w-full max-w-sm shadow-2xl">
                <div class="flex flex-col items-center mb-8">
                    <div class="w-12 h-12 bg-sky-500 rounded-xl flex items-center justify-center mb-4"><svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></div>
                    <h1 class="text-2xl font-bold">ProTrack</h1><p class="text-slate-400 text-xs tracking-widest mt-1">Enterprise Analytics</p>
                </div>
                <?php if ($auth_error): ?><div class="bg-red-500/20 border border-red-500 text-red-200 text-xs p-3 rounded-lg mb-4 text-center"><?php echo $auth_error; ?></div><?php endif; ?>
                <?php if ($auth_success): ?><div class="bg-emerald-500/20 border border-emerald-500 text-emerald-200 text-xs p-3 rounded-lg mb-4 text-center"><?php echo $auth_success; ?></div><?php endif; ?>

                <?php if ($view === 'auth'): ?>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="auth_action" id="auth-type" value="login">
                        <div><label class="block text-[10px] font-bold text-slate-300 uppercase mb-1">Email</label><input type="email" name="email" required class="w-full rounded-xl p-3 outline-none"></div>
                        <div><label class="block text-[10px] font-bold text-slate-300 uppercase mb-1">Password</label><input type="password" name="password" required class="w-full rounded-xl p-3 outline-none"></div>
                        <button type="submit" id="submit-btn" class="w-full bg-sky-600 hover:bg-sky-500 text-white font-bold py-3 rounded-xl shadow-lg active:scale-95 uppercase text-sm">Sign In</button>
                        <div class="flex flex-col gap-3 text-center mt-6"><button type="button" onclick="toggleAuth()" id="toggle-link" class="text-xs text-sky-400 font-semibold hover:underline">Register Account</button><a href="?action=forgot" class="text-xs text-slate-500 hover:text-slate-300 font-semibold">Forgot Password?</a></div>
                    </form>
                <?php elseif ($view === 'forgot'): ?>
                    <form method="POST" class="space-y-4"><input type="hidden" name="auth_action" value="request_reset"><p class="text-xs text-slate-400 mb-4 text-center">Request a reset link.</p><div><label class="block text-[10px] font-bold text-slate-300 uppercase mb-1">Email</label><input type="email" name="email" required class="w-full rounded-xl p-3 outline-none"></div><button type="submit" class="w-full bg-sky-600 font-bold py-3 rounded-xl uppercase text-sm shadow-lg">Send Link</button><div class="text-center mt-4"><a href="?" class="text-xs text-sky-400 font-semibold hover:underline">Back to Login</a></div></form>
                <?php elseif ($view === 'reset'): ?>
                    <form method="POST" class="space-y-4"><input type="hidden" name="auth_action" value="do_reset"><input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>"><p class="text-xs text-slate-400 mb-4 text-center">Enter new password.</p><div><label class="block text-[10px] font-bold text-slate-300 uppercase mb-1">New Password</label><input type="password" name="password" required class="w-full rounded-xl p-3 outline-none"></div><button type="submit" class="w-full bg-emerald-600 font-bold py-3 rounded-xl uppercase text-sm shadow-lg">Update Password</button></form>
                <?php endif; ?>
            </div>
        </div>
        <script>function toggleAuth(){const t=document.getElementById('auth-type'),b=document.getElementById('submit-btn'),l=document.getElementById('toggle-link');if(t.value==='login'){t.value='register';b.innerText='Create Account';l.innerText='Have an account? Login';}else{t.value='login';b.innerText='Sign In';l.innerText='Need an account? Register';}}</script>

    <?php else: ?>
        <div class="max-w-6xl mx-auto">
            <header class="mb-4 flex flex-col md:flex-row md:items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-sky-500 rounded-lg flex items-center justify-center shadow-lg"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></div>
                    <div><h1 class="text-xl font-bold">ProTrack</h1><p class="text-slate-400 text-[10px] uppercase font-semibold">Analytics</p></div>
                </div>
                <div class="flex gap-2 items-center">
                    <button onclick="document.getElementById('export-sec').classList.toggle('hidden')" class="glass w-11 h-11 rounded-xl flex items-center justify-center border-sky-500/30 text-sky-400"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg></button>
                    <button onclick="document.getElementById('config-modal').classList.add('active')" class="glass w-11 h-11 rounded-xl flex items-center justify-center"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-100" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /></svg></button>
                    <a href="?action=logout" class="glass w-11 h-11 rounded-xl flex items-center justify-center border-red-500/30 text-red-400"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg></a>
                    <div class="glass px-2 py-1 rounded-xl min-w-[100px] max-w-[150px]"><p class="text-[8px] text-slate-300 font-bold uppercase truncate">ID: <?php echo $_SESSION['user_email']; ?></p><p class="font-mono text-[10px] text-white" id="sys-clock"><?php echo date('H:i:s'); ?></p></div>
                </div>
            </header>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-2">
                <div class="lg:col-span-4 space-y-2">
                    <div id="export-sec" class="hidden glass rounded-2xl p-2 bg-sky-900/20 border-sky-500/30"><div class="grid grid-cols-3 gap-1"><a href="?export_range=7" class="bg-slate-800 text-[9px] font-bold p-2 rounded text-center uppercase">7D</a><a href="?export_range=30" class="bg-slate-800 text-[9px] font-bold p-2 rounded text-center uppercase">30D</a><a href="?export_range=all" class="bg-sky-600 text-[9px] font-bold p-2 rounded text-center uppercase">ALL</a></div></div>
                    
                    <div class="glass rounded-2xl p-3 relative overflow-hidden">
                        <?php if ($active_task): ?>
                            <div class="flex items-center gap-2 mb-2"><span class="animate-ping h-2 w-2 rounded-full bg-sky-400"></span><h2 class="text-[10px] font-bold text-sky-400 uppercase">Timing Active</h2></div>
                            <p class="text-lg font-bold leading-none mb-1"><?php echo htmlspecialchars($active_task['category']); ?></p>
                            <?php if ($active_task['project_name']): ?><p class="text-sky-400 text-xs font-bold uppercase mb-1"><?php echo htmlspecialchars($active_task['project_name']); ?></p><?php endif; ?>
                            <div class="text-4xl font-mono font-bold text-white mb-4 text-center timer-glow" id="active-timer" data-start="<?php echo $active_task['start_time']; ?>">00:00:00</div>
                            <form method="POST"><input type="hidden" name="action" value="stop"><input type="hidden" name="task_id" value="<?php echo $active_task['id']; ?>"><textarea name="notes" maxlength="500" class="w-full rounded-lg text-xs outline-none mb-2 h-16" placeholder="Session details..."><?php echo htmlspecialchars($active_task['notes'] ?? ''); ?></textarea><button type="submit" class="w-full bg-white text-slate-900 font-bold py-2 rounded-xl text-xs active:scale-95 uppercase">Stop Recording</button></form>
                        <?php else: ?>
                            <div class="flex justify-between items-center mb-3">
                                <h2 class="text-[10px] font-bold uppercase tracking-widest text-slate-300">New Entry</h2>
                                <button onclick="openManualModal()" class="text-[8px] font-bold text-sky-400 border border-sky-400/30 px-1.5 py-0.5 rounded hover:bg-sky-400/10 transition-colors uppercase">Manual Entry</button>
                            </div>
                            <form method="POST" class="space-y-3"><input type="hidden" name="action" value="start">
                                <div><label class="text-[8px] font-bold text-slate-400 uppercase">Contact</label><select name="user_name" class="w-full rounded-lg text-xs"><?php foreach ($config['analysts'] as $a): ?><option value="<?php echo $a; ?>"><?php echo $a; ?></option><?php endforeach; ?></select></div>
                                <div><label class="text-[8px] font-bold text-slate-400 uppercase">Category</label><select name="category" id="cat-sel" class="w-full rounded-lg text-xs"><?php foreach ($config['categories'] as $c): ?><option value="<?php echo $c; ?>"><?php echo $c; ?></option><?php endforeach; ?></select></div>
                                <div id="proj-wrap" class="hidden"><label class="text-[8px] font-bold text-slate-400 uppercase">Project</label><select name="project_name" class="w-full rounded-lg text-xs"><option value="">-- No Project --</option><?php foreach ($config['projects'] as $p): ?><option value="<?php echo $p; ?>"><?php echo $p; ?></option><?php endforeach; ?></select></div>
                                <textarea name="notes" maxlength="500" class="w-full rounded-lg text-xs outline-none h-16" placeholder="Short description..."></textarea><button type="submit" class="w-full bg-sky-600 text-white font-bold py-2 rounded-xl text-xs shadow-lg uppercase">Start Session</button></form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="lg:col-span-8">
                    <div class="space-y-4">
                        <?php foreach ($grouped_history as $date => $logs): ?>
                            <div class="flex items-center gap-2 mb-1"><h3 class="text-[10px] font-bold text-slate-400 uppercase"><?php echo (date('Y-m-d') == $date) ? "Today" : date('D, M d', strtotime($date)); ?></h3><div class="h-[1px] flex-grow bg-white/5"></div><?php if(!$active_task): ?><button onclick="window.location.reload()" class="text-[8px] text-slate-500 font-bold uppercase hover:text-white">Refresh</button><?php endif; ?><a href="?export_day=<?php echo $date; ?>" class="text-[8px] font-bold text-sky-400 uppercase">CSV Export</a></div>
                            <?php foreach ($logs as $row): ?>
                                <div class="history-card rounded-xl flex items-center relative overflow-hidden shadow-sm mb-2">
                                    <div class="flex-grow">
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="text-sm font-bold text-white truncate pr-2">
                                                <?php 
                                                    $parts = [htmlspecialchars($row['category'])];
                                                    if (!empty($row['project_name'])) $parts[] = "<span class='text-sky-400'>".htmlspecialchars($row['project_name'])."</span>";
                                                    if (!empty($row['user_name']) && $row['user_name'] !== 'Other') $parts[] = htmlspecialchars($row['user_name']);
                                                    echo implode(' : ', $parts);
                                                ?>
                                            </span>
                                            <span class="font-mono text-sm font-bold text-sky-400 whitespace-nowrap"><?php echo formatDuration($row['duration_seconds']); ?></span>
                                        </div>
                                        <div class="flex justify-between items-end">
                                            <div class="flex-grow border-l-2 border-sky-500/50 pl-2 pr-2 text-[10px] text-slate-100 italic leading-relaxed min-h-[1.2rem]">
                                                <?php echo !empty($row['notes']) ? '"'.htmlspecialchars($row['notes']).'"' : ''; ?>
                                            </div>
                                            <span class="text-[9px] text-slate-500 font-bold whitespace-nowrap uppercase tracking-tighter">
                                                <?php echo date('H:i', strtotime($row['start_time'])); ?> - <?php echo date('H:i', strtotime($row['end_time'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-1 pl-3 justify-center">
                                        <button onclick="editRecord(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="text-slate-400 hover:text-white p-1"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg></button>
                                        <a href="?delete=<?php echo $row['id']; ?>" class="text-slate-400 hover:text-red-500 p-1" onclick="return confirm('Delete?')"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <div id="config-modal" class="modal"><div class="glass rounded-2xl p-4 w-full max-w-lg mx-2"><h2 class="text-xs font-bold mb-4 uppercase text-center text-white">System Config</h2><form method="POST"><input type="hidden" name="action" value="update_config"><div class="grid grid-cols-3 gap-2"><div><label class="text-[7px] text-center block mb-1">Contacts</label><textarea name="analysts_list" rows="10" class="w-full text-[9px]"><?php echo implode("\n", $config['analysts']); ?></textarea></div><div><label class="text-[7px] text-center block mb-1">Categories</label><textarea name="categories_list" rows="10" class="w-full text-[9px]"><?php echo implode("\n", $config['categories']); ?></textarea></div><div><label class="text-[7px] text-center block mb-1">Projects</label><textarea name="projects_list" rows="10" class="w-full text-[9px]"><?php echo implode("\n", $config['projects']); ?></textarea></div></div><div class="flex gap-2 mt-4"><button type="button" onclick="document.getElementById('config-modal').classList.remove('active')" class="flex-grow bg-slate-700 py-2 rounded text-xs uppercase font-bold">Cancel</button><button type="submit" class="flex-grow bg-sky-600 py-2 rounded text-xs uppercase font-bold">Save Changes</button></div></form></div></div>
        
        <!-- Manual Entry Modal -->
        <div id="manual-modal" class="modal"><div class="glass rounded-2xl p-4 w-full max-w-lg mx-2"><h2 class="text-xs font-bold mb-3 uppercase text-center text-white">Add Manual Record</h2><form method="POST"><input type="hidden" name="action" value="manual">
            <div class="grid grid-cols-2 gap-2">
                <select name="user_name" class="text-xs rounded"><?php foreach ($config['analysts'] as $a): ?><option value="<?php echo $a; ?>"><?php echo $a; ?></option><?php endforeach; ?></select>
                <select name="category" id="m-cat" class="text-xs rounded"><?php foreach ($config['categories'] as $c): ?><option value="<?php echo $c; ?>"><?php echo $c; ?></option><?php endforeach; ?></select>
            </div>
            <div id="m-proj-wrap" class="mt-2 hidden"><label class="block text-[8px] uppercase font-bold text-slate-400 mb-1">Project</label><select name="project_name" class="w-full text-xs rounded"><option value="">-- No Project --</option><?php foreach ($config['projects'] as $p): ?><option value="<?php echo $p; ?>"><?php echo $p; ?></option><?php endforeach; ?></select></div>
            <div class="grid grid-cols-2 gap-2 mt-2">
                <div><label class="block text-[8px] uppercase font-bold text-slate-400 mb-1">Start Time</label><input type="text" name="start_time" placeholder="YYYY-MM-DD HH:MM:SS" value="<?php echo date('Y-m-d H:i:s'); ?>" class="w-full text-xs font-mono"></div>
                <div><label class="block text-[8px] uppercase font-bold text-slate-400 mb-1">End Time</label><input type="text" name="end_time" placeholder="YYYY-MM-DD HH:MM:SS" value="<?php echo date('Y-m-d H:i:s'); ?>" class="w-full text-xs font-mono"></div>
            </div>
            <textarea name="notes" maxlength="500" class="w-full text-xs h-20 outline-none mt-2 rounded" placeholder="Add details..."></textarea>
            <div class="flex gap-2 mt-3"><button type="button" onclick="document.getElementById('manual-modal').classList.remove('active')" class="flex-grow bg-slate-700 py-2 rounded text-xs font-bold uppercase">Cancel</button><button type="submit" class="flex-grow bg-sky-600 py-2 rounded text-xs font-bold uppercase">Save Entry</button></div></form></div></div>

        <!-- Edit Modal -->
        <div id="edit-modal" class="modal"><div class="glass rounded-2xl p-4 w-full max-w-lg mx-2"><h2 class="text-xs font-bold mb-3 uppercase text-center text-white">Edit Record</h2><form method="POST"><input type="hidden" name="action" value="update"><input type="hidden" name="task_id" id="e-id">
            <div class="grid grid-cols-2 gap-2"><select name="user_name" id="e-user" class="text-xs rounded"></select><select name="category" id="e-cat" class="text-xs rounded"></select></div>
            <div id="e-proj-wrap" class="mt-2"><label class="block text-[8px] uppercase font-bold text-slate-400 mb-1">Project</label><select name="project_name" id="e-proj" class="w-full text-xs rounded"></select></div>
            <div class="grid grid-cols-2 gap-2 mt-2">
                <div><label class="block text-[8px] uppercase font-bold text-slate-400 mb-1">Start Time</label><input type="text" name="start_time" id="e-start" class="w-full text-xs font-mono"></div>
                <div><label class="block text-[8px] uppercase font-bold text-slate-400 mb-1">End Time</label><input type="text" name="end_time" id="e-end" class="w-full text-xs font-mono"></div>
            </div>
            <textarea name="notes" id="e-notes" maxlength="500" class="w-full text-xs h-20 outline-none mt-2 rounded" placeholder="Notes..."></textarea>
            <div class="flex gap-2 mt-3"><button type="button" onclick="document.getElementById('edit-modal').classList.remove('active')" class="flex-grow bg-slate-700 py-2 rounded text-xs font-bold uppercase">Cancel</button><button type="submit" class="flex-grow bg-sky-600 py-2 rounded text-xs font-bold uppercase">Update</button></div></form></div></div>

        <script>
            setInterval(() => { const c = document.getElementById('sys-clock'); if(c) c.textContent = new Date().toLocaleTimeString('en-GB'); }, 1000);
            const activeTimer = document.getElementById('active-timer');
            if (activeTimer) {
                const s = new Date(activeTimer.getAttribute('data-start').replace(/-/g, '/')).getTime();
                setInterval(() => {
                    const d = Math.max(0, new Date().getTime() - s);
                    activeTimer.textContent = [Math.floor(d/3600000), Math.floor((d%3600000)/60000), Math.floor((d%60000)/1000)].map(v => v.toString().padStart(2, '0')).join(':');
                }, 1000);
            }
            const cS = document.getElementById('cat-sel'); if(cS) { const w = document.getElementById('proj-wrap'); const ch = () => w.className = cS.value.toLowerCase().includes('project') ? '' : 'hidden'; cS.onchange = ch; ch(); }
            
            const mCS = document.getElementById('m-cat'); if(mCS) { const mw = document.getElementById('m-proj-wrap'); const mch = () => mw.className = mCS.value.toLowerCase().includes('project') ? 'mt-2' : 'hidden'; mCS.onchange = mch; mch(); }

            function openManualModal() {
                document.getElementById('manual-modal').classList.add('active');
            }

            function editRecord(data) {
                document.getElementById('e-id').value = data.id; 
                document.getElementById('e-notes').value = data.notes || ''; 
                document.getElementById('e-start').value = data.start_time; 
                document.getElementById('e-end').value = data.end_time;
                
                const u = document.getElementById('e-user'), c = document.getElementById('e-cat'), p = document.getElementById('e-proj'), pw = document.getElementById('e-proj-wrap');
                u.innerHTML = <?php echo json_encode(array_map(fn($v) => "<option value='$v'>$v</option>", $config['analysts'])); ?>.join('');
                c.innerHTML = <?php echo json_encode(array_map(fn($v) => "<option value='$v'>$v</option>", $config['categories'])); ?>.join('');
                p.innerHTML = '<option value="">-- No Project --</option>' + <?php echo json_encode(array_map(fn($v) => "<option value='$v'>$v</option>", $config['projects'])); ?>.join('');
                
                u.value = data.user_name; c.value = data.category; p.value = data.project_name || '';
                const toggleP = () => pw.style.display = c.value.toLowerCase().includes('project') ? 'block' : 'none';
                c.onchange = toggleP; toggleP();
                
                document.getElementById('edit-modal').classList.add('active');
            }
        </script>
    <?php endif; ?>
</body>
</html>