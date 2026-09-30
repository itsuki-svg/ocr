<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$user = require_role('sysadmin');
layout_head('ユーザー管理');
$role_labels = ['sysadmin'=>'システム管理者','accounting'=>'経理担当者','general'=>'一般社員'];
?>
<meta name="csrf-token" content="<?= csrf_token() ?>">
<?php layout_topbar($user); ?>
<main class="main main-mid">
  <h1 class="page-title">ユーザー管理</h1>
  <div id="pending-card" class="card" style="margin-bottom:12px">
    <div class="section-title"><i class="ti ti-clock" style="color:#d97706"></i> 承認待ち <span class="badge badge-審査待ち" id="pending-count" style="display:none"></span></div>
    <div id="pending-list"><div style="text-align:center;padding:16px"><div class="spinner" style="margin:0 auto"></div></div></div>
  </div>
  <div class="card">
    <div class="section-title"><i class="ti ti-users" style="color:#2563eb"></i> 承認済みユーザー</div>
    <div id="active-list"><div style="text-align:center;padding:16px"><div class="spinner" style="margin:0 auto"></div></div></div>
  </div>
</main>
<script>
const ME_ID = <?= (int)$user['id'] ?>;
const ROLE_LABELS = <?= json_encode($role_labels) ?>;

async function loadUsers() {
  const data = await fetch('<?= APP_URL ?>/api/users/list.php').then(r=>r.json());
  const pending = data.filter(u=>u.status==='pending');
  const active  = data.filter(u=>u.status==='active');

  // 承認待ち
  const pc = document.getElementById('pending-count');
  if (pending.length) { pc.textContent=pending.length+'件'; pc.style.display=''; }
  document.getElementById('pending-list').innerHTML = pending.length
    ? pending.map(u=>`
      <div class="user-row" id="pu-${u.id}">
        <div class="user-avatar ua-pending">${u.username.slice(0,1)}</div>
        <div class="user-info"><p class="user-name">${u.username}</p><p class="user-email">${u.email}</p></div>
        <div class="user-actions">
          <button class="btn btn-success btn-sm" onclick="doApprove(${u.id},'approve','${u.username}')"><i class="ti ti-check"></i> 承認</button>
          <button class="btn btn-danger btn-sm"  onclick="doApprove(${u.id},'reject','${u.username}')"><i class="ti ti-x"></i> 拒否</button>
        </div>
      </div>`).join('')
    : '<p class="text-muted" style="padding:8px 0">承認待ちのユーザーはいません</p>';

  // 承認済み
  document.getElementById('active-list').innerHTML = active.map(u=>`
    <div class="user-row">
      <div class="user-avatar ua-${u.role}">${u.username.slice(0,1)}</div>
      <div class="user-info">
        <div style="display:flex;align-items:center;gap:6px">
          <p class="user-name" id="uname-${u.id}">${u.username}</p>
          ${u.id!=ME_ID?`<button class="btn-icon" style="font-size:14px" onclick="editName(${u.id})"><i class="ti ti-pencil"></i></button>`:''}
        </div>
        <p class="user-email">${u.email}</p>
      </div>
      <div class="user-actions">
        ${u.id==ME_ID
          ? `<span class="role-badge role-${u.role}">${ROLE_LABELS[u.role]}</span><span class="text-sm text-muted">変更不可</span>`
          : `<select class="btn btn-sm btn-secondary" onchange="changeRole(${u.id},this.value)">
               ${Object.entries(ROLE_LABELS).map(([v,l])=>`<option value="${v}" ${u.role===v?'selected':''}>${l}</option>`).join('')}
             </select>`
        }
      </div>
    </div>`).join('');
}

async function doApprove(id, action, name) {
  try {
    await apiFetch('<?= APP_URL ?>/api/users/approve.php',{method:'POST',body:JSON.stringify({user_id:id,action})});
    document.getElementById('pu-'+id)?.remove();
    showToast(action==='approve'?`${name}を承認しました`:`${name}を拒否しました`);
    loadUsers();
  } catch(e){showToast(e.message,'error');}
}

async function changeRole(id, role) {
  try {
    await apiFetch('<?= APP_URL ?>/api/users/approve.php',{method:'POST',body:JSON.stringify({user_id:id,role})});
    showToast('権限を更新しました');
  } catch(e){showToast(e.message,'error');}
}

function editName(id) {
  const el = document.getElementById('uname-'+id);
  const cur = el.textContent;
  el.innerHTML = `<input type="text" value="${cur}" maxlength="30" style="width:120px;padding:3px 6px;border:1px solid #d1d5db;border-radius:6px;font-size:13px" id="name-input-${id}">
    <button class="btn btn-sm btn-primary" onclick="saveName(${id})">保存</button>`;
}

async function saveName(id) {
  const val = document.getElementById('name-input-'+id).value.trim();
  if (!val) return;
  try {
    await apiFetch('<?= APP_URL ?>/api/users/approve.php',{method:'POST',body:JSON.stringify({user_id:id,username:val})});
    showToast('ユーザーネームを更新しました');
    loadUsers();
  } catch(e){showToast(e.message,'error');}
}

loadUsers();
</script>
<?php layout_foot(); ?>
