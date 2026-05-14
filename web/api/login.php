<?php
/**
 * api/login.php — Handle login POST request
 */
require_once dirname(__DIR__) . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    json_response(['error' => 'Invalid request body'], 400);
}

$email    = trim($body['email']    ?? '');
$password = trim($body['password'] ?? '');

if (!$email || !$password) {
    json_response(['error' => 'Email e senha são obrigatórios.'], 400);
}

// Handle session-fresh placeholder
if ($password === '__SESSION_FRESH__') {
    // Auto-login via cookie already handled in auth.php bootstrap
    if (is_logged_in()) {
        json_response(['success' => true]);
    } else {
        json_response(['error' => 'Sessão expirada. Por favor insira a sua senha.'], 401);
    }
}

$result = attempt_login($email, $password);

if ($result['success']) {
    json_response(['success' => true, 'role' => $result['user']['role']]);
} else {
    json_response(['success' => false, 'error' => $result['error']], 401);
}
