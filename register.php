<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
session_start_safe();

// OAuth経由でない場合はログインへ
if (empty($_SESSION['oauth_email'])) {
    redirect('/login.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    if (!$username || mb_strlen($username) > 30) {
        $error = 'ユーザーネームは1〜30文字で入力してください';
    } else {
        $email = $_SESSION['oauth_email'];
        db_execute(
            'INSERT INTO users (email, username, role, status) VALUES (?, ?, ?, ?)',
            [$email, $username, 'general', 'pending']
        );
        // セッションにユーザーを保存
        $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
        login_user($user);
        unset($_SESSION['oauth_email'], $_SESSION['oauth_name']);

        // 管理者へ通知
        discord_notify_new_user($username, $email);
        $mail = build_new_user_mail($username, $email);
        $admin_email = get_setting('admin_email');
        if ($admin_email) {
            send_gmail($admin_email, $mail['subject'], $mail['html'], '領収書整理アプリ', $admin_email);
        }
        redirect('/pending.php');
    }
}

layout_head('ユーザーネーム登録');
?>
<meta name="csrf-token" content="<?= csrf_token() ?>">
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-icon"><i class="ti ti-user-plus"></i></div>
    <h1 class="auth-title">ユーザーネームの登録</h1>
    <p class="auth-sub">アプリ内の表示名を設定してください。<br>メールの差出人名にも使用されます。</p>
    <div class="auth-divider"></div>
    <?php if ($error): ?>
    <div class="err-box" style="width:100%">
      <i class="ti ti-alert-circle"></i>
      <div><p class="err-body"><?= htmlspecialchars($error) ?></p></div>
    </div>
    <?php endif; ?>
    <form method="POST" style="width:100%;display:flex;flex-direction:column;gap:14px">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="form-group" style="margin:0">
        <label>ユーザーネーム <span class="required">*</span></label>
        <input type="text" name="username" placeholder="例: 田中 花子" maxlength="30"
               value="<?= htmlspecialchars($_SESSION['oauth_name'] ?? '') ?>" required>
        <span class="text-sm text-muted mt-1">1〜30文字。変更はシステム管理者のみ可能です。</span>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">登録して始める</button>
    </form>
  </div>
</div>
<?php layout_foot(); ?>
