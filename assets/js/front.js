(function () {
  'use strict';
  function initForm(form) {
    var root = form.closest('.alw-gc-wrap');
    if (!root) return;
    var choices = form.querySelectorAll('input[name="alw_gc_amount_choice"]');
    var custom = form.querySelector('.alw-gc-custom');
    var customInput = form.querySelector('#alw-gc-custom-amount');
    var preview = root.querySelector('.alw-gc-preview-value');
    var buttonValue = form.querySelector('.alw-gc-button-value');
    var targets = form.querySelectorAll('input[name="alw_gc_target"]');
    var recipient = form.querySelector('.alw-gc-recipient');
    var email = form.querySelector('#alw-gc-recipient-email');
    function updateAmount() {
      var selected = form.querySelector('input[name="alw_gc_amount_choice"]:checked');
      if (!selected) return;
      var isCustom = selected.value === 'custom';
      custom.hidden = !isCustom;
      customInput.required = isCustom;
      var amount = isCustom ? Number(customInput.value) : Number(selected.value);
      var label = (Number.isFinite(amount) && amount > 0 ? amount : 0).toLocaleString('de-DE', { minimumFractionDigits: amount % 1 ? 2 : 0, maximumFractionDigits: 2 }) + ' €';
      if (preview) preview.textContent = label;
      if (buttonValue) buttonValue.textContent = label;
    }
    function updateTarget() {
      var selected = form.querySelector('input[name="alw_gc_target"]:checked');
      var friend = selected && selected.value === 'friend';
      recipient.hidden = !friend;
      email.required = !!friend;
      if (friend) email.setAttribute('autocomplete', 'email');
    }
    Array.prototype.forEach.call(choices, function (input) { input.addEventListener('change', updateAmount); });
    customInput.addEventListener('input', updateAmount);
    Array.prototype.forEach.call(targets, function (input) { input.addEventListener('change', updateTarget); });
    updateAmount();
    updateTarget();
  }
  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('.alw-gc-buy'), initForm);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();