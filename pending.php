<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
session_start_safe();
$user = current_user();
if (!$user) redirect('/login.php');
layout_head('承認待ち');
?>
<div class="center-page">
  <div class="center-icon center-icon-warn"><i class="ti ti-clock"></i></div>
  <h1 class="center-title">承認待ちです</h1>
  <p class="center-desc">システム管理者がアカウントを確認しています。<br>承認後にご利用いただけます。<br>承認完了後、メールでお知らせします。</p>
  <div class="info-box">
    <p>登録メール: <strong><?= htmlspecialchars($user['email']) ?></strong></p>
    <p class="text-sm text-muted mt-1">管理者へはDiscordおよびメールで通知済みです</p>
  </div>
  <a href="<?= APP_URL ?>/api/auth/logout.php" class="btn btn-secondary">ログアウト</a>
</div>
<?php layout_foot(); ?>
