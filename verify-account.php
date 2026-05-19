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
   <title>Verify Account - EVSU Document Request</title>
</head>
<body class="verify-body">

   <!--=============== LOGIN IMAGE ===============-->
   <svg class="login__blob" viewBox="0 0 566 840" xmlns="http://www.w3.org/2000/svg">
      <mask id="mask0" mask-type="alpha">
         <path d="M342.407 73.6315C388.53 56.4007 394.378 17.3643 391.538 0H566V840H0C14.5385 834.991 100.266 804.436 77.2046 707.263C49.6393 591.11 115.306 518.927 176.468 488.873C363.385 397.026 156.98 302.824 167.945 179.32C173.46 117.209 284.755 95.1699 342.407 73.6315Z"/>
      </mask>
      <g mask="url(#mask0)">
         <path d="M342.407 73.6315C388.53 56.4007 394.378 17.3643 391.538 0H566V840H0C14.5385 834.991 100.266 804.436 77.2046 707.263C49.6393 591.11 115.306 518.927 176.468 488.873C363.385 397.026 156.98 302.824 167.945 179.32C173.46 117.209 284.755 95.1699 342.407 73.6315Z"/>
         <image class="login__img" href="assets/img/bg-img.jpg"/>
      </g>
   </svg>

   <div class="login container grid verify-page">
      <div class="login__access page-animate">

         <!-- Logo -->
         <div class="login__logo-wrap">
            <img src="assets/img/Evsu_Logo.png" alt="EVSU" class="login__logo">
         </div>

         <h1 class="login__title">Verify Your Account</h1>

         <div class="login__area">

            <!-- Email display -->
            <div class="verify__email-display">
               <i class="ri-mail-check-line"></i>
               <div>
                  <p class="verify__email-label">Verification code sent to</p>
                  <p class="verify__email-value" id="displayEmail">loading...</p>
               </div>
            </div>

            <!-- OTP Boxes -->
            <form id="verifyForm">
               <div class="verify__otp-wrap">
                  <input class="verify__otp-box" type="text" inputmode="numeric" maxlength="1" data-index="0">
                  <input class="verify__otp-box" type="text" inputmode="numeric" maxlength="1" data-index="1">
                  <input class="verify__otp-box" type="text" inputmode="numeric" maxlength="1" data-index="2">
                  <span class="verify__otp-dash">—</span>
                  <input class="verify__otp-box" type="text" inputmode="numeric" maxlength="1" data-index="3">
                  <input class="verify__otp-box" type="text" inputmode="numeric" maxlength="1" data-index="4">
                  <input class="verify__otp-box" type="text" inputmode="numeric" maxlength="1" data-index="5">
               </div>

               <!-- Countdown -->
               <div class="verify__timer" id="timerWrap">
                  <i class="ri-time-line"></i>
                  Code expires in <span id="countdown">10:00</span>
               </div>
               <div class="verify__expired hidden" id="expiredMsg">
                  <i class="ri-error-warning-line"></i> Code has expired.
               </div>

               <button type="submit" class="login__button" id="verifyBtn">
                  <i class="ri-shield-check-line"></i> Verify Account
               </button>
            </form>

            <!-- Resend -->
            <p class="login__switch" style="margin-top:1rem;">
               Didn't receive the code?
               <button id="resendBtn" disabled>Resend <span id="resendCooldown">(wait 60s)</span></button>
            </p>

            <p class="login__switch">
               <a href="index.php" style="color:var(--first-color);font-weight:var(--font-semi-bold);">
                  <i class="ri-arrow-left-line"></i> Back to Register
               </a>
            </p>

         </div>
      </div>
   </div>

   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   <script src="assets/js/app-config.js"></script>
   <script src="assets/js/verify-account.js"></script>
</body>
</html>
