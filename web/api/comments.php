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

if ($method === 'GET') {
    $articleId = (int)($_GET['article_id'] ?? 0);
    if (!$articleId) json_response(['error' => 'article_id é obrigatório.'], 400);

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
