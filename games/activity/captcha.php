<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $answer = isset($data['answer']) ? intval($data['answer']) : null;

    if ($answer === null) {
        echo json_encode(['valid' => false, 'error' => 'No answer provided']);
        exit;
    }

    $valid = isset($_SESSION['captcha_answer']) && $answer === (int)$_SESSION['captcha_answer'];
    echo json_encode(['valid' => $valid]);
    exit;
}

// GET – generate a new graphical captcha and output as PNG
$a = rand(2, 15);
$b = rand(2, 15);
$_SESSION['captcha_answer'] = $a + $b;

$width  = 200;
$height = 60;
$img    = imagecreatetruecolor($width, $height);

// Background gradient (dark blue-grey)
$bgTop    = imagecolorallocate($img, 22,  33,  62);
$bgBottom = imagecolorallocate($img, 15,  52,  96);
for ($y = 0; $y < $height; $y++) {
    $r       = (int)(22  + ($y / $height) * (15  - 22));
    $g       = (int)(33  + ($y / $height) * (52  - 33));
    $blueVal = (int)(62  + ($y / $height) * (96  - 62));
    $lc = imagecolorallocate($img, $r, $g, $blueVal);
    imageline($img, 0, $y, $width, $y, $lc);
}

// Random noise dots
for ($i = 0; $i < 80; $i++) {
    $nc = imagecolorallocate($img, rand(40, 100), rand(40, 100), rand(80, 140));
    imagesetpixel($img, rand(0, $width - 1), rand(0, $height - 1), $nc);
}

// Interference lines
for ($i = 0; $i < 5; $i++) {
    $lc = imagecolorallocate($img, rand(50, 120), rand(50, 120), rand(100, 180));
    imageline($img, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $lc);
}

// Text colour (bright accent)
$textCol  = imagecolorallocate($img, 233, 69, 96);
$textCol2 = imagecolorallocate($img, 200, 200, 220);

$text = "{$a} + {$b} = ?";

// Use built-in font (size 5 = largest built-in, ~9x15 px per char)
$fontW  = imagefontwidth(5);
$fontH  = imagefontheight(5);
$textW  = strlen($text) * $fontW;
$startX = (int)(($width  - $textW)  / 2);
$startY = (int)(($height - $fontH) / 2);

// Draw text with slight per-character vertical jitter
$x = $startX;
for ($i = 0; $i < strlen($text); $i++) {
    $jitter = rand(-4, 4);
    $col = ($i % 2 === 0) ? $textCol : $textCol2;
    imagechar($img, 5, $x, $startY + $jitter, $text[$i], $col);
    $x += $fontW + rand(0, 2);
}

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
imagepng($img);
imagedestroy($img);
