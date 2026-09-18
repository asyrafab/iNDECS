/* iNDECS theme preference — mode (light/dark/system) and accent.
   Stored per browser in localStorage; applied to <html> as
   data-theme and data-accent. Load this in <head> BEFORE the
   stylesheet render so there is no flash of the wrong theme:

     <script src="{{ asset('js/theme.js') }}"></script>

   To persist the choice per user instead, POST the two values to
   your own endpoint from onChange below and render the attributes
   server-side on <html>. */
(function () {
  var MODE = 'indecs.theme.mode';   // 'light' | 'dark' | 'system'
  var ACCENT = 'indecs.theme.accent'; // 'merah' | 'hijau' | 'biru' | 'ambar'
  var root = document.documentElement;

  function apply() {
    var mode = localStorage.getItem(MODE) || 'system';
    if (mode === 'system') root.removeAttribute('data-theme');
    else root.setAttribute('data-theme', mode);

    var accent = localStorage.getItem(ACCENT) || 'merah';
    root.setAttribute('data-accent', accent);
    sync();
  }

  function sync() {
    var mode = localStorage.getItem(MODE) || 'system';
    var accent = localStorage.getItem(ACCENT) || 'merah';
    document.querySelectorAll('[data-theme-mode]').forEach(function (b) {
      b.setAttribute('aria-pressed', String(b.dataset.themeMode === mode));
    });
    document.querySelectorAll('[data-theme-accent]').forEach(function (b) {
      b.setAttribute('aria-pressed', String(b.dataset.themeAccent === accent));
    });
  }

  window.iNDECSTheme = {
    setMode: function (m) { localStorage.setItem(MODE, m); apply(); },
    setAccent: function (a) { localStorage.setItem(ACCENT, a); apply(); }
  };

  apply();

  document.addEventListener('click', function (e) {
    var mode = e.target.closest('[data-theme-mode]');
    if (mode) { window.iNDECSTheme.setMode(mode.dataset.themeMode); return; }
    var accent = e.target.closest('[data-theme-accent]');
    if (accent) window.iNDECSTheme.setAccent(accent.dataset.themeAccent);
  });

  document.addEventListener('DOMContentLoaded', sync);
})();
