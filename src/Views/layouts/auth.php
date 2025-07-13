<?php
  $pageMeta = $meta ?? [];
  $meta = array_merge([
    'title' => 'BerryGo',
    'description' => '',
    'keywords' => '',
  ], array_filter($pageMeta, static fn($v) => $v !== null && $v !== ''));
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

  <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="/assets/images/favicon.svg">

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
