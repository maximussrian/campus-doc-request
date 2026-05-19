<?php require_once __DIR__ . '/api/guard_student.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="icon" type="image/png" href="assets/img/Evsu_Logo.png">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css">
   <link rel="stylesheet" href="assets/css/styles.css">
   <link rel="stylesheet" href="assets/css/dashboard.css?v=8">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
   <title>Document Request - Dashboard</title>
   <script>
      (function(){fetch('api/check-maintenance.php').then(r=>r.json()).then(d=>{if(d.maintenance)location.href='maintenance.php';}).catch(()=>{});})();
   </script>
</head>
<body>
   <header class="dashboard__header">
      <div class="container dashboard__nav">
         <a href="dashboard.php" class="dashboard__logo">
            <img src="assets/img/Evsu_Logo.png" alt="EVSU" class="dashboard__logo-img">
            <span>Document Request</span>
         </a>
         <div class="dashboard__user">
            <div class="notif-wrap" title="Notifications">
               <button type="button" class="notif-bell" id="notifBell" aria-label="Notifications">
                  <i class="ri-notification-3-line"></i>
                  <span class="notif-badge hidden" id="notifBadge">0</span>
               </button>
               <div class="notif-dropdown" id="notifDropdown">
                  <div class="notif-dropdown__header">
                     <span>Notifications</span>
                     <button type="button" class="notif-mark-all" id="notifMarkAll" style="display:none;">Mark all read</button>
                  </div>
                  <div class="notif-dropdown__list" id="notifList">
                     <p class="notif-empty">No notifications yet.</p>
                  </div>
               </div>
            </div>
            <div class="admin__profile">
               <div class="admin__avatar"><i class="ri-user-fill"></i></div>
               <span id="userName">Loading...</span>
            </div>
            <button id="logoutBtn" class="dashboard__btn-logout">
               <i class="ri-logout-box-line"></i><span class="logout-label"> Logout</span>
            </button>
         </div>
      </div>
   </header>

   <main class="dashboard__main container">
      <section class="dashboard__welcome">
         <h1>Request Documents</h1>
         <p>Select the document you need and submit your request.</p>
      </section>

      <section class="dashboard__request">
         <h2><i class="ri-file-add-line"></i> New Request</h2>
         <form id="requestForm" class="dashboard__form">
            <div class="dashboard__field">
               <label for="documentType">Document Type</label>
               <select id="documentType" required class="dashboard__select">
                  <option value="">Select document...</option>
               </select>
            </div>
            <div class="dashboard__field">
               <label for="department">Program</label>
               <p class="dashboard__field-hint" id="programHint" style="display:none;">Your first selection becomes your official program and cannot be changed for future requests.</p>
               <select id="department" required class="dashboard__select">
                  <option value="">Select program...</option>
                  <optgroup label="Business, Entrepreneurship, and Management Department">
                     <option value="Bachelor of Science in Accountancy (BSA)">Bachelor of Science in Accountancy (BSA)</option>
                     <option value="Bachelor of Science in Entrepreneurship (BSE)">Bachelor of Science in Entrepreneurship (BSE)</option>
                     <option value="Bachelor of Science in Marketing (BSM)">Bachelor of Science in Marketing (BSM)</option>
                     <option value="Bachelor of Science in Office Administration (BSOA)">Bachelor of Science in Office Administration (BSOA)</option>
                  </optgroup>
                  <optgroup label="Education Department">
                     <option value="Bachelor of Secondary Education (BSEd) - Mathematics">BSEd major in Mathematics</option>
                     <option value="Bachelor of Secondary Education (BSEd) - Science">BSEd major in Science</option>
                     <option value="Bachelor of Secondary Education (BSEd) - Technology Livelihood Education">BSEd major in Technology Livelihood Education</option>
                     <option value="Bachelor of Physical Education (BPEd)">Bachelor of Physical Education (BPEd)</option>
                     <option value="Diploma in Teaching Secondary (DTS)">Diploma in Teaching Secondary (DTS)</option>
                     <option value="BTVTEd - Food and Service Management">BTVTEd major in Food and Service Management</option>
                     <option value="BTVTEd - Garments, Fashion, and Design">BTVTEd major in Garments, Fashion, and Design</option>
                     <option value="BTLEd - Industrial Arts (IA)">BTLEd major in Industrial Arts (IA)</option>
                     <option value="BTLEd - Home Economics (HE)">BTLEd major in Home Economics (HE)</option>
                  </optgroup>
                  <optgroup label="Engineering Department">
                     <option value="Bachelor of Science in Civil Engineering (BSCE)">Bachelor of Science in Civil Engineering (BSCE)</option>
                     <option value="Bachelor of Science in Information Technology (BSIT)">Bachelor of Science in Information Technology (BSIT)</option>
                  </optgroup>
                  <optgroup label="Technology Department">
                     <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                     <option value="Bachelor of Science in Hotel and Restaurant Technology">Bachelor of Science in Hotel and Restaurant Technology</option>
                     <option value="BS in Industrial Technology - Culinary Arts">BS in Industrial Technology major in Culinary Arts</option>
                     <option value="BS in Industrial Technology - Electricity">BS in Industrial Technology major in Electricity</option>
                     <option value="BS in Industrial Technology - Electronics">BS in Industrial Technology major in Electronics</option>
                     <option value="BS in Mechanical Technology - Automotive">BS in Mechanical Technology major in Automotive</option>
                     <option value="BS in Mechanical Technology - Welding and Fabrication">BS in Mechanical Technology major in Welding and Fabrication</option>
                     <option value="Bachelor of Industrial Technology - Electrical Technology">Bachelor of Industrial Technology major in Electrical Technology</option>
                  </optgroup>
               </select>
            </div>
            <div class="dashboard__field">
               <label for="purpose">Purpose</label>
               <input type="text" id="purpose" required class="dashboard__input" placeholder="e.g. Employment, Scholarship, Board Exam...">
            </div>
            <div class="dashboard__field">
               <label for="notes">Notes (optional)</label>
               <textarea id="notes" class="dashboard__textarea" rows="3" placeholder="Add any additional notes..."></textarea>
            </div>
            <button type="submit" class="dashboard__btn-submit">
               <i class="ri-send-plane-fill"></i> Submit Request
            </button>
         </form>
      </section>

      <section class="dashboard__requests">
         <h2><i class="ri-file-list-3-line"></i> My Requests</h2>
         <div id="requestsList" class="dashboard__list">
            <p class="dashboard__empty">Loading your requests...</p>
         </div>
      </section>
   </main>

   <!-- ─── FLOATING CHAT WIDGET ─── -->
   <button class="chat-fab" id="chatFab" title="Contact Support">
      <i class="ri-customer-service-2-line"></i>
      <span class="chat-fab__badge hidden" id="chatFabBadge">0</span>
   </button>

   <div class="chat-panel" id="chatPanel">
      <div class="chat-panel__header">
         <div class="chat-panel__header-left">
            <div class="chat-panel__title">
               <div class="chat-panel__hdr-icon"><i class="ri-customer-service-2-line"></i></div>
               <div class="chat-panel__title-text">
                  <strong id="chatPanelTitle">Document Support</strong>
                  <span class="chat-panel__subtitle" id="chatPanelSubtitle">Registrar — Document requests</span>
               </div>
            </div>
            <div class="chat-panel__channels">
               <button type="button" class="chat-channel-btn active" data-channel="registrar" title="Document requests, status updates"><i class="ri-file-list-3-line"></i> Document</button>
               <button type="button" class="chat-channel-btn" data-channel="developer" title="Bugs, feedback, questions"><i class="ri-code-box-line"></i> Developer</button>
            </div>
         </div>
         <button class="chat-panel__close" id="chatPanelClose"><i class="ri-close-line"></i></button>
      </div>
      <div class="chat-panel__messages" id="chatPanelMessages">
         <div class="chat-msg-empty"><i class="ri-chat-3-line"></i><p>Send us a message and we'll get back to you shortly.</p></div>
      </div>
      <div class="chat-panel__contact-footer" id="chatPanelContactFooter" style="display:none;padding:.5rem 1rem;font-size:.7rem;color:rgba(255,255,255,.8);border-top:1px solid rgba(255,255,255,.2)"></div>
      <div class="chat-panel__input-wrap">
         <input type="text" id="chatPanelInput" class="chat-panel__input" placeholder="Type a message…">
         <button id="chatPanelSend" class="chat-panel__send-btn"><i class="ri-send-plane-fill"></i></button>
      </div>
   </div>

   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   <script src="assets/js/app-config.js"></script>
   <script src="assets/js/dashboard.js?v=5"></script>
</body>
</html>
