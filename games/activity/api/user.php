<?php
session_start();
header('Content-Type: application/json');

define('DATA_DIR', __DIR__ . '/../data/');

/* ── Auth guard ── */
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$sessionUser = $_SESSION['user'];

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

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true) ?? [];
$action = $input['action'] ?? '';

$usersFile = DATA_DIR . 'users.json';

switch ($action) {

    case 'get_profile': {
        $users = readJsonFile($usersFile);
        if (!isset($users[$sessionUser])) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }
        echo json_encode([
            'profile' => $users[$sessionUser]['profile'] ?? [],
            'goals'   => $users[$sessionUser]['goals']   ?? [],
            'theme'   => $users[$sessionUser]['theme']   ?? 'dark',
        ]);
        break;
    }

    case 'update_profile': {
        $users = readJsonFile($usersFile);
        if (!isset($users[$sessionUser])) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        $allowed = ['full_name','weight','height','age','gender'];
        foreach ($allowed as $field) {
            if (isset($input[$field])) {
                if (in_array($field, ['weight','height','age'])) {
                    $users[$sessionUser]['profile'][$field] = floatval($input[$field]);
                } elseif ($field === 'gender') {
                    $g = $input[$field];
                    $users[$sessionUser]['profile'][$field] = in_array($g, ['m','f']) ? $g : 'm';
                } else {
                    $users[$sessionUser]['profile'][$field] = trim($input[$field]);
                }
            }
        }

        writeJsonFile($usersFile, $users);
        echo json_encode(['success' => true, 'profile' => $users[$sessionUser]['profile']]);
        break;
    }

    case 'update_goals': {
        $users = readJsonFile($usersFile);
        if (!isset($users[$sessionUser])) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        $allowed = ['avg_steps','workout_hours','sleep_goal','clean_meals_goal','activity_points_goal'];
        foreach ($allowed as $field) {
            if (isset($input[$field])) {
                $users[$sessionUser]['goals'][$field] = floatval($input[$field]);
            }
        }

        writeJsonFile($usersFile, $users);
        echo json_encode(['success' => true, 'goals' => $users[$sessionUser]['goals']]);
        break;
    }

    case 'update_theme': {
        $theme  = $input['theme'] ?? 'dark';
        $valid  = ['dark','light','ocean','dark blue','light blue','dark green','light green','dark red','light red','industrial'];

        if (!in_array($theme, $valid)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid theme']);
            exit;
        }

        $users = readJsonFile($usersFile);
        if (!isset($users[$sessionUser])) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        $users[$sessionUser]['theme'] = $theme;
        writeJsonFile($usersFile, $users);
        echo json_encode(['success' => true, 'theme' => $theme]);
        break;
    }

    case 'change_password': {
        $oldPass  = $input['old_password'] ?? '';
        $newPass  = $input['new_password'] ?? '';

        if (strlen($newPass) < 6) {
            http_response_code(400);
            echo json_encode(['error' => 'New password must be at least 6 characters']);
            exit;
        }

        $users = readJsonFile($usersFile);
        if (!isset($users[$sessionUser])) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        if (!password_verify($oldPass, $users[$sessionUser]['password_hash'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Current password is incorrect']);
            exit;
        }

        $users[$sessionUser]['password_hash'] = password_hash($newPass, PASSWORD_DEFAULT);
        writeJsonFile($usersFile, $users);
        echo json_encode(['success' => true]);
        break;
    }

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
