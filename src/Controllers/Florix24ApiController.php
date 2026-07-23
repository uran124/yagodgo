<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Florix24InboundService;
use PDO;

final class Florix24ApiController {
 public function __construct(private PDO $pdo) {}
 public function customer(): void { $this->handle(fn($s)=>$s->customerByPhone((string)($_GET['phone']??''))); }
 public function order(): void { $this->handle(fn($s)=>$s->createOrder($this->body())); }
 public function cancel(string $externalId): void { $this->handle(fn($s)=>$s->cancel(urldecode($externalId))); }
 public function feed(): void {
  header('Content-Type: application/xml; charset=UTF-8');
  $base=(isset($_SERVER['HTTPS'])?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost');
  $q=$this->pdo->query("SELECT p.id,p.price,p.sale_price,p.image_path,p.external_image_path,p.external_name,p.external_description,p.external_sku,p.variety,p.description,p.alias,p.is_active,p.product_type_id,pt.name category FROM products p LEFT JOIN product_types pt ON pt.id=p.product_type_id WHERE p.external_catalog_enabled=1");
  $esc=fn($v)=>htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8'); echo '<?xml version="1.0" encoding="UTF-8"?><yml_catalog date="'.date('c').'"><shop><name>BerryGo</name><currencies><currency id="RUR" rate="1"/></currencies><categories>';
  foreach($this->pdo->query('SELECT id,name FROM product_types') as $c) echo '<category id="'.(int)$c['id'].'">'.$esc($c['name']).'</category>';
  echo '</categories><offers>'; foreach($q as $p){$price=(int)$p['sale_price']>0?$p['sale_price']:$p['price'];echo '<offer id="'.(int)$p['id'].'" available="'.((int)$p['is_active']?'true':'false').'">'.'<name>'.$esc($p['external_name']?:$p['variety']).'</name><vendorCode>'.$esc($p['external_sku']?:$p['alias']).'</vendorCode><price>'.$esc($price).'</price><currencyId>RUR</currencyId><categoryId>'.(int)$p['product_type_id'].'</categoryId>';if($p['external_image_path']||$p['image_path'])echo '<picture>'.$esc($base.($p['external_image_path']?:$p['image_path'])).'</picture>';echo '<description>'.$esc($p['external_description']?:$p['description']).'</description></offer>';} echo '</offers></shop></yml_catalog>';
 }
 private function handle(callable $action): void { $started=microtime(true);$status=200;$payload=[];try{$this->authorize();$payload=$action(new Florix24InboundService($this->pdo));}catch(\RuntimeException $e){$status=422;$payload=['result'=>'error','code'=>$e->getMessage()];}catch(\Throwable $e){$status=500;$payload=['result'=>'error','code'=>'internal_error'];}http_response_code($status);header('Content-Type: application/json; charset=UTF-8');echo json_encode($payload,JSON_UNESCAPED_UNICODE); }
 private function body(): array {$raw=file_get_contents('php://input');$data=json_decode($raw?:'',true);if(!is_array($data))throw new \RuntimeException('validation_error');return $data;}
 private function authorize(): void {$h=(string)($_SERVER['HTTP_AUTHORIZATION']??'');if(!preg_match('/^Bearer\s+(.+)$/i',$h,$m))throw new \RuntimeException('invalid_token');$q=$this->pdo->prepare("SELECT token_hash,is_active FROM integration_clients WHERE source='florix24' LIMIT 1");$q->execute();$c=$q->fetch(PDO::FETCH_ASSOC);if(!$c||!(int)$c['is_active']||!password_verify($m[1],$c['token_hash']))throw new \RuntimeException('invalid_token');}
}
