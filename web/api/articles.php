<?php
/**
 * api/articles.php — CRUD for articles (admin) + list (all users)
 *
 * GET  ?action=list              → list articles for current user's institution
 * GET  ?action=get&id=X          → get single article
 * POST {action:create, ...}      → create (admin)
 * POST {action:update, id, ...}  → update (admin)
 * POST {action:delete, id}       → delete (admin)
 */
require_once dirname(__DIR__) . '/auth.php';
require_login();

$db   = get_db();
$user = current_user();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'get') {
        $id  = (int)($_GET['id'] ?? 0);
        $row = get_article_if_allowed($db, $user, $id);
        if (!$row) json_response(['error' => 'Artigo não encontrado.'], 404);
        json_response(['article' => $row]);
    }

    // list
    $rows = get_articles_for_user($db, $user);
    json_response(['articles' => $rows]);
}

if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? '';

    // ── CREATE ───────────────────────────────────────────────────────────────
    if ($action === 'create') {
        require_admin();
        $title   = trim($data['title']   ?? '');
        $content = trim($data['content'] ?? '');
        $instId  = isset($data['institution_id']) ? (int)$data['institution_id'] : null;

        if (!$title) json_response(['error' => 'Título é obrigatório.'], 400);

        $stmt = $db->prepare("
            INSERT INTO articles (title, content, institution_id, created_by)
            VALUES (:t, :c, :i, :u)
        ");
        $stmt->bindValue(':t', $title);
        $stmt->bindValue(':c', $content);
        $stmt->bindValue(':i', $instId, SQLITE3_INTEGER);
        $stmt->bindValue(':u', $user['id'], SQLITE3_INTEGER);
        $stmt->execute();
        json_response(['success' => true, 'id' => $db->lastInsertRowID()]);
    }

    // ── UPDATE ───────────────────────────────────────────────────────────────
    if ($action === 'update') {
        require_admin();
        $id      = (int)($data['id'] ?? 0);
        $title   = trim($data['title']   ?? '');
        $content = trim($data['content'] ?? '');
        $instId  = isset($data['institution_id']) ? (int)$data['institution_id'] : null;

        if (!$id || !$title) json_response(['error' => 'Dados inválidos.'], 400);

        $stmt = $db->prepare("
            UPDATE articles SET title=:t, content=:c, institution_id=:i, updated_at=datetime('now') WHERE id=:id
        ");
        $stmt->bindValue(':t',  $title);
        $stmt->bindValue(':c',  $content);
        $stmt->bindValue(':i',  $instId, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        json_response(['success' => true]);
    }

    // ── DELETE ───────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        require_admin();
        $id = (int)($data['id'] ?? 0);
        if (!$id) json_response(['error' => 'ID inválido.'], 400);
        $db->exec("DELETE FROM articles WHERE id=$id");
        json_response(['success' => true]);
    }

    json_response(['error' => 'Ação desconhecida.'], 400);
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function get_articles_for_user(SQLite3 $db, array $user): array {
    $rows = [];
    if ($user['role'] === 'admin') {
        // Admin sees all
        $res = $db->query("
            SELECT a.id, a.title, a.created_at, a.updated_at,
                   a.institution_id,
                   i.name AS institution_name,
                   u.name AS author_name
            FROM articles a
            LEFT JOIN institutions i ON i.id = a.institution_id
            LEFT JOIN users u ON u.id = a.created_by
            ORDER BY a.created_at DESC
        ");
    } else {
        $iid = (int)($user['institution_id'] ?? 0);
        $res = $db->query("
            SELECT a.id, a.title, a.created_at, a.updated_at,
                   a.institution_id,
                   i.name AS institution_name,
                   u.name AS author_name
            FROM articles a
            LEFT JOIN institutions i ON i.id = a.institution_id
            LEFT JOIN users u ON u.id = a.created_by
            WHERE a.institution_id IS NULL OR a.institution_id = $iid
            ORDER BY a.created_at DESC
        ");
    }
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) $rows[] = $r;
    return $rows;
}

function get_article_if_allowed(SQLite3 $db, array $user, int $id): ?array {
    $stmt = $db->prepare("
        SELECT a.*, i.name AS institution_name, u.name AS author_name
        FROM articles a
        LEFT JOIN institutions i ON i.id = a.institution_id
        LEFT JOIN users u ON u.id = a.created_by
        WHERE a.id = :id
    ");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;

    // Access check
    if ($user['role'] !== 'admin') {
        $iid = (int)($user['institution_id'] ?? 0);
        if ($row['institution_id'] !== null && (int)$row['institution_id'] !== $iid) {
            return null;
        }
    }
    return $row;
}
