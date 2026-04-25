<?php
// api.php - Handles writing to JSON files
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // For development
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action']) && isset($input['data'])) {
        $action = $input['action'];
        $data = $input['data'];
        $file = '';

        if ($action === 'save_users') {
            $file = 'users.json';
        } elseif ($action === 'save_logs') {
            $file = 'logs.json';
        }

        if ($file) {
            // Write data to file (Compact format)
            if (file_put_contents($file, json_encode($data))) {
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'message' => 'Failed to write to file'];
            }
        }
    }
}

echo json_encode($response);
?>