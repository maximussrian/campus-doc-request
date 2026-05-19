/* API from app-config.js */
let allRequests = [];
let allStudents = [];
let myPermissions = {};

/* ════════════════════════════════
   AUTH CHECK — teller only
════════════════════════════════ */
async function handleAuthResponse(res, data) {
  if (res.status === 401 && data && data.account_deactivated) {
    await showDeactivatedModal();
    return true;
  }
  return false;
}

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
  window.location.href = 'teller-login.php';
}

async function checkAuth() {
  const opts = { credentials: 'include', cache: 'no-store' };
  let data = {};
  for (let attempt = 1; attempt <= 5; attempt++) {
    try {
      const res = await fetch(API + '/teller-check-auth.php?t=' + Date.now(), opts);
      const text = await res.text();
      data = text ? JSON.parse(text) : {};
      if (data.account_deactivated) { await showDeactivatedModal(); return null; }
      if (data.success && data.role === 'teller') { myPermissions = data.permissions || {}; applyTellerPermissions(); return data.name; }
    } catch (e) { data = {}; }
    if (attempt === 5) { window.location.href = 'teller-login.php'; return null; }
    await new Promise(r => setTimeout(r, 600));
  }
  myPermissions = data.permissions || {};
  applyTellerPermissions();
  return data.name;
}

function applyTellerPermissions() {
  const studentsLink  = document.querySelector('.sidebar__link[data-page="students"]');
  const reportsLink   = document.querySelector('.sidebar__link[data-page="reports"]');
  const studentsPage  = document.getElementById('page-students');
  const reportsPage   = document.getElementById('page-reports');

  if (myPermissions.view_students) {
    if (studentsLink) studentsLink.style.display = '';
    if (studentsPage) studentsPage.style.display = '';
    pageNames['students'] = 'Students';
    // Register nav listener for dynamically shown link
    if (studentsLink && !studentsLink._bound) {
      studentsLink.addEventListener('click', e => { e.preventDefault(); navigateTo('students'); });
      studentsLink._bound = true;
    }
  }
  if (myPermissions.export_reports) {
    if (reportsLink) reportsLink.style.display = '';
    if (reportsPage) reportsPage.style.display = '';
    pageNames['reports'] = 'Reports';
    if (reportsLink && !reportsLink._bound) {
      reportsLink.addEventListener('click', e => { e.preventDefault(); navigateTo('reports'); });
      reportsLink._bound = true;
    }
    setupExportButtons();
  }
  if (myPermissions.view_students) {
    setupStudentListeners();
  }
  if (myPermissions.view_students || myPermissions.export_reports) {
    updateReportsSummary();
  }
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

const pageNames = { home: 'Home', requests: 'Requests' };

function navigateTo(pageId, filterStatus) {
  document.querySelectorAll('.sidebar__link').forEach(l => {
    l.classList.toggle('active', l.dataset.page === pageId);
  });
  document.querySelectorAll('.admin-page').forEach(p => p.classList.remove('active'));
  const target = document.getElementById('page-' + pageId);
  if (target) {
    target.classList.add('active');
    target.style.animation = 'none';
    target.offsetHeight;
    target.style.animation = '';
  }
  if (pageTitleEl) pageTitleEl.textContent = pageNames[pageId] || '';
  if (pageId === 'requests' && filterStatus !== undefined) {
    document.getElementById('filterStatus').value = filterStatus;
    renderTable();
  }
  if (pageId === 'home' || pageId === 'requests') startRequestsPoll();
  else stopRequestsPoll();
  closeMobileSidebar();
}

document.querySelectorAll('.sidebar__link[data-page]').forEach(link => {
  link.addEventListener('click', e => { e.preventDefault(); navigateTo(link.dataset.page); });
});

document.querySelectorAll('a[data-page]').forEach(el => {
  if (!el.classList.contains('sidebar__link')) {
    el.addEventListener('click', e => { e.preventDefault(); navigateTo(el.dataset.page); });
  }
});

document.querySelectorAll('.admin__stat-card[data-page="requests"]').forEach(card => {
  card.addEventListener('click', () => navigateTo('requests', card.dataset.filter || ''));
});

// Hover expand/collapse
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

mobileMenuBtn.addEventListener('click', openMobileSidebar);
sidebarOverlay.addEventListener('click', closeMobileSidebar);

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
  const res  = await fetch(API + '/teller-get-requests.php', { credentials: 'include' });
  const data = await res.json();
  if (await handleAuthResponse(res, data)) return;
  if (!data.success) return;
  allRequests = data.requests;
  updateStats();
  renderTable();
  renderRecentTable();
  if (myPermissions.export_reports) updateReportsSummary();
}

function updateReportsSummary() {
  const counts = { pending: 0, processing: 0, ready: 0, claimed: 0 };
  allRequests.forEach(r => { if (counts[r.status] !== undefined) counts[r.status]++; });
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  set('rAll', allRequests.length);
  set('rPending', counts.pending);
  set('rProcessing', counts.processing);
  set('rReady', counts.ready);
  set('rClaimed', counts.claimed);
}

function updateStats() {
  const counts = { pending: 0, processing: 0, ready: 0, claimed: 0 };
  allRequests.forEach(r => { if (counts[r.status] !== undefined) counts[r.status]++; });

  document.getElementById('statAll').textContent        = allRequests.length;
  document.getElementById('statPending').textContent    = counts.pending;
  document.getElementById('statProcessing').textContent = counts.processing;
  document.getElementById('statReady').textContent      = counts.ready;
  document.getElementById('statClaimed').textContent    = counts.claimed;

  const badge = document.getElementById('pendingBadge');
  badge.textContent = counts.pending;
  badge.classList.toggle('hidden', counts.pending === 0);
}

function renderRecentTable() {
  const tbody  = document.getElementById('recentBody');
  const recent = allRequests.slice(0, 8);
  if (!recent.length) { tbody.innerHTML = '<tr><td colspan="4" class="admin__no-results">No requests yet.</td></tr>'; return; }
  tbody.innerHTML = recent.map(r => {
    const date = new Date(r.requested_at).toLocaleDateString();
    return `<tr class="req-row-clickable" onclick="viewRequestDetail(${r.id})" title="Click to view details">
      <td>
        <div class="student-name">${escHtml(r.names)} ${escHtml(r.surnames)}</div>
        <div class="student-info">${escHtml(r.student_number)}</div>
      </td>
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
      <td>
        <div class="student-name">${escHtml(r.names)} ${escHtml(r.surnames)}</div>
        <div class="student-info">${escHtml(r.student_number)}</div>
        <div class="student-info">${escHtml(r.email || '')}</div>
      </td>
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
        <button class="req-detail__view-btn" onclick="viewRequestDetail(${r.id})" title="View Details">
          <i class="ri-eye-line"></i>
        </button>
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

  const r = allRequests.find(r => r.id == id);
  if (r && r.status === 'claimed') {
    select.value = 'claimed';
    Swal.fire({ icon: 'warning', title: 'Locked', text: 'This request has already been claimed and cannot be changed.', confirmButtonColor: '#DD0426' });
    return;
  }

  select.className = 'admin__status-select ' + newStatus;
  try {
    const res  = await fetch(API + '/teller-update-status.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
      body: JSON.stringify({ id, status: newStatus })
    });
    const data = await res.json();
    if (data.success) {
      if (r) r.status = newStatus;
      updateStats();
      renderRecentTable();
      renderTable(); // re-render so claimed rows show locked badge
      const extra = data.email_sent ? '<br><small>📧 Notification sent to student.</small>' : '';
      Swal.fire({ icon: 'success', title: 'Updated', html: `Status set to <strong>${newStatus}</strong>${extra}`, timer: 2000, showConfirmButton: false });
    } else {
      select.className = prevClass;
      if (r) select.value = r.status;
      Swal.fire({ icon: 'error', title: 'Error', text: data.message });
    }
  } catch {
    select.className = prevClass;
    if (r) select.value = r.status;
    Swal.fire({ icon: 'error', title: 'Connection error', text: 'Please try again.' });
  }
}

/* ════════════════════════════════
   REQUEST DETAIL MODAL
════════════════════════════════ */
const STATUS_ORDER = ['pending', 'processing', 'ready', 'claimed'];
const STATUS_ICONS = { pending: 'ri-time-line', processing: 'ri-loader-3-line', ready: 'ri-checkbox-circle-line', claimed: 'ri-hand-coin-line' };
const STATUS_LABELS = { pending: 'Pending', processing: 'Processing', ready: 'Ready to Claim', claimed: 'Claimed' };

function buildTimeline(currentStatus) {
  return STATUS_ORDER.map(s => {
    const idx     = STATUS_ORDER.indexOf(s);
    const curIdx  = STATUS_ORDER.indexOf(currentStatus);
    const done    = idx < curIdx;
    const active  = s === currentStatus;
    const cls     = done ? 'done' : active ? 'active' : 'upcoming';
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

  document.getElementById('rdDocName').textContent  = r.document_name;
  document.getElementById('rdRefNum').textContent   = `Request #${r.id}`;

  const badge = document.getElementById('rdHeaderStatus');
  badge.className = `admin__status-badge ${r.status}`;
  badge.innerHTML = r.status === 'claimed' ? '<i class="ri-lock-line"></i> Claimed' : capitalize(r.status);

  const fullName = `${r.names} ${r.surnames}`;
  const initials = fullName.trim().split(/\s+/).map(w => (w[0] || '').toUpperCase()).slice(0, 2).join('');
  document.getElementById('rdAvatar').textContent         = initials;
  document.getElementById('rdStudentName').textContent    = fullName;
  document.getElementById('rdStudentNum').textContent     = r.student_number;
  document.getElementById('rdStudentEmail').textContent   = r.email || '';

  document.getElementById('rdDocument').textContent = r.document_name;
  document.getElementById('rdDept').textContent     = r.department || '—';
  document.getElementById('rdPurpose').textContent  = r.purpose    || '—';
  document.getElementById('rdDate').textContent     = new Date(r.requested_at).toLocaleString();

  const processedSection = document.getElementById('rdProcessedSection');
  const processedByEl = document.getElementById('rdProcessedBy');
  if (processedSection && processedByEl) {
    if (r.processed_by_name || r.processed_by_admin_id) {
      const name = r.processed_by_name || ('Staff #' + r.processed_by_admin_id);
      const date = r.processed_at ? ' (' + new Date(r.processed_at).toLocaleString() + ')' : '';
      processedByEl.textContent = name + date;
      processedSection.style.display = '';
    } else {
      processedSection.style.display = 'none';
    }
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
    const res  = await fetch(API + '/teller-update-status.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
      body: JSON.stringify({ id, status: newStatus })
    });
    const data = await res.json();
    if (data.success) {
      if (r) r.status = newStatus;
      updateStats();
      renderRecentTable();
      renderTable();
      if (myPermissions.export_reports) updateReportsSummary();

      const badge = document.getElementById('rdHeaderStatus');
      badge.className = `admin__status-badge ${newStatus}`;
      badge.innerHTML = newStatus === 'claimed' ? '<i class="ri-lock-line"></i> Claimed' : capitalize(newStatus);
      document.getElementById('rdTimeline').innerHTML = buildTimeline(newStatus);
      if (newStatus === 'claimed') document.getElementById('rdUpdateSection').style.display = 'none';

      const extra = data.email_sent ? '<br><small>📧 Notification sent to student.</small>' : '';
      Swal.fire({ icon: 'success', title: 'Updated', html: `Status set to <strong>${newStatus}</strong>${extra}`, timer: 2200, showConfirmButton: false });
    } else {
      Swal.fire({ icon: 'error', title: 'Error', text: data.message });
    }
  } catch {
    Swal.fire({ icon: 'error', title: 'Connection error', text: 'Please try again.' });
  }
});

/* ════════════════════════════════
   STUDENTS PAGE (if view_students)
════════════════════════════════ */
async function loadStudents() {
  const res  = await fetch(API + '/teller-get-students.php', { credentials: 'include' });
  const data = await res.json();
  if (await handleAuthResponse(res, data)) return;
  if (!data.success) return;
  allStudents = data.students;
  renderStudentsTable();
}

function renderStudentsTable() {
  const tbody = document.getElementById('studentsBody');
  if (!tbody) return;
  const search = (document.getElementById('studentSearch')?.value || '').toLowerCase();
  const rows = allStudents.filter(s =>
    !search || [s.names, s.surnames, s.student_number, s.email].some(v => (v||'').toLowerCase().includes(search))
  );
  if (!rows.length) { tbody.innerHTML = `<tr><td colspan="10" class="admin__no-results">No students found.</td></tr>`; return; }
  tbody.innerHTML = rows.map((s, i) => {
    const date  = new Date(s.created_at).toLocaleDateString();
    const total = parseInt(s.total_requests) || 0;
    return `<tr>
      <td>${i + 1}</td>
      <td><div class="student-name">${s.names} ${s.surnames}</div><div class="student-info">${s.student_number}</div></td>
      <td><div class="student-info">${s.email}</div></td>
      <td><strong>${total}</strong></td>
      <td>${s.pending   || 0}</td>
      <td>${s.processing|| 0}</td>
      <td>${s.ready     || 0}</td>
      <td>${s.claimed   || 0}</td>
      <td>${date}</td>
      <td>${total > 0
        ? `<button class="dev__action-btn edit" onclick="viewStudentHistory(${s.id},'${escHtml(s.names + ' ' + s.surnames)}')" title="View History"><i class="ri-eye-line"></i></button>`
        : `<span style="color:#ccc;font-size:.75rem">None</span>`}
      </td>
    </tr>`;
  }).join('');
}

let _historyStudentId = null;

function viewStudentHistory(studentId, studentName) {
  _historyStudentId = studentId;
  const overlay = document.getElementById('historyModalOverlay');
  document.getElementById('historyModalTitle').textContent = studentName + ' — Request History';
  overlay.classList.add('open');
  const tbody   = document.getElementById('historyBody');
  const student = allStudents.find(st => st.id == studentId);
  const transferCb = document.getElementById('historyFlagTransfer');
  const gradCb = document.getElementById('historyFlagGraduated');
  const saveBtn = document.getElementById('historyFlagsSave');
  if (transferCb) { transferCb.checked = !!student?.transfer_authorized; transferCb.dataset.original = transferCb.checked ? '1' : '0'; }
  if (gradCb) { gradCb.checked = !!student?.graduated; gradCb.dataset.original = gradCb.checked ? '1' : '0'; }
  if (saveBtn) saveBtn.style.display = 'none';
  const reqs    = student ? allRequests.filter(r => r.student_number === student.student_number) : [];
  if (!reqs.length) { tbody.innerHTML = `<tr><td colspan="5" class="admin__no-results">No requests found.</td></tr>`; return; }
  tbody.innerHTML = reqs.map(r => {
    const date = new Date(r.requested_at).toLocaleDateString();
    return `<tr>
      <td>${r.document_name}</td>
      <td>${r.department || '-'}</td>
      <td>${r.purpose    || '-'}</td>
      <td>${date}</td>
      <td><span class="admin__status-badge ${r.status}">${capitalize(r.status)}</span></td>
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
    const res = await fetch(API + '/teller-update-student-flags.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
      body: JSON.stringify({ user_id: _historyStudentId, transfer_authorized: transferCb?.checked ?? false, graduated: gradCb?.checked ?? false })
    });
    const data = await res.json();
    if (data.success) {
      if (transferCb) transferCb.dataset.original = transferCb.checked ? '1' : '0';
      if (gradCb) gradCb.dataset.original = gradCb.checked ? '1' : '0';
      saveBtn.style.display = 'none';
      const s = allStudents.find(st => st.id == _historyStudentId);
      if (s) { s.transfer_authorized = transferCb?.checked ? 1 : 0; s.graduated = gradCb?.checked ? 1 : 0; }
      if (typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: 'Saved', text: 'Student eligibility flags updated.', timer: 1500, showConfirmButton: false });
    } else if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to save' });
  } catch (e) { if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed' }); }
  saveBtn.disabled = false;
}

function setupStudentListeners() {
  const searchEl   = document.getElementById('studentSearch');
  const refreshBtn = document.getElementById('refreshStudentsBtn');
  const closeBtn   = document.getElementById('historyModalClose');
  const closeBtnF  = document.getElementById('historyCloseBtn');
  const overlay    = document.getElementById('historyModalOverlay');
  if (searchEl   && !searchEl._bound)   { searchEl.addEventListener('input', renderStudentsTable); searchEl._bound = true; }
  if (refreshBtn && !refreshBtn._bound) { refreshBtn.addEventListener('click', loadStudents); refreshBtn._bound = true; }
  if (closeBtn   && !closeBtn._bound)   { closeBtn.addEventListener('click', () => overlay.classList.remove('open')); closeBtn._bound = true; }
  if (closeBtnF  && !closeBtnF._bound)  { closeBtnF.addEventListener('click', () => overlay.classList.remove('open')); closeBtnF._bound = true; }
  if (overlay    && !overlay._bound)    { overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); }); overlay._bound = true; }
  const transferCb = document.getElementById('historyFlagTransfer');
  const gradCb = document.getElementById('historyFlagGraduated');
  const saveBtn = document.getElementById('historyFlagsSave');
  if (transferCb && !transferCb._bound) { transferCb.addEventListener('change', onHistoryFlagChange); transferCb._bound = true; }
  if (gradCb && !gradCb._bound) { gradCb.addEventListener('change', onHistoryFlagChange); gradCb._bound = true; }
  if (saveBtn && !saveBtn._bound) { saveBtn.addEventListener('click', saveHistoryFlags); saveBtn._bound = true; }
}

function escHtml(str) {
  return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

/* ════════════════════════════════
   EXPORT / REPORTS PAGE (if export_reports)
════════════════════════════════ */
function setupExportButtons() {
  const csvBtn   = document.getElementById('exportCsvBtn');
  const xlsBtn   = document.getElementById('exportExcelBtn');
  const clrBtn   = document.getElementById('clearDates');
  if (csvBtn && !csvBtn._bound) {
    csvBtn.addEventListener('click', () => doExport('csv'));
    csvBtn._bound = true;
  }
  if (xlsBtn && !xlsBtn._bound) {
    xlsBtn.addEventListener('click', () => doExport('xls'));
    xlsBtn._bound = true;
  }
  if (clrBtn && !clrBtn._bound) {
    clrBtn.addEventListener('click', () => {
      const df = document.getElementById('exportDateFrom');
      const dt = document.getElementById('exportDateTo');
      if (df) df.value = '';
      if (dt) dt.value = '';
    });
    clrBtn._bound = true;
  }
}

function doExport(format) {
  const status   = document.getElementById('exportStatus')?.value   || '';
  const dateFrom = document.getElementById('exportDateFrom')?.value || '';
  const dateTo   = document.getElementById('exportDateTo')?.value   || '';
  const params   = new URLSearchParams({ format });
  if (status)   params.set('status',    status);
  if (dateFrom) params.set('date_from', dateFrom);
  if (dateTo)   params.set('date_to',   dateTo);
  window.open(API + '/teller-export.php?' + params.toString(), '_blank');
}

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
  await fetch(API + '/teller-logout.php', { credentials: 'include' });
  window.location.href = 'teller-login.php';
});

/* ════════════════════════════════
   HELPERS & INIT
════════════════════════════════ */
function capitalize(str) { return str.charAt(0).toUpperCase() + str.slice(1); }

function setInitials(fullName) {
  const initials = fullName.trim().split(/\s+/).map(w => w[0].toUpperCase()).slice(0, 2).join('');
  ['sidebarAvatar', 'topbarAvatar'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.textContent = initials;
  });
}

setInterval(() => {
  loadRequests();
  if (myPermissions.view_students) loadStudents();
}, 30000);

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
    const res = await fetch(API + '/teller-check-maintenance.php?t=' + Date.now(), { credentials: 'include', cache: 'no-store' });
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
      await fetch(API + '/teller-logout.php', { credentials: 'include' });
      window.location.href = 'teller-login.php';
    }
  } catch (e) { /* ignore */ }
}

(async () => {
  const name = await checkAuth();
  if (!name) return;
  document.getElementById('tellerName').textContent   = name;
  document.getElementById('welcomeName').textContent  = name.split(' ')[0];
  setInitials(name);
  maintenancePollId = setInterval(checkMaintenanceAndForceLogout, 5000);
  const tasks = [loadRequests()];
  startRequestsPoll();
  if (myPermissions.view_students) tasks.push(loadStudents());
  await Promise.all(tasks);
})();
