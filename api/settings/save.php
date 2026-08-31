<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
session_start_safe();
require_api_role('sysadmin');

$body = json_decode(file_get_contents('php://input'), true);
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) json_error('Invalid CSRF', 403);

$allowed = ['ocr_model','discord_webhook','gmail_sender','admin_email','gmail_tokens'];
foreach ($body as $key => $val) {
    if (!in_array($key, $allowed, true)) continue;
    db_execute(
        'INSERT INTO settings (key_name, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?',
        [$key, $val, $val]
    );
}
json_response(['ok' => true]);
