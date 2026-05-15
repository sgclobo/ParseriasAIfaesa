<?php

/**
 * api/institutions.php — CRUD for institutions (admin only)
 *
 * GET                      → list institutions
 * POST {action:create,...} → create institution
 * POST {action:update,...} → update institution
 * POST {action:delete,...} → delete institution
 */
require_once dirname(__DIR__) . '/auth.php';
require_admin();

$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $res = $db->query('SELECT id, name, created_at FROM institutions ORDER BY name');
    $rows = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    json_response(['institutions' => $rows]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? '';

    if ($action === 'create') {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            json_response(['error' => 'Institution name is required.'], 400);
        }

        $stmt = $db->prepare('INSERT INTO institutions (name) VALUES (:name)');
        $stmt->bindValue(':name', $name);
        try {
            $stmt->execute();
            json_response(['success' => true, 'id' => $db->lastInsertRowID()]);
        } catch (Exception $ex) {
            json_response(['error' => 'Institution already exists.'], 409);
        }
    }

    if ($action === 'update') {
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['name'] ?? ''));
        if (!$id || $name === '') {
            json_response(['error' => 'Invalid institution data.'], 400);
        }

        $stmt = $db->prepare('UPDATE institutions SET name = :name WHERE id = :id');
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        try {
            $stmt->execute();
            json_response(['success' => true]);
        } catch (Exception $ex) {
            json_response(['error' => 'Institution already exists.'], 409);
        }
    }

    if ($action === 'delete') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            json_response(['error' => 'Invalid institution id.'], 400);
        }

        $userCount = (int)$db->querySingle('SELECT COUNT(*) FROM users WHERE institution_id = ' . $id);
        $legacyArticleCount = (int)$db->querySingle('SELECT COUNT(*) FROM articles WHERE institution_id = ' . $id);
        $targetCount = (int)$db->querySingle('SELECT COUNT(*) FROM article_institutions WHERE institution_id = ' . $id);

        if ($userCount > 0 || $legacyArticleCount > 0 || $targetCount > 0) {
            json_response(['error' => 'Cannot delete institution that is linked to users or articles.'], 409);
        }

        $stmt = $db->prepare('DELETE FROM institutions WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        json_response(['success' => true]);
    }

    json_response(['error' => 'Unknown action.'], 400);
}

json_response(['error' => 'Method not allowed.'], 405);
