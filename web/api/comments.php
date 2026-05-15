<?php

/**
 * api/comments.php — Get and post comments for an article
 *
 * GET  ?article_id=X         → list comments
 * POST {article_id, comment} → post comment
 */
require_once dirname(__DIR__) . '/auth.php';
require_login();

$db   = get_db();
$user = current_user();
$method = $_SERVER['REQUEST_METHOD'];

function article_is_visible(SQLite3 $db, array $user, int $articleId): bool
{
    $stmt = $db->prepare(
        "SELECT a.id, a.institution_id
         FROM articles a
         WHERE a.id = :id"
    );
    $stmt->bindValue(':id', $articleId, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    if (!$row) {
        return false;
    }

    if ($user['role'] === 'admin') {
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

    // Legacy fallback for old rows with only articles.institution_id
    if ($row['institution_id'] === null) {
        return true;
    }

    return (int)$row['institution_id'] === $institutionId;
}

if ($method === 'GET') {
    $articleId = (int)($_GET['article_id'] ?? 0);
    if (!$articleId) json_response(['error' => 'article_id é obrigatório.'], 400);

    if (!article_is_visible($db, $user, $articleId)) {
        json_response(['error' => 'Forbidden'], 403);
    }

    $stmt = $db->prepare("
        SELECT c.id, c.comment, c.created_at,
               u.id AS user_id, u.name AS user_name, u.photo AS user_photo
        FROM comments c
        JOIN users u ON u.id = c.user_id
        WHERE c.article_id = :aid
        ORDER BY c.created_at ASC
    ");
    $stmt->bindValue(':aid', $articleId, SQLITE3_INTEGER);
    $res  = $stmt->execute();
    $rows = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) $rows[] = $r;
    json_response(['comments' => $rows]);
}

if ($method === 'POST') {
    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $articleId = (int)($data['article_id'] ?? 0);
    $comment   = trim($data['comment'] ?? '');

    if (!$articleId || !$comment) {
        json_response(['error' => 'article_id e comment são obrigatórios.'], 400);
    }

    if (!article_is_visible($db, $user, $articleId)) {
        json_response(['error' => 'Forbidden'], 403);
    }

    $stmt = $db->prepare("
        INSERT INTO comments (article_id, user_id, comment)
        VALUES (:a, :u, :c)
    ");
    $stmt->bindValue(':a', $articleId, SQLITE3_INTEGER);
    $stmt->bindValue(':u', $user['id'], SQLITE3_INTEGER);
    $stmt->bindValue(':c', $comment);
    $stmt->execute();

    json_response([
        'success'    => true,
        'id'         => $db->lastInsertRowID(),
        'user_name'  => $user['name'],
        'user_photo' => $user['photo'],
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}
