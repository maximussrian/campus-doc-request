<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Activate Account - EVSU Document Request</title>
   <link rel="icon" type="image/png" href="assets/img/Evsu_Logo.png">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css">
   <style>
      *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

      body {
         min-height: 100vh;
         display: flex;
         align-items: center;
         justify-content: center;
         background: linear-gradient(135deg, #fef0f3 0%, #f9f9ff 60%, #fff 100%);
         font-family: 'Segoe UI', Arial, sans-serif;
         padding: 24px;
      }

      .card {
         background: #fff;
         border-radius: 16px;
         box-shadow: 0 8px 40px rgba(221,4,38,.10), 0 2px 8px rgba(0,0,0,.06);
         padding: 56px 48px 48px;
         max-width: 460px;
         width: 100%;
         text-align: center;
         animation: slideUp .5s ease;
      }

      @keyframes slideUp {
         from { opacity: 0; transform: translateY(20px); }
         to   { opacity: 1; transform: translateY(0); }
      }

      .logo {
         width: 72px;
         height: 72px;
         object-fit: contain;
         margin-bottom: 16px;
      }

      .icon-circle {
         width: 72px;
         height: 72px;
         border-radius: 50%;
         display: flex;
         align-items: center;
         justify-content: center;
         margin: 0 auto 20px;
         font-size: 2rem;
      }
      .icon-circle.loading  { background: #f0f0f0; color: #aaa; }
      .icon-circle.success  { background: #e8f5e9; color: #2e7d32; }
      .icon-circle.error    { background: #ffebee; color: #c62828; }
      .icon-circle.warning  { background: #fff8e1; color: #f57f17; }

      h2 {
         font-size: 1.35rem;
         color: #1a1a2e;
         margin-bottom: 10px;
         font-weight: 700;
      }

      p {
         color: #666;
         font-size: .92rem;
         line-height: 1.6;
         margin-bottom: 6px;
      }

      .role-badge {
         display: inline-block;
         margin-top: 8px;
         padding: 4px 14px;
         border-radius: 20px;
         font-size: .8rem;
         font-weight: 600;
         text-transform: capitalize;
      }
      .role-badge.developer { background: #e8eaf6; color: #3949ab; }
      .role-badge.registrar { background: #e0f7fa; color: #00695c; }
      .role-badge.teller    { background: #fce4ec; color: #c62828; }

      .btn {
         display: inline-block;
         margin-top: 28px;
         padding: 13px 32px;
         background: #DD0426;
         color: #fff;
         text-decoration: none;
         border-radius: 8px;
         font-weight: 700;
         font-size: .95rem;
         transition: background .25s, transform .2s;
      }
      .btn:hover { background: #b00320; transform: translateY(-1px); }

      .spinner {
         width: 36px;
         height: 36px;
         border: 3px solid #f3f3f3;
         border-top-color: #DD0426;
         border-radius: 50%;
         animation: spin .7s linear infinite;
         margin: 0 auto;
      }
      @keyframes spin { to { transform: rotate(360deg); } }
   </style>
</head>
<body>
   <div class="card" id="card">
      <img src="assets/img/Evsu_Logo.png" alt="EVSU" class="logo">
      <div class="spinner" id="spinner"></div>
      <h2 id="title" style="display:none">Activating Account…</h2>
      <p id="message" style="display:none">Please wait.</p>
   </div>

   <script src="assets/js/app-config.js"></script>
   <script>
      function renderState(type, icon, title, message, role, loginHref) {
         document.getElementById('spinner').remove();

         const circle = document.createElement('div');
         circle.className = 'icon-circle ' + type;
         circle.innerHTML = `<i class="${icon}"></i>`;
         document.getElementById('card').insertBefore(circle, document.getElementById('title'));

         const h2 = document.getElementById('title');
         const p  = document.getElementById('message');
         h2.textContent = title;
         p.textContent  = message;
         h2.style.display = '';
         p.style.display  = '';

         if (role) {
            const badge = document.createElement('span');
            badge.className = 'role-badge ' + role;
            badge.textContent = role;
            p.insertAdjacentElement('afterend', badge);
         }

         if (loginHref) {
            const btn = document.createElement('a');
            btn.className = 'btn';
            btn.href = loginHref;
            btn.textContent = 'Go to Login';
            document.getElementById('card').appendChild(btn);
         }
      }

      (async () => {
         const params = new URLSearchParams(window.location.search);
         const token  = params.get('token');

         if (!token) {
            renderState('error', 'ri-error-warning-line', 'Invalid Link', 'No activation token found. Please check your email for the correct link.', null, null);
            return;
         }

         try {
            const res  = await fetch(`${API}/admin-activate.php?token=${encodeURIComponent(token)}`);
            const data = await res.json();

            if (data.success) {
               const role = data.role || 'teller';
               const loginMap = { developer: 'developer-login.php', registrar: 'admin-login.php', teller: 'teller-login.php' };
               const loginHref = loginMap[role] || 'admin-login.php';

               if (data.already_active) {
                  renderState('warning', 'ri-shield-check-line', 'Already Activated', data.message, role, loginHref);
               } else {
                  renderState('success', 'ri-checkbox-circle-line', 'Account Activated!', data.message, role, loginHref);
               }
            } else {
               renderState('error', 'ri-close-circle-line', 'Activation Failed', data.message, null, null);
            }
         } catch {
            renderState('error', 'ri-wifi-off-line', 'Connection Error', 'Could not connect to the server. Please try again later.', null, null);
         }
      })();
   </script>
</body>
</html>
