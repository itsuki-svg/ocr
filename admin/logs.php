<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$user = require_role('sysadmin');
layout_head('操作ログ');

$from     = $_GET['from']     ?? '';
$to       = $_GET['to']       ?? '';
$operator = $_GET['operator'] ?? '';

$sql    = 'SELECT * FROM logs WHERE 1=1';
$params = [];
if ($from)     { $sql .= ' AND created_at >= ?'; $params[] = $from; }
if ($to)       { $sql .= ' AND created_at <= ?'; $params[] = $to . ' 23:59:59'; }
if ($operator) { $sql .= ' AND operator_name = ?'; $params[] = $operator; }
$sql .= ' ORDER BY id DESC LIMIT 500';
$logs = db_query($sql, $params);
?>
<?php layout_topbar($user); ?>
<main class="main">
  <h1 class="page-title">操作ログ</h1>
  <div class="card">
    <form method="GET" class="filter-bar">
      <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
      <input type="date" name="to"   value="<?= htmlspecialchars($to) ?>">
      <input type="text" name="operator" placeholder="操作者名" value="<?= htmlspecialchars($operator) ?>" style="width:140px">
      <button type="submit" class="btn btn-primary btn-sm"><i class="ti ti-search"></i> 検索</button>
    </form>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>操作日時</th><th>操作者</th><th>操作内容</th><th>変更前</th><th>変更後</th><th>コメント</th></tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
          <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:24px">ログがありません</td></tr>
          <?php else: ?>
          <?php foreach($logs as $log): ?>
          <tr>
            <td style="white-space:nowrap;font-size:12px"><?= htmlspecialchars($log['created_at']) ?></td>
            <td><?= htmlspecialchars($log['operator_name']) ?></td>
            <td>
              <?= htmlspecialchars($log['action']) ?>
              <?php if($log['target_receipt_id']): ?>
              <span class="text-muted text-sm">(<?= htmlspecialchars(substr($log['target_receipt_id'],0,8)) ?>...)</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if($log['before_status']): ?>
              <span class="badge badge-<?= htmlspecialchars($log['before_status']) ?>"><?= htmlspecialchars($log['before_status']) ?></span>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <td>
              <?php if($log['after_status']): ?>
              <span class="badge badge-<?= htmlspecialchars($log['after_status']) ?>"><?= htmlspecialchars($log['after_status']) ?></span>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <td class="td-truncate" style="max-width:200px"><?= htmlspecialchars($log['comment'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php layout_foot(); ?>
