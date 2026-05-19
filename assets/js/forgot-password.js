/* API from app-config.js */

document.getElementById('forgotPasswordForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = e.target.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'Sending...';

  try {
    const res = await fetch(API + '/forgot-password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: document.getElementById('forgot_email').value.trim() })
    });
    const data = await res.json();

    if (data.success) {
      Swal.fire({ icon: 'success', title: 'Code sent', text: data.message }).then(() => {
        if (data.verify_url) window.location.href = data.verify_url;
        else window.location.href = 'index.php';
      });
    } else {
      Swal.fire({ icon: 'error', title: 'Error', text: data.message });
    }
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Check XAMPP Apache & MySQL.' });
  }

  btn.disabled = false;
  btn.textContent = 'Send verification code';
});
