/**
 * 🔐 Auth UI Controller
 */
const AuthUI = {
  showLogin() {
    document.body.innerHTML = `
      <div class="starfield"></div>
      <div class="auth-page">
        <div class="auth-card glass">
          <div class="auth-logo gold-text">NexusChat</div>
          <div class="auth-subtitle">✨ به کهکشان گفتگو خوش آمدید</div>
          <div id="authError"></div>
          <form id="loginForm">
            <input class="auth-input" name="identifier" placeholder="نام کاربری یا ایمیل" required autocomplete="username">
            <input class="auth-input" type="password" name="password" placeholder="رمز عبور" required autocomplete="current-password">
            <button class="btn-primary" type="submit">🚀 ورود به کهکشان</button>
          </form>
          <div class="auth-link">حساب ندارید؟ <a href="register">ثبت‌نام کنید</a></div>
        </div>
      </div>
    `;
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      App.showLoading();
      const res = await App.login(fd.get('identifier'), fd.get('password'));
      App.hideLoading();
      if (!res.success) {
        const err = document.getElementById('authError');
        err.innerHTML = `<div class="auth-error">${App.escapeHTML(res.message || 'خطا')}</div>`;
        setTimeout(() => err.innerHTML = '', 4000);
      }
    });
  },

  showRegister() {
    document.body.innerHTML = `
      <div class="starfield"></div>
      <div class="auth-page">
        <div class="auth-card glass">
          <div class="auth-logo gold-text">NexusChat</div>
          <div class="auth-subtitle">🌟 ساخت حساب جدید</div>
          <div id="authError"></div>
          <form id="registerForm">
            <input class="auth-input" name="display_name" placeholder="نام نمایشی" required>
            <input class="auth-input" name="username" placeholder="نام کاربری (انگلیسی)" required pattern="[a-zA-Z0-9_]+">
            <input class="auth-input" name="email" type="email" placeholder="ایمیل" required>
            <input class="auth-input" name="phone" placeholder="شماره تلفن (اختیاری)">
            <input class="auth-input" type="password" name="password" placeholder="رمز عبور (حداقل ۶ کاراکتر)" required minlength="6">
            <button class="btn-primary" type="submit">✨ ساخت حساب</button>
          </form>
          <div class="auth-link">حساب دارید؟ <a href="login">ورود</a></div>
        </div>
      </div>
    `;
    document.getElementById('registerForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      App.showLoading();
      const res = await App.register(Object.fromEntries(fd));
      App.hideLoading();
      if (!res.success) {
        const err = document.getElementById('authError');
        err.innerHTML = `<div class="auth-error">${App.escapeHTML(res.message || 'خطا')}</div>`;
      }
    });
  }
};

if (window.location.pathname.includes('register')) {
  document.addEventListener('DOMContentLoaded', () => AuthUI.showRegister());
} else if (window.location.pathname.includes('login')) {
  document.addEventListener('DOMContentLoaded', () => AuthUI.showLogin());
}
