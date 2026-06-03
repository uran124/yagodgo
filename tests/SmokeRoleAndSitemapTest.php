<?php

namespace Tests;

use App\Controllers\AppsController;
use App\Middleware\AuthMiddleware;
use PDO;
use PHPUnit\Framework\TestCase;

class SmokeRoleAndSitemapTest extends TestCase
{
    public function testSmokeRoutesAreDeclaredInFrontController(): void
    {
        $publicRoutes = (string)file_get_contents(__DIR__ . '/../routes/public.php');
        $clientRoutes = (string)file_get_contents(__DIR__ . '/../routes/client.php');

        $this->assertStringContainsString("routeExact('GET', '/',", $publicRoutes);
        $this->assertStringContainsString("routeExact('GET', '/login',", $publicRoutes);
        $this->assertStringContainsString("routeExact('GET', '/register',", $publicRoutes);
        $this->assertStringContainsString("routeExact('GET', '/catalog',", $clientRoutes);
        $this->assertStringContainsString("routeExact('GET', '/checkout',", $clientRoutes);
    }

    public function testRoleAccessMatrixForMainRoles(): void
    {
        $middleware = new AuthMiddleware();

        foreach (['admin', 'manager', 'partner', 'seller', 'client'] as $role) {
            $session = ['user_id' => 101, 'role' => $role];
            $this->assertTrue($middleware->isAuthorized([$role], $session));
        }

        $this->assertFalse($middleware->isAuthorized(['admin'], ['user_id' => 101, 'role' => 'client']));
    }


    public function testManagerAndPartnerCanUseClientPurchaseControls(): void
    {
        $productView = (string)file_get_contents(__DIR__ . '/../src/Views/client/product.php');
        $cardView = (string)file_get_contents(__DIR__ . '/../src/Views/client/_card.php');

        foreach ([$productView, $cardView] as $view) {
            $this->assertStringContainsString("'partner'", $view);
            $this->assertStringContainsString("'manager'", $view);
            $this->assertStringContainsString('action="/cart/add"', $view);
        }
    }

    public function testHeaderUsesSingleAdminSectionShortcutForStaff(): void
    {
        $layout = (string)file_get_contents(__DIR__ . '/../src/Views/layouts/main.php');

        $this->assertStringContainsString("'manager' => '/manager/dashboard'", $layout);
        $this->assertStringContainsString("'partner' => '/partner/dashboard'", $layout);
        $this->assertStringContainsString('admin_panel_settings', $layout);
        $this->assertStringNotContainsString('person_add</span>', $layout);
        $this->assertStringNotContainsString('add_shopping_cart</span>', $layout);
    }

    public function testSitemapGenerationWorksInCliMode(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec('CREATE TABLE sitemap_settings (id INTEGER PRIMARY KEY, is_active INTEGER, last_generated TEXT)');
        $pdo->exec('CREATE TABLE content_categories (id INTEGER PRIMARY KEY, alias TEXT)');
        $pdo->exec('CREATE TABLE materials (id INTEGER PRIMARY KEY, alias TEXT, category_id INTEGER, in_sitemap INTEGER)');
        $pdo->exec('CREATE TABLE product_types (id INTEGER PRIMARY KEY, alias TEXT)');
        $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, alias TEXT, product_type_id INTEGER, in_sitemap INTEGER, is_active INTEGER)');

        $pdo->exec("INSERT INTO sitemap_settings (id, is_active, last_generated) VALUES (1, 1, NULL)");
        $pdo->exec("INSERT INTO content_categories (id, alias) VALUES (1, 'news')");
        $pdo->exec("INSERT INTO materials (id, alias, category_id, in_sitemap) VALUES (1, 'spring', 1, 1)");
        $pdo->exec("INSERT INTO product_types (id, alias) VALUES (1, 'berries')");
        $pdo->exec("INSERT INTO products (id, alias, product_type_id, in_sitemap, is_active) VALUES (1, 'fresh-box', 1, 1, 1)");

        $sitemapPath = __DIR__ . '/../sitemap.xml';
        $original = file_exists($sitemapPath) ? (string)file_get_contents($sitemapPath) : null;

        $controller = new AppsController($pdo);
        $controller->generateSitemap();

        $generated = (string)file_get_contents($sitemapPath);
        $this->assertStringContainsString('https://berrygo.ru/catalog', $generated);
        $this->assertStringContainsString('https://berrygo.ru/content/news/spring', $generated);
        $this->assertStringContainsString('https://berrygo.ru/catalog/berries/fresh-box', $generated);

        if ($original !== null) {
            file_put_contents($sitemapPath, $original);
        }
    }
}
