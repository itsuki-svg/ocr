<?php
// api/users/approve.php — 承認・拒否・権限変更
require_once __DIR__ . '/../../includes/bootstrap.php';
session_start_safe();
$user = require_api_role('sysadmin');

$body = json_decode(file_get_contents('php://input'), true);
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) json_error('Invalid CSRF', 403);

$target_id = (int)($body['user_id'] ?? 0);
$action    = $body['action']   ?? '';
$role      = $body['role']     ?? '';
$username  = $body['username'] ?? '';

if ($target_id === (int)$user['id'] && ($action || $role)) {
    json_error('自分自身の権限は変更できません');
}

$target = db_one('SELECT * FROM users WHERE id=?', [$target_id]);
if (!$target) json_error('ユーザーが見つかりません', 404);

$app_url = APP_URL;
$sender_email = get_setting('gmail_sender');

if ($action === 'approve' || $action === 'reject') {
    $new_status = $action === 'approve' ? 'active' : 'rejected';
    db_execute('UPDATE users SET status=?, updated_at=NOW() WHERE id=?', [$new_status, $target_id]);
    write_log([
        'operator_id'   => $user['id'],
        'operator_name' => $user['username'],
        'action'        => $action === 'approve' ? 'user_approve' : 'user_reject',
        'before_status' => $target['status'],
        'after_status'  => $new_status,
        'comment'       => "対象: {$target['username']} ({$target['email']})",
    ]);
    // 承認/拒否メール
    if ($sender_email) {
        $mail = build_approval_mail($action === 'approve', $target['username']);
        send_gmail($target['email'], $mail['subject'], $mail['html'], $user['username'], $sender_email);
    }
    json_response(['ok' => true]);
}

if ($role) {
    $allowed = ['sysadmin','accounting','general'];
    if (!in_array($role, $allowed, true)) json_error('無効なroleです');
    db_execute('UPDATE users SET role=?, updated_at=NOW() WHERE id=?', [$role, $target_id]);
    write_log([
        'operator_id'   => $user['id'],
        'operator_name' => $user['username'],
        'action'        => 'user_role_change',
        'before_status' => $target['role'],
        'after_status'  => $role,
        'comment'       => "対象: {$target['username']}",
    ]);
    json_response(['ok' => true]);
}

if ($username) {
    if (mb_strlen($username) > 30) json_error('ユーザーネームは30文字以内にしてください');
    db_execute('UPDATE users SET username=?, updated_at=NOW() WHERE id=?', [$username, $target_id]);
    json_response(['ok' => true]);
}

json_error('更新内容が不明です');
