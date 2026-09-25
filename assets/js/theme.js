/* Theme for /my/ and /admin/: follows the phone, with a manual sun toggle remembered per device.
   Put in <head> before CSS paints to avoid a flash. The public site stays dark and needs none of this. */
(function () {
  var KEY = 'st-theme', root = document.documentElement;
  function saved() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
  function apply(t) { root.setAttribute('data-theme', t); root.setAttribute('data-bs-theme', t); }
  var mq = window.matchMedia('(prefers-color-scheme: light)');
  apply(saved() || (mq.matches ? 'light' : 'dark'));
  mq.addEventListener && mq.addEventListener('change', function (e) { if (!saved()) apply(e.matches ? 'light' : 'dark'); });
  window.stToggleTheme = function () {
    var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    try { localStorage.setItem(KEY, next); } catch (e) {}
    apply(next);
  };
})();
