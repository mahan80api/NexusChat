// Add to chat.js - integrate theme builder

// In the renderShell method, add theme button to sidebar header buttons:
// <button class="icon-btn" id="themeBtn" title="تم‌ها" style="color:var(--gold)">🎨</button>

// Add event listener for theme button:
// document.getElementById('themeBtn').addEventListener('click', () => ThemeBuilder.open());

// On app load, apply active theme:
// (async () => {
//   const res = await App.api('themes', 'active');
//   if (res.success && res.theme && res.theme.css) {
//     const style = document.createElement('style');
//     style.id = 'dynamic-theme';
//     style.textContent = res.theme.css;
//     document.head.appendChild(style);
//   }
// })();
