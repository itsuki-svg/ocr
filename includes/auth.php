<?php
// includes/auth.php — 認証・セッション管理

function session_start_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function current_user(): ?array {
    session_start_safe();
    return $_SESSION['user'] ?? null;
}

function login_user(array $user): void {
    session_start_safe();
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
}

function logout(): void {
    session_start_safe();
    $_SESSION = [];
    session_destroy();
}

function require_auth(): array {
    $user = current_user();
    if (!$user) {
        redirect('/login.php');
    }
    if ($user['status'] === 'pending') {
        redirect('/pending.php');
    }
    if ($user['status'] === 'rejected') {
        redirect('/error.php?reason=rejected');
    }
    return $user;
}

function require_role(string ...$roles): array {
    $user = require_auth();
    if (!in_array($user['role'], $roles, true)) {
        redirect('/error.php?reason=forbidden');
    }
    return $user;
}

function redirect(string $path): never {
    $base = rtrim(APP_URL, '/');
    header('Location: ' . $base . $path);
    exit;
}

function json_response(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $status = 400): never {
    json_response(['error' => $message], $status);
}

function csrf_token(): string {
    session_start_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        json_error('Invalid CSRF token', 403);
    }
}

function require_api_auth(): array {
    session_start_safe();
    $user = current_user();
    if (!$user || $user['status'] !== 'active') {
        json_error('Unauthorized', 401);
    }
    return $user;
}

function require_api_role(string ...$roles): array {
    $user = require_api_auth();
    if (!in_array($user['role'], $roles, true)) {
        json_error('Forbidden', 403);
    }
    return $user;
}
