<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="icon" type="image/png" href="assets/img/Evsu_Logo.png">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css">
   <link rel="stylesheet" href="assets/css/styles.css">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
   <title>Developer Login - EVSU Document Request</title>
</head>
<body>
   <svg class="login__blob" viewBox="0 0 566 840" xmlns="http://www.w3.org/2000/svg">
      <mask id="mask0" mask-type="alpha">
         <path d="M342.407 73.6315C388.53 56.4007 394.378 17.3643 391.538 0H566V840H0C14.5385 834.991 100.266 804.436 77.2046 707.263C49.6393 591.11 115.306 518.927 176.468 488.873C363.385 397.026 156.98 302.824 167.945 179.32C173.46 117.209 284.755 95.1699 342.407 73.6315Z"/>
      </mask>
      <g mask="url(#mask0)">
         <path d="M342.407 73.6315C388.53 56.4007 394.378 17.3643 391.538 0H566V840H0C14.5385 834.991 100.266 804.436 77.2046 707.263C49.6393 591.11 115.306 518.927 176.468 488.873C363.385 397.026 156.98 302.824 167.945 179.32C173.46 117.209 284.755 95.1699 342.407 73.6315Z"/>
         <image class="login__img" href="assets/img/bg-img.jpg"/>
      </g>
   </svg>

   <div class="login container grid">
      <div class="login__access">
         <div class="login__logo-wrap">
            <img src="assets/img/Evsu_Logo.png" alt="EVSU" class="login__logo">
         </div>
         <h1 class="login__title">Developer Portal</h1>
         <div class="login__area">
            <form class="login__form" id="loginForm">
               <div class="login__content grid">
                  <div class="login__box">
                     <input type="text" id="username" required placeholder=" " class="login__input" autocomplete="off">
                     <label for="username" class="login__label">Username</label>
                     <i class="ri-code-box-line login__icon"></i>
                  </div>
                  <div class="login__box">
                     <input type="password" id="password" required placeholder=" " class="login__input" autocomplete="off">
                     <label for="password" class="login__label">Password</label>
                     <i class="ri-eye-off-fill login__icon login__password" id="passwordEye"></i>
                  </div>
               </div>
               <button type="submit" class="login__button">Login</button>
            </form>
            <div class="login__switch" style="text-align:center;margin-top:.75rem;">
               <a href="index.php" style="color:var(--first-color);font-weight:var(--font-semi-bold);font-size:.875rem;">← Student Login</a>
            </div>
         </div>
      </div>
   </div>

   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   <script src="assets/js/app-config.js"></script>
   <script>
     const ALLOWED_ROLE = 'developer';

     const eye = document.getElementById('passwordEye');
     eye.addEventListener('click', () => {
       const inp = document.getElementById('password');
       inp.type = inp.type === 'password' ? 'text' : 'password';
       eye.classList.toggle('ri-eye-fill');
       eye.classList.toggle('ri-eye-off-fill');
     });

     document.getElementById('loginForm').addEventListener('submit', async (e) => {
       e.preventDefault();
       const btn = e.target.querySelector('button[type="submit"]');
       btn.disabled = true; btn.textContent = 'Authenticating...';
       try {
         const res = await fetch(API + '/admin-login.php', {
           method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Session-Realm': 'developer' }, credentials: 'include',
           body: JSON.stringify({
             username: document.getElementById('username').value.trim(),
             password: document.getElementById('password').value
           })
         });
         const data = await res.json();
         if (data.success) {
           if (data.role !== ALLOWED_ROLE) {
             Swal.fire({
               icon: 'error', title: 'Access Denied',
               text: 'This login page is for Developer accounts only.',
               confirmButtonColor: '#DD0426'
             });
           } else {
             window.location.href = 'developer-dashboard.php';
           }
         } else {
           Swal.fire({ icon: 'error', title: 'Login Failed', text: data.message, confirmButtonColor: '#DD0426' });
         }
       } catch { Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error.' }); }
       btn.disabled = false; btn.textContent = 'Login';
     });
   </script>
</body>
</html>
