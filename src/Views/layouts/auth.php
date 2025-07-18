<?php
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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= htmlspecialchars($meta['title']) ?></title>
  <?php if (!empty($meta['description'])): ?>
    <meta name="description" content="<?= htmlspecialchars($meta['description']) ?>">
  <?php endif; ?>
  <?php if (!empty($meta['keywords'])): ?>
    <meta name="keywords" content="<?= htmlspecialchars($meta['keywords']) ?>">
  <?php endif; ?>

  <!-- Favicon и основные иконки -->
  <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/icon-192.png">

  <!-- Иконки для Android / PWA -->
  <link rel="icon" type="image/png" sizes="72x72" href="/assets/images/icon-72.png">
  <link rel="icon" type="image/png" sizes="96x96" href="/assets/images/icon-96.png">
  <link rel="icon" type="image/png" sizes="128x128" href="/assets/images/icon-128.png">
  <link rel="icon" type="image/png" sizes="144x144" href="/assets/images/icon-144.png">
  <link rel="icon" type="image/png" sizes="152x152" href="/assets/images/icon-152.png">
  <link rel="icon" type="image/png" sizes="192x192" href="/assets/images/icon-192.png">
  <link rel="icon" type="image/png" sizes="384x384" href="/assets/images/icon-384.png">
  <link rel="icon" type="image/png" sizes="512x512" href="/assets/images/icon-512.png">

  <!-- PWA maskable icons -->
  <link rel="icon" type="image/png" sizes="192x192" href="/assets/images/icon-192-maskable.png" purpose="maskable">
  <link rel="icon" type="image/png" sizes="512x512" href="/assets/images/icon-512-maskable.png" purpose="maskable">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">

  <style>
    :root {
      --berry-red: #FF6B6B;
      --berry-pink: #FF8E8E;
      --fresh-green: #4ECDC4;
      --leaf-green: #45B7AA;
      --cream: #FFF8F5;
      --soft-gray: #F8FAFC;
      --text-dark: #1A202C;
      --text-gray: #4A5568;
    }
    * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
    body { background: linear-gradient(135deg, var(--cream) 0%, #FFF 100%); }
    .berry-gradient { background: linear-gradient(135deg, var(--berry-red) 0%, var(--berry-pink) 100%); }
  </style>
</head>
<body class="bg-gradient-to-br from-orange-50 via-white to-pink-50 text-gray-800 min-h-screen">
<?= $content ?>
<?php include __DIR__ . '/scripts.php'; ?>
</body>
</html>
