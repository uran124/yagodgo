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
                "SELECT COUNT(*) AS total_records, SUM(allow_mailing = 1) AS active_records FROM mailing_clients"
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
        $settings = $this->pdo->query("SELECT * FROM sitemap_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $materials = $this->pdo->query("SELECT id, title, in_sitemap FROM materials ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $products = $this->pdo->query(
            "SELECT p.id, p.variety, p.in_sitemap, t.name AS product FROM products p JOIN product_types t ON t.id = p.product_type_id ORDER BY p.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('apps/sitemap', [
            'pageTitle' => 'Sitemap',
            'settings'  => $settings,
            'materials' => $materials,
            'products'  => $products,
        ]);
    }

    public function toggleItem(): void
    {
        $type = $_POST['type'] ?? '';
        $id   = (int)($_POST['id'] ?? 0);
        if ($id && in_array($type, ['material','product'], true)) {
            $table = $type === 'material' ? 'materials' : 'products';
            $stmt = $this->pdo->prepare("UPDATE {$table} SET in_sitemap = CASE WHEN in_sitemap=1 THEN 0 ELSE 1 END WHERE id = ?");
            $stmt->execute([$id]);
        }
        header('Location: /admin/apps/sitemap');
        exit;
    }

    public function generateSitemap(): void
    {
        $baseUrl = 'https://example.com';
        $urls = [
            ['',        '1.0'],
            ['catalog', '0.8'],
            ['register', '0.6'],
            ['login',    '0.6'],
            ['cart',     '0.5'],
            ['checkout', '0.5'],
            ['profile',  '0.5'],
            ['orders',   '0.5'],
            ['favorites','0.4'],
            ['reset-pin','0.3'],
        ];

        $stmt = $this->pdo->query(
            "SELECT m.alias AS mat_alias, c.alias AS cat_alias FROM materials m JOIN content_categories c ON c.id = m.category_id WHERE m.in_sitemap = 1"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $urls[] = ['content/' . $row['cat_alias'] . '/' . $row['mat_alias'], '0.7'];
        }

        $stmt = $this->pdo->query(
            "SELECT p.alias, t.alias AS type_alias FROM products p JOIN product_types t ON t.id = p.product_type_id WHERE p.in_sitemap = 1 AND p.is_active = 1"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $urls[] = ['catalog/' . $row['type_alias'] . '/' . $row['alias'], '0.7'];
        }

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>');
        $xml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($urls as $u) {
            [$path, $prio] = $u;
            $url = $xml->addChild('url');
            $url->addChild('loc', rtrim($baseUrl . '/' . $path, '/'));
            $url->addChild('priority', $prio);
        }

        $xml->asXML(__DIR__ . '/../../sitemap.xml');
        $this->pdo->exec("UPDATE sitemap_settings SET last_generated = NOW() WHERE id=1");

        if (PHP_SAPI !== 'cli') {
            header('Location: /admin/apps/sitemap');
            exit;
        }
    }
}
