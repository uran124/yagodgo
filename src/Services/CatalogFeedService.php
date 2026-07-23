<?php
declare(strict_types=1);
namespace App\Services;
use PDO;

final class CatalogFeedService
{
    public function __construct(private PDO $pdo, private ?string $root = null) { $this->root ??= dirname(__DIR__, 2); }
    public function markDirty(): void { $this->pdo->exec('UPDATE catalog_feed_state SET is_dirty=1 WHERE id=1'); }
    public function generate(bool $force = false): bool
    {
        $state=$this->pdo->query('SELECT is_dirty FROM catalog_feed_state WHERE id=1')->fetchColumn(); if (!$force && !(int)$state) return false;
        try {
        $dir=$this->root.'/feeds'; if(!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) throw new \RuntimeException('Cannot create feeds directory');
        $rows=$this->pdo->query("SELECT p.*, pt.name AS category_name FROM products p JOIN product_types pt ON pt.id=p.product_type_id WHERE p.external_catalog_enabled=1 AND p.is_active=1 ORDER BY p.id")->fetchAll(PDO::FETCH_ASSOC);
        $xml=new \XMLWriter();$xml->openMemory();$xml->startDocument('1.0','UTF-8');$xml->startElement('yml_catalog');$xml->writeAttribute('date',date('c'));$xml->startElement('shop');$xml->writeElement('name','BerryGo');$xml->startElement('currencies');$xml->startElement('currency');$xml->writeAttribute('id','RUR');$xml->writeAttribute('rate','1');$xml->endElement();$xml->endElement();$xml->startElement('categories');$categories=[];foreach($rows as $r)$categories[(int)$r['product_type_id']]=$r['category_name'];foreach($categories as $id=>$name){$xml->startElement('category');$xml->writeAttribute('id',(string)$id);$xml->text($name);$xml->endElement();}$xml->endElement();$xml->startElement('offers');foreach($rows as $r){$xml->startElement('offer');$xml->writeAttribute('id',(string)$r['id']);$xml->writeAttribute('available','true');$xml->writeElement('name',$r['external_name']?:$r['variety']);$xml->writeElement('vendorCode',$r['external_sku']?:$r['alias']);$xml->writeElement('price',(string)((int)$r['sale_price']>0?$r['sale_price']:$r['price']));$xml->writeElement('currencyId','RUR');$xml->writeElement('categoryId',(string)$r['product_type_id']);if($r['external_image_path']||$r['image_path'])$xml->writeElement('picture',(string)($r['external_image_path']?:$r['image_path']));$xml->writeElement('description',$r['external_description']?:$r['description']);$xml->endElement();}$xml->endElement();$xml->endElement();$xml->endElement();$xml->endDocument();$contents=$xml->outputMemory();if(!simplexml_load_string($contents)) throw new \RuntimeException('Generated YML is invalid');$tmp=$dir.'/catalog.yml.tmp';if(file_put_contents($tmp,$contents,LOCK_EX)===false||!rename($tmp,$dir.'/catalog.yml')) throw new \RuntimeException('Cannot publish YML');$this->pdo->exec("UPDATE catalog_feed_state SET is_dirty=0,generated_at=CURRENT_TIMESTAMP,last_error=NULL WHERE id=1");return true;
        } catch (\Throwable $e) { $stmt=$this->pdo->prepare('UPDATE catalog_feed_state SET last_error=? WHERE id=1'); $stmt->execute([$e->getMessage()]); throw $e; }
    }
}
