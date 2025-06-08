<?php /** @var array|null $coupon */ ?>
<form action="/admin/coupons/save" method="post" class="bg-white p-6 rounded shadow max-w-md space-y-4">
  <?php if ($coupon): ?>
    <input type="hidden" name="id" value="<?= $coupon['id'] ?>">
  <?php endif; ?>
  <div>
    <label class="block mb-1">Код</label>
    <input name="code" type="text" value="<?= htmlspecialchars($coupon['code'] ?? '') ?>" class="w-full border px-2 py-1 rounded" required>
  </div>
  <div>
    <label class="block mb-1">Тип</label>
    <select name="type" class="w-full border px-2 py-1 rounded">
      <option value="discount" <?= ($coupon['type'] ?? '')==='discount' ? 'selected' : '' ?>>Скидка %</option>
      <option value="points" <?= ($coupon['type'] ?? '')==='points' ? 'selected' : '' ?>>Баллы</option>
      <option value="registration" <?= ($coupon['type'] ?? '')==='registration' ? 'selected' : '' ?>>При регистрации</option>
    </select>
  </div>
  <div>
    <label class="block mb-1">Скидка %</label>
    <input type="number" name="discount" value="<?= $coupon['discount'] ?? 0 ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Баллы</label>
    <input type="number" name="points" value="<?= $coupon['points'] ?? 0 ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Истекает</label>
    <input type="date" name="expires_at" value="<?= htmlspecialchars($coupon['expires_at'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div class="flex items-center space-x-2">
    <input type="checkbox" name="is_active" value="1" <?= empty($coupon) || $coupon['is_active'] ? 'checked' : '' ?>>
    <span>Активен</span>
  </div>
  <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Сохранить</button>
</form>
