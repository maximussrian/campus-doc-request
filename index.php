<!DOCTYPE html>
   <html lang="en">
   <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <link rel="icon" type="image/png" href="assets/img/Evsu_Logo.png">

      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css">

      <link rel="stylesheet" href="assets/css/styles.css">
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
      
      <title>Responsive login and registration form - Bedimcode</title>
      <script>
         (function(){if(location.search.includes('show=login'))return;fetch('api/check-maintenance.php').then(r=>r.json()).then(d=>{if(d.maintenance)location.href='maintenance.php';}).catch(()=>{});})();
      </script>
   </head>
   <body>
      <svg class="login__blob" viewBox="0 0 566 840" xmlns="http://www.w3.org/2000/svg">
         <mask id="mask0" mask-type="alpha">
            <path d="M342.407 73.6315C388.53 56.4007 394.378 17.3643 391.538 
            0H566V840H0C14.5385 834.991 100.266 804.436 77.2046 707.263C49.6393 
            591.11 115.306 518.927 176.468 488.873C363.385 397.026 156.98 302.824 
            167.945 179.32C173.46 117.209 284.755 95.1699 342.407 73.6315Z"/>
         </mask>
      
         <g mask="url(#mask0)">
            <path d="M342.407 73.6315C388.53 56.4007 394.378 17.3643 391.538 
            0H566V840H0C14.5385 834.991 100.266 804.436 77.2046 707.263C49.6393 
            591.11 115.306 518.927 176.468 488.873C363.385 397.026 156.98 302.824 
            167.945 179.32C173.46 117.209 284.755 95.1699 342.407 73.6315Z"/>
      
            <image class="login__img" href="assets/img/bg-img.jpg"/>
         </g>
      </svg>      

      <div class="login container grid" id="loginAccessRegister">
         <div class="login__access">
            <div class="login__logo-wrap">
               <img src="assets/img/Evsu_Logo.png" alt="Eastern Visayas State University" class="login__logo">
            </div>
            <h1 class="login__title">Log in</h1>
            
            <div class="login__area">
               <form action="" class="login__form" id="loginForm">
                  <div class="login__content grid">
                     <div class="login__box">
                        <input type="text" id="student_number" required placeholder=" " class="login__input">
                        <label for="student_number" class="login__label">Student Number</label>
                        <i class="ri-id-card-fill login__icon"></i>
                     </div>
         
                     <div class="login__box">
                        <input type="password" id="password" required placeholder=" " class="login__input">
                        <label for="password" class="login__label">Password</label>
            
                        <i class="ri-eye-off-fill login__icon login__password" id="loginPassword"></i>
                     </div>
                  </div>
         
                  <button type="button" id="loginButtonForgot" class="login__forgot">Forgot your password?</button>
         
                  <button type="submit" class="login__button">Login</button>
               </form>
      
               <p class="login__switch">
                  Don't have an account? 
                  <button id="loginButtonRegister">Create Account</button>
               </p>
            </div>
         </div>

         <div class="login__forgot-panel">
            <div class="login__logo-wrap">
               <img src="assets/img/Evsu_Logo.png" alt="Eastern Visayas State University" class="login__logo">
            </div>
            <h1 class="login__title">Forgot your password?</h1>

            <div class="login__area">
               <p class="login__forgot-desc">
                  <i class="ri-mail-send-line"></i>
                  Enter your university email and we'll send you a verification code.
               </p>
               <form class="login__form" id="forgotPasswordForm">
                  <div class="login__content grid">
                     <div class="login__box">
                        <input type="email" id="forgot_email" required placeholder=" " class="login__input">
                        <label for="forgot_email" class="login__label">Email address</label>
                        <i class="ri-mail-fill login__icon"></i>
                     </div>
                  </div>
                  <button type="submit" class="login__button">
                     <i class="ri-send-plane-fill"></i> Send verification code
                  </button>
               </form>
               <p class="login__switch">
                  Remember your password?
                  <button id="loginButtonBackFromForgot">Log In</button>
               </p>
            </div>
         </div>

         <div class="login__register">
            <div class="login__logo-wrap">
               <img src="assets/img/Evsu_Logo.png" alt="Eastern Visayas State University" class="login__logo">
            </div>
            <h1 class="login__title">Create new account.</h1>

            <div class="login__area">
               <form action="" class="login__form" id="registerForm">
                  <div class="login__content grid">
                     <div class="login__box">
                        <input type="text" id="student_number_create" required placeholder=" " class="login__input" maxlength="10" pattern="\d{4}-\d{5}">
                        <label for="student_number_create" class="login__label">Student Number</label>
                        <i class="ri-id-card-fill login__icon"></i>
                     </div>
                     <p class="login__email-hint"><i class="ri-information-line"></i> Format: <strong>YYYY-NNNNN</strong> &nbsp;e.g. 2022-32222</p>
                     <div class="login__group grid">
                        <div class="login__box">
                           <input type="text" id="names" required placeholder=" " class="login__input">
                           <label for="names" class="login__label">Names</label>
      
                           <i class="ri-id-card-fill login__icon"></i>
                        </div>
      
                        <div class="login__box">
                           <input type="text" id="surnames" required placeholder=" " class="login__input">
                           <label for="surnames" class="login__label">Surnames</label>
      
                           <i class="ri-id-card-fill login__icon"></i>
                        </div>
                     </div>
   
                     <div class="login__box">
                        <input type="email" id="emailCreate" required placeholder=" " class="login__input">
                        <label for="emailCreate" class="login__label">Email</label>
                        <i class="ri-mail-fill login__icon"></i>
                     </div>
                     <p class="login__email-hint"><i class="ri-information-line"></i> Use your university email: <strong>@evsu.edu.ph</strong></p>
   
                     <div class="login__box">
                        <input type="password" id="passwordCreate" required placeholder=" " class="login__input">
                        <label for="passwordCreate" class="login__label">Password</label>
   
                        <i class="ri-eye-off-fill login__icon login__password" id="loginPasswordCreate"></i>
                     </div>
                  </div>
   
                  <button type="submit" class="login__button">Create account</button>
               </form>
   
               <p class="login__switch">
                  Already have an account? 
                  <button id="loginButtonAccess">Log In</button>
               </p>
            </div>
         </div>
      </div>
      
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script src="assets/js/app-config.js"></script>
      <script src="assets/js/main.js"></script>
   </body>
</html>