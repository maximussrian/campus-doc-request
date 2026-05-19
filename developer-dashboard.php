<?php require_once __DIR__ . '/api/guard_developer.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="icon" type="image/png" href="assets/img/Evsu_Logo.png">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css">
   <link rel="stylesheet" href="assets/css/styles.css?v=3">
   <link rel="stylesheet" href="assets/css/admin.css?v=14">
   <link rel="stylesheet" href="assets/css/developer.css?v=8">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
   <title>Developer Dashboard - EVSU</title>
</head>
<body class="admin-body">

   <!-- ═══════════ SIDEBAR ═══════════ -->
   <aside class="sidebar" id="sidebar">
      <div class="sidebar__top">
         <img src="assets/img/Evsu_Logo.png" alt="EVSU" class="sidebar__logo">
         <div class="sidebar__brand">
            <span class="sidebar__brand-name">EVSU</span>
            <span class="sidebar__brand-sub">Developer Portal</span>
         </div>
         <button class="sidebar__toggle" id="sidebarToggle"><i class="ri-menu-fold-line"></i></button>
      </div>

      <nav class="sidebar__nav">
         <a href="#" class="sidebar__link active" data-page="home" data-label="Home">
            <i class="ri-home-5-line"></i><span>Home</span>
         </a>
         <a href="#" class="sidebar__link" data-page="users" data-label="Users">
            <i class="ri-team-line"></i><span>Users</span>
            <span class="sidebar__badge" id="pendingBadge" style="display:none">0</span>
         </a>
         <a href="#" class="sidebar__link" data-page="students" data-label="Students">
            <i class="ri-graduation-cap-line"></i><span>Students</span>
         </a>
         <a href="#" class="sidebar__link" data-page="requests" data-label="Request Monitor">
            <i class="ri-eye-line"></i><span>Request Monitor</span>
         </a>
         <a href="#" class="sidebar__link" data-page="reports" data-label="Reports">
            <i class="ri-bar-chart-2-line"></i><span>Reports</span>
         </a>
         <a href="#" class="sidebar__link" data-page="support" data-label="Developer Support">
            <i class="ri-code-box-line"></i><span>Developer Support</span>
            <span class="sidebar__badge hidden" id="supportBadge">0</span>
         </a>
         <a href="#" class="sidebar__link" data-page="settings" data-label="Settings">
            <i class="ri-settings-3-line"></i><span>Settings</span>
         </a>
      </nav>

      <div class="sidebar__bottom">
         <div class="sidebar__profile">
            <div class="sidebar__avatar" id="sidebarAvatar">?</div>
            <div class="sidebar__profile-info">
               <span class="sidebar__profile-name" id="devName">Loading...</span>
               <span class="sidebar__profile-role">Developer</span>
            </div>
         </div>
         <button class="sidebar__logout" id="logoutBtn" data-label="Logout">
            <i class="ri-logout-box-line"></i><span>Logout</span>
         </button>
      </div>
   </aside>

   <!-- ═══════════ MAIN CONTENT ═══════════ -->
   <div class="admin-content" id="adminContent">

      <!-- Mobile topbar -->
      <header class="admin-topbar">
         <button class="admin-topbar__menu" id="mobileMenuBtn"><i class="ri-menu-line"></i></button>
         <span class="admin-topbar__title" id="pageTitle">Home</span>
         <div class="admin-topbar__right">
            <div class="sidebar__avatar sm" id="topbarAvatar">?</div>
         </div>
      </header>

      <!-- ─── PAGE: HOME ─── -->
      <section class="admin-page active" id="page-home">
         <div class="admin-page__header">
            <h1>System Overview</h1>
            <p>Welcome, <span id="welcomeName">Developer</span>. Here's a real-time snapshot of the system.</p>
         </div>

         <!-- System stat cards -->
         <div class="dev__system-stats">
            <div class="dev__sys-card users">
               <div class="dev__sys-icon"><i class="ri-graduation-cap-line"></i></div>
               <div class="dev__sys-body">
                  <span class="dev__sys-num" id="sysStudents">0</span>
                  <span class="dev__sys-label">Registered Students</span>
               </div>
            </div>
            <div class="dev__sys-card admins">
               <div class="dev__sys-icon"><i class="ri-shield-user-line"></i></div>
               <div class="dev__sys-body">
                  <span class="dev__sys-num" id="sysAdmins">0</span>
                  <span class="dev__sys-label">Admin Accounts</span>
               </div>
            </div>
            <div class="dev__sys-card requests">
               <div class="dev__sys-icon"><i class="ri-file-list-line"></i></div>
               <div class="dev__sys-body">
                  <span class="dev__sys-num" id="sysRequests">0</span>
                  <span class="dev__sys-label">Total Requests</span>
               </div>
            </div>
            <div class="dev__sys-card pending">
               <div class="dev__sys-icon"><i class="ri-time-line"></i></div>
               <div class="dev__sys-body">
                  <span class="dev__sys-num" id="sysPending">0</span>
                  <span class="dev__sys-label">Pending Requests</span>
               </div>
            </div>
         </div>

         <!-- Admin accounts summary -->
         <div class="admin-card">
            <div class="admin-card__header">
               <h2><i class="ri-team-line"></i> Admin Accounts by Role</h2>
               <a href="#" class="admin-card__link" data-page="users">Manage Users <i class="ri-arrow-right-line"></i></a>
            </div>
            <div class="dev__role-summary">
               <div class="dev__role-item developer">
                  <i class="ri-code-box-line"></i>
                  <span class="dev__role-count" id="roleDevCount">0</span>
                  <span class="dev__role-label">Developer</span>
               </div>
               <div class="dev__role-item registrar">
                  <i class="ri-user-star-line"></i>
                  <span class="dev__role-count" id="roleRegCount">0</span>
                  <span class="dev__role-label">Registrar</span>
               </div>
               <div class="dev__role-item teller">
                  <i class="ri-user-line"></i>
                  <span class="dev__role-count" id="roleTelCount">0</span>
                  <span class="dev__role-label">Teller</span>
               </div>
            </div>
         </div>

         <!-- Recent requests -->
         <div class="admin-card">
            <div class="admin-card__header">
               <h2><i class="ri-history-line"></i> Recent Requests</h2>
               <a href="#" class="admin-card__link" data-page="requests">View All <i class="ri-arrow-right-line"></i></a>
            </div>
            <div class="admin__table-wrap">
               <table class="admin__table" id="recentTable">
                  <thead><tr><th>Student</th><th>Document</th><th>Date</th><th>Status</th></tr></thead>
                  <tbody id="recentBody"><tr><td colspan="4" style="text-align:center;padding:1.5rem;">Loading...</td></tr></tbody>
               </table>
            </div>
         </div>
      </section>

      <!-- ─── PAGE: USERS ─── -->
      <section class="admin-page" id="page-users">
         <div class="admin-page__header">
            <h1>User Management</h1>
            <p>Create and manage registrar and teller accounts.</p>
         </div>

         <div class="admin-card">
            <div class="admin__toolbar">
               <input type="text" id="userSearch" placeholder="Search by name or username..." class="admin__search">
               <select id="userRoleFilter" class="admin__filter-select">
                  <option value="">All Roles</option>
                  <option value="registrar">Registrar</option>
                  <option value="teller">Teller</option>
               </select>
               <button id="addUserBtn" class="admin__btn-primary">
                  <i class="ri-user-add-line"></i> Add Account
               </button>
            </div>

            <div class="admin__table-wrap">
               <table class="admin__table">
                  <thead>
                     <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Permissions</th>
                        <th>Created By</th>
                        <th>Date Added</th>
                        <th>Actions</th>
                     </tr>
                  </thead>
                  <tbody id="usersBody">
                     <tr><td colspan="10" style="text-align:center;padding:2rem;">Loading...</td></tr>
                  </tbody>
               </table>
            </div>
         </div>
      </section>

      <!-- ─── PAGE: REQUESTS (Monitor only — registrar handles) ─── -->
      <section class="admin-page" id="page-requests">
         <div class="admin-page__header">
            <h1>Request Monitor</h1>
            <p>View-only. Document requests are handled by Registrar.</p>
         </div>
         <div class="admin-card">
            <div class="admin__toolbar">
               <input type="text" id="searchInput" placeholder="Search by name, student no., document..." class="admin__search">
               <select id="filterStatus" class="admin__filter-select">
                  <option value="">All Status</option>
                  <option value="pending">Pending</option>
                  <option value="processing">Processing</option>
                  <option value="ready">Ready</option>
                  <option value="claimed">Claimed</option>
               </select>
               <button id="refreshBtn" class="admin__btn-icon"><i class="ri-refresh-line"></i> Refresh</button>
            </div>
            <div class="admin__table-wrap">
               <table class="admin__table">
                  <thead>
                     <tr>
                        <th>#</th><th>Student</th><th>Document</th><th>Program</th>
                        <th>Purpose</th><th>Date</th><th>Status</th><th>Notes</th>
                     </tr>
                  </thead>
                  <tbody id="requestsBody"><tr><td colspan="8" style="text-align:center;padding:2rem;">Loading...</td></tr></tbody>
               </table>
            </div>
         </div>
      </section>

      <!-- ─── PAGE: REPORTS ─── -->
      <section class="admin-page" id="page-reports">
         <div class="admin-page__header">
            <h1>Reports &amp; Export</h1>
            <p>Export document request records to CSV or Excel.</p>
         </div>

         <div class="admin-card reports-card">
            <h2 class="reports-card__title"><i class="ri-download-2-line"></i> Export Records</h2>
            <div class="reports__filters">
               <div class="reports__filter-group">
                  <label>Status Filter</label>
                  <select id="exportStatus" class="admin__filter-select">
                     <option value="">All Status</option>
                     <option value="pending">Pending</option>
                     <option value="processing">Processing</option>
                     <option value="ready">Ready</option>
                     <option value="claimed">Claimed</option>
                  </select>
               </div>
               <div class="reports__filter-group">
                  <label>Date From</label>
                  <input type="date" id="exportDateFrom" class="admin__date-input">
               </div>
               <div class="reports__filter-group">
                  <label>Date To</label>
                  <input type="date" id="exportDateTo" class="admin__date-input">
               </div>
               <button id="clearDates" class="admin__btn-ghost"><i class="ri-close-line"></i> Clear Dates</button>
            </div>
            <div class="reports__export-btns">
               <button id="exportCsvBtn" class="reports__btn csv">
                  <i class="ri-file-text-line"></i>
                  <span><strong>Export as CSV</strong><small>Compatible with all spreadsheet apps</small></span>
               </button>
               <button id="exportExcelBtn" class="reports__btn excel">
                  <i class="ri-file-excel-2-line"></i>
                  <span><strong>Export as Excel</strong><small>Formatted .xls with colored status rows</small></span>
               </button>
            </div>
         </div>

         <div class="admin-card">
            <h2 class="reports-card__title"><i class="ri-pie-chart-line"></i> Summary</h2>
            <div class="reports__summary">
               <div class="reports__summary-item all"><span id="rAll">0</span><label>Total</label></div>
               <div class="reports__summary-item pending"><span id="rPending">0</span><label>Pending</label></div>
               <div class="reports__summary-item processing"><span id="rProcessing">0</span><label>Processing</label></div>
               <div class="reports__summary-item ready"><span id="rReady">0</span><label>Ready</label></div>
               <div class="reports__summary-item claimed"><span id="rClaimed">0</span><label>Claimed</label></div>
            </div>
         </div>
      </section>

      <!-- ─── PAGE: STUDENTS ─── -->
      <section class="admin-page" id="page-students">
         <div class="admin-page__header">
            <h1>Registered Students</h1>
            <p>View all registered student accounts and their complete request history.</p>
         </div>

         <div class="admin-card">
            <div class="admin__toolbar">
               <input type="text" id="devStudentSearch" placeholder="Search by name, student no., email..." class="admin__search">
               <select id="devStudentProgramFilter" class="admin__filter-select">
                  <option value="">All Programs</option>
               </select>
               <button id="devRefreshStudentsBtn" class="admin__btn-icon"><i class="ri-refresh-line"></i> Refresh</button>
            </div>

            <div class="admin__table-wrap">
               <table class="admin__table">
                  <thead>
                     <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Total</th>
                        <th>Pending</th>
                        <th>Processing</th>
                        <th>Ready</th>
                        <th>Claimed</th>
                        <th>Registered</th>
                        <th>Action</th>
                     </tr>
                  </thead>
                  <tbody id="devStudentsBody">
                     <tr><td colspan="10" style="text-align:center;padding:2rem;">Loading...</td></tr>
                  </tbody>
               </table>
            </div>
         </div>
      </section>

      <!-- ─── PAGE: DEVELOPER SUPPORT / CHAT ─── -->
      <section class="admin-page" id="page-support">
         <div class="admin-page__header admin-page__header--support">
            <button type="button" class="support-nav-btn" id="supportNavBtn" title="Open menu" aria-label="Open menu">
               <i class="ri-menu-line"></i><span>Menu</span>
            </button>
            <h1>Developer Support</h1>
            <p>Respond to bugs, feedback, and technical questions from students.</p>
         </div>

         <div class="admin-card support-card support-card--developer">
            <div class="support-card__header">
               <h2 class="support-card__title"><i class="ri-code-box-line"></i> Developer Support Conversations</h2>
               <button class="support-card__refresh chat-list__refresh" id="chatRefreshBtn" title="Refresh"><i class="ri-refresh-line"></i></button>
            </div>
            <div class="chat-layout-wrap">
         <div class="chat-layout">
            <div class="chat-list" id="chatList">
               <div class="chat-list__search">
                  <label for="chatListSearch" class="support-search-label">Search</label>
                  <input type="text" id="chatListSearch" placeholder="Search student..." class="admin__search" style="margin:0" aria-label="Search conversations by student name">
               </div>
               <div id="chatListBody">
                  <div class="chat-list__loading"><i class="ri-loader-4-line"></i> Loading...</div>
               </div>
            </div>
            <div class="chat-window" id="chatWindow">
               <div class="chat-window__empty" id="chatWindowEmpty">
                  <i class="ri-code-box-line"></i>
                  <p>Select a conversation<br>to reply to technical support</p>
               </div>
               <div class="chat-window__active" id="chatWindowActive" style="display:none">
                  <div class="chat-window__header">
                     <div class="chat-window__student">
                        <div class="chat-window__avatar" id="cwAvatar">?</div>
                        <div>
                           <strong id="cwStudentName">—</strong>
                           <span id="cwStudentNum">—</span>
                        </div>
                     </div>
                     <div class="chat-window__actions">
                        <span class="chat-window__status-badge" id="cwStatusBadge">open</span>
                        <button class="chat-window__resolve-btn" id="cwResolveBtn">
                           <i class="ri-check-double-line"></i> Resolve
                        </button>
                     </div>
                  </div>
                  <div class="chat-window__messages" id="cwMessages"></div>
                  <div class="chat-window__input-wrap">
                     <textarea id="cwInput" class="chat-window__input" placeholder="Type a reply… (Enter to send)" rows="2"></textarea>
                     <button id="cwSendBtn" class="chat-window__send-btn"><i class="ri-send-plane-fill"></i></button>
                  </div>
               </div>
            </div>
         </div>
         </div><!-- /chat-layout-wrap -->
            </div><!-- /support-card -->
      </section>

      <!-- ─── PAGE: SETTINGS ─── -->
      <section class="admin-page" id="page-settings">
         <div class="admin-page__header">
            <h1>Settings</h1>
            <p>System configuration, maintenance mode, backup, and database management.</p>
         </div>

         <!-- Maintenance Mode -->
         <div class="admin-card">
            <h2 class="reports-card__title"><i class="ri-tools-line"></i> Maintenance Mode</h2>
            <p class="settings-desc">For system updates and maintenance. When enabled, visitors see a maintenance page; only developers can log in.</p>
            <div class="settings-toggle-row">
               <label class="settings-toggle" for="maintenanceToggle">
                  <input type="checkbox" id="maintenanceToggle" name="maintenance">
                  <span class="settings-toggle__slider"></span>
               </label>
               <span class="settings-toggle-label" id="maintenanceStatus">Off</span>
            </div>
         </div>

         <!-- Backup & Recovery (requires maintenance mode) -->
         <div class="admin-card settings-requires-maintenance" id="settingsBackupCard">
            <div class="settings-unavailable-overlay" id="backupOverlay">
               <span><i class="ri-lock-line"></i> Enable maintenance mode to use Backup &amp; Recovery</span>
            </div>
            <h2 class="reports-card__title"><i class="ri-database-2-line"></i> Backup &amp; Recovery</h2>
            <p class="settings-desc">Export every critical table to CSV, bundle them into a ZIP archive. Includes all <strong>document requests</strong>, <strong>teller accounts</strong>, registrar, developer, users, and complete data.</p>

            <div class="settings-backup-flow">
               <h3 class="settings-flow-title"><i class="ri-cloud-line"></i> Backup flow logic</h3>
               <ol class="settings-flow-list">
                  <li>User clicks "Backup Database" to trigger the process.</li>
                  <li>System loads the curated list of mission-critical tables.</li>
                  <li>Each table fetches all rows, captures ordered column names, and prepares CSV headers.</li>
                  <li>Rows are converted into CSV format and saved as <code>&lt;table_name&gt;.csv</code>.</li>
                  <li>All CSV files are automatically compressed into a single ZIP archive.</li>
                  <li>The admin receives a success message and the saved file download.</li>
               </ol>
            </div>

            <div class="settings-recovery-flow">
               <h3 class="settings-flow-title"><i class="ri-file-recovery-line"></i> Guided recovery workflow</h3>
               <ol class="settings-flow-list">
                  <li>Select the ZIP file exported from a backup (no mixing of versions).</li>
                  <li>Validate table coverage and column headers before any data touches the DB.</li>
                  <li>Temporarily disable foreign key triggers to avoid parent-child conflicts.</li>
                  <li>Choose a restore mode: Full (truncate first) or Append (skip existing rows).</li>
                  <li>Load tables in dependency order, then verify row counts.</li>
               </ol>
            </div>

            <div class="settings-export-status">
               <h3 class="settings-flow-title"><i class="ri-file-list-3-line"></i> Table export status</h3>
               <p class="settings-desc" style="margin-bottom:.5rem;">Latest backup, restore, or reset activity. <button type="button" id="refreshExportStatusBtn" class="settings-refresh-btn"><i class="ri-refresh-line"></i> Refresh</button></p>
               <div class="settings-export-table-wrap">
                  <table class="admin__table settings-export-table" id="exportStatusTable">
                     <thead>
                        <tr><th>Action</th><th>Time</th><th>Table</th><th>Rows</th><th>Status</th></tr>
                     </thead>
                     <tbody id="exportStatusBody">
                        <tr><td colspan="5" class="settings-export-empty">No activity yet. Run a backup or restore.</td></tr>
                     </tbody>
                  </table>
               </div>
            </div>

            <div class="settings-included-tables">
               <h3 class="settings-flow-title"><i class="ri-table-line"></i> Included tables</h3>
               <p class="settings-desc" style="margin-bottom:.5rem;">Full data backup including <strong>document_requests</strong>, <strong>admins</strong> (developer, registrar, teller accounts), users, and every other table.</p>
               <div class="settings-table-tags" id="settingsTableTags">
                  <span class="settings-table-tag"><i class="ri-table-line"></i>users</span>
                  <span class="settings-table-tag"><i class="ri-table-line"></i>document_types</span>
                  <span class="settings-table-tag"><i class="ri-table-line"></i>document_requests</span>
                  <span class="settings-table-tag"><i class="ri-table-line"></i>admins</span>
                  <span class="settings-table-tag"><i class="ri-table-line"></i>system_settings</span>
                  <span class="settings-table-tag"><i class="ri-table-line"></i>registration_otps</span>
                  <span class="settings-table-tag"><i class="ri-table-line"></i>password_reset_tokens</span>
                  <span class="settings-table-tag"><i class="ri-table-line"></i>chats</span>
                  <span class="settings-table-tag"><i class="ri-table-line"></i>chat_messages</span>
               </div>
            </div>

            <div class="settings-actions">
               <button type="button" id="backupBtn" class="reports__btn csv">
                  <i class="ri-download-cloud-line"></i>
                  <span>
                     <strong>Backup Database</strong>
                     <small>Download ZIP with CSV files for all tables</small>
                  </span>
               </button>
               <div class="settings-restore">
                  <div class="settings-restore-mode">
                     <label class="settings-radio">
                        <input type="radio" name="restoreMode" value="full" checked>
                        <span>Full restore</span>
                     </label>
                     <label class="settings-radio">
                        <input type="radio" name="restoreMode" value="append">
                        <span>Append restore</span>
                     </label>
                  </div>
                  <label class="settings-file-label" for="restoreFile">
                     <i class="ri-upload-cloud-line"></i>
                     <span><strong>Restore from Backup</strong><small>Select .zip file — Full truncates first, Append skips existing rows</small></span>
                  </label>
                  <input type="file" id="restoreFile" accept=".zip" style="display:none">
               </div>
            </div>
         </div>

         <!-- Audit Log -->
         <div class="admin-card">
            <h2 class="reports-card__title"><i class="ri-file-list-3-line"></i> Activity &amp; Audit Log</h2>
            <p class="settings-desc">Recent admin actions: logins, logouts, status updates, user management, maintenance, backup, restore, and reset.</p>
            <div class="settings-actions" style="margin-bottom:1rem;">
               <button type="button" id="refreshAuditBtn" class="settings-refresh-btn"><i class="ri-refresh-line"></i> Refresh</button>
            </div>
            <div class="settings-export-table-wrap">
               <table class="admin__table settings-export-table" id="auditLogTable">
                  <thead>
                     <tr><th>Time</th><th>Action</th><th>Admin</th><th>Details</th><th>IP</th></tr>
                  </thead>
                  <tbody id="auditLogBody">
                     <tr><td colspan="5" class="settings-export-empty">Loading audit log…</td></tr>
                  </tbody>
               </table>
            </div>
         </div>

         <!-- Reset Database (requires maintenance mode) -->
         <div class="admin-card settings-requires-maintenance" id="settingsResetCard">
            <div class="settings-unavailable-overlay" id="resetOverlay">
               <span><i class="ri-lock-line"></i> Enable maintenance mode to use Reset Database</span>
            </div>
            <h2 class="reports-card__title"><i class="ri-restart-line"></i> Reset Database</h2>
            <p class="settings-desc">Reset the database to factory state. Full reset wipes everything. <strong>Preserve users</strong> keeps students and all admin accounts (developer, registrar, teller).</p>
            <div class="settings-reset-actions">
               <button type="button" id="resetFullBtn" class="reports__btn" style="background:#c0392b;">
                  <i class="ri-delete-bin-line"></i>
                  <span><strong>Full reset</strong><small>Wipe all data and recreate schema</small></span>
               </button>
               <button type="button" id="resetPreserveBtn" class="reports__btn csv">
                  <i class="ri-user-line"></i>
                  <span><strong>Preserve users</strong><small>Reset tables but keep users and admins</small></span>
               </button>
            </div>
         </div>
      </section>

   </div><!-- /admin-content -->

   <!-- Student History Modal -->
   <div class="dev-modal__overlay" id="devHistoryModalOverlay">
      <div class="dev-modal" style="max-width:700px;width:95%">
         <div class="dev-modal__header">
            <h3 id="devHistoryModalTitle">Request History</h3>
            <button class="dev-modal__close" id="devHistoryModalClose"><i class="ri-close-line"></i></button>
         </div>
         <div class="dev-modal__body" style="padding:0;max-height:440px;overflow-y:auto;">
            <table class="admin__table" style="margin:0">
               <thead>
                  <tr><th>Document</th><th>Program</th><th>Purpose</th><th>Date</th><th>Status</th></tr>
               </thead>
               <tbody id="devHistoryBody">
                  <tr><td colspan="5" style="text-align:center;padding:1.5rem;">Loading...</td></tr>
               </tbody>
            </table>
         </div>
         <div class="dev-modal__footer">
            <button class="dev-modal__btn cancel" id="devHistoryCloseBtn">Close</button>
         </div>
      </div>
   </div>

   <!-- Add/Edit User Modal -->
   <div class="dev-modal__overlay" id="userModalOverlay">
      <div class="dev-modal">
         <div class="dev-modal__header">
            <h3 id="modalTitle">Add Account</h3>
            <button class="dev-modal__close" id="modalClose"><i class="ri-close-line"></i></button>
         </div>
         <div class="dev-modal__body">
            <input type="hidden" id="editUserId">
            <div class="dev-modal__field">
               <label>Full Name</label>
               <input type="text" id="modalName" placeholder="e.g. Juan dela Cruz" class="dev-modal__input">
            </div>
            <div class="dev-modal__field">
               <label>Username</label>
               <input type="text" id="modalUsername" placeholder="e.g. jdelacruz" class="dev-modal__input">
            </div>
            <div class="dev-modal__field" id="emailField">
               <label>Email Address <small style="color:#DD0426">*</small></label>
               <input type="email" id="modalEmail" placeholder="e.g. juan@evsu.edu.ph" class="dev-modal__input">
               <small id="emailHint" style="color:#999;font-size:.76rem;margin-top:4px;display:block">
                  <i class="ri-mail-send-line"></i> Credentials &amp; activation link will be sent here.
               </small>
            </div>
            <div class="dev-modal__field">
               <label>Role</label>
               <select id="modalRole" class="dev-modal__input">
                  <option value="teller">Teller</option>
                  <option value="registrar">Registrar</option>
               </select>
            </div>
            <!-- Password field only shown when editing -->
            <div class="dev-modal__field" id="passwordField" style="display:none">
               <label id="passwordLabel">New Password (leave blank to keep current)</label>
               <input type="password" id="modalPassword" placeholder="Leave blank to keep current" class="dev-modal__input">
            </div>
            <!-- Permissions — changes based on role selection -->
            <div class="dev-modal__field" id="permissionsField">
               <label>Permissions</label>
               <!-- Teller permissions (shown when role=teller) -->
               <div class="dev-modal__permissions" id="tellerPermsGroup">
                  <label class="dev-modal__checkbox disabled">
                     <input type="checkbox" id="devPermHandleRequests" checked disabled>
                     <span><i class="ri-file-list-3-line"></i> Handle Requests <small>(required)</small></span>
                  </label>
                  <label class="dev-modal__checkbox">
                     <input type="checkbox" id="devPermViewStudents">
                     <span><i class="ri-graduation-cap-line"></i> View Students</span>
                  </label>
                  <label class="dev-modal__checkbox">
                     <input type="checkbox" id="devPermExportReports">
                     <span><i class="ri-bar-chart-2-line"></i> Export Reports</span>
                  </label>
               </div>
               <!-- Registrar permissions (shown when role=registrar) -->
               <div class="dev-modal__permissions" id="registrarPermsGroup" style="display:none">
                  <label class="dev-modal__checkbox">
                     <input type="checkbox" id="devPermManageRequests" checked>
                     <span><i class="ri-file-list-3-line"></i> Manage Requests</span>
                  </label>
                  <label class="dev-modal__checkbox">
                     <input type="checkbox" id="devPermManageStaff" checked>
                     <span><i class="ri-team-line"></i> Manage Staff (Tellers)</span>
                  </label>
                  <label class="dev-modal__checkbox">
                     <input type="checkbox" id="devPermViewStudentsReg" checked>
                     <span><i class="ri-graduation-cap-line"></i> View Students</span>
                  </label>
                  <label class="dev-modal__checkbox">
                     <input type="checkbox" id="devPermExportReportsReg" checked>
                     <span><i class="ri-bar-chart-2-line"></i> Export Reports</span>
                  </label>
                  <label class="dev-modal__checkbox">
                     <input type="checkbox" id="devPermAssignDepartments" checked>
                     <span><i class="ri-building-line"></i> Assign Departments</span>
                  </label>
               </div>
            </div>
         </div>
         <div class="dev-modal__footer">
            <button class="dev-modal__btn cancel" id="modalCancelBtn">Cancel</button>
            <button class="dev-modal__btn save" id="modalSaveBtn">Create &amp; Send Invite</button>
         </div>
      </div>
   </div>

   <!-- Request Detail Modal -->
   <div class="req-detail__overlay" id="reqDetailOverlay">
      <div class="req-detail__modal">
         <div class="req-detail__header">
            <div class="req-detail__header-icon"><i class="ri-file-list-3-line"></i></div>
            <div class="req-detail__header-info">
               <h3 id="rdDocName">Document Name</h3>
               <span class="req-detail__ref" id="rdRefNum">Request #—</span>
            </div>
            <span class="admin__status-badge" id="rdHeaderStatus">—</span>
            <button class="req-detail__close" id="rdClose"><i class="ri-close-line"></i></button>
         </div>
         <div class="req-detail__body">
            <div class="req-detail__student-card">
               <div class="req-detail__student-avatar" id="rdAvatar">—</div>
               <div class="req-detail__student-info">
                  <strong id="rdStudentName">—</strong>
                  <span id="rdStudentNum">—</span>
                  <span id="rdStudentEmail">—</span>
               </div>
            </div>
            <div class="req-detail__grid">
               <div class="req-detail__item">
                  <i class="ri-file-paper-2-line"></i>
                  <div><span class="req-detail__label">Document</span><span class="req-detail__value" id="rdDocument">—</span></div>
               </div>
               <div class="req-detail__item">
                  <i class="ri-building-line"></i>
                  <div><span class="req-detail__label">Department</span><span class="req-detail__value" id="rdDept">—</span></div>
               </div>
               <div class="req-detail__item">
                  <i class="ri-flag-line"></i>
                  <div><span class="req-detail__label">Purpose</span><span class="req-detail__value" id="rdPurpose">—</span></div>
               </div>
               <div class="req-detail__item">
                  <i class="ri-calendar-event-line"></i>
                  <div><span class="req-detail__label">Date Requested</span><span class="req-detail__value" id="rdDate">—</span></div>
               </div>
            </div>
            <div class="req-detail__notes" id="rdNotesSection" style="display:none">
               <i class="ri-sticky-note-line"></i>
               <div><span class="req-detail__label">Admin Notes</span><p id="rdNotes">—</p></div>
            </div>
            <div class="req-detail__timeline">
               <div class="req-detail__timeline-header"><i class="ri-timer-line"></i> Status Progress</div>
               <div class="req-detail__timeline-track" id="rdTimeline"></div>
            </div>
            <div class="req-detail__update" id="rdUpdateSection">
               <div class="req-detail__update-header"><i class="ri-exchange-line"></i><span>Update Status</span></div>
               <div class="req-detail__update-row">
                  <select id="rdStatusSelect" class="admin__filter-select">
                     <option value="pending">Pending</option>
                     <option value="processing">Processing</option>
                     <option value="ready">Ready</option>
                     <option value="claimed">Claimed</option>
                  </select>
                  <button class="admin__btn-primary" id="rdUpdateBtn"><i class="ri-check-line"></i> Update</button>
               </div>
            </div>
         </div>
      </div>
   </div>

   <div class="sidebar-overlay" id="sidebarOverlay"></div>

   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   <script src="assets/js/app-config.js"></script>
   <script src="assets/js/developer-dashboard.js?v=5"></script>
</body>
</html>
