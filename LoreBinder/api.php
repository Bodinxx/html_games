<?php
declare(strict_types=1);

session_name('lorebinder_sid');
session_start();

header('Content-Type: application/json');

const STORAGE_DIR = __DIR__ . '/storage';
const ASSETS_DIR = STORAGE_DIR . '/assets';
const PROJECTS_DIR = STORAGE_DIR . '/projects';
const PROJECT_FILE = STORAGE_DIR . '/project.json';
const USERS_FILE = STORAGE_DIR . '/users.enc';
const USERS_KEY_FILE = STORAGE_DIR . '/users.key';
const USERS_KEY_ENV = 'LOREBINDER_USERS_KEY';
const MAIL_LOG_FILE = STORAGE_DIR . '/mail.outbox.json';
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
const PASSWORD_RESET_EXPIRY_SECONDS = 3600;
const SESSION_COOKIE_PAST_EXPIRY_SECONDS = 42000;
const PROJECT_STATUS_ACTIVE = 'active';
const PROJECT_STATUS_ARCHIVED = 'archived';
const DEFAULT_THEME_KEY = 'dnd';
const DEFAULT_INTERFACE_THEME = 'midnight';
const APP_URL_ENV = 'LOREBINDER_APP_URL';
const DEFAULT_FONT_SCALE = 1.0;

ensureStorage();

$action = $_GET['action'] ?? 'state';

if ($action === 'auth_state') {
    $user = currentSessionUserPayload();
    respond([
        'user' => $user,
        'themePresets' => themePresetSummaries(),
        'interfaceThemes' => interfaceThemeOptions(),
    ]);
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

    ensureProjectForUserId((string) ($user['id'] ?? ''));

    session_regenerate_id(true);
    $_SESSION['lorebinder_user_id'] = $store['users'][$userIndex]['id'];

    respond(['ok' => true, 'user' => sessionUserPayload($store['users'][$userIndex])]);
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        $cookieOptions = [
            'expires' => time() - SESSION_COOKIE_PAST_EXPIRY_SECONDS,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
        ];
        if (isset($params['samesite']) && is_string($params['samesite']) && $params['samesite'] !== '') {
            $cookieOptions['samesite'] = $params['samesite'];
        }
        setcookie(session_name(), '', $cookieOptions);
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
    if (!in_array($role, userAssignableRoles(), true)) {
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

if ($action === 'request_password_reset') {
    $payload = requestJson();
    $email = strtolower(trim((string) ($payload['email'] ?? '')));

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        issuePasswordResetForEmail($email);
    }

    respond(['ok' => true]);
}

if ($action === 'request_password_reset_current') {
    $user = requireActiveSessionUserRecord();
    issuePasswordResetForEmail(strtolower((string) ($user['email'] ?? '')));
    respond(['ok' => true]);
}

if ($action === 'complete_password_reset') {
    $payload = requestJson();
    $token = trim((string) ($payload['token'] ?? ''));
    $password = (string) ($payload['password'] ?? '');

    if ($token === '' || mb_strlen($password) < 8 || mb_strlen($password) > 120) {
        respond(['error' => 'A valid reset token and password are required.'], 400);
    }

    $store = loadUserStore();
    $resetIndex = findValidPasswordResetIndex($store['passwordResets'], $token);
    if ($resetIndex === null) {
        respond(['error' => 'This password reset link is invalid or has expired.'], 400);
    }

    $resetEntry = $store['passwordResets'][$resetIndex];
    $userIndex = findUserIndexById($store['users'], (string) ($resetEntry['userId'] ?? ''));
    if ($userIndex === null) {
        respond(['error' => 'The requested account no longer exists.'], 404);
    }

    $now = gmdate(DATE_ATOM);
    $store['users'][$userIndex]['passwordHash'] = password_hash($password, PASSWORD_DEFAULT);
    $store['users'][$userIndex]['updatedAt'] = $now;

    foreach ($store['passwordResets'] as &$entry) {
        if (($entry['userId'] ?? '') === ($resetEntry['userId'] ?? '')) {
            $entry['usedAt'] = $now;
        }
    }
    unset($entry);

    saveUserStore($store);
    respond(['ok' => true]);
}

if ($action === 'public_profile') {
    $username = trim((string) ($_GET['username'] ?? ''));
    if ($username === '') {
        respond(['error' => 'Username is required.'], 400);
    }

    $profile = publicProfileByUsername($username);
    if ($profile === null) {
        respond(['error' => 'Profile not found.'], 404);
    }

    respond(['profile' => $profile]);
}

if ($action === 'state') {
    $user = requireActiveSessionUserRecord();
    respond(buildStatePayload($user));
}

if ($action === 'save_state') {
    $user = requireActiveSessionUserRecord();
    $payload = requestJson();
    $project = $payload['project'] ?? null;

    if (!is_array($project)) {
        respond(['error' => 'Invalid project payload.'], 400);
    }

    $projectId = trim((string) ($project['id'] ?? ''));
    if ($projectId === '') {
        respond(['error' => 'Missing project id.'], 400);
    }

    $projectStore = readProjectStore();
    $index = findProjectIndexById($projectStore['projects'], $projectId);
    if ($index === null) {
        respond(['error' => 'Project not found.'], 404);
    }

    $existing = $projectStore['projects'][$index];
    if (!canAccessProject($existing, $user)) {
        respond(['error' => 'You do not have access to this project.'], 403);
    }

    $normalized = normalizeProject($project, (string) ($existing['ownerUserId'] ?? ($user['id'] ?? '')));
    $normalized['id'] = $existing['id'];
    $normalized['ownerUserId'] = $existing['ownerUserId'];
    $normalized['createdAt'] = $existing['createdAt'] ?? gmdate(DATE_ATOM);
    $normalized['updatedAt'] = gmdate(DATE_ATOM);
    $projectStore['projects'][$index] = $normalized;
    $projectStore['activeProjectIdByUser'][(string) ($user['id'] ?? '')] = $projectId;
    writeProjectStore($projectStore);
    syncProjectDocumentFiles($normalized);

    respond(buildStatePayload($user));
}

if ($action === 'create_project') {
    $user = requireActiveSessionUserRecord();
    $payload = requestJson();
    $title = trim((string) ($payload['title'] ?? 'New Project'));
    $themeKey = trim((string) ($payload['themeKey'] ?? DEFAULT_THEME_KEY));
    if ($title === '') {
        $title = 'New Project';
    }
    if (!themePresetExists($themeKey)) {
        $themeKey = DEFAULT_THEME_KEY;
    }

    $projectStore = readProjectStore();
    $project = defaultProject((string) ($user['id'] ?? ''), $title, $themeKey);
    $projectStore['projects'][] = $project;
    $projectStore['activeProjectIdByUser'][(string) ($user['id'] ?? '')] = $project['id'];
    writeProjectStore($projectStore);

    respond(buildStatePayload($user));
}

if ($action === 'switch_project') {
    $user = requireActiveSessionUserRecord();
    $payload = requestJson();
    $projectId = trim((string) ($payload['projectId'] ?? ''));
    if ($projectId === '') {
        respond(['error' => 'Project id is required.'], 400);
    }

    $projectStore = readProjectStore();
    $project = findAccessibleProjectById($projectStore['projects'], $projectId, $user);
    if ($project === null) {
        respond(['error' => 'Project not found.'], 404);
    }

    $projectStore['activeProjectIdByUser'][(string) ($user['id'] ?? '')] = $projectId;
    writeProjectStore($projectStore);

    respond(buildStatePayload($user, $projectId));
}

if ($action === 'archive_project') {
    $user = requireActiveSessionUserRecord();
    $payload = requestJson();
    $projectId = trim((string) ($payload['projectId'] ?? ''));
    if ($projectId === '') {
        respond(['error' => 'Project id is required.'], 400);
    }

    $projectStore = readProjectStore();
    $index = findProjectIndexById($projectStore['projects'], $projectId);
    if ($index === null) {
        respond(['error' => 'Project not found.'], 404);
    }

    $project = $projectStore['projects'][$index];
    if (!canManageProject($project, $user)) {
        respond(['error' => 'You do not have permission to archive this project.'], 403);
    }

    $projectStore['projects'][$index]['status'] = ($project['status'] ?? PROJECT_STATUS_ACTIVE) === PROJECT_STATUS_ARCHIVED
        ? PROJECT_STATUS_ACTIVE
        : PROJECT_STATUS_ARCHIVED;
    $projectStore['projects'][$index]['updatedAt'] = gmdate(DATE_ATOM);
    $projectStore = repairProjectSelections($projectStore);
    writeProjectStore($projectStore);

    respond(buildStatePayload($user, $projectId));
}

if ($action === 'delete_project') {
    $user = requireActiveSessionUserRecord();
    $payload = requestJson();
    $projectId = trim((string) ($payload['projectId'] ?? ''));
    if ($projectId === '') {
        respond(['error' => 'Project id is required.'], 400);
    }

    $projectStore = readProjectStore();
    $index = findProjectIndexById($projectStore['projects'], $projectId);
    if ($index === null) {
        respond(['error' => 'Project not found.'], 404);
    }

    $project = $projectStore['projects'][$index];
    if (!canManageProject($project, $user)) {
        respond(['error' => 'You do not have permission to delete this project.'], 403);
    }

    $ownerId = (string) ($project['ownerUserId'] ?? '');
    array_splice($projectStore['projects'], $index, 1);
    deleteProjectAssets($projectId);
    deleteProjectDocuments($projectId);
    $projectStore = repairProjectSelections($projectStore);
    writeProjectStore($projectStore);

    if ($ownerId !== '') {
        ensureProjectForUserId($ownerId);
    }

    respond(buildStatePayload($user));
}

if ($action === 'upload_asset') {
    $user = requireActiveSessionUserRecord();
    $projectId = trim((string) ($_POST['projectId'] ?? ''));
    $project = requireAccessibleProjectById($projectId, $user);

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

    $targetName = uniqueAssetName((string) ($project['id'] ?? ''), $sanitized);
    $assetsDir = ensureProjectAssetsDir((string) ($project['id'] ?? ''));
    $targetPath = $assetsDir . '/' . $targetName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        respond(['error' => 'Unable to store uploaded asset.'], 500);
    }

    optimizeAsset($targetPath, $extension);
    respond([
        'ok' => true,
        'asset' => assetMeta((string) ($project['id'] ?? ''), $targetName),
        'assets' => listAssets((string) ($project['id'] ?? '')),
    ]);
}

if ($action === 'delete_asset') {
    $user = requireActiveSessionUserRecord();
    $payload = requestJson();
    $projectId = trim((string) ($payload['projectId'] ?? ''));
    $filename = sanitizeFilename((string) ($payload['filename'] ?? ''));
    $project = requireAccessibleProjectById($projectId, $user);

    if ($filename === '') {
        respond(['error' => 'Missing filename.'], 400);
    }

    $fullPath = ensureProjectAssetsDir((string) ($project['id'] ?? '')) . '/' . $filename;
    if (!is_file($fullPath)) {
        respond(['error' => 'Asset not found.'], 404);
    }
    if (!unlink($fullPath)) {
        respond(['error' => 'Unable to delete asset.'], 500);
    }

    respond(['ok' => true, 'assets' => listAssets((string) ($project['id'] ?? ''))]);
}

if ($action === 'rename_asset') {
    $user = requireActiveSessionUserRecord();
    $payload = requestJson();
    $projectId = trim((string) ($payload['projectId'] ?? ''));
    $oldName = sanitizeFilename((string) ($payload['oldName'] ?? ''));
    $newNameInput = sanitizeFilename((string) ($payload['newName'] ?? ''));
    $project = requireAccessibleProjectById($projectId, $user);

    if ($oldName === '' || $newNameInput === '') {
        respond(['error' => 'Missing rename fields.'], 400);
    }

    $assetsDir = ensureProjectAssetsDir((string) ($project['id'] ?? ''));
    $oldPath = $assetsDir . '/' . $oldName;
    if (!is_file($oldPath)) {
        respond(['error' => 'Asset not found.'], 404);
    }

    $ext = strtolower((string) pathinfo($newNameInput, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, ALLOWED_EXTENSIONS, true)) {
        respond(['error' => 'Unsupported asset format.'], 400);
    }

    $newName = uniqueAssetName((string) ($project['id'] ?? ''), $newNameInput);
    $newPath = $assetsDir . '/' . $newName;
    if (!rename($oldPath, $newPath)) {
        respond(['error' => 'Unable to rename asset.'], 500);
    }

    respond(['ok' => true, 'assets' => listAssets((string) ($project['id'] ?? ''))]);
}

if ($action === 'update_profile') {
    $user = requireActiveSessionUserRecord();
    $payload = requestJson();
    $aboutMe = trim((string) ($payload['aboutMe'] ?? ''));
    $website = trim((string) ($payload['website'] ?? ''));
    $gamesPlayed = normalizeGamesPlayed($payload['gamesPlayed'] ?? []);

    if (mb_strlen($aboutMe) > 800) {
        respond(['error' => 'About Me must be 800 characters or fewer.'], 400);
    }
    if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
        respond(['error' => 'Website must be a valid URL.'], 400);
    }

    $store = loadUserStore();
    $index = findUserIndexById($store['users'], (string) ($user['id'] ?? ''));
    if ($index === null) {
        respond(['error' => 'User not found.'], 404);
    }

    $store['users'][$index]['profile'] = [
        'aboutMe' => $aboutMe,
        'website' => $website,
        'gamesPlayed' => $gamesPlayed,
    ];
    $store['users'][$index]['updatedAt'] = gmdate(DATE_ATOM);
    saveUserStore($store);

    respond(['ok' => true, 'user' => sessionUserPayload($store['users'][$index])]);
}

if ($action === 'update_preferences') {
    $user = requireActiveSessionUserRecord();
    $payload = requestJson();
    $preferences = normalizeUserPreferences($payload);

    $store = loadUserStore();
    $index = findUserIndexById($store['users'], (string) ($user['id'] ?? ''));
    if ($index === null) {
        respond(['error' => 'User not found.'], 404);
    }

    $store['users'][$index]['preferences'] = $preferences;
    $store['users'][$index]['updatedAt'] = gmdate(DATE_ATOM);
    saveUserStore($store);

    respond(['ok' => true, 'user' => sessionUserPayload($store['users'][$index])]);
}

if ($action === 'admin_list_users') {
    requireAdminUserRecord();
    $store = loadUserStore();
    $profiles = array_map(static function (array $user): array {
        $profile = publicUserProfile($user);
        $profile['updatedAt'] = (string) ($user['updatedAt'] ?? '');
        return $profile;
    }, $store['users']);
    respond(['users' => $profiles]);
}

if ($action === 'admin_list_requests') {
    requireAdminUserRecord();
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

if ($action === 'admin_list_projects') {
    requireAdminUserRecord();
    $projectStore = readProjectStore();
    $usernames = usernamesById(loadUserStore()['users']);
    $projects = array_map(static fn (array $project): array => projectSummary($project, $usernames), $projectStore['projects']);
    respond(['projects' => $projects]);
}

if ($action === 'admin_approve_request') {
    requireAdminUserRecord();
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
    if (!in_array($role, userAssignableRoles(), true)) {
        respond(['error' => 'Invalid role for approved account.'], 400);
    }

    if (findUserIndexByUsername($store['users'], (string) ($request['usernameLower'] ?? '')) !== null) {
        respond(['error' => 'A user with this username already exists.'], 409);
    }

    $now = gmdate(DATE_ATOM);
    $newUser = normalizeUserRecord([
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
    ]);

    $store['users'][] = $newUser;
    array_splice($store['requests'], $requestIndex, 1);
    saveUserStore($store);
    ensureProjectForUserId((string) ($newUser['id'] ?? ''));

    respond(['ok' => true]);
}

if ($action === 'admin_reject_request') {
    requireAdminUserRecord();
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
    $adminUser = requireAdminUserRecord();
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

if ($action === 'admin_set_user_role') {
    $adminUser = requireAdminUserRecord();
    $payload = requestJson();
    $userId = trim((string) ($payload['userId'] ?? ''));
    $role = trim((string) ($payload['role'] ?? ''));

    if ($userId === '' || !in_array($role, adminAssignableRoles(), true)) {
        respond(['error' => 'Invalid user role request.'], 400);
    }

    $store = loadUserStore();
    $index = findUserIndexById($store['users'], $userId);
    if ($index === null) {
        respond(['error' => 'User not found.'], 404);
    }
    if (($store['users'][$index]['id'] ?? '') === ($adminUser['id'] ?? '')) {
        respond(['error' => 'Admins cannot change their own role.'], 400);
    }

    $store['users'][$index]['role'] = $role;
    $store['users'][$index]['updatedAt'] = gmdate(DATE_ATOM);
    saveUserStore($store);
    respond(['ok' => true]);
}

if ($action === 'admin_reset_user_password') {
    requireAdminUserRecord();
    $payload = requestJson();
    $userId = trim((string) ($payload['userId'] ?? ''));
    $password = (string) ($payload['password'] ?? '');

    if ($userId === '' || mb_strlen($password) < 8 || mb_strlen($password) > 120) {
        respond(['error' => 'A new password between 8 and 120 characters is required.'], 400);
    }

    $store = loadUserStore();
    $index = findUserIndexById($store['users'], $userId);
    if ($index === null) {
        respond(['error' => 'User not found.'], 404);
    }

    $store['users'][$index]['passwordHash'] = password_hash($password, PASSWORD_DEFAULT);
    $store['users'][$index]['updatedAt'] = gmdate(DATE_ATOM);
    foreach ($store['passwordResets'] as &$entry) {
        if (($entry['userId'] ?? '') === $userId) {
            $entry['usedAt'] = gmdate(DATE_ATOM);
        }
    }
    unset($entry);
    saveUserStore($store);
    respond(['ok' => true]);
}

if ($action === 'admin_delete_user') {
    $adminUser = requireAdminUserRecord();
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
    $store['passwordResets'] = array_values(array_filter(
        $store['passwordResets'],
        static fn (array $reset): bool => ($reset['userId'] ?? '') !== $userId
    ));
    saveUserStore($store);

    $projectStore = readProjectStore();
    foreach ($projectStore['projects'] as $projectIndex => $project) {
        if (($project['ownerUserId'] ?? '') === $userId) {
            deleteProjectAssets((string) ($project['id'] ?? ''));
            deleteProjectDocuments((string) ($project['id'] ?? ''));
            unset($projectStore['projects'][$projectIndex]);
        }
    }
    unset($projectStore['activeProjectIdByUser'][$userId]);
    $projectStore['projects'] = array_values($projectStore['projects']);
    $projectStore = repairProjectSelections($projectStore);
    writeProjectStore($projectStore);

    respond(['ok' => true]);
}

if ($action === 'admin_delete_project') {
    requireAdminUserRecord();
    $payload = requestJson();
    $projectId = trim((string) ($payload['projectId'] ?? ''));
    if ($projectId === '') {
        respond(['error' => 'Project id is required.'], 400);
    }

    $projectStore = readProjectStore();
    $index = findProjectIndexById($projectStore['projects'], $projectId);
    if ($index === null) {
        respond(['error' => 'Project not found.'], 404);
    }

    array_splice($projectStore['projects'], $index, 1);
    deleteProjectAssets($projectId);
    deleteProjectDocuments($projectId);
    $projectStore = repairProjectSelections($projectStore);
    writeProjectStore($projectStore);

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
    if (!is_dir(PROJECTS_DIR)) {
        mkdir(PROJECTS_DIR, STORAGE_DIR_PERMISSIONS, true);
    }
    if (!is_file(MAIL_LOG_FILE)) {
        file_put_contents(MAIL_LOG_FILE, json_encode([], JSON_PRETTY_PRINT));
    }

    ensureUserStore();
    ensureProjectStore();
}

function ensureUserStore(): void
{
    $store = is_file(USERS_FILE) ? loadUserStore() : defaultUserStore();
    if (findUserIndexByUsername($store['users'], normalizeUsername(DEFAULT_ADMIN_USERNAME)) === null) {
        $now = gmdate(DATE_ATOM);
        $store['users'][] = normalizeUserRecord([
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
        ]);
    }
    saveUserStore($store);
}

function ensureProjectStore(): void
{
    $store = is_file(PROJECT_FILE) ? readProjectStore() : defaultProjectStore();
    $users = loadUserStore()['users'];
    foreach ($users as $user) {
        if (($user['status'] ?? USER_STATUS_ACTIVE) !== USER_STATUS_ACTIVE) {
            continue;
        }
        $store = ensureProjectForUser($store, (string) ($user['id'] ?? ''), (string) ($user['username'] ?? 'Project'));
    }
    writeProjectStore($store);
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

function buildStatePayload(array $user, ?string $preferredProjectId = null): array
{
    $projectStore = readProjectStore();
    $projects = accessibleProjects($projectStore['projects'], $user);
    if ($projects === []) {
        $projectStore = ensureProjectForUser($projectStore, (string) ($user['id'] ?? ''), (string) ($user['username'] ?? 'Project'));
        writeProjectStore($projectStore);
        $projects = accessibleProjects($projectStore['projects'], $user);
    }

    $currentProject = currentProjectForUser($projectStore, $user, $preferredProjectId);
    $usernames = usernamesById(loadUserStore()['users']);

    return [
        'project' => $currentProject,
        'projects' => array_map(static fn (array $project): array => projectSummary($project, $usernames), $projects),
        'assets' => $currentProject ? listAssets((string) ($currentProject['id'] ?? '')) : [],
        'user' => sessionUserPayload(refreshUserRecord((string) ($user['id'] ?? '')) ?? $user),
        'themePresets' => themePresetSummaries(),
        'interfaceThemes' => interfaceThemeOptions(),
    ];
}

function readProjectStore(): array
{
    $raw = is_file(PROJECT_FILE) ? (string) file_get_contents(PROJECT_FILE) : '';
    $decoded = json_decode($raw, true);
    return normalizeProjectStore(is_array($decoded) ? $decoded : []);
}

function writeProjectStore(array $store): void
{
    file_put_contents(PROJECT_FILE, json_encode(normalizeProjectStore($store), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function normalizeProjectStore(array $store): array
{
    if (!array_key_exists('projects', $store) && array_key_exists('tree', $store)) {
        $store = [
            'projects' => [normalizeProject($store, 'user-admin')],
            'activeProjectIdByUser' => ['user-admin' => (string) ($store['id'] ?? 'legacy-project')],
        ];
    }

    $projects = is_array($store['projects'] ?? null) ? $store['projects'] : [];
    $normalizedProjects = [];
    foreach ($projects as $project) {
        if (!is_array($project)) {
            continue;
        }
        $normalizedProjects[] = normalizeProject($project, (string) ($project['ownerUserId'] ?? 'user-admin'));
    }

    $activeProjectIdByUser = [];
    foreach ((array) ($store['activeProjectIdByUser'] ?? []) as $userId => $projectId) {
        if (!is_string($userId) || $userId === '' || !is_string($projectId) || $projectId === '') {
            continue;
        }
        $activeProjectIdByUser[$userId] = $projectId;
    }

    return [
        'projects' => $normalizedProjects,
        'activeProjectIdByUser' => $activeProjectIdByUser,
    ];
}

function normalizeProject(array $project, string $ownerUserId): array
{
    $projectId = trim((string) ($project['id'] ?? ''));
    if ($projectId === '') {
        $projectId = 'project-' . bin2hex(random_bytes(8));
    }

    $themeKey = trim((string) ($project['themeKey'] ?? DEFAULT_THEME_KEY));
    if (!themePresetExists($themeKey)) {
        $themeKey = DEFAULT_THEME_KEY;
    }

    $title = trim((string) ($project['title'] ?? 'Untitled LoreBinder Project'));
    if ($title === '') {
        $title = 'Untitled LoreBinder Project';
    }

    $documents = [];
    foreach ((array) ($project['documents'] ?? []) as $docId => $doc) {
        if (!is_array($doc)) {
            continue;
        }
        $normalizedDocId = trim((string) ($doc['id'] ?? $docId));
        if ($normalizedDocId === '') {
            continue;
        }
        $documents[$normalizedDocId] = [
            'id' => $normalizedDocId,
            'name' => trim((string) ($doc['name'] ?? 'Untitled.md')) ?: 'Untitled.md',
            'content' => (string) ($doc['content'] ?? ''),
            'updatedAt' => (string) ($doc['updatedAt'] ?? gmdate(DATE_ATOM)),
        ];
    }

    $openTabs = array_values(array_filter(
        is_array($project['openTabs'] ?? null) ? $project['openTabs'] : [],
        static fn ($id) => is_string($id) && $id !== ''
    ));

    $activeDocumentId = trim((string) ($project['activeDocumentId'] ?? ''));
    if ($activeDocumentId === '' && $openTabs !== []) {
        $activeDocumentId = (string) $openTabs[0];
    }

    return [
        'id' => $projectId,
        'ownerUserId' => trim((string) ($project['ownerUserId'] ?? $ownerUserId)) ?: $ownerUserId,
        'title' => $title,
        'status' => in_array(($project['status'] ?? PROJECT_STATUS_ACTIVE), [PROJECT_STATUS_ACTIVE, PROJECT_STATUS_ARCHIVED], true)
            ? (string) $project['status']
            : PROJECT_STATUS_ACTIVE,
        'themeKey' => $themeKey,
        'tree' => normalizeProjectTree((array) ($project['tree'] ?? []), $documents),
        'documents' => $documents,
        'openTabs' => $openTabs,
        'activeDocumentId' => $activeDocumentId,
        'styleCss' => (string) ($project['styleCss'] ?? themePresetCss($themeKey)),
        'createdAt' => (string) ($project['createdAt'] ?? gmdate(DATE_ATOM)),
        'updatedAt' => (string) ($project['updatedAt'] ?? gmdate(DATE_ATOM)),
    ];
}

function normalizeProjectTree(array $nodes, array $documents): array
{
    $result = [];
    foreach ($nodes as $node) {
        if (!is_array($node)) {
            continue;
        }
        $id = trim((string) ($node['id'] ?? ''));
        $type = trim((string) ($node['type'] ?? 'document'));
        $name = trim((string) ($node['name'] ?? 'Untitled'));
        if ($id === '' || $name === '') {
            continue;
        }

        $normalized = [
            'id' => $id,
            'type' => $type === 'folder' ? 'folder' : 'document',
            'name' => $name,
            'includeInCompile' => ($node['includeInCompile'] ?? true) !== false,
        ];

        if ($normalized['type'] === 'folder') {
            $normalized['children'] = normalizeProjectTree((array) ($node['children'] ?? []), $documents);
        } else {
            $docId = trim((string) ($node['docId'] ?? ''));
            if ($docId === '' || !isset($documents[$docId])) {
                continue;
            }
            $normalized['docId'] = $docId;
        }

        $result[] = $normalized;
    }
    return $result;
}

function defaultProjectStore(): array
{
    return [
        'projects' => [],
        'activeProjectIdByUser' => [],
    ];
}

function defaultProject(string $ownerUserId, string $title = 'LoreBinder Project', string $themeKey = DEFAULT_THEME_KEY): array
{
    $docId = 'doc-' . bin2hex(random_bytes(6));
    $styleDocId = 'doc-' . bin2hex(random_bytes(6));
    $projectId = 'project-' . bin2hex(random_bytes(8));
    $now = gmdate(DATE_ATOM);
    $baseCss = themePresetCss($themeKey);

    return [
        'id' => $projectId,
        'ownerUserId' => $ownerUserId,
        'title' => $title,
        'status' => PROJECT_STATUS_ACTIVE,
        'themeKey' => $themeKey,
        'tree' => [
            [
                'id' => 'folder-' . bin2hex(random_bytes(4)),
                'type' => 'folder',
                'name' => 'Chapter 1',
                'includeInCompile' => true,
                'children' => [
                    [
                        'id' => 'node-' . bin2hex(random_bytes(4)),
                        'type' => 'document',
                        'name' => 'base.css',
                        'docId' => $styleDocId,
                        'includeInCompile' => false,
                    ],
                    [
                        'id' => 'node-' . bin2hex(random_bytes(4)),
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
                'content' => "# {$title}\n> #### by Author Name\n\n---\n## Introduction\nWelcome to LoreBinder. This starter document follows a Homebrewery-style flow while keeping your project in a multi-file structure.\n\n### Quick Start\n- Write markdown in the left code editor.\n- Edit from the right view editor when you want to work in context.\n- Open `base.css` in the file tree to adjust your project's base styling.\n\n---\n## Chapter 1 | Getting Started\nThis is a sample section for your setting, adventure, or sourcebook.\n\n> ### Sidebar\n> Use sidebars for lore notes, callouts, and reminders.\n\n### Sample Creature\n**Armor Class** 12  \n**Hit Points** 15 (2d8 + 6)  \n**Speed** 30 ft.\n",
                'updatedAt' => $now,
            ],
            $styleDocId => [
                'id' => $styleDocId,
                'name' => 'base.css',
                'content' => $baseCss,
                'updatedAt' => $now,
            ],
        ],
        'openTabs' => [$docId],
        'activeDocumentId' => $docId,
        'styleCss' => $baseCss,
        'createdAt' => $now,
        'updatedAt' => $now,
    ];
}

function accessibleProjects(array $projects, array $user): array
{
    return array_values(array_filter($projects, static fn (array $project): bool => canAccessProject($project, $user)));
}

function currentProjectForUser(array $store, array $user, ?string $preferredProjectId = null): ?array
{
    $projects = accessibleProjects($store['projects'], $user);
    if ($projects === []) {
        return null;
    }

    $targetId = $preferredProjectId ?: (string) ($store['activeProjectIdByUser'][$user['id']] ?? '');
    foreach ($projects as $project) {
        if (($project['id'] ?? '') === $targetId) {
            return $project;
        }
    }

    foreach ($projects as $project) {
        if (($project['status'] ?? PROJECT_STATUS_ACTIVE) === PROJECT_STATUS_ACTIVE) {
            return $project;
        }
    }

    return $projects[0];
}

function ensureProjectForUser(array $store, string $userId, string $username): array
{
    if ($userId === '') {
        return $store;
    }

    $ownedProjects = array_values(array_filter(
        $store['projects'],
        static fn (array $project): bool => ($project['ownerUserId'] ?? '') === $userId
    ));

    if ($ownedProjects === []) {
        $title = $username === DEFAULT_ADMIN_USERNAME ? 'LoreBinder Project' : $username . "'s Project";
        $project = defaultProject($userId, $title, DEFAULT_THEME_KEY);
        $store['projects'][] = $project;
        $store['activeProjectIdByUser'][$userId] = $project['id'];
        return $store;
    }

    $activeId = (string) ($store['activeProjectIdByUser'][$userId] ?? '');
    foreach ($ownedProjects as $project) {
        if (($project['id'] ?? '') === $activeId) {
            return $store;
        }
    }

    $store['activeProjectIdByUser'][$userId] = (string) ($ownedProjects[0]['id'] ?? '');
    return $store;
}

function ensureProjectForUserId(string $userId): void
{
    $user = refreshUserRecord($userId);
    if ($user === null) {
        return;
    }

    $store = readProjectStore();
    $updated = ensureProjectForUser($store, $userId, (string) ($user['username'] ?? 'Project'));
    writeProjectStore($updated);
}

function repairProjectSelections(array $store): array
{
    $validSelections = [];
    $owners = [];

    foreach ($store['projects'] as $project) {
        $ownerId = (string) ($project['ownerUserId'] ?? '');
        if ($ownerId === '') {
            continue;
        }
        $owners[$ownerId][] = $project;
    }

    foreach ($owners as $ownerId => $projects) {
        $selected = (string) ($store['activeProjectIdByUser'][$ownerId] ?? '');
        foreach ($projects as $project) {
            if (($project['id'] ?? '') === $selected) {
                $validSelections[$ownerId] = $selected;
                continue 2;
            }
        }

        foreach ($projects as $project) {
            if (($project['status'] ?? PROJECT_STATUS_ACTIVE) === PROJECT_STATUS_ACTIVE) {
                $validSelections[$ownerId] = (string) ($project['id'] ?? '');
                continue 2;
            }
        }

        $validSelections[$ownerId] = (string) ($projects[0]['id'] ?? '');
    }

    $store['activeProjectIdByUser'] = $validSelections;
    return $store;
}

function findProjectIndexById(array $projects, string $projectId): ?int
{
    foreach ($projects as $index => $project) {
        if (($project['id'] ?? '') === $projectId) {
            return $index;
        }
    }
    return null;
}

function findAccessibleProjectById(array $projects, string $projectId, array $user): ?array
{
    foreach ($projects as $project) {
        if (($project['id'] ?? '') === $projectId && canAccessProject($project, $user)) {
            return $project;
        }
    }
    return null;
}

function requireAccessibleProjectById(string $projectId, array $user): array
{
    if ($projectId === '') {
        respond(['error' => 'Project id is required.'], 400);
    }

    $project = findAccessibleProjectById(readProjectStore()['projects'], $projectId, $user);
    if ($project === null) {
        respond(['error' => 'Project not found.'], 404);
    }

    return $project;
}

function canAccessProject(array $project, array $user): bool
{
    return ($project['ownerUserId'] ?? '') === ($user['id'] ?? '') || ($user['role'] ?? '') === USER_ROLE_ADMIN;
}

function canManageProject(array $project, array $user): bool
{
    return canAccessProject($project, $user);
}

function projectSummary(array $project, array $usernamesById): array
{
    $ownerId = (string) ($project['ownerUserId'] ?? '');
    return [
        'id' => (string) ($project['id'] ?? ''),
        'title' => (string) ($project['title'] ?? ''),
        'status' => (string) ($project['status'] ?? PROJECT_STATUS_ACTIVE),
        'themeKey' => (string) ($project['themeKey'] ?? DEFAULT_THEME_KEY),
        'updatedAt' => (string) ($project['updatedAt'] ?? ''),
        'createdAt' => (string) ($project['createdAt'] ?? ''),
        'ownerUserId' => $ownerId,
        'ownerUsername' => $usernamesById[$ownerId] ?? 'Unknown',
    ];
}

function ensureProjectAssetsDir(string $projectId): string
{
    $path = ASSETS_DIR . '/' . $projectId;
    if (!is_dir($path)) {
        mkdir($path, STORAGE_DIR_PERMISSIONS, true);
    }
    return $path;
}

function ensureProjectDocumentsDir(string $projectId): string
{
    $safeProjectId = sanitizePathSegment($projectId, 'project');
    $path = PROJECTS_DIR . '/' . $safeProjectId;
    if (!is_dir($path)) {
        mkdir($path, STORAGE_DIR_PERMISSIONS, true);
    }
    return $path;
}

function sanitizePathSegment(string $value, string $fallback = 'item'): string
{
    $segment = trim(str_replace(['\\', '/'], '_', $value));
    $segment = preg_replace('/[^a-zA-Z0-9._ -]/', '_', $segment) ?? '';
    $segment = trim($segment, " ._\t\n\r\0\x0B");
    return $segment !== '' ? $segment : $fallback;
}

function syncProjectDocumentFiles(array $project): void
{
    $projectId = trim((string) ($project['id'] ?? ''));
    if ($projectId === '') {
        return;
    }

    $baseDir = ensureProjectDocumentsDir($projectId);
    $documents = is_array($project['documents'] ?? null) ? $project['documents'] : [];
    $tree = is_array($project['tree'] ?? null) ? $project['tree'] : [];
    $desiredFiles = [];
    $usedPaths = [];

    $walk = static function (array $nodes, array $segments = []) use (&$walk, $documents, &$desiredFiles, &$usedPaths): void {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            $type = (string) ($node['type'] ?? 'document');
            $name = sanitizePathSegment((string) ($node['name'] ?? ''), $type === 'folder' ? 'folder' : 'document.md');
            if ($type === 'folder') {
                $walk((array) ($node['children'] ?? []), [...$segments, $name]);
                continue;
            }

            $docId = trim((string) ($node['docId'] ?? ''));
            if ($docId === '' || !isset($documents[$docId]) || !is_array($documents[$docId])) {
                continue;
            }

            if (!str_contains($name, '.')) {
                $name .= '.md';
            }

            $relativePath = implode('/', [...$segments, $name]);
            $info = pathinfo($relativePath);
            $dir = $info['dirname'] ?? '';
            $filename = $info['filename'] ?? 'document';
            $extension = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
            $uniquePath = $relativePath;
            $counter = 1;
            while (isset($usedPaths[$uniquePath])) {
                $uniqueName = $filename . '-' . $counter . $extension;
                $uniquePath = ($dir === '.' || $dir === '') ? $uniqueName : $dir . '/' . $uniqueName;
                $counter++;
            }
            $usedPaths[$uniquePath] = true;
            $desiredFiles[$uniquePath] = (string) ($documents[$docId]['content'] ?? '');
        }
    };

    $walk($tree, []);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo) {
            continue;
        }
        $fullPath = $entry->getPathname();
        $relative = ltrim(str_replace('\\', '/', substr($fullPath, strlen($baseDir))), '/');
        if ($entry->isFile()) {
            if (!array_key_exists($relative, $desiredFiles)) {
                @unlink($fullPath);
            }
            continue;
        }
        @rmdir($fullPath);
    }

    foreach ($desiredFiles as $relative => $content) {
        $target = $baseDir . '/' . $relative;
        $directory = dirname($target);
        if (!is_dir($directory)) {
            mkdir($directory, STORAGE_DIR_PERMISSIONS, true);
        }
        file_put_contents($target, $content);
    }
}

function deleteProjectAssets(string $projectId): void
{
    $path = ASSETS_DIR . '/' . $projectId;
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);
    if (is_array($items)) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $filePath = $path . '/' . $item;
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
    }
    @rmdir($path);
}

function deleteProjectDocuments(string $projectId): void
{
    $path = PROJECTS_DIR . '/' . sanitizePathSegment($projectId, 'project');
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo) {
            continue;
        }
        if ($entry->isDir()) {
            @rmdir($entry->getPathname());
        } else {
            @unlink($entry->getPathname());
        }
    }

    @rmdir($path);
}

function sanitizeFilename(string $filename): string
{
    $filename = basename(str_replace('\\', '/', $filename));
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?? '';
    return trim($filename, '._');
}

function uniqueAssetName(string $projectId, string $filename): string
{
    $base = (string) pathinfo($filename, PATHINFO_FILENAME);
    $ext = (string) pathinfo($filename, PATHINFO_EXTENSION);
    $base = $base !== '' ? $base : 'asset';
    $candidate = $base . ($ext !== '' ? '.' . $ext : '');
    $counter = 1;
    $directory = ensureProjectAssetsDir($projectId);

    while (is_file($directory . '/' . $candidate)) {
        $candidate = sprintf('%s-%d%s', $base, $counter, $ext !== '' ? '.' . $ext : '');
        $counter++;
    }

    return $candidate;
}

function listAssets(string $projectId): array
{
    $assets = [];
    $directory = ensureProjectAssetsDir($projectId);
    $items = scandir($directory);
    if (!is_array($items)) {
        return [];
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $meta = assetMeta($projectId, $item);
        if ($meta !== null) {
            $assets[] = $meta;
        }
    }

    usort($assets, static fn (array $a, array $b) => strcmp($a['name'], $b['name']));
    return $assets;
}

function assetMeta(string $projectId, string $filename): ?array
{
    $fullPath = ensureProjectAssetsDir($projectId) . '/' . $filename;
    if (!is_file($fullPath)) {
        return null;
    }

    return [
        'name' => $filename,
        'size' => filesize($fullPath) ?: 0,
        'url' => 'storage/assets/' . rawurlencode($projectId) . '/' . rawurlencode($filename),
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
        'passwordResets' => [],
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

    $users = array_map('normalizeUserRecord', is_array($decoded['users'] ?? null) ? $decoded['users'] : []);
    $requests = is_array($decoded['requests'] ?? null) ? $decoded['requests'] : [];
    $passwordResets = array_values(array_filter(
        is_array($decoded['passwordResets'] ?? null) ? $decoded['passwordResets'] : [],
        static function (array $entry): bool {
            if (($entry['usedAt'] ?? '') !== '') {
                return true;
            }
            $expiresAt = strtotime((string) ($entry['expiresAt'] ?? ''));
            return $expiresAt === false || $expiresAt >= time();
        }
    ));

    return [
        'users' => $users,
        'requests' => array_values($requests),
        'passwordResets' => $passwordResets,
    ];
}

function saveUserStore(array $store): void
{
    $payload = [
        'users' => array_values(array_map('normalizeUserRecord', is_array($store['users'] ?? null) ? $store['users'] : [])),
        'requests' => array_values(is_array($store['requests'] ?? null) ? $store['requests'] : []),
        'passwordResets' => array_values(is_array($store['passwordResets'] ?? null) ? $store['passwordResets'] : []),
    ];

    file_put_contents(USERS_FILE, encryptUserPayload($payload));
}

function normalizeUserRecord(array $user): array
{
    return [
        'id' => trim((string) ($user['id'] ?? '')),
        'username' => trim((string) ($user['username'] ?? '')),
        'usernameLower' => trim((string) ($user['usernameLower'] ?? normalizeUsername((string) ($user['username'] ?? '')))),
        'realName' => trim((string) ($user['realName'] ?? '')),
        'email' => trim((string) ($user['email'] ?? '')),
        'status' => in_array(($user['status'] ?? USER_STATUS_ACTIVE), [USER_STATUS_ACTIVE, USER_STATUS_BANNED], true)
            ? (string) $user['status']
            : USER_STATUS_ACTIVE,
        'lastLoginAt' => $user['lastLoginAt'] ?? null,
        'role' => in_array(($user['role'] ?? USER_ROLE_SUB_AUTHOR), adminAssignableRoles(), true)
            ? (string) $user['role']
            : USER_ROLE_SUB_AUTHOR,
        'passwordHash' => (string) ($user['passwordHash'] ?? ''),
        'preferences' => normalizeUserPreferences((array) ($user['preferences'] ?? [])),
        'profile' => normalizeShareProfile((array) ($user['profile'] ?? [])),
        'createdAt' => (string) ($user['createdAt'] ?? gmdate(DATE_ATOM)),
        'updatedAt' => (string) ($user['updatedAt'] ?? gmdate(DATE_ATOM)),
    ];
}

function normalizeUserPreferences(array $preferences): array
{
    $fontScale = (float) ($preferences['fontScale'] ?? DEFAULT_FONT_SCALE);
    if ($fontScale < 0.85 || $fontScale > 1.4) {
        $fontScale = DEFAULT_FONT_SCALE;
    }

    $interfaceTheme = trim((string) ($preferences['interfaceTheme'] ?? DEFAULT_INTERFACE_THEME));
    if (!array_key_exists($interfaceTheme, interfaceThemeOptions())) {
        $interfaceTheme = DEFAULT_INTERFACE_THEME;
    }

    return [
        'fontScale' => round($fontScale, 2),
        'interfaceTheme' => $interfaceTheme,
    ];
}

function normalizeShareProfile(array $profile): array
{
    return [
        'aboutMe' => trim((string) ($profile['aboutMe'] ?? '')),
        'website' => trim((string) ($profile['website'] ?? '')),
        'gamesPlayed' => normalizeGamesPlayed($profile['gamesPlayed'] ?? []),
    ];
}

function normalizeGamesPlayed($games): array
{
    $values = is_array($games) ? $games : [];
    $result = [];
    foreach ($values as $game) {
        $name = trim((string) $game);
        if ($name === '' || mb_strlen($name) > 60) {
            continue;
        }
        $result[$name] = $name;
    }
    return array_values($result);
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
    if (!is_int($ivLength) || $ivLength <= 0) {
        throw new RuntimeException('Unable to initialize user encryption cipher.');
    }
    $iv = random_bytes($ivLength);
    $encrypted = openssl_encrypt($json, $cipher, usersEncryptionKey(), OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        throw new RuntimeException('Unable to encrypt user store payload.');
    }

    return json_encode([
        'iv' => base64_encode($iv),
        'data' => base64_encode($encrypted),
    ], JSON_UNESCAPED_SLASHES) ?: '';
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

function findValidPasswordResetIndex(array $entries, string $token): ?int
{
    foreach ($entries as $index => $entry) {
        if (($entry['usedAt'] ?? '') !== '') {
            continue;
        }
        $expiresAt = strtotime((string) ($entry['expiresAt'] ?? ''));
        if ($expiresAt !== false && $expiresAt < time()) {
            continue;
        }
        if (password_verify($token, (string) ($entry['tokenHash'] ?? ''))) {
            return $index;
        }
    }
    return null;
}

function usernamesById(array $users): array
{
    $map = [];
    foreach ($users as $user) {
        $map[(string) ($user['id'] ?? '')] = (string) ($user['username'] ?? '');
    }
    return $map;
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

function sessionUserPayload(array $user): array
{
    $payload = publicUserProfile($user);
    $payload['preferences'] = normalizeUserPreferences((array) ($user['preferences'] ?? []));
    $payload['profile'] = normalizeShareProfile((array) ($user['profile'] ?? []));
    return $payload;
}

function currentSessionUserRecord(): ?array
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

    return $user;
}

function currentSessionUserPayload(): ?array
{
    $user = currentSessionUserRecord();
    return $user ? sessionUserPayload($user) : null;
}

function requireActiveSessionUserRecord(): array
{
    $user = currentSessionUserRecord();
    if ($user === null) {
        respond(['error' => 'Authentication required.'], 401);
    }
    if (($user['status'] ?? USER_STATUS_ACTIVE) !== USER_STATUS_ACTIVE) {
        respond(['error' => 'Account is not active.'], 403);
    }
    return $user;
}

function requireAdminUserRecord(): array
{
    $user = requireActiveSessionUserRecord();
    if (($user['role'] ?? '') !== USER_ROLE_ADMIN) {
        respond(['error' => 'Admin privileges are required.'], 403);
    }
    return $user;
}

function refreshUserRecord(string $userId): ?array
{
    $store = loadUserStore();
    $index = findUserIndexById($store['users'], $userId);
    return $index === null ? null : $store['users'][$index];
}

function publicProfileByUsername(string $username): ?array
{
    $store = loadUserStore();
    $index = findUserIndexByUsername($store['users'], normalizeUsername($username));
    if ($index === null) {
        return null;
    }

    $user = $store['users'][$index];
    $projectStore = readProjectStore();
    $projects = array_values(array_filter(
        $projectStore['projects'],
        static fn (array $project): bool => ($project['ownerUserId'] ?? '') === ($user['id'] ?? '')
    ));

    return [
        'username' => (string) ($user['username'] ?? ''),
        'realName' => (string) ($user['realName'] ?? ''),
        'aboutMe' => (string) (($user['profile']['aboutMe'] ?? '')),
        'website' => (string) (($user['profile']['website'] ?? '')),
        'gamesPlayed' => normalizeGamesPlayed($user['profile']['gamesPlayed'] ?? []),
        'projects' => array_map(static function (array $project): array {
            return [
                'title' => (string) ($project['title'] ?? ''),
                'status' => (string) ($project['status'] ?? PROJECT_STATUS_ACTIVE),
                'themeKey' => (string) ($project['themeKey'] ?? DEFAULT_THEME_KEY),
            ];
        }, $projects),
    ];
}

function issuePasswordResetForEmail(string $email): void
{
    $store = loadUserStore();
    foreach ($store['users'] as $index => $user) {
        if (strtolower((string) ($user['email'] ?? '')) !== $email) {
            continue;
        }
        if (($user['status'] ?? USER_STATUS_ACTIVE) !== USER_STATUS_ACTIVE) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $now = gmdate(DATE_ATOM);
        $store['passwordResets'][] = [
            'id' => 'reset-' . bin2hex(random_bytes(8)),
            'userId' => (string) ($user['id'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'tokenHash' => password_hash($token, PASSWORD_DEFAULT),
            'createdAt' => $now,
            'expiresAt' => gmdate(DATE_ATOM, time() + PASSWORD_RESET_EXPIRY_SECONDS),
            'usedAt' => '',
        ];
        saveUserStore($store);
        queuePasswordResetEmail((string) ($user['email'] ?? ''), (string) ($user['username'] ?? ''), $token);
        return;
    }
}

function queuePasswordResetEmail(string $email, string $username, string $token): void
{
    $resetUrl = baseAppUrl() . '?reset=' . rawurlencode($token);
    $subject = 'LoreBinder password reset';
    $expiresInMinutes = max(1, (int) ceil(PASSWORD_RESET_EXPIRY_SECONDS / 60));
    $safeUsername = trim((string) preg_replace('/[\r\n]+/', ' ', $username));
    if ($safeUsername === '') {
        $safeUsername = 'LoreBinder user';
    }
    $message = "Hello {$safeUsername},\n\nUse this link to reset your LoreBinder password:\n{$resetUrl}\n\nThis link expires in {$expiresInMinutes} minutes.\n";

    $log = json_decode((string) file_get_contents(MAIL_LOG_FILE), true);
    if (!is_array($log)) {
        $log = [];
    }

    $mailStatus = 'skipped';
    $mailTo = trim($email);
    if (isSafeMailAddress($mailTo) && function_exists('mail')) {
        $mailStatus = mail($mailTo, $subject, $message) ? 'sent' : 'failed';
    }

    $log[] = [
        'email' => $email,
        'username' => $username,
        'subject' => $subject,
        'message' => $message,
        'mailStatus' => $mailStatus,
        'createdAt' => gmdate(DATE_ATOM),
    ];
    file_put_contents(MAIL_LOG_FILE, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}


function baseAppUrl(): string
{
    $configured = trim((string) getenv(APP_URL_ENV));
    if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL)) {
        return rtrim($configured, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $serverName = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
    if (!preg_match('/^[a-zA-Z0-9.-]+$/', $serverName)) {
        $serverName = 'localhost';
    }
    $serverPort = (string) ($_SERVER['SERVER_PORT'] ?? '');
    $defaultPort = $scheme === 'https' ? '443' : '80';
    $host = $serverName;
    if ($serverPort !== '' && $serverPort !== $defaultPort && preg_match('/^[0-9]{1,5}$/', $serverPort)) {
        $host .= ':' . $serverPort;
    }
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/LoreBinder/api.php');
    $directory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    return $scheme . '://' . $host . $directory . '/index.php';
}

function isSafeMailAddress(string $email): bool
{
    if ($email === '' || strpbrk($email, "\r\n") !== false) {
        return false;
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $email) === 1) {
        return false;
    }

    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function userAssignableRoles(): array
{
    return [USER_ROLE_PRIMARY_AUTHOR, USER_ROLE_SUB_AUTHOR, USER_ROLE_REVIEWER];
}

function adminAssignableRoles(): array
{
    return [USER_ROLE_ADMIN, USER_ROLE_PRIMARY_AUTHOR, USER_ROLE_SUB_AUTHOR, USER_ROLE_REVIEWER];
}

function interfaceThemeOptions(): array
{
    return [
        'midnight' => 'Midnight Blue',
        'forest' => 'Forest Ink',
        'ember' => 'Ember Alloy',
    ];
}

function themePresetSummaries(): array
{
    $presets = [];
    foreach (themePresetCatalog() as $key => $preset) {
        $presets[] = [
            'key' => $key,
            'label' => $preset['label'],
            'css' => $preset['css'],
        ];
    }
    return $presets;
}

function themePresetExists(string $key): bool
{
    return array_key_exists($key, themePresetCatalog());
}

function themePresetCss(string $key): string
{
    $catalog = themePresetCatalog();
    return (string) ($catalog[$key]['css'] ?? $catalog[DEFAULT_THEME_KEY]['css']);
}

function themePresetCatalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $catalog = [
        'star-trek-tos' => themePalettePreset('Star Trek (TOS)', '#f6e6a8', '#7f1028', '#201525', '#fdf9e8'),
        'star-trek-tng' => themePalettePreset('Star Trek (TNG)', '#d7ab42', '#6f0d1b', '#142133', '#f5f7fc'),
        'star-wars' => themePalettePreset('Star Wars Style', '#f4d35e', '#101820', '#251f0d', '#fff8de'),
        'dystopian' => themePalettePreset('Dystopian', '#b7b2a3', '#4a4d57', '#1b1b1f', '#efede7'),
        'post-apocalyptic' => themePalettePreset('Post-Apocalyptic', '#c57b57', '#6f3f28', '#2a1d16', '#f6e8de'),
        'cyberpunk' => themePalettePreset('Cyberpunk', '#34f5c5', '#ff4fd8', '#0f1020', '#e8fff9'),
        'mecha' => themePalettePreset('Mecha', '#8ecae6', '#375a7f', '#18202b', '#edf6fb'),
        'cosmic-horror' => themePalettePreset('Cosmic Horror', '#95a0ff', '#423772', '#130f1f', '#eef0ff'),
        'gothic-horror' => themePalettePreset('Gothic Horror', '#d0c1a4', '#4b2434', '#1e161d', '#f6efe4'),
        'urban-fantasy' => themePalettePreset('Urban Fantasy', '#c7b5ff', '#513d8a', '#1a1826', '#f6f3ff'),
        'dnd' => themePalettePreset('D&D', '#d9b46b', '#8c2f1f', '#251913', '#faf1e2'),
        'sword-sorcery' => themePalettePreset('Sword & Sorcery', '#d7ba7d', '#7c4c20', '#26170e', '#fbf2df'),
        'steampunk' => themePalettePreset('Steampunk', '#d3a15d', '#6c4e2d', '#2b1f17', '#f9efde'),
    ];

    return $catalog;
}

function themePalettePreset(string $label, string $accent, string $heading, string $ink, string $paper): array
{
    $css = <<<CSS
.rendered-preview,
#visual-editor {
  background: {$paper};
  color: {$ink};
  border-radius: 10px;
  padding: 1rem;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.08);
}

.rendered-preview h1,
.rendered-preview h2,
.rendered-preview h3,
.rendered-preview h4,
.rendered-preview h5,
.rendered-preview h6,
#visual-editor h1,
#visual-editor h2,
#visual-editor h3,
#visual-editor h4,
#visual-editor h5,
#visual-editor h6 {
  color: {$heading};
}

.rendered-preview a,
#visual-editor a,
.rendered-preview strong,
#visual-editor strong {
  color: {$accent};
}

.rendered-preview code,
#visual-editor code {
  background: rgba(0, 0, 0, 0.08);
  padding: 0.1rem 0.3rem;
  border-radius: 4px;
}

.rendered-preview blockquote,
#visual-editor blockquote,
.rendered-preview .monster-stat-block,
#visual-editor .monster-stat-block {
  border-left: 4px solid {$accent};
  background: rgba(255, 255, 255, 0.6);
  padding: 0.75rem 1rem;
  margin: 0.75rem 0;
}

.rendered-preview img,
#visual-editor img {
  max-width: 100%;
  border-radius: 8px;
}

.rendered-preview .wrap-left img,
#visual-editor .wrap-left img {
  float: left;
  max-width: 38%;
  margin: 0 1rem 0.75rem 0;
}
CSS;

    return ['label' => $label, 'css' => $css];
}
