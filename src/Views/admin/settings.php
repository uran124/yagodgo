<?php /** @var array $settings */ ?>
<form action="/admin/settings" method="post" class="bg-white p-6 rounded shadow max-w-lg space-y-4">
  <div>
    <label class="block mb-1">Название компании</label>
    <input name="company_name" type="text"
           value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>"
           class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Контактный телефон</label>
    <input name="contact_phone" type="text"
           value="<?= htmlspecialchars($settings['contact_phone'] ?? '') ?>"
           class="w-full border px-2 py-1 rounded">
  </div>
  <!-- Добавьте другие поля по аналогии -->
  <button type="submit"
          class="bg-[#C86052] text-white px-4 py-2 rounded hover:bg-[#B44D47]">
    Сохранить настройки
  </button>
</form>
