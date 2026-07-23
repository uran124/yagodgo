<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\PhoneNormalizer;
use PDO;
use RuntimeException;

/** Transactional BerryGo-side implementation of the Florix24 contract. */
final class Florix24InboundService
{
    public function __construct(private PDO $pdo) {}

    public function customerByPhone(string $phone): array
    {
        $phone = PhoneNormalizer::normalize($phone);
        if ($phone === '') throw new RuntimeException('validation_error');
        $q = $this->pdo->prepare('SELECT id, name, phone, points_balance FROM users WHERE phone = ? LIMIT 1');
        $q->execute([$phone]); $user = $q->fetch(PDO::FETCH_ASSOC);
        if (!$user) return ['result'=>'success','customer_found'=>false,'points'=>['balance'=>0,'max_available_to_use'=>0]];
        $balance = max(0, (int)$user['points_balance']);
        return ['result'=>'success','customer_found'=>true,'customer'=>['id'=>(int)$user['id'],'name'=>$user['name'],'phone'=>$phone],'points'=>['balance'=>$balance,'max_available_to_use'=>$balance]];
    }

    public function createOrder(array $input): array
    {
        $externalId = trim((string)($input['external_order_id'] ?? ''));
        $customer = (array)($input['customer'] ?? []); $items = (array)($input['items'] ?? []);
        $phone = PhoneNormalizer::normalize((string)($customer['phone'] ?? ''));
        if ($externalId === '' || $phone === '' || !$items) throw new RuntimeException('validation_error');
        $this->pdo->beginTransaction();
        try {
            $existing = $this->pdo->prepare("SELECT id FROM orders WHERE integration_source = 'florix24' AND external_order_id = ? LIMIT 1");
            $existing->execute([$externalId]);
            if ($id = $existing->fetchColumn()) { $this->pdo->commit(); return ['result'=>'success','idempotent_replay'=>true,'order_id'=>(int)$id,'order_number'=>'BG-'.$id]; }
            $u = $this->pdo->prepare('SELECT id, points_balance FROM users WHERE phone = ? LIMIT 1' . ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : ''));
            $u->execute([$phone]); $user = $u->fetch(PDO::FETCH_ASSOC);
            if (!$user) { $ins=$this->pdo->prepare("INSERT INTO users (role,name,phone,password_hash,points_balance,created_at) VALUES ('client',?,?, '',0,CURRENT_TIMESTAMP)"); $ins->execute([trim((string)($customer['name'] ?? 'Клиент')) ?: 'Клиент',$phone]); $user=['id'=>(int)$this->pdo->lastInsertId(),'points_balance'=>0]; }
            $subtotal=0; $snapshots=[];
            foreach ($items as $item) { $pid=(int)($item['product_id'] ?? 0); $qty=(float)($item['quantity'] ?? 0); if($pid<1||$qty<=0) throw new RuntimeException('validation_error'); $p=$this->pdo->prepare('SELECT id, price, sale_price, is_active FROM products WHERE id=?'); $p->execute([$pid]); $product=$p->fetch(PDO::FETCH_ASSOC); if(!$product) throw new RuntimeException('product_not_found'); if(!(int)$product['is_active']) throw new RuntimeException('product_inactive'); $price=(int)((int)$product['sale_price']>0?$product['sale_price']:$product['price']); $subtotal += $price*$qty; $snapshots[]=[$pid,$qty,$price]; }
            $used = !empty($input['use_all_available_points']) ? min(max(0,(int)$user['points_balance']), (int)$subtotal) : 0;
            [$partner,$warnings]=$this->partner((array)($input['partner'] ?? []));
            $address = $this->pdo->prepare('SELECT id FROM addresses WHERE user_id = ? ORDER BY is_primary DESC, id LIMIT 1'); $address->execute([(int)$user['id']]); $addressId=(int)($address->fetchColumn() ?: 0);
            if (!$addressId) { $newAddress=$this->pdo->prepare("INSERT INTO addresses (user_id,street,recipient_name,recipient_phone,created_at) VALUES (?,'Florix24: адрес уточняется',?,?,CURRENT_TIMESTAMP)"); $newAddress->execute([(int)$user['id'],trim((string)($customer['name'] ?? '')),$phone]); $addressId=(int)$this->pdo->lastInsertId(); }
            $order=$this->pdo->prepare("INSERT INTO orders (user_id,address_id,status,total_amount,delivery_date,discount_applied,points_used,integration_source,external_order_id,partner_user_id,partner_source,external_partner_id,external_partner_name,subtotal_before_points,points_discount_amount,total_after_points,created_at) VALUES (?,?,'new',?,CURRENT_DATE,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)");
            $order->execute([(int)$user['id'],$addressId,$subtotal-$used,$used,$used,'florix24',$externalId,$partner['id']??null,$partner?'florix24':null,$partner['external_id']??null,$partner['name']??null,$subtotal,$used,$subtotal-$used]); $orderId=(int)$this->pdo->lastInsertId();
            $oi=$this->pdo->prepare("INSERT INTO order_items (order_id,product_id,quantity,unit_price) VALUES (?,?,?,?)"); foreach($snapshots as $s)$oi->execute([$orderId,...$s]);
            if($used){$this->pdo->prepare('UPDATE users SET points_balance=points_balance-? WHERE id=?')->execute([$used,$user['id']]); $tx=$this->pdo->prepare("INSERT INTO points_transactions (user_id,order_id,amount,transaction_type,description,source,external_order_id,created_at) VALUES (?,?,?,'usage',?,'florix24_order',?,CURRENT_TIMESTAMP)"); $tx->execute([$user['id'],$orderId,-$used,'Florix24 order '.$externalId,$externalId]);}
            $this->pdo->commit(); return ['result'=>'success','order_id'=>$orderId,'order_number'=>'BG-'.$orderId,'external_order_id'=>$externalId,'customer'=>['id'=>(int)$user['id'],'points_balance_before'=>(int)$user['points_balance'],'points_used'=>$used,'points_balance_after'=>(int)$user['points_balance']-$used],'amounts'=>['subtotal'=>$subtotal,'points_discount'=>$used,'total'=>$subtotal-$used],'partner'=>['user_id'=>$partner['id']??null,'assigned'=>(bool)$partner],'warnings'=>$warnings];
        } catch (\Throwable $e) { if($this->pdo->inTransaction())$this->pdo->rollBack(); throw $e; }
    }

    public function cancel(string $externalId): array { $this->pdo->beginTransaction(); try { $q=$this->pdo->prepare("SELECT id,user_id,status,points_used FROM orders WHERE integration_source='florix24' AND external_order_id=? LIMIT 1");$q->execute([$externalId]);$o=$q->fetch(PDO::FETCH_ASSOC);if(!$o)throw new RuntimeException('validation_error');if($o['status']==='cancelled'){$this->pdo->commit();return ['result'=>'success','order_id'=>(int)$o['id'],'status'=>'cancelled','points_returned'=>0];}$returned=0;if((int)$o['points_used']>0){$check=$this->pdo->prepare("SELECT id FROM points_transactions WHERE order_id=? AND source='florix24_order_cancel' LIMIT 1");$check->execute([$o['id']]);if(!$check->fetchColumn()){$usage=$this->pdo->prepare("SELECT id FROM points_transactions WHERE order_id=? AND transaction_type='usage' AND source='florix24_order' LIMIT 1");$usage->execute([$o['id']]);$related=$usage->fetchColumn();$returned=(int)$o['points_used'];$this->pdo->prepare('UPDATE users SET points_balance=points_balance+? WHERE id=?')->execute([$returned,$o['user_id']]);$this->pdo->prepare("INSERT INTO points_transactions (user_id,order_id,amount,transaction_type,description,source,external_order_id,related_transaction_id,created_at) VALUES (?,?,?,'refund',?,'florix24_order_cancel',?,?,CURRENT_TIMESTAMP)")->execute([$o['user_id'],$o['id'],$returned,'Florix24 cancellation '.$externalId,$externalId,$related]);}}$this->pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=?")->execute([$o['id']]);$this->pdo->commit();return ['result'=>'success','order_id'=>(int)$o['id'],'status'=>'cancelled','points_returned'=>$returned];}catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}}
    private function partner(array $p): array { if(!isset($p['berrygo_user_id']))return [null,[]];$q=$this->pdo->prepare("SELECT id,role,is_blocked,integration_partner_enabled,name FROM users WHERE id=?");$q->execute([(int)$p['berrygo_user_id']]);$u=$q->fetch(PDO::FETCH_ASSOC);if(!$u||(int)$u['is_blocked']||!(int)$u['integration_partner_enabled']||!in_array($u['role'],['partner','manager','admin'],true))return [null,[['code'=>'partner_not_available','message'=>'Партнер не назначен']]];return [['id'=>(int)$u['id'],'external_id'=>(string)($p['florix_user_id']??''),'name'=>(string)($p['name']??$u['name'])],[]]; }
}
