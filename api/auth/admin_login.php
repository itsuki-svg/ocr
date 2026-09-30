<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
session_start_safe();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/login.php');
}

csrf_check();

$login_id = trim($_POST['login_id'] ?? '');
$password  = $_POST['password'] ?? '';

if (!$login_id || !$password) {
    redirect('/login.php?error=invalid');
}

// admin_credentials を検索
$cred = db_one(
    'SELECT ac.password, ac.user_id FROM admin_credentials ac WHERE ac.login_id = ?',
    [$login_id]
);

if (!$cred || !password_verify($password, $cred['password'])) {
    redirect('/login.php?error=invalid');
}

// ユーザー情報を取得
$user = db_one('SELECT * FROM users WHERE id = ?', [$cred['user_id']]);

if (!$user || $user['status'] !== 'active') {
    redirect('/login.php?error=invalid');
}

// セッションにログイン情報を保存
login_user($user);

// ロールに応じてリダイレクト
redirect('/admin/receipts.php');
