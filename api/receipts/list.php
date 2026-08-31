<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// api/receipts/list.php — 全件取得（経理・管理者）
require_once __DIR__ . '/../../includes/bootstrap.php';
session_start_safe();
require_api_role('sysadmin', 'accounting');

$status   = $_GET['status']   ?? '';
$category = $_GET['category'] ?? '';

$all = get_all_receipts();
if ($status)   $all = array_filter($all, fn($r) => $r['status']   === $status);
if ($category) $all = array_filter($all, fn($r) => $r['category'] === $category);

usort($all, fn($a,$b) => strcmp($b['created_at'], $a['created_at']));
json_response(array_values($all));
