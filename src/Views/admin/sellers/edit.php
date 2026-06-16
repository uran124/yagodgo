<?php /** @var array|null $seller */ ?>
<?php $base = '/admin'; ?>
<form action="<?= $base ?>/sellers/save" method="post" class="bg-white p-6 rounded shadow max-w-md space-y-4">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $seller['id'] ?? '' ?>">
  <div>
    <label class="block mb-1">Название компании</label>
    <input type="text" name="company_name" value="<?= htmlspecialchars($seller['company_name'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Адрес самовывоза</label>
    <input type="text" name="pickup_address" value="<?= htmlspecialchars($seller['pickup_address'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Стоимость доставки</label>
    <input type="number" step="0.01" name="delivery_cost" value="<?= htmlspecialchars($seller['delivery_cost'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Режим работы</label>
    <select name="work_mode" class="w-full border px-2 py-1 rounded">
      <option value="berrygo_store" <?= ($seller['work_mode'] ?? '')==='berrygo_store' ? 'selected' : '' ?>>Товар в BerryGo</option>
      <option value="own_store" <?= ($seller['work_mode'] ?? '')==='own_store' ? 'selected' : '' ?>>Со своего магазина</option>
      <option value="warehouse_delivery" <?= ($seller['work_mode'] ?? '')==='warehouse_delivery' ? 'selected' : '' ?>>Со своего склада</option>
    </select>
  </div>
  <?php $profile = $seller['partner_profile'] ?? []; ?>
  <div class="border-t pt-4">
    <h2 class="font-semibold mb-2">Модель селлера</h2>
    <label class="block mb-1">Монетизация</label>
    <select name="monetization_model" class="w-full border px-2 py-1 rounded">
      <option value="commission" <?= ($profile['monetization_model'] ?? 'commission')==='commission' ? 'selected' : '' ?>>Комиссия с продаж</option>
      <option value="subscription" <?= ($profile['monetization_model'] ?? '')==='subscription' ? 'selected' : '' ?>>Абонентская плата</option>
      <option value="commission_plus_subscription" <?= ($profile['monetization_model'] ?? '')==='commission_plus_subscription' ? 'selected' : '' ?>>Комиссия + абонентка</option>
      <option value="fixed_fee_per_order" <?= ($profile['monetization_model'] ?? '')==='fixed_fee_per_order' ? 'selected' : '' ?>>Фикс за заказ</option>
    </select>
  </div>
  <div>
    <label class="block mb-1">Комиссия, %</label>
    <input type="number" step="0.01" name="commission_rate" value="<?= htmlspecialchars((string)($profile['commission_rate'] ?? 30)) ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Абонентская плата, ₽</label>
    <input type="number" step="0.01" name="subscription_fee" value="<?= htmlspecialchars((string)($profile['subscription_fee'] ?? 0)) ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Фикс за заказ, ₽</label>
    <input type="number" step="0.01" name="fixed_fee_per_order" value="<?= htmlspecialchars((string)($profile['fixed_fee_per_order'] ?? 0)) ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Видимость селлера клиенту</label>
    <select name="client_visibility" class="w-full border px-2 py-1 rounded">
      <option value="seller_visible" <?= ($profile['client_visibility'] ?? 'seller_visible')==='seller_visible' ? 'selected' : '' ?>>Показывать селлера</option>
      <option value="berrygo_only" <?= ($profile['client_visibility'] ?? '')==='berrygo_only' ? 'selected' : '' ?>>Только berryGo</option>
    </select>
  </div>
  <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Сохранить</button>
</form>
