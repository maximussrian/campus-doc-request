/* API from app-config.js */
const params = new URLSearchParams(window.location.search);
const email = params.get('email');
const OTP_EXPIRY_SECONDS = 600; // 10 minutes

function startCountdown() {
  let secondsLeft = OTP_EXPIRY_SECONDS;
  const el = document.getElementById('countdown');
  const interval = setInterval(() => {
    secondsLeft--;
    const m = Math.floor(secondsLeft / 60);
    const s = secondsLeft % 60;
    el.textContent = m + ':' + String(s).padStart(2, '0');
    if (secondsLeft <= 0) {
      clearInterval(interval);
      el.textContent = '0:00';
      el.style.color = 'var(--title-color)';
      Swal.fire({ icon: 'warning', title: 'Code expired', text: 'Please request a new code.' }).then(() => {
        window.location.href = 'forgot-password.php?email=' + encodeURIComponent(email || '');
      });
    } else if (secondsLeft <= 60) {
      el.style.color = '#c0392b';
    }
  }, 1000);
}

if (!email) {
  Swal.fire({ icon: 'error', title: 'Invalid link', text: 'Please request a new code from the forgot password page.' }).then(() => {
    window.location.href = 'forgot-password.php';
  });
} else {
  document.getElementById('emailDisplay').textContent = 'Code sent to ' + email;
  startCountdown();

  document.getElementById('verifyOtpForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const otp = document.getElementById('otp').value.replace(/\D/g, '').slice(0, 6);
    if (otp.length !== 6) {
      Swal.fire({ icon: 'error', title: 'Invalid OTP', text: 'Enter a 6-digit code.' });
      return;
    }
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Verifying...';
    try {
      const res = await fetch(API + '/verify-otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, otp })
      });
      const data = await res.json();
      if (data.success) {
        Swal.fire({ icon: 'success', title: 'Verified!', text: data.message }).then(() => {
          window.location.href = data.reset_link;
        });
      } else {
        Swal.fire({ icon: 'error', title: 'Verification failed', text: data.message });
      }
    } catch (err) {
      Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Please try again.' });
    }
    btn.disabled = false;
    btn.textContent = 'Verify & proceed';
  });

  document.getElementById('otp').addEventListener('input', (e) => {
    e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
  });
}
