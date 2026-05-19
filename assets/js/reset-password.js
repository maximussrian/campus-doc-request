/* API from app-config.js */
const token = new URLSearchParams(window.location.search).get('token');

if (!token) {
  Swal.fire({ icon: 'error', title: 'Invalid link', text: 'This reset link is invalid or has expired.' }).then(() => {
    window.location.href = 'forgot-password.php';
  });
} else {
  document.getElementById('resetPasswordForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const pass = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    if (pass !== confirm) {
      Swal.fire({ icon: 'error', title: 'Error', text: 'Passwords do not match' });
      return;
    }
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Resetting...';
    try {
      const res = await fetch(API + '/reset-password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, password: pass })
      });
      const data = await res.json();
      if (data.success) {
        Swal.fire({ icon: 'success', title: 'Password reset', text: data.message }).then(() => {
          window.location.href = 'index.php';
        });
      } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
      }
    } catch (err) {
      Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Please try again.' });
    }
    btn.disabled = false;
    btn.textContent = 'Reset password';
  });
}

document.getElementById('togglePassword')?.addEventListener('click', () => {
  const el = document.getElementById('new_password');
  el.type = el.type === 'password' ? 'text' : 'password';
});
document.getElementById('toggleConfirm')?.addEventListener('click', () => {
  const el = document.getElementById('confirm_password');
  el.type = el.type === 'password' ? 'text' : 'password';
});
