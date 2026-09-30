// app.js — フロントエンド共通JS

// ── トースト通知 ──
function showToast(msg, type = 'success') {
  let wrap = document.getElementById('toast-wrap');
  if (!wrap) {
    wrap = document.createElement('div');
    wrap.id = 'toast-wrap';
    wrap.className = 'toast-wrap';
    document.body.appendChild(wrap);
  }
  const t = document.createElement('div');
  const icon = type === 'success' ? 'ti-check' : 'ti-alert-circle';
  t.className = `toast toast-${type}`;
  t.innerHTML = `<i class="ti ${icon}"></i><span>${msg}</span>`;
  wrap.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

// ── ハンバーガーメニュー ──
const hamburger = document.getElementById('hamburger');
const nav = document.getElementById('topbar-nav');
if (hamburger && nav) {
  hamburger.addEventListener('click', () => nav.classList.toggle('open'));
  document.addEventListener('click', e => {
    if (!hamburger.contains(e.target) && !nav.contains(e.target)) {
      nav.classList.remove('open');
    }
  });
}

// ── API fetch ヘルパー ──
async function apiFetch(url, options = {}) {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const res = await fetch(url, {
    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options,
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || '処理に失敗しました');
  return data;
}

async function apiFetchForm(url, formData) {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': csrf },
    body: formData,
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || '処理に失敗しました');
  return data;
}

// ── ステータスバッジ HTML ──
function statusBadge(status) {
  return `<span class="badge badge-${status}">${status}</span>`;
}

// ── 金額フォーマット ──
function formatAmount(n) {
  return '¥' + Number(n).toLocaleString('ja-JP');
}

// ── 日付フォーマット ──
function formatDate(s) { return s ? s.slice(0, 10) : ''; }
function formatDateTime(s) { return s ? s.slice(0, 16).replace('T', ' ') : ''; }

// ── ドラッグ&ドロップ ──
function initDropzone(zone, onFile) {
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
  zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    const f = e.dataTransfer.files[0];
    if (f) onFile(f);
  });
  zone.addEventListener('click', () => {
    const inp = document.createElement('input');
    inp.type = 'file'; inp.accept = 'image/*'; inp.capture = 'environment';
    inp.onchange = () => { if (inp.files[0]) onFile(inp.files[0]); };
    inp.click();
  });
}

// ── OCR ──
async function runOCR(file, fields) {
  const fd = new FormData();
  fd.append('file', file);
  fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
  const data = await apiFetchForm('/ocr/api/ocr.php', fd);
  if (data.date)           { const el = document.getElementById(fields.date);           if (el) el.value = data.date; }
  if (data.vendor)         { const el = document.getElementById(fields.vendor);         if (el) el.value = data.vendor; }
  if (data.amount)         { const el = document.getElementById(fields.amount);         if (el) el.value = data.amount; }
  if (data.tax_rate)       { const el = document.getElementById(fields.tax_rate);       if (el) el.value = data.tax_rate; }
  if (data.category)       { const el = document.getElementById(fields.category);       if (el) el.value = data.category; }
  if (data.payment_method) { const el = document.getElementById(fields.payment_method); if (el) el.value = data.payment_method; }
}
