<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$user = require_role('sysadmin');
layout_head('管理者設定');
$settings = [];
foreach(db_query('SELECT key_name, value FROM settings') as $row) {
    $settings[$row['key_name']] = $row['value'];
}
$models = ['gemini-1.5-flash','gemini-1.5-flash-8b','gemini-1.5-pro','gemini-1.0-pro-vision'];
?>
<meta name="csrf-token" content="<?= csrf_token() ?>">
<?php layout_topbar($user); ?>
<main class="main main-mid">
  <h1 class="page-title">管理者設定</h1>
  <div class="card">

    <div class="setting-row">
      <p class="setting-label">OCRモデル</p>
      <p class="setting-desc">領収書画像の読み取りに使用するGemini APIモデルを選択します。</p>
      <div class="setting-inline">
        <select id="ocr-model">
          <?php foreach($models as $m): ?>
          <option <?= ($settings['ocr_model']??'')===$m?'selected':'' ?>><?= $m ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm" onclick="save('ocr_model',document.getElementById('ocr-model').value)">保存</button>
      </div>
    </div>

    <div class="setting-row">
      <p class="setting-label">Discord Webhook URL</p>
      <p class="setting-desc">審査待ち通知・新規ユーザー登録通知を送信するDiscord WebhookのURLを登録します。</p>
      <div class="setting-inline">
        <input type="url" id="discord-url" value="<?= htmlspecialchars($settings['discord_webhook']??'') ?>" placeholder="https://discord.com/api/webhooks/...">
        <button class="btn btn-secondary btn-sm" onclick="testDiscord()"><i class="ti ti-send"></i> テスト</button>
        <button class="btn btn-primary btn-sm" onclick="save('discord_webhook',document.getElementById('discord-url').value)">保存</button>
      </div>
    </div>

    <div class="setting-row">
      <p class="setting-label">Gmail送信元アドレス</p>
      <p class="setting-desc">通知メール送信に使用するGmailアカウントを登録します。</p>
      <div class="setting-inline">
        <input type="email" id="gmail-sender" value="<?= htmlspecialchars($settings['gmail_sender']??'') ?>" placeholder="accounting@gmail.com">
        <button class="btn btn-primary btn-sm" onclick="save('gmail_sender',document.getElementById('gmail-sender').value)">保存</button>
      </div>
    </div>

    <div class="setting-row">
      <p class="setting-label">管理者通知先メール</p>
      <p class="setting-desc">新規ユーザー登録時の通知先メールアドレスです。</p>
      <div class="setting-inline">
        <input type="email" id="admin-email" value="<?= htmlspecialchars($settings['admin_email']??'') ?>" placeholder="admin@gmail.com">
        <button class="btn btn-primary btn-sm" onclick="save('admin_email',document.getElementById('admin-email').value)">保存</button>
      </div>
    </div>

  </div>
</main>
<script>
async function save(key, value) {
  try {
    await apiFetch('<?= APP_URL ?>/api/settings/save.php', {method:'POST', body:JSON.stringify({[key]:value})});
    showToast('設定を保存しました');
  } catch(e) { showToast(e.message,'error'); }
}
async function testDiscord() {
  const url = document.getElementById('discord-url').value;
  if (!url) { showToast('Webhook URLを入力してください','error'); return; }
  try {
    await fetch(url, {method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({content:'✅ 領収書整理アプリからのテスト送信です。'})});
    showToast('テスト送信しました');
  } catch(e) { showToast('送信に失敗しました','error'); }
}
</script>
<?php layout_foot(); ?>
