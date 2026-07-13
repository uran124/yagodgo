<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

class OrderGroupMigrationSqlTest extends TestCase
{
    private string $migrationSql;
    private string $dumpSql;

    protected function setUp(): void
    {
        $root = dirname(__DIR__);
        $this->migrationSql = file_get_contents($root . '/database/20260713_order_groups_and_order_items_identity.sql') ?: '';
        $this->dumpSql = file_get_contents($root . '/database/db.sql') ?: '';
    }

    public function testMigrationCreatesOrderGroupsAndLinksOrders(): void
    {
        self::assertStringContainsString('CREATE TABLE `order_groups`', $this->migrationSql);
        self::assertStringContainsString('`user_id` INT UNSIGNED NOT NULL', $this->migrationSql);
        self::assertStringContainsString('`created_by_user_id` INT UNSIGNED DEFAULT NULL', $this->migrationSql);
        self::assertStringContainsString('ADD COLUMN `order_group_id` BIGINT UNSIGNED DEFAULT NULL AFTER `id`', $this->migrationSql);
        self::assertStringContainsString('ADD KEY `idx_orders_order_group_id` (`order_group_id`)', $this->migrationSql);
        self::assertStringContainsString('CONSTRAINT `fk_orders_order_group` FOREIGN KEY (`order_group_id`) REFERENCES `order_groups` (`id`) ON DELETE SET NULL', $this->migrationSql);
    }

    public function testMigrationReplacesCompositeOrderItemsPrimaryKeyWithoutDroppingData(): void
    {
        self::assertStringContainsString('DROP PRIMARY KEY', $this->migrationSql);
        self::assertStringContainsString('ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST', $this->migrationSql);
        self::assertStringContainsString('ADD PRIMARY KEY (`id`)', $this->migrationSql);
        self::assertStringContainsString('ADD KEY `idx_order_items_order_id` (`order_id`)', $this->migrationSql);
        self::assertStringContainsString('ADD UNIQUE KEY `uniq_order_items_order_product_batch_mode` (`order_id`, `product_id`, `purchase_batch_id`, `stock_mode`)', $this->migrationSql);
        self::assertStringNotContainsString('DELETE FROM `ORDER_ITEMS`', strtoupper($this->migrationSql));
        self::assertStringNotContainsString('TRUNCATE TABLE `ORDER_ITEMS`', strtoupper($this->migrationSql));
    }

    public function testBaselineDumpMatchesPreparedSchema(): void
    {
        self::assertStringContainsString('CREATE TABLE `order_groups`', $this->dumpSql);
        self::assertStringContainsString('`order_group_id` bigint UNSIGNED DEFAULT NULL', $this->dumpSql);
        self::assertStringContainsString('`id` bigint UNSIGNED NOT NULL', $this->dumpSql);
        self::assertStringContainsString('ADD UNIQUE KEY `uniq_order_items_order_product_batch_mode` (`order_id`,`product_id`,`purchase_batch_id`,`stock_mode`)', $this->dumpSql);
        self::assertStringContainsString('INSERT INTO `order_items` (`id`, `order_id`, `product_id`', $this->dumpSql);
    }
}
