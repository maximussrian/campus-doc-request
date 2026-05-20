/* API from app-config.js */
const email = new URLSearchParams(window.location.search).get('email') || '';

if (!email) window.location.href = 'index.php';

document.getElementById('displayEmail').textContent = email;

/* ── OTP box navigation ── */
const boxes = Array.from(document.querySelectorAll('.verify__otp-box'));

boxes.forEach((box, i) => {
  box.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace') {
      box.value = '';
      box.classList.remove('filled');
      if (i > 0) boxes[i - 1].focus();
      e.preventDefault();
    } else if (e.key === 'ArrowLeft' && i > 0) {
      boxes[i - 1].focus();
    } else if (e.key === 'ArrowRight' && i < 5) {
      boxes[i + 1].focus();
    }
  });

  box.addEventListener('input', (e) => {
    const val = e.target.value.replace(/\D/g, '');
    box.value = val ? val[0] : '';
    box.classList.toggle('filled', !!box.value);
    if (box.value && i < 5) boxes[i + 1].focus();
  });

  // Handle paste across all boxes
  box.addEventListener('paste', (e) => {
    e.preventDefault();
    const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
    pasted.split('').forEach((ch, idx) => {
      if (boxes[idx]) { boxes[idx].value = ch; boxes[idx].classList.add('filled'); }
    });
    const next = Math.min(pasted.length, 5);
    boxes[next].focus();
  });
});

function getOtp() {
  return boxes.map(b => b.value).join('');
}

function setBoxState(state) {
  boxes.forEach(b => {
    b.classList.remove('filled', 'success');
    if (state === 'success') b.classList.add('success');
  });
}

/* ── Countdown (10 min) ── */
let totalSeconds  = 600;
let resendSeconds = 60;
const countdownEl = document.getElementById('countdown');
const timerWrap   = document.getElementById('timerWrap');
const expiredMsg  = document.getElementById('expiredMsg');
const verifyBtn   = document.getElementById('verifyBtn');
const resendBtn   = document.getElementById('resendBtn');
const resendCD    = document.getElementById('resendCooldown');

const mainTimer = setInterval(() => {
  totalSeconds--;
  const m = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
  const s = String(totalSeconds % 60).padStart(2, '0');
  if (countdownEl) countdownEl.textContent = `${m}:${s}`;
  if (totalSeconds <= 0) {
    clearInterval(mainTimer);
    timerWrap.classList.add('hidden');
    expiredMsg.classList.remove('hidden');
    verifyBtn.disabled = true;
  }
}, 1000);

const resendTimer = setInterval(() => {
  resendSeconds--;
  resendCD.textContent = `(wait ${resendSeconds}s)`;
  if (resendSeconds <= 0) {
    clearInterval(resendTimer);
    resendBtn.disabled = false;
    resendCD.textContent = '';
  }
}, 1000);

/* ── Verify form submit ── */
document.getElementById('verifyForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const otp = getOtp();

  if (otp.length !== 6) {
    const wrap = document.querySelector('.verify__otp-wrap');
    wrap.classList.add('shake');
    setTimeout(() => wrap.classList.remove('shake'), 500);
    Swal.fire({ icon: 'warning', title: 'Incomplete Code', text: 'Please enter all 6 digits.', confirmButtonColor: '#DD0426' });
    return;
  }

  verifyBtn.disabled = true;
  verifyBtn.innerHTML = '<i class="ri-loader-4-line"></i> Verifying...';

  try {
    const res  = await fetch(API + '/verify-register-otp.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
      body: JSON.stringify({ email, otp })
    });
    const data = await res.json();

    if (data.success) {
      clearInterval(mainTimer);
      setBoxState('success');
      await Swal.fire({
        icon: 'success',
        title: 'Account Verified!',
        html: `<p>${data.message}</p><p style="font-size:13px;color:#888;margin-top:8px;">Redirecting to login...</p>`,
        timer: 2500,
        showConfirmButton: false,
        confirmButtonColor: '#DD0426'
      });
      window.location.href = 'index.php';
    } else {
      const wrap = document.querySelector('.verify__otp-wrap');
      wrap.classList.add('shake');
      setTimeout(() => wrap.classList.remove('shake'), 500);
      Swal.fire({ icon: 'error', title: 'Verification Failed', text: data.message, confirmButtonColor: '#DD0426' });
      boxes.forEach(b => { b.value = ''; b.classList.remove('filled'); });
      boxes[0].focus();
      if (data.blocked) {
        verifyBtn.disabled = true;
        resendBtn.disabled = true;
        const mins = data.blocked_minutes || 2;
        let secs = mins * 60;
        const unblockInterval = setInterval(() => {
          secs--;
          if (resendCD) resendCD.textContent = `(blocked ${Math.ceil(secs / 60)}m)`;
          if (secs <= 0) {
            clearInterval(unblockInterval);
            verifyBtn.disabled = false;
            resendBtn.disabled = false;
            if (resendCD) resendCD.textContent = '';
          }
        }, 1000);
      }
    }
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Check XAMPP Apache & MySQL.' });
  }

  verifyBtn.disabled = false;
  verifyBtn.innerHTML = '<i class="ri-shield-check-line"></i> Verify Account';
});

/* ── Resend OTP ── */
resendBtn.addEventListener('click', async () => {
  const stored = sessionStorage.getItem('registerData');
  if (!stored) {
    Swal.fire({ icon: 'error', title: 'Session Expired', text: 'Please fill the registration form again.', confirmButtonColor: '#DD0426' })
      .then(() => window.location.href = 'index.php');
    return;
  }

  resendBtn.disabled = true;
  resendBtn.textContent = 'Sending...';

  try {
    const otpUrl = (API.replace(/\/$/, '') + '/send-register-otp.php');
    const res  = await fetch(otpUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: stored,
      cache: 'no-store',
      credentials: 'same-origin',
    });
    let data;
    try {
      data = await res.json();
    } catch {
      Swal.fire({ icon: 'error', title: 'Server Error', text: 'Could not read the server response.', confirmButtonColor: '#DD0426' });
      resendBtn.disabled = false;
      resendBtn.textContent = 'Resend';
      return;
    }

    if (data.success) {
      // Reset main countdown
      totalSeconds = 600;
      timerWrap.classList.remove('hidden');
      expiredMsg.classList.add('hidden');
      verifyBtn.disabled = false;

      // Reset resend cooldown
      let cd = 60;
      resendCD.textContent = `(wait ${cd}s)`;
      const t = setInterval(() => {
        cd--;
        resendCD.textContent = `(wait ${cd}s)`;
        if (cd <= 0) { clearInterval(t); resendBtn.disabled = false; resendCD.textContent = ''; }
      }, 1000);

      if (data.show_otp && !data.email_sent) {
        Swal.fire({
          icon: 'warning',
          title: 'No Email Sent',
          html: `<p style="margin:0;line-height:1.6">${data.message}</p>`,
          confirmButtonColor: '#DD0426',
        });
      } else if (data.email_sent) {
        Swal.fire({ icon: 'success', title: 'Code Resent', text: data.message || 'A new code was sent to your @evsu.edu.ph inbox.', timer: 1800, showConfirmButton: false });
      }
      boxes.forEach(b => { b.value = ''; b.classList.remove('filled'); });
      boxes[0].focus();
    } else {
      Swal.fire({
        icon: 'error',
        title: data.email_error ? 'Email Not Sent' : 'Error',
        text: data.message,
        confirmButtonColor: '#DD0426',
      });
      resendBtn.disabled = false;
    }
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Please try again.' });
    resendBtn.disabled = false;
  }

  resendBtn.textContent = 'Resend';
  resendCD.textContent  = '';
});

// Auto-focus first box
boxes[0].focus();
