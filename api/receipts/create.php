<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../includes/bootstrap.php';
session_start_safe();
$user = require_api_auth();
csrf_check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);

$date           = trim($_POST['date']           ?? '');
$vendor         = trim($_POST['vendor']         ?? '');
$amount         = (int)($_POST['amount']        ?? 0);
$tax_rate       = trim($_POST['tax_rate']       ?? '');
$category       = trim($_POST['category']       ?? '');
$payment_method = trim($_POST['payment_method'] ?? '');
$note           = trim($_POST['note']           ?? '');
$input_method   = trim($_POST['input_method']   ?? 'manual');
$image_data     = $_POST['image_data'] ?? '';
$image_name     = $_POST['image_name'] ?? '';

if (!$date || !$vendor || !$amount || !$tax_rate || !$category || !$payment_method) {
    json_error('必須項目が不足しています');
}

// 画像アップロード
$image_url = '';
if ($image_data && $image_name) {
    $ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
    $mime_map = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','heic'=>'image/heic','heif'=>'image/heif'];
    $mime = $mime_map[$ext] ?? 'image/jpeg';
    $year_month = substr($date, 0, 7); // "2026-05"
    $folder_id  = drive_get_or_create_folder(DRIVE_FOLDER_ID, $year_month);
    $u = str_replace(' ', '', $user['username']);
    $v = mb_substr(preg_replace('/[\/\\\\:\*\?"<>\|]/', '', $vendor), 0, 20);
    $file_name = date('Ymd', strtotime($date)) . "_{$u}_{$v}_{$amount}.{$ext}";
    $image_url = drive_upload($folder_id, $file_name, base64_decode($image_data), $mime);
}

// Sheetsに追記
$id  = generate_uuid();
$now = date('Y-m-d H:i:s');
$row = [
    $id, $now, $user['email'], $user['username'],
    $date, $vendor, $amount, $tax_rate, $category, $payment_method,
    $note, $image_url, $input_method,
    '審査待ち', '', $now, $user['username'],
];
sheets_append(SHEET_ID, 'receipts!A:Q', $row);

// ログ記録
write_log([
    'operator_id'       => $user['id'],
    'operator_name'     => $user['username'],
    'target_receipt_id' => $id,
    'action'            => 'create',
    'after_status'      => '審査待ち',
]);

json_response(['ok' => true, 'id' => $id]);
