/* API from app-config.js */

async function checkAuth() {
  const opts = { credentials: 'include', cache: 'no-store' };
  let data = {};
  for (let attempt = 1; attempt <= 5; attempt++) {
    try {
      const res = await fetch(API + '/check-auth.php?t=' + Date.now(), opts);
      const text = await res.text();
      data = text ? JSON.parse(text) : {};
      if (data.success) return data.user;
    } catch (e) { data = {}; }
    if (attempt === 5) { window.location.href = 'index.php'; return null; }
    await new Promise(r => setTimeout(r, 600));
  }
  return data.user;
}

async function loadDocumentTypes() {
  const res = await fetch(API + '/get-document-types.php', { credentials: 'include' });
  const data = await res.json();
  if (!data.success) return;
  documentTypes = data.document_types;
  const select = document.getElementById('documentType');
  select.innerHTML = '<option value="">Select document...</option>';
  documentTypes.forEach(t => {
    const opt = document.createElement('option');
    opt.value = t.unavailable ? '' : t.id;
    opt.textContent = t.name + (t.description && !t.unavailable ? ' — ' + t.description : '') + (t.unavailable_reason ? ' — ' + t.unavailable_reason : '');
    if (t.unavailable) {
      opt.disabled = true;
      opt.dataset.docId = t.id;
    }
    select.appendChild(opt);
  });
}

let documentTypes = [];
let userDepartment = null; // Set when user has department locked from registration

async function loadMyRequests() {
  const res = await fetch(API + '/get-my-requests.php', { credentials: 'include' });
  const data = await res.json();
  const list = document.getElementById('requestsList');
  if (!data.success || !data.requests.length) {
    list.innerHTML = '<p class="dashboard__empty">No requests yet. Submit one above.</p>';
    return;
  }
  list.innerHTML = data.requests.map(r => {
    const date = new Date(r.requested_at).toLocaleDateString();
    const dept = r.department || '';
    const purp = r.purpose || '';
    const editable = r.status === 'pending';
    const dataAttrs = `data-id="${r.id}" data-doc-type="${r.document_type_id}" data-dept="${dept}" data-purp="${purp.replace(/"/g, '&quot;')}" data-notes="${(r.notes || '').replace(/"/g, '&quot;')}"`;
    return `
      <div class="dashboard__item" ${dataAttrs}>
        <div class="dashboard__item-info">
          <strong>${r.document_name}</strong>
          ${dept ? `<span class="dashboard__item-detail">Program: ${dept}</span>` : ''}
          ${purp ? `<span class="dashboard__item-detail">Purpose: ${purp}</span>` : ''}
          <span class="dashboard__item-detail">Requested ${date}</span>
        </div>
        <div class="dashboard__item-actions">
          ${editable ? `<button type="button" class="dashboard__btn-edit" data-edit="${r.id}"><i class="ri-edit-line"></i> Edit</button>` : ''}
          <span class="dashboard__item-badge ${r.status}">${r.status}</span>
        </div>
      </div>
    `;
  }).join('');
  list.querySelectorAll('[data-edit]').forEach(btn => {
    btn.addEventListener('click', () => openEditModal(parseInt(btn.dataset.edit)));
  });
}

function openEditModal(requestId) {
  const req = document.querySelector(`.dashboard__item[data-id="${requestId}"]`);
  if (!req) return;
  const docTypeId = req.dataset.docType || '';
  const dept = req.dataset.dept || '';
  const purp = req.dataset.purp || '';
  const notes = req.dataset.notes || '';
  const docTypesOpts = documentTypes.map(t => {
    const disabled = t.unavailable && t.id != docTypeId ? ' disabled' : '';
    const reason = t.unavailable && t.id != docTypeId && t.unavailable_reason ? ' — ' + t.unavailable_reason : '';
    return `<option value="${t.id}" ${t.id == docTypeId ? 'selected' : ''}${disabled}>${t.name}${reason}</option>`;
  }).join('');
  const showDeptField = !userDepartment;
  const deptFieldHtml = showDeptField ? `
        <div class="dashboard__field" style="margin-bottom:1rem;">
          <label>Program</label>
          <select id="editDept" class="dashboard__select" required>
            <option value="">Select program...</option>
            <optgroup label="Business, Entrepreneurship, and Management Department">
              <option value="Bachelor of Science in Accountancy (BSA)" ${dept==='Bachelor of Science in Accountancy (BSA)'?'selected':''}>BSA</option>
              <option value="Bachelor of Science in Entrepreneurship (BSE)" ${dept==='Bachelor of Science in Entrepreneurship (BSE)'?'selected':''}>BSE</option>
              <option value="Bachelor of Science in Marketing (BSM)" ${dept==='Bachelor of Science in Marketing (BSM)'?'selected':''}>BSM</option>
              <option value="Bachelor of Science in Office Administration (BSOA)" ${dept==='Bachelor of Science in Office Administration (BSOA)'?'selected':''}>BSOA</option>
            </optgroup>
            <optgroup label="Education Department">
              <option value="Bachelor of Secondary Education (BSEd) - Mathematics" ${dept==='Bachelor of Secondary Education (BSEd) - Mathematics'?'selected':''}>BSEd - Mathematics</option>
              <option value="Bachelor of Secondary Education (BSEd) - Science" ${dept==='Bachelor of Secondary Education (BSEd) - Science'?'selected':''}>BSEd - Science</option>
              <option value="Bachelor of Secondary Education (BSEd) - Technology Livelihood Education" ${dept==='Bachelor of Secondary Education (BSEd) - Technology Livelihood Education'?'selected':''}>BSEd - TLE</option>
              <option value="Bachelor of Physical Education (BPEd)" ${dept==='Bachelor of Physical Education (BPEd)'?'selected':''}>BPEd</option>
              <option value="Diploma in Teaching Secondary (DTS)" ${dept==='Diploma in Teaching Secondary (DTS)'?'selected':''}>DTS</option>
              <option value="BTVTEd - Food and Service Management" ${dept==='BTVTEd - Food and Service Management'?'selected':''}>BTVTEd - Food and Service</option>
              <option value="BTVTEd - Garments, Fashion, and Design" ${dept==='BTVTEd - Garments, Fashion, and Design'?'selected':''}>BTVTEd - Garments</option>
              <option value="BTLEd - Industrial Arts (IA)" ${dept==='BTLEd - Industrial Arts (IA)'?'selected':''}>BTLEd - IA</option>
              <option value="BTLEd - Home Economics (HE)" ${dept==='BTLEd - Home Economics (HE)'?'selected':''}>BTLEd - HE</option>
            </optgroup>
            <optgroup label="Engineering Department">
              <option value="Bachelor of Science in Civil Engineering (BSCE)" ${dept==='Bachelor of Science in Civil Engineering (BSCE)'?'selected':''}>BSCE</option>
              <option value="Bachelor of Science in Information Technology (BSIT)" ${dept==='Bachelor of Science in Information Technology (BSIT)'?'selected':''}>BSIT</option>
            </optgroup>
            <optgroup label="Technology Department">
              <option value="Bachelor of Science in Hospitality Management" ${dept==='Bachelor of Science in Hospitality Management'?'selected':''}>BS Hospitality Management</option>
              <option value="Bachelor of Science in Hotel and Restaurant Technology" ${dept==='Bachelor of Science in Hotel and Restaurant Technology'?'selected':''}>BS Hotel and Restaurant Tech</option>
              <option value="BS in Industrial Technology - Culinary Arts" ${dept==='BS in Industrial Technology - Culinary Arts'?'selected':''}>BS Industrial Tech - Culinary</option>
              <option value="BS in Industrial Technology - Electricity" ${dept==='BS in Industrial Technology - Electricity'?'selected':''}>BS Industrial Tech - Electricity</option>
              <option value="BS in Industrial Technology - Electronics" ${dept==='BS in Industrial Technology - Electronics'?'selected':''}>BS Industrial Tech - Electronics</option>
              <option value="BS in Mechanical Technology - Automotive" ${dept==='BS in Mechanical Technology - Automotive'?'selected':''}>BS Mechanical Tech - Automotive</option>
              <option value="BS in Mechanical Technology - Welding and Fabrication" ${dept==='BS in Mechanical Technology - Welding and Fabrication'?'selected':''}>BS Mechanical Tech - Welding</option>
              <option value="Bachelor of Industrial Technology - Electrical Technology" ${dept==='Bachelor of Industrial Technology - Electrical Technology'?'selected':''}>BIT - Electrical Technology</option>
            </optgroup>
          </select>
        </div>` : '';
  Swal.fire({
    title: 'Edit Request',
    html: `
      <form id="editForm">
        <div class="dashboard__field" style="margin-bottom:1rem;">
          <label>Document Type</label>
          <select id="editDocType" class="dashboard__select" required>
            <option value="">Select...</option>
            ${docTypesOpts}
          </select>
        </div>
        ${deptFieldHtml}
        <div class="dashboard__field" style="margin-bottom:1rem;">
          <label>Purpose</label>
          <input type="text" id="editPurpose" class="dashboard__input" value="${purp}" required>
        </div>
        <div class="dashboard__field">
          <label>Notes (optional)</label>
          <textarea id="editNotes" class="dashboard__textarea" rows="2">${notes}</textarea>
        </div>
      </form>
    `,
    showCancelButton: true,
    confirmButtonText: 'Save',
    preConfirm: () => {
      const payload = {
        document_type_id: parseInt(document.getElementById('editDocType').value),
        purpose: document.getElementById('editPurpose').value.trim(),
        notes: document.getElementById('editNotes').value.trim()
      };
      if (showDeptField) {
        const ed = document.getElementById('editDept');
        payload.department = ed ? ed.value : '';
      }
      return payload;
    }
  }).then(async (result) => {
    if (!result.isConfirmed) return;
    const payload = result.value;
    const res = await fetch(API + '/update-request.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id: requestId, ...payload })
    });
    const data = await res.json();
    if (data.success) {
      Swal.fire({ icon: 'success', title: 'Updated!', text: data.message });
      loadMyRequests();
    } else Swal.fire({ icon: 'error', title: 'Error', text: data.message });
  });
}

document.getElementById('requestForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = e.target.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.innerHTML = '<i class="ri-loader-4-line"></i> Submitting...';
  try {
    const payload = {
      document_type_id: parseInt(document.getElementById('documentType').value),
      purpose: document.getElementById('purpose').value.trim(),
      notes: document.getElementById('notes').value.trim()
    };
    if (!userDepartment) {
      const deptEl = document.getElementById('department');
      payload.department = deptEl ? deptEl.value?.trim() : '';
    }
    const res = await fetch(API + '/request-document.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      Swal.fire({ icon: 'success', title: 'Request submitted!', text: data.message });
      document.getElementById('requestForm').reset();
      loadMyRequests();
    } else {
      const isDuplicate = (data.message || '').toLowerCase().includes('already requested');
      Swal.fire({ icon: 'error', title: isDuplicate ? 'Invalid Request' : 'Error', text: data.message });
    }
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Connection error', text: 'Please try again.' });
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="ri-send-plane-fill"></i> Submit Request';
});

document.getElementById('notifBell')?.addEventListener('click', (e) => {
  e.stopPropagation();
  const dd = document.getElementById('notifDropdown');
  if (dd) dd.classList.toggle('open');
});
document.getElementById('notifMarkAll')?.addEventListener('click', async () => {
  await markAllNotificationsRead();
});
document.addEventListener('click', () => {
  const dd = document.getElementById('notifDropdown');
  if (dd) dd.classList.remove('open');
});
document.getElementById('notifDropdown')?.addEventListener('click', e => e.stopPropagation());

document.getElementById('logoutBtn')?.addEventListener('click', async () => {
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
  await fetch(API + '/logout.php', { credentials: 'include' });
  window.location.href = 'index.php';
});

/* ════════════════════════════════
   NOTIFICATIONS (Ready / Claimed)
════════════════════════════════ */
let _notifications = [];
let _notifPollId = null;
let _lastNotifUnreadCount = -1;

async function loadNotifications(showModalOnNew = false) {
  try {
    const res = await fetch(API + '/get-notifications.php?t=' + Date.now(), { credentials: 'include', cache: 'no-store' });
    const data = await res.json();
    if (data.success && Array.isArray(data.notifications)) {
      const unread = data.unread_count || 0;
      const prevUnread = _lastNotifUnreadCount;
      _lastNotifUnreadCount = unread;
      _notifications = data.notifications;
      renderNotificationsDropdown();
      updateNotifBadge(unread);
      if (unread > 0 && (prevUnread < 0 || unread > prevUnread) && showModalOnNew) {
        await showAlertModalForUnread();
      }
      return data;
    }
  } catch (e) {}
  return { unread_count: 0 };
}

function updateNotifBadge(n) {
  const badge = document.getElementById('notifBadge');
  if (!badge) return;
  badge.textContent = n > 99 ? '99+' : n;
  badge.classList.toggle('hidden', n <= 0);
}

function fmtNotifTime(createdAt) {
  const d = new Date(createdAt);
  const now = new Date();
  const diff = (now - d) / 1000;
  if (diff < 60) return 'Just now';
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  return d.toLocaleDateString();
}

function renderNotificationsDropdown() {
  const list = document.getElementById('notifList');
  const markAll = document.getElementById('notifMarkAll');
  if (!list) return;
  const unread = _notifications.filter(n => !n.is_read);
  if (markAll) markAll.style.display = unread.length ? '' : 'none';
  if (!_notifications.length) {
    list.innerHTML = '<p class="notif-empty">No notifications yet.</p>';
    return;
  }
  list.innerHTML = _notifications.map(n => {
    const icon = n.status === 'claimed' ? 'ri-checkbox-circle-fill' : 'ri-file-check-line';
    const iconClass = n.status;
    const title = n.status === 'ready' ? 'Document Ready to Claim' : 'Document Claimed';
    const cls = n.is_read ? 'notif-item' : 'notif-item unread';
    const docName = escapeHtml(n.document_name || '');
    return `<div class="${cls}" data-id="${n.id}" data-read="${n.is_read ? 1 : 0}">
      <div class="notif-item__icon ${iconClass}"><i class="${icon}"></i></div>
      <div class="notif-item__body">
        <div class="notif-item__title">${title}</div>
        <div class="notif-item__doc">${docName}</div>
        <div class="notif-item__time">${fmtNotifTime(n.created_at)}</div>
      </div>
    </div>`;
  }).join('');
  list.querySelectorAll('.notif-item').forEach(el => {
    el.addEventListener('click', () => {
      const id = parseInt(el.dataset.id, 10);
      const n = _notifications.find(x => x.id === id);
      if (n) showNotificationModal(n);
      if (!el.dataset.read || el.dataset.read === '0') markNotificationRead([id]);
    });
  });
}

function showNotificationModal(n) {
  const isReady = n.status === 'ready';
  const title = isReady ? 'Document Ready to Claim' : 'Document Claimed';
  const icon = isReady ? 'info' : 'success';
  let body = `<p><strong>${escapeHtml(n.document_name)}</strong></p>`;
  if (isReady) {
    body += `<p>Your document is now ready for claiming at the Registrar's Office. Please bring your school ID.</p>`;
  } else {
    body += `<p>This document has been successfully claimed. This transaction is now complete.</p>`;
  }
  if (n.created_at) {
    const dt = new Date(n.created_at);
    body += `<p style="font-size:0.85rem;color:#888;margin-top:1rem;">${dt.toLocaleString()}</p>`;
  }
  if (typeof Swal !== 'undefined') {
    Swal.fire({
      icon,
      title,
      html: body,
      confirmButtonColor: '#DD0426',
      confirmButtonText: 'OK'
    });
  }
}

function escapeHtml(s) {
  const div = document.createElement('div');
  div.textContent = s;
  return div.innerHTML;
}

async function markNotificationRead(ids) {
  try {
    await fetch(API + '/mark-notification-read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ ids })
    });
  } catch (e) {}
  await loadNotifications();
}

async function markAllNotificationsRead() {
  try {
    await fetch(API + '/mark-notification-read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ mark_all: true })
    });
  } catch (e) {}
  await loadNotifications();
}

async function showAlertModalForUnread() {
  const unread = _notifications.filter(n => !n.is_read && (n.status === 'ready' || n.status === 'claimed'));
  if (unread.length === 0) return;
  const readyCount = unread.filter(n => n.status === 'ready').length;
  const claimedCount = unread.filter(n => n.status === 'claimed').length;
  let msg = '';
  if (readyCount && claimedCount) {
    msg = `You have ${readyCount} document(s) ready to claim and ${claimedCount} document(s) that have been claimed.`;
  } else if (readyCount) {
    msg = `You have ${readyCount} document(s) ready to claim at the Registrar's Office!`;
  } else {
    msg = `You have ${claimedCount} document(s) that have been successfully claimed.`;
  }
  if (typeof Swal !== 'undefined') {
    await Swal.fire({
      icon: 'info',
      title: 'Document Status Update',
      html: `<p>${msg}</p><p style="font-size:0.9rem;color:#666;">Check the notification bell for details.</p>`,
      confirmButtonColor: '#DD0426',
      confirmButtonText: 'OK'
    });
  }
}

function setInitials(fullName) {
  const initials = fullName.trim().split(/\s+/).map(w => w[0].toUpperCase()).slice(0, 2).join('');
  const avatar = document.querySelector('.admin__avatar');
  if (avatar) { avatar.textContent = initials; avatar.style.fontSize = '.85rem'; avatar.style.fontWeight = '700'; }
}

/* ════════════════════════════════
   SUPPORT CHAT WIDGET
════════════════════════════════ */
let _chatIds      = { registrar: null, developer: null };
let _currentChannel = 'registrar';
let _chatOpen     = false;
let _chatPollTimer= null;

const CHANNEL_LABELS = {
  registrar: { title: 'Document Support', subtitle: 'Registrar — Document requests' },
  developer: { title: 'Developer Support', subtitle: 'Bugs, feedback, questions' }
};

function getChatId() { return _chatIds[_currentChannel]; }
function setChatId(id, channel) { _chatIds[channel || _currentChannel] = id; }

function handleChatAuthFailure() {
  if (_chatPollTimer) { clearInterval(_chatPollTimer); _chatPollTimer = null; }
  _chatOpen = false;
  const panel = document.getElementById('chatPanel');
  const fab = document.getElementById('chatFab');
  if (panel) panel.classList.remove('open');
  if (fab) fab.classList.remove('hidden');
  window.location.href = 'index.php';
}

function updateChatPanelHeader() {
  const lbl = CHANNEL_LABELS[_currentChannel] || CHANNEL_LABELS.registrar;
  const t = document.getElementById('chatPanelTitle');
  const s = document.getElementById('chatPanelSubtitle');
  if (t) t.textContent = lbl.title;
  if (s) s.textContent = lbl.subtitle;
  document.querySelectorAll('.chat-channel-btn').forEach(btn => {
    btn.classList.toggle('active', (btn.dataset.channel || '') === _currentChannel);
  });
  const footer = document.getElementById('chatPanelContactFooter');
  if (footer && _currentChannel === 'developer') {
    fetch(API + '/dev-contact.php', { cache: 'no-store' }).then(r => r.json()).then(d => {
      if (d.success && d.email) { footer.innerHTML = 'Or email: <a href="mailto:' + d.email + '" style="color:#fff;text-decoration:underline">' + d.email + '</a>'; footer.style.display = ''; }
      else footer.style.display = 'none';
    }).catch(() => { footer.style.display = 'none'; });
  } else if (footer) footer.style.display = 'none';
}

function escH(str) {
  return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function fmtChatTime(dt) {
  return new Date(dt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

async function chatInit(channel) {
  channel = channel || _currentChannel;
  const res  = await fetch(API + '/chat.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ action: 'init', channel: channel })
  });
  if (res.status === 401 || res.status === 403) { handleChatAuthFailure(); return null; }
  const data = await res.json();
  if (!data.success) return null;
  setChatId(data.chat_id, channel);
  updateChatFabBadge(await chatGetUnread());
  return data;
}

async function chatGetUnread() {
  try {
    const res = await fetch(API + '/chat.php?action=unread', { credentials: 'include' });
    if (res.status === 401) return 0;
    const data = await res.json();
    return data.success ? (data.unread || 0) : 0;
  } catch (e) { return 0; }
}

function updateChatFabBadge(n) {
  const badge = document.getElementById('chatFabBadge');
  if (!badge) return;
  badge.textContent = n;
  badge.classList.toggle('hidden', n <= 0);
}

async function chatLoadMessages(scroll = true) {
  const cid = getChatId();
  const channel = _currentChannel;
  if (!cid) return;
  const res  = await fetch(`${API}/chat.php?action=messages&chat_id=${cid}&channel=${channel}&_=${Date.now()}`, { credentials: 'include', cache: 'no-store' });
  if (res.status === 401 || res.status === 403) { handleChatAuthFailure(); return; }
  const data = await res.json();
  if (!data.success) return;
  // Only render if we're still on this channel (prevents race when switching quickly)
  if (_currentChannel !== channel) return;
  renderChatMessages(data.messages, scroll);
  updateChatFabBadge(0);
}

function renderChatMessages(messages) {
  const wrap = document.getElementById('chatPanelMessages');
  if (!messages.length) {
    wrap.innerHTML = '<div class="chat-msg-empty"><i class="ri-chat-3-line"></i><p>Send us a message and we\'ll get back to you shortly.</p></div>';
    return;
  }
  wrap.innerHTML = messages.map(m => {
    const isMe = (m.sender_type || '').toLowerCase() === 'student';
    const side = isMe ? 'out' : 'in';
    const name = (m.sender_name || '').toString().trim() || (isMe ? 'You' : 'Support');
    const initials = name.split(/\s+/).map(w => (w[0]||'').toUpperCase()).slice(0,2).join('') || '?';
    return `<div class="chat-msg chat-msg--${side}">
      ${side === 'in' ? `<div class="chat-msg__avatar">${initials}</div>` : ''}
      <div class="chat-msg__bubble">
        ${side === 'in' ? `<span class="chat-msg__sender">${escH(name)}</span>` : ''}
        <p>${escH(m.message || '')}</p>
        <span class="chat-msg__time">${fmtChatTime(m.created_at)}</span>
      </div>
    </div>`;
  }).join('');
  wrap.scrollTop = wrap.scrollHeight;
}

async function chatSend() {
  const input = document.getElementById('chatPanelInput');
  const msg   = input.value.trim();
  const cid   = getChatId();
  if (!msg || !cid) return;
  input.value = '';
  const res  = await fetch(API + '/chat.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ action: 'send', chat_id: cid, message: msg })
  });
  if (res.status === 401 || res.status === 403) { handleChatAuthFailure(); return; }
  const data = await res.json();
  if (data.success) await chatLoadMessages();
}

async function switchChannel(channel) {
  if (channel === _currentChannel) return;
  _currentChannel = channel;
  updateChatPanelHeader();
  document.getElementById('chatPanelInput').value = '';
  // Clear messages immediately so we never show the wrong channel's history
  const wrap = document.getElementById('chatPanelMessages');
  if (wrap) wrap.innerHTML = '<div class="chat-msg-empty"><p>Loading…</p></div>';
  if (!getChatId()) await chatInit(_currentChannel);
  await chatLoadMessages();
}

async function openChatPanel() {
  _chatOpen = true;
  updateChatPanelHeader();
  document.getElementById('chatPanel').classList.add('open');
  document.getElementById('chatFab').classList.add('hidden');
  if (!getChatId()) await chatInit(_currentChannel);
  await chatLoadMessages();
  if (_chatPollTimer) clearInterval(_chatPollTimer);
  _chatPollTimer = setInterval(() => { if (_chatOpen) chatLoadMessages(false); }, 5000);
}

function closeChatPanel() {
  _chatOpen = false;
  document.getElementById('chatPanel').classList.remove('open');
  document.getElementById('chatFab').classList.remove('hidden');
  if (_chatPollTimer) clearInterval(_chatPollTimer);
}

document.getElementById('chatFab').addEventListener('click', async () => {
  openChatPanel();
});

document.querySelectorAll('.chat-channel-btn').forEach(btn => {
  btn.addEventListener('click', () => switchChannel(btn.dataset.channel || 'registrar'));
});

document.getElementById('chatPanelClose').addEventListener('click', closeChatPanel);
document.getElementById('chatPanelSend').addEventListener('click', chatSend);
document.getElementById('chatPanelInput').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); chatSend(); }
});

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
      await fetch(API + '/logout.php', { credentials: 'include' });
      window.location.href = 'index.php';
    }
  } catch (e) { /* ignore */ }
}

function setupDepartmentField(dept) {
  const select = document.getElementById('department');
  const hint = document.getElementById('programHint');
  if (!select || !dept) return;
  userDepartment = dept;
  if (hint) hint.style.display = 'none';
  select.value = dept;
  select.disabled = true;
  select.setAttribute('aria-readonly', 'true');
  select.setAttribute('title', 'Program is fixed and cannot be changed');
  select.closest('.dashboard__field')?.classList.add('dashboard__field--disabled');
}

(async () => {
  const user = await checkAuth();
  if (user) {
    const fullName = (user.names + ' ' + user.surnames).trim();
    document.getElementById('userName').textContent = user.names;
    setInitials(fullName);
    if (user.department) {
      setupDepartmentField(user.department);
    } else {
      const h = document.getElementById('programHint');
      if (h) h.style.display = 'block';
    }
    maintenancePollId = setInterval(checkMaintenanceAndForceLogout, 5000);
    await loadDocumentTypes();
    await loadMyRequests();
    const notifData = await loadNotifications(false);
    if (notifData.unread_count > 0) await showAlertModalForUnread();
    setTimeout(() => { loadNotifications(true); loadMyRequests(); }, 3000);
    _notifPollId = setInterval(() => { loadNotifications(true); loadMyRequests(); }, 15000);
    // Initialise both channels so each has its own chat_id (keeps Document/Developer separate)
    await chatInit('registrar');
    await chatInit('developer');
  }
})();
