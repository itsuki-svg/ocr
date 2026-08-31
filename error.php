<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$reason = $_GET['reason'] ?? '';
$msgs = [
    'rejected'  => ['title'=>'アクセスできません',  'desc'=>'このアカウントは利用が承認されていません。システム管理者にお問い合わせください。'],
    'forbidden' => ['title'=>'権限がありません',    'desc'=>'このページにアクセスする権限がありません。'],
];
$msg = $msgs[$reason] ?? ['title'=>'エラーが発生しました','desc'=>'予期しないエラーが発生しました。'];
layout_head('エラー');
?>
<div class="center-page">
  <div class="center-icon center-icon-error"><i class="ti ti-shield-x"></i></div>
  <h1 class="center-title"><?= htmlspecialchars($msg['title']) ?></h1>
  <p class="center-desc"><?= htmlspecialchars($msg['desc']) ?></p>
  <a href="<?= APP_URL ?>/login.php" class="btn btn-secondary">← ログイン画面に戻る</a>
</div>
<?php layout_foot(); ?>
