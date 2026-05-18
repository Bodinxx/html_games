<?php
declare(strict_types=1);

header('Content-Type: application/json');

const STORAGE_DIR = __DIR__ . '/storage';
const ASSETS_DIR = STORAGE_DIR . '/assets';
const PROJECT_FILE = STORAGE_DIR . '/project.json';
const MAX_UPLOAD_SIZE = 10 * 1024 * 1024;
const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'];

ensureStorage();

$action = $_GET['action'] ?? 'state';

if ($action === 'state') {
    respond([
        'project' => readProject(),
        'assets' => listAssets(),
    ]);
}

if ($action === 'save_state') {
    $payload = requestJson();
    $project = $payload['project'] ?? null;

    if (!is_array($project)) {
        respond(['error' => 'Invalid project payload.'], 400);
    }

    writeProject(normalizeProject($project));
    respond(['ok' => true]);
}

if ($action === 'upload_asset') {
    if (!isset($_FILES['asset']) || !is_array($_FILES['asset'])) {
        respond(['error' => 'Missing asset upload.'], 400);
    }

    $file = $_FILES['asset'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        respond(['error' => 'Upload failed.'], 400);
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    $originalName = (string) ($file['name'] ?? '');

    if ($size <= 0 || $size > MAX_UPLOAD_SIZE) {
        respond(['error' => 'Asset must be between 1 byte and 10MB.'], 400);
    }

    $sanitized = sanitizeFilename($originalName);
    $extension = strtolower((string) pathinfo($sanitized, PATHINFO_EXTENSION));

    if (!in_array($extension, ALLOWED_EXTENSIONS, true)) {
        respond(['error' => 'Unsupported asset format.'], 400);
    }

    $targetName = uniqueAssetName($sanitized);
    $targetPath = ASSETS_DIR . '/' . $targetName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        respond(['error' => 'Unable to store uploaded asset.'], 500);
    }

    optimizeAsset($targetPath, $extension);

    respond([
        'ok' => true,
        'asset' => assetMeta($targetName),
        'assets' => listAssets(),
    ]);
}

if ($action === 'delete_asset') {
    $payload = requestJson();
    $filename = sanitizeFilename((string) ($payload['filename'] ?? ''));

    if ($filename === '') {
        respond(['error' => 'Missing filename.'], 400);
    }

    $fullPath = ASSETS_DIR . '/' . $filename;
    if (!is_file($fullPath)) {
        respond(['error' => 'Asset not found.'], 404);
    }

    if (!unlink($fullPath)) {
        respond(['error' => 'Unable to delete asset.'], 500);
    }

    respond(['ok' => true, 'assets' => listAssets()]);
}

if ($action === 'rename_asset') {
    $payload = requestJson();
    $oldName = sanitizeFilename((string) ($payload['oldName'] ?? ''));
    $newNameInput = sanitizeFilename((string) ($payload['newName'] ?? ''));

    if ($oldName === '' || $newNameInput === '') {
        respond(['error' => 'Missing rename fields.'], 400);
    }

    $oldPath = ASSETS_DIR . '/' . $oldName;
    if (!is_file($oldPath)) {
        respond(['error' => 'Asset not found.'], 404);
    }

    $ext = strtolower((string) pathinfo($newNameInput, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, ALLOWED_EXTENSIONS, true)) {
        respond(['error' => 'Unsupported asset format.'], 400);
    }

    $newName = uniqueAssetName($newNameInput);
    $newPath = ASSETS_DIR . '/' . $newName;

    if (!rename($oldPath, $newPath)) {
        respond(['error' => 'Unable to rename asset.'], 500);
    }

    respond(['ok' => true, 'assets' => listAssets()]);
}

respond(['error' => 'Unknown action.'], 404);

function ensureStorage(): void
{
    if (!is_dir(STORAGE_DIR)) {
        mkdir(STORAGE_DIR, 0775, true);
    }
    if (!is_dir(ASSETS_DIR)) {
        mkdir(ASSETS_DIR, 0775, true);
    }
    if (!is_file(PROJECT_FILE)) {
        writeProject(defaultProject());
    }
}

function requestJson(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : [];
}

function respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function readProject(): array
{
    $raw = file_get_contents(PROJECT_FILE);
    $decoded = json_decode((string) $raw, true);

    if (!is_array($decoded)) {
        return defaultProject();
    }

    return normalizeProject($decoded);
}

function writeProject(array $project): void
{
    file_put_contents(PROJECT_FILE, json_encode($project, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function normalizeProject(array $project): array
{
    $project['title'] = trim((string) ($project['title'] ?? 'Untitled LoreBinder Project'));
    if ($project['title'] === '') {
        $project['title'] = 'Untitled LoreBinder Project';
    }

    $project['tree'] = is_array($project['tree'] ?? null) ? $project['tree'] : [];
    $project['documents'] = is_array($project['documents'] ?? null) ? $project['documents'] : [];
    $project['openTabs'] = array_values(array_filter(
        is_array($project['openTabs'] ?? null) ? $project['openTabs'] : [],
        static fn ($id) => is_string($id) && $id !== ''
    ));
    $project['activeDocumentId'] = (string) ($project['activeDocumentId'] ?? '');
    $project['styleCss'] = (string) ($project['styleCss'] ?? '');

    if ($project['activeDocumentId'] === '' && !empty($project['openTabs'])) {
        $project['activeDocumentId'] = (string) $project['openTabs'][0];
    }

    return $project;
}

function defaultProject(): array
{
    $docId = 'doc-welcome';

    return [
        'title' => 'LoreBinder Project',
        'tree' => [
            [
                'id' => 'folder-chapter-1',
                'type' => 'folder',
                'name' => 'Chapter 1',
                'includeInCompile' => true,
                'children' => [
                    [
                        'id' => 'node-doc-welcome',
                        'type' => 'document',
                        'name' => 'Welcome.md',
                        'docId' => $docId,
                        'includeInCompile' => true,
                    ],
                ],
            ],
        ],
        'documents' => [
            $docId => [
                'id' => $docId,
                'name' => 'Welcome.md',
                'content' => "[var:campaign_setting=\"The Iron Domains\"]\n\n# LoreBinder\n\nWelcome to [var:campaign_setting].\n\n::: wrap-left\n![Concept Art](assets/sample-art.webp)\n:::\n\n{{monster-stat-block,border-color:crimson\n### Fire Drake\n*Medium dragon, chaotic evil*\n}}\n",
                'updatedAt' => gmdate(DATE_ATOM),
            ],
        ],
        'openTabs' => [$docId],
        'activeDocumentId' => $docId,
        'styleCss' => ".monster-stat-block {\n  border: 2px solid #8b0000;\n  border-radius: 8px;\n  padding: 0.75rem;\n  background: rgba(255, 255, 255, 0.75);\n}\n\n.wrap-left img {\n  float: left;\n  max-width: 40%;\n  margin: 0 1rem 0.75rem 0;\n}\n",
    ];
}

function sanitizeFilename(string $filename): string
{
    $filename = basename(str_replace('\\', '/', $filename));
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?? '';
    return trim($filename, '._');
}

function uniqueAssetName(string $filename): string
{
    $base = (string) pathinfo($filename, PATHINFO_FILENAME);
    $ext = (string) pathinfo($filename, PATHINFO_EXTENSION);

    $base = $base !== '' ? $base : 'asset';
    $candidate = $base . ($ext !== '' ? '.' . $ext : '');
    $counter = 1;

    while (is_file(ASSETS_DIR . '/' . $candidate)) {
        $candidate = sprintf('%s-%d%s', $base, $counter, $ext !== '' ? '.' . $ext : '');
        $counter++;
    }

    return $candidate;
}

function listAssets(): array
{
    $assets = [];
    $items = scandir(ASSETS_DIR);
    if (!is_array($items)) {
        return [];
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $meta = assetMeta($item);
        if ($meta !== null) {
            $assets[] = $meta;
        }
    }

    usort($assets, static fn (array $a, array $b) => strcmp($a['name'], $b['name']));

    return $assets;
}

function assetMeta(string $filename): ?array
{
    $fullPath = ASSETS_DIR . '/' . $filename;
    if (!is_file($fullPath)) {
        return null;
    }

    return [
        'name' => $filename,
        'size' => filesize($fullPath) ?: 0,
        'url' => 'storage/assets/' . rawurlencode($filename),
    ];
}

function optimizeAsset(string $path, string $extension): void
{
    if (!extension_loaded('gd')) {
        return;
    }

    if ($extension === 'jpg' || $extension === 'jpeg') {
        $image = @imagecreatefromjpeg($path);
        if ($image === false) {
            return;
        }
        imagejpeg($image, $path, 82);
        imagedestroy($image);
        return;
    }

    if ($extension === 'png') {
        $image = @imagecreatefrompng($path);
        if ($image === false) {
            return;
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagepng($image, $path, 6);
        imagedestroy($image);
        return;
    }

    if ($extension === 'webp' && function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
        $image = @imagecreatefromwebp($path);
        if ($image === false) {
            return;
        }
        imagewebp($image, $path, 80);
        imagedestroy($image);
    }
}
