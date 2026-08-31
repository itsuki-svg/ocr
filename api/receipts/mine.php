<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
session_start_safe();
$user = require_api_auth();

$days  = (int)($_GET['days'] ?? 30);
$since = date('Y-m-d', strtotime("-{$days} days"));

$all  = get_all_receipts();
$mine = array_filter($all, fn($r) =>
    $r['email'] === $user['email'] && substr($r['date'], 0, 10) >= $since
);
usort($mine, fn($a,$b) => strcmp($b['date'], $a['date']));
json_response(array_values($mine));
