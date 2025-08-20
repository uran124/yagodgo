<?php /** @var array|null $seller */ ?>
<?php $base = '/admin'; ?>
<form action="<?= $base ?>/sellers/save" method="post" class="bg-white p-6 rounded shadow max-w-md space-y-4">
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
  <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Сохранить</button>
</form>
