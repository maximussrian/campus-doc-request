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
   <title>Verify OTP - Document Request</title>
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
         <h1 class="login__title">Enter verification code</h1>
         <p style="text-align:center;font-size:var(--small-font-size);color:var(--text-color);margin-bottom:0.5rem;" id="emailDisplay"></p>
         <p style="text-align:center;font-size:var(--small-font-size);color:var(--first-color);font-weight:var(--font-semi-bold);margin-bottom:1rem;">
            Code expires in: <span id="countdown">10:00</span>
         </p>
         <div class="login__area">
            <form class="login__form" id="verifyOtpForm">
               <div class="login__content grid">
                  <div class="login__box">
                     <input type="text" id="otp" required placeholder=" " class="login__input" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code">
                     <label for="otp" class="login__label">6-digit OTP</label>
                     <i class="ri-shield-check-fill login__icon"></i>
                  </div>
               </div>
               <button type="submit" class="login__button">Verify & proceed</button>
            </form>
            <p class="login__switch">
               <a href="forgot-password.php" style="color: var(--first-color); font-weight: var(--font-semi-bold);">← Back</a>
            </p>
         </div>
      </div>
   </div>
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   <script src="assets/js/app-config.js"></script>
   <script src="assets/js/verify-otp.js"></script>
</body>
</html>
