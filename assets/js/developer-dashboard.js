/* API from app-config.js */
const DEV_REALM = 'developer';
const _fetch = window.fetch;
window.fetch = function(url, opts) {
  opts = opts || {};
  if (typeof url === 'string' && url.indexOf(API) === 0) {
    const h = opts.headers || {};
    opts.headers = (h instanceof Headers) ? new Headers(h) : new Headers(h);
    opts.headers.set('X-Session-Realm', DEV_REALM);
  }
  return _fetch.apply(this, arguments);
};
let allRequests  = [];
let allUsers     = [];
let allStudents  = [];

/* ════════════════════════════════
   AUTH CHECK
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
  window.location.href = 'developer-login.php';
}

async function checkAuth() {
  const opts = { credentials: 'include', cache: 'no-store' };
  for (let attempt = 1; attempt <= 12; attempt++) {
    try {
      const res = await fetch(API + '/admin-check-auth.php?t=' + Date.now(), opts);
      const data = res.ok ? (await res.json()) : {};
      if (data.account_deactivated) { await showDeactivatedModal(); return null; }
      if (data.success && data.role === 'developer') return data.name || 'Developer';
    } catch (e) { }
    if (attempt === 12) { window.location.href = 'developer-login.php'; return null; }
    await new Promise(r => setTimeout(r, 400));
  }
  return null;
}

// Show the correct permission checkboxes for the selected role in the modal
function updatePermissionsPanel(role) {
  const tellerGroup    = document.getElementById('tellerPermsGroup');
  const registrarGroup = document.getElementById('registrarPermsGroup');
  const permField      = document.getElementById('permissionsField');
  if (role === 'teller') {
    tellerGroup.style.display    = '';
    registrarGroup.style.display = 'none';
    permField.style.display      = '';
  } else if (role === 'registrar') {
    tellerGroup.style.display    = 'none';
    registrarGroup.style.display = '';
    permField.style.display      = '';
  } else {
    permField.style.display = 'none'; // developer has all permissions, no panel needed
  }
}

// Load permissions into the modal checkboxes from a permissions object
function loadPermissionsIntoModal(role, perms) {
  if (role === 'teller') {
    document.getElementById('devPermViewStudents').checked  = !!(perms && perms.view_students);
    document.getElementById('devPermExportReports').checked = !!(perms && perms.export_reports);
  } else if (role === 'registrar') {
    document.getElementById('devPermManageRequests').checked   = perms ? perms.manage_requests    !== false : true;
    document.getElementById('devPermManageStaff').checked      = perms ? perms.manage_staff       !== false : true;
    document.getElementById('devPermViewStudentsReg').checked  = perms ? perms.view_students      !== false : true;
    document.getElementById('devPermExportReportsReg').checked = perms ? perms.export_reports     !== false : true;
    const adEl = document.getElementById('devPermAssignDepartments');
    if (adEl) adEl.checked = perms ? perms.assign_departments !== false : true;
  }
}

// Read permissions from modal checkboxes for the given role
// Note: Department assignment is managed exclusively by the Head Registrar (admin-dashboard)
function readPermissionsFromModal(role) {
  if (role === 'teller') {
    return {
      handle_requests: true,
      view_students:   document.getElementById('devPermViewStudents').checked,
      export_reports:  document.getElementById('devPermExportReports').checked,
    };
  } else if (role === 'registrar') {
    const adEl = document.getElementById('devPermAssignDepartments');
    return {
      manage_requests:    document.getElementById('devPermManageRequests').checked,
      manage_staff:       document.getElementById('devPermManageStaff').checked,
      view_students:      document.getElementById('devPermViewStudentsReg').checked,
      export_reports:     document.getElementById('devPermExportReportsReg').checked,
      assign_departments: adEl ? adEl.checked : true,
    };
  }
  return {};
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

const pageNames = { home: 'Home', users: 'Users', students: 'Students', requests: 'Request Monitor', reports: 'Reports', support: 'Developer Support', settings: 'Settings' };

function navigateTo(pageId) {
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
  if (pageId === 'settings') loadSettings();
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
   LOAD SYSTEM DATA
════════════════════════════════ */
async function loadAll() {
  await Promise.all([loadRequests(), loadUsers(), loadStudents()]);
}

async function loadRequests() {
  const res  = await fetch(API + '/admin-get-requests.php', { credentials: 'include' });
  const data = await res.json();
  if (await handleAuthResponse(res, data)) return;
  if (!data.success) return;
  allRequests = data.requests;
  updateSystemStats();
  renderRecentTable();
  renderRequestsTable();
  updateReportsSummary();
}

async function loadUsers() {
  const res  = await fetch(API + '/admin-manage-users.php', { credentials: 'include', cache: 'no-store' });
  const data = await res.json();
  if (await handleAuthResponse(res, data)) return;
  if (!data.success) {
    if (res.status === 401 && typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Session Expired', text: 'Please refresh the page and log in again.', confirmButtonColor: '#DD0426' });
    return;
  }
  allUsers = data.users;
  updateRoleCounts();
  renderUsersTable();
}

function updateSystemStats() {
  const pending = allRequests.filter(r => r.status === 'pending').length;
  document.getElementById('sysRequests').textContent  = allRequests.length;
  document.getElementById('sysPending').textContent   = pending;
  document.getElementById('sysStudents').textContent  = allStudents.length;
}

function updateRoleCounts() {
  const counts = { developer: 0, registrar: 0, teller: 0 };
  allUsers.forEach(u => { if (counts[u.role] !== undefined && u.is_active == 1) counts[u.role]++; });
  document.getElementById('sysAdmins').textContent   = allUsers.filter(u => u.is_active == 1).length;
  document.getElementById('roleDevCount').textContent = counts.developer;
  document.getElementById('roleRegCount').textContent = counts.registrar;
  document.getElementById('roleTelCount').textContent = counts.teller;
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

function updateReportsSummary() {
  const counts = { pending: 0, processing: 0, ready: 0, claimed: 0 };
  allRequests.forEach(r => { if (counts[r.status] !== undefined) counts[r.status]++; });
  document.getElementById('rAll').textContent        = allRequests.length;
  document.getElementById('rPending').textContent    = counts.pending;
  document.getElementById('rProcessing').textContent = counts.processing;
  document.getElementById('rReady').textContent      = counts.ready;
  document.getElementById('rClaimed').textContent    = counts.claimed;
}

/* ════════════════════════════════
   REQUESTS TABLE
════════════════════════════════ */
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

function renderRequestsTable() {
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
      <td><span class="admin__status-badge ${r.status}">${r.status === 'claimed' ? '<i class="ri-lock-line"></i> Claimed' : capitalize(r.status)}</span></td>
      <td>
        <button class="req-detail__view-btn" onclick="viewRequestDetail(${r.id})" title="View Details"><i class="ri-eye-line"></i></button>
        ${r.notes ? `<button class="req-detail__view-btn notes" onclick="viewRequestDetail(${r.id}, true)" title="View notes"><i class="ri-sticky-note-line"></i></button>` : ''}
      </td>
    </tr>`;
  }).join('');
}

document.getElementById('searchInput').addEventListener('input', renderRequestsTable);
document.getElementById('filterStatus').addEventListener('change', renderRequestsTable);
document.getElementById('refreshBtn').addEventListener('click', loadAll);

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
    const res  = await fetch(API + '/admin-update-status.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
      body: JSON.stringify({ id, status: newStatus })
    });
    const data = await res.json();
    if (data.success) {
      if (r) r.status = newStatus;
      updateSystemStats();
      renderRecentTable();
      updateReportsSummary();
      renderRequestsTable(); // re-render so claimed rows show locked badge
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
   STUDENTS PAGE
════════════════════════════════ */
async function loadStudents() {
  const res  = await fetch(API + '/admin-get-students.php', { credentials: 'include' });
  const data = await res.json();
  if (await handleAuthResponse(res, data)) return;
  if (!data.success) return;
  allStudents = data.students;
  updateSystemStats();
  populateDevProgramFilter();
  renderDevStudentsTable();
}

function populateDevProgramFilter() {
  const sel      = document.getElementById('devStudentProgramFilter');
  const programs = [...new Set(allStudents.map(s => s.program).filter(Boolean))].sort();
  sel.innerHTML  = '<option value="">All Programs</option>' +
    programs.map(p => `<option value="${p}">${p}</option>`).join('');
}

function getFilteredDevStudents() {
  const search  = (document.getElementById('devStudentSearch').value || '').toLowerCase();
  const program = document.getElementById('devStudentProgramFilter').value;
  return allStudents.filter(s => {
    const matchProgram = !program || s.program === program;
    const matchSearch  = !search  || [s.names, s.surnames, s.student_number, s.email]
      .some(v => (v || '').toLowerCase().includes(search));
    return matchProgram && matchSearch;
  });
}

function renderDevStudentsTable() {
  const tbody = document.getElementById('devStudentsBody');
  const rows  = getFilteredDevStudents();
  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="10" class="admin__no-results">No students found.</td></tr>`;
    return;
  }
  tbody.innerHTML = rows.map((s, i) => {
    const date  = new Date(s.created_at).toLocaleDateString();
    const total = parseInt(s.total_requests) || 0;
    const active = String(s.is_active ?? 1) === '1';
    const statusBadge = active
      ? '<span class="dev__status-badge active" style="margin-right:.35rem;">Active</span>'
      : '<span class="dev__status-badge inactive" style="margin-right:.35rem;">Inactive</span>';
    return `<tr>
      <td>${i + 1}</td>
      <td>
        <div class="student-name">${s.names} ${s.surnames}</div>
        <div class="student-info">${s.student_number}</div>
      </td>
      <td><div class="student-info">${s.email}</div></td>
      <td><strong>${total}</strong></td>
      <td>${s.pending    || 0}</td>
      <td>${s.processing || 0}</td>
      <td>${s.ready      || 0}</td>
      <td>${s.claimed    || 0}</td>
      <td>${date}</td>
      <td>
        ${statusBadge}
        ${total > 0
          ? `<button class="dev__action-btn edit" onclick="viewDevStudentHistory(${s.id},'${escHtml(s.names + ' ' + s.surnames)}')" title="View History"><i class="ri-eye-line"></i></button>`
          : `<span style="color:#ccc;font-size:.75rem;margin-right:.35rem">No requests</span>`}
        ${active
          ? `<button class="dev__action-btn delete" onclick="manageDevStudent('deactivate', ${s.id})" title="Deactivate"><i class="ri-user-unfollow-line"></i></button>`
          : `<button class="dev__action-btn activate" onclick="manageDevStudent('reactivate', ${s.id})" title="Reactivate"><i class="ri-user-follow-line"></i></button>`}
        <button class="dev__action-btn cancel" onclick="manageDevStudent('delete', ${s.id})" title="Permanently Delete"><i class="ri-delete-bin-line"></i></button>
      </td>
    </tr>`;
  }).join('');
}

async function manageDevStudent(action, studentId) {
  const student = allStudents.find(s => s.id == studentId);
  if (!student) { Swal.fire({ icon: 'error', title: 'Error', text: 'Student not found. Please refresh.' }); return; }
  const name = `${student.names || ''} ${student.surnames || ''}`.trim();
  const confirmCode = generateConfirmCode();
  const isDelete = action === 'delete';
  const isDeactivate = action === 'deactivate';
  const title = isDelete ? 'Delete Student Record?' : isDeactivate ? 'Deactivate Student?' : 'Reactivate Student?';
  const explain = isDelete
    ? '<p>This will permanently delete the student account and all related requests.</p>'
    : isDeactivate
      ? '<p>This student will not be able to log in.</p>'
      : '<p>This student will be able to log in again.</p>';

  // For delete: require verification code. Deactivate/Reactivate: simple Yes/No only.
  if (isDelete) {
    const codeCheck = await Swal.fire({
      icon: 'warning',
      title,
      html: explain + '<p>Type <strong>' + confirmCode + '</strong> to continue.</p>',
      input: 'text',
      inputPlaceholder: 'Type the code above',
      showCancelButton: true,
      confirmButtonColor: '#DD0426',
      confirmButtonText: 'Continue',
      cancelButtonText: 'Cancel',
      inputValidator: (val) => (val?.trim().toUpperCase() !== confirmCode ? 'Type the generated code to confirm' : null)
    });
    if (!codeCheck.isConfirmed) return;
  }

  const final = await Swal.fire({
    icon: isDelete ? 'error' : 'question',
    title,
    html: `<p><strong>${escHtml(name)}</strong></p>` + explain,
    showCancelButton: true,
    confirmButtonColor: '#DD0426',
    confirmButtonText: isDelete ? 'Yes, Delete' : isDeactivate ? 'Yes, Deactivate' : 'Yes, Reactivate',
    cancelButtonText: 'No'
  });
  if (!final.isConfirmed) return;

  const res = await fetch(API + '/admin-manage-students.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ action, user_id: student.id })
  });
  const data = await res.json().catch(() => ({}));
  if (data.success) {
    await loadStudents();
    Swal.fire({ icon: 'success', title: 'Done', text: data.message || 'Updated', timer: 1500, showConfirmButton: false });
  } else {
    const isAuth = res.status === 401 || /not authenticated|session expired/i.test((data.message || '').toLowerCase());
    const msg = isAuth ? 'Session expired. Please refresh and log in again.' : (data.message || 'Request failed');
    Swal.fire({ icon: 'error', title: 'Error', text: msg });
  }
}

function viewDevStudentHistory(studentId, studentName) {
  const overlay = document.getElementById('devHistoryModalOverlay');
  document.getElementById('devHistoryModalTitle').textContent = studentName + ' — Request History';
  overlay.classList.add('open');

  const tbody   = document.getElementById('devHistoryBody');
  const student = allStudents.find(st => st.id == studentId);
  const reqs    = student
    ? allRequests.filter(r => r.student_number === student.student_number)
    : [];

  if (!reqs.length) {
    tbody.innerHTML = `<tr><td colspan="5" class="admin__no-results">No requests found.</td></tr>`;
    return;
  }
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

document.getElementById('devStudentSearch').addEventListener('input', renderDevStudentsTable);
document.getElementById('devStudentProgramFilter').addEventListener('change', renderDevStudentsTable);
document.getElementById('devRefreshStudentsBtn').addEventListener('click', loadStudents);

document.getElementById('devHistoryModalClose').addEventListener('click', () => document.getElementById('devHistoryModalOverlay').classList.remove('open'));
document.getElementById('devHistoryCloseBtn').addEventListener('click',   () => document.getElementById('devHistoryModalOverlay').classList.remove('open'));
document.getElementById('devHistoryModalOverlay').addEventListener('click', e => {
  if (e.target === document.getElementById('devHistoryModalOverlay'))
    document.getElementById('devHistoryModalOverlay').classList.remove('open');
});

/* ════════════════════════════════
   USERS TABLE
════════════════════════════════ */
function getFilteredUsers() {
  const search = document.getElementById('userSearch').value.toLowerCase();
  const role   = document.getElementById('userRoleFilter').value;
  return allUsers.filter(u => {
    const matchRole   = !role   || u.role === role;
    const matchSearch = !search || [u.name, u.username, u.email].some(v => (v||'').toLowerCase().includes(search));
    return matchRole && matchSearch;
  });
}

function buildPermTags(role, perms) {
  if (role === 'developer') return '<span class="perm-tag required">Full Access</span>';
  if (role === 'registrar') {
    const p = perms || {};
    return [
      p.manage_requests    !== false ? '<span class="perm-tag granted">Requests</span>' : '<span class="perm-tag denied">Requests</span>',
      p.manage_staff       !== false ? '<span class="perm-tag granted">Staff</span>'    : '<span class="perm-tag denied">Staff</span>',
      p.view_students      !== false ? '<span class="perm-tag granted">Students</span>' : '<span class="perm-tag denied">Students</span>',
      p.export_reports     !== false ? '<span class="perm-tag granted">Reports</span>'  : '<span class="perm-tag denied">Reports</span>',
      p.assign_departments !== false ? '<span class="perm-tag granted">Depts</span>'    : '<span class="perm-tag denied">Depts</span>',
    ].join('');
  }
  // teller
  const p = perms || {};
  return [
    '<span class="perm-tag required">Requests</span>',
    p.view_students  ? '<span class="perm-tag granted">Students</span>' : '<span class="perm-tag denied">Students</span>',
    p.export_reports ? '<span class="perm-tag granted">Reports</span>'  : '<span class="perm-tag denied">Reports</span>',
  ].join('');
}

function renderUsersTable() {
  const tbody = document.getElementById('usersBody');
  const users = getFilteredUsers();
  if (!users.length) { tbody.innerHTML = `<tr><td colspan="10" class="admin__no-results">No users found.</td></tr>`; return; }
  tbody.innerHTML = users.map((u, i) => {
    const date    = new Date(u.created_at).toLocaleDateString();
    const active  = u.is_active == 1;
    const pending = !active;
    const canEdit = u.role !== 'developer';
    const statusLabel = pending ? 'Pending Activation' : 'Active';
    const statusClass = pending ? 'pending'             : 'active';
    return `<tr>
      <td>${i + 1}</td>
      <td><strong>${u.name}</strong></td>
      <td><code style="font-size:.8rem;background:#f5f5f5;padding:.1rem .4rem;border-radius:.3rem">${u.username}</code></td>
      <td style="font-size:.82rem;color:#555">${u.email || '<span style="color:#ccc">—</span>'}</td>
      <td><span class="dev__role-badge ${u.role}"><i class="ri-${u.role === 'developer' ? 'code-box' : u.role === 'registrar' ? 'user-star' : 'user'}-line"></i>${capitalize(u.role)}</span></td>
      <td><span class="dev__status-badge ${statusClass}">${statusLabel}</span></td>
      <td><div class="perm-tags">${buildPermTags(u.role, u.permissions)}</div></td>
      <td>${u.created_by_name || '<span style="color:#ccc">—</span>'}</td>
      <td>${date}</td>
      <td style="display:flex;gap:.3rem;flex-wrap:wrap;align-items:center">
        ${canEdit ? `
          ${pending ? `
            <button class="dev__action-btn activate" onclick="activateUser(${u.id},'${escHtml(u.name)}')" title="Manually Activate"><i class="ri-user-follow-line"></i></button>
            ${u.activation_link ? `<button class="dev__action-btn copy-link" onclick="copyActivationLink('${escHtml(u.activation_link)}','${escHtml(u.name)}')" title="Copy Activation Link"><i class="ri-link"></i></button>` : ''}
          ` : ''}
          <button class="dev__action-btn edit" onclick="openEditModal(${u.id})" title="Edit"><i class="ri-edit-line"></i></button>
          ${active ? `<button class="dev__action-btn delete" onclick="deactivateUser(${u.id},'${escHtml(u.name)}')" title="Deactivate"><i class="ri-user-unfollow-line"></i></button>` : ''}
          <button class="dev__action-btn cancel" onclick="cancelUser(${u.id},'${escHtml(u.name)}')" title="Cancel &amp; Permanently Delete"><i class="ri-delete-bin-line"></i></button>
        ` : '<span style="color:#ccc;font-size:.75rem">Protected</span>'}
      </td>
    </tr>`;
  }).join('');
}

document.getElementById('userSearch').addEventListener('input', renderUsersTable);
document.getElementById('userRoleFilter').addEventListener('change', renderUsersTable);

/* ════════════════════════════════
   USER MODAL
════════════════════════════════ */
const modalOverlay  = document.getElementById('userModalOverlay');
const modalTitle    = document.getElementById('modalTitle');
const editUserId    = document.getElementById('editUserId');
const modalName     = document.getElementById('modalName');
const modalUsername = document.getElementById('modalUsername');
const modalEmail    = document.getElementById('modalEmail');
const modalRole     = document.getElementById('modalRole');
const modalPassword = document.getElementById('modalPassword');
const passwordField = document.getElementById('passwordField');
const emailField    = document.getElementById('emailField');
const emailHint     = document.getElementById('emailHint');

function openAddModal() {
  modalTitle.textContent = 'Add Account';
  editUserId.value    = '';
  modalName.value     = '';
  modalUsername.value = '';
  modalEmail.value    = '';
  modalPassword.value = '';
  modalRole.value     = 'teller';
  modalUsername.disabled = false;
  emailField.style.display    = '';
  emailHint.style.display     = '';
  passwordField.style.display = 'none';
  document.getElementById('modalSaveBtn').textContent = 'Create & Send Invite';
  updatePermissionsPanel('teller');
  loadPermissionsIntoModal('teller', null);
  modalOverlay.classList.add('open');
}

function openEditModal(id) {
  const user = allUsers.find(u => u.id == id);
  if (!user) return;
  modalTitle.textContent  = 'Edit Account';
  editUserId.value        = user.id;
  modalName.value         = user.name;
  modalUsername.value     = user.username;
  modalEmail.value        = user.email || '';
  modalPassword.value     = '';
  modalRole.value         = user.role;
  modalUsername.disabled  = true;
  emailField.style.display    = '';
  emailHint.style.display     = 'none';
  passwordField.style.display = '';
  document.getElementById('modalSaveBtn').textContent = 'Save Changes';
  updatePermissionsPanel(user.role);
  loadPermissionsIntoModal(user.role, user.permissions || null);
  modalOverlay.classList.add('open');
}

function closeModal() {
  modalOverlay.classList.remove('open');
}

document.getElementById('addUserBtn').addEventListener('click', openAddModal);
document.getElementById('modalClose').addEventListener('click', closeModal);
document.getElementById('modalCancelBtn').addEventListener('click', closeModal);
modalOverlay.addEventListener('click', e => { if (e.target === modalOverlay) closeModal(); });
modalRole.addEventListener('change', () => {
  updatePermissionsPanel(modalRole.value);
  loadPermissionsIntoModal(modalRole.value, null);
});

document.getElementById('modalSaveBtn').addEventListener('click', async () => {
  const id       = editUserId.value;
  const name     = modalName.value.trim();
  const username = modalUsername.value.trim();
  const email    = modalEmail.value.trim();
  const role     = modalRole.value;
  const password = modalPassword.value;

  if (!name || (!id && (!username || !email))) {
    Swal.fire({ icon: 'warning', title: 'Missing Fields', text: 'Name, username, and email are required.', confirmButtonColor: '#DD0426' });
    return;
  }
  if (!(await ensureSession())) return;

  const btn = document.getElementById('modalSaveBtn');
  btn.disabled = true;
  btn.textContent = 'Saving...';

  try {
    const permissions = (role !== 'developer') ? readPermissionsFromModal(role) : undefined;
    const method = id ? 'PUT' : 'POST';
    const body   = id
      ? { id: parseInt(id), name, email, role, password, permissions }
      : { username, name, email, role, permissions };

    const res  = await fetch(API + '/admin-manage-users.php', {
      method, headers: { 'Content-Type': 'application/json' }, credentials: 'include',
      body: JSON.stringify(body)
    });
    const data = await res.json();

    if (data.success) {
      closeModal();
      await loadUsers();
      const extra = (!id && data.email_sent) ? '<br><small style="color:#555">📧 Activation email sent to <strong>' + email + '</strong></small>' : '';
      Swal.fire({ icon: 'success', title: 'Success', html: data.message + extra, confirmButtonColor: '#DD0426' });
    } else {
      const isAuth = res.status === 401 || /not authenticated|session expired/i.test((data.message || '').toLowerCase());
      const msg = isAuth ? 'Your session may have expired. Please refresh the page, log in again, and try again.' : (data.message || 'Failed to save');
      Swal.fire({ icon: 'error', title: isAuth ? 'Session Expired' : 'Error', text: msg, confirmButtonColor: '#DD0426' });
    }
  } catch {
    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Please try again.', confirmButtonColor: '#DD0426' });
  }

  btn.disabled = false;
  btn.textContent = id ? 'Save Changes' : 'Create & Send Invite';
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
    // Fallback: show link in a prompt so user can copy manually
    Swal.fire({
      icon: 'info',
      title: 'Activation Link',
      html: `Copy and send this link to <strong>${name}</strong>:<br><br>
             <textarea style="width:100%;height:80px;font-size:.8rem;padding:.5rem;border-radius:.5rem;border:1px solid #ddd;resize:none">${link}</textarea>`,
      confirmButtonColor: '#DD0426',
    });
  });
}

async function activateUser(id, name) {
  const result = await Swal.fire({
    icon: 'question',
    title: 'Manually Activate Account?',
    html: `Activate <strong>${name}</strong> without email verification?<br><small style="color:#888">Use this when the activation email link is not accessible (e.g. local/offline setup).</small>`,
    showCancelButton: true,
    confirmButtonColor: '#DD0426',
    cancelButtonColor: '#aaa',
    confirmButtonText: 'Yes, Activate',
  });
  if (!result.isConfirmed) return;
  if (!(await ensureSession())) return;

  try {
    const res  = await fetch(API + '/admin-manage-users.php', {
      method: 'PATCH', headers: { 'Content-Type': 'application/json' }, credentials: 'include', cache: 'no-store',
      body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (data.success) {
      await loadUsers();
      Swal.fire({ icon: 'success', title: 'Activated!', text: data.message, timer: 2000, showConfirmButton: false });
    } else {
      const isAuth = res.status === 401 || /not authenticated|session expired/i.test((data.message || '').toLowerCase());
      const msg = isAuth ? 'Your session may have expired. Please refresh the page, log in again, and try again.' : (data.message || 'Failed to activate');
      Swal.fire({ icon: 'error', title: isAuth ? 'Session Expired' : 'Error', text: msg, confirmButtonColor: '#DD0426' });
    }
  } catch {
    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Please try again.', confirmButtonColor: '#DD0426' });
  }
}

async function ensureSession() {
  const r = await fetch(API + '/admin-check-auth.php?t=' + Date.now(), { credentials: 'include', cache: 'no-store' });
  const d = r.ok ? await r.json() : {};
  if (!d.success || d.role !== 'developer') {
    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Session Expired', text: 'Please refresh the page and log in again, then try again.', confirmButtonColor: '#DD0426' });
    return false;
  }
  return true;
}

async function deactivateUser(id, name) {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Deactivate Account?',
    html: `Are you sure you want to deactivate <strong>${name}</strong>?<br>They will no longer be able to log in.`,
    showCancelButton: true,
    confirmButtonColor: '#DD0426',
    cancelButtonColor: '#aaa',
    confirmButtonText: 'Yes, Deactivate',
  });
  if (!result.isConfirmed) return;
  if (!(await ensureSession())) return;

  try {
    const res  = await fetch(API + '/admin-manage-users.php', {
      method: 'DELETE', headers: { 'Content-Type': 'application/json' }, credentials: 'include', cache: 'no-store',
      body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (data.success) {
      await loadUsers();
      Swal.fire({ icon: 'success', title: 'Deactivated', text: data.message, timer: 2000, showConfirmButton: false });
    } else {
      const isAuth = res.status === 401 || /not authenticated|session expired/i.test((data.message || '').toLowerCase());
      const msg = isAuth ? 'Your session may have expired. Please refresh the page, log in again, and try again.' : (data.message || 'Failed to deactivate');
      Swal.fire({ icon: 'error', title: isAuth ? 'Session Expired' : 'Error', text: msg, confirmButtonColor: '#DD0426' });
    }
  } catch {
    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Please try again.', confirmButtonColor: '#DD0426' });
  }
}

async function cancelUser(id, name) {
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
  if (!(await ensureSession())) return;

  try {
    const res  = await fetch(API + '/admin-manage-users.php', {
      method: 'DELETE', headers: { 'Content-Type': 'application/json' }, credentials: 'include', cache: 'no-store',
      body: JSON.stringify({ id, permanent: true })
    });
    const data = await res.json();
    if (data.success) {
      await loadUsers();
      Swal.fire({ icon: 'success', title: 'Deleted', text: data.message, timer: 2000, showConfirmButton: false });
    } else {
      const isAuth = res.status === 401 || /not authenticated|session expired/i.test((data.message || '').toLowerCase());
      const msg = isAuth ? 'Your session may have expired. Please refresh the page, log in again, and try again.' : (data.message || 'Failed to delete');
      Swal.fire({ icon: 'error', title: isAuth ? 'Session Expired' : 'Error', text: msg, confirmButtonColor: '#DD0426' });
    }
  } catch {
    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Please try again.', confirmButtonColor: '#DD0426' });
  }
}

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

document.getElementById('exportCsvBtn').addEventListener('click', () => { const u = buildExportUrl('csv');   if (u) window.location.href = u; });
document.getElementById('exportExcelBtn').addEventListener('click', () => { const u = buildExportUrl('excel'); if (u) window.location.href = u; });
document.getElementById('clearDates').addEventListener('click', () => {
  document.getElementById('exportDateFrom').value = '';
  document.getElementById('exportDateTo').value   = '';
});

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

  const notesSection = document.getElementById('rdNotesSection');
  if (r.notes) {
    document.getElementById('rdNotes').textContent = r.notes;
    notesSection.style.display = '';
  } else {
    notesSection.style.display = 'none';
  }

  document.getElementById('rdTimeline').innerHTML = buildTimeline(r.status);

  const updateSection = document.getElementById('rdUpdateSection');
  updateSection.style.display = 'none';

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
      updateSystemStats();
      renderRecentTable();
      renderRequestsTable();
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
  window.location.href = 'developer-login.php';
});

/* ════════════════════════════════
   HELPERS
════════════════════════════════ */
function capitalize(str) { return str.charAt(0).toUpperCase() + str.slice(1); }
function escHtml(str)    { return str.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function setInitials(fullName) {
  const initials = fullName.trim().split(/\s+/).map(w => w[0].toUpperCase()).slice(0, 2).join('');
  ['sidebarAvatar', 'topbarAvatar'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.textContent = initials;
  });
}

/* ════════════════════════════════
   SETTINGS (maintenance, backup, restore)
════════════════════════════════ */
let maintenanceToggleUpdating = false;

function updateSettingsDataAvailability(maintenanceOn) {
  const backupCard = document.getElementById('settingsBackupCard');
  const resetCard = document.getElementById('settingsResetCard');
  if (backupCard) backupCard.classList.toggle('settings-disabled', !maintenanceOn);
  if (resetCard) resetCard.classList.toggle('settings-disabled', !maintenanceOn);
}

async function loadSettings() {
  let data = { success: false };
  try {
    const res = await fetch(API + '/admin-settings.php?t=' + Date.now(), { credentials: 'include', cache: 'no-store' });
    data = await res.json();
  } catch (e) { /* ignore */ }
  if (data.success) {
    const toggle = document.getElementById('maintenanceToggle');
    const status = document.getElementById('maintenanceStatus');
    maintenanceToggleUpdating = true;
    if (toggle) toggle.checked = !!data.maintenance_mode;
    if (status) status.textContent = data.maintenance_mode ? 'On' : 'Off';
    updateSettingsDataAvailability(!!data.maintenance_mode);
    maintenanceToggleUpdating = false;
  }
  await Promise.all([loadExportStatus(), loadAuditLog()]);
}

async function loadAuditLog() {
  const body = document.getElementById('auditLogBody');
  if (!body) return;
  const abort = new AbortController();
  const timeout = setTimeout(() => abort.abort(), 15000);
  try {
    const res = await fetch(API + '/admin-get-audit-logs.php?limit=50&t=' + Date.now(), { credentials: 'include', cache: 'no-store', signal: abort.signal });
    clearTimeout(timeout);
    const text = await res.text();
    const data = text ? JSON.parse(text) : {};
    if (!res.ok) {
      body.innerHTML = '<tr><td colspan="5" class="settings-export-empty">' + (res.status === 401 ? 'Session expired.' : 'Request failed.') + '</td></tr>';
      return;
    }
    if (!data.success || !data.logs || !data.logs.length) {
      body.innerHTML = '<tr><td colspan="5" class="settings-export-empty">No audit entries yet.</td></tr>';
      return;
    }
    const rows = data.logs.map(log => {
      const ts = log.created_at ? new Date(log.created_at).toLocaleString() : '-';
      const action = escHtml((log.action || '').replace(/_/g, ' '));
      const admin = escHtml(log.admin_name || (log.admin_id ? '#' + log.admin_id : '-'));
      const details = escHtml((log.details || '').toString().substring(0, 80)) + ((log.details || '').length > 80 ? '…' : '');
      const ip = escHtml(log.ip_address || '-');
      return `<tr><td>${ts}</td><td>${action}</td><td>${admin}</td><td>${details || '-'}</td><td>${ip}</td></tr>`;
    });
    body.innerHTML = rows.join('');
  } catch (e) {
    clearTimeout(timeout);
    const msg = e.name === 'AbortError' ? 'Request timed out. Click Refresh to try again.' : 'Failed to load audit log.';
    body.innerHTML = '<tr><td colspan="5" class="settings-export-empty">' + msg + '</td></tr>';
  }
}

let savingMaintenance = false;
async function saveMaintenance(enabled) {
  if (savingMaintenance) return;
  savingMaintenance = true;
  const toggle = document.getElementById('maintenanceToggle');
  const status = document.getElementById('maintenanceStatus');
  const res = await fetch(API + '/admin-settings.php?t=' + Date.now(), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'maintenance', enabled }),
    credentials: 'include',
    cache: 'no-store'
  });
  const data = await res.json();
  if (!data.success) {
    if (toggle) toggle.checked = !enabled;
    if (status) status.textContent = enabled ? 'Off' : 'On';
    const isAuthError = (res.status === 401) || /not authenticated|session expired/i.test((data.message || '').toLowerCase());
    if (isAuthError && typeof Swal !== 'undefined') {
      const r = await Swal.fire({
        icon: 'warning',
        title: 'Session Expired',
        text: 'Please refresh the page and try again.',
        showCancelButton: true,
        confirmButtonColor: '#DD0426',
        confirmButtonText: 'Yes, Refresh',
        cancelButtonText: 'No'
      });
      if (r.isConfirmed) location.reload();
    } else if (typeof Swal !== 'undefined') {
      Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to update' });
    }
    savingMaintenance = false;
    return;
  }
  maintenanceToggleUpdating = true;
  if (toggle) toggle.checked = !!data.maintenance_mode;
  if (status) status.textContent = data.maintenance_mode ? 'On' : 'Off';
  updateSettingsDataAvailability(!!data.maintenance_mode);
  maintenanceToggleUpdating = false;
  if (typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: 'Saved', text: 'Maintenance mode ' + (data.maintenance_mode ? 'enabled' : 'disabled'), timer: 1500, showConfirmButton: false });
  savingMaintenance = false;
  await loadSettings(); // Re-sync from server
}

async function loadExportStatus() {
  const body = document.getElementById('exportStatusBody');
  if (!body) return;
  try {
    const res = await fetch(API + '/admin-settings.php?action=backup_logs&limit=5', { credentials: 'include', cache: 'no-store' });
    const data = await res.json();
    if (!data.success || !data.logs || !data.logs.length) {
      body.innerHTML = '<tr><td colspan="5" class="settings-export-empty">No activity yet. Run a backup or restore.</td></tr>';
      return;
    }
    const rows = [];
    for (const log of data.logs) {
      const ts = log.created_at ? new Date(log.created_at).toLocaleString() : '-';
      const action = (log.action || '').charAt(0).toUpperCase() + (log.action || '').slice(1);
      const statusLabel = (log.status || '').charAt(0).toUpperCase() + (log.status || '').slice(1);
      const tables = log.table_status || [];
      if (tables.length > 0) {
        tables.forEach((t, i) => {
          const msgStr = (t.message || '').toString();
          const msg = msgStr ? ' (' + escHtml(msgStr.substring(0, 50)) + (msgStr.length > 50 ? '…' : '') + ')' : '';
          rows.push(`<tr><td>${i === 0 ? action : ''}</td><td>${i === 0 ? ts : ''}</td><td>${escHtml(t.table || '')}</td><td>${t.rows ?? '-'}</td><td class="status-${t.status || 'success'}">${escHtml(t.status || 'success')}${msg}</td></tr>`);
        });
      } else {
        rows.push(`<tr><td>${action}</td><td>${ts}</td><td>-</td><td>-</td><td class="status-${log.status || 'success'}">${statusLabel}${log.message ? ': ' + escHtml(log.message) : ''}</td></tr>`);
      }
    }
    body.innerHTML = rows.join('');
  } catch (e) {
    body.innerHTML = '<tr><td colspan="5" class="settings-export-empty">Failed to load activity log.</td></tr>';
  }
}

async function doBackup() {
  try {
    const res = await fetch(API + '/admin-settings.php?action=backup', { credentials: 'include' });
    const contentType = res.headers.get('Content-Type') || '';
    if (contentType.includes('application/json')) {
      const data = await res.json();
      if (!data.success && typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'error', title: 'Backup Failed', text: data.message || 'Could not create backup' });
      }
      return;
    }
    const blob = await res.blob();
    const disp = res.headers.get('Content-Disposition') || '';
    const m = disp.match(/filename="?([^";\n]+)"?/);
    const filename = m ? m[1].trim() : 'docu_request_backup_' + new Date().toISOString().slice(0,10) + '.zip';
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: 'Backup Complete', text: 'ZIP file downloaded', timer: 2000, showConfirmButton: false });
    await loadExportStatus();
  } catch (e) {
    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Backup Failed', text: e.message || 'Network error' });
  }
}

async function doRestore(file) {
  if (!file || !file.name.toLowerCase().endsWith('.zip')) {
    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Invalid File', text: 'Please select a .zip backup file (CSV format)' });
    return;
  }
  const mode = document.querySelector('input[name="restoreMode"]:checked')?.value || 'full';
  const isFull = mode === 'full';
  const confirm = await (typeof Swal !== 'undefined' ? Swal.fire({
    icon: 'warning',
    title: isFull ? 'Full Restore?' : 'Append Restore?',
    text: isFull ? 'This will replace ALL current data. This cannot be undone.' : 'This will merge backup data into existing tables. Duplicates will be skipped.',
    showCancelButton: true,
    confirmButtonColor: '#DD0426',
    confirmButtonText: 'Yes, Restore'
  }) : Promise.resolve({ isConfirmed: window.confirm('Restore database?') }));
  if (!confirm.isConfirmed) return;
  const authCheck = await fetch(API + '/admin-check-auth.php?t=' + Date.now(), { credentials: 'include', cache: 'no-store' });
  if (!authCheck.ok) {
    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Session Expired', text: 'Please refresh the page, log in again, then try the restore.' });
    else alert('Session expired. Please refresh and log in again.');
    return;
  }
  function buildRestoreForm() {
    const fd = new FormData();
    fd.append('action', 'restore');
    fd.append('restore_mode', mode);
    fd.append('file', file);
    return fd;
  }
  let res = await fetch(API + '/admin-settings.php', { method: 'POST', body: buildRestoreForm(), credentials: 'include' });
  if (res.status === 401) {
    await new Promise(r => setTimeout(r, 500));
    res = await fetch(API + '/admin-settings.php', { method: 'POST', body: buildRestoreForm(), credentials: 'include' });
  }
  let data = {};
  try {
    const text = await res.text();
    data = text ? JSON.parse(text) : {};
  } catch (e) { data = { success: false, message: 'Invalid response' }; }
  if (res.status === 401) {
    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Session Expired', text: 'Please refresh the page, log in again, then try the restore.' });
    else alert('Session expired. Please refresh and log in again.');
    return;
  }
  if (data.success) {
    await loadExportStatus();
    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: 'Restored', text: data.message }).then(() => location.reload());
    else { alert(data.message); location.reload(); }
  } else {
    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Restore Failed', text: data.message || 'Unknown error' });
    else alert(data.message || 'Restore failed');
  }
}

document.getElementById('maintenanceToggle')?.addEventListener('change', async (e) => {
  if (maintenanceToggleUpdating) return;
  const enabled = e.target.checked;
  const toggle = e.target;
  const status = document.getElementById('maintenanceStatus');
  let result;
  if (enabled) {
    const confirmCode = (() => { const c = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; return Array.from(crypto.getRandomValues(new Uint8Array(6))).map(n => c[n % c.length]).join(''); })();
    result = await (typeof Swal !== 'undefined' ? Swal.fire({
      icon: 'warning',
      title: 'Enable Maintenance Mode?',
      html: '<p>Registrar, Teller, and Students will not be able to log in.</p><p>Type <strong>' + confirmCode + '</strong> to confirm.</p>',
      input: 'text',
      inputPlaceholder: 'Type the code above',
      showCancelButton: true,
      confirmButtonColor: '#DD0426',
      confirmButtonText: 'Enable',
      cancelButtonText: 'Cancel',
      inputValidator: (val) => (val?.trim().toUpperCase() !== confirmCode ? 'Type the generated code to confirm' : null)
    }) : Promise.resolve({ isConfirmed: window.confirm('Enable maintenance mode?') }));
  } else {
    result = await (typeof Swal !== 'undefined' ? Swal.fire({
      icon: 'question',
      title: 'Disable Maintenance Mode?',
      text: 'Registrar, Teller, and Students will be able to log in again.',
      showCancelButton: true,
      confirmButtonColor: '#DD0426',
      confirmButtonText: 'Yes, Disable',
      cancelButtonText: 'No'
    }) : Promise.resolve({ isConfirmed: window.confirm('Disable maintenance mode?') }));
  }
  if (!result.isConfirmed) {
    maintenanceToggleUpdating = true;
    toggle.checked = !enabled;
    status.textContent = enabled ? 'Off' : 'On';
    maintenanceToggleUpdating = false;
    return;
  }
  maintenanceToggleUpdating = true;
  await saveMaintenance(enabled);
  maintenanceToggleUpdating = false;
});
document.getElementById('backupBtn')?.addEventListener('click', doBackup);
document.getElementById('restoreFile')?.addEventListener('change', e => { const f = e.target.files[0]; if (f) doRestore(f); e.target.value = ''; });
document.getElementById('refreshExportStatusBtn')?.addEventListener('click', loadExportStatus);
document.getElementById('refreshAuditBtn')?.addEventListener('click', loadAuditLog);

function generateConfirmCode() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  return Array.from(crypto.getRandomValues(new Uint8Array(6))).map(n => chars[n % chars.length]).join('');
}

async function doReset(mode) {
  const isFull = mode === 'full';
  const confirmCode = generateConfirmCode();
  const confirm = await (typeof Swal !== 'undefined' ? Swal.fire({
    icon: 'warning',
    title: isFull ? 'Full Reset?' : 'Preserve Users?',
    html: (isFull
      ? '<p>This will <strong>wipe all data</strong> and recreate the schema. Nothing will remain.</p>'
      : '<p>This will reset all tables but <strong>keep users and admins</strong>.</p>') +
      '<p>Type <strong>' + confirmCode + '</strong> to confirm.</p>',
    input: 'text',
    inputPlaceholder: 'Type the code above',
    showCancelButton: true,
    confirmButtonColor: '#DD0426',
    confirmButtonText: isFull ? 'Full Reset' : 'Reset (Keep Users)',
    cancelButtonText: 'Cancel',
    inputValidator: (val) => (val?.trim().toUpperCase() !== confirmCode ? 'Type the generated code to confirm' : null)
  }) : Promise.resolve({ isConfirmed: window.confirm('Reset database? This cannot be undone.') }));
  if (!confirm.isConfirmed) return;
  if (!(await ensureSession())) return;
  const res = await fetch(API + '/admin-settings.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'reset', mode }),
    credentials: 'include'
  });
  const data = await res.json();
  if (data.success) {
    await loadExportStatus();
    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: 'Reset Complete', text: data.message }).then(() => location.reload());
    else { alert(data.message); location.reload(); }
  } else {
    const isAuth = res.status === 401 || /not authenticated|session expired/i.test((data.message || '').toLowerCase());
    const msg = isAuth ? 'Your session may have expired. Please refresh the page, log in again, and try the reset.' : (data.message || 'Unknown error');
    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: isAuth ? 'Session Expired' : 'Reset Failed', text: msg });
    else alert(msg);
  }
}

document.getElementById('resetFullBtn')?.addEventListener('click', () => doReset('full'));
document.getElementById('resetPreserveBtn')?.addEventListener('click', () => doReset('preserve_users'));

setInterval(() => { loadAll(); loadChatList(); }, 30000);

let requestsPollId = null;
function startRequestsPoll() {
  if (requestsPollId) return;
  requestsPollId = setInterval(() => { loadRequests(); }, 2000);
}
function stopRequestsPoll() {
  if (requestsPollId) { clearInterval(requestsPollId); requestsPollId = null; }
}

(async () => {
  const name = await checkAuth();
  if (!name) return;
  document.getElementById('devName').textContent     = name;
  document.getElementById('welcomeName').textContent = name.split(' ')[0];
  setInitials(name);
  await Promise.all([loadAll(), loadChatList()]);
  loadAuditLog();
  startRequestsPoll();
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

const CHAT_CHANNEL = 'developer';

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
  await fetch(API + '/chat.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
    body: JSON.stringify({ action: 'send', chat_id: activeChatId, message: msg, as_admin: true, channel: CHAT_CHANNEL }) });
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

const _supportNavDev = document.querySelector('.sidebar__link[data-page="support"]');
if (_supportNavDev && !_supportNavDev._chatBound) {
  _supportNavDev.addEventListener('click', e => { e.preventDefault(); navigateTo('support'); loadChatList(); });
  _supportNavDev._chatBound = true;
}
