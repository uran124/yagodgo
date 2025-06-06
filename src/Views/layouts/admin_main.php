<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Админка ЯгодGO – <?= htmlspecialchars($pageTitle) ?></title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
</head>
<body class="flex h-screen bg-gray-100 font-sans">

  <!-- Sidebar -->
  <aside class="w-64 bg-white shadow-md">
    <div class="p-4 text-xl font-bold text-[#C86052]">ЯгодGO Admin</div>
    <nav class="px-4">
      <a href="/admin/dashboard" class="flex items-center p-2 mb-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2">dashboard</span> Dashboard
      </a>
      <a href="/admin/orders" class="flex items-center p-2 mb-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2">receipt_long</span> Заказы
      </a>
      <a href="/admin/products" class="flex items-center p-2 mb-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2">inventory_2</span> Товары
      </a>
      <a href="/admin/slots" class="flex items-center p-2 mb-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2">calendar_today</span> Слоты
      </a>
      <a href="/admin/coupons" class="flex items-center p-2 mb-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2">local_offer</span> Промокоды
      </a>
      <a href="/admin/users" class="flex items-center p-2 mb-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2">people</span> Пользователи
      </a>
      <a href="/admin/settings" class="flex items-center p-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2">settings</span> Настройки
      </a>
    </nav>
  </aside>

  <!-- Контент -->
  <div class="flex-1 flex flex-col">
    <!-- Header -->
    <header class="flex items-center justify-between bg-white p-4 shadow">
      <h1 class="text-2xl font-semibold text-gray-700"><?= htmlspecialchars($pageTitle) ?></h1>
      <form action="/logout" method="post">
        <button type="submit" class="flex items-center text-red-500 hover:underline">
          <span class="material-icons-round mr-1">logout</span> Выход
        </button>
      </form>
    </header>
    <!-- Main -->
    <main class="p-6 overflow-auto bg-gray-50 flex-1">
      <?= $content ?>
    </main>
  </div>

</body>
</html>
