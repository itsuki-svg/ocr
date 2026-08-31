<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
session_start_safe();

// ログイン済みならリダイレクト
$user = current_user();
if ($user) {
    if ($user['status'] === 'pending')  redirect('/pending.php');
    if ($user['status'] === 'rejected') redirect('/error.php?reason=rejected');
    if (in_array($user['role'], ['sysadmin','accounting'])) redirect('/admin/receipts.php');
    redirect('/new.php');
}

$error = $_GET['error'] ?? '';
layout_head('ログイン');
?>
<meta name="csrf-token" content="<?= csrf_token() ?>">
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-icon"><i class="ti ti-receipt"></i></div>
    <h1 class="auth-title">領収書整理アプリ</h1>
    <p class="auth-sub">社内経費精算システム</p>

    <?php if ($error): ?>
    <div class="err-box" style="width:100%">
      <i class="ti ti-alert-circle"></i>
      <div><p class="err-body"><?= htmlspecialchars($error === 'invalid' ? 'IDまたはパスワードが正しくありません' : 'ログインに失敗しました') ?></p></div>
    </div>
    <?php endif; ?>

    <div class="auth-divider"></div>

    <!-- タブ切り替え -->
    <div class="tab-switch" style="width:100%">
      <button class="tab-btn active" id="tab-google" onclick="switchTab('google')">一般ログイン</button>
      <button class="tab-btn" id="tab-admin" onclick="switchTab('admin')">管理者ログイン</button>
    </div>

    <!-- Google ログイン -->
    <div id="pane-google" style="width:100%;display:flex;flex-direction:column;gap:12px">
      <a href="<?= google_auth_url() ?>" class="google-btn">
        <svg viewBox="0 0 18 18" width="18" height="18">
          <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.716v2.259h2.908C18.622 13.815 17.64 11.507 17.64 9.2z" fill="#4285F4"/>
          <path d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332C2.438 15.983 5.482 18 9 18z" fill="#34A853"/>
          <path d="M3.964 10.706c-.18-.54-.282-1.117-.282-1.706s.102-1.166.282-1.706V4.962H.957C.347 6.175 0 7.55 0 9s.348 2.825.957 4.038l3.007-2.332z" fill="#FBBC05"/>
          <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.962L3.964 6.294C4.672 4.169 6.656 3.58 9 3.58z" fill="#EA4335"/>
        </svg>
        Googleでログイン
      </a>
      <p class="auth-notice">初回ログイン後、システム管理者の承認が必要です</p>
    </div>

    <!-- 管理者 ID/PASS ログイン -->
    <div id="pane-admin" style="width:100%;display:none">
      <form method="POST" action="<?= APP_URL ?>/api/auth/admin_login.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
          <label>ログインID <span class="required">*</span></label>
          <input type="text" name="login_id" placeholder="ログインIDを入力" required autocomplete="username">
        </div>
        <div class="form-group">
          <label>パスワード <span class="required">*</span></label>
          <div class="input-wrap">
            <input type="password" name="password" id="pass-input" placeholder="パスワードを入力" required autocomplete="current-password">
            <button type="button" class="input-icon" onclick="togglePass()"><i class="ti ti-eye" id="eye-icon"></i></button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:4px">ログイン</button>
        <p class="auth-notice" style="margin-top:10px">管理者専用ログインです</p>
      </form>
    </div>
  </div>
</div>
<script>
function switchTab(tab) {
  document.getElementById('pane-google').style.display = tab==='google' ? 'flex' : 'none';
  document.getElementById('pane-admin').style.display  = tab==='admin'  ? 'block': 'none';
  document.getElementById('tab-google').classList.toggle('active', tab==='google');
  document.getElementById('tab-admin').classList.toggle('active',  tab==='admin');
}
function togglePass() {
  const inp = document.getElementById('pass-input');
  const ico = document.getElementById('eye-icon');
  if (inp.type==='password') { inp.type='text'; ico.className='ti ti-eye-off'; }
  else { inp.type='password'; ico.className='ti ti-eye'; }
}
// エラー時は管理者タブを開く
<?php if ($error): ?>switchTab('admin');<?php endif; ?>
</script>
<?php layout_foot(); ?>
