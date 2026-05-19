/* API from app-config.js */
const REG_HEADERS = { 'X-Session-Realm': 'registrar' };
let allRequests = [];
let allStudents = [];
let allStaff    = [];

function escHtml(str) {
  if (str == null) return '';
  return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function capitalize(str) {
  if (str == null) return '';
  const s = String(str);
  return s.charAt(0).toUpperCase() + s.slice(1).toLowerCase();
}

/* ════════════════════════════════
   AUTH CHECK
════════════════════════════════ */
let myPermissions = {};

async function showDeactivatedModal() {
  const redirectSec = 3;
  if (typeof Swal !== 'undefined') {
    await Swal.fire({
      icon: 'warning',
      title: 'Account Deactivated',
      html: `Your account has been deactivated. Redirecting to login in ${redirectSec} seconds...`,
      confirmButtonColor: '#DD0426',
      allowOutsideClick: false,
      allowEscapeKey: false,
      timer: redirectSec * 1000,
      timerProgressBar: true,
      showConfirmButton: false,
    });
  }
  window.location.href = 'admin-login.php';
}

async function checkAuth() {
  const opts = { credentials: 'include', cache: 'no-store' };
  let data = {};
  for (let attempt = 1; attempt <= 5; attempt++) {
    try {
      const res = await fetch(API + '/admin-check-auth.php?t=' + Date.now(), { ...opts, headers: REG_HEADERS });
      const text = await res.text();
      data = text ? JSON.parse(text) : {};
      if (data.account_deactivated) { await showDeactivatedModal(); return null; }
      if (data.success && data.role === 'registrar') { myPermissions = data.permissions || {}; applyPermissionVisibility(); return data.name; }
    } catch (e) { data = {}; }
    if (attempt === 5) { window.location.href = 'admin-login.php'; return null; }
    await new Promise(r => setTimeout(r, 600));
  }
  myPermissions = data.permissions || {};
  applyPermissionVisibility();
  return data.name;
}

function applyPermissionVisibility() {
  const navMap = {
    requests: 'manage_requests',
    staff:    'manage_staff',
    students: 'view_students',
    reports:  'export_reports',
  };
  Object.entries(navMap).forEach(([page, perm]) => {
    const link = document.querySelector(`.sidebar__link[data-page="${page}"]`);
    const sect = document.getElementById('page-' + page);
    const allowed = !!myPermissions[perm];
    if (link) link.style.display = allowed ? '' : 'none';
    if (sect) sect.style.display = allowed ? '' : 'none';
  });
  if (!myPermissions['manage_requests']) {
    document.querySelectorAll('.admin__stat-card[data-page="requests"]').forEach(c => c.style.pointerEvents = 'none');
  }
  // Department assignment: only show when assign_departments permission is granted
  const deptField = document.getElementById('tellerDepartmentsField');
  if (deptField) deptField.style.display = myPermissions.assign_departments ? '' : 'none';
}

async function refreshPermissions() {
  try {
    const res = await fetch(API + '/admin-check-auth.php?t=' + Date.now(), { credentials: 'include', headers: REG_HEADERS });
    const data = res.ok ? await res.json() : {};
    if (data.account_deactivated) { await showDeactivatedModal(); return false; }
    if (data.success && data.permissions) {
      myPermissions = data.permissions;
      applyPermissionVisibility();
      return true;
    }
  } catch (_) {}
  return false;
}

/* ════════════════════════════════
   SIDEBAR NAVIGATION
════════════════════════════════ */
const sidebar        = document.getElementById('sidebar');
const adminContent   = document.getElementById('adminContent');
const sidebarToggle  = document.getElementById('sidebarToggle');
const mobileMenuBtn  = document.getElementById('mobileMenuBtn');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const pageTitleEl    = document.getElementById('pageTitle');

const pageNames = { home: 'Home', requests: 'Requests', staff: 'Staff', students: 'Students', reports: 'Reports', support: 'Document Support' };

function navigateTo(pageId, filterStatus) {
  // Update nav links
  document.querySelectorAll('.sidebar__link').forEach(l => {
    l.classList.toggle('active', l.dataset.page === pageId);
  });

  // Show page
  document.querySelectorAll('.admin-page').forEach(p => p.classList.remove('active'));
  const target = document.getElementById('page-' + pageId);
  if (target) { target.classList.add('active'); target.style.animation = 'none'; target.offsetHeight; target.style.animation = ''; }

  // Update topbar title
  if (pageTitleEl) pageTitleEl.textContent = pageNames[pageId] || '';

  // If navigating to requests with a filter, apply it
  if (pageId === 'requests' && filterStatus !== undefined) {
    document.getElementById('filterStatus').value = filterStatus;
    renderTable();
  }

  // Realtime: poll requests when on home or requests page
  if (pageId === 'home' || pageId === 'requests') startRequestsPoll();
  else stopRequestsPoll();

  closeMobileSidebar();
}

// Sidebar nav links
document.querySelectorAll('.sidebar__link[data-page]').forEach(link => {
  link.addEventListener('click', (e) => {
    e.preventDefault();
    navigateTo(link.dataset.page);
  });
});

// "View All" link on home card
document.querySelectorAll('[data-page]').forEach(el => {
  if (el.tagName === 'A' && !el.classList.contains('sidebar__link')) {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      navigateTo(el.dataset.page);
    });
  }
});

// Stat cards → go to requests with filter
document.querySelectorAll('.admin__stat-card[data-page="requests"]').forEach(card => {
  card.addEventListener('click', () => {
    navigateTo('requests', card.dataset.filter || '');
  });
});

// Sidebar: hover to preview, click toggle button to pin/unpin expanded
let sidebarPinned = false;
sidebar.classList.add('collapsed');

sidebarToggle.addEventListener('click', () => {
  sidebarPinned = !sidebarPinned;
  sidebar.classList.toggle('collapsed', !sidebarPinned);
  sidebarToggle.innerHTML = sidebarPinned
    ? '<i class="ri-menu-fold-line"></i>'
    : '<i class="ri-menu-unfold-line"></i>';
});

sidebar.addEventListener('mouseenter', () => { if (!sidebarPinned) sidebar.classList.remove('collapsed'); });
sidebar.addEventListener('mouseleave', () => { if (!sidebarPinned) sidebar.classList.add('collapsed'); });

// Mobile menu
mobileMenuBtn.addEventListener('click', openMobileSidebar);
sidebarOverlay.addEventListener('click', closeMobileSidebar);
const supportNavBtn = document.getElementById('supportNavBtn');
if (supportNavBtn) supportNavBtn.addEventListener('click', openMobileSidebar);

function openMobileSidebar() {
  sidebar.classList.add('mobile-open');
  sidebarOverlay.classList.add('active');
}

function closeMobileSidebar() {
  sidebar.classList.remove('mobile-open');
  sidebarOverlay.classList.remove('active');
}

/* ════════════════════════════════
   LOAD REQUESTS
════════════════════════════════ */
async function loadRequests() {
  const res  = await fetch(API + '/admin-get-requests.php', { credentials: 'include' });
  const data = await res.json();
  if (await handleAuthResponse(res, data)) return;
  if (!data.success) return;
  allRequests = data.requests;
  updateStats();
  renderTable();
  renderRecentTable();
  updateReportsSummary();
  if (myPermissions['manage_requests']) loadDailyWorkload();
}

async function loadDailyWorkload() {
  const card = document.getElementById('dailyWorkloadCard');
  const body = document.getElementById('dailyWorkloadBody');
  const badge = document.getElementById('dailyWorkloadBadge');
  const dateInput = document.getElementById('dailySummaryDate');
  if (dateInput && !dateInput.value) dateInput.value = new Date().toISOString().slice(0, 10);
  if (dateInput && !dateInput._bound) {
    dateInput._bound = true;
    dateInput.addEventListener('change', loadDailySummaryForDate);
  }
  if (dateInput?.value) loadDailySummaryForDate();
  const monthlyInput = document.getElementById('monthlyTotalMonth');
  if (monthlyInput && !monthlyInput.value) monthlyInput.value = new Date().toISOString().slice(0, 7);
  if (!card || !body || !badge) return;
  card.style.display = '';
  try {
    const res = await fetch(API + '/admin-get-daily-requests.php?days=14', { credentials: 'include' });
    const data = await res.json();
    if (!data.success) { body.innerHTML = '<tr><td colspan="3" class="admin__no-results">Failed to load.</td></tr>'; return; }
    const limit = data.limit || 50;
    badge.textContent = `${data.today_count || 0} / ${limit} today`;
    badge.classList.toggle('at-limit', (data.today_count || 0) >= limit);
    if (!data.daily || !data.daily.length) {
      body.innerHTML = '<tr><td colspan="3" class="admin__no-results">No requests in the last 14 days.</td></tr>';
      return;
    }
    body.innerHTML = data.daily.map(r => {
      const date = new Date(r.request_date).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
      const isToday = r.request_date === new Date().toISOString().slice(0, 10);
      const status = r.at_limit ? '<span class="admin__status-badge claimed" title="Limit reached">At limit</span>' : '<span class="admin__status-badge processing">Accepting</span>';
      return `<tr${isToday ? ' class="admin__row-today"' : ''}>
        <td>${escHtml(date)}${isToday ? ' <span style="font-size:.7rem;opacity:.8">(Today)</span>' : ''}</td>
        <td>${r.total}</td>
        <td>${status}</td>
      </tr>`;
    }).join('');
  } catch (e) {
    body.innerHTML = '<tr><td colspan="3" class="admin__no-results">Could not load daily workload.</td></tr>';
  }
}

async function loadDailySummaryForDate() {
  const dateInput = document.getElementById('dailySummaryDate');
  const resultEl = document.getElementById('dailySummaryResult');
  if (!dateInput || !resultEl) return;
  const d = dateInput.value;
  if (!d) { resultEl.innerHTML = ''; return; }
  try {
    const res = await fetch(API + '/admin-get-report-summary.php?date=' + d, { credentials: 'include' });
    const data = await res.json();
    if (!data.success) { resultEl.innerHTML = '<div class="admin__no-results">Failed to load.</div>'; return; }
    const fmt = new Date(d).toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    let html = `<div><strong>${fmt}</strong> — Total: <strong>${data.total}</strong>${data.limit ? ` / ${data.limit}` : ''}</div>`;
    if (data.breakdown && data.breakdown.length) {
      html += '<table class="admin__table" style="margin-top:.5rem;font-size:.9rem"><thead><tr><th>Document Type</th><th>Count</th></tr></thead><tbody>';
      data.breakdown.forEach(b => { html += `<tr><td>${escHtml(b.document_name)}</td><td>${b.count}</td></tr>`; });
      html += '</tbody></table>';
    } else {
      html += '<p style="font-size:.85rem;color:#888;margin-top:.25rem">No requests that day.</p>';
    }
    resultEl.innerHTML = html;
  } catch (e) {
    resultEl.innerHTML = '<div class="admin__no-results">Could not load.</div>';
  }
}

function updateStats() {
  const counts = { pending: 0, processing: 0, ready: 0, claimed: 0 };
  allRequests.forEach(r => { if (counts[r.status] !== undefined) counts[r.status]++; });

  document.getElementById('statAll').textContent        = allRequests.length;
  document.getElementById('statPending').textContent    = counts.pending;
  document.getElementById('statProcessing').textContent = counts.processing;
  document.getElementById('statReady').textContent      = counts.ready;
  document.getElementById('statClaimed').textContent    = counts.claimed;

  // Pending badge on sidebar
  const badge = document.getElementById('pendingBadge');
  badge.textContent = counts.pending;
  badge.classList.toggle('hidden', counts.pending === 0);
}

function updateReportsSummary() {
  const counts = { pending: 0, processing: 0, ready: 0, claimed: 0 };
  allRequests.forEach(r => { if (counts[r.status] !== undefined) counts[r.status]++; });
  document.getElementById('rAll').textContent        = allRequests.length;
  document.getElementById('rPending').textContent    = counts.pending;
  document.getElementById('rProcessing').textContent = counts.processing;
  document.getElementById('rReady').textContent      = counts.ready;
  document.getElementById('rClaimed').textContent    = counts.claimed;
}

function renderRecentTable() {
  const tbody  = document.getElementById('recentBody');
  const recent = allRequests.slice(0, 8);
  if (!recent.length) { tbody.innerHTML = '<tr><td colspan="4" class="admin__no-results">No requests yet.</td></tr>'; return; }
  tbody.innerHTML = recent.map(r => {
    const date = new Date(r.requested_at).toLocaleDateString();
    return `<tr class="req-row-clickable" onclick="viewRequestDetail(${r.id})" title="Click to view details">
      <td><div class="student-name">${escHtml(r.names)} ${escHtml(r.surnames)}</div><div class="student-info">${escHtml(r.student_number)}</div></td>
      <td>${escHtml(r.document_name)}</td>
      <td>${date}</td>
      <td><span class="admin__status-badge ${r.status}">${r.status === 'claimed' ? '<i class="ri-lock-line"></i> Claimed' : capitalize(r.status)}</span></td>
    </tr>`;
  }).join('');
}

function getFilteredRows() {
  const search = document.getElementById('searchInput').value.toLowerCase();
  const status = document.getElementById('filterStatus').value;
  return allRequests.filter(r => {
    const matchStatus = !status || r.status === status;
    const matchSearch = !search || [r.names, r.surnames, r.student_number, r.document_name, r.department, r.purpose]
      .some(v => (v || '').toLowerCase().includes(search));
    return matchStatus && matchSearch;
  });
}

function renderTable() {
  const tbody = document.getElementById('requestsBody');
  const rows  = getFilteredRows();
  if (!rows.length) { tbody.innerHTML = `<tr><td colspan="8" class="admin__no-results">No requests found.</td></tr>`; return; }
  tbody.innerHTML = rows.map((r, i) => {
    const date = new Date(r.requested_at).toLocaleDateString();
    return `<tr>
      <td>${i + 1}</td>
      <td><div class="student-name">${escHtml(r.names)} ${escHtml(r.surnames)}</div><div class="student-info">${escHtml(r.student_number)}</div><div class="student-info">${escHtml(r.email || '')}</div></td>
      <td>${escHtml(r.document_name)}</td>
      <td>${escHtml(r.department || '—')}</td>
      <td><span class="req-purpose-text" title="${escHtml(r.purpose || '')}">${escHtml(r.purpose || '—')}</span></td>
      <td>${date}</td>
      <td>${r.status === 'claimed'
        ? `<span class="admin__status-badge claimed" title="Locked — already claimed."><i class="ri-lock-line"></i> Claimed</span>`
        : `<select class="admin__status-select ${r.status}" data-id="${r.id}" onchange="updateStatus(this)">
            <option value="pending"    ${r.status==='pending'   ?'selected':''}>Pending</option>
            <option value="processing" ${r.status==='processing'?'selected':''}>Processing</option>
            <option value="ready"      ${r.status==='ready'     ?'selected':''}>Ready</option>
            <option value="claimed"    >Claimed</option>
          </select>`
      }</td>
      <td>
        <button class="req-detail__view-btn" onclick="viewRequestDetail(${r.id})" title="View Details"><i class="ri-eye-line"></i></button>
        ${r.notes ? `<button class="req-detail__view-btn notes" onclick="viewRequestDetail(${r.id}, true)" title="View notes"><i class="ri-sticky-note-line"></i></button>` : ''}
      </td>
    </tr>`;
  }).join('');
}

document.getElementById('searchInput').addEventListener('input', renderTable);
document.getElementById('filterStatus').addEventListener('change', renderTable);
document.getElementById('refreshBtn').addEventListener('click', loadRequests);

/* ════════════════════════════════
   UPDATE STATUS
════════════════════════════════ */
async function updateStatus(select) {
  const id        = parseInt(select.dataset.id);
  const newStatus = select.value;
  const prevClass = select.className;

  // Prevent setting back from a non-claimed status to something weird — but
  // the main lock is: if the original row was claimed, the dropdown isn't shown.
  // Extra guard: revert immediately if somehow called on a claimed row.
  const r = allRequests.find(r => r.id == id);
  if (r && r.status === 'claimed') {
    select.value = 'claimed';
    Swal.fire({ icon: 'warning', title: 'Locked', text: 'This request has already been claimed and cannot be changed.', confirmButtonColor: '#DD0426' });
    return;
  }

  select.className = 'admin__status-select ' + newStatus;
  try {
    const res  = await fetch(API + '/admin-update-status.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
      body: JSON.stringify({ id, status: newStatus })
    });
    const data = await res.json();
    if (data.success) {
      if (r) r.status = newStatus;
      updateStats();
      renderRecentTable();
      updateReportsSummary();
      // Re-render table so claimed rows swap to locked badge
      renderTable();
      const extra = data.email_sent
        ? (newStatus === 'claimed' ? '<br><small>📧 Claim confirmation sent.</small>' : '<br><small>📧 Notification sent to student.</small>')
        : '';
      Swal.fire({ icon: 'success', title: 'Updated', html: `Status set to <strong>${newStatus}</strong>${extra}`, timer: 2000, showConfirmButton: false });
    } else {
      select.className = prevClass;
      if (r) select.value = r.status;
      Swal.fire({ icon: 'error', title: 'Error', text: data.message });
    }
  } catch (err) {
    select.className = prevClass;
    if (r) select.value = r.status;
    Swal.fire({ icon: 'error', title: 'Connection error', text: 'Please try again.' });
  }
}

/* ════════════════════════════════
   REQUEST DETAIL MODAL
════════════════════════════════ */
const STATUS_ORDER  = ['pending', 'processing', 'ready', 'claimed'];
const STATUS_ICONS  = { pending: 'ri-time-line', processing: 'ri-loader-3-line', ready: 'ri-checkbox-circle-line', claimed: 'ri-hand-coin-line' };
const STATUS_LABELS = { pending: 'Pending', processing: 'Processing', ready: 'Ready to Claim', claimed: 'Claimed' };

function buildTimeline(currentStatus) {
  return STATUS_ORDER.map(s => {
    const idx    = STATUS_ORDER.indexOf(s);
    const curIdx = STATUS_ORDER.indexOf(currentStatus);
    const done   = idx < curIdx;
    const active = s === currentStatus;
    const cls    = done ? 'done' : active ? 'active' : 'upcoming';
    return `<div class="req-detail__tl-step ${cls}">
      <div class="req-detail__tl-dot"><i class="${STATUS_ICONS[s]}"></i></div>
      <span>${STATUS_LABELS[s]}</span>
    </div>`;
  }).join('<div class="req-detail__tl-line"></div>');
}

let _rdCurrentId = null;

function viewRequestDetail(id, scrollToNotes = false) {
  const r = allRequests.find(req => req.id == id);
  if (!r) return;
  _rdCurrentId = r.id;

  document.getElementById('rdDocName').textContent = r.document_name;
  document.getElementById('rdRefNum').textContent  = `Request #${r.id}`;

  const badge = document.getElementById('rdHeaderStatus');
  badge.className = `admin__status-badge ${r.status}`;
  badge.innerHTML = r.status === 'claimed' ? '<i class="ri-lock-line"></i> Claimed' : capitalize(r.status);

  const fullName = `${r.names} ${r.surnames}`;
  const initials = fullName.trim().split(/\s+/).map(w => (w[0] || '').toUpperCase()).slice(0, 2).join('');
  document.getElementById('rdAvatar').textContent       = initials;
  document.getElementById('rdStudentName').textContent  = fullName;
  document.getElementById('rdStudentNum').textContent   = r.student_number;
  document.getElementById('rdStudentEmail').textContent = r.email || '';

  document.getElementById('rdDocument').textContent = r.document_name;
  document.getElementById('rdDept').textContent     = r.department || '—';
  document.getElementById('rdPurpose').textContent  = r.purpose    || '—';
  document.getElementById('rdDate').textContent     = new Date(r.requested_at).toLocaleString();

  const processedSection = document.getElementById('rdProcessedSection');
  const processedByEl = document.getElementById('rdProcessedBy');
  if (r.processed_by_name || r.processed_by_admin_id) {
    const name = r.processed_by_name || ('Staff #' + r.processed_by_admin_id);
    const date = r.processed_at ? ' (' + new Date(r.processed_at).toLocaleString() + ')' : '';
    processedByEl.textContent = name + date;
    if (processedSection) processedSection.style.display = '';
  } else {
    if (processedSection) processedSection.style.display = 'none';
  }

  const notesSection = document.getElementById('rdNotesSection');
  if (r.notes) {
    document.getElementById('rdNotes').textContent = r.notes;
    notesSection.style.display = '';
  } else {
    notesSection.style.display = 'none';
  }

  document.getElementById('rdTimeline').innerHTML = buildTimeline(r.status);

  const updateSection = document.getElementById('rdUpdateSection');
  if (r.status === 'claimed') {
    updateSection.style.display = 'none';
  } else {
    updateSection.style.display = '';
    document.getElementById('rdStatusSelect').value = r.status;
  }

  document.getElementById('reqDetailOverlay').classList.add('open');

  if (scrollToNotes && r.notes) {
    setTimeout(() => {
      const ns = document.getElementById('rdNotesSection');
      if (ns) {
        ns.scrollIntoView({ behavior: 'smooth', block: 'center' });
        ns.classList.add('req-detail__notes--pulse');
        setTimeout(() => ns.classList.remove('req-detail__notes--pulse'), 1400);
      }
    }, 280);
  }
}

function closeReqDetailModal() {
  document.getElementById('reqDetailOverlay').classList.remove('open');
  _rdCurrentId = null;
}

document.getElementById('rdClose').addEventListener('click', closeReqDetailModal);
document.getElementById('reqDetailOverlay').addEventListener('click', e => {
  if (e.target === document.getElementById('reqDetailOverlay')) closeReqDetailModal();
});

document.getElementById('rdUpdateBtn').addEventListener('click', async () => {
  const id        = _rdCurrentId;
  const newStatus = document.getElementById('rdStatusSelect').value;
  if (!id) return;

  const r = allRequests.find(req => req.id == id);
  if (r && r.status === 'claimed') {
    Swal.fire({ icon: 'warning', title: 'Locked', text: 'This request has already been claimed and cannot be changed.', confirmButtonColor: '#DD0426' });
    return;
  }

  try {
    const res  = await fetch(API + '/admin-update-status.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
      body: JSON.stringify({ id, status: newStatus })
    });
    const data = await res.json();
    if (data.success) {
      if (r) r.status = newStatus;
      updateStats();
      renderRecentTable();
      renderTable();
      updateReportsSummary();

      const badge = document.getElementById('rdHeaderStatus');
      badge.className = `admin__status-badge ${newStatus}`;
      badge.innerHTML = newStatus === 'claimed' ? '<i class="ri-lock-line"></i> Claimed' : capitalize(newStatus);
      document.getElementById('rdTimeline').innerHTML = buildTimeline(newStatus);
      if (newStatus === 'claimed') document.getElementById('rdUpdateSection').style.display = 'none';

      const extra = data.email_sent ? '<br><small>📧 Notification sent to student.</small>' : '';
      Swal.fire({ icon: 'success', title: 'Updated', html: `Status set to <strong>${newStatus}</strong>${extra}`, timer: 2000, showConfirmButton: false });
    } else {
      Swal.fire({ icon: 'error', title: 'Error', text: data.message });
    }
  } catch {
    Swal.fire({ icon: 'error', title: 'Connection error', text: 'Please try again.' });
  }
});

/* ════════════════════════════════
   LOGOUT
════════════════════════════════ */
document.getElementById('logoutBtn').addEventListener('click', async () => {
  const result = await Swal.fire({
    icon: 'question',
    title: 'Logout?',
    text: 'Are you sure you want to logout?',
    showCancelButton: true,
    confirmButtonColor: '#DD0426',
    confirmButtonText: 'Yes, Logout',
    cancelButtonText: 'No'
  });
  if (!result.isConfirmed) return;
  await fetch(API + '/admin-logout.php', { credentials: 'include' });
  window.location.href = 'admin-login.php';
});

/* ════════════════════════════════
   EXPORT
════════════════════════════════ */
function buildExportUrl(format) {
  const dateFrom = document.getElementById('exportDateFrom').value;
  const dateTo   = document.getElementById('exportDateTo').value;
  const status   = document.getElementById('exportStatus').value;
  if (dateFrom && dateTo && dateFrom > dateTo) {
    Swal.fire({ icon: 'warning', title: 'Invalid Date Range', text: '"Date From" cannot be after "Date To".', confirmButtonColor: '#DD0426' });
    return null;
  }
  const params = new URLSearchParams({ format });
  if (status)   params.append('status',    status);
  if (dateFrom) params.append('date_from', dateFrom);
  if (dateTo)   params.append('date_to',   dateTo);
  return `${API}/admin-export.php?${params.toString()}`;
}

document.getElementById('exportCsvBtn').addEventListener('click', () => {
  const url = buildExportUrl('csv');
  if (url) window.location.href = url;
});

document.getElementById('exportExcelBtn').addEventListener('click', () => {
  const url = buildExportUrl('excel');
  if (url) window.location.href = url;
});

document.getElementById('loadMonthlyBtn')?.addEventListener('click', async () => {
  const input = document.getElementById('monthlyTotalMonth');
  const numEl = document.getElementById('monthlyTotalNum');
  const labelEl = document.getElementById('monthlyTotalLabel');
  if (!input || !numEl || !labelEl) return;
  const m = input.value;
  if (!m) { numEl.textContent = '—'; labelEl.textContent = 'Select month and click Load'; return; }
  try {
    const res = await fetch(API + '/admin-get-report-summary.php?month=' + m, { credentials: 'include' });
    const data = await res.json();
    if (!data.success) { numEl.textContent = '—'; labelEl.textContent = 'Failed to load'; return; }
    const fmt = new Date(m + '-01').toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    numEl.textContent = data.total;
    labelEl.textContent = 'Total requests for ' + fmt;
  } catch (e) {
    numEl.textContent = '—';
    labelEl.textContent = 'Failed to load';
  }
});

document.getElementById('clearDates').addEventListener('click', () => {
  document.getElementById('exportDateFrom').value = '';
  document.getElementById('exportDateTo').value   = '';
});

/* ════════════════════════════════
   INIT
════════════════════════════════ */
function setInitials(fullName) {
  const initials = fullName.trim().split(/\s+/).map(w => w[0].toUpperCase()).slice(0, 2).join('');
  ['sidebarAvatar', 'topbarAvatar'].forEach(id => {
    const el = document.getElementById(id);
    if (el) { el.textContent = initials; }
  });
}

/* ════════════════════════════════
   STUDENTS PAGE
════════════════════════════════ */
async function loadStudents() {
  const res  = await fetch(API + '/admin-get-students.php', { credentials: 'include' });
  const data = await res.json();
  if (await handleAuthResponse(res, data)) return;
  if (!data.success) return;
  allStudents = data.students;
  populateProgramFilter();
  renderStudentsTable();
}

function populateProgramFilter() {
  const sel = document.getElementById('studentProgramFilter');
  const programs = [...new Set(allStudents.map(s => s.program).filter(Boolean))].sort();
  sel.innerHTML = '<option value="">All Programs</option>' +
    programs.map(p => `<option value="${p}">${p}</option>`).join('');
}

function getFilteredStudents() {
  const search  = document.getElementById('studentSearch').value.toLowerCase();
  const program = document.getElementById('studentProgramFilter').value;
  return allStudents.filter(s => {
    const matchProgram = !program || s.program === program;
    const matchSearch  = !search  || [s.names, s.surnames, s.student_number, s.email]
      .some(v => (v || '').toLowerCase().includes(search));
    return matchProgram && matchSearch;
  });
}

function renderStudentsTable() {
  const tbody = document.getElementById('studentsBody');
  const rows  = getFilteredStudents();
  if (!rows.length) { tbody.innerHTML = `<tr><td colspan="10" class="admin__no-results">No students found.</td></tr>`; return; }
  tbody.innerHTML = rows.map((s, i) => {
    const date  = new Date(s.created_at).toLocaleDateString();
    const total = parseInt(s.total_requests) || 0;
    return `<tr>
      <td>${i + 1}</td>
      <td>
        <div class="student-name">${s.names} ${s.surnames}</div>
        <div class="student-info">${s.student_number}</div>
      </td>
      <td><div class="student-info">${s.email}</div></td>
      <td><strong>${total}</strong></td>
      <td>${s.pending   || 0}</td>
      <td>${s.processing|| 0}</td>
      <td>${s.ready     || 0}</td>
      <td>${s.claimed   || 0}</td>
      <td>${date}</td>
      <td>
        ${total > 0
          ? `<button class="dev__action-btn edit" onclick="viewStudentHistory(${s.id},'${escHtml(s.names + ' ' + s.surnames)}')" title="View History"><i class="ri-eye-line"></i></button>`
          : `<span style="color:#ccc;font-size:.75rem">No requests</span>`}
      </td>
    </tr>`;
  }).join('');
}

let _historyStudentId = null;

function viewStudentHistory(studentId, studentName) {
  _historyStudentId = studentId;
  document.getElementById('historyModalTitle').textContent = studentName + ' — Request History';
  document.getElementById('historyModalOverlay').classList.add('open');
  const tbody = document.getElementById('historyBody');
  tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:1.5rem;">Loading...</td></tr>';

  const student = allStudents.find(s => s.id == studentId);
  const transferCb = document.getElementById('historyFlagTransfer');
  const gradCb = document.getElementById('historyFlagGraduated');
  const saveBtn = document.getElementById('historyFlagsSave');
  if (transferCb) { transferCb.checked = !!student?.transfer_authorized; transferCb.dataset.original = transferCb.checked ? '1' : '0'; }
  if (gradCb) { gradCb.checked = !!student?.graduated; gradCb.dataset.original = gradCb.checked ? '1' : '0'; }
  if (saveBtn) saveBtn.style.display = 'none';

  const requests = allRequests.filter(r => {
    const s = allStudents.find(st => st.id == studentId);
    return s && r.student_number === s.student_number;
  });

  if (!requests.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="admin__no-results">No requests found.</td></tr>';
    return;
  }
  tbody.innerHTML = requests.map(r => {
    const date = new Date(r.requested_at).toLocaleDateString();
    return `<tr>
      <td>${r.document_name}</td>
      <td>${r.department || '-'}</td>
      <td>${r.purpose    || '-'}</td>
      <td>${date}</td>
      <td><span class="admin__status-badge ${r.status}">${r.status.charAt(0).toUpperCase() + r.status.slice(1)}</span></td>
    </tr>`;
  }).join('');
}

function onHistoryFlagChange() {
  const transferCb = document.getElementById('historyFlagTransfer');
  const gradCb = document.getElementById('historyFlagGraduated');
  const saveBtn = document.getElementById('historyFlagsSave');
  if (!saveBtn) return;
  const changed = (transferCb && transferCb.checked !== (transferCb.dataset.original === '1')) ||
    (gradCb && gradCb.checked !== (gradCb.dataset.original === '1'));
  saveBtn.style.display = changed ? 'inline-flex' : 'none';
}

async function saveHistoryFlags() {
  if (!_historyStudentId) return;
  const transferCb = document.getElementById('historyFlagTransfer');
  const gradCb = document.getElementById('historyFlagGraduated');
  const saveBtn = document.getElementById('historyFlagsSave');
  saveBtn.disabled = true;
  try {
    const res = await fetch(API + '/admin-update-student-flags.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        user_id: _historyStudentId,
        transfer_authorized: transferCb?.checked ?? false,
        graduated: gradCb?.checked ?? false
      })
    });
    const data = await res.json();
    if (data.success) {
      if (transferCb) transferCb.dataset.original = transferCb.checked ? '1' : '0';
      if (gradCb) gradCb.dataset.original = gradCb.checked ? '1' : '0';
      saveBtn.style.display = 'none';
      const s = allStudents.find(st => st.id == _historyStudentId);
      if (s) { s.transfer_authorized = transferCb?.checked ? 1 : 0; s.graduated = gradCb?.checked ? 1 : 0; }
      Swal.fire({ icon: 'success', title: 'Saved', text: 'Student eligibility flags updated.', timer: 1500, showConfirmButton: false });
    } else Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to save' });
  } catch (e) { Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed' }); }
  saveBtn.disabled = false;
}

document.getElementById('historyFlagTransfer')?.addEventListener('change', onHistoryFlagChange);
document.getElementById('historyFlagGraduated')?.addEventListener('change', onHistoryFlagChange);
document.getElementById('historyFlagsSave')?.addEventListener('click', saveHistoryFlags);

document.getElementById('studentSearch').addEventListener('input', renderStudentsTable);
document.getElementById('studentProgramFilter').addEventListener('change', renderStudentsTable);
document.getElementById('refreshStudentsBtn').addEventListener('click', loadStudents);

// History modal close
document.getElementById('historyModalClose').addEventListener('click', () => document.getElementById('historyModalOverlay').classList.remove('open'));
document.getElementById('historyCloseBtn').addEventListener('click',   () => document.getElementById('historyModalOverlay').classList.remove('open'));
document.getElementById('historyModalOverlay').addEventListener('click', e => {
  if (e.target === document.getElementById('historyModalOverlay')) document.getElementById('historyModalOverlay').classList.remove('open');
});

/* ════════════════════════════════
   STAFF PAGE (Teller Management)
════════════════════════════════ */
async function handleAuthResponse(res, data) {
  if (res.status === 401 && data && data.account_deactivated) {
    await showDeactivatedModal();
    return true;
  }
  return false;
}

async function loadStaff() {
  const res  = await fetch(API + '/admin-manage-users.php', { credentials: 'include', headers: REG_HEADERS });
  const data = await res.json();
  if (await handleAuthResponse(res, data)) return;
  if (!data.success) {
    if (res.status === 403) console.warn('Staff API 403: Log out and log in again as Registrar to manage tellers. Close any Teller dashboard tabs if open.');
    return;
  }
  allStaff = data.users || [];
  renderStaffTable();
}

function getFilteredStaff() {
  const search = (document.getElementById('staffSearch').value || '').toLowerCase();
  const status = document.getElementById('staffStatusFilter').value;
  return allStaff.filter(u => {
    const matchSearch = !search || [u.name, u.username].some(v => (v || '').toLowerCase().includes(search));
    const matchStatus = status === '' || String(u.is_active) === status;
    return matchSearch && matchStatus;
  });
}

function renderStaffTable() {
  const tbody = document.getElementById('staffBody');
  const rows  = getFilteredStaff();
  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="10" class="admin__no-results">No teller accounts found.</td></tr>`;
    return;
  }
  tbody.innerHTML = rows.map((u, i) => {
    const date    = new Date(u.created_at).toLocaleDateString();
    const active  = parseInt(u.is_active);
    const pending = !active;
    const statusLabel = pending ? 'Pending Activation' : 'Active';
    const statusClass = pending ? 'pending'            : 'active';
    const perms = u.permissions || {};
    const depts = Array.isArray(perms.assigned_departments) && perms.assigned_departments.length
      ? perms.assigned_departments.map(d => {
          const short = (d.match(/\(([^)]+)\)/) || [null, d.slice(0,12)])[1] || d.slice(0,12);
          return `<span class="perm-tag granted" title="${escHtml(d)}">${escHtml(short)}</span>`;
        }).join('')
      : '<span class="perm-tag" style="opacity:.7">All</span>';
    const permTags = [
      '<span class="perm-tag required" title="Required">Requests</span>',
      perms.view_students  ? '<span class="perm-tag granted" title="Can view students">Students</span>'    : '<span class="perm-tag denied">Students</span>',
      perms.export_reports ? '<span class="perm-tag granted" title="Can export reports">Reports</span>'    : '<span class="perm-tag denied">Reports</span>',
    ].join('');
    return `<tr>
      <td>${i + 1}</td>
      <td><strong>${escHtml(u.name)}</strong></td>
      <td>${escHtml(u.username)}</td>
      <td style="font-size:.82rem;color:#555">${u.email ? escHtml(u.email) : '—'}</td>
      <td><span class="dev__status-badge ${statusClass}">${statusLabel}</span></td>
      <td><div class="perm-tags">${depts}</div></td>
      <td><div class="perm-tags">${permTags}</div></td>
      <td>${u.created_by_name ? escHtml(u.created_by_name) : '—'}</td>
      <td>${date}</td>
      <td style="display:flex;gap:.3rem;flex-wrap:wrap;align-items:center">
        <button class="dev__action-btn edit" onclick="editTeller(${u.id})" title="Edit permissions &amp; departments"><i class="ri-edit-line"></i></button>
        ${pending ? `
          <button class="dev__action-btn activate" onclick="activateTeller(${u.id},'${escHtml(u.name)}')" title="Manually Activate"><i class="ri-user-follow-line"></i></button>
          ${u.activation_link ? `<button class="dev__action-btn copy-link" onclick="copyActivationLink('${escHtml(u.activation_link)}','${escHtml(u.name)}')" title="Copy Activation Link"><i class="ri-link"></i></button>` : ''}
        ` : `<button class="dev__action-btn delete" onclick="deactivateTeller(${u.id},'${escHtml(u.name)}')" title="Deactivate"><i class="ri-user-unfollow-line"></i></button>`}
        <button class="dev__action-btn cancel" onclick="cancelTeller(${u.id},'${escHtml(u.name)}')" title="Cancel &amp; Permanently Delete"><i class="ri-delete-bin-line"></i></button>
      </td>
    </tr>`;
  }).join('');
}

document.getElementById('staffSearch').addEventListener('input', renderStaffTable);
document.getElementById('staffStatusFilter').addEventListener('change', renderStaffTable);

// Fallback department list (used if API fails)
const DEPARTMENTS_FALLBACK = [
  { value: 'Bachelor of Science in Accountancy (BSA)', label: 'BSA' },
  { value: 'Bachelor of Science in Entrepreneurship (BSE)', label: 'BSE' },
  { value: 'Bachelor of Science in Marketing (BSM)', label: 'BSM' },
  { value: 'Bachelor of Science in Office Administration (BSOA)', label: 'BSOA' },
  { value: 'Bachelor of Secondary Education (BSEd) - Mathematics', label: 'BSEd - Mathematics' },
  { value: 'Bachelor of Secondary Education (BSEd) - Science', label: 'BSEd - Science' },
  { value: 'Bachelor of Secondary Education (BSEd) - Technology Livelihood Education', label: 'BSEd - TLE' },
  { value: 'Bachelor of Physical Education (BPEd)', label: 'BPEd' },
  { value: 'Diploma in Teaching Secondary (DTS)', label: 'DTS' },
  { value: 'BTVTEd - Food and Service Management', label: 'BTVTEd - Food and Service' },
  { value: 'BTVTEd - Garments, Fashion, and Design', label: 'BTVTEd - Garments' },
  { value: 'BTLEd - Industrial Arts (IA)', label: 'BTLEd - IA' },
  { value: 'BTLEd - Home Economics (HE)', label: 'BTLEd - HE' },
  { value: 'Bachelor of Science in Civil Engineering (BSCE)', label: 'BSCE' },
  { value: 'Bachelor of Science in Information Technology (BSIT)', label: 'BSIT' },
  { value: 'Bachelor of Science in Hospitality Management', label: 'BS Hospitality Management' },
  { value: 'Bachelor of Science in Hotel and Restaurant Technology', label: 'BS Hotel and Restaurant Tech' },
  { value: 'BS in Industrial Technology - Culinary Arts', label: 'BS Industrial Tech - Culinary' },
  { value: 'BS in Industrial Technology - Electricity', label: 'BS Industrial Tech - Electricity' },
  { value: 'BS in Industrial Technology - Electronics', label: 'BS Industrial Tech - Electronics' },
  { value: 'BS in Mechanical Technology - Automotive', label: 'BS Mechanical Tech - Automotive' },
  { value: 'BS in Mechanical Technology - Welding and Fabrication', label: 'BS Mechanical Tech - Welding' },
  { value: 'Bachelor of Industrial Technology - Electrical Technology', label: 'BIT - Electrical Technology' },
];

let departmentsList = [];

async function ensureDepartmentsLoaded() {
  if (!departmentsList.length) {
    try {
      const r = await fetch(API + '/get-departments.php', { credentials: 'include' });
      const d = await r.json();
      if (d.success && Array.isArray(d.departments) && d.departments.length) {
        departmentsList = d.departments;
      } else {
        departmentsList = DEPARTMENTS_FALLBACK;
      }
    } catch (_) {
      departmentsList = DEPARTMENTS_FALLBACK;
    }
  }
}

document.getElementById('addTellerBtn').addEventListener('click', async () => {
  await refreshPermissions();
  document.getElementById('tellerEditId').value   = '';
  document.getElementById('tellerName').value     = '';
  document.getElementById('tellerUsername').value = '';
  document.getElementById('tellerEmail').value    = '';
  document.getElementById('tellerPermViewStudents').checked   = false;
  document.getElementById('tellerPermExportReports').checked  = false;
  document.getElementById('tellerModalTitle').textContent = 'Add Teller Account';
  document.getElementById('tellerUsername').disabled = false;
  if (myPermissions.assign_departments) {
    document.querySelectorAll('#tellerDeptCheckboxes .teller-dept-cb').forEach(cb => { if (cb) cb.checked = false; });
    await ensureDepartmentsLoaded();
    renderTellerDeptCheckboxes();
  }
  document.getElementById('tellerModalSaveBtn').textContent = 'Create & Send Invite';
  document.getElementById('tellerModalOverlay').classList.add('open');
});

async function editTeller(id) {
  await refreshPermissions();
  const u = allStaff.find(x => x.id === id);
  if (!u) return;
  document.getElementById('tellerEditId').value = String(id);
  document.getElementById('tellerName').value = u.name || '';
  document.getElementById('tellerUsername').value = u.username || '';
  document.getElementById('tellerEmail').value = u.email || '';
  document.getElementById('tellerUsername').disabled = true;
  const perms = u.permissions || {};
  document.getElementById('tellerPermViewStudents').checked = !!perms.view_students;
  document.getElementById('tellerPermExportReports').checked = !!perms.export_reports;
  if (myPermissions.assign_departments) {
    await ensureDepartmentsLoaded();
    renderTellerDeptCheckboxes();
    const depts = perms.assigned_departments || [];
    document.querySelectorAll('#tellerDeptCheckboxes .teller-dept-cb').forEach(cb => {
      if (cb) cb.checked = depts.includes(cb.value);
    });
  }
  document.getElementById('tellerModalTitle').textContent = 'Edit Teller';
  document.getElementById('tellerModalSaveBtn').textContent = 'Save Changes';
  document.getElementById('tellerModalOverlay').classList.add('open');
}

function renderTellerDeptCheckboxes() {
  const wrap = document.getElementById('tellerDeptCheckboxes');
  const loading = document.getElementById('tellerDeptLoading');
  if (!wrap || !loading) return;
  loading.style.display = 'none';
  if (!departmentsList.length) {
    loading.textContent = 'No departments available.';
    loading.style.display = 'block';
    wrap.style.display = 'none';
    return;
  }
  wrap.style.display = 'block';
  wrap.innerHTML = departmentsList.map(d => `
    <label class="dev-modal__checkbox">
      <input type="checkbox" class="teller-dept-cb" value="${escHtml(d.value)}">
      <span>${escHtml(d.label)}</span>
    </label>
  `).join('');
}

document.getElementById('tellerModalClose').addEventListener('click',     () => document.getElementById('tellerModalOverlay').classList.remove('open'));
document.getElementById('tellerModalCancelBtn').addEventListener('click', () => document.getElementById('tellerModalOverlay').classList.remove('open'));
document.getElementById('tellerModalOverlay').addEventListener('click', e => {
  if (e.target === document.getElementById('tellerModalOverlay')) document.getElementById('tellerModalOverlay').classList.remove('open');
});

document.getElementById('tellerModalSaveBtn').addEventListener('click', async () => {
  const editId  = document.getElementById('tellerEditId').value.trim();
  const name    = document.getElementById('tellerName').value.trim();
  const username = document.getElementById('tellerUsername').value.trim();
  const email   = document.getElementById('tellerEmail').value.trim();
  const isEdit  = !!editId;

  if (!name || !email) {
    Swal.fire({ icon: 'warning', title: 'Incomplete', text: 'Please fill in name and email.', confirmButtonColor: '#DD0426' });
    return;
  }
  if (!isEdit && !username) {
    Swal.fire({ icon: 'warning', title: 'Incomplete', text: 'Please fill in all fields (name, username, email).', confirmButtonColor: '#DD0426' });
    return;
  }

    const permissions = {
      handle_requests: true,
      view_students:   document.getElementById('tellerPermViewStudents').checked,
      export_reports:  document.getElementById('tellerPermExportReports').checked,
    };
    if (myPermissions.assign_departments) {
      permissions.assigned_departments = Array.from(document.querySelectorAll('#tellerDeptCheckboxes .teller-dept-cb:checked')).map(cb => cb.value).filter(Boolean);
    }

  const btn = document.getElementById('tellerModalSaveBtn');
  btn.disabled = true;
  btn.textContent = isEdit ? 'Saving...' : 'Sending Invite...';

  try {
    const payload = isEdit
      ? { id: parseInt(editId, 10), name, email, role: 'teller', permissions }
      : { name, username, email, role: 'teller', permissions };
    const res  = await fetch(API + '/admin-manage-users.php', {
      method: isEdit ? 'PUT' : 'POST',
      credentials: 'include',
      headers: { ...REG_HEADERS, 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      document.getElementById('tellerModalOverlay').classList.remove('open');
      if (isEdit) {
        Swal.fire({ icon: 'success', title: 'Saved', text: data.message, confirmButtonColor: '#DD0426' });
      } else {
        const extra = data.email_sent ? `<br><small>📧 Activation email sent to <strong>${email}</strong></small>` : '';
        Swal.fire({ icon: 'success', title: 'Account Created!', html: data.message + extra, confirmButtonColor: '#DD0426' });
      }
      loadStaff();
    } else {
      Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#DD0426' });
    }
  } catch {
    Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not connect to server.', confirmButtonColor: '#DD0426' });
  }

  btn.disabled = false;
  btn.textContent = isEdit ? 'Save Changes' : 'Create & Send Invite';
});

function copyActivationLink(link, name) {
  navigator.clipboard.writeText(link).then(() => {
    Swal.fire({
      icon: 'success',
      title: 'Link Copied!',
      html: `Activation link for <strong>${name}</strong> copied to clipboard.<br>
             <small style="color:#888;word-break:break-all">${link}</small><br><br>
             <small>Send this link to <strong>${name}</strong> so they can activate their account.</small>`,
      confirmButtonColor: '#DD0426',
    });
  }).catch(() => {
    Swal.fire({
      icon: 'info',
      title: 'Activation Link',
      html: `Copy and send this link to <strong>${name}</strong>:<br><br>
             <textarea style="width:100%;height:80px;font-size:.8rem;padding:.5rem;border-radius:.5rem;border:1px solid #ddd;resize:none">${link}</textarea>`,
      confirmButtonColor: '#DD0426',
    });
  });
}

async function activateTeller(id, name) {
  const confirmed = await Swal.fire({
    icon: 'question',
    title: 'Manually Activate Account?',
    html: `Activate <strong>${name}</strong> without email verification?<br><small style="color:#888">Use this when the activation email link is not accessible (local/offline setup).</small>`,
    showCancelButton: true,
    confirmButtonColor: '#DD0426',
    cancelButtonColor: '#888',
    confirmButtonText: 'Yes, Activate'
  });
  if (!confirmed.isConfirmed) return;

  const res  = await fetch(API + '/admin-manage-users.php', {
    method: 'PATCH',
    credentials: 'include',
    headers: { ...REG_HEADERS, 'Content-Type': 'application/json' },
    body: JSON.stringify({ id })
  });
  const data = await res.json();
  if (data.success) {
    Swal.fire({ icon: 'success', title: 'Activated!', text: data.message, timer: 2000, showConfirmButton: false });
    loadStaff();
  } else {
    Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#DD0426' });
  }
}

async function deactivateTeller(id, name) {
  const confirmed = await Swal.fire({
    icon: 'warning',
    title: 'Deactivate Teller?',
    text: `Are you sure you want to deactivate "${name}"? They will no longer be able to log in.`,
    showCancelButton: true,
    confirmButtonColor: '#DD0426',
    cancelButtonColor: '#888',
    confirmButtonText: 'Yes, Deactivate'
  });
  if (!confirmed.isConfirmed) return;

  const res  = await fetch(API + '/admin-manage-users.php', {
    method: 'DELETE',
    credentials: 'include',
    headers: { ...REG_HEADERS, 'Content-Type': 'application/json' },
    body: JSON.stringify({ id })
  });
  const data = await res.json();
  if (data.success) {
    Swal.fire({ icon: 'success', title: 'Deactivated', text: data.message, confirmButtonColor: '#DD0426' });
    loadStaff();
  } else {
    Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#DD0426' });
  }
}

async function cancelTeller(id, name) {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Cancel & Delete Account?',
    html: `This will <strong>permanently remove</strong> <strong>${name}</strong>'s account.<br>
           <small style="color:#DD0426">This action cannot be undone.</small>`,
    input: 'text',
    inputPlaceholder: 'Type DELETE to confirm',
    showCancelButton: true,
    confirmButtonColor: '#DD0426',
    cancelButtonColor: '#aaa',
    confirmButtonText: 'Permanently Delete',
    preConfirm: (val) => {
      if (val !== 'DELETE') {
        Swal.showValidationMessage('Type DELETE in all caps to confirm');
      }
    }
  });
  if (!result.isConfirmed) return;

  try {
    const res  = await fetch(API + '/admin-manage-users.php', {
      method: 'DELETE', headers: { ...REG_HEADERS, 'Content-Type': 'application/json' }, credentials: 'include',
      body: JSON.stringify({ id, permanent: true })
    });
    const data = await res.json();
    if (data.success) {
      loadStaff();
      Swal.fire({ icon: 'success', title: 'Deleted', text: data.message, timer: 2000, showConfirmButton: false });
    } else {
      Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#DD0426' });
    }
  } catch {
    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Please try again.', confirmButtonColor: '#DD0426' });
  }
}

setInterval(() => {
  loadRequests();
  loadStudents();
  if (myPermissions.manage_staff) loadStaff();
  loadChatList();
}, 30000);

// Realtime monitoring: poll requests every 5s when on Home or Requests page
let requestsPollId = null;
function startRequestsPoll() {
  if (requestsPollId) return;
  requestsPollId = setInterval(() => { loadRequests(); }, 2000);
}
function stopRequestsPoll() {
  if (requestsPollId) { clearInterval(requestsPollId); requestsPollId = null; }
}

/* ── Maintenance mode poll: if developer enables, force logout ── */
let maintenancePollId = null;
async function checkMaintenanceAndForceLogout() {
  try {
    const res = await fetch(API + '/check-maintenance.php?t=' + Date.now(), { credentials: 'include', cache: 'no-store' });
    const data = await res.json();
    if (data.maintenance && maintenancePollId) {
      clearInterval(maintenancePollId);
      maintenancePollId = null;
      await Swal.fire({
        icon: 'warning',
        title: 'Maintenance Mode Enabled',
        html: '<p>System is now under maintenance. You must logout.</p>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showCancelButton: false,
        confirmButtonColor: '#DD0426',
        confirmButtonText: 'Logout'
      });
      await fetch(API + '/admin-logout.php', { credentials: 'include' });
      window.location.href = 'admin-login.php';
    }
  } catch (e) { /* ignore */ }
}

(async () => {
  const name = await checkAuth();
  if (name) {
    document.getElementById('adminName').textContent  = name;
    document.getElementById('welcomeName').textContent = name.split(' ')[0];
    setInitials(name);
    maintenancePollId = setInterval(checkMaintenanceAndForceLogout, 5000);
    const initPromises = [loadRequests(), loadStudents(), loadChatList()];
    if (myPermissions.manage_staff) initPromises.push(loadStaff());
    await Promise.all(initPromises);
    startRequestsPoll(); // Start realtime poll (home is default page)
  }
})();

/* ════════════════════════════════
   SUPPORT / CHAT
════════════════════════════════ */
let allChats      = [];
let activeChatId  = null;
let chatPollTimer = null;

function fmtTime(dt) { return new Date(dt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
function fmtDate(dt) {
  if (!dt) return '';
  const d = new Date(dt), today = new Date();
  return d.toDateString() === today.toDateString() ? fmtTime(dt) : d.toLocaleDateString();
}

const CHAT_CHANNEL = 'registrar';

async function loadChatList() {
  const res  = await fetch(API + '/chat.php?action=list&channel=' + CHAT_CHANNEL, { credentials: 'include' });
  const data = await res.json();
  if (!data.success) return;
  allChats = data.chats;
  renderChatList();
  updateSupportBadge();
}

function renderChatList() {
  const body   = document.getElementById('chatListBody');
  if (!body) return;
  const search = (document.getElementById('chatListSearch')?.value || '').toLowerCase();
  const rows   = allChats.filter(c => !search ||
    [c.names, c.surnames, c.student_number, c.email].some(v => (v||'').toLowerCase().includes(search)));
  if (!rows.length) { body.innerHTML = '<div class="chat-list__empty"><i class="ri-chat-off-line"></i><p>No conversations yet</p></div>'; return; }
  body.innerHTML = rows.map(c => {
    const name    = `${c.names} ${c.surnames}`;
    const initials= name.trim().split(/\s+/).map(w => (w[0]||'').toUpperCase()).slice(0,2).join('');
    const unread  = parseInt(c.unread) || 0;
    const active  = activeChatId === parseInt(c.id) ? ' active' : '';
    const resolved= c.status === 'resolved' ? ' resolved' : '';
    const preview = c.last_message ? escHtml(c.last_message).substring(0,55) + (c.last_message.length > 55 ? '…' : '') : '<em>No messages yet</em>';
    return `<div class="chat-list__item${active}${resolved}" data-chat-id="${c.id}" onclick="openChat(${c.id})">
      <div class="chat-list__avatar">${initials}</div>
      <div class="chat-list__info">
        <div class="chat-list__name"><span>${escHtml(name)}</span><span class="chat-list__time">${fmtDate(c.last_at)}</span></div>
        <div class="chat-list__preview"><span>${preview}</span>${unread > 0 ? `<span class="chat-list__unread">${unread}</span>` : ''}</div>
        ${c.status === 'resolved' ? '<span class="chat-list__resolved-tag">Resolved</span>' : ''}
      </div></div>`;
  }).join('');
}

function updateSupportBadge() {
  const total = allChats.reduce((s, c) => s + (parseInt(c.unread)||0), 0);
  const badge = document.getElementById('supportBadge');
  if (badge) { badge.textContent = total; badge.classList.toggle('hidden', total === 0); }
}

async function openChat(chatId) {
  activeChatId = parseInt(chatId);
  renderChatList();
  const chat = allChats.find(c => parseInt(c.id) === activeChatId);
  if (!chat) return;
  const name = `${chat.names} ${chat.surnames}`;
  const initials = name.trim().split(/\s+/).map(w => (w[0]||'').toUpperCase()).slice(0,2).join('');
  document.getElementById('cwAvatar').textContent      = initials;
  document.getElementById('cwStudentName').textContent = name;
  document.getElementById('cwStudentNum').textContent  = chat.student_number;
  const badge = document.getElementById('cwStatusBadge');
  badge.textContent = chat.status; badge.className = `chat-window__status-badge ${chat.status}`;
  const resolveBtn = document.getElementById('cwResolveBtn');
  if (chat.status === 'resolved') { resolveBtn.innerHTML = '<i class="ri-refresh-line"></i> Reopen'; resolveBtn.dataset.action = 'reopen'; }
  else { resolveBtn.innerHTML = '<i class="ri-check-double-line"></i> Resolve'; resolveBtn.dataset.action = 'resolve'; }
  document.getElementById('chatWindowEmpty').style.display  = 'none';
  document.getElementById('chatWindowActive').style.display = '';
  await loadChatMessages(activeChatId);
  if (chatPollTimer) clearInterval(chatPollTimer);
  chatPollTimer = setInterval(() => { if (activeChatId) loadChatMessages(activeChatId, true); }, 5000);
  const c = allChats.find(x => parseInt(x.id) === activeChatId);
  if (c) { c.unread = 0; updateSupportBadge(); }
}

async function loadChatMessages(chatId, silent = false) {
  const wrap = document.getElementById('cwMessages');
  if (!wrap) return;
  try {
    const res  = await fetch(`${API}/chat.php?action=messages&chat_id=${chatId}&channel=${CHAT_CHANNEL}&_=${Date.now()}`, { credentials: 'include', cache: 'no-store' });
    const data = await res.json();
    if (!data.success) {
      wrap.innerHTML = '<div class="chat-msg-empty"><i class="ri-error-warning-line"></i><p>Could not load messages. ' + escHtml(data.message || 'Please refresh.') + '</p></div>';
      return;
    }
    const messages = Array.isArray(data.messages) ? data.messages : [];
    if (!messages.length) {
      wrap.innerHTML = '<div class="chat-msg-empty"><i class="ri-chat-3-line"></i><p>No messages yet. Start the conversation!</p></div>';
      return;
    }
    const atBottom = wrap.scrollHeight - wrap.scrollTop - wrap.clientHeight < 60;
    wrap.innerHTML = messages.map(m => {
      const isMe = (m.sender_type || '').toLowerCase() === 'admin';
      const side = isMe ? 'out' : 'in';
      const name = (m.sender_name || '').toString().trim() || (isMe ? 'You' : 'Student');
      const initials = name.split(/\s+/).map(w => (w[0]||'').toUpperCase()).slice(0,2).join('') || '?';
      return `<div class="chat-msg chat-msg--${side}">
        ${side === 'in' ? `<div class="chat-msg__avatar">${initials}</div>` : ''}
        <div class="chat-msg__bubble">
          ${side === 'in' ? `<span class="chat-msg__sender">${escHtml(name)}</span>` : ''}
          <p>${escHtml(m.message || '')}</p>
          <span class="chat-msg__time">${fmtTime(m.created_at)}</span>
        </div>
      </div>`;
    }).join('');
    if (!silent || atBottom) wrap.scrollTop = wrap.scrollHeight;
  } catch (e) {
    wrap.innerHTML = '<div class="chat-msg-empty"><i class="ri-error-warning-line"></i><p>Failed to load messages. Check your connection.</p></div>';
  }
}

async function sendChatMessage() {
  const input = document.getElementById('cwInput');
  const msg   = input.value.trim();
  if (!msg || !activeChatId) return;
  input.value = '';
  try {
    const res = await fetch(API + '/chat.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
      body: JSON.stringify({ action: 'send', chat_id: activeChatId, message: msg, as_admin: true, channel: CHAT_CHANNEL }) });
    const data = res.ok ? await res.json().catch(() => ({})) : { success: false, message: 'Request failed' };
    if (!res.ok) {
      const errMsg = (data.message || 'Could not send message. Please log out and log in again.').replace(/^Cannot send: /, '');
      if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Send Failed', text: errMsg });
      input.value = msg;
      return;
    }
  } catch (e) {
    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
    input.value = msg;
    return;
  }
  await loadChatMessages(activeChatId);
  const c = allChats.find(x => parseInt(x.id) === activeChatId);
  if (c) { c.last_message = msg; c.last_at = new Date().toISOString(); renderChatList(); }
}

document.getElementById('cwResolveBtn').addEventListener('click', async () => {
  if (!activeChatId) return;
  const btn = document.getElementById('cwResolveBtn');
  const action = btn.dataset.action || 'resolve';
  const res  = await fetch(API + '/chat.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
    body: JSON.stringify({ action, chat_id: activeChatId, channel: CHAT_CHANNEL }) });
  const data = await res.json();
  if (data.success) {
    const c = allChats.find(x => parseInt(x.id) === activeChatId);
    if (c) { c.status = data.status; renderChatList(); }
    const badge = document.getElementById('cwStatusBadge');
    badge.textContent = data.status; badge.className = `chat-window__status-badge ${data.status}`;
    if (data.status === 'resolved') { btn.innerHTML = '<i class="ri-refresh-line"></i> Reopen'; btn.dataset.action = 'reopen'; }
    else { btn.innerHTML = '<i class="ri-check-double-line"></i> Resolve'; btn.dataset.action = 'resolve'; }
  }
});

document.getElementById('cwSendBtn').addEventListener('click', sendChatMessage);
document.getElementById('cwInput').addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChatMessage(); } });
document.getElementById('chatRefreshBtn').addEventListener('click', loadChatList);
document.getElementById('chatListSearch').addEventListener('input', renderChatList);

const _supportNavAdmin = document.querySelector('.sidebar__link[data-page="support"]');
if (_supportNavAdmin && !_supportNavAdmin._chatBound) {
  _supportNavAdmin.addEventListener('click', e => { e.preventDefault(); navigateTo('support'); loadChatList(); });
  _supportNavAdmin._chatBound = true;
}
