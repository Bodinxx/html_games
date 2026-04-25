<?php
/**
 * counter.php
 * Handles background requests from game HTML files to increment counts in click_counts.json.
 */

// 1. Path to your JSON file
$json_file = 'click_counts.json';

// 2. Get the filename passed from the JavaScript fetch request
$file_to_increment = isset($_GET['file']) ? $_GET['file'] : null;

if ($file_to_increment && file_exists($json_file)) {
    // 3. Read and decode the existing JSON
    $data = json_decode(file_get_contents($json_file), true);

    if (is_array($data)) {
        // 4. Increment the value if key exists, otherwise initialize it
        if (isset($data[$file_to_increment])) {
            $data[$file_to_increment]++;
        } else {
            $data[$file_to_increment] = 1;
        }

        // 5. Save the updated JSON back to the file
        file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT));
        
        echo "Success: $file_to_increment incremented.";
    } else {
        echo "Error: Invalid JSON format.";
    }
} else {
    echo "Error: Missing filename or JSON file not found.";
}
?>