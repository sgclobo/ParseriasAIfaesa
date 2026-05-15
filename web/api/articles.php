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

function institution_exists(SQLite3 $db, int $id): bool
{
    $stmt = $db->prepare('SELECT id FROM institutions WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    return (bool)$res->fetchArray(SQLITE3_ASSOC);
}

function resolve_institution_id(SQLite3 $db, mixed $raw): ?int
{
    $value = trim((string)$raw);
    if ($value === '') {
        return null;
    }

    if (ctype_digit($value)) {
        $id = (int)$value;
        return institution_exists($db, $id) ? $id : null;
    }

    $escaped = SQLite3::escapeString($value);
    $db->exec("INSERT OR IGNORE INTO institutions (name) VALUES ('$escaped')");
    $stmt = $db->prepare('SELECT id FROM institutions WHERE name = :name');
    $stmt->bindValue(':name', $value);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    return $row ? (int)$row['id'] : null;
}

function resolve_institution_ids(SQLite3 $db, array $data): array
{
    $rawIds = [];

    if (isset($data['institution_ids']) && is_array($data['institution_ids'])) {
        $rawIds = $data['institution_ids'];
    } elseif (isset($data['institution_ids']) && is_string($data['institution_ids'])) {
        $rawIds = array_filter(array_map('trim', explode(',', $data['institution_ids'])));
    } elseif (isset($data['institution_id']) || isset($data['institution'])) {
        $single = resolve_institution_id($db, $data['institution_id'] ?? $data['institution']);
        return $single ? [$single] : [];
    }

    $resolved = [];
    foreach ($rawIds as $value) {
        $id = resolve_institution_id($db, $value);
        if ($id !== null) {
            $resolved[$id] = true;
        }
    }

    return array_map('intval', array_keys($resolved));
}

function set_article_targets(SQLite3 $db, int $articleId, array $institutionIds): void
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

function get_article_target_ids(SQLite3 $db, int $articleId): array
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

function user_can_access_article(SQLite3 $db, array $user, int $articleId): bool
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

    // Legacy fallback for old rows that only use articles.institution_id
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

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'get') {
        $id  = (int)($_GET['id'] ?? 0);
        $row = get_article_if_allowed($db, $user, $id);
        if (!$row) {
            json_response(['error' => 'Artigo não encontrado.'], 404);
        }
        json_response(['article' => $row]);
    }

    $rows = get_articles_for_user($db, $user);
    json_response(['articles' => $rows]);
}

if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? '';

    if ($action === 'create') {
        require_admin();
        $title   = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');
        $institutionIds = resolve_institution_ids($db, $data);

        if (!$title) {
            json_response(['error' => 'Título é obrigatório.'], 400);
        }

        $stmt = $db->prepare('INSERT INTO articles (title, content, institution_id, created_by) VALUES (:t, :c, NULL, :u)');
        $stmt->bindValue(':t', $title);
        $stmt->bindValue(':c', $content);
        $stmt->bindValue(':u', (int)$user['id'], SQLITE3_INTEGER);
        $stmt->execute();

        $articleId = (int)$db->lastInsertRowID();
        set_article_targets($db, $articleId, $institutionIds);

        json_response(['success' => true, 'id' => $articleId]);
    }

    if ($action === 'update') {
        require_admin();
        $id      = (int)($data['id'] ?? 0);
        $title   = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');
        $institutionIds = resolve_institution_ids($db, $data);

        if (!$id || !$title) {
            json_response(['error' => 'Dados inválidos.'], 400);
        }

        $stmt = $db->prepare("UPDATE articles SET title = :t, content = :c, institution_id = NULL, updated_at = datetime('now') WHERE id = :id");
        $stmt->bindValue(':t', $title);
        $stmt->bindValue(':c', $content);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();

        set_article_targets($db, $id, $institutionIds);
        json_response(['success' => true]);
    }

    if ($action === 'delete') {
        require_admin();
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            json_response(['error' => 'ID inválido.'], 400);
        }
        $db->exec("DELETE FROM articles WHERE id = $id");
        json_response(['success' => true]);
    }

    json_response(['error' => 'Ação desconhecida.'], 400);
}

function get_articles_for_user(SQLite3 $db, array $user): array
{
    $rows = [];

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

    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $row['institution_ids'] = get_article_target_ids($db, (int)$row['id']);
        $rows[] = $row;
    }

    return $rows;
}

function get_article_if_allowed(SQLite3 $db, array $user, int $id): ?array
{
    $stmt = $db->prepare(
        "SELECT a.*, 
                COALESCE(NULLIF(GROUP_CONCAT(DISTINCT i.name), ''), legacy_i.name, 'Todos os públicos') AS institution_names,
                u.name AS author_name
         FROM articles a
         LEFT JOIN article_institutions ai ON ai.article_id = a.id
         LEFT JOIN institutions i ON i.id = ai.institution_id
         LEFT JOIN institutions legacy_i ON legacy_i.id = a.institution_id
         LEFT JOIN users u ON u.id = a.created_by
         WHERE a.id = :id
         GROUP BY a.id"
    );
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    if (!$row) {
        return null;
    }

    if (!user_can_access_article($db, $user, $id)) {
        return null;
    }

    $row['institution_ids'] = get_article_target_ids($db, $id);
    return $row;
}
