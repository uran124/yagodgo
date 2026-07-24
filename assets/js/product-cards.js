(function () {
  'use strict';

  function integer(value, fallback) {
    var parsed = parseInt(value, 10);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  function initCard(card) {
    if (card.dataset.productCardReady === '1') return;
    card.dataset.productCardReady = '1';

    var quantity = card.querySelector('[data-qty-input]');
    var quantityWrap = card.querySelector('[data-card-qty]');
    var cartQuantity = card.querySelector('[data-cart-quantity]');
    var max = Math.max(1, integer((quantityWrap && quantityWrap.dataset.max) || (quantity && quantity.max), 99));

    function syncQuantity(value) {
      var next = Math.max(1, Math.min(max, integer(value, 1)));
      if (quantity) quantity.value = String(next);
      if (cartQuantity) cartQuantity.value = String(next);
      return next;
    }

    if (quantity) {
      quantity.addEventListener('change', function () { syncQuantity(quantity.value); });
      quantity.addEventListener('input', function () { syncQuantity(quantity.value); });
      syncQuantity(quantity.value);
    }
    var minus = card.querySelector('[data-qty-minus]');
    var plus = card.querySelector('[data-qty-plus]');
    if (minus) minus.addEventListener('click', function () { syncQuantity(integer(quantity && quantity.value, 1) - 1); });
    if (plus) plus.addEventListener('click', function () { syncQuantity(integer(quantity && quantity.value, 1) + 1); });

    var preorderButton = card.querySelector('.preorder-intent-btn');
    if (preorderButton) initPreorder(preorderButton, syncQuantity, max, card);
  }

  function initPreorder(button, syncQuantity, max, card) {
    button.addEventListener('click', function () {
      var requested = syncQuantity(card.querySelector('[data-qty-input]')?.value || 1);
      var price = Number(button.dataset.preorderPrice || 0);
      if (!Number.isFinite(price) || price <= 0) return;

      var form = document.createElement('form');
      form.className = 'preorder-dialog';
      form.innerHTML = '<div class="preorder-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="preorder-title">'
        + '<button type="button" class="preorder-dialog__close" aria-label="Закрыть">×</button>'
        + '<h2 id="preorder-title">Предзаказ</h2><p class="preorder-dialog__product"></p>'
        + '<label>Количество ящиков<input name="requested_boxes" type="number" min="1" max="' + max + '" step="1" value="' + requested + '"></label>'
        + '<label>Желаемая дата получения<input name="desired_delivery_date" type="date"></label>'
        + '<p class="preorder-dialog__price"></p><p class="preorder-dialog__notice">Точная стоимость будет подтверждена после поступления в магазин.</p>'
        + '<div class="preorder-dialog__actions"><button type="button" class="preorder-dialog__cancel">Отмена</button><button type="submit">Оформить предзаказ</button></div></div>';
      document.body.appendChild(form);

      var date = form.elements.desired_delivery_date;
      var minimum = new Date(); minimum.setDate(minimum.getDate() + 2);
      var minimumIso = minimum.toISOString().slice(0, 10);
      date.min = minimumIso;
      var supplyDate = button.dataset.supplyDate || button.dataset.deliveryDate || '';
      date.value = supplyDate >= minimumIso ? supplyDate : minimumIso;
      form.querySelector('.preorder-dialog__product').textContent = button.dataset.productTitle || '';
      form.querySelector('.preorder-dialog__price').textContent = 'Ожидаемая цена: ' + Math.round(price).toLocaleString('ru-RU') + ' ₽ за ящик';

      function close() { form.remove(); }
      form.querySelector('.preorder-dialog__close').addEventListener('click', close);
      form.querySelector('.preorder-dialog__cancel').addEventListener('click', close);
      form.addEventListener('click', function (event) { if (event.target === form) close(); });
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        var submit = form.querySelector('[type="submit"]');
        submit.disabled = true;
        var payload = new URLSearchParams({
          product_id: button.dataset.productId || '0',
          requested_boxes: String(Math.max(1, Math.min(max, integer(form.elements.requested_boxes.value, 1)))),
          source_section: button.dataset.sourceSection || '',
          source_delivery_date: button.dataset.deliveryDate || '',
          desired_delivery_date: date.value,
          expected_price_per_box: button.dataset.preorderPrice || '0',
          discount_percent_snapshot: button.dataset.preorderDiscount || '0'
        });
        fetch('/preorder-intents', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload.toString() })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            var message = card.querySelector('[data-card-message]');
            if (message) { message.textContent = data.message || data.error || 'Не удалось оформить предзаказ.'; message.classList.remove('hidden'); message.classList.toggle('is-error', !data.ok); }
            if (data.ok) close(); else submit.disabled = false;
          })
          .catch(function () { submit.disabled = false; });
      });
    });
  }

  function init() { document.querySelectorAll('[data-product-card]').forEach(initCard); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
