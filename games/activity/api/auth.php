<?php
// Buffer any PHP notices/warnings so they never corrupt the JSON response body
ob_start();
session_start();
ob_end_clean();

header('Content-Type: application/json');

define('DATA_DIR', __DIR__ . '/../data/');

/* ── JSON helpers ── */
function readJsonFile(string $path): array {
    if (!file_exists($path)) return [];
    $fp = fopen($path, 'r');
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function writeJsonFile(string $path, array $data): bool {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fp = fopen($path, 'c');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

/* ── Request ── */
$raw    = file_get_contents('php://input');
$input  = json_decode($raw, true) ?? [];
$action = $input['action'] ?? '';

$usersFile = DATA_DIR . 'users.json';

switch ($action) {

    /* ---- CHECK USERNAME ---- */
    case 'check_username': {
        $username = strtolower(trim($input['username'] ?? ''));
        if ($username === '') {
            echo json_encode(['available' => false, 'error' => 'Username is required']);
            exit;
        }
        $users = readJsonFile($usersFile);
        $taken = isset($users[$username]);
        echo json_encode(['available' => !$taken]);
        break;
    }

    /* ---- LOGIN ---- */
    case 'login': {
        $username = strtolower(trim($input['username'] ?? ''));
        $password = $input['password'] ?? '';

        if ($username === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Username and password required']);
            exit;
        }

        $users = readJsonFile($usersFile);

        if (!isset($users[$username])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            exit;
        }

        if (!password_verify($password, $users[$username]['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            exit;
        }

        // Update last_login
        $users[$username]['last_login'] = date('Y-m-d H:i:s');
        writeJsonFile($usersFile, $users);

        $_SESSION['user'] = $username;
        $_SESSION['role'] = $users[$username]['role'] ?? 'user';

        echo json_encode([
            'success'  => true,
            'username' => $username,
            'role'     => $users[$username]['role'] ?? 'user',
            'theme'    => $users[$username]['theme'] ?? 'dark',
            'goals'    => $users[$username]['goals'] ?? [],
            'profile'  => $users[$username]['profile'] ?? [],
        ]);
        break;
    }

    /* ---- SIGNUP ---- */
    case 'signup': {
        $username  = strtolower(trim($input['username'] ?? ''));
        $password  = $input['password'] ?? '';
        $captchaAns = isset($input['captcha_answer']) ? intval($input['captcha_answer']) : null;

        // Validate captcha
        if ($captchaAns === null || !isset($_SESSION['captcha_answer']) ||
            (int)$captchaAns !== (int)$_SESSION['captcha_answer']) {
            http_response_code(400);
            echo json_encode(['error' => 'Incorrect captcha answer']);
            exit;
        }

        if ($username === '' || strlen($username) < 3) {
            http_response_code(400);
            echo json_encode(['error' => 'Username must be at least 3 characters']);
            exit;
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            http_response_code(400);
            echo json_encode(['error' => 'Username may only contain letters, numbers, and underscores']);
            exit;
        }

        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 6 characters']);
            exit;
        }

        $users = readJsonFile($usersFile);

        if (isset($users[$username])) {
            http_response_code(409);
            echo json_encode(['error' => 'Username already taken']);
            exit;
        }

        $profile = [
            'full_name' => trim($input['full_name'] ?? ''),
            'weight'    => 80,
            'height'    => 175,
            'age'       => intval($input['age'] ?? 25),
            'gender'    => in_array($input['gender'] ?? '', ['m','f']) ? $input['gender'] : 'm',
        ];

        $users[$username] = [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'last_login'    => null,
            'theme'         => 'dark',
            'role'          => empty($users) ? 'admin' : 'user', // first user becomes admin
            'goals' => [
                'avg_steps'            => 6000,
                'workout_hours'        => 5,
                'sleep_goal'           => 7,
                'clean_meals_goal'     => 14,
                'activity_points_goal' => 300,
            ],
            'profile' => $profile,
        ];

        if (!writeJsonFile($usersFile, $users)) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not save user data']);
            exit;
        }

        // Clear captcha
        unset($_SESSION['captcha_answer']);

        // Auto-login
        $_SESSION['user'] = $username;
        $_SESSION['role'] = $users[$username]['role'];

        echo json_encode([
            'success'  => true,
            'username' => $username,
            'role'     => $users[$username]['role'],
            'theme'    => 'dark',
            'goals'    => $users[$username]['goals'],
            'profile'  => $users[$username]['profile'],
        ]);
        break;
    }

    /* ---- LOGOUT ---- */
    case 'logout': {
        session_destroy();
        echo json_encode(['success' => true]);
        break;
    }

    /* ---- CHECK ---- */
    case 'check': {
        if (empty($_SESSION['user'])) {
            echo json_encode(['logged_in' => false]);
            exit;
        }

        $username = $_SESSION['user'];
        $users    = readJsonFile($usersFile);

        if (!isset($users[$username])) {
            session_destroy();
            echo json_encode(['logged_in' => false]);
            exit;
        }

        echo json_encode([
            'logged_in' => true,
            'username'  => $username,
            'role'      => $users[$username]['role'] ?? 'user',
            'theme'     => $users[$username]['theme'] ?? 'dark',
            'goals'     => $users[$username]['goals'] ?? [],
            'profile'   => $users[$username]['profile'] ?? [],
        ]);
        break;
    }

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
