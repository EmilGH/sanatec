/* Public page: keep deep links working with the CSS-only tabs on phones.
   A link to #adventures, or to a row inside it, switches to that tab first. */
(function () {
  function sync() {
    var id = location.hash.slice(1);
    if (!id) return;
    var el = document.getElementById(id);
    if (!el) return;
    var section = el.closest('#training, #adventures');
    if (!section) return;
    var radio = document.getElementById('seg-' + section.id);
    if (radio && !radio.checked) { radio.checked = true; el.scrollIntoView(); }
  }
  window.addEventListener('hashchange', sync);
  sync();
})();
