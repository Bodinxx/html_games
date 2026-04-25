<?php
// CRITICAL: Suppress PHP warnings/notices so they don't break the JSON output
error_reporting(0); 
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Helper to calculate phonetic similarity (optional, using levenshtein mainly)
function isFuzzyMatch($input, $target) {
    $input = strtolower(trim($input));
    $target = strtolower(trim($target));

    // 1. Direct match
    if ($input === $target) return true;

    // 2. Inclusion (e.g. "grass" inside "green grass")
    if (strpos($target, $input) !== false && strlen($input) > 3) return true;
    
    // 3. Levenshtein Distance (allows for small typos)
    // Allow 1 error for short words, 2 for longer words
    $limit = strlen($target) > 5 ? 2 : 1;
    if (levenshtein($input, $target) <= $limit) return true;

    return false;
}

// Load Questions
$jsonFile = __DIR__ . '/questions.json';
// Logic to find questions.json if not in current dir
if (!file_exists($jsonFile)) {
    $parentJson = dirname(__DIR__) . '/questions.json';
    if (file_exists($parentJson)) {
        $jsonFile = $parentJson;
    }
}

$questions = [];
if (file_exists($jsonFile)) {
    $questions = json_decode(file_get_contents($jsonFile), true) ?? [];
}

// Get Request Data
$action = $_GET['action'] ?? '';
$rawInput = file_get_contents('php://input');
$inputData = !empty($rawInput) ? json_decode($rawInput, true) : [];

switch ($action) {
    case 'get_question':
        if (empty($questions)) {
            echo json_encode(['error' => 'No questions loaded']);
            exit;
        }
        $exclude = isset($_GET['exclude']) ? explode(',', $_GET['exclude']) : [];
        $available = array_filter($questions, function($q) use ($exclude) {
            return !in_array($q['id'], $exclude);
        });
        if (empty($available)) $available = $questions;

        $q = $available[array_rand($available)];
        echo json_encode([
            'id' => $q['id'],
            'question' => $q['question'],
            'count' => count($q['answers'])
        ]);
        break;

    case 'check_answer':
        $qId = $inputData['question_id'] ?? 0;
        $userAnswer = $inputData['answer'] ?? '';
        
        $currentQ = null;
        foreach ($questions as $q) {
            if ($q['id'] == $qId) {
                $currentQ = $q;
                break;
            }
        }

        if (!$currentQ) {
            echo json_encode(['success' => false, 'message' => 'Question not found']);
            exit;
        }

        $matchFound = false;
        $matchedData = null;
        $rank = 0;

        foreach ($currentQ['answers'] as $index => $ans) {
            if (isFuzzyMatch($userAnswer, $ans['text'])) {
                $matchFound = true;
                $matchedData = $ans;
                $rank = $index;
                break;
            }
            if (isset($ans['keywords'])) {
                foreach ($ans['keywords'] as $keyword) {
                    if (isFuzzyMatch($userAnswer, $keyword)) {
                        $matchFound = true;
                        $matchedData = $ans;
                        $rank = $index;
                        break 2; 
                    }
                }
            }
        }

        if ($matchFound) {
            echo json_encode([
                'correct' => true,
                'points' => $matchedData['points'],
                'text' => $matchedData['text'],
                'rank' => $rank
            ]);
        } else {
            echo json_encode(['correct' => false]);
        }
        break;

    case 'reveal_round':
        $qId = $_GET['question_id'] ?? 0;
        foreach ($questions as $q) {
            if ($q['id'] == $qId) {
                echo json_encode(['answers' => $q['answers']]);
                exit;
            }
        }
        echo json_encode(['error' => 'Question not found']);
        break;

    case 'save_score':
        $scoresFile = __DIR__ . '/scores.json';
        $currentScores = [];
        if (file_exists($scoresFile)) {
            $content = file_get_contents($scoresFile);
            $currentScores = json_decode($content, true) ?? [];
        }
        
        $newScore = [
            'team1' => $inputData['team1'] ?? 'Player 1',
            'score1' => $inputData['score1'] ?? 0,
            'team2' => $inputData['team2'] ?? 'Player 2',
            'score2' => $inputData['score2'] ?? 0,
            'winner' => $inputData['winner'] ?? 'Tie',
            'series' => $inputData['gameSeries'] ?? '',
            'date' => date('Y-m-d H:i')
        ];
        
        $currentScores[] = $newScore;
        
        // Sort by highest winning score descending
        usort($currentScores, function($a, $b) {
            $maxA = max($a['score1'], $a['score2']);
            $maxB = max($b['score1'], $b['score2']);
            return $maxB - $maxA; 
        });
        
        // Keep top 50
        $currentScores = array_slice($currentScores, 0, 50);
        
        file_put_contents($scoresFile, json_encode($currentScores, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        break;

    case 'get_scores':
        $scoresFile = __DIR__ . '/scores.json';
        if (file_exists($scoresFile)) {
            echo file_get_contents($scoresFile);
        } else {
            echo json_encode([]);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}