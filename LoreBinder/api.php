<?php
declare(strict_types=1);

session_name('lorebinder_sid');
session_start();

header('Content-Type: application/json');

const STORAGE_DIR = __DIR__ . '/storage';
const ASSETS_DIR = STORAGE_DIR . '/assets';
const PROJECT_FILE = STORAGE_DIR . '/project.json';
const USERS_FILE = STORAGE_DIR . '/users.enc';
const USERS_KEY_FILE = STORAGE_DIR . '/users.key';
const USERS_KEY_ENV = 'LOREBINDER_USERS_KEY';
const MAX_UPLOAD_SIZE = 10 * 1024 * 1024;
const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'];
const STORAGE_DIR_PERMISSIONS = 0755;
const JPEG_QUALITY = 82;
const PNG_COMPRESSION = 6;
const WEBP_QUALITY = 80;
const DEFAULT_ADMIN_USERNAME = 'ADMIN';
const DEFAULT_ADMIN_PASSWORD = 'admin';
const USER_ROLE_ADMIN = 'admin';
const USER_ROLE_PRIMARY_AUTHOR = 'primary_author';
const USER_ROLE_SUB_AUTHOR = 'sub_author';
const USER_ROLE_REVIEWER = 'reviewer';
const USER_STATUS_ACTIVE = 'active';
const USER_STATUS_BANNED = 'banned';
const REQUEST_STATUS_PENDING = 'pending';
const SESSION_COOKIE_PAST_EXPIRY_SECONDS = 42000;

ensureStorage();

$action = $_GET['action'] ?? 'state';

if ($action === 'auth_state') {
    respond(['user' => currentSessionUser()]);
}

if ($action === 'login') {
    $payload = requestJson();
    $username = trim((string) ($payload['username'] ?? ''));
    $password = (string) ($payload['password'] ?? '');

    if ($username === '' || $password === '') {
        respond(['error' => 'Username and password are required.'], 400);
    }

    $store = loadUserStore();
    $normalizedUsername = normalizeUsername($username);
    $userIndex = findUserIndexByUsername($store['users'], $normalizedUsername);

    if ($userIndex === null) {
        respond(['error' => 'Invalid credentials.'], 401);
    }

    $user = $store['users'][$userIndex];

    if (($user['status'] ?? USER_STATUS_ACTIVE) === USER_STATUS_BANNED) {
        respond(['error' => 'Account is banned. Contact an administrator.'], 403);
    }

    $hash = (string) ($user['passwordHash'] ?? '');
    if ($hash === '' || !password_verify($password, $hash)) {
        respond(['error' => 'Invalid credentials.'], 401);
    }

    $store['users'][$userIndex]['lastLoginAt'] = gmdate(DATE_ATOM);
    $store['users'][$userIndex]['updatedAt'] = gmdate(DATE_ATOM);
    saveUserStore($store);

    session_regenerate_id(true);
    $_SESSION['lorebinder_user_id'] = $store['users'][$userIndex]['id'];

    respond(['ok' => true, 'user' => publicUserProfile($store['users'][$userIndex])]);
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - SESSION_COOKIE_PAST_EXPIRY_SECONDS,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();

    respond(['ok' => true]);
}

if ($action === 'request_account') {
    $payload = requestJson();
    $username = trim((string) ($payload['username'] ?? ''));
    $realName = trim((string) ($payload['realName'] ?? ''));
    $email = trim((string) ($payload['email'] ?? ''));
    $password = (string) ($payload['password'] ?? '');
    $role = trim((string) ($payload['role'] ?? USER_ROLE_SUB_AUTHOR));

    if (!isValidUsername($username)) {
        respond(['error' => 'Username must be 3-40 chars and contain letters, numbers, dot, underscore, or dash.'], 400);
    }

    if (mb_strlen($realName) < 2 || mb_strlen($realName) > 80) {
        respond(['error' => 'Real name must be between 2 and 80 characters.'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) {
        respond(['error' => 'A valid email address is required.'], 400);
    }

    if (mb_strlen($password) < 8 || mb_strlen($password) > 120) {
        respond(['error' => 'Password must be between 8 and 120 characters.'], 400);
    }

    if (!in_array($role, [USER_ROLE_PRIMARY_AUTHOR, USER_ROLE_SUB_AUTHOR, USER_ROLE_REVIEWER], true)) {
        respond(['error' => 'Requested role is invalid.'], 400);
    }

    $store = loadUserStore();
    $normalizedUsername = normalizeUsername($username);
    $normalizedEmail = strtolower($email);

    foreach ($store['users'] as $existingUser) {
        if (($existingUser['usernameLower'] ?? '') === $normalizedUsername) {
            respond(['error' => 'Username already exists.'], 409);
        }
        if (strtolower((string) ($existingUser['email'] ?? '')) === $normalizedEmail) {
            respond(['error' => 'Email address already in use.'], 409);
        }
    }

    foreach ($store['requests'] as $existingRequest) {
        if (($existingRequest['status'] ?? '') !== REQUEST_STATUS_PENDING) {
            continue;
        }
        if (($existingRequest['usernameLower'] ?? '') === $normalizedUsername) {
            respond(['error' => 'A request for this username already exists.'], 409);
        }
        if (strtolower((string) ($existingRequest['email'] ?? '')) === $normalizedEmail) {
            respond(['error' => 'A request for this email already exists.'], 409);
        }
    }

    $now = gmdate(DATE_ATOM);
    $store['requests'][] = [
        'id' => 'req-' . bin2hex(random_bytes(8)),
        'username' => $username,
        'usernameLower' => $normalizedUsername,
        'realName' => $realName,
        'email' => $email,
        'role' => $role,
        'status' => REQUEST_STATUS_PENDING,
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'createdAt' => $now,
        'updatedAt' => $now,
    ];

    saveUserStore($store);

    respond(['ok' => true]);
}

if ($action === 'state') {
    $sessionUser = requireActiveSessionUser();

    respond([
        'project' => readProject(),
        'assets' => listAssets(),
        'user' => $sessionUser,
    ]);
}

if ($action === 'save_state') {
    requireActiveSessionUser();

    $payload = requestJson();
    $project = $payload['project'] ?? null;

    if (!is_array($project)) {
        respond(['error' => 'Invalid project payload.'], 400);
    }

    writeProject(normalizeProject($project));
    respond(['ok' => true]);
}

if ($action === 'upload_asset') {
    requireActiveSessionUser();

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
    requireActiveSessionUser();

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
    requireActiveSessionUser();

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

if ($action === 'admin_list_users') {
    requireAdminUser();
    $store = loadUserStore();
    $profiles = array_map(static fn (array $user): array => publicUserProfile($user), $store['users']);
    respond(['users' => $profiles]);
}

if ($action === 'admin_list_requests') {
    requireAdminUser();
    $store = loadUserStore();
    $requests = array_values(array_filter(
        $store['requests'],
        static fn (array $request): bool => ($request['status'] ?? '') === REQUEST_STATUS_PENDING
    ));

    $requests = array_map(static function (array $request): array {
        return [
            'id' => (string) ($request['id'] ?? ''),
            'username' => (string) ($request['username'] ?? ''),
            'realName' => (string) ($request['realName'] ?? ''),
            'email' => (string) ($request['email'] ?? ''),
            'role' => (string) ($request['role'] ?? USER_ROLE_SUB_AUTHOR),
            'status' => (string) ($request['status'] ?? REQUEST_STATUS_PENDING),
            'createdAt' => (string) ($request['createdAt'] ?? ''),
        ];
    }, $requests);

    respond(['requests' => $requests]);
}

if ($action === 'admin_approve_request') {
    requireAdminUser();

    $payload = requestJson();
    $requestId = trim((string) ($payload['requestId'] ?? ''));
    $overrideRole = trim((string) ($payload['role'] ?? ''));

    if ($requestId === '') {
        respond(['error' => 'Missing request id.'], 400);
    }

    $store = loadUserStore();
    $requestIndex = findRequestIndexById($store['requests'], $requestId);
    if ($requestIndex === null) {
        respond(['error' => 'Request not found.'], 404);
    }

    $request = $store['requests'][$requestIndex];
    if (($request['status'] ?? '') !== REQUEST_STATUS_PENDING) {
        respond(['error' => 'Request is no longer pending.'], 409);
    }

    $role = $overrideRole !== '' ? $overrideRole : (string) ($request['role'] ?? USER_ROLE_SUB_AUTHOR);
    if (!in_array($role, [USER_ROLE_PRIMARY_AUTHOR, USER_ROLE_SUB_AUTHOR, USER_ROLE_REVIEWER], true)) {
        respond(['error' => 'Invalid role for approved account.'], 400);
    }

    if (findUserIndexByUsername($store['users'], (string) ($request['usernameLower'] ?? '')) !== null) {
        respond(['error' => 'A user with this username already exists.'], 409);
    }

    $now = gmdate(DATE_ATOM);
    $store['users'][] = [
        'id' => 'user-' . bin2hex(random_bytes(8)),
        'username' => (string) ($request['username'] ?? ''),
        'usernameLower' => (string) ($request['usernameLower'] ?? normalizeUsername((string) ($request['username'] ?? ''))),
        'realName' => (string) ($request['realName'] ?? ''),
        'email' => (string) ($request['email'] ?? ''),
        'status' => USER_STATUS_ACTIVE,
        'lastLoginAt' => null,
        'role' => $role,
        'passwordHash' => (string) ($request['passwordHash'] ?? ''),
        'createdAt' => $now,
        'updatedAt' => $now,
    ];

    array_splice($store['requests'], $requestIndex, 1);
    saveUserStore($store);

    respond(['ok' => true]);
}

if ($action === 'admin_reject_request') {
    requireAdminUser();

    $payload = requestJson();
    $requestId = trim((string) ($payload['requestId'] ?? ''));
    if ($requestId === '') {
        respond(['error' => 'Missing request id.'], 400);
    }

    $store = loadUserStore();
    $requestIndex = findRequestIndexById($store['requests'], $requestId);
    if ($requestIndex === null) {
        respond(['error' => 'Request not found.'], 404);
    }

    array_splice($store['requests'], $requestIndex, 1);
    saveUserStore($store);

    respond(['ok' => true]);
}

if ($action === 'admin_set_user_status') {
    $adminUser = requireAdminUser();

    $payload = requestJson();
    $userId = trim((string) ($payload['userId'] ?? ''));
    $status = trim((string) ($payload['status'] ?? ''));

    if ($userId === '' || !in_array($status, [USER_STATUS_ACTIVE, USER_STATUS_BANNED], true)) {
        respond(['error' => 'Invalid user status request.'], 400);
    }

    $store = loadUserStore();
    $index = findUserIndexById($store['users'], $userId);
    if ($index === null) {
        respond(['error' => 'User not found.'], 404);
    }

    if (($store['users'][$index]['id'] ?? '') === ($adminUser['id'] ?? '')) {
        respond(['error' => 'Admins cannot change their own status.'], 400);
    }

    $store['users'][$index]['status'] = $status;
    $store['users'][$index]['updatedAt'] = gmdate(DATE_ATOM);
    saveUserStore($store);

    respond(['ok' => true]);
}

if ($action === 'admin_delete_user') {
    $adminUser = requireAdminUser();

    $payload = requestJson();
    $userId = trim((string) ($payload['userId'] ?? ''));

    if ($userId === '') {
        respond(['error' => 'Missing user id.'], 400);
    }

    $store = loadUserStore();
    $index = findUserIndexById($store['users'], $userId);
    if ($index === null) {
        respond(['error' => 'User not found.'], 404);
    }

    if (($store['users'][$index]['id'] ?? '') === ($adminUser['id'] ?? '')) {
        respond(['error' => 'Admins cannot delete their own account.'], 400);
    }

    array_splice($store['users'], $index, 1);
    saveUserStore($store);

    respond(['ok' => true]);
}

if ($action === 'admin_delete_project') {
    requireAdminUser();

    writeProject(defaultProject());

    $items = scandir(ASSETS_DIR);
    if (is_array($items)) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = ASSETS_DIR . '/' . $item;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    respond(['ok' => true]);
}

respond(['error' => 'Unknown action.'], 404);

function ensureStorage(): void
{
    if (!is_dir(STORAGE_DIR)) {
        mkdir(STORAGE_DIR, STORAGE_DIR_PERMISSIONS, true);
    }
    if (!is_dir(ASSETS_DIR)) {
        mkdir(ASSETS_DIR, STORAGE_DIR_PERMISSIONS, true);
    }
    if (!is_file(PROJECT_FILE)) {
        writeProject(defaultProject());
    }
    ensureUserStore();
}

function ensureUserStore(): void
{
    $store = is_file(USERS_FILE) ? loadUserStore() : defaultUserStore();

    if (findUserIndexByUsername($store['users'], normalizeUsername(DEFAULT_ADMIN_USERNAME)) === null) {
        $now = gmdate(DATE_ATOM);
        $store['users'][] = [
            'id' => 'user-admin',
            'username' => DEFAULT_ADMIN_USERNAME,
            'usernameLower' => normalizeUsername(DEFAULT_ADMIN_USERNAME),
            'realName' => 'System Administrator',
            'email' => 'admin@local.lorebinder',
            'status' => USER_STATUS_ACTIVE,
            'lastLoginAt' => null,
            'role' => USER_ROLE_ADMIN,
            'passwordHash' => password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT),
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
    }

    saveUserStore($store);
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
        imagejpeg($image, $path, JPEG_QUALITY);
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
        imagepng($image, $path, PNG_COMPRESSION);
        imagedestroy($image);
        return;
    }

    if ($extension === 'webp' && function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
        $image = @imagecreatefromwebp($path);
        if ($image === false) {
            return;
        }
        imagewebp($image, $path, WEBP_QUALITY);
        imagedestroy($image);
    }
}

function defaultUserStore(): array
{
    return [
        'users' => [],
        'requests' => [],
    ];
}

function loadUserStore(): array
{
    if (!is_file(USERS_FILE)) {
        return defaultUserStore();
    }

    $raw = (string) file_get_contents(USERS_FILE);
    $decoded = decryptUserPayload($raw);

    if (!is_array($decoded)) {
        return defaultUserStore();
    }

    $users = is_array($decoded['users'] ?? null) ? $decoded['users'] : [];
    $requests = is_array($decoded['requests'] ?? null) ? $decoded['requests'] : [];

    return [
        'users' => $users,
        'requests' => $requests,
    ];
}

function saveUserStore(array $store): void
{
    $payload = [
        'users' => array_values(is_array($store['users'] ?? null) ? $store['users'] : []),
        'requests' => array_values(is_array($store['requests'] ?? null) ? $store['requests'] : []),
    ];

    file_put_contents(USERS_FILE, encryptUserPayload($payload));
}

function encryptUserPayload(array $payload): string
{
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL is required for encrypted user storage.');
    }

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Unable to encode user store payload.');
    }

    $cipher = 'aes-256-cbc';
    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = random_bytes($ivLength);

    $encrypted = openssl_encrypt($json, $cipher, usersEncryptionKey(), OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        throw new RuntimeException('Unable to encrypt user store payload.');
    }

    $envelope = [
        'iv' => base64_encode($iv),
        'data' => base64_encode($encrypted),
    ];

    return json_encode($envelope, JSON_UNESCAPED_SLASHES) ?: '';
}

function decryptUserPayload(string $raw): ?array
{
    if (!function_exists('openssl_decrypt')) {
        return null;
    }

    $envelope = json_decode($raw, true);
    if (!is_array($envelope)) {
        return null;
    }

    $iv = base64_decode((string) ($envelope['iv'] ?? ''), true);
    $data = base64_decode((string) ($envelope['data'] ?? ''), true);

    if ($iv === false || $data === false) {
        return null;
    }

    $decrypted = openssl_decrypt($data, 'aes-256-cbc', usersEncryptionKey(), OPENSSL_RAW_DATA, $iv);
    if (!is_string($decrypted)) {
        return null;
    }

    $decoded = json_decode($decrypted, true);
    return is_array($decoded) ? $decoded : null;
}

function usersEncryptionKey(): string
{
    $configured = trim((string) getenv(USERS_KEY_ENV));
    if ($configured !== '') {
        return hash('sha256', $configured, true);
    }

    if (!is_file(USERS_KEY_FILE)) {
        $generated = base64_encode(random_bytes(32));
        file_put_contents(USERS_KEY_FILE, $generated, LOCK_EX);
        if (!chmod(USERS_KEY_FILE, 0600)) {
            throw new RuntimeException('Unable to secure user encryption key permissions.');
        }
    }

    $stored = trim((string) file_get_contents(USERS_KEY_FILE));
    $decoded = base64_decode($stored, true);
    if ($decoded === false || strlen($decoded) < 32) {
        throw new RuntimeException('User encryption key is invalid or corrupted.');
    }

    return substr($decoded, 0, 32);
}

function normalizeUsername(string $username): string
{
    return strtolower(trim($username));
}

function isValidUsername(string $username): bool
{
    $length = mb_strlen($username);
    if ($length < 3 || $length > 40) {
        return false;
    }
    return (bool) preg_match('/^[a-zA-Z0-9._-]+$/', $username);
}

function findUserIndexByUsername(array $users, string $usernameLower): ?int
{
    foreach ($users as $index => $user) {
        if (($user['usernameLower'] ?? '') === $usernameLower) {
            return $index;
        }
    }
    return null;
}

function findUserIndexById(array $users, string $id): ?int
{
    foreach ($users as $index => $user) {
        if (($user['id'] ?? '') === $id) {
            return $index;
        }
    }
    return null;
}

function findRequestIndexById(array $requests, string $id): ?int
{
    foreach ($requests as $index => $request) {
        if (($request['id'] ?? '') === $id) {
            return $index;
        }
    }
    return null;
}

function publicUserProfile(array $user): array
{
    return [
        'id' => (string) ($user['id'] ?? ''),
        'username' => (string) ($user['username'] ?? ''),
        'realName' => (string) ($user['realName'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'status' => (string) ($user['status'] ?? USER_STATUS_ACTIVE),
        'lastLoginAt' => $user['lastLoginAt'] ?? null,
        'role' => (string) ($user['role'] ?? USER_ROLE_SUB_AUTHOR),
    ];
}

function currentSessionUser(): ?array
{
    $userId = (string) ($_SESSION['lorebinder_user_id'] ?? '');
    if ($userId === '') {
        return null;
    }

    $store = loadUserStore();
    $index = findUserIndexById($store['users'], $userId);
    if ($index === null) {
        unset($_SESSION['lorebinder_user_id']);
        return null;
    }

    $user = $store['users'][$index];
    if (($user['status'] ?? USER_STATUS_ACTIVE) !== USER_STATUS_ACTIVE) {
        unset($_SESSION['lorebinder_user_id']);
        return null;
    }

    return publicUserProfile($user);
}

function requireActiveSessionUser(): array
{
    $sessionUser = currentSessionUser();
    if ($sessionUser === null) {
        respond(['error' => 'Authentication required.'], 401);
    }

    if (($sessionUser['status'] ?? USER_STATUS_ACTIVE) !== USER_STATUS_ACTIVE) {
        respond(['error' => 'Account is not active.'], 403);
    }

    return $sessionUser;
}

function requireAdminUser(): array
{
    $sessionUser = requireActiveSessionUser();
    if (($sessionUser['role'] ?? '') !== USER_ROLE_ADMIN) {
        respond(['error' => 'Admin privileges are required.'], 403);
    }
    return $sessionUser;
}
