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
      --accent-image: <?= htmlspecialchars($lightTheme['image']) ?>;
      --accent-image-alt: <?= htmlspecialchars($lightTheme['imageAlt']) ?>;
    }

    [data-theme='dark'] {
      --accent-primary: <?= htmlspecialchars($darkTheme['primary']) ?>;
      --accent-hover: <?= htmlspecialchars($darkTheme['secondary']) ?>;
      --accent-strong: <?= htmlspecialchars($darkTheme['strong']) ?>;
      --accent-soft: <?= htmlspecialchars($darkTheme['soft']) ?>;
      --accent-contrast: <?= htmlspecialchars($darkTheme['contrast']) ?>;
      --accent-image: <?= htmlspecialchars($darkTheme['image']) ?>;
      --accent-image-alt: <?= htmlspecialchars($darkTheme['imageAlt']) ?>;
    }

    .bg-\[\#C86052\] { background-color: var(--accent-primary) !important; color: var(--accent-contrast); }
    .hover\:bg-\[\#C86052\]:hover { background-color: var(--accent-hover) !important; color: var(--accent-contrast); }
    .text-\[\#C86052\] { color: var(--accent-primary) !important; }
    .hover\:text-\[\#C86052\]:hover { color: var(--accent-hover) !important; }
    .focus\:ring-\[\#C86052\]:focus { box-shadow: 0 0 0 2px var(--accent-primary) !important; outline: none; }
    .bg-\[\#C86052\]\/20 { background-color: var(--accent-soft) !important; color: var(--accent-primary) !important; }
    .hover\:bg-\[\#C86052\]\/20:hover { background-color: var(--accent-soft) !important; }
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

    /* Mobile width guard: allow nested flex/grid children and long table/form content to shrink instead of forcing horizontal page overflow. */
    .admin-content-shell,
    .admin-page-title,
    main,
    main > *,
    form,
    fieldset,
    label {
      min-width: 0;
    }

    input,
    select,
    textarea,
    button {
      max-width: 100%;
    }

    /* Mobile optimizations */
    @media (max-width: 768px) {
      table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
        width: 100%;
      }
    }

    @media (max-width: 640px) {
      html {
        font-size: 12px;
      }

      body {
        font-size: 12px;
        line-height: 1.25;
      }

      [data-theme='dark'] main {
        margin: 2px;
        border-radius: 8px 8px 0 0;
      }

      header {
        min-height: 42px;
        padding: 0.35rem 0.5rem !important;
      }

      .admin-page-title {
        gap: 0.45rem !important;
        padding: 0.55rem 0.65rem !important;
        font-size: 1.2rem !important;
        line-height: 1.18 !important;
      }

      main {
        padding: 0.25rem !important;
      }

      .font-bold.text-xl,
      .admin-page-title .material-icons-round {
        font-size: 1.25rem !important;
      }

      .bg-white,
      .bg-slate-900 {
        border-radius: 0.75rem !important;
      }

      .p-4,
      .sm\:p-6 {
        padding: 0.65rem !important;
      }

      .mb-4 {
        margin-bottom: 0.5rem !important;
      }

      .mb-3,
      .mb-2 {
        margin-bottom: 0.35rem !important;
      }

      .space-y-4 > :not([hidden]) ~ :not([hidden]),
      .space-y-3 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 0.45rem !important;
      }

      .gap-4,
      .gap-3,
      .gap-2 {
        gap: 0.45rem !important;
      }

      .text-sm {
        font-size: 0.92rem !important;
        line-height: 1.25 !important;
      }

      .text-xs {
        font-size: 0.78rem !important;
        line-height: 1.2 !important;
      }

      .text-lg {
        font-size: 1.08rem !important;
      }

      .text-xl {
        font-size: 1.18rem !important;
      }

      input,
      select,
      textarea,
      button,
      .rounded-lg.border,
      .rounded.border {
        font-size: 12px !important;
      }

      input,
      select,
      textarea {
        border-radius: 0.55rem !important;
        padding: 0.42rem 0.55rem !important;
      }

      input:not([type="checkbox"]):not([type="radio"]),
      select {
        min-height: 2.35rem;
      }

      textarea {
        min-height: 3.7rem;
      }

      button,
      a[class*="px-"],
      .mobile-sticky-actions a,
      .mobile-sticky-actions button {
        min-height: 2.6rem;
      }

      button,
      .px-5,
      .px-4,
      .px-3 {
        padding-left: 0.7rem !important;
        padding-right: 0.7rem !important;
      }

      button,
      .py-3,
      .py-2 {
        padding-top: 0.45rem !important;
        padding-bottom: 0.45rem !important;
      }

      table {
        font-size: 0.82rem !important;
      }

      th,
      td {
        padding: 0.35rem 0.5rem !important;
      }

      .mobile-bottom-action-spacer {
        padding-bottom: calc(4.7rem + env(safe-area-inset-bottom, 0px)) !important;
      }

      .mobile-sticky-actions {
        padding: 0.45rem 0.55rem calc(0.45rem + env(safe-area-inset-bottom, 0px)) !important;
        border-radius: 0.9rem 0.9rem 0 0;
        background: rgba(15, 23, 42, 0.94) !important;
        border-color: rgba(71, 85, 105, 0.9) !important;
        box-shadow: 0 -10px 24px rgba(2, 6, 23, 0.42);
        backdrop-filter: blur(14px);
      }

      .mobile-sticky-actions .grid {
        gap: 0.45rem !important;
      }

      .mobile-sticky-actions a,
      .mobile-sticky-actions button {
        height: 2.55rem !important;
        border-radius: 0.65rem !important;
        font-size: 0.9rem !important;
        font-weight: 700;
      }

      #globalScrollTopBtn {
        right: 0.65rem !important;
        bottom: calc(4.6rem + env(safe-area-inset-bottom, 0px)) !important;
        width: 2.45rem !important;
        height: 2.45rem !important;
      }
    }

    @media (max-width: 380px) {
      html {
        font-size: 11px;
      }

      .admin-page-title {
        font-size: 1.08rem !important;
      }

      input,
      select,
      textarea,
      button,
      .rounded-lg.border,
      .rounded.border {
        font-size: 11px !important;
      }
    }

    /* Remove tap highlight from menu buttons */
    #sidebar a {
      -webkit-tap-highlight-color: transparent;
    }

    /* Sidebar layout adjustments */
    #sidebar {
      width: 16rem;
    }

    #sidebar[data-collapsed="true"] {
      width: 4.5rem !important;
    }

    #sidebar[data-collapsed="true"] .menu-text,
    #sidebar[data-collapsed="true"] .sidebar-logo-text,
    #sidebar[data-collapsed="true"] .sidebar-footer .menu-text {
      display: none;
    }

    #sidebar[data-collapsed="true"] .menu-item {
      justify-content: center;
    }

    #sidebar[data-collapsed="true"] .menu-item .material-icons-round {
      margin-right: 0 !important;
    }

    #sidebar[data-collapsed="true"] .sidebar-logo {
      justify-content: center;
    }

    #sidebar[data-collapsed="true"] .sidebar-footer form {
      width: auto;
    }

    #sidebar[data-collapsed="true"] .sidebar-footer button {
      justify-content: center;
    }

    #sidebar[data-collapsed="true"] .collapse-icon {
      transform: rotate(0);
    }

    #sidebar .collapse-icon {
      transition: transform 0.3s ease;
    }
  </style>
</head>
<body class="min-h-screen bg-gray-100 font-sans">
  <?php
    $currentPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '/';
    $menuItems = [
      ['href' => '/admin/dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard'],
      ['href' => '/admin/orders', 'icon' => 'receipt_long', 'label' => 'Заказы'],
      ['href' => '/admin/purchases', 'icon' => 'local_shipping', 'label' => 'Закупки'],
      ['href' => '/admin/products', 'icon' => 'inventory_2', 'label' => 'Товары'],
      ['href' => '/admin/product-types', 'icon' => 'category', 'label' => 'Категории'],
      ['href' => '/admin/slots', 'icon' => 'calendar_today', 'label' => 'Слоты'],
      ['href' => '/admin/coupons', 'icon' => 'local_offer', 'label' => 'Промокоды'],
      ['href' => '/admin/content', 'icon' => 'article', 'label' => 'Контент'],
      ['href' => '/admin/users', 'icon' => 'people', 'label' => 'Пользователи'],
      ['href' => '/admin/sellers', 'icon' => 'storefront', 'label' => 'Селлеры'],
      ['href' => '/admin/apps', 'icon' => 'apps', 'label' => 'Приложения'],
      ['href' => '/admin/settings', 'icon' => 'settings', 'label' => 'Настройки'],
    ];
  ?>

  <div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside id="sidebar" data-collapsed="false"
           class="fixed md:static top-0 left-0 z-40 h-full md:h-auto md:min-h-full w-64 bg-white shadow-lg transform -translate-x-full md:translate-x-0 transition-all duration-300 flex flex-col">
      <div class="flex items-center justify-between p-4 border-b border-gray-200 sidebar-header">
        <div class="flex items-center space-x-2 sidebar-logo">
          <span class="material-icons-round text-[#C86052] sidebar-logo-icon">local_florist</span>
          <span class="font-bold text-xl text-[#C86052] sidebar-logo-text">BerryGo Admin</span>
        </div>
        <button id="sidebarCloseBtn" class="md:hidden p-2 text-gray-400 hover:text-white focus:outline-none" aria-label="Закрыть меню">
          <span class="material-icons-round">close</span>
        </button>
        <button id="sidebarCollapseBtn" class="hidden md:flex items-center justify-center w-9 h-9 rounded-full bg-gray-800/40 text-gray-300 hover:text-white focus:outline-none focus:ring-2 focus:ring-[#C86052]"
                aria-label="Свернуть меню" aria-expanded="true">
          <span class="material-icons-round collapse-icon">chevron_left</span>
        </button>
      </div>
      <nav class="p-4 space-y-1 overflow-y-auto">
        <?php foreach ($menuItems as $item):
          $isActive = strpos($currentPath, $item['href']) === 0;
        ?>
          <a href="<?= $item['href'] ?>"
             class="menu-item flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors <?= $isActive ? 'bg-[#C86052]/20 text-[#C86052]' : 'text-gray-300 hover:text-white hover:bg-gray-700/30' ?>">
            <span class="material-icons-round text-lg mr-3"><?= $item['icon'] ?></span>
            <span class="menu-text"><?= $item['label'] ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="mt-auto p-4 border-t border-gray-200 hidden md:flex sidebar-footer">
        <form action="/logout" method="post" class="w-full">
            <?= csrf_field() ?>
          <button type="submit" class="w-full flex items-center justify-center gap-2 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 py-2">
            <span class="material-icons-round text-base">logout</span>
            <span class="menu-text">Выход</span>
          </button>
        </form>
      </div>
    </aside>

    <div id="sidebarBackdrop" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

    <!-- Контент -->
    <div class="admin-content-shell flex-1 min-w-0 flex flex-col md:ml-0">
      <!-- Header -->
      <header class="flex items-center justify-between bg-white p-4 shadow md:ml-0">
        <div class="flex items-center space-x-3">
          <button id="sidebarToggle" class="p-2 rounded-full text-gray-600 hover:text-[#C86052] focus:outline-none focus:ring-2 focus:ring-[#C86052]" aria-label="Меню">
            <span class="material-icons-round">menu</span>
          </button>
          <div class="font-bold text-xl text-[#C86052] md:hidden">BerryGo Admin</div>
        </div>
        <form action="/logout" method="post" class="md:hidden">
            <?= csrf_field() ?>
          <button type="submit" class="flex items-center text-red-400 hover:text-red-300">
            <span class="material-icons-round mr-1">logout</span> Выход
          </button>
        </form>
      </header>

      <div class="flex-1 min-w-0 flex flex-col overflow-hidden">
        <h1 class="admin-page-title text-2xl font-semibold text-gray-700 p-4 flex items-center gap-3 min-w-0">
          <span class="material-icons-round text-[#C86052]">auto_awesome_mosaic</span>
          <?= htmlspecialchars($pageTitle) ?>
        </h1>
        <main class="p-0 sm:p-3 md:p-4 overflow-auto bg-gray-50 flex-1 min-w-0">
          <?= $content ?>
        </main>
      </div>
    </div>
  </div>

  <button id="globalScrollTopBtn" type="button" aria-label="Наверх" class="fixed right-4 bottom-5 z-50 w-10 h-10 rounded-full bg-[#C86052] text-white shadow-lg transition-all duration-300" style="opacity:0;pointer-events:none;transform:translateY(12px);">↑</button>

  <script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const SIDEBAR_COLLAPSED_KEY = 'berrygo-admin-sidebar-collapsed';
    const globalScrollTopBtn = document.getElementById('globalScrollTopBtn');
    const mainScrollContainer = document.querySelector('main.overflow-auto') || document.querySelector('main') || window;
    const getMainScrollTop = () => mainScrollContainer === window ? window.scrollY : mainScrollContainer.scrollTop;
    const updateGlobalScrollBtn = () => {
      const visible = getMainScrollTop() > 120;
      if (!globalScrollTopBtn) return;
      globalScrollTopBtn.style.opacity = visible ? '1' : '0';
      globalScrollTopBtn.style.pointerEvents = visible ? 'auto' : 'none';
      globalScrollTopBtn.style.transform = visible ? 'translateY(0)' : 'translateY(12px)';
    };
    mainScrollContainer.addEventListener('scroll', updateGlobalScrollBtn, { passive: true });
    globalScrollTopBtn?.addEventListener('click', () => {
      if (mainScrollContainer === window) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        mainScrollContainer.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
    updateGlobalScrollBtn();


    if (sidebar) {
      const storage = (() => {
        try {
          const testKey = '__berrygo-admin__';
          localStorage.setItem(testKey, '1');
          localStorage.removeItem(testKey);
          return localStorage;
        } catch (error) {
          return null;
        }
      })();

      let isCollapsed = storage?.getItem(SIDEBAR_COLLAPSED_KEY) === '1';

      const updateCollapseIcon = (collapsed) => {
        if (!sidebarCollapseBtn) {
          return;
        }

        const icon = sidebarCollapseBtn.querySelector('.collapse-icon');
        if (icon) {
          icon.textContent = collapsed ? 'chevron_right' : 'chevron_left';
        }
        sidebarCollapseBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      };

      const applyCollapsedState = () => {
        const shouldCollapse = window.innerWidth >= 768 && isCollapsed;
        sidebar.setAttribute('data-collapsed', shouldCollapse ? 'true' : 'false');
        updateCollapseIcon(shouldCollapse);
      };

      const openSidebarMobile = () => {
        sidebar.classList.remove('-translate-x-full');
        sidebarBackdrop?.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        sidebar.setAttribute('data-collapsed', 'false');
      };

      const closeSidebarMobile = () => {
        sidebar.classList.add('-translate-x-full');
        sidebarBackdrop?.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
      };

      const toggleSidebar = () => {
        if (window.innerWidth < 768) {
          if (sidebar.classList.contains('-translate-x-full')) {
            openSidebarMobile();
          } else {
            closeSidebarMobile();
          }
        } else {
          isCollapsed = !isCollapsed;
          if (isCollapsed) {
            storage?.setItem(SIDEBAR_COLLAPSED_KEY, '1');
          } else {
            storage?.removeItem(SIDEBAR_COLLAPSED_KEY);
          }
          applyCollapsedState();
        }
      };

      const syncSidebarState = () => {
        if (window.innerWidth >= 768) {
          sidebar.classList.remove('-translate-x-full');
          sidebarBackdrop?.classList.add('hidden');
          document.body.classList.remove('overflow-hidden');
        } else if (sidebarBackdrop?.classList.contains('hidden')) {
          document.body.classList.remove('overflow-hidden');
        }
        applyCollapsedState();
      };

      sidebarToggle?.addEventListener('click', toggleSidebar);
      sidebarCollapseBtn?.addEventListener('click', () => {
        if (window.innerWidth >= 768) {
          toggleSidebar();
        }
      });
      sidebarCloseBtn?.addEventListener('click', closeSidebarMobile);
      sidebarBackdrop?.addEventListener('click', closeSidebarMobile);

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && window.innerWidth < 768 && !sidebar.classList.contains('-translate-x-full')) {
          closeSidebarMobile();
        }
      });

      window.addEventListener('resize', syncSidebarState);

      syncSidebarState();
    }
  </script>
<?php include __DIR__ . '/scripts.php'; ?>
</body>
</html>
