<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Админка BerryGo – <?= htmlspecialchars($pageTitle) ?></title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
  <style>
    [data-theme='dark'] body {
      background-color: #1f2937;
      color: #f1f5f9;
    }
    [data-theme='dark'] .bg-white { background-color: #374151; }
    [data-theme='dark'] .bg-gray-100 { background-color: #1f2937; }
    [data-theme='dark'] .bg-gray-50 { background-color: #111827; }
    [data-theme='dark'] .text-gray-700 { color: #e5e7eb; }
    [data-theme='dark'] .text-gray-600 { color: #d1d5db; }
    [data-theme='dark'] .text-gray-500 { color: #9ca3af; }
    [data-theme='dark'] .border-gray-200 { border-color: #374151; }
  </style>
</head>
<body class="flex flex-col min-h-screen bg-gray-100 font-sans">

  <!-- Header -->
  <header class="flex items-center justify-between bg-white p-4 shadow">
    <div class="flex items-center space-x-4">
      <div class="font-bold text-xl text-[#C86052]">BerryGo Admin</div>
      <nav class="hidden md:flex space-x-4">
        <a href="/admin/dashboard" class="hover:underline">Dashboard</a>
        <a href="/admin/orders" class="hover:underline">Заказы</a>
        <a href="/admin/products" class="hover:underline">Товары</a>
        <a href="/admin/slots" class="hover:underline">Слоты</a>
        <a href="/admin/coupons" class="hover:underline">Промокоды</a>
        <a href="/admin/users" class="hover:underline">Пользователи</a>
        <a href="/admin/settings" class="hover:underline">Настройки</a>
      </nav>
      <button id="burgerBtn" class="md:hidden p-2 text-gray-600">
        <span class="material-icons-round">menu</span>
      </button>
    </div>
    <form action="/logout" method="post">
      <button type="submit" class="flex items-center text-red-500 hover:underline">
        <span class="material-icons-round mr-1">logout</span> Выход
      </button>
    </form>
  </header>

  <!-- Sidebar for small screens -->
  <aside id="sidebar" class="md:hidden fixed top-16 left-0 w-64 bg-white shadow-md transform -translate-x-full transition-transform duration-300 z-40">
    <nav class="p-4">
      <a href="/admin/dashboard" class="flex items-center p-2 mb-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2">dashboard</span>
        <span class="menu-text">Dashboard</span>
      </a>
      <a href="/admin/orders" class="flex items-center p-2 mb-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2">receipt_long</span>
        <span class="menu-text">Заказы</span>
      </a>
      <a href="/admin/products" class="flex items-center p-2 mb-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2">inventory_2</span>
        <span class="menu-text">Товары</span>
      </a>
      <a href="/admin/slots" class="flex items-center p-2 mb-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2">calendar_today</span>
        <span class="menu-text">Слоты</span>
      </a>
      <a href="/admin/coupons" class="flex items-center p-2 mb-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2">local_offer</span>
        <span class="menu-text">Промокоды</span>
      </a>
      <a href="/admin/users" class="flex items-center p-2 mb-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2">people</span>
        <span class="menu-text">Пользователи</span>
      </a>
      <a href="/admin/settings" class="flex items-center p-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2">settings</span>
        <span class="menu-text">Настройки</span>
      </a>
    </nav>
  </aside>

  <!-- Контент -->
  <div class="flex-1 flex flex-col">
    <h1 class="text-2xl font-semibold text-gray-700 p-4"><?= htmlspecialchars($pageTitle) ?></h1>
    <!-- Main -->
    <main class="p-6 overflow-auto bg-gray-50 flex-1">
      <?= $content ?>
    </main>
  </div>

  <script>
    const sidebar = document.getElementById('sidebar');
    const burgerBtn = document.getElementById('burgerBtn');

    function closeSidebar() {
      sidebar.classList.add('-translate-x-full');
    }

    function openSidebar() {
      sidebar.classList.remove('-translate-x-full');
    }

    burgerBtn?.addEventListener('click', () => {
      if (sidebar.classList.contains('-translate-x-full')) {
        openSidebar();
      } else {
        closeSidebar();
      }
    });

    document.addEventListener('click', (e) => {
      if (window.innerWidth < 768 && !sidebar.contains(e.target) && !burgerBtn.contains(e.target)) {
        closeSidebar();
      }
    });

    function handleResize() {
      if (window.innerWidth >= 768) {
        closeSidebar();
      }
    }

    window.addEventListener('resize', handleResize);
  </script>
</body>
</html>