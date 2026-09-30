<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
session_start_safe();

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';

if ($error || !$code) {
    redirect('/login.php?error=oauth');
}

// CSRF チェック
if (!hash_equals($_SESSION['csrf_token'] ?? '', $state)) {
    redirect('/login.php?error=csrf');
}

// コードをトークンに交換
$tokens = google_exchange_code($code);
if (empty($tokens['access_token'])) {
    redirect('/login.php?error=token');
}

// ユーザー情報を取得
$info = google_get_userinfo($tokens['access_token']);
$email = $info['email'] ?? '';

if (!$email) {
    redirect('/login.php?error=email');
}

// DBのユーザーを確認
$user = db_one('SELECT * FROM users WHERE email = ?', [$email]);

if (!$user) {
    // 初回ログイン → ユーザーネーム登録へ
    $_SESSION['oauth_email']    = $email;
    $_SESSION['oauth_name']     = $info['name'] ?? '';
    redirect('/register.php');
}

if ($user['status'] === 'pending') {
    login_user($user);
    redirect('/pending.php');
}

if ($user['status'] === 'rejected') {
    redirect('/error.php?reason=rejected');
}

// ログイン成功
login_user($user);
if (in_array($user['role'], ['sysadmin', 'accounting'])) {
    redirect('/admin/receipts.php');
}
redirect('/new.php');
