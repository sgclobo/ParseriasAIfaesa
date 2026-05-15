<?php

/**
 * api/mobile.php — Token-based API for the mobile app shell.
 */
require_once dirname(__DIR__) . '/auth.php';

if (PHP_SAPI === 'cli' && !isset($_SERVER['REQUEST_METHOD'])) {
    return;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$db = get_db();

function mobile_json(array $data, int $status = 200): void
{
    json_response($data, $status);
}

function mobile_token_from_request(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function mobile_user_by_token(SQLite3 $db, string $token): ?array
{
    $stmt = $db->prepare(
        "SELECT u.*, i.name AS institution_name
         FROM mobile_sessions ms
         JOIN users u ON u.id = ms.user_id
         LEFT JOIN institutions i ON i.id = u.institution_id
         WHERE ms.token = :token AND datetime(ms.expires_at) > datetime('now')"
    );
    $stmt->bindValue(':token', $token);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

function mobile_authenticate(SQLite3 $db): array
{
    $token = mobile_token_from_request();
    if (!$token) {
        mobile_json(['error' => 'Missing bearer token.'], 401);
    }

    $user = mobile_user_by_token($db, $token);
    if (!$user) {
        mobile_json(['error' => 'Session expired.'], 401);
    }

    return $user;
}

function mobile_issue_token(SQLite3 $db, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_48H);

    $stmt = $db->prepare('INSERT INTO mobile_sessions (token, user_id, expires_at) VALUES (:token, :user_id, :expires_at)');
    $stmt->bindValue(':token', $token);
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':expires_at', $expiresAt);
    $stmt->execute();

    $cleanup = $db->prepare("DELETE FROM mobile_sessions WHERE datetime(expires_at) <= datetime('now')");
    $cleanup->execute();

    return $token;
}

function mobile_normalize_identifier(string $value): string
{
    return trim($value);
}

function mobile_find_user(SQLite3 $db, string $identifier, string $password): ?array
{
    $stmt = $db->prepare(
        "SELECT u.*, i.name AS institution_name
         FROM users u
         LEFT JOIN institutions i ON i.id = u.institution_id
         WHERE u.email = :identifier OR u.name = :identifier
         LIMIT 1"
    );
    $stmt->bindValue(':identifier', $identifier);
    $res = $stmt->execute();
    $user = $res->fetchArray(SQLITE3_ASSOC);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }
    return $user;
}

function mobile_article_ids(SQLite3 $db, int $articleId): array
{
    $stmt = $db->prepare('SELECT institution_id FROM article_institutions WHERE article_id = :article_id ORDER BY institution_id');
    $stmt->bindValue(':article_id', $articleId, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $ids = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $ids[] = (int)$row['institution_id'];
    }
    return $ids;
}

function mobile_article_visible(SQLite3 $db, array $user, int $articleId): bool
{
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }

    $institutionId = (int)($user['institution_id'] ?? 0);
    $targetCountStmt = $db->prepare('SELECT COUNT(*) AS total FROM article_institutions WHERE article_id = :article_id');
    $targetCountStmt->bindValue(':article_id', $articleId, SQLITE3_INTEGER);
    $targetCountRes = $targetCountStmt->execute();
    $targetCount = (int)($targetCountRes->fetchArray(SQLITE3_ASSOC)['total'] ?? 0);

    if ($targetCount > 0) {
        $matchStmt = $db->prepare('SELECT 1 FROM article_institutions WHERE article_id = :article_id AND institution_id = :institution_id LIMIT 1');
        $matchStmt->bindValue(':article_id', $articleId, SQLITE3_INTEGER);
        $matchStmt->bindValue(':institution_id', $institutionId, SQLITE3_INTEGER);
        $matchRes = $matchStmt->execute();
        return (bool)$matchRes->fetchArray(SQLITE3_ASSOC);
    }

    $legacyStmt = $db->prepare('SELECT institution_id FROM articles WHERE id = :id');
    $legacyStmt->bindValue(':id', $articleId, SQLITE3_INTEGER);
    $legacyRes = $legacyStmt->execute();
    $legacyRow = $legacyRes->fetchArray(SQLITE3_ASSOC);
    if (!$legacyRow) {
        return false;
    }
    if ($legacyRow['institution_id'] === null) {
        return true;
    }

    return (int)$legacyRow['institution_id'] === $institutionId;
}

function mobile_articles(SQLite3 $db, array $user): array
{
    if (($user['role'] ?? '') === 'admin') {
        $res = $db->query(
            "SELECT a.id, a.title, a.content, a.created_at, a.updated_at,
                    a.institution_id,
                    COALESCE(NULLIF(GROUP_CONCAT(DISTINCT i.name), ''), legacy_i.name, 'Todos os públicos') AS institution_names,
                    u.name AS author_name
             FROM articles a
             LEFT JOIN article_institutions ai ON ai.article_id = a.id
             LEFT JOIN institutions i ON i.id = ai.institution_id
             LEFT JOIN institutions legacy_i ON legacy_i.id = a.institution_id
             LEFT JOIN users u ON u.id = a.created_by
             GROUP BY a.id
             ORDER BY a.created_at DESC"
        );
    } else {
        $institutionId = (int)($user['institution_id'] ?? 0);
        $stmt = $db->prepare(
            "SELECT a.id, a.title, a.content, a.created_at, a.updated_at,
                    a.institution_id,
                    COALESCE(NULLIF(GROUP_CONCAT(DISTINCT i.name), ''), legacy_i.name, 'Todos os públicos') AS institution_names,
                    u.name AS author_name
             FROM articles a
             LEFT JOIN article_institutions ai ON ai.article_id = a.id
             LEFT JOIN institutions i ON i.id = ai.institution_id
             LEFT JOIN institutions legacy_i ON legacy_i.id = a.institution_id
             LEFT JOIN users u ON u.id = a.created_by
             WHERE EXISTS (
                    SELECT 1
                    FROM article_institutions ai2
                    WHERE ai2.article_id = a.id AND ai2.institution_id = :institution_id
             )
             OR (
                    NOT EXISTS (SELECT 1 FROM article_institutions ai3 WHERE ai3.article_id = a.id)
                    AND (a.institution_id IS NULL OR a.institution_id = :institution_id)
             )
             GROUP BY a.id
             ORDER BY a.created_at DESC"
        );
        $stmt->bindValue(':institution_id', $institutionId, SQLITE3_INTEGER);
        $res = $stmt->execute();
    }

    $rows = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $row['institution_ids'] = mobile_article_ids($db, (int)$row['id']);
        $row['snippet'] = mb_substr(trim(preg_replace('/\s+/', ' ', (string)$row['content'])), 0, 140);
        $rows[] = $row;
    }

    return $rows;
}

function mobile_institutions(SQLite3 $db): array
{
    $res = $db->query('SELECT id, name, created_at FROM institutions ORDER BY name');
    $rows = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

function mobile_institution_in_use(SQLite3 $db, int $id): bool
{
    $userCount = (int)$db->querySingle('SELECT COUNT(*) FROM users WHERE institution_id = ' . $id);
    $legacyArticleCount = (int)$db->querySingle('SELECT COUNT(*) FROM articles WHERE institution_id = ' . $id);
    $targetCount = (int)$db->querySingle('SELECT COUNT(*) FROM article_institutions WHERE institution_id = ' . $id);
    return $userCount > 0 || $legacyArticleCount > 0 || $targetCount > 0;
}

function mobile_users(SQLite3 $db): array
{
    $res = $db->query(
        "SELECT u.id, u.name, u.position, u.email, u.whatsapp, u.role,
                u.institution_id,
                i.name AS institution_name,
                u.created_at,
                u.last_login
         FROM users u
         LEFT JOIN institutions i ON i.id = u.institution_id
         ORDER BY u.name"
    );

    $rows = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

function mobile_resolve_institution_id(SQLite3 $db, mixed $institutionIdRaw, mixed $institutionNameRaw): ?int
{
    $idValue = trim((string)$institutionIdRaw);
    if ($idValue !== '' && ctype_digit($idValue)) {
        $id = (int)$idValue;
        $stmt = $db->prepare('SELECT id FROM institutions WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $res = $stmt->execute();
        if ($res->fetchArray(SQLITE3_ASSOC)) {
            return $id;
        }
    }

    $name = trim((string)$institutionNameRaw);
    if ($name === '') {
        return null;
    }

    $escaped = SQLite3::escapeString($name);
    $db->exec("INSERT OR IGNORE INTO institutions (name) VALUES ('$escaped')");
    return (int)$db->querySingle("SELECT id FROM institutions WHERE name='$escaped'");
}

function mobile_institution_exists(SQLite3 $db, int $id): bool
{
    $stmt = $db->prepare('SELECT id FROM institutions WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    return (bool)$res->fetchArray(SQLITE3_ASSOC);
}

function mobile_resolve_institution_ids(SQLite3 $db, mixed $rawValue): array
{
    if (!is_array($rawValue)) {
        return [];
    }

    $resolved = [];
    foreach ($rawValue as $value) {
        if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
            $id = (int)$value;
            if ($id > 0 && mobile_institution_exists($db, $id)) {
                $resolved[$id] = true;
            }
        }
    }

    return array_map('intval', array_keys($resolved));
}

function mobile_set_article_targets(SQLite3 $db, int $articleId, array $institutionIds): void
{
    $delete = $db->prepare('DELETE FROM article_institutions WHERE article_id = :article_id');
    $delete->bindValue(':article_id', $articleId, SQLITE3_INTEGER);
    $delete->execute();

    if (!$institutionIds) {
        return;
    }

    $insert = $db->prepare('INSERT OR IGNORE INTO article_institutions (article_id, institution_id) VALUES (:article_id, :institution_id)');
    foreach ($institutionIds as $institutionId) {
        $insert->bindValue(':article_id', $articleId, SQLITE3_INTEGER);
        $insert->bindValue(':institution_id', $institutionId, SQLITE3_INTEGER);
        $insert->execute();
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'login') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $identifier = mobile_normalize_identifier((string)($body['identifier'] ?? $body['email'] ?? $body['username'] ?? ''));
    $password = trim((string)($body['password'] ?? ''));

    if ($identifier === '' || $password === '') {
        mobile_json(['error' => 'Identifier and password are required.'], 400);
    }

    $user = mobile_find_user($db, $identifier, $password);
    if (!$user) {
        mobile_json(['error' => 'Invalid email or password.'], 401);
    }

    $token = mobile_issue_token($db, (int)$user['id']);
    $userProfile = [
        'id' => (int)$user['id'],
        'username' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'institution' => $user['institution_name'] ?? '',
        'institution_id' => $user['institution_id'] !== null ? (int)$user['institution_id'] : null,
    ];

    mobile_json([
        'success' => true,
        'token' => $token,
        'user' => $userProfile,
        'articles' => mobile_articles($db, $user),
        'institutions' => mobile_institutions($db),
        'users' => ($user['role'] ?? '') === 'admin' ? mobile_users($db) : [],
    ]);
}

$currentUser = mobile_authenticate($db);

if ($method === 'GET' && $action === 'bootstrap') {
    mobile_json([
        'success' => true,
        'user' => [
            'id' => (int)$currentUser['id'],
            'username' => $currentUser['name'],
            'email' => $currentUser['email'],
            'role' => $currentUser['role'],
            'institution' => $currentUser['institution_name'] ?? '',
            'institution_id' => $currentUser['institution_id'] !== null ? (int)$currentUser['institution_id'] : null,
        ],
        'articles' => mobile_articles($db, $currentUser),
        'institutions' => mobile_institutions($db),
        'users' => ($currentUser['role'] ?? '') === 'admin' ? mobile_users($db) : [],
    ]);
}

if ($method === 'POST' && $action === 'profile') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = trim((string)($body['name'] ?? $currentUser['name'] ?? ''));
    $position = trim((string)($body['position'] ?? $currentUser['position'] ?? ''));
    $whatsapp = trim((string)($body['whatsapp'] ?? $currentUser['whatsapp'] ?? ''));
    $email = trim((string)($body['email'] ?? $currentUser['email'] ?? ''));
    $currentPassword = (string)($body['current_password'] ?? '');
    $newPassword = (string)($body['new_password'] ?? '');

    if ($name === '' || $email === '') {
        mobile_json(['error' => 'Name and email are required.'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        mobile_json(['error' => 'Invalid email address.'], 400);
    }

    $id = (int)$currentUser['id'];

    if ($newPassword !== '') {
        if ($currentPassword === '' || !password_verify($currentPassword, $currentUser['password_hash'])) {
            mobile_json(['error' => 'Current password is incorrect.'], 401);
        }
        if (strlen($newPassword) < 6) {
            mobile_json(['error' => 'New password must have at least 6 characters.'], 400);
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET name = :name, position = :position, whatsapp = :whatsapp, email = :email, password_hash = :password_hash WHERE id = :id');
        $stmt->bindValue(':password_hash', $passwordHash);
    } else {
        $stmt = $db->prepare('UPDATE users SET name = :name, position = :position, whatsapp = :whatsapp, email = :email WHERE id = :id');
    }

    $stmt->bindValue(':name', $name);
    $stmt->bindValue(':position', $position);
    $stmt->bindValue(':whatsapp', $whatsapp);
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);

    try {
        $stmt->execute();
    } catch (Exception $ex) {
        mobile_json(['error' => 'Email already in use.'], 409);
    }

    $row = $db->querySingle(
        "SELECT u.id, u.name AS username, u.email, u.role, i.name AS institution, u.institution_id
         FROM users u
         LEFT JOIN institutions i ON i.id = u.institution_id
         WHERE u.id = $id",
        true
    );

    mobile_json(['success' => true, 'user' => $row]);
}

if ($action === 'users') {
    if (($currentUser['role'] ?? '') !== 'admin') {
        mobile_json(['error' => 'Forbidden'], 403);
    }

    if ($method === 'GET') {
        mobile_json([
            'success' => true,
            'users' => mobile_users($db),
            'institutions' => mobile_institutions($db),
        ]);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $operation = $body['operation'] ?? '';

        if ($operation === 'create') {
            $name = trim((string)($body['name'] ?? ''));
            $email = trim((string)($body['email'] ?? ''));
            $role = (($body['role'] ?? 'user') === 'admin') ? 'admin' : 'user';
            $password = (string)($body['password'] ?? '');
            $position = trim((string)($body['position'] ?? ''));
            $whatsapp = trim((string)($body['whatsapp'] ?? ''));
            $institutionId = mobile_resolve_institution_id($db, $body['institution_id'] ?? null, $body['institution'] ?? null);

            if ($name === '' || $email === '' || $password === '') {
                mobile_json(['error' => 'Name, email and password are required.'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                mobile_json(['error' => 'Invalid email address.'], 400);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (name, position, institution_id, whatsapp, email, role, password_hash) VALUES (:name, :position, :institution_id, :whatsapp, :email, :role, :password_hash)');
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':position', $position);
            $stmt->bindValue(':institution_id', $institutionId, SQLITE3_INTEGER);
            $stmt->bindValue(':whatsapp', $whatsapp);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':role', $role);
            $stmt->bindValue(':password_hash', $hash);

            try {
                $stmt->execute();
            } catch (Exception $ex) {
                mobile_json(['error' => 'Email already in use.'], 409);
            }

            mobile_json(['success' => true, 'users' => mobile_users($db)]);
        }

        if ($operation === 'update') {
            $id = (int)($body['id'] ?? 0);
            $name = trim((string)($body['name'] ?? ''));
            $email = trim((string)($body['email'] ?? ''));
            $role = (($body['role'] ?? 'user') === 'admin') ? 'admin' : 'user';
            $position = trim((string)($body['position'] ?? ''));
            $whatsapp = trim((string)($body['whatsapp'] ?? ''));
            $password = (string)($body['password'] ?? '');
            $institutionId = mobile_resolve_institution_id($db, $body['institution_id'] ?? null, $body['institution'] ?? null);

            if (!$id || $name === '' || $email === '') {
                mobile_json(['error' => 'Invalid user data.'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                mobile_json(['error' => 'Invalid email address.'], 400);
            }

            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE users SET name = :name, position = :position, institution_id = :institution_id, whatsapp = :whatsapp, email = :email, role = :role, password_hash = :password_hash WHERE id = :id');
                $stmt->bindValue(':password_hash', $passwordHash);
            } else {
                $stmt = $db->prepare('UPDATE users SET name = :name, position = :position, institution_id = :institution_id, whatsapp = :whatsapp, email = :email, role = :role WHERE id = :id');
            }

            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':position', $position);
            $stmt->bindValue(':institution_id', $institutionId, SQLITE3_INTEGER);
            $stmt->bindValue(':whatsapp', $whatsapp);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':role', $role);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);

            try {
                $stmt->execute();
            } catch (Exception $ex) {
                mobile_json(['error' => 'Email already in use.'], 409);
            }

            mobile_json(['success' => true, 'users' => mobile_users($db)]);
        }

        if ($operation === 'delete') {
            $id = (int)($body['id'] ?? 0);
            if (!$id) {
                mobile_json(['error' => 'Invalid user id.'], 400);
            }
            if ($id === (int)$currentUser['id']) {
                mobile_json(['error' => 'You cannot delete your own account.'], 403);
            }

            $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            mobile_json(['success' => true, 'users' => mobile_users($db)]);
        }

        mobile_json(['error' => 'Unknown users operation.'], 400);
    }
}

if ($action === 'articles') {
    if ($method === 'GET') {
        mobile_json([
            'success' => true,
            'articles' => mobile_articles($db, $currentUser),
            'institutions' => mobile_institutions($db),
        ]);
    }

    if (($currentUser['role'] ?? '') !== 'admin') {
        mobile_json(['error' => 'Forbidden'], 403);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $operation = $body['operation'] ?? '';

        if ($operation === 'create') {
            $title = trim((string)($body['title'] ?? ''));
            $content = trim((string)($body['content'] ?? ''));
            $institutionIds = mobile_resolve_institution_ids($db, $body['institution_ids'] ?? []);

            if ($title === '') {
                mobile_json(['error' => 'Title is required.'], 400);
            }

            $stmt = $db->prepare('INSERT INTO articles (title, content, institution_id, created_by) VALUES (:title, :content, NULL, :created_by)');
            $stmt->bindValue(':title', $title);
            $stmt->bindValue(':content', $content);
            $stmt->bindValue(':created_by', (int)$currentUser['id'], SQLITE3_INTEGER);
            $stmt->execute();

            $articleId = (int)$db->lastInsertRowID();
            mobile_set_article_targets($db, $articleId, $institutionIds);

            mobile_json(['success' => true, 'articles' => mobile_articles($db, $currentUser)]);
        }

        if ($operation === 'update') {
            $id = (int)($body['id'] ?? 0);
            $title = trim((string)($body['title'] ?? ''));
            $content = trim((string)($body['content'] ?? ''));
            $institutionIds = mobile_resolve_institution_ids($db, $body['institution_ids'] ?? []);

            if (!$id || $title === '') {
                mobile_json(['error' => 'Invalid article data.'], 400);
            }

            $stmt = $db->prepare("UPDATE articles SET title = :title, content = :content, institution_id = NULL, updated_at = datetime('now') WHERE id = :id");
            $stmt->bindValue(':title', $title);
            $stmt->bindValue(':content', $content);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();

            mobile_set_article_targets($db, $id, $institutionIds);
            mobile_json(['success' => true, 'articles' => mobile_articles($db, $currentUser)]);
        }

        if ($operation === 'delete') {
            $id = (int)($body['id'] ?? 0);
            if (!$id) {
                mobile_json(['error' => 'Invalid article id.'], 400);
            }

            $stmt = $db->prepare('DELETE FROM articles WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            mobile_json(['success' => true, 'articles' => mobile_articles($db, $currentUser)]);
        }

        mobile_json(['error' => 'Unknown articles operation.'], 400);
    }
}

if ($method === 'POST' && $action === 'institutions') {
    if (($currentUser['role'] ?? '') !== 'admin') {
        mobile_json(['error' => 'Forbidden'], 403);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $operation = $body['operation'] ?? '';

    if ($operation === 'create') {
        $name = trim((string)($body['name'] ?? ''));
        if ($name === '') {
            mobile_json(['error' => 'Institution name is required.'], 400);
        }

        $stmt = $db->prepare('INSERT INTO institutions (name) VALUES (:name)');
        $stmt->bindValue(':name', $name);
        try {
            $stmt->execute();
            mobile_json(['success' => true, 'institutions' => mobile_institutions($db)]);
        } catch (Exception $ex) {
            mobile_json(['error' => 'Institution already exists.'], 409);
        }
    }

    if ($operation === 'update') {
        $id = (int)($body['id'] ?? 0);
        $name = trim((string)($body['name'] ?? ''));
        if (!$id || $name === '') {
            mobile_json(['error' => 'Invalid institution data.'], 400);
        }

        $stmt = $db->prepare('UPDATE institutions SET name = :name WHERE id = :id');
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        try {
            $stmt->execute();
            mobile_json(['success' => true, 'institutions' => mobile_institutions($db)]);
        } catch (Exception $ex) {
            mobile_json(['error' => 'Institution already exists.'], 409);
        }
    }

    if ($operation === 'delete') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) {
            mobile_json(['error' => 'Invalid institution id.'], 400);
        }

        if (mobile_institution_in_use($db, $id)) {
            mobile_json(['error' => 'Cannot delete institution that is linked to users or articles.'], 409);
        }

        $stmt = $db->prepare('DELETE FROM institutions WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        mobile_json(['success' => true, 'institutions' => mobile_institutions($db)]);
    }

    mobile_json(['error' => 'Unknown operation.'], 400);
}

if ($method === 'POST' && $action === 'logout') {
    $token = mobile_token_from_request();
    if ($token) {
        $stmt = $db->prepare('DELETE FROM mobile_sessions WHERE token = :token');
        $stmt->bindValue(':token', $token);
        $stmt->execute();
    }
    mobile_json(['success' => true]);
}

mobile_json(['error' => 'Method not allowed.'], 405);
