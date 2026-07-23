<?php
declare(strict_types=1);
namespace Tests;

use App\Services\CatalogFeedService;
use PDO;
use PHPUnit\Framework\TestCase;

final class CatalogFeedServiceTest extends TestCase
{
    public function testPublishesValidatedFeedAtomicallyForEnabledActiveProducts(): void
    {
        $pdo = new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE catalog_feed_state (id INTEGER PRIMARY KEY, is_dirty INTEGER, generated_at TEXT, last_error TEXT)');
        $pdo->exec('CREATE TABLE product_types (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, product_type_id INTEGER, external_catalog_enabled INTEGER, is_active INTEGER, external_name TEXT, external_sku TEXT, external_description TEXT, external_image_path TEXT, variety TEXT, alias TEXT, description TEXT, image_path TEXT, price INTEGER, sale_price INTEGER)');
        $pdo->exec('INSERT INTO catalog_feed_state (id,is_dirty) VALUES (1,1)');
        $pdo->exec("INSERT INTO product_types (id,name) VALUES (3,'Ягоды')");
        $pdo->exec("INSERT INTO products VALUES (15,3,1,1,'Клубника','BERRY-15','Описание','https://cdn.example.test/berry.webp','Обычное','ordinary','Обычное описание','/uploads/default.webp',1350,0)");
        $pdo->exec("INSERT INTO products VALUES (16,3,1,0,'Скрытый','BERRY-16','',NULL,'','',NULL,NULL,99,0)");
        $root = sys_get_temp_dir() . '/berrygo-feed-' . bin2hex(random_bytes(6)); mkdir($root);
        try {
            $this->assertTrue((new CatalogFeedService($pdo, $root))->generate());
            $xml = (string)file_get_contents($root . '/feeds/catalog.yml');
            $this->assertStringContainsString('<offer id="15" available="true">', $xml);
            $this->assertStringNotContainsString('id="16"', $xml);
            $this->assertStringContainsString('<picture>https://cdn.example.test/berry.webp</picture>', $xml);
            $this->assertNotFalse(simplexml_load_string($xml));
            $this->assertSame(0, (int)$pdo->query('SELECT is_dirty FROM catalog_feed_state WHERE id=1')->fetchColumn());
        } finally { @unlink($root . '/feeds/catalog.yml'); @rmdir($root . '/feeds'); @rmdir($root); }
    }
}
