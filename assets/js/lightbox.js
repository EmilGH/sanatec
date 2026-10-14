/* Tap-to-enlarge for item photos. Every [data-lightbox] link is a photo; the
   link itself still opens the image when scripts are off. One <dialog> is
   built on first use. Keys: Esc closes, arrows move; swipe moves on touch. */
(function () {
  var links = Array.prototype.slice.call(document.querySelectorAll('[data-lightbox]'));
  if (!links.length || !window.HTMLDialogElement) { return; }
  var photos = links.map(function (a) { return { src: a.getAttribute('href'), caption: a.dataset.caption || '' }; });
  var dialog = null, img, cap, count, prev, next, index = 0, startX = null;

  function svg(d) { return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + d + '"/></svg>'; }
  function build() {
    dialog = document.createElement('dialog');
    dialog.className = 'st-lightbox';
    dialog.innerHTML = '<div class="st-lightbox__stage"><img alt=""></div>'
      + '<div class="st-lightbox__bar"><span class="st-lightbox__cap"></span><small class="st-lightbox__count"></small></div>'
      + '<button type="button" class="st-lightbox__prev" aria-label="Previous">' + svg('m15 6-6 6 6 6') + '</button>'
      + '<button type="button" class="st-lightbox__next" aria-label="Next">' + svg('m9 6 6 6-6 6') + '</button>'
      + '<button type="button" class="st-lightbox__close" aria-label="Close">' + svg('M6 6l12 12M18 6 6 18') + '</button>';
    document.body.appendChild(dialog);
    img = dialog.querySelector('img'); cap = dialog.querySelector('.st-lightbox__cap'); count = dialog.querySelector('.st-lightbox__count');
    prev = dialog.querySelector('.st-lightbox__prev'); next = dialog.querySelector('.st-lightbox__next');
    prev.addEventListener('click', function () { show(index - 1); });
    next.addEventListener('click', function () { show(index + 1); });
    dialog.querySelector('.st-lightbox__close').addEventListener('click', function () { dialog.close(); });
    dialog.querySelector('.st-lightbox__stage').addEventListener('click', function (e) { if (e.target === e.currentTarget) { dialog.close(); } });
    dialog.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { show(index - 1); } else if (e.key === 'ArrowRight') { show(index + 1); }
    });
    dialog.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
    dialog.addEventListener('touchend', function (e) {
      if (startX === null) { return; }
      var dx = e.changedTouches[0].clientX - startX; startX = null;
      if (Math.abs(dx) > 40) { show(index + (dx < 0 ? 1 : -1)); }
    });
    dialog.addEventListener('close', function () { img.removeAttribute('src'); });
  }
  function show(i) {
    if (i < 0 || i >= photos.length) { return; }
    index = i;
    img.src = photos[i].src; img.alt = photos[i].caption;
    cap.textContent = photos[i].caption;
    count.textContent = photos.length > 1 ? (i + 1) + ' / ' + photos.length : '';
    prev.hidden = i === 0; next.hidden = i === photos.length - 1;
    var n = photos[i + 1]; if (n) { (new Image()).src = n.src; }      // have the next one ready
  }
  links.forEach(function (a, i) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      if (!dialog) { build(); }
      show(i);
      if (!dialog.open) { dialog.showModal(); }
    });
  });
})();
