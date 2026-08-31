<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_auth();
layout_head('新規申請');
$today = date('Y-m-d');
?>
<meta name="csrf-token" content="<?= csrf_token() ?>">
<?php layout_topbar($user); ?>
<main class="main main-narrow">
  <h1 class="page-title">新規申請</h1>

  <!-- 画像アップロード -->
  <div class="card" style="margin-bottom:12px">
    <p class="card-title">領収書画像</p>
    <div class="upload-zone" id="upload-zone">
      <i class="ti ti-cloud-upload"></i>
      <p>クリックまたはドラッグ&ドロップ</p>
      <small>JPEG / PNG / HEIC ・最大10MB・スマートフォンはカメラ起動可</small>
    </div>
    <div id="img-wrap" style="display:none;position:relative">
      <div class="img-preview"><img id="img-preview" src="" alt="領収書"></div>
      <button class="remove-img" id="remove-img" title="削除"><i class="ti ti-x"></i></button>
    </div>
    <div id="ocr-loading" style="display:none;text-align:center;padding:20px">
      <div class="spinner" style="width:28px;height:28px;margin:0 auto 8px"></div>
      <p class="text-sm text-muted">OCR処理中...</p>
    </div>
  </div>

  <!-- 入力フォーム -->
  <form id="receipt-form" class="card">
    <p class="card-title">申請内容</p>
    <input type="hidden" id="image-data" name="image_data" value="">
    <input type="hidden" id="image-name" name="image_name" value="">
    <input type="hidden" id="input-method" name="input_method" value="manual">

    <div class="form-grid">
      <div class="form-group">
        <label>利用日 <span class="required">*</span></label>
        <input type="date" id="f-date" name="date" value="<?= $today ?>" required>
      </div>
      <div class="form-group">
        <label>金額（税込）<span class="required">*</span></label>
        <input type="number" id="f-amount" name="amount" placeholder="例: 580" min="1" max="9999999" required>
      </div>
    </div>
    <div class="form-group">
      <label>店舗名 / 取引先 <span class="required">*</span></label>
      <input type="text" id="f-vendor" name="vendor" placeholder="例: スターバックス渋谷店" maxlength="50" required>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>税区分 <span class="required">*</span></label>
        <select id="f-tax" name="tax_rate">
          <option>10%</option><option>8%</option><option>非課税</option>
        </select>
      </div>
      <div class="form-group">
        <label>支払方法 <span class="required">*</span></label>
        <select id="f-pay" name="payment_method">
          <option>個人立替</option><option>法人カード</option><option>現金</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label>カテゴリ <span class="required">*</span></label>
      <select id="f-cat" name="category">
        <option>会議費</option><option>交通費</option><option>消耗品</option><option>接待交際費</option><option>その他</option>
      </select>
    </div>
    <div class="form-group">
      <label>備考</label>
      <textarea name="note" rows="3" placeholder="用途・参加者など（200字以内）" maxlength="200"></textarea>
    </div>
    <div class="btn-row">
      <button type="button" class="btn btn-secondary" id="clear-btn">クリア</button>
      <button type="submit" class="btn btn-primary" id="submit-btn">保存する</button>
    </div>
  </form>
</main>

<script>
let imageFile = null;

// app.js読み込み後に実行
window.addEventListener('DOMContentLoaded', () => {
  initDropzone(document.getElementById('upload-zone'), handleFile);

  document.getElementById('remove-img').onclick = () => {
    imageFile = null;
    document.getElementById('image-data').value = '';
    document.getElementById('image-name').value = '';
    document.getElementById('input-method').value = 'manual';
    document.getElementById('img-wrap').style.display = 'none';
    document.getElementById('upload-zone').style.display = 'block';
  };

  document.getElementById('clear-btn').onclick = () => {
    document.getElementById('receipt-form').reset();
    document.getElementById('f-date').value = new Date().toISOString().slice(0,10);
    document.getElementById('img-wrap').style.display = 'none';
    document.getElementById('upload-zone').style.display = 'block';
    document.getElementById('image-data').value = '';
  };
});

async function handleFile(file) {
  imageFile = file;
  document.getElementById('upload-zone').style.display = 'none';
  document.getElementById('ocr-loading').style.display = 'block';

  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('img-preview').src = e.target.result;
    const b64 = e.target.result.split(',')[1];
    document.getElementById('image-data').value = b64;
    document.getElementById('image-name').value = file.name;
    document.getElementById('input-method').value = 'ocr';
  };
  reader.readAsDataURL(file);

  try {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    const res = await fetch('<?= APP_URL ?>/api/ocr.php', { method:'POST', body:fd });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error);
    if (data.date)           document.getElementById('f-date').value   = data.date;
    if (data.vendor)         document.getElementById('f-vendor').value = data.vendor;
    if (data.amount)         document.getElementById('f-amount').value = data.amount;
    if (data.tax_rate)       document.getElementById('f-tax').value    = data.tax_rate;
    if (data.category)       document.getElementById('f-cat').value    = data.category;
    if (data.payment_method) document.getElementById('f-pay').value    = data.payment_method;
    showToast('OCR完了。内容を確認・修正してください。');
  } catch(e) {
    showToast('OCRに失敗しました。手入力してください。', 'error');
  }
  document.getElementById('ocr-loading').style.display = 'none';
  document.getElementById('img-wrap').style.display = 'block';
}

document.getElementById('receipt-form').onsubmit = async e => {
  e.preventDefault();
  const btn = document.getElementById('submit-btn');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner"></div> 保存中...';
  try {
    const fd = new FormData(e.target);
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    const res = await fetch('<?= APP_URL ?>/api/receipts/create.php', { method:'POST', body:fd });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error);
    showToast('保存しました');
    setTimeout(() => { location.href = '<?= APP_URL ?>/history.php'; }, 1000);
  } catch(err) {
    showToast(err.message || '保存に失敗しました', 'error');
  }
  btn.disabled = false;
  btn.innerHTML = '保存する';
};
</script>
<?php layout_foot(); ?>