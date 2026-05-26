<?php require_once __DIR__ . '/api/guard_registrar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="icon" type="image/png" href="assets/img/Evsu_Logo.png">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css">
   <link rel="stylesheet" href="assets/css/styles.css?v=3">
   <link rel="stylesheet" href="assets/css/admin.css?v=14">
   <link rel="stylesheet" href="assets/css/developer.css?v=3">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
   <title>Registrar Dashboard - EVSU</title>
</head>
<body class="admin-body">

   <!-- ═══════════ SIDEBAR ═══════════ -->
   <aside class="sidebar" id="sidebar">
      <div class="sidebar__top">
         <img src="assets/img/Evsu_Logo.png" alt="EVSU" class="sidebar__logo">
         <div class="sidebar__brand">
            <span class="sidebar__brand-name">EVSU</span>
            <span class="sidebar__brand-sub">Registrar Portal</span>
         </div>
         <button class="sidebar__toggle" id="sidebarToggle">
            <i class="ri-menu-fold-line"></i>
         </button>
      </div>

      <nav class="sidebar__nav">
         <a href="#" class="sidebar__link active" data-page="home" data-label="Home">
            <i class="ri-home-5-line"></i>
            <span>Home</span>
         </a>
         <a href="#" class="sidebar__link" data-page="requests" data-label="Requests">
            <i class="ri-file-list-3-line"></i>
            <span>Requests</span>
            <span class="sidebar__badge" id="pendingBadge">0</span>
         </a>
         <a href="#" class="sidebar__link" data-page="students" data-label="Students">
            <i class="ri-graduation-cap-line"></i>
            <span>Students</span>
         </a>
         <a href="#" class="sidebar__link" data-page="staff" data-label="Staff">
            <i class="ri-team-line"></i>
            <span>Staff</span>
         </a>
         <a href="#" class="sidebar__link" data-page="reports" data-label="Reports">
            <i class="ri-bar-chart-2-line"></i>
            <span>Reports</span>
         </a>
         <a href="#" class="sidebar__link" data-page="support" data-label="Document Support">
            <i class="ri-file-list-3-line"></i>
            <span>Document Support</span>
            <span class="sidebar__badge hidden" id="supportBadge">0</span>
         </a>
      </nav>

      <div class="sidebar__bottom">
         <div class="sidebar__profile">
            <div class="sidebar__avatar" id="sidebarAvatar">?</div>
            <div class="sidebar__profile-info">
               <span class="sidebar__profile-name" id="adminName">Loading...</span>
               <span class="sidebar__profile-role">Administrator</span>
            </div>
         </div>
         <button class="sidebar__logout" id="logoutBtn" data-label="Logout">
            <i class="ri-logout-box-line"></i>
            <span>Logout</span>
         </button>
      </div>
   </aside>

   <!-- ═══════════ MAIN CONTENT ═══════════ -->
   <div class="admin-content" id="adminContent">

      <!-- Mobile topbar -->
      <header class="admin-topbar">
         <button class="admin-topbar__menu" id="mobileMenuBtn">
            <i class="ri-menu-line"></i>
         </button>
         <span class="admin-topbar__title" id="pageTitle">Home</span>
         <div class="admin-topbar__right">
            <div class="sidebar__avatar sm" id="topbarAvatar">?</div>
         </div>
      </header>

      <!-- ─── PAGE: HOME ─── -->
      <section class="admin-page active" id="page-home">
         <div class="admin-page__header">
            <h1>Dashboard Overview</h1>
            <p>Welcome back, <span id="welcomeName">Admin</span>. Here's what's happening today.</p>
         </div>

         <div class="admin__stats">
            <div class="admin__stat-card all" data-filter="" data-page="requests">
               <div class="admin__stat-icon"><i class="ri-file-list-line"></i></div>
               <div class="admin__stat-body">
                  <span class="admin__stat-num" id="statAll">0</span>
                  <span class="admin__stat-label">Total Requests</span>
               </div>
            </div>
            <div class="admin__stat-card pending" data-filter="pending" data-page="requests">
               <div class="admin__stat-icon"><i class="ri-time-line"></i></div>
               <div class="admin__stat-body">
                  <span class="admin__stat-num" id="statPending">0</span>
                  <span class="admin__stat-label">Pending</span>
               </div>
            </div>
            <div class="admin__stat-card processing" data-filter="processing" data-page="requests">
               <div class="admin__stat-icon"><i class="ri-loader-3-line"></i></div>
               <div class="admin__stat-body">
                  <span class="admin__stat-num" id="statProcessing">0</span>
                  <span class="admin__stat-label">Processing</span>
               </div>
            </div>
            <div class="admin__stat-card ready" data-filter="ready" data-page="requests">
               <div class="admin__stat-icon"><i class="ri-checkbox-circle-line"></i></div>
               <div class="admin__stat-body">
                  <span class="admin__stat-num" id="statReady">0</span>
                  <span class="admin__stat-label">Ready</span>
               </div>
            </div>
            <div class="admin__stat-card claimed" data-filter="claimed" data-page="requests">
               <div class="admin__stat-icon"><i class="ri-hand-coin-line"></i></div>
               <div class="admin__stat-body">
                  <span class="admin__stat-num" id="statClaimed">0</span>
                  <span class="admin__stat-label">Claimed</span>
               </div>
            </div>
         </div>

         <!-- Daily Summary (US-07) -->
         <!-- Daily Workload (registrar) -->
         <div class="admin-card" id="dailyWorkloadCard" style="display:none;">
            <div class="admin-card__header">
               <h2><i class="ri-calendar-check-line"></i> Daily Workload</h2>
               <span class="admin-card__badge" id="dailyWorkloadBadge">— / 50 today</span>
            </div>
            <p class="admin-card__sub">Total requests per day. Select date to view breakdown by document type. Max 50 per day.</p>
            <div class="reports__filter-group" style="margin-bottom:1rem;">
               <label>Select date</label>
               <input type="date" id="dailySummaryDate" class="admin__date-input" style="max-width:180px">
            </div>
            <div id="dailySummaryResult" style="margin-bottom:1rem;min-height:0"></div>
            <div class="admin__table-wrap">
               <table class="admin__table" id="dailyWorkloadTable">
                  <thead>
                     <tr>
                        <th>Date</th>
                        <th>Requests</th>
                        <th>Status</th>
                     </tr>
                  </thead>
                  <tbody id="dailyWorkloadBody">
                     <tr><td colspan="3" style="text-align:center;padding:1rem;">Loading...</td></tr>
                  </tbody>
               </table>
            </div>
         </div>

         <!-- Recent requests preview -->
         <div class="admin-card">
            <div class="admin-card__header">
               <h2><i class="ri-history-line"></i> Recent Requests</h2>
               <a href="#" class="admin-card__link" data-page="requests">View All <i class="ri-arrow-right-line"></i></a>
            </div>
            <div class="admin__table-wrap">
               <table class="admin__table" id="recentTable">
                  <thead>
                     <tr>
                        <th>Student</th>
                        <th>Document</th>
                        <th>Date</th>
                        <th>Status</th>
                     </tr>
                  </thead>
                  <tbody id="recentBody">
                     <tr><td colspan="4" style="text-align:center;padding:1.5rem;">Loading...</td></tr>
                  </tbody>
               </table>
            </div>
         </div>
      </section>

      <!-- ─── PAGE: REQUESTS ─── -->
      <section class="admin-page" id="page-requests">
         <div class="admin-page__header">
            <h1>Document Requests</h1>
            <p>Manage and update all student document requests.</p>
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
               <button id="refreshBtn" class="admin__btn-icon" title="Refresh">
                  <i class="ri-refresh-line"></i> Refresh
               </button>
            </div>

            <div class="admin__table-wrap">
               <table class="admin__table">
                  <thead>
                     <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Document</th>
                        <th>Program</th>
                        <th>Purpose</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Notes</th>
                     </tr>
                  </thead>
                  <tbody id="requestsBody">
                     <tr><td colspan="8" style="text-align:center;padding:2rem;">Loading...</td></tr>
                  </tbody>
               </table>
            </div>
         </div>
      </section>

      <!-- ─── PAGE: REPORTS ─── -->
      <section class="admin-page" id="page-reports">
         <div class="admin-page__header">
            <h1>Reports & Export</h1>
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
               <button id="clearDates" class="admin__btn-ghost">
                  <i class="ri-close-line"></i> Clear Dates
               </button>
            </div>

            <div class="reports__export-btns">
               <button id="exportCsvBtn" class="reports__btn csv">
                  <i class="ri-file-text-line"></i>
                  <span>
                     <strong>Export as CSV</strong>
                     <small>Compatible with all spreadsheet apps</small>
                  </span>
               </button>
               <button id="exportExcelBtn" class="reports__btn excel">
                  <i class="ri-file-excel-2-line"></i>
                  <span>
                     <strong>Export as Excel</strong>
                     <small>Formatted .xls with colored status rows</small>
                  </span>
               </button>
            </div>
         </div>

         <!-- Summary cards in reports -->
         <!-- Monthly Total (US-08) -->
         <div class="admin-card">
            <h2 class="reports-card__title"><i class="ri-calendar-line"></i> Monthly Total</h2>
            <p class="admin-card__sub">View total requests for a selected month to identify peak periods.</p>
            <div class="reports__filter-group" style="display:flex;gap:1rem;align-items:flex-end;margin-bottom:1rem;">
               <div>
                  <label>Month</label>
                  <input type="month" id="monthlyTotalMonth" class="admin__date-input" style="max-width:160px">
               </div>
               <button type="button" id="loadMonthlyBtn" class="admin__btn-primary"><i class="ri-search-line"></i> Load</button>
            </div>
            <div id="monthlyTotalResult" class="reports__summary-item all" style="padding:1rem;background:#f8f9fa;border-radius:0.5rem;">
               <span id="monthlyTotalNum">—</span><label id="monthlyTotalLabel">Select month and click Load</label>
            </div>
         </div>

         <div class="admin-card">
            <h2 class="reports-card__title"><i class="ri-pie-chart-line"></i> Summary</h2>
            <div class="reports__summary">
               <div class="reports__summary-item all">
                  <span id="rAll">0</span><label>Total</label>
               </div>
               <div class="reports__summary-item pending">
                  <span id="rPending">0</span><label>Pending</label>
               </div>
               <div class="reports__summary-item processing">
                  <span id="rProcessing">0</span><label>Processing</label>
               </div>
               <div class="reports__summary-item ready">
                  <span id="rReady">0</span><label>Ready</label>
               </div>
               <div class="reports__summary-item claimed">
                  <span id="rClaimed">0</span><label>Claimed</label>
               </div>
            </div>
         </div>
      </section>

      <!-- ─── PAGE: STAFF ─── -->
      <section class="admin-page" id="page-staff">
         <div class="admin-page__header">
            <h1>Staff Management</h1>
            <p>Create and manage Teller accounts for your office.</p>
         </div>

         <div class="admin-card">
            <div class="admin__toolbar">
               <input type="text" id="staffSearch" placeholder="Search by name or username..." class="admin__search">
               <select id="staffStatusFilter" class="admin__filter-select">
                  <option value="">All Status</option>
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
               </select>
               <button id="addTellerBtn" class="admin__btn-primary">
                  <i class="ri-user-add-line"></i> Add Teller
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
                        <th>Status</th>
                        <th>Departments</th>
                        <th>Permissions</th>
                        <th>Created By</th>
                        <th>Date Added</th>
                        <th>Actions</th>
                     </tr>
                  </thead>
                  <tbody id="staffBody">
                     <tr><td colspan="10" style="text-align:center;padding:2rem;">Loading...</td></tr>
                  </tbody>
               </table>
            </div>
         </div>
      </section>

      <!-- ─── PAGE: STUDENTS ─── -->
      <section class="admin-page" id="page-students">
         <div class="admin-page__header">
            <h1>Registered Students</h1>
            <p>View all registered student accounts and their request history.</p>
         </div>

         <div class="admin-card">
            <div class="admin__toolbar">
               <input type="text" id="studentSearch" placeholder="Search by name, student no., email..." class="admin__search">
               <select id="studentProgramFilter" class="admin__filter-select">
                  <option value="">All Programs</option>
               </select>
               <button id="refreshStudentsBtn" class="admin__btn-icon"><i class="ri-refresh-line"></i> Refresh</button>
            </div>

            <div class="admin__table-wrap">
               <table class="admin__table">
                  <thead>
                     <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Total Requests</th>
                        <th>Pending</th>
                        <th>Processing</th>
                        <th>Ready</th>
                        <th>Claimed</th>
                        <th>Registered</th>
                        <th>Action</th>
                     </tr>
                  </thead>
                  <tbody id="studentsBody">
                     <tr><td colspan="10" style="text-align:center;padding:2rem;">Loading...</td></tr>
                  </tbody>
               </table>
            </div>
         </div>
      </section>

      <!-- ─── PAGE: DOCUMENT SUPPORT / CHAT ─── -->
      <section class="admin-page" id="page-support">
         <div class="admin-page__header admin-page__header--support">
            <button type="button" class="support-nav-btn" id="supportNavBtn" title="Open menu" aria-label="Open menu">
               <i class="ri-menu-line"></i><span>Menu</span>
            </button>
            <h1>Document Support</h1>
            <p>Respond to document request and status inquiries from students.</p>
         </div>

         <div class="admin-card support-card support-card--registrar">
            <div class="support-card__header">
               <h2 class="support-card__title"><i class="ri-file-list-3-line"></i> Document Support Conversations</h2>
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
                  <i class="ri-file-list-3-line"></i>
                  <p>Select a conversation<br>to reply to document inquiries</p>
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

   </div><!-- /admin-content -->

   <!-- Add Teller Modal -->
   <div class="dev-modal__overlay" id="tellerModalOverlay">
      <div class="dev-modal">
         <div class="dev-modal__header">
            <h3 id="tellerModalTitle">Add Teller Account</h3>
            <button class="dev-modal__close" id="tellerModalClose"><i class="ri-close-line"></i></button>
         </div>
         <div class="dev-modal__body">
            <input type="hidden" id="tellerEditId">
            <div class="dev-modal__field">
               <label>Full Name</label>
               <input type="text" id="tellerName" placeholder="e.g. Maria Santos" class="dev-modal__input">
            </div>
            <div class="dev-modal__field">
               <label>Username</label>
               <input type="text" id="tellerUsername" placeholder="e.g. msantos" class="dev-modal__input">
            </div>
            <div class="dev-modal__field">
               <label>Email Address <small style="color:#DD0426">*</small></label>
               <input type="email" id="tellerEmail" placeholder="e.g. msantos@evsu.edu.ph" class="dev-modal__input">
               <small style="color:#999;font-size:.76rem;margin-top:4px;display:block">
                  <i class="ri-mail-send-line"></i> Credentials &amp; activation link will be sent here.
               </small>
            </div>
            <div class="dev-modal__field">
               <label>Permissions</label>
               <div class="dev-modal__permissions">
                  <label class="dev-modal__checkbox disabled">
                     <input type="checkbox" id="tellerPermHandleRequests" checked disabled>
                     <span><i class="ri-file-list-3-line"></i> Handle Requests <small>(required)</small></span>
                  </label>
                  <label class="dev-modal__checkbox">
                     <input type="checkbox" id="tellerPermViewStudents">
                     <span><i class="ri-graduation-cap-line"></i> View Students</span>
                  </label>
                  <label class="dev-modal__checkbox">
                     <input type="checkbox" id="tellerPermExportReports">
                     <span><i class="ri-bar-chart-2-line"></i> Export Reports</span>
                  </label>
               </div>
            </div>
            <div class="dev-modal__field" id="tellerDepartmentsField">
               <label><i class="ri-building-line"></i> Assigned Departments</label>
               <small style="color:#888;font-size:.78rem;display:block;margin-bottom:6px">
                  Limit this teller to requests from specific departments. Leave all unchecked to allow all departments.
               </small>
               <div class="dev-modal__dept-list" id="tellerDeptList">
                  <div class="admin__dept-loading" id="tellerDeptLoading">Loading departments...</div>
                  <div class="dev-modal__permissions" id="tellerDeptCheckboxes" style="display:none;max-height:180px;overflow-y:auto;border:1px solid #e0e0e0;border-radius:6px;padding:.5rem;background:#fafafa"></div>
               </div>
            </div>
         </div>
         <div class="dev-modal__footer">
            <button class="dev-modal__btn cancel" id="tellerModalCancelBtn">Cancel</button>
            <button class="dev-modal__btn save" id="tellerModalSaveBtn">Create &amp; Send Invite</button>
         </div>
      </div>
   </div>

   <!-- Student History Modal -->
   <div class="dev-modal__overlay" id="historyModalOverlay">
      <div class="dev-modal" style="max-width:680px;width:95%">
         <div class="dev-modal__header">
            <h3 id="historyModalTitle">Request History</h3>
            <button class="dev-modal__close" id="historyModalClose"><i class="ri-close-line"></i></button>
         </div>
         <div class="dev-modal__body" style="padding:0;max-height:500px;overflow-y:auto;">
            <div class="history-flags" id="historyFlags" style="padding:1rem 1.5rem;background:#f8f9fa;border-bottom:1px solid #eee;">
               <div class="history-flags__label"><i class="ri-shield-keyhole-line"></i> Document eligibility</div>
               <label class="history-flags__item">
                  <input type="checkbox" id="historyFlagTransfer" title="Authorize Form 137 requests (transfer to another school)">
                  <span>Transfer authorized (Form 137)</span>
               </label>
               <label class="history-flags__item">
                  <input type="checkbox" id="historyFlagGraduated" title="Student has graduated">
                  <span>Graduated</span>
               </label>
               <button type="button" class="admin__btn-icon" id="historyFlagsSave" style="margin-left:.5rem;display:none"><i class="ri-save-line"></i> Save</button>
            </div>
            <table class="admin__table" style="margin:0">
               <thead>
                  <tr><th>Document</th><th>Program</th><th>Purpose</th><th>Date</th><th>Status</th></tr>
               </thead>
               <tbody id="historyBody">
                  <tr><td colspan="5" style="text-align:center;padding:1.5rem;">Loading...</td></tr>
               </tbody>
            </table>
         </div>
         <div class="dev-modal__footer">
            <button class="dev-modal__btn cancel" id="historyCloseBtn">Close</button>
         </div>
      </div>
   </div>

   <!-- Sidebar overlay (mobile) -->
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
               <div class="req-detail__item" id="rdProcessedSection" style="display:none">
                  <i class="ri-user-star-line"></i>
                  <div><span class="req-detail__label">Processed By</span><span class="req-detail__value" id="rdProcessedBy">—</span></div>
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
   <script src="assets/js/admin-dashboard.js?v=10"></script>
</body>
</html>
