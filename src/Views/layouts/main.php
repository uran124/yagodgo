<?php
  $points = (int)($_SESSION['points_balance'] ?? 0);

  /** Метаданные страницы */
  $pageMeta = $meta ?? [];
  global $pdo;
  $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
  $slugMap = [
    '/'          => 'home',
    '/catalog'   => 'catalog',
    '/about'     => 'about_app',
    '/about_app' => 'about_app',
    '/contacts'  => 'contacts',
  ];
  $pageSlug = $slugMap[$path] ?? trim($path, '/');

  $meta = [
    'title'       => 'BerryGo',
    'description' => '',
    'keywords'    => '',
    'h1'          => '',
    'text'        => '',
  ];

  if (isset($pdo)) {
      $stmt = $pdo->prepare('SELECT title, description, keywords, h1, text FROM metadata WHERE page = ? LIMIT 1');
      if ($stmt->execute([$pageSlug])) {
          $row = $stmt->fetch(PDO::FETCH_ASSOC);
          if ($row) {
              $meta = $row;
          }
      }
  }
  if (!empty($pageMeta)) {
      $meta = array_merge($meta, array_filter($pageMeta, static fn($v) => $v !== null && $v !== ''));
  }

  $lightTheme = get_theme_colors('light');
  $darkTheme  = get_theme_colors('dark');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= htmlspecialchars($meta['title'] ?? 'BerryGo') ?></title>
  <?php if (!empty($meta['description'])): ?>
    <meta name="description" content="<?= htmlspecialchars($meta['description']) ?>">
  <?php endif; ?>
  <?php if (!empty($meta['keywords'])): ?>
    <meta name="keywords" content="<?= htmlspecialchars($meta['keywords']) ?>">
  <?php endif; ?>
  
  <!-- Favicon и основные иконки -->
  <link rel="icon" href="assets/images/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/images/icon-192.png">

  <!-- Иконки для Android / PWA -->
  <link rel="icon" type="image/png" sizes="72x72" href="assets/images/icon-72.png">
  <link rel="icon" type="image/png" sizes="96x96" href="assets/images/icon-96.png">
  <link rel="icon" type="image/png" sizes="128x128" href="assets/images/icon-128.png">
  <link rel="icon" type="image/png" sizes="144x144" href="assets/images/icon-144.png">
  <link rel="icon" type="image/png" sizes="152x152" href="assets/images/icon-152.png">
  <link rel="icon" type="image/png" sizes="192x192" href="assets/images/icon-192.png">
  <link rel="icon" type="image/png" sizes="384x384" href="assets/images/icon-384.png">
  <link rel="icon" type="image/png" sizes="512x512" href="assets/images/icon-512.png">

  <!-- PWA maskable icons -->
  <link rel="icon" type="image/png" sizes="192x192" href="assets/images/icon-192-maskable.png" purpose="maskable">
  <link rel="icon" type="image/png" sizes="512x512" href="assets/images/icon-512-maskable.png" purpose="maskable">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
  
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#FF6B6B">
  <meta name="mobile-web-app-capable" content="yes">

  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "BerryGo",
      "image": "https://berrygo.ru/assets/images/icon-512.png",
      "url": "https://berrygo.ru",
      "telephone": "+7 902 923-77-94",
      "email": "support@berrygo.ru",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "улица 9 Мая, 73",
        "addressLocality": "Красноярск",
        "addressCountry": "RU"
      },
      "areaServed": {
        "@type": "City",
        "name": "Красноярск"
      },
      "openingHoursSpecification": [
        {
          "@type": "OpeningHoursSpecification",
          "dayOfWeek": [
            "Monday",
            "Tuesday",
            "Wednesday",
            "Thursday",
            "Friday",
            "Saturday",
            "Sunday"
          ],
          "opens": "09:00",
          "closes": "21:00"
        }
      ],
      "sameAs": [
        "https://t.me/klubnikovoe",
        "https://t.me/klubnikovoe_bot"
      ],
      "makesOffer": {
        "@type": "Offer",
        "name": "Самовывоз заказов BerryGo",
        "availableDeliveryMethod": "https://schema.org/OnSitePickup",
        "itemOffered": {
          "@type": "Service",
          "name": "Самовывоз заказов BerryGo"
        },
        "areaServed": {
          "@type": "City",
          "name": "Красноярск"
        },
        "pickupLocation": {
          "@type": "Place",
          "name": "Пункт самовывоза BerryGo",
          "address": {
            "@type": "PostalAddress",
            "streetAddress": "улица 9 Мая, 73",
            "addressLocality": "Красноярск",
            "addressCountry": "RU"
          }
        }
      }
    }
  </script>

  <style>
    :root {
      --berry-red: <?= htmlspecialchars($lightTheme['strong']) ?>;
      --berry-pink: <?= htmlspecialchars($lightTheme['secondary']) ?>;
      --accent-primary: <?= htmlspecialchars($lightTheme['primary']) ?>;
      --accent-secondary: <?= htmlspecialchars($lightTheme['secondary']) ?>;
      --accent-strong: <?= htmlspecialchars($lightTheme['strong']) ?>;
      --accent-via: <?= htmlspecialchars($lightTheme['via']) ?>;
      --accent-soft: <?= htmlspecialchars($lightTheme['soft']) ?>;
      --accent-contrast: <?= htmlspecialchars($lightTheme['contrast']) ?>;
      --accent-image: <?= htmlspecialchars($lightTheme['image']) ?>;
      --accent-image-alt: <?= htmlspecialchars($lightTheme['imageAlt']) ?>;
      --fresh-green: #4ECDC4;
      --leaf-green: #45B7AA;
      --cream: #FFF8F5;
      --soft-gray: #F8FAFC;
      --text-dark: #1A202C;
      --text-gray: #4A5568;
    }

    @media (prefers-color-scheme: dark) {
      :root {
        --berry-red: <?= htmlspecialchars($darkTheme['strong']) ?>;
        --berry-pink: <?= htmlspecialchars($darkTheme['secondary']) ?>;
        --accent-primary: <?= htmlspecialchars($darkTheme['primary']) ?>;
        --accent-secondary: <?= htmlspecialchars($darkTheme['secondary']) ?>;
        --accent-strong: <?= htmlspecialchars($darkTheme['strong']) ?>;
        --accent-via: <?= htmlspecialchars($darkTheme['via']) ?>;
        --accent-soft: <?= htmlspecialchars($darkTheme['soft']) ?>;
        --accent-contrast: <?= htmlspecialchars($darkTheme['contrast']) ?>;
        --accent-image: <?= htmlspecialchars($darkTheme['image']) ?>;
        --accent-image-alt: <?= htmlspecialchars($darkTheme['imageAlt']) ?>;
      }
    }

    .text-\[\#C86052\] { color: var(--accent-primary) !important; }
    .hover\:text-\[\#C86052\]:hover { color: var(--accent-secondary) !important; }

    .accent-gradient {
      --tw-gradient-from: var(--accent-strong) !important;
      --tw-gradient-via: var(--accent-secondary) !important;
      --tw-gradient-to: var(--accent-secondary) !important;
      --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important;
      background-image: linear-gradient(135deg, var(--accent-strong), var(--accent-secondary));
    }

    .accent-gradient-via {
      --tw-gradient-from: var(--accent-strong) !important;
      --tw-gradient-to: var(--accent-secondary) !important;
      --tw-gradient-stops: var(--tw-gradient-from), var(--accent-via), var(--tw-gradient-to) !important;
      background-image: linear-gradient(135deg, var(--accent-strong), var(--accent-via), var(--accent-secondary));
    }

    .accent-text {
      color: var(--accent-primary);
    }

    .accent-text-gradient {
      background-image: linear-gradient(135deg, var(--accent-strong), var(--accent-secondary));
    }

    .accent-soft {
      background-color: var(--accent-soft);
    }

    .accent-focus:focus-visible {
      outline: 2px solid var(--accent-primary);
      outline-offset: 2px;
    }

    .product-image,
    .material-image,
    .material-card-image {
      background: radial-gradient(circle at 20% 20%, var(--accent-image), var(--accent-image-alt));
      border: 1px solid rgba(255, 255, 255, 0.32);
      border-radius: 1.25rem;
    }

    .product-image-placeholder {
      background: linear-gradient(135deg, var(--accent-soft), rgba(255, 255, 255, 0.92));
      color: var(--accent-primary);
    }

    @media (prefers-color-scheme: dark) {
      body {
        background: linear-gradient(135deg, #0f172a 0%, #1f2937 100%);
        color: #e2e8f0;
      }

      .glass-effect {
        background: rgba(15, 23, 42, 0.75);
        border-color: rgba(148, 163, 184, 0.25);
        color: inherit;
      }

      .product-image,
      .material-image,
      .material-card-image {
        border-color: rgba(148, 163, 184, 0.35);
      }

      .product-image-placeholder {
        background: linear-gradient(135deg, rgba(148, 163, 184, 0.2), var(--accent-image));
        color: var(--accent-contrast);
      }
    }
    
    * {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }
    
    body {
      background: linear-gradient(135deg, var(--cream) 0%, #FFF 100%);
    }
    
    .berry-gradient {
      background: linear-gradient(135deg, var(--berry-red) 0%, var(--berry-pink) 100%);
    }
    
    .fresh-gradient {
      background: linear-gradient(135deg, var(--fresh-green) 0%, var(--leaf-green) 100%);
    }
    
    .glass-effect {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .card-shadow {
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    
    .berry-shadow {
      box-shadow: 0 20px 40px -10px rgba(255, 107, 107, 0.3);
    }
    
    .floating-animation {
      animation: float 3s ease-in-out infinite;
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-5px); }
    }
    
    .nav-item {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .nav-item:hover {
      transform: translateY(-2px);
    }
    
    .install-pulse {
      animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }

    .no-scrollbar::-webkit-scrollbar {
      display: none;
    }
    .no-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }

    .availability-badge {
      --badge-bg: rgba(255, 255, 255, 0.9);
      --badge-text: #1f2937;
      --badge-border: rgba(15, 23, 42, 0.08);
      background: var(--badge-bg);
      color: var(--badge-text);
      border: 1px solid var(--badge-border);
      border-radius: 9999px;
      padding: 0.35rem 0.75rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
      backdrop-filter: blur(8px);
    }

    .availability-badge--instock {
      --badge-bg: rgba(34, 197, 94, 0.14);
      --badge-text: #166534;
      --badge-border: rgba(34, 197, 94, 0.32);
    }

    .availability-badge--date {
      --badge-bg: rgba(249, 115, 22, 0.16);
      --badge-text: #9a3412;
      --badge-border: rgba(249, 115, 22, 0.28);
    }

    .availability-badge--placeholder {
      --badge-bg: rgba(59, 130, 246, 0.14);
      --badge-text: #1d4ed8;
      --badge-border: rgba(59, 130, 246, 0.26);
    }

    @media (prefers-color-scheme: dark) {
      .availability-badge {
        --badge-bg: rgba(15, 23, 42, 0.7);
        --badge-text: #f8fafc;
        --badge-border: rgba(148, 163, 184, 0.35);
        box-shadow: 0 10px 26px rgba(2, 6, 23, 0.45);
      }

      .availability-badge--instock {
        --badge-bg: color-mix(in srgb, #22c55e 32%, transparent);
        --badge-text: #bbf7d0;
        --badge-border: rgba(74, 222, 128, 0.45);
      }

      .availability-badge--date {
        --badge-bg: color-mix(in srgb, #f97316 32%, transparent);
        --badge-text: #fde68a;
        --badge-border: rgba(251, 191, 36, 0.42);
      }

      .availability-badge--placeholder {
        --badge-bg: color-mix(in srgb, #38bdf8 30%, transparent);
        --badge-text: #e0f2fe;
        --badge-border: rgba(125, 211, 252, 0.4);
      }
    }

    .embla {
      overflow: hidden;
    }
    .embla__container {
      display: flex;
    }
    .embla__slide {
      position: relative;
    }
    .embla--fade {
      position: relative;
    }
    .embla--fade .embla__container {
      display: block;
    }
    .embla--fade .embla__slide {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      opacity: 0;
      transition: opacity 0.5s ease;
    }
    .embla--fade .embla__slide.is-selected {
      position: relative;
      opacity: 1;
    }
    .embla__controls {
      display: flex;
      justify-content: center;
      margin-top: 0.5rem;
    }
    .embla__dots {
      display: flex;
      gap: 0.5rem;
    }
    .embla__dots button {
      width: 8px;
      height: 8px;
      border-radius: 9999px;
      background: #d1d5db;
    }
    .embla__dots button.is-active {
      background: #374151;
    }

    .embla-news {
      position: relative;
    }
    .embla-news .embla__controls {
      position: absolute;
      right: 0.5rem;
      bottom: 0.5rem;
      margin-top: 0;
      justify-content: flex-end;
    }
    .embla-news .embla__dots {
      justify-content: flex-end;
    }

  </style>


  <script>
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/service-worker.js')
        .catch(e => console.error('Service worker registration failed:', e));
    }
  </script>
</head>
<body class="bg-gradient-to-br from-orange-50 via-white to-pink-50 text-gray-800 min-h-screen">

  <!-- Header -->
<div id="installPromptBar" class="fixed top-0 left-0 right-0 z-30 px-4 pt-3" style="display:none;">
  <div class="glass-effect border border-white/30 rounded-2xl px-4 py-3 flex items-center justify-between shadow-lg">
    <button id="installLogoBtn" class="group flex flex-row items-center text-left space-x-2 install-pulse">
      <span id="installLogoBtnText" class="font-bold text-sm leading-tight bg-gradient-to-r from-red-500 to-pink-500 bg-clip-text text-transparent transition-opacity duration-500">
        Установите<br>приложение
      </span>
      <span class="material-icons-round text-red-500">install_mobile</span>
    </button>
    <button id="installPromptClose" class="material-icons-round text-gray-500 hover:text-gray-700 p-1" aria-label="Скрыть блок установки приложения">close</button>
  </div>
</div>

<header class="fixed top-0 left-0 right-0 glass-effect flex items-center justify-between p-4 z-20 border-b border-white/20">
  <a href="/" id="logoLink" class="flex items-center">
    <div class="w-10 h-10 flex items-center justify-center floating-animation">
      <img src="assets/berrygo_strawberry.svg" alt="BerryGo" class="w-10 h-10">
    </div>
  </a>

  <div class="flex items-center space-x-3">
    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
      <button id="adminToggle" class="material-icons-round text-2xl text-gray-600 hover:text-emerald-500 transition-colors p-2 hover:bg-emerald-50 rounded-xl">settings</button>
    <?php elseif (($_SESSION['role'] ?? '') === 'manager'): ?>
      <a href="/manager/users/edit" class="p-2 text-gray-600 hover:text-[#C86052]" title="Добавить пользователя">
        <span class="material-icons-round">person_add</span>
      </a>
      <a href="/manager/orders/create" class="p-2 text-gray-600 hover:text-[#C86052]" title="Создать заказ">
        <span class="material-icons-round">add_shopping_cart</span>
      </a>
      <a href="/manager/profile" class="p-2 text-gray-600 hover:text-[#C86052]" title="Дашборд">
        <span class="material-icons-round">dashboard</span>
      </a>
    <?php elseif (($_SESSION['role'] ?? '') === 'partner'): ?>
      <a href="/partner/users/edit" class="p-2 text-gray-600 hover:text-[#C86052]" title="Добавить пользователя">
        <span class="material-icons-round">person_add</span>
      </a>
      <a href="/partner/orders/create" class="p-2 text-gray-600 hover:text-[#C86052]" title="Создать заказ">
        <span class="material-icons-round">add_shopping_cart</span>
      </a>
      <a href="/partner/profile" class="p-2 text-gray-600 hover:text-[#C86052]" title="Дашборд">
        <span class="material-icons-round">dashboard</span>
      </a>
    <?php elseif (($_SESSION['role'] ?? '') === 'seller'): ?>
      <a href="/seller/products" class="p-2 text-gray-600 hover:text-[#C86052]" title="Товары">
        <span class="material-icons-round">inventory_2</span>
      </a>
      <a href="/seller/dashboard" class="p-2 text-gray-600 hover:text-[#C86052]" title="Дашборд">
        <span class="material-icons-round">dashboard</span>
      </a>
    <?php endif; ?>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <!-- Авторизованный пользователь -->
      <?php $points = (int)($_SESSION['points_balance'] ?? 0); ?>
      <button
        id="openPointsPopup"
        class=" shadow-lg ml-2 flex items-center space-x-1 bg-white/20 backdrop-blur-sm rounded-full px-3 py-1 hover:bg-red-100 transition-colors hover:shadow-xl transition-shadow duration-200"
        title="Ваш баланс клубничек"
      >
        <span class="text-xl">🍓</span>
        <span class="font-medium text-gray-800"><?= $points ?></span>
      </button>

      <form action="/logout" method="post">
        <?= csrf_field() ?>
        <button type="submit"
                class="material-icons-round text-2xl text-gray-600 hover:text-red-500 transition-colors p-2 hover:bg-red-50 rounded-xl"
                title="Выйти">
          logout
        </button>
      </form>
    <?php else: ?>
      <!-- Гость -->
      <a href="/login"
         class="ml-2 flex items-center space-x-1 bg-white/20 backdrop-blur-sm rounded-full px-3 py-1 hover:bg-white/30 transition-colors"
         title="Войти, чтобы собирать клубнички"
      >
        <span class="text-xl">🍓</span>
        <span class="font-medium text-gray-800">0</span>
      </a>
    <?php endif; ?>
  </div>
</header>


  <!-- Админ-sidebar (off-canvas) -->
  <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
  <aside
    id="adminSidebar"
    class="fixed top-0 right-0 h-full w-80 glass-effect shadow-2xl transform translate-x-full transition-transform duration-300 z-30 border-l border-white/20"
  >
    <div class="p-6 font-bold text-xl border-b border-gray-100 fresh-gradient text-white rounded-tr-xl">
      <span class="material-icons-round mr-2">admin_panel_settings</span>
      Админ-панель
    </div>
    <nav class="p-4 space-y-1">
      <a href="/admin/dashboard" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-emerald-50 hover:to-teal-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-emerald-500 group-hover:scale-110 transition-transform">dashboard</span> 
        <span class="font-medium">Dashboard</span>
      </a>
      <a href="/admin/orders" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-blue-500 group-hover:scale-110 transition-transform">receipt_long</span> 
        <span class="font-medium">Заказы</span>
      </a>
      <a href="/admin/products" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-purple-50 hover:to-pink-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-purple-500 group-hover:scale-110 transition-transform">inventory_2</span> 
        <span class="font-medium">Товары</span>
      </a>
      <a href="/admin/slots" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-orange-50 hover:to-red-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-orange-500 group-hover:scale-110 transition-transform">calendar_today</span> 
        <span class="font-medium">Слоты</span>
      </a>
      <a href="/admin/coupons" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-yellow-50 hover:to-orange-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-yellow-500 group-hover:scale-110 transition-transform">local_offer</span> 
        <span class="font-medium">Промокоды</span>
      </a>
      <a href="/admin/users" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-teal-50 hover:to-cyan-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-teal-500 group-hover:scale-110 transition-transform">people</span> 
        <span class="font-medium">Пользователи</span>
      </a>
    </nav>
  </aside>
  <?php endif; ?>

  <!-- Manager sidebar (off-canvas) -->
  <?php if (($_SESSION['role'] ?? '') === 'manager'): ?>
  <aside
    id="managerSidebar"
    class="fixed top-0 right-0 h-full w-80 glass-effect shadow-2xl transform translate-x-full transition-transform duration-300 z-30 border-l border-white/20"
  >
    <div class="p-6 font-bold text-xl border-b border-gray-100 fresh-gradient text-white rounded-tr-xl">
      <span class="material-icons-round mr-2">manage_accounts</span>
      Менеджер
    </div>
    <nav class="p-4 space-y-1">
      <a href="/manager/orders" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-blue-500 group-hover:scale-110 transition-transform">receipt_long</span>
        <span class="font-medium">Заказы</span>
      </a>
      <a href="/manager/products" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-purple-50 hover:to-pink-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-purple-500 group-hover:scale-110 transition-transform">inventory_2</span>
        <span class="font-medium">Товары</span>
      </a>
      <a href="/manager/users" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-teal-50 hover:to-cyan-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-teal-500 group-hover:scale-110 transition-transform">people</span>
        <span class="font-medium">Пользователи</span>
      </a>
      <a href="/manager/profile" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-red-50 hover:to-orange-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-red-500 group-hover:scale-110 transition-transform">account_circle</span>
        <span class="font-medium">Профиль</span>
      </a>
    </nav>
  </aside>
  <?php endif; ?>

  <!-- Partner sidebar (off-canvas) -->
  <?php if (($_SESSION['role'] ?? '') === 'partner'): ?>
  <aside
    id="partnerSidebar"
    class="fixed top-0 right-0 h-full w-80 glass-effect shadow-2xl transform translate-x-full transition-transform duration-300 z-30 border-l border-white/20"
  >
    <div class="p-6 font-bold text-xl border-b border-gray-100 fresh-gradient text-white rounded-tr-xl">
      <span class="material-icons-round mr-2">manage_accounts</span>
      Партнёр
    </div>
    <nav class="p-4 space-y-1">
      <a href="/partner/orders" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-blue-500 group-hover:scale-110 transition-transform">receipt_long</span>
        <span class="font-medium">Заказы</span>
      </a>
      <a href="/partner/products" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-purple-50 hover:to-pink-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-purple-500 group-hover:scale-110 transition-transform">inventory_2</span>
        <span class="font-medium">Товары</span>
      </a>
      <a href="/partner/users" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-teal-50 hover:to-cyan-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-teal-500 group-hover:scale-110 transition-transform">people</span>
        <span class="font-medium">Пользователи</span>
      </a>
      <a href="/partner/profile" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-red-50 hover:to-orange-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-red-500 group-hover:scale-110 transition-transform">account_circle</span>
        <span class="font-medium">Профиль</span>
      </a>
    </nav>
  </aside>
  <?php endif; ?>
  
  <!-- Install PWA Banner (replaced by logo button) -->

  <!-- Контент -->
  <div class="pt-16 pb-24">
    <?php if ($path !== '/') : ?>
      <?php
        $crumbs = $breadcrumbs ?? [];
        if (empty($crumbs)) {
            $segments = array_filter(explode('/', trim($path, '/')));
            $accum = '';
            foreach ($segments as $i => $seg) {
                $accum .= '/' . $seg;
                $label = ($i === count($segments)-1 && !empty($meta['h1'])) ? $meta['h1'] : $seg;
                $crumbs[] = ['label' => $label, 'url' => $i < count($segments)-1 ? $accum : null];
            }
        }
      ?>
      <nav class="px-4 pb-2 text-sm text-gray-500 mt-4" aria-label="Breadcrumb">
        <a href="/" class="text-red-500 hover:underline">Главная</a>
        <?php foreach ($crumbs as $bc): ?>
          <span class="mx-1">/</span>
          <?php if (!empty($bc['url'])): ?>
            <a href="<?= htmlspecialchars($bc['url']) ?>" class="hover:underline">
              <?= htmlspecialchars($bc['label']) ?>
            </a>
          <?php else: ?>
            <?= htmlspecialchars($bc['label']) ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <?= $content ?>
    <?php
      $showMetaText = $showMetaText ?? true;
      if (!empty($meta['text']) && $showMetaText): ?>
      <div class="max-w-screen-lg mx-auto px-4 pb-24 text-gray-700 text-sm">
        <?php if (!empty($meta['h1'])): ?>
          <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($meta['h1']) ?></h1>
        <?php endif; ?>
        <p><?= nl2br(htmlspecialchars($meta['text'])) ?></p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Bottom Navigation -->
  <?php
  $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
  $role = $_SESSION['role'] ?? 'guest';
  $cartTotal = $_SESSION['cart_total'] ?? 0;

  function isActive(string $path) {
      return ($_SERVER['REQUEST_URI'] === $path) ? 'text-red-500 bg-red-50' : 'text-gray-500';
  }
  ?>
  
  <nav class="fixed bottom-0 left-0 w-full glass-effect border-t border-white/20 shadow-2xl z-20">
    <ul class="flex justify-between px-2 py-2">      
      <!-- Каталог -->
      <li class="flex-1 mx-1">
        <a href="/catalog" class="nav-item flex flex-col items-center py-3 px-2 rounded-2xl transition-all <?= isActive('/catalog') ?>">
          <span class="material-icons-round text-xl mb-1">grid_view</span>
          <span class="text-xs font-medium">Каталог</span>
        </a>
      </li>
      
      <!-- Корзина -->
      <?php
        $cartClass = ($cartTotal > 0 || $uri === '/cart')
                     ? 'text-red-500 bg-red-50' : 'text-gray-500';
      ?>
      <li class="flex-1 mx-1">
        <a href="/cart" class="nav-item flex flex-col items-center py-3 px-2 rounded-2xl transition-all <?= $cartClass ?> relative">
          <?php if ($cartTotal > 0): ?>
            <div class="absolute -top-1 -right-5 px-1 h-5 berry-gradient rounded-sm flex items-center justify-center">
              <span class="text-xs font text-white"><?= $cartTotal ?>₽</span>
            </div>
          <?php endif; ?>
          <span class="material-icons-round text-xl mb-1">shopping_cart</span>
          <span class="text-xs font-medium">Корзина</span>
        </a>
      </li>
      
      <!-- Мои заказы -->
      <?php if (in_array($role, ['client','partner','manager','admin','seller'])): ?>
        <li class="flex-1 mx-1">
          <a href="/orders" class="nav-item flex flex-col items-center py-3 px-2 rounded-2xl transition-all <?= isActive('/orders') ?>">
            <span class="material-icons-round text-xl mb-1">receipt_long</span>
            <span class="text-xs font-medium">Заказы</span>
          </a>
        </li>
      <?php else: ?>
        <li class="flex-1 ml-1">
          <div class="nav-item flex flex-col items-center py-3 px-2 rounded-2xl text-gray-400">
            <span class="material-icons-round text-xl mb-1">receipt_long</span>
            <span class="text-xs font-medium">Заказы</span>
          </div>
        </li>
      <?php endif; ?>

      <!-- Уведомления -->
      <?php if ($role === 'admin'): ?>
        <li class="flex-1 mx-1">
          <a href="/notifications" class="nav-item flex flex-col items-center py-3 px-2 rounded-2xl transition-all <?= isActive('/notifications') ?>">
            <span class="material-icons-round text-xl mb-1">notifications</span>
            <span class="text-xs font-medium">Уведомления</span>
          </a>
        </li>
      <?php else: ?>
        <li class="flex-1 mx-1">
          <div class="nav-item flex flex-col items-center py-3 px-2 rounded-2xl text-gray-400">
            <span class="material-icons-round text-xl mb-1">notifications</span>
            <span class="text-xs font-medium">Уведомления</span>
          </div>
        </li>
      <?php endif; ?>

      <!-- Профиль -->
      <?php if (in_array($role, ['client','partner','manager','admin','seller'])): ?>
        <li class="flex-1">
          <a href="/profile" class="nav-item flex flex-col items-center py-3 px-2 rounded-2xl transition-all <?= isActive('/profile') ?>">
            <span class="material-icons-round text-xl mb-1">person</span>
            <span class="text-xs font-medium">Профиль</span>
          </a>
        </li>
      <?php else: ?>
        <li class="flex-1">
          <a href="/login" class="nav-item flex flex-col items-center py-3 px-2 rounded-2xl transition-all text-gray-500 hover:text-emerald-500 hover:bg-emerald-50">
            <span class="material-icons-round text-xl mb-1">person</span>
            <span class="text-xs font-medium">Войти</span>
          </a>
        </li>
      <?php endif; ?>
    </ul>
  </nav>

  <footer class="hidden md:block glass-effect border-t border-white/20 mt-8">
    <div class="max-w-screen-xl mx-auto py-8 px-6 grid grid-cols-2 lg:grid-cols-4 gap-8 text-sm text-gray-700">
      <div>
        <h3 class="font-semibold mb-2">О компании</h3>
        <p class="mb-1">berryGo — доставка ягод и фруктов по Красноярску</p>
        <p class="mb-1">Адрес: Красноярск, ул. 9 Мая, 73</p>
        <p class="mb-1">Время работы: Пн–Вс 9:00–21:00</p>
        <p class="mb-1">Телефон:
          <a href="tel:+79029237794" class="text-red-500 hover:underline">+7 902 923‑77‑94</a>
        </p>
        <p>E-mail:
          <a href="mailto:support@berrygo.ru" class="text-red-500 hover:underline">support@berrygo.ru</a>
        </p>
      </div>
      <div>
        <h3 class="font-semibold mb-2">Покупателям</h3>
        <ul class="space-y-1">
          <li><a href="#" class="hover:underline">Доставка и оплата</a></li>
          <li><a href="#" class="hover:underline">Частые вопросы (FAQ)</a></li>
          <li><a href="#" class="hover:underline">Оставить отзыв</a></li>
          <li><a href="#" class="hover:underline">Политика конфиденциальности</a></li>
          <li><a href="#" class="hover:underline">Обработка персональных данных</a></li>
        </ul>
      </div>
      <div>
        <h3 class="font-semibold mb-2">Мы в сети</h3>
        <ul class="space-y-1">
          <li>
            <a href="https://t.me/klubnikovoe" target="_blank" rel="noopener" class="hover:underline">
              Telegram-канал @klubnikovoe
            </a>
          </li>
          <li>
            <a href="https://t.me/klubnikovoe_bot" target="_blank" rel="noopener" class="hover:underline">
              Бот для заказов @klubnikovoe_bot
            </a>
          </li>
          <li><a href="#" class="hover:underline">VK / Instagram</a></li>
          <li><a href="#" class="hover:underline">Стикеры Telegram</a></li>
        </ul>
      </div>
      <div>
        <h3 class="font-semibold mb-2">Информация</h3>
        <ul class="space-y-1">
          <li><a href="/sitemap.xml" class="hover:underline">Карта сайта</a></li>
          <li><a href="#" class="hover:underline">Реквизиты (ИП, ИНН, ОГРНИП)</a></li>
          <li>© berryGo, 2023–2025</li>
          <li>Разработка: berryGo team</li>
        </ul>
      </div>
    </div>
  </footer>

  <script>
    // Admin sidebar
    const adminToggle = document.getElementById('adminToggle');
    const adminSidebar = document.getElementById('adminSidebar');
    if (adminToggle && adminSidebar) {
      adminToggle.addEventListener('click', () => {
        adminSidebar.classList.toggle('translate-x-full');
      });
      document.addEventListener('click', (e) => {
        if (!adminSidebar.contains(e.target) && !adminToggle.contains(e.target)) {
          adminSidebar.classList.add('translate-x-full');
        }
      });
    }

    // Manager sidebar
    const managerToggle = document.getElementById('managerToggle');
    const managerSidebar = document.getElementById('managerSidebar');
    if (managerToggle && managerSidebar) {
      managerToggle.addEventListener('click', () => {
        managerSidebar.classList.toggle('translate-x-full');
      });
      document.addEventListener('click', (e) => {
        if (!managerSidebar.contains(e.target) && !managerToggle.contains(e.target)) {
          managerSidebar.classList.add('translate-x-full');
        }
      });
    }

    // Partner sidebar
    const partnerToggle = document.getElementById('partnerToggle');
    const partnerSidebar = document.getElementById('partnerSidebar');
    if (partnerToggle && partnerSidebar) {
      partnerToggle.addEventListener('click', () => {
        partnerSidebar.classList.toggle('translate-x-full');
      });
      document.addEventListener('click', (e) => {
        if (!partnerSidebar.contains(e.target) && !partnerToggle.contains(e.target)) {
          partnerSidebar.classList.add('translate-x-full');
        }
      });
    }

    // PWA Install
    let deferredPrompt = null;
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    const installPromptBar = document.getElementById('installPromptBar');
    const siteHeader = document.querySelector('header');
    const installLogoBtn = document.getElementById('installLogoBtn');
    const installPromptClose = document.getElementById('installPromptClose');

    function showInstalled() {
      if (installPromptBar) installPromptBar.style.display = 'none';
      if (siteHeader) siteHeader.style.top = '0';
    }

    function showInstall() {
      if (installPromptBar && sessionStorage.getItem('installPromptClosed') !== '1') {
        installPromptBar.style.display = 'block';
        if (siteHeader) siteHeader.style.top = '84px';
      }
    }

    window.addEventListener('beforeinstallprompt', (e) => {
      if (isStandalone) return;
      e.preventDefault();
      deferredPrompt = e;
      showInstall();
    });

    window.addEventListener('appinstalled', () => {
      console.log('✅ Приложение установлено');
      showInstalled();
    });

    document.addEventListener('DOMContentLoaded', () => {
      if (isStandalone) {
        showInstalled();
      } else {
        showInstall();
      }




      installPromptClose?.addEventListener('click', () => {
        sessionStorage.setItem('installPromptClosed', '1');
        showInstalled();
      });

      installLogoBtn?.addEventListener('click', () => {
        if (deferredPrompt) {
          deferredPrompt.prompt();
          deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
              console.log('✅ Пользователь установил приложение');
              showInstalled();
            } else {
              console.log('❌ Пользователь отказался от установки');
            }
            deferredPrompt = null;
          });
        } else {
          alert("📱 Установка недоступна — попробуйте с мобильного браузера");
        }
      });
    });
  </script>

<script>
  // Enable mouse drag scrolling for carousels
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.scroll-row').forEach(row => {
      let isDown = false;
      let startX;
      let scrollLeft;

      const start = x => {
        isDown = true;
        startX = x;
        scrollLeft = row.scrollLeft;
      };

      const move = x => {
        if (!isDown) return;
        row.scrollLeft = scrollLeft - (x - startX);
      };

      row.addEventListener('mousedown', e => start(e.pageX));
      row.addEventListener('mousemove', e => {
        if (!isDown) return;
        e.preventDefault();
        move(e.pageX);
      });
      window.addEventListener('mouseup', () => { isDown = false; });

      row.addEventListener('touchstart', e => start(e.touches[0].pageX));
      row.addEventListener('touchmove', e => move(e.touches[0].pageX));
      window.addEventListener('touchend', () => { isDown = false; });
    });
  });
</script>

  
  
      
      
      
      <!-- DEBUG OVERLAY (скрыт по умолчанию) 
    <div id="debugOverlay" style="
        position: fixed;
        bottom: 10px;
        right: 10px;
        width: 320px;
        max-height: 50vh;
        overflow-y: auto;
        background: rgba(0,0,0,0.8);
        color: #fff;
        font-family: monospace;
        font-size: 12px;
        line-height: 1.2;
        padding: 8px;
        border-radius: 6px;
        z-index: 9999;
        display: none;
    ">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
        <strong>DEBUG INFO</strong>
        <button id="debugToggle" style="
            background: transparent;
            border: none;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
        ">×</button>
      </div>
      <pre id="debugContent" style="white-space: pre-wrap;"></pre>
    </div>

    <button id="debugOpenBtn" style="
        position: fixed;
        bottom: 10px;
        right: 10px;
        background: #e53e3e;
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 6px 10px;
        font-size: 12px;
        cursor: pointer;
        z-index: 9999;
    ">Открыть DEBUG</button>

    <script>
      // Получаем переменную debugData из PHP, если она определена
      const __DEBUG_DATA__ = <?= json_encode($debugData ?? [], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) ?>;

      const overlay = document.getElementById('debugOverlay');
      const content = document.getElementById('debugContent');
      const btnOpen = document.getElementById('debugOpenBtn');
      const btnClose = document.getElementById('debugToggle');

      // Заполняем содержимое, если debugData есть
      if (__DEBUG_DATA__ && Object.keys(__DEBUG_DATA__).length > 0) {
        content.textContent = JSON.stringify(__DEBUG_DATA__, null, 2);
      } else {
        content.textContent = "Нет данных для отладки";
      }

      // Открыть оверлей
      btnOpen.addEventListener('click', () => {
        overlay.style.display = 'block';
        btnOpen.style.display = 'none';
      });

      // Закрыть оверлей
      btnClose.addEventListener('click', () => {
        overlay.style.display = 'none';
        btnOpen.style.display = 'block';
      });
    </script>
      -->
      
      
      
      
      
      
      
      
      
      
      
      
<!-- ====== МОДАЛЬНОЕ ОКНО «Клубнички» – ЯРКИЙ СТИЛЬ, ОПТИМИЗИРОВАННОЕ ДЛЯ МОБИЛЬНЫХ УСТРОЙСТВ ====== -->
<div id="pointsPopupBackdrop" class="fixed inset-0 bg-black/50 z-50 hidden"></div>
<div id="pointsPopup" class="fixed inset-0 flex items-center justify-center z-50 hidden px-4">
  <div class="relative bg-gradient-to-br from-red-500 via-pink-500 to-rose-400 text-white rounded-3xl shadow-2xl w-full max-w-md sm:max-w-lg overflow-hidden">
    <!-- Декоративные полупрозрачные круги -->
    <div class="absolute top-0 right-0 w-24 h-24 sm:w-32 sm:h-32 bg-white/10 rounded-full -translate-y-12 translate-x-12 sm:-translate-y-16 sm:translate-x-16"></div>
    <div class="absolute bottom-0 left-0 w-16 h-16 sm:w-24 sm:h-24 bg-white/10 rounded-full translate-y-8 -translate-x-8 sm:translate-y-12 sm:-translate-x-12"></div>

    <!-- Заголовок и кнопка закрытия -->
    <div class="relative z-10 flex justify-between items-center px-4 sm:px-6 py-3 sm:py-4 border-b border-white/20">
      <h2 class="text-xl sm:text-2xl font-bold flex items-center space-x-2">
        <span class="text-2xl sm:text-3xl">🍓</span>
        <span>Ваши клубнички</span>
      </h2>
      <button id="closePointsPopup" class="text-white text-2xl hover:opacity-80 px-2 sm:px-3">&times;</button>
    </div>

    <!-- Содержимое поп-апа -->
    <div class="relative z-10 p-4 sm:p-6 space-y-4 sm:space-y-6">
      <!-- Баланс -->
      <div class="text-center">
        <div class="text-4xl sm:text-5xl font-extrabold">
           <?= (int)($_SESSION['points_balance'] ?? 0) ?> <span class="text-2xl sm:text-3xl">🍓</span>
        </div>
        <p class="mt-2 text-base sm:text-lg opacity-90">
          Это ваши подарочные баллы — 1 🍓 = 1 ₽. Каждый раз, когда вы дарите другу скидку 10% по вашей ссылке или купону, он экономит и радуется, а вы получаете 🍓 за каждый его заказ!
        </p>
      </div>

      <hr class="border-white/30">

      <!-- Блок «Подарите другу скидку» -->
      <div class="bg-white/10 rounded-2xl p-3 sm:p-4 space-y-3 sm:space-y-4">
        <h3 class="text-lg sm:text-xl font-semibold text-white">
          Подарите другу <span class="text-yellow-200">10 %</span> скидку!
        </h3>
        <p class="text-sm sm:text-base text-white/90">
          Отправьте ссылку или купон — и ваш друг сразу получит подарок.
        </p>

        <!-- Ссылка-приглашение -->
        <div class="relative flex flex-col sm:flex-row items-stretch sm:items-center space-y-2 sm:space-y-0 sm:space-x-2">
            <?php $inviteLink = "https://berrygo.ru/?invite=" . urlencode($_SESSION['referral_code'] ?? ''); ?>
            <input
              id="inviteLinkInput"
              type="text"
              readonly
              value="<?= htmlspecialchars($inviteLink) ?>"
              class="flex-1 bg-white/20 rounded-lg px-3 py-2 text-xs sm:text-sm text-white outline-none cursor-pointer transition-all"
              onclick="copyText(this, 'linkCopied')"
            />
             <span id="linkCopied" class="absolute top-0 right-3 sm:right-0 mt-2 mr-2 text-xs text-green-300 opacity-0 transition-opacity">Скопировано!</span>
        </div>

        <!-- Купон -->
        <div class="relative flex items-center space-x-2">
          <code
              id="inviteCode"
              class="bg-white/20 rounded-lg px-3 py-2 font-mono text-sm sm:text-base text-white cursor-pointer transition-all"
              onclick="copyText(this, 'codeCopied')"
            >
              <?= htmlspecialchars($_SESSION['referral_code'] ?? '') ?>
            </code>

          <span id="codeCopied" class="absolute top-0 right-0 mt-2 mr-2 text-xs text-green-300 opacity-0 transition-opacity">Скопировано!</span>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // Открыть/закрыть модальное окно «Клубничек»
  const btnOpenPoints = document.getElementById('openPointsPopup');
  const popup = document.getElementById('pointsPopup');
  const backdrop = document.getElementById('pointsPopupBackdrop');
  const btnClosePoints = document.getElementById('closePointsPopup');

  function showPointsPopup() {
    backdrop.classList.remove('hidden');
    popup.classList.remove('hidden');
  }
  function hidePointsPopup() {
    popup.classList.add('hidden');
    backdrop.classList.add('hidden');
  }
  if (btnOpenPoints) {
    btnOpenPoints.addEventListener('click', showPointsPopup);
  }
  if (btnClosePoints) {
    btnClosePoints.addEventListener('click', hidePointsPopup);
  }
  backdrop.addEventListener('click', hidePointsPopup);

  // Функция копирования с анимацией и подсказкой
  function copyText(el, hintId) {
    let textToCopy = el.value ?? el.innerText;
    navigator.clipboard.writeText(textToCopy).then(() => {
      // Показать подсказку «Скопировано!»
      const hintEl = document.getElementById(hintId);
      hintEl.classList.remove('opacity-0');
      hintEl.classList.add('opacity-100');

      // Скрыть подсказку через 1,2 сек
      setTimeout(() => {
        hintEl.classList.remove('opacity-100');
        hintEl.classList.add('opacity-0');
      }, 1200);

      // Краткая анимация подсветки поля
      el.classList.add('bg-white/40', 'scale-105');
      setTimeout(() => {
        el.classList.remove('bg-white/40', 'scale-105');
      }, 400);
    }).catch(() => {
      alert('Не удалось скопировать.');
    });
  }
</script>

<script>
  // Equalize heights of product cards on the home page
  function equalizeProductRows() {
    document.querySelectorAll('.eq-row').forEach(row => {
      let max = 0;
      const items = Array.from(row.children);
      items.forEach(el => {
        el.style.height = 'auto';
        const h = el.offsetHeight;
        if (h > max) max = h;
      });
      items.forEach(el => {
        el.style.height = max + 'px';
      });
    });
  }

  document.addEventListener('DOMContentLoaded', equalizeProductRows);
  window.addEventListener('resize', () => {
    clearTimeout(window.eqResizeTimeout);
    window.eqResizeTimeout = setTimeout(equalizeProductRows, 100);
  });
</script>

  <script src="https://cdn.jsdelivr.net/npm/embla-carousel@8/embla-carousel.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/embla-carousel-autoplay@6/embla-carousel-autoplay.umd.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/embla-carousel-fade@8/embla-carousel-fade.umd.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    // Drag free carousels
    document.querySelectorAll('.embla.drag-free').forEach(wrapper => {
      const viewport = wrapper.querySelector('.embla__viewport');
      const embla = EmblaCarousel(viewport, { dragFree: true });
      const prev = wrapper.querySelector('[data-dir="left"]');
      const next = wrapper.querySelector('[data-dir="right"]');
      if (prev && next) {
        prev.addEventListener('click', () => embla.scrollPrev());
        next.addEventListener('click', () => embla.scrollNext());
      }
    });

    // News fade carousel
    const news = document.querySelector('.embla-news');
    if (news) {
      const viewport = news.querySelector('.embla__viewport');
      const dotsContainer = news.querySelector('.embla__dots');
      const embla = EmblaCarousel(viewport, { loop: true }, [
        EmblaCarouselAutoplay({ delay: 4000, stopOnInteraction: false }),
        EmblaCarouselFade()
      ]);
      const slides = embla.slideNodes();
      const dots = slides.map((_, i) => {
        const b = document.createElement('button');
        b.addEventListener('click', () => embla.scrollTo(i));
        dotsContainer.appendChild(b);
        return b;
      });
      function update() {
        const index = embla.selectedScrollSnap();
        slides.forEach((s, i) => {
          s.classList.toggle('is-selected', i === index);
        });
        dots.forEach((d, i) => {
          d.classList.toggle('is-active', i === index);
        });
      }
      embla.on('select', update);
      update();
    }
  });
</script>





      
      
      
      
      

      
      
      
      
      

  


<?php include __DIR__ . '/scripts.php'; ?>
</body>
</html>
