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

/* ── ISO week helper ── */
function getIsoWeekKey(string $date = ''): string {
    $ts = $date ? strtotime($date) : time();
    return date('o-W', $ts);
}

function getWeekDates(string $weekKey): array {
    [$year, $week] = explode('-', $weekKey);
    $dates = [];
    $dto = new DateTime();
    $dto->setISODate((int)$year, (int)$week, 1);
    for ($i = 0; $i < 7; $i++) {
        $dates[] = $dto->format('Y-m-d');
        $dto->modify('+1 day');
    }
    return $dates;
}

function generateId(): string {
    return uniqid('entry_', true);
}

function safePct(float $val, float $goal): int {
    if ($goal <= 0) return 0;
    return (int) min(round(($val / $goal) * 100), 999);
}

$logsFile = DATA_DIR . 'activity_logs.json';
$method   = $_SERVER['REQUEST_METHOD'];

/* ── POST ── */
if ($method === 'POST') {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
    $action = $input['action'] ?? '';

    switch ($action) {

        case 'add_entry': {
            $timestamp = trim($input['timestamp'] ?? '');
            $type      = trim($input['type'] ?? '');
            $quantity  = floatval($input['quantity'] ?? 0);
            $unit      = trim($input['unit'] ?? '');
            $note      = trim($input['note'] ?? '');
            $name      = trim($input['name'] ?? '');
            $factor    = floatval($input['factor'] ?? 0);

            if (!$timestamp || !$type) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing timestamp or type']);
                exit;
            }

            // Validate timestamp format
            $posT = strpos($timestamp, 'T');
            if ($posT === false) {
                $parsedDate = DateTime::createFromFormat('Y-m-d', $timestamp);
                if (!$parsedDate) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid timestamp format']);
                    exit;
                }
                $timestamp = $parsedDate->format('Y-m-d') . 'T00:00:00';
            }

            $entry = [
                'id'        => generateId(),
                'userId'    => $sessionUser,
                'timestamp' => $timestamp,
                'type'      => $type,
                'quantity'  => $quantity,
                'unit'      => $unit,
                'note'      => $note,
            ];
            
            // Store activity name and factor for activity entries
            if ($type === 'activity' && $name) {
                $entry['name'] = $name;
                $entry['factor'] = $factor;
            }

            $logs = readJsonFile($logsFile);
            $logs[] = $entry;

            if (!writeJsonFile($logsFile, $logs)) {
                http_response_code(500);
                echo json_encode(['error' => 'Could not save entry']);
                exit;
            }

            echo json_encode(['success' => true, 'entry' => $entry]);
            break;
        }

        case 'edit_entry': {
            $entryId   = trim($input['id'] ?? '');
            $quantity  = floatval($input['quantity'] ?? 0);
            $unit      = trim($input['unit'] ?? '');
            $note      = trim($input['note'] ?? '');
            $timestamp = trim($input['timestamp'] ?? '');

            if (!$entryId) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing entry ID']);
                exit;
            }

            $logs = readJsonFile($logsFile);
            $found = false;

            foreach ($logs as &$entry) {
                if ($entry['id'] === $entryId && $entry['userId'] === $sessionUser) {
                    $entry['quantity'] = $quantity;
                    $entry['unit'] = $unit;
                    $entry['note'] = $note;
                    if ($timestamp) {
                        $entry['timestamp'] = $timestamp;
                    }
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                http_response_code(404);
                echo json_encode(['error' => 'Entry not found']);
                exit;
            }

            if (!writeJsonFile($logsFile, $logs)) {
                http_response_code(500);
                echo json_encode(['error' => 'Could not update entry']);
                exit;
            }

            echo json_encode(['success' => true]);
            break;
        }

        case 'delete_entry': {
            $entryId = trim($input['id'] ?? '');

            if (!$entryId) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing entry ID']);
                exit;
            }

            $logs = readJsonFile($logsFile);
            $found = false;

            foreach ($logs as $idx => $entry) {
                if ($entry['id'] === $entryId && $entry['userId'] === $sessionUser) {
                    unset($logs[$idx]);
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                http_response_code(404);
                echo json_encode(['error' => 'Entry not found']);
                exit;
            }

            // Reindex array
            $logs = array_values($logs);

            if (!writeJsonFile($logsFile, $logs)) {
                http_response_code(500);
                echo json_encode(['error' => 'Could not delete entry']);
                exit;
            }

            echo json_encode(['success' => true]);
            break;
        }

        case 'get_week': {
            $weekKey   = $input['week'] ?? getIsoWeekKey();
            $logs      = readJsonFile($logsFile);
            $weekDates = getWeekDates($weekKey);
            $weekStart = $weekDates[0];
            $weekEnd   = $weekDates[6];

            // Filter entries for this user and week
            $entries = array_filter($logs, function ($entry) use ($sessionUser, $weekStart, $weekEnd) {
                $entryDate = substr($entry['timestamp'], 0, 10);
                return $entry['userId'] === $sessionUser 
                    && $entryDate >= $weekStart 
                    && $entryDate <= $weekEnd;
            });

            echo json_encode([
                'week'    => $weekKey,
                'entries' => array_values($entries),
                'dates'   => $weekDates
            ]);
            break;
        }

        case 'get_day': {
            $date = trim($input['date'] ?? '');
            $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
            
            if (!$date || !$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD.']);
                exit;
            }

            $logs = readJsonFile($logsFile);
            
            // Filter entries for this user and date
            $entries = array_filter($logs, function ($entry) use ($sessionUser, $date) {
                $entryDate = substr($entry['timestamp'], 0, 10);
                return $entry['userId'] === $sessionUser && $entryDate === $date;
            });

            // Sort by timestamp descending (newest first)
            usort($entries, function ($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });

            echo json_encode([
                'date'    => $date,
                'entries' => array_values($entries)
            ]);
            break;
        }

        case 'get_leaderboard': {
            $usersFile  = DATA_DIR . 'users.json';
            $users      = readJsonFile($usersFile);
            $logs       = readJsonFile($logsFile);
            $weekKey    = getIsoWeekKey();
            $weekDates  = getWeekDates($weekKey);
            $weekStart  = $weekDates[0];
            $weekEnd    = $weekDates[6];

            $leaderboard = [];

            foreach ($users as $username => $userData) {
                // Collect this user's entries for the current week
                $userEntries = array_filter($logs, function ($entry) use ($username, $weekStart, $weekEnd) {
                    $entryDate = substr($entry['timestamp'], 0, 10);
                    return $entry['userId'] === $username
                        && $entryDate >= $weekStart
                        && $entryDate <= $weekEnd;
                });

                // Aggregate totals
                $steps  = 0;
                $water  = 0;
                $sleep  = 0;
                $meals  = 0;
                $points = 0.0;

                foreach ($userEntries as $entry) {
                    switch ($entry['type']) {
                        case 'steps':    $steps  += floatval($entry['quantity'] ?? 0); break;
                        case 'water':    $water  += floatval($entry['quantity'] ?? 0); break;
                        case 'sleep':    $sleep  += floatval($entry['quantity'] ?? 0); break;
                        case 'meal':     $meals  += 1; break;
                        case 'activity':
                            $factor  = floatval($entry['factor'] ?? 0);
                            $qty     = floatval($entry['quantity'] ?? 0);
                            $points += $qty * $factor;
                            break;
                    }
                }

                // User goals / defaults
                $goals         = $userData['goals'] ?? [];
                $stepsGoalWeek = (floatval($goals['avg_steps'] ?? 6000)) * 7;
                $sleepGoalWeek = (floatval($goals['sleep_goal'] ?? 7)) * 7;
                $mealsGoal     = floatval($goals['clean_meals_goal'] ?? 14);
                $waterGoal     = (floatval($goals['water_goal'] ?? 8)) * 7;
                $pointsGoal    = floatval($goals['activity_points_goal'] ?? 300);
                if ($pointsGoal <= 0) $pointsGoal = 300;

                $leaderboard[] = [
                    'username'   => $username,
                    'points_pct' => safePct($points,     $pointsGoal),
                    'steps_pct'  => safePct($steps,      $stepsGoalWeek),
                    'sleep_pct'  => safePct($sleep,      $sleepGoalWeek),
                    'meals_pct'  => safePct($meals,      $mealsGoal),
                    'water_pct'  => safePct($water,      $waterGoal),
                ];
            }

            // Sort by points percentage descending, then by username
            usort($leaderboard, function ($a, $b) {
                if ($b['points_pct'] !== $a['points_pct']) {
                    return $b['points_pct'] - $a['points_pct'];
                }
                return strcmp($a['username'], $b['username']);
            });

            echo json_encode(['leaderboard' => $leaderboard]);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
