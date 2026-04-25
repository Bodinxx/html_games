<?php
session_start();
header('Content-Type: application/json');

define('DATA_DIR', __DIR__ . '/../data/');

$raw    = file_get_contents('php://input');
$input  = json_decode($raw, true) ?? [];
$action = $input['action'] ?? ($_GET['action'] ?? '');

/* ── Auth + admin guard ── */
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

if ($action !== 'get_activities' && (($_SESSION['role'] ?? 'user') !== 'admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

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

$usersFile      = DATA_DIR . 'users.json';
$activitiesFile = DATA_DIR . 'activities.json';

switch ($action) {

    case 'list_users': {
        $users  = readJsonFile($usersFile);
        $result = [];
        foreach ($users as $username => $data) {
            $result[] = [
                'username'   => $username,
                'last_login' => $data['last_login'] ?? null,
                'role'       => $data['role'] ?? 'user',
            ];
        }
        echo json_encode(['users' => $result]);
        break;
    }

    case 'reset_password': {
        $targetUser = $input['username'] ?? '';
        if ($targetUser === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Username required']);
            exit;
        }

        $users = readJsonFile($usersFile);
        if (!isset($users[$targetUser])) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        // Generate a random temp password
        $chars    = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#';
        $tempPass = '';
        for ($i = 0; $i < 10; $i++) {
            $tempPass .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $users[$targetUser]['password_hash'] = password_hash($tempPass, PASSWORD_DEFAULT);
        writeJsonFile($usersFile, $users);

        echo json_encode(['success' => true, 'temp_password' => $tempPass]);
        break;
    }

    case 'remove_user': {
        $targetUser = $input['username'] ?? '';
        if ($targetUser === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Username required']);
            exit;
        }

        // Prevent self-deletion
        if ($targetUser === $_SESSION['user']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot remove your own account']);
            exit;
        }

        $users = readJsonFile($usersFile);
        if (!isset($users[$targetUser])) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        unset($users[$targetUser]);
        writeJsonFile($usersFile, $users);
        echo json_encode(['success' => true]);
        break;
    }

    case 'get_activities': {
        $activities = readJsonFile($activitiesFile);
        echo json_encode(['activities' => $activities]);
        break;
    }

    case 'add_activity': {
        $name     = trim($input['name'] ?? '');
        $unit     = trim($input['unit'] ?? '');
        $factor   = floatval($input['factor'] ?? 0);
        $category = trim($input['category'] ?? 'Other');

        if ($name === '' || $unit === '' || $factor <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Name, unit, and a positive factor are required']);
            exit;
        }

        $activities = readJsonFile($activitiesFile);

        if (isset($activities[$name])) {
            http_response_code(409);
            echo json_encode(['error' => 'Activity already exists']);
            exit;
        }

        $activities[$name] = ['unit' => $unit, 'factor' => $factor, 'category' => $category];
        ksort($activities);
        writeJsonFile($activitiesFile, $activities);
        echo json_encode(['success' => true, 'activities' => $activities]);
        break;
    }

    case 'remove_activity': {
        $name = trim($input['name'] ?? '');
        if ($name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Activity name required']);
            exit;
        }

        $activities = readJsonFile($activitiesFile);
        if (!isset($activities[$name])) {
            http_response_code(404);
            echo json_encode(['error' => 'Activity not found']);
            exit;
        }

        unset($activities[$name]);
        writeJsonFile($activitiesFile, $activities);
        echo json_encode(['success' => true]);
        break;
    }

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
