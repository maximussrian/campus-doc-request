/*=============== BACKEND API (see app-config.js) ===============*/

document.getElementById('loginForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = e.target.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'Logging in...';
  try {
    const res = await fetch(API + '/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ student_number: document.getElementById('student_number').value.trim(), password: document.getElementById('password').value })
    });
    const data = await res.json();
    if (data.success) {
      window.location.href = 'dashboard.php';
      return;
    } else {
      if (data.maintenance) {
        Swal.fire({ icon: 'info', title: 'Under Maintenance', html: '<p>Come back soon.</p>', confirmButtonColor: '#DD0426' });
      } else {
        Swal.fire({ icon: 'error', title: 'Login Failed', text: data.message });
      }
    }
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Check XAMPP Apache & MySQL.' });
  }
  btn.disabled = false;
  btn.textContent = 'Login';
});

const ALLOWED_EMAIL_DOMAIN = 'evsu.edu.ph';
const STUDENT_NUMBER_REGEX = /^\d{4}-\d{5}$/;

function isUniversityEmail(email) {
  return email.toLowerCase().endsWith('@' + ALLOWED_EMAIL_DOMAIN);
}

function isValidStudentNumber(sn) {
  return STUDENT_NUMBER_REGEX.test(sn);
}

let registerSubmitting = false;

document.getElementById('registerForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  if (registerSubmitting) return;
  const snVal    = document.getElementById('student_number_create').value.trim();
  const emailVal = document.getElementById('emailCreate').value.trim().toLowerCase();

  if (!isValidStudentNumber(snVal)) {
    Swal.fire({
      icon: 'error',
      title: 'Invalid Student Number',
      html: `Student number must follow the format <strong>YYYY-NNNNN</strong>.<br><small>Example: <strong>2022-32222</strong></small>`
    });
    return;
  }

  if (!isUniversityEmail(emailVal)) {
    Swal.fire({
      icon: 'error',
      title: 'Invalid Email',
      html: `Only university email addresses are allowed.<br><small>Please use your <strong>@${ALLOWED_EMAIL_DOMAIN}</strong> email.</small>`
    });
    return;
  }

  const btn = e.target.querySelector('button[type="submit"]');
  registerSubmitting = true;
  btn.disabled = true;
  btn.textContent = 'Sending code...';

  const formData = {
    student_number: snVal,
    names:    document.getElementById('names').value.trim(),
    surnames: document.getElementById('surnames').value.trim(),
    email:    emailVal,
    password: document.getElementById('passwordCreate').value
  };

  try {
    // Send OTP to university email
    const res  = await fetch(API + '/send-register-otp.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(formData)
    });
    const data = await res.json();

    if (!data.success) {
      Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#DD0426' });
      registerSubmitting = false;
      btn.disabled = false; btn.textContent = 'Create account';
      return;
    }

    // Save form data for resend feature on verify-account page
    sessionStorage.setItem('registerData', JSON.stringify(formData));

    // Redirect to dedicated verification page
    window.location.href = `verify-account.php?email=${encodeURIComponent(emailVal)}`;

  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Check XAMPP Apache & MySQL.' });
    registerSubmitting = false;
    btn.disabled = false;
    btn.textContent = 'Create account';
  }
});

/*=============== STUDENT NUMBER AUTO-FORMAT ===============*/
document.getElementById('student_number_create')?.addEventListener('input', function () {
  let val = this.value.replace(/\D/g, ''); // digits only
  if (val.length > 4) val = val.slice(0, 4) + '-' + val.slice(4, 9);
  else val = val.slice(0, 4);
  this.value = val;
});

/*=============== SHOW HIDE PASSWORD LOGIN ===============*/
const passwordAccess = (loginPass, loginEye) =>{
  const input = document.getElementById(loginPass),
        iconEye = document.getElementById(loginEye)

  iconEye.addEventListener('click', () =>{
     // Change password to text
     input.type === 'password' ? input.type = 'text'
                         : input.type = 'password'

     // Icon change
     iconEye.classList.toggle('ri-eye-fill')
     iconEye.classList.toggle('ri-eye-off-fill')
  })
}
passwordAccess('password','loginPassword')

/*=============== SHOW HIDE PASSWORD CREATE ACCOUNT ===============*/
const passwordRegister = (loginPass, loginEye) =>{
  const input = document.getElementById(loginPass),
        iconEye = document.getElementById(loginEye)

  iconEye.addEventListener('click', () =>{
     // Change password to text
     input.type === 'password' ? input.type = 'text'
                         : input.type = 'password'

     // Icon change
     iconEye.classList.toggle('ri-eye-fill')
     iconEye.classList.toggle('ri-eye-off-fill')
  })
}
passwordRegister('passwordCreate','loginPasswordCreate')

/*=============== SHOW HIDE LOGIN, REGISTER & FORGOT ===============*/
const loginAcessRegister  = document.getElementById('loginAccessRegister');
const buttonRegister      = document.getElementById('loginButtonRegister');
const buttonAccess        = document.getElementById('loginButtonAccess');
const buttonForgot        = document.getElementById('loginButtonForgot');
const buttonBackFromForgot= document.getElementById('loginButtonBackFromForgot');

buttonRegister?.addEventListener('click', () => {
  loginAcessRegister.classList.remove('forgot-active');
  loginAcessRegister.classList.add('active');
});

buttonAccess?.addEventListener('click', () => {
  loginAcessRegister.classList.remove('active');
  loginAcessRegister.classList.remove('forgot-active');
});

buttonForgot?.addEventListener('click', () => {
  loginAcessRegister.classList.remove('active');
  loginAcessRegister.classList.add('forgot-active');
});

buttonBackFromForgot?.addEventListener('click', () => {
  loginAcessRegister.classList.remove('forgot-active');
});

/*=============== FORGOT PASSWORD FORM (inline panel) ===============*/
document.getElementById('forgotPasswordForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = e.target.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.innerHTML = '<i class="ri-loader-4-line"></i> Sending...';

  try {
    const res = await fetch(API + '/forgot-password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: document.getElementById('forgot_email').value.trim() })
    });
    const data = await res.json();

    if (data.success) {
      Swal.fire({ icon: 'success', title: 'Code sent!', text: data.message, confirmButtonColor: '#DD0426' })
        .then(() => {
          if (data.verify_url) window.location.href = data.verify_url;
        });
    } else {
      Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#DD0426' });
    }
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Check XAMPP Apache & MySQL.' });
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="ri-send-plane-fill"></i> Send verification code';
});