<?php

/**
 * auth.php — Session helpers and authentication logic
 */

require_once __DIR__ . '/db.php';

session_start();

define('SESSION_48H', 48 * 3600);

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) return null;
    static $user = null;
    if ($user === null) {
        $db   = get_db();
        $stmt = $db->prepare("
            SELECT u.*, i.name AS institution_name
            FROM users u
            LEFT JOIN institutions i ON i.id = u.institution_id
            WHERE u.id = :id
        ");
        $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $res  = $stmt->execute();
        $user = $res->fetchArray(SQLITE3_ASSOC) ?: null;
    }
    return $user;
}

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit(json_encode(['error' => 'Forbidden']));
    }
}

function attempt_login(string $identifier, string $password): array
{
    $db   = get_db();
    // Accept username (name) or email
    $stmt = $db->prepare("
        SELECT u.*, i.name AS institution_name
        FROM users u
        LEFT JOIN institutions i ON i.id = u.institution_id
        WHERE u.email = :i OR u.name = :i
    ");
    $stmt->bindValue(':i', $identifier);
    $res  = $stmt->execute();
    $user = $res->fetchArray(SQLITE3_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Utilizador ou senha incorretos.'];
    }

    // Update last_login
    $upd = $db->prepare("UPDATE users SET last_login = datetime('now') WHERE id = :id");
    $upd->bindValue(':id', $user['id'], SQLITE3_INTEGER);
    $upd->execute();

    // Set session
    $_SESSION['user_id'] = $user['id'];

    // Cookie for 48h auto-login hint (just stores timestamp + user id, not password)
    $payload = base64_encode(json_encode(['uid' => $user['id'], 'ts' => time()]));
    setcookie('pars_session', $payload, time() + SESSION_48H, '/', '', false, true);

    return ['success' => true, 'user' => $user];
}

function get_session_cookie_age(): ?int
{
    if (!isset($_COOKIE['pars_session'])) return null;
    $data = json_decode(base64_decode($_COOKIE['pars_session']), true);
    if (!$data || !isset($data['ts'])) return null;
    return time() - (int)$data['ts'];
}

function get_cookie_user_id(): ?int
{
    if (!isset($_COOKIE['pars_session'])) return null;
    $data = json_decode(base64_decode($_COOKIE['pars_session']), true);
    if (!$data || !isset($data['uid'])) return null;
    return (int)$data['uid'];
}

// Auto-login from cookie if session expired but cookie still fresh (<48h)
if (!is_logged_in() && isset($_COOKIE['pars_session'])) {
    $age = get_session_cookie_age();
    $uid = get_cookie_user_id();
    if ($age !== null && $uid !== null && $age < SESSION_48H) {
        $_SESSION['user_id'] = $uid;
    }
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sanitize(string $v): string
{
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}
