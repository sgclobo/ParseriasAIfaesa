<?php
/**
 * api/profile.php — Update own profile (photo + password)
 */
require_once dirname(__DIR__) . '/auth.php';
require_login();

$db   = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$isMultipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart') !== false;
$data = $isMultipart ? $_POST : (json_decode(file_get_contents('php://input'), true) ?? []);

$action = $data['action'] ?? '';

// ── Change password ─────────────────────────────────────────────────────────
if ($action === 'change_password') {
    $current = $data['current_password'] ?? '';
    $new     = $data['new_password']     ?? '';
    $confirm = $data['confirm_password'] ?? '';

    if (!$current || !$new || !$confirm) {
        json_response(['error' => 'Todos os campos são obrigatórios.'], 400);
    }
    if ($new !== $confirm) {
        json_response(['error' => 'As senhas não coincidem.'], 400);
    }
    if (strlen($new) < 6) {
        json_response(['error' => 'A senha deve ter pelo menos 6 caracteres.'], 400);
    }

    // Verify current
    $row = $db->querySingle("SELECT password_hash FROM users WHERE id={$user['id']}", true);
    if (!password_verify($current, $row['password_hash'])) {
        json_response(['error' => 'Senha atual incorreta.'], 401);
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password_hash=:h WHERE id=:id");
    $stmt->bindValue(':h',  $hash);
    $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
    $stmt->execute();
    json_response(['success' => true]);
}

// ── Upload / replace photo ──────────────────────────────────────────────────
if ($action === 'update_photo') {
    if (!$isMultipart || !isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        json_response(['error' => 'Nenhuma foto enviada.'], 400);
    }

    $uploadDir = dirname(__DIR__) . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext   = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $allow = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allow)) {
        json_response(['error' => 'Formato de imagem inválido.'], 400);
    }

    // Delete old photo
    $old = $db->querySingle("SELECT photo FROM users WHERE id={$user['id']}", true);
    if ($old && $old['photo']) @unlink($uploadDir . $old['photo']);

    $fname = 'photo_' . uniqid('', true) . '.' . $ext;
    move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $fname);

    $stmt = $db->prepare("UPDATE users SET photo=:p WHERE id=:id");
    $stmt->bindValue(':p',  $fname);
    $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
    $stmt->execute();

    json_response(['success' => true, 'photo' => $fname]);
}

json_response(['error' => 'Ação desconhecida.'], 400);
