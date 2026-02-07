<?php
$role = $_SESSION['role'] ?? '';
$base = $role === 'partner' ? '/partner' : '/manager';
$titleRole = $role === 'partner' ? 'Partner' : 'Manager';
$labelRole = $role === 'partner' ? 'Партнёр' : 'Менеджер';
?>
<?php
  $lightTheme = get_theme_colors('light');
  $darkTheme  = get_theme_colors('dark');
?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Админка BerryGo – <?= htmlspecialchars($pageTitle) ?></title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
  <style>
    :root {
      --accent-primary: <?= htmlspecialchars($lightTheme['primary']) ?>;
      --accent-hover: <?= htmlspecialchars($lightTheme['secondary']) ?>;
      --accent-strong: <?= htmlspecialchars($lightTheme['strong']) ?>;
      --accent-soft: <?= htmlspecialchars($lightTheme['soft']) ?>;
      --accent-contrast: <?= htmlspecialchars($lightTheme['contrast']) ?>;
    }

    [data-theme='dark'] {
      --accent-primary: <?= htmlspecialchars($darkTheme['primary']) ?>;
      --accent-hover: <?= htmlspecialchars($darkTheme['secondary']) ?>;
      --accent-strong: <?= htmlspecialchars($darkTheme['strong']) ?>;
      --accent-soft: <?= htmlspecialchars($darkTheme['soft']) ?>;
      --accent-contrast: <?= htmlspecialchars($darkTheme['contrast']) ?>;
    }

    .bg-\[\#C86052\] { background-color: var(--accent-primary) !important; color: var(--accent-contrast); }
    .hover\:bg-\[\#C86052\]:hover { background-color: var(--accent-hover) !important; color: var(--accent-contrast); }
    .text-\[\#C86052\] { color: var(--accent-primary) !important; }
    .hover\:text-\[\#C86052\]:hover { color: var(--accent-hover) !important; }
    .focus\:ring-\[\#C86052\]:focus { box-shadow: 0 0 0 2px var(--accent-primary) !important; outline: none; }
    .bg-\[\#C86052\]\/20 { background-color: var(--accent-soft) !important; color: var(--accent-primary) !important; }
    .accent-gradient { background: linear-gradient(135deg, var(--accent-strong), var(--accent-hover)); color: var(--accent-contrast); }
    .accent-focus:focus-visible { outline: 2px solid var(--accent-primary); outline-offset: 2px; }
    .accent-text { color: var(--accent-primary); }

    /* Основная темная тема */
    [data-theme='dark'] body {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      color: #f8fafc;
    }

    /* Градиентные фоны для карточек */
    [data-theme='dark'] .bg-white {
      background: linear-gradient(145deg, #1e293b, #334155);
      border: 1px solid #334155;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }

    [data-theme='dark'] .bg-gray-100 {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    }

    [data-theme='dark'] .bg-gray-50 {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    }

    [data-theme='dark'] .bg-gray-200 {
      background: linear-gradient(145deg, #334155, #475569);
      color: #e2e8f0;
    }

    /* Цвета текста */
    [data-theme='dark'] .text-gray-700 { color: #e2e8f0; }
    [data-theme='dark'] .text-gray-600 { color: #cbd5e1; }
    [data-theme='dark'] .text-gray-500 { color: #94a3b8; }

    /* Границы с неоновым эффектом */
    [data-theme='dark'] .border-gray-200 {
      border-color: #334155;
      box-shadow: inset 0 1px 0 rgba(148, 163, 184, 0.1);
    }

    /* Ховер эффекты */
    [data-theme='dark'] .hover\:bg-gray-50:hover {
      background: linear-gradient(145deg, #334155, #475569) !important;
      transform: translateY(-1px);
      box-shadow: 0 4px 20px rgba(200, 96, 82, 0.2);
      transition: all 0.3s ease;
    }

    [data-theme='dark'] .hover\:bg-gray-200:hover {
      background: linear-gradient(145deg, #475569, #64748b) !important;
      transform: translateY(-1px);
      box-shadow: 0 4px 20px rgba(200, 96, 82, 0.15);
    }

    /* Анимации и переходы */
    [data-theme='dark'] * {
      transition: all 0.2s ease;
    }

    /* Неоновые акценты для активных элементов */
    [data-theme='dark'] .text-\[\#C86052\],
    [data-theme='dark'] .accent-text {
      color: var(--accent-primary) !important;
      text-shadow: 0 0 10px color-mix(in srgb, var(--accent-primary) 40%, transparent);
    }

    /* Кнопки и ссылки */
    [data-theme='dark'] a:hover {
      text-shadow: 0 0 8px rgba(200, 96, 82, 0.5);
      transform: translateX(2px);
    }

    /* Header с глянцевым эффектом */
    [data-theme='dark'] header {
      background: linear-gradient(145deg, #1e293b, #334155);
      border-bottom: 1px solid #475569;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(10px);
    }

    /* Sidebar с улучшенным стилем */
    [data-theme='dark'] aside {
      background: linear-gradient(180deg, #1e293b 0%, #334155 100%);
      border-right: 1px solid #475569;
      box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
      backdrop-filter: blur(10px);
    }

    /* Таблицы с современным стилем */
    [data-theme='dark'] table {
      background: linear-gradient(145deg, #1e293b, #334155);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
    }

    [data-theme='dark'] thead {
      background: linear-gradient(145deg, #334155, #475569);
    }

    [data-theme='dark'] tbody tr:hover {
      background: linear-gradient(90deg, rgba(200, 96, 82, 0.1), transparent) !important;
    }

    /* Форматирование для input элементов */
    [data-theme='dark'] input[type="checkbox"] {
      accent-color: var(--accent-primary);
      transform: scale(1.2);
    }

    /* Кастомные скроллбары */
    [data-theme='dark'] ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    [data-theme='dark'] ::-webkit-scrollbar-track {
      background: #1e293b;
      border-radius: 4px;
    }

    [data-theme='dark'] ::-webkit-scrollbar-thumb {
      background: linear-gradient(145deg, #475569, #64748b);
      border-radius: 4px;
    }

    [data-theme='dark'] ::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(145deg, #64748b, #94a3b8);
    }

    /* Иконки с неоновым эффектом */
    [data-theme='dark'] .material-icons-round {
      filter: drop-shadow(0 0 2px rgba(200, 96, 82, 0.3));
    }

    /* Пульсирующая анимация для логотипа */
    [data-theme='dark'] .font-bold.text-xl {
      animation: pulse-glow 3s ease-in-out infinite alternate;
    }

    @keyframes pulse-glow {
      from {
        text-shadow: 0 0 5px rgba(200, 96, 82, 0.5);
      }
      to {
        text-shadow: 0 0 20px rgba(200, 96, 82, 0.8), 0 0 30px rgba(200, 96, 82, 0.4);
      }
    }

    /* Улучшенные тени для карточек */
    [data-theme='dark'] .shadow {
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.05);
    }

    [data-theme='dark'] .shadow-md {
      box-shadow: 0 15px 50px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.08);
    }

    /* Плавный градиент для main области */
    [data-theme='dark'] main {
      background: radial-gradient(ellipse at top, #1e293b 0%, #0f172a 100%);
      border-radius: 12px 12px 0 0;
      margin: 8px;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
    }

    /* Дополнительные стили для таблиц и переключателей */
    [data-theme='dark'] .bg-red-100 {
      background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.3)) !important;
      color: #fca5a5 !important;
    }

    [data-theme='dark'] .text-red-800 {
      color: #fca5a5 !important;
    }

    [data-theme='dark'] .bg-green-100 {
      background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(22, 163, 74, 0.3)) !important;
      color: #86efac !important;
    }

    [data-theme='dark'] .text-green-800 {
      color: #86efac !important;
    }

    [data-theme='dark'] .peer-checked\:bg-red-600:checked {
      background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
      box-shadow: 0 0 10px rgba(220, 38, 38, 0.5);
    }

    [data-theme='dark'] .bg-gray-200 {
      background: linear-gradient(135deg, #475569, #64748b) !important;
    }

    [data-theme='dark'] .after\:bg-white::after {
      background: #f1f5f9 !important;
    }

    [data-theme='dark'] .border-\[\#C86052\],
    [data-theme='dark'] .accent-border {
      border-color: color-mix(in srgb, var(--accent-primary) 65%, transparent) !important;
      color: var(--accent-primary) !important;
    }

    [data-theme='dark'] .hover\:bg-\[\#C86052\]:hover,
    [data-theme='dark'] .accent-hover:hover {
      background: linear-gradient(135deg, var(--accent-primary), var(--accent-hover)) !important;
      box-shadow: 0 4px 12px color-mix(in srgb, var(--accent-primary) 45%, transparent);
    }

    [data-theme='dark'] select {
      background: #334155;
      color: #f8fafc;
      border-color: #475569;
    }

    [data-theme='dark'] select option {
      background: #334155;
      color: #f8fafc;
    }

    [data-theme='dark'] .status-btn {
      background: #334155;
      color: #f8fafc;
    }

    [data-theme='dark'] .status-btn:hover {
      background: #475569;
    }

    @media (max-width: 768px) {
      body { font-size: 14px; }
    }
  </style>
</head>
<body class="flex flex-col min-h-screen bg-gray-100 font-sans">

  <!-- Header -->
  <header class="flex items-center justify-between bg-white p-2 md:p-4 shadow">
    <div class="flex items-center space-x-2 md:space-x-4">
      <div class="font-bold text-lg md:text-xl text-[#C86052]">BerryGo <?= htmlspecialchars($titleRole) ?></div>
      <nav class="hidden md:flex space-x-2 md:space-x-4">
        <a href="<?= $base ?>/orders" class="hover:underline">Заказы</a>
        <a href="<?= $base ?>/products" class="hover:underline">Товары</a>
        <a href="<?= $base ?>/users" class="hover:underline">Пользователи</a>
        <a href="<?= $base ?>/profile" class="hover:underline">Профиль</a>
      </nav>
      <button id="burgerBtn" class="md:hidden p-2 text-gray-600">
        <span class="material-icons-round">menu</span>
      </button>
    </div>
    <form action="/logout" method="post">
      <?= csrf_field() ?>
      <button type="submit" class="flex items-center text-red-500 hover:underline">
        <span class="material-icons-round mr-1">logout</span> Выход
      </button>
    </form>
  </header>

  <!-- Sidebar for small screens -->
  <aside id="sidebar" class="md:hidden fixed top-16 left-0 w-52 md:w-64 bg-white shadow-md transform -translate-x-full transition-transform duration-300 z-40">
    <nav class="p-2 md:p-4">
      <a href="<?= $base ?>/orders" class="flex items-center p-2 mb-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2 text-base md:text-lg">receipt_long</span>
        <span class="menu-text text-sm md:text-base">Заказы</span>
      </a>
      <a href="<?= $base ?>/products" class="flex items-center p-2 mb-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2 text-base md:text-lg">inventory_2</span>
        <span class="menu-text text-sm md:text-base">Товары</span>
      </a>
      <a href="<?= $base ?>/users" class="flex items-center p-2 rounded hover:bg-gray-200">
        <span class="material-icons-round mr-2 text-base md:text-lg">people</span>
        <span class="menu-text text-sm md:text-base">Пользователи</span>
      </a>
      <a href="<?= $base ?>/profile" class="flex items-center p-2 mt-2 rounded hover:bg-gray-200">
      <span class="material-icons-round mr-2 text-base md:text-lg">account_circle</span>
      <span class="menu-text text-sm md:text-base">Профиль</span>
      </a>
    </nav>
  </aside>

  <!-- Контент -->
  <div class="flex-1 flex flex-col">
    <h1 class="text-xl md:text-2xl font-semibold text-gray-700 p-2 md:p-4"><?= htmlspecialchars($pageTitle) ?></h1>
    <!-- Main -->
    <main class="p-0 sm:p-3 md:p-6 overflow-auto bg-gray-50 flex-1">
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
<?php include __DIR__ . '/scripts.php'; ?>
</body>
</html>
