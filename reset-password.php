<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="icon" type="image/png" href="assets/img/Evsu_Logo.png">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css">
   <link rel="stylesheet" href="assets/css/styles.css">
   <link rel="stylesheet" href="assets/css/verify-account.css">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
   <title>Reset Password - Document Request</title>
</head>
<body class="verify-body">
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
      <div class="login__access page-animate">
         <div class="login__logo-wrap">
            <img src="assets/img/Evsu_Logo.png" alt="EVSU" class="login__logo">
         </div>
         <h1 class="login__title">Reset your password</h1>
         <div class="login__area">
            <form class="login__form" id="resetPasswordForm">
               <div class="login__content grid">
                  <div class="login__box">
                     <input type="password" id="new_password" required placeholder=" " class="login__input" minlength="6">
                     <label for="new_password" class="login__label">New password</label>
                     <i class="ri-eye-off-fill login__icon login__password" id="togglePassword"></i>
                  </div>
                  <div class="login__box">
                     <input type="password" id="confirm_password" required placeholder=" " class="login__input" minlength="6">
                     <label for="confirm_password" class="login__label">Confirm password</label>
                     <i class="ri-eye-off-fill login__icon login__password" id="toggleConfirm"></i>
                  </div>
               </div>
               <button type="submit" class="login__button">Reset password</button>
            </form>
            <p class="login__switch"><a href="index.php" style="color: var(--first-color); font-weight: var(--font-semi-bold);">← Back to login</a></p>
         </div>
      </div>
   </div>
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   <script src="assets/js/app-config.js"></script>
   <script src="assets/js/reset-password.js"></script>
</body>
</html>
