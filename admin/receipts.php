<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$user = require_role('sysadmin','accounting');
layout_head('全件一覧');
?>
<meta name="csrf-token" content="<?= csrf_token() ?>">
<?php layout_topbar($user); ?>
<main class="main">
  <h1 class="page-title">全件一覧</h1>
  <div class="card">
    <div class="filter-bar">
      <select id="f-status"><option value="">すべてのステータス</option>
        <?php foreach(['審査待ち','修正待ち','再審査待ち','受理','差し戻し'] as $s): ?>
        <option value="<?=$s?>"><?=$s?></option>
        <?php endforeach; ?>
      </select>
      <select id="f-category"><option value="">すべてのカテゴリ</option>
        <?php foreach(['会議費','交通費','消耗品','接待交際費','その他'] as $c): ?>
        <option value="<?=$c?>"><?=$c?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary btn-sm" onclick="loadReceipts()"><i class="ti ti-search"></i> 検索</button>
    </div>
    <div id="loading" style="text-align:center;padding:32px"><div class="spinner" style="width:28px;height:28px;margin:0 auto"></div></div>
    <div class="table-wrap" id="table-wrap" style="display:none">
      <table>
        <thead><tr><th>申請日</th><th>申請者</th><th>利用日</th><th>店舗名</th><th>金額</th><th>カテゴリ</th><th>ステータス</th><th></th></tr></thead>
        <tbody id="tbody"></tbody>
      </table>
      <p id="empty" style="display:none;text-align:center;padding:32px;color:#9ca3af">申請がありません</p>
    </div>
  </div>
</main>
<script>
function loadReceipts() {
  document.getElementById('loading').style.display='block';
  document.getElementById('table-wrap').style.display='none';
  const p = new URLSearchParams();
  const s = document.getElementById('f-status').value;
  const c = document.getElementById('f-category').value;
  if(s) p.set('status',s); if(c) p.set('category',c);
  fetch('<?= APP_URL ?>/api/receipts/list.php?'+p)
    .then(r=>r.json()).then(data=>{
      document.getElementById('loading').style.display='none';
      document.getElementById('table-wrap').style.display='block';
      if(!data.length){document.getElementById('empty').style.display='block';return;}
      document.getElementById('empty').style.display='none';
      document.getElementById('tbody').innerHTML = data.map(r=>`
        <tr>
          <td>${(r.created_at||'').slice(0,10)}</td>
          <td>${r.username||''}</td>
          <td>${r.date||''}</td>
          <td class="td-truncate">${r.vendor||''}</td>
          <td>¥${Number(r.amount).toLocaleString()}</td>
          <td>${r.category||''}</td>
          <td>${statusBadge(r.status)}</td>
          <td><a href="<?= APP_URL ?>/receipt.php?id=${r.id}" class="btn btn-sm btn-secondary">詳細</a></td>
        </tr>`).join('');
    });
}
loadReceipts();
</script>
<?php layout_foot(); ?>
