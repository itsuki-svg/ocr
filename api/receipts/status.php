<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
session_start_safe();
$user = require_api_role('sysadmin', 'accounting');

$body = json_decode(file_get_contents('php://input'), true);
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) json_error('Invalid CSRF', 403);

$id      = $body['id']      ?? '';
$status  = $body['status']  ?? '';
$comment = $body['comment'] ?? '';

if (!$id || !$status) json_error('パラメータ不足');

$require_comment = ['修正待ち', '差し戻し'];
if (in_array($status, $require_comment, true) && !trim($comment)) {
    json_error('このステータスにはコメントが必須です');
}

$receipt = get_receipt_by_id($id);
if (!$receipt) json_error('申請が見つかりません', 404);

$before = $receipt['status'];
update_receipt_status($id, $status, $comment, $user['username']);

// ログコメント詳細
$log_comment = "申請者: {$receipt['username']} / 店舗: {$receipt['vendor']} / 金額: ¥{$receipt['amount']}";
if ($comment) {
    $log_comment .= " / 審査コメント: {$comment}";
}

write_log([
    'operator_id'       => $user['id'],
    'operator_name'     => $user['username'],
    'target_receipt_id' => $id,
    'action'            => 'status_change',
    'before_status'     => $before,
    'after_status'      => $status,
    'comment'           => $log_comment,
]);

// メール送信
$sender_email = get_setting('gmail_sender');
if ($sender_email) {
    $mail = build_status_mail($status, $receipt, $comment, $user['username']);
    send_gmail($receipt['email'], $mail['subject'], $mail['html'], $user['username'], $sender_email);
}

json_response(['ok' => true]);