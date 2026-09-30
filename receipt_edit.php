<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_auth();
$id   = $_GET['id'] ?? '';
if (!$id) redirect('/history.php');

$receipt = get_receipt_by_id($id);
if (!$receipt) redirect('/history.php');
if ($receipt['email'] !== $user['email']) redirect('/error.php?reason=forbidden');
if ($receipt['status'] !== '修正待ち') redirect('/receipt.php?id='.$id);

layout_head('申請の修正');
?>
<meta name="csrf-token" content="<?= csrf_token() ?>">
<?php layout_topbar($user); ?>
<main class="main main-narrow">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
    <a href="<?= APP_URL ?>/history.php" class="btn btn-sm btn-secondary">← 履歴に戻る</a>
    <h1 style="font-size:18px;font-weight:600;margin:0">申請の修正</h1>
    <span class="badge badge-修正待ち">修正待ち</span>
  </div>

  <?php if ($receipt['last_comment']): ?>
  <div class="warn-box" style="margin-bottom:12px">
    <i class="ti ti-alert-circle"></i>
    <div>
      <p class="warn-title">修正依頼のお知らせ</p>
      <p class="warn-body"><?= htmlspecialchars($receipt['last_comment']) ?></p>
      <p class="text-sm text-muted mt-1">— <?= htmlspecialchars($receipt['status_updated_by']) ?></p>
    </div>
  </div>
  <?php endif; ?>

  <div class="card" style="margin-bottom:12px">
    <p class="card-title">領収書画像（差し替え不可）</p>
    <?php if ($receipt['image_url']): ?>
    <div class="img-preview"><img src="<?= htmlspecialchars(str_replace('/view','/preview',$receipt['image_url'])) ?>" alt="領収書"></div>
    <?php else: ?>
    <div class="img-preview-none">画像なし</div>
    <?php endif; ?>
  </div>

  <form class="card" id="edit-form">
    <p class="card-title">内容を修正してください</p>
    <div class="form-grid">
      <div class="form-group">
        <label>利用日 <span class="required">*</span></label>
        <input type="date" id="f-date" value="<?= htmlspecialchars($receipt['date']) ?>" required>
      </div>
      <div class="form-group">
        <label>金額（税込）<span class="required">*</span></label>
        <input type="number" id="f-amount" value="<?= htmlspecialchars($receipt['amount']) ?>" required>
      </div>
    </div>
    <div class="form-group">
      <label>店舗名 / 取引先 <span class="required">*</span></label>
      <input type="text" id="f-vendor" value="<?= htmlspecialchars($receipt['vendor']) ?>" maxlength="50" required>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>税区分 <span class="required">*</span></label>
        <select id="f-tax">
          <?php foreach(['10%','8%','非課税'] as $t): ?>
          <option <?= $receipt['tax_rate']===$t?'selected':'' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>支払方法 <span class="required">*</span></label>
        <select id="f-pay">
          <?php foreach(['個人立替','法人カード','現金'] as $p): ?>
          <option <?= $receipt['payment_method']===$p?'selected':'' ?>><?= $p ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label>カテゴリ <span class="required">*</span></label>
      <select id="f-cat">
        <?php foreach(['会議費','交通費','消耗品','接待交際費','その他'] as $c): ?>
        <option <?= $receipt['category']===$c?'selected':'' ?>><?= $c ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>備考</label>
      <textarea id="f-note" rows="3" maxlength="200"><?= htmlspecialchars($receipt['note']) ?></textarea>
    </div>
    <div class="btn-row">
      <a href="<?= APP_URL ?>/history.php" class="btn btn-secondary">キャンセル</a>
      <button type="submit" class="btn btn-primary" id="submit-btn">修正して再申請</button>
    </div>
  </form>
</main>
<script>
document.getElementById('edit-form').onsubmit = async e => {
  e.preventDefault();
  const btn = document.getElementById('submit-btn');
  btn.disabled = true; btn.innerHTML = '<div class="spinner"></div> 送信中...';
  try {
    await apiFetch('<?= APP_URL ?>/api/receipts/edit.php', {
      method:'POST',
      body: JSON.stringify({
        id: '<?= htmlspecialchars($id) ?>',
        date:           document.getElementById('f-date').value,
        vendor:         document.getElementById('f-vendor').value,
        amount:         Number(document.getElementById('f-amount').value),
        tax_rate:       document.getElementById('f-tax').value,
        category:       document.getElementById('f-cat').value,
        payment_method: document.getElementById('f-pay').value,
        note:           document.getElementById('f-note').value,
      }),
    });
    showToast('再申請しました');
    setTimeout(() => location.href='<?= APP_URL ?>/history.php', 1200);
  } catch(e) { showToast(e.message,'error'); btn.disabled=false; btn.innerHTML='修正して再申請'; }
};
</script>
<?php layout_foot(); ?>
