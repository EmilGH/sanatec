/* Date fields (ui_date_field): a typed DD/MM/YYYY box with a Bootstrap-styled
   calendar behind it. Typing always wins — the picker never rewrites what the
   person typed, and the server accepts the same loose formats either way. */
(function () {
  if (!window.Datepicker) { return; }
  var lang = (document.documentElement.lang || 'en').slice(0, 2);
  var touch = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
  document.querySelectorAll('.st-datefield input.form-control').forEach(function (input) {
    var opts = {
      format: 'dd/mm/yyyy', autohide: true, todayHighlight: true, weekStart: 1,
      updateOnBlur: false, showOnFocus: !touch, showOnClick: !touch,
      buttonClass: 'btn', language: Datepicker.locales[lang] ? lang : 'en',
      prevArrow: '‹', nextArrow: '›'
    };
    if (input.dataset.min) { opts.minDate = input.dataset.min; }
    if (input.dataset.max) { opts.maxDate = input.dataset.max; }
    var picker = new Datepicker(input, opts);
    var button = input.parentNode.querySelector('[data-datepick]');
    if (button) {
      button.addEventListener('click', function () {
        if (picker.active) { picker.hide(); } else { picker.show(); }
      });
    }
    // Keep the picker on the typed date when it opens.
    input.addEventListener('focus', function () { picker.update({ autohide: false }); });
  });
})();
