<?php
// api/receipts/edit.php — 修正待ち申請の内容変更
require_once __DIR__ . '/../../includes/bootstrap.php';
session_start_safe();
$user = require_api_auth();

$body  = json_decode(file_get_contents('php://input'), true);
$csrf  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) json_error('Invalid CSRF', 403);

$id             = $body['id']             ?? '';
$date           = $body['date']           ?? '';
$vendor         = $body['vendor']         ?? '';
$amount         = (int)($body['amount']   ?? 0);
$tax_rate       = $body['tax_rate']       ?? '';
$category       = $body['category']       ?? '';
$payment_method = $body['payment_method'] ?? '';
$note           = $body['note']           ?? '';

$receipt = get_receipt_by_id($id);
if (!$receipt)                            json_error('申請が見つかりません', 404);
if ($receipt['email'] !== $user['email']) json_error('Forbidden', 403);
if ($receipt['status'] !== '修正待ち')   json_error('修正待ち状態の申請のみ編集できます');

// 変更内容を記録
$changes = [];
if ($receipt['date']           !== $date)           $changes[] = "利用日: {$receipt['date']}→{$date}";
if ($receipt['vendor']         !== $vendor)         $changes[] = "店舗名: {$receipt['vendor']}→{$vendor}";
if ((int)$receipt['amount']    !== $amount)         $changes[] = "金額: ¥{$receipt['amount']}→¥{$amount}";
if ($receipt['tax_rate']       !== $tax_rate)       $changes[] = "税区分: {$receipt['tax_rate']}→{$tax_rate}";
if ($receipt['category']       !== $category)       $changes[] = "カテゴリ: {$receipt['category']}→{$category}";
if ($receipt['payment_method'] !== $payment_method) $changes[] = "支払方法: {$receipt['payment_method']}→{$payment_method}";
if ($receipt['note']           !== $note)           $changes[] = "備考: 変更あり";

$change_detail = !empty($changes)
    ? implode(' / ', $changes)
    : '変更なし';

// 内容を更新
$row_num = sheets_find_row(SHEET_ID, $id);
if ($row_num < 0) json_error('申請が見つかりません', 404);

sheets_batch_update(SHEET_ID, [
    ['range' => "receipts!E{$row_num}", 'values' => [[$date]]],
    ['range' => "receipts!F{$row_num}", 'values' => [[$vendor]]],
    ['range' => "receipts!G{$row_num}", 'values' => [[$amount]]],
    ['range' => "receipts!H{$row_num}", 'values' => [[$tax_rate]]],
    ['range' => "receipts!I{$row_num}", 'values' => [[$category]]],
    ['range' => "receipts!J{$row_num}", 'values' => [[$payment_method]]],
    ['range' => "receipts!K{$row_num}", 'values' => [[$note]]],
]);

// ステータスを「再審査待ち」に
update_receipt_status($id, '再審査待ち', '', $user['username']);

// ログ記録（変更内容の詳細付き）
write_log([
    'operator_id'       => $user['id'],
    'operator_name'     => $user['username'],
    'target_receipt_id' => $id,
    'action'            => 'edit',
    'before_status'     => '修正待ち',
    'after_status'      => '再審査待ち',
    'comment'           => "変更内容: {$change_detail}",
]);

json_response(['ok' => true]);