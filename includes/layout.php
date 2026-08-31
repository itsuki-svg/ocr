<?php
// includes/layout.php — 共通レイアウト

function layout_head(string $title = '領収書整理アプリ'): void { ?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> — 領収書整理アプリ</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.7.0/dist/tabler-icons.min.css">
</head>
<body>
<?php } ?>

<?php
function layout_topbar(array $user): void {
    $role_labels = ['sysadmin'=>'システム管理者','accounting'=>'経理担当者','general'=>'一般社員'];
    $role_label  = $role_labels[$user['role']] ?? '';
    $is_admin    = in_array($user['role'], ['sysadmin','accounting'], true);
    $base        = APP_URL;
?>
<header class="topbar">
  <div class="topbar-inner">
    <div class="topbar-left">
      <i class="ti ti-receipt"></i>
      <span class="topbar-title">領収書整理アプリ</span>
    </div>
    <nav class="topbar-nav" id="topbar-nav">
      <?php if ($is_admin): ?>
      <a href="<?=$base?>/admin/receipts.php" class="nav-link <?=str_contains($_SERVER['REQUEST_URI'],'admin/receipts')?'active':''?>">全件一覧</a>
      <?php endif; ?>
      <?php if ($user['role']==='sysadmin'): ?>
      <a href="<?=$base?>/admin/users.php"    class="nav-link <?=str_contains($_SERVER['REQUEST_URI'],'admin/users')?'active':''?>">ユーザー管理</a>
      <a href="<?=$base?>/admin/settings.php" class="nav-link <?=str_contains($_SERVER['REQUEST_URI'],'admin/settings')?'active':''?>">設定</a>
      <a href="<?=$base?>/admin/logs.php"     class="nav-link <?=str_contains($_SERVER['REQUEST_URI'],'admin/logs')?'active':''?>">ログ</a>
      <?php endif; ?>
      <a href="<?=$base?>/new.php"     class="nav-link <?=str_contains($_SERVER['REQUEST_URI'],'new.php')?'active':''?>">新規申請</a>
      <a href="<?=$base?>/history.php" class="nav-link <?=str_contains($_SERVER['REQUEST_URI'],'history')?'active':''?>">申請履歴</a>
      <div class="topbar-user">
        <div class="topbar-user-info">
          <span class="topbar-username"><?=htmlspecialchars($user['username'])?></span>
          <span class="role-badge role-<?=htmlspecialchars($user['role'])?>"><?=htmlspecialchars($role_label)?></span>
        </div>
        <a href="<?=$base?>/api/auth/logout.php" class="btn-icon" title="ログアウト"><i class="ti ti-logout"></i></a>
      </div>
    </nav>
    <button class="hamburger" id="hamburger" aria-label="メニュー">
      <i class="ti ti-menu-2"></i>
    </button>
  </div>
</header>
<?php } ?>

<?php
function layout_foot(): void { ?>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
<?php } ?>
