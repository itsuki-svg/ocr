<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_auth();
$id   = $_GET['id'] ?? '';
if (!$id) redirect('/history.php');

$receipt = get_receipt_by_id($id);
if (!$receipt) { echo '申請が見つかりません'; exit; }

// 一般社員は自分の申請のみ
if ($user['role'] === 'general' && $receipt['email'] !== $user['email']) redirect('/error.php?reason=forbidden');

$is_reviewer = in_array($user['role'], ['sysadmin','accounting'], true);
$transitions = [
    '審査待ち'  => ['受理','修正待ち'],
    '再審査待ち'=> ['受理','修正待ち'],
    '受理'      => ['差し戻し'],
    '修正待ち'  => [],
    '差し戻し'  => [],
];
$next_statuses = $transitions[$receipt['status']] ?? [];

// 修正画面へリダイレクト（一般社員・修正待ち）
if ($user['role']==='general' && $receipt['status']==='修正待ち') redirect('/receipt_edit.php?id='.$id);

// Google DriveのファイルIDを抽出
preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $receipt['image_url'] ?? '', $m);
$file_id = $m[1] ?? '';

layout_head('申請詳細');
?>
<meta name="csrf-token" content="<?= csrf_token() ?>">
<?php layout_topbar($user); ?>
<main class="main main-mid">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
    <a href="javascript:history.back()" class="btn btn-sm btn-secondary">← 戻る</a>
    <h1 style="font-size:18px;font-weight:600;margin:0">申請詳細</h1>
    <span class="badge badge-<?= htmlspecialchars($receipt['status']) ?>"><?= htmlspecialchars($receipt['status']) ?></span>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
    <!-- 画像 -->
    <div class="card">
      <p class="card-title">領収書画像</p>
      <?php if ($receipt['image_url'] && $file_id): ?>
      <div style="margin-bottom:10px;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;background:#f9fafb;min-height:200px;display:flex;align-items:center;justify-content:center">
        <img src="<?= APP_URL ?>/api/image_proxy.php?id=<?= htmlspecialchars($file_id) ?>"
             alt="領収書"
             style="max-width:100%;max-height:420px;object-fit:contain;display:block"
             onerror="this.parentNode.innerHTML='<p style=\'color:#9ca3af;font-size:13px;padding:20px\'>画像を読み込めませんでした</p>'">
      </div>
      <a href="<?= htmlspecialchars($receipt['image_url']) ?>" target="_blank" class="btn btn-sm btn-secondary">
        <i class="ti ti-external-link"></i> Google Driveで開く
      </a>
      <?php elseif ($receipt['image_url']): ?>
      <div style="margin-bottom:10px">
        <a href="<?= htmlspecialchars($receipt['image_url']) ?>" target="_blank" class="btn btn-sm btn-secondary">
          <i class="ti ti-external-link"></i> Google Driveで開く
        </a>
      </div>
      <?php else: ?>
      <div class="img-preview-none">画像なし</div>
      <?php endif; ?>
    </div>

    <!-- 詳細 -->
    <div class="card">
      <p class="card-title">申請内容</p>
      <div class="detail-grid">
        <?php foreach ([
          ['申請者', $receipt['username']],
          ['申請日時', $receipt['created_at']],
          ['利用日', $receipt['date']],
          ['金額（税込）', '¥'.number_format($receipt['amount'])],
          ['店舗名', $receipt['vendor']],
          ['税区分', $receipt['tax_rate']],
          ['カテゴリ', $receipt['category']],
          ['支払方法', $receipt['payment_method']],
        ] as [$label, $val]): ?>
        <div class="detail-item">
          <span class="detail-label"><?= htmlspecialchars($label) ?></span>
          <span class="detail-value"><?= htmlspecialchars($val) ?></span>
        </div>
        <?php endforeach; ?>
        <div class="detail-item detail-full">
          <span class="detail-label">備考</span>
          <span class="detail-value"><?= htmlspecialchars($receipt['note'] ?: '—') ?></span>
        </div>
      </div>

      <?php if ($receipt['last_comment']): ?>
      <div class="comment-box mt-2">
        <p class="text-sm" style="font-weight:500;margin-bottom:4px">担当者コメント</p>
        <p><?= htmlspecialchars($receipt['last_comment']) ?></p>
        <p class="text-sm text-muted mt-1"><?= htmlspecialchars($receipt['status_updated_by']) ?> ・ <?= htmlspecialchars($receipt['status_updated_at']) ?></p>
      </div>
      <?php endif; ?>

      <?php if ($is_reviewer && $next_statuses): ?>
      <div class="status-section">
        <p class="card-title mb-0">ステータス変更</p>
        <div class="form-group mt-2">
          <label>コメント（修正待ち・差し戻し時は必須）</label>
          <textarea id="status-comment" rows="2" placeholder="審査コメントを入力..."></textarea>
        </div>
        <div class="status-btns mt-2">
          <?php foreach ($next_statuses as $s): ?>
          <button class="btn <?= $s==='受理'?'btn-success':($s==='修正待ち'?'btn-danger':'btn-secondary') ?>"
                  onclick="changeStatus('<?= $s ?>')">
            <?= htmlspecialchars($s) ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>
<script>
async function changeStatus(status) {
  const comment = document.getElementById('status-comment')?.value || '';
  const needComment = ['修正待ち','差し戻し'];
  if (needComment.includes(status) && !comment.trim()) {
    showToast('このステータスにはコメントが必須です', 'error'); return;
  }
  try {
    await apiFetch('<?= APP_URL ?>/api/receipts/status.php', {
      method:'POST',
      body: JSON.stringify({ id:'<?= htmlspecialchars($id) ?>', status, comment }),
    });
    showToast(`${status}にしました。申請者にメールを送信しました。`);
    setTimeout(() => location.reload(), 1500);
  } catch(e) { showToast(e.message,'error'); }
}
</script>
<?php layout_foot(); ?>