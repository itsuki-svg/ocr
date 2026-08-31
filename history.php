<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_auth();
layout_head('申請履歴');
?>
<meta name="csrf-token" content="<?= csrf_token() ?>">
<?php layout_topbar($user); ?>
<main class="main main-mid">
  <h1 class="page-title">申請履歴（直近30日）</h1>
  <div class="card">
    <div id="loading" style="text-align:center;padding:32px"><div class="spinner" style="width:28px;height:28px;margin:0 auto"></div></div>
    <div class="table-wrap" id="table-wrap" style="display:none">
      <table>
        <thead><tr><th>利用日</th><th>店舗名</th><th>金額</th><th>カテゴリ</th><th>ステータス</th><th></th></tr></thead>
        <tbody id="tbody"></tbody>
      </table>
      <p id="empty" style="display:none;text-align:center;padding:32px;color:#9ca3af">申請履歴がありません</p>
    </div>
  </div>
</main>
<script>
fetch('<?= APP_URL ?>/api/receipts/mine.php')
  .then(r=>r.json()).then(data => {
    document.getElementById('loading').style.display='none';
    document.getElementById('table-wrap').style.display='block';
    if (!data.length) { document.getElementById('empty').style.display='block'; return; }
    document.getElementById('tbody').innerHTML = data.map(r => `
      <tr>
        <td>${r.date||''}</td>
        <td class="td-truncate">${r.vendor||''}</td>
        <td>¥${Number(r.amount).toLocaleString()}</td>
        <td>${r.category||''}</td>
        <td>${statusBadge(r.status)}</td>
        <td>
          <a href="<?= APP_URL ?>/receipt.php?id=${r.id}" class="btn btn-sm btn-secondary">
            ${r.status==='修正待ち'?'修正する':'詳細'}
          </a>
        </td>
      </tr>`).join('');
  });
</script>
<?php layout_foot(); ?>
