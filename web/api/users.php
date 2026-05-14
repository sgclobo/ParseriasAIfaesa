<?php
/**
 * api/users.php — CRUD for users (admin only)
 *
 * GET    ?action=list              → list all users
 * GET    ?action=institutions      → list institutions
 * POST   {action:create, ...}      → create user
 * POST   {action:update, id, ...}  → update user
 * POST   {action:delete, id}       → delete user
 */
require_once dirname(__DIR__) . '/auth.php';
require_admin();

$db = get_db();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'institutions') {
        $res  = $db->query("SELECT * FROM institutions ORDER BY name");
        $rows = [];
        while ($r = $res->fetchArray(SQLITE3_ASSOC)) $rows[] = $r;
        json_response(['institutions' => $rows]);
    }

    // list users
    $res  = $db->query("
        SELECT u.id, u.name, u.position, u.email, u.whatsapp, u.photo, u.role,
               u.created_at, u.last_login,
               u.institution_id,
               i.name AS institution_name
        FROM users u
        LEFT JOIN institutions i ON i.id = u.institution_id
        ORDER BY u.name
    ");
    $rows = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) $rows[] = $r;
    json_response(['users' => $rows]);
}

if ($method === 'POST') {
    // Handle multipart (with photo upload) or JSON
    $isMultipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart') !== false;

    if ($isMultipart) {
        $data = $_POST;
    } else {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    $action = $data['action'] ?? '';

    // ── CREATE ───────────────────────────────────────────────────────────────
    if ($action === 'create') {
        $name     = trim($data['name']     ?? '');
        $position = trim($data['position'] ?? '');
        $instName = trim($data['institution'] ?? '');
        $whatsapp = trim($data['whatsapp'] ?? '');
        $email    = trim($data['email']    ?? '');
        $role     = in_array($data['role'] ?? '', ['admin','user']) ? $data['role'] : 'user';
        $password = $data['password'] ?? '';

        if (!$name || !$email || !$password) {
            json_response(['error' => 'Nome, email e senha são obrigatórios.'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['error' => 'Email inválido.'], 400);
        }

        // Upsert institution
        $instId = null;
        if ($instName) {
            $db->exec("INSERT OR IGNORE INTO institutions (name) VALUES ('" . SQLite3::escapeString($instName) . "')");
            $instId = $db->querySingle("SELECT id FROM institutions WHERE name='" . SQLite3::escapeString($instName) . "'");
        }

        // Handle photo
        $photoPath = '';
        if ($isMultipart && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photoPath = save_photo($_FILES['photo']);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (name, position, institution_id, whatsapp, email, photo, role, password_hash)
            VALUES (:n, :p, :i, :w, :e, :ph, :r, :h)
        ");
        $stmt->bindValue(':n',  $name);
        $stmt->bindValue(':p',  $position);
        $stmt->bindValue(':i',  $instId, SQLITE3_INTEGER);
        $stmt->bindValue(':w',  $whatsapp);
        $stmt->bindValue(':e',  $email);
        $stmt->bindValue(':ph', $photoPath);
        $stmt->bindValue(':r',  $role);
        $stmt->bindValue(':h',  $hash);

        try {
            $stmt->execute();
            json_response(['success' => true, 'id' => $db->lastInsertRowID()]);
        } catch (Exception $ex) {
            json_response(['error' => 'Email já registado.'], 409);
        }
    }

    // ── UPDATE ───────────────────────────────────────────────────────────────
    if ($action === 'update') {
        $id       = (int)($data['id'] ?? 0);
        $name     = trim($data['name']     ?? '');
        $position = trim($data['position'] ?? '');
        $instName = trim($data['institution'] ?? '');
        $whatsapp = trim($data['whatsapp'] ?? '');
        $email    = trim($data['email']    ?? '');
        $role     = in_array($data['role'] ?? '', ['admin','user']) ? $data['role'] : 'user';
        $password = $data['password'] ?? '';

        if (!$id || !$name || !$email) {
            json_response(['error' => 'Dados inválidos.'], 400);
        }

        $instId = null;
        if ($instName) {
            $db->exec("INSERT OR IGNORE INTO institutions (name) VALUES ('" . SQLite3::escapeString($instName) . "')");
            $instId = $db->querySingle("SELECT id FROM institutions WHERE name='" . SQLite3::escapeString($instName) . "'");
        }

        // Handle photo update
        $existing = $db->querySingle("SELECT photo FROM users WHERE id=$id", true);
        $photoPath = $existing['photo'] ?? '';
        if ($isMultipart && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            if ($photoPath) @unlink(dirname(__DIR__) . '/uploads/' . $photoPath);
            $photoPath = save_photo($_FILES['photo']);
        }

        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET name=:n, position=:p, institution_id=:i, whatsapp=:w, email=:e, photo=:ph, role=:r, password_hash=:h WHERE id=:id");
            $stmt->bindValue(':h', $hash);
        } else {
            $stmt = $db->prepare("UPDATE users SET name=:n, position=:p, institution_id=:i, whatsapp=:w, email=:e, photo=:ph, role=:r WHERE id=:id");
        }
        $stmt->bindValue(':n',  $name);
        $stmt->bindValue(':p',  $position);
        $stmt->bindValue(':i',  $instId, SQLITE3_INTEGER);
        $stmt->bindValue(':w',  $whatsapp);
        $stmt->bindValue(':e',  $email);
        $stmt->bindValue(':ph', $photoPath);
        $stmt->bindValue(':r',  $role);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);

        try {
            $stmt->execute();
            json_response(['success' => true]);
        } catch (Exception $ex) {
            json_response(['error' => 'Email já em uso.'], 409);
        }
    }

    // ── DELETE ───────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) json_response(['error' => 'ID inválido.'], 400);

        // Prevent self-delete
        if ($id === (int)$_SESSION['user_id']) {
            json_response(['error' => 'Não pode eliminar a sua própria conta.'], 403);
        }

        $row = $db->querySingle("SELECT photo FROM users WHERE id=$id", true);
        if ($row && $row['photo']) @unlink(dirname(__DIR__) . '/uploads/' . $row['photo']);

        $db->exec("DELETE FROM users WHERE id=$id");
        json_response(['success' => true]);
    }

    json_response(['error' => 'Ação desconhecida.'], 400);
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function save_photo(array $file): string {
    $uploadDir = dirname(__DIR__) . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allow = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allow)) return '';

    $fname = uniqid('photo_', true) . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $uploadDir . $fname);
    return $fname;
}
