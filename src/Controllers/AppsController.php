<?php
namespace App\Controllers;

use PDO;
use PDOException;

class AppsController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(): void
    {
        $stmt = $this->pdo->query("SELECT * FROM sitemap_settings LIMIT 1");
        $sitemap = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sitemap) {
            $this->pdo->exec("INSERT INTO sitemap_settings (is_active) VALUES (0)");
            $sitemap = ['is_active' => 0, 'last_generated' => null];
        }

        try {
            $mailingStatsStmt = $this->pdo->query(
                "SELECT COUNT(*) AS total_records,"
                . " SUM(CASE WHEN mc.allow_mailing = 0 THEN 0 ELSE 1 END) AS active_records"
                . " FROM users u"
                . " LEFT JOIN mailing_clients mc ON mc.user_id = u.id"
                . " WHERE u.role = 'client'"
            );
            $mailingStatsRow = $mailingStatsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $mailingStats = [
                'total'  => (int)($mailingStatsRow['total_records'] ?? 0),
                'active' => (int)($mailingStatsRow['active_records'] ?? 0),
            ];
        } catch (PDOException $e) {
            $mailingStats = ['total' => 0, 'active' => 0];
        }

        viewAdmin('apps/index', [
            'pageTitle' => 'Приложения',
            'sitemap'   => $sitemap,
            'mailing'   => $mailingStats,
        ]);
    }

    public function toggleSitemap(): void
    {
        $this->pdo->exec("UPDATE sitemap_settings SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=1");
        header('Location: /admin/apps');
        exit;
    }

    public function sitemapSettings(): void
    {
        $settings = $this->ensureSitemapSettings();
        $staticPages = $this->getStaticSitemapPages();
        $pageToggles = $this->getSitemapPageToggles(array_column($staticPages, 'key'));
        foreach ($staticPages as &$page) {
            $page['in_sitemap'] = $pageToggles[$page['key']] ?? true;
        }
        unset($page);

        $categoryInSitemap = $this->columnExists('content_categories', 'in_sitemap');
        $typeInSitemap = $this->columnExists('product_types', 'in_sitemap');
        $materialIsActive = $this->columnExists('materials', 'is_active');

        $materialsWhere = $materialIsActive ? 'WHERE m.is_active = 1' : '';
        $materials = $this->pdo->query(
            "SELECT m.id, m.title, m.alias, m.in_sitemap, c.name AS category_name, c.alias AS category_alias
               FROM materials m
               JOIN content_categories c ON c.id = m.category_id
               {$materialsWhere}
              ORDER BY m.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $categorySitemapSelect = $categoryInSitemap ? 'c.in_sitemap' : '1 AS in_sitemap';
        $categories = $this->pdo->query(
            "SELECT c.id, c.name, c.alias, {$categorySitemapSelect}, COUNT(m.id) AS active_materials_count
               FROM content_categories c
               JOIN materials m ON m.category_id = c.id" . ($materialIsActive ? ' AND m.is_active = 1' : '') . "
              GROUP BY c.id, c.name, c.alias" . ($categoryInSitemap ? ', c.in_sitemap' : '') . "
              ORDER BY c.name ASC, c.id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $typeSitemapSelect = $typeInSitemap ? 't.in_sitemap' : '1 AS in_sitemap';
        $productTypes = $this->pdo->query(
            "SELECT t.id, t.name, t.alias, {$typeSitemapSelect}, COUNT(p.id) AS active_products_count
               FROM product_types t
               JOIN products p ON p.product_type_id = t.id AND p.is_active = 1
              GROUP BY t.id, t.name, t.alias" . ($typeInSitemap ? ', t.in_sitemap' : '') . "
              ORDER BY t.name ASC, t.id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $products = $this->pdo->query(
            "SELECT p.id, p.variety, p.alias, p.in_sitemap, t.name AS product, t.alias AS type_alias
               FROM products p
               JOIN product_types t ON t.id = p.product_type_id
              WHERE p.is_active = 1
              ORDER BY p.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('apps/sitemap', [
            'pageTitle'     => 'Карта сайта',
            'settings'      => $settings,
            'staticPages'   => $staticPages,
            'categories'    => $categories,
            'materials'     => $materials,
            'productTypes'  => $productTypes,
            'products'      => $products,
            'hasCategoryInSitemap' => $categoryInSitemap,
            'hasTypeInSitemap'     => $typeInSitemap,
        ]);
    }

    public function toggleItem(): void
    {
        $type = $_POST['type'] ?? '';
        $id   = (int)($_POST['id'] ?? 0);
        if ($id && in_array($type, ['material', 'product', 'category', 'product_type'], true)) {
            $tableMap = [
                'material'     => 'materials',
                'product'      => 'products',
                'category'     => 'content_categories',
                'product_type' => 'product_types',
            ];
            $table = $tableMap[$type];
            if ($this->columnExists($table, 'in_sitemap')) {
                $stmt = $this->pdo->prepare("UPDATE {$table} SET in_sitemap = CASE WHEN in_sitemap=1 THEN 0 ELSE 1 END WHERE id = ?");
                $stmt->execute([$id]);
            }
        }
        header('Location: /admin/apps/sitemap');
        exit;
    }

    public function togglePage(): void
    {
        $key = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_POST['key'] ?? ''));
        $knownKeys = array_column($this->getStaticSitemapPages(), 'key');
        if ($key !== '' && in_array($key, $knownKeys, true)) {
            $settings = $this->getSitemapPageToggles($knownKeys);
            $nextValue = !($settings[$key] ?? true) ? '1' : '0';
            $stmt = $this->pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute(['sitemap_page_' . $key, $nextValue]);
        }
        header('Location: /admin/apps/sitemap');
        exit;
    }

    public function generateSitemap(): void
    {
        $baseUrl = 'https://berrygo.ru';
        $urls = [];

        $staticPages = $this->getStaticSitemapPages();
        $pageToggles = $this->getSitemapPageToggles(array_column($staticPages, 'key'));
        foreach ($staticPages as $page) {
            if ($pageToggles[$page['key']] ?? true) {
                $urls[] = [$page['path'], $page['priority']];
            }
        }

        $categoryInSitemap = $this->columnExists('content_categories', 'in_sitemap');
        $typeInSitemap = $this->columnExists('product_types', 'in_sitemap');
        $materialIsActive = $this->columnExists('materials', 'is_active');

        $materialWhere = ['m.in_sitemap = 1'];
        if ($materialIsActive) {
            $materialWhere[] = 'm.is_active = 1';
        }
        if ($categoryInSitemap) {
            $materialWhere[] = 'c.in_sitemap = 1';
        }
        $stmt = $this->pdo->query(
            "SELECT m.alias AS mat_alias, c.alias AS cat_alias
               FROM materials m
               JOIN content_categories c ON c.id = m.category_id
              WHERE " . implode(' AND ', $materialWhere) . "
              ORDER BY m.id DESC"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $urls[] = ['content/' . $row['cat_alias'] . '/' . $row['mat_alias'], '0.7'];
        }

        $typeWhere = $typeInSitemap ? 'WHERE t.in_sitemap = 1' : '';
        $stmt = $this->pdo->query(
            "SELECT DISTINCT t.alias
               FROM product_types t
               JOIN products p ON p.product_type_id = t.id AND p.is_active = 1
               {$typeWhere}
              ORDER BY t.alias ASC"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $urls[] = ['catalog/' . $row['alias'], '0.8'];
        }

        $productWhere = ['p.in_sitemap = 1', 'p.is_active = 1'];
        if ($typeInSitemap) {
            $productWhere[] = 't.in_sitemap = 1';
        }
        $stmt = $this->pdo->query(
            "SELECT p.alias, t.alias AS type_alias
               FROM products p
               JOIN product_types t ON t.id = p.product_type_id
              WHERE " . implode(' AND ', $productWhere) . "
              ORDER BY p.id DESC"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $urls[] = ['catalog/' . $row['type_alias'] . '/' . $row['alias'], '0.7'];
        }

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>');
        $xml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $seen = [];
        foreach ($urls as $u) {
            [$path, $prio] = $u;
            $loc = rtrim($baseUrl . '/' . ltrim((string)$path, '/'), '/');
            if (isset($seen[$loc])) {
                continue;
            }
            $seen[$loc] = true;
            $url = $xml->addChild('url');
            $url->addChild('loc', $loc);
            $url->addChild('priority', (string)$prio);
        }

        $xml->asXML(__DIR__ . '/../../sitemap.xml');
        try {
            $this->pdo->exec("UPDATE sitemap_settings SET last_generated = NOW() WHERE id=1");
        } catch (PDOException $e) {
            $this->pdo->exec("UPDATE sitemap_settings SET last_generated = CURRENT_TIMESTAMP WHERE id=1");
        }

        if (PHP_SAPI !== 'cli') {
            header('Location: /admin/apps/sitemap');
            exit;
        }
    }

    /**
     * @return array<int, array{key: string, label: string, path: string, priority: string}>
     */
    private function getStaticSitemapPages(): array
    {
        return [
            ['key' => 'home', 'label' => 'Главная', 'path' => '', 'priority' => '1.0'],
            ['key' => 'catalog', 'label' => 'Каталог', 'path' => 'catalog', 'priority' => '0.9'],
            ['key' => 'register', 'label' => 'Регистрация', 'path' => 'register', 'priority' => '0.4'],
            ['key' => 'login', 'label' => 'Вход', 'path' => 'login', 'priority' => '0.3'],
            ['key' => 'reset_pin', 'label' => 'Восстановление PIN', 'path' => 'reset-pin', 'priority' => '0.2'],
        ];
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, bool>
     */
    private function getSitemapPageToggles(array $keys): array
    {
        if ($keys === [] || !$this->tableExists('settings')) {
            return array_fill_keys($keys, true);
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $settingKeys = array_map(static fn (string $key): string => 'sitemap_page_' . $key, $keys);
        $stmt = $this->pdo->prepare(
            "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ({$placeholders})"
        );
        $stmt->execute($settingKeys);

        $toggles = array_fill_keys($keys, true);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = substr((string)$row['setting_key'], strlen('sitemap_page_'));
            $toggles[$key] = (string)$row['setting_value'] === '1';
        }

        return $toggles;
    }

    /**
     * @return array<string, mixed>
     */
    private function ensureSitemapSettings(): array
    {
        $settings = $this->pdo->query("SELECT * FROM sitemap_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$settings) {
            $this->pdo->exec("INSERT INTO sitemap_settings (is_active) VALUES (0)");
            $settings = ['is_active' => 0, 'last_generated' => null];
        }

        return $settings;
    }

    private function tableExists(string $table): bool
    {
        try {
            $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
                $stmt->execute([$table]);
                return (bool)$stmt->fetchColumn();
            }

            $stmt = $this->pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->prepare("PRAGMA table_info({$table})");
                $stmt->execute();
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if (($row['name'] ?? '') === $column) {
                        return true;
                    }
                }
                return false;
            }

            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
            $stmt->execute([$column]);
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }
}
