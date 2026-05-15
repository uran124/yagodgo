<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

class PreorderIntentService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{offered_count:int,allocated_boxes:float} */
    public function allocateOfferWave(int $productId, float $availableBoxes, float $pricePerBox, int $ttlHours = 4): array
    {
        if ($productId <= 0 || $availableBoxes <= 0 || $pricePerBox <= 0 || $ttlHours <= 0) {
            return ['offered_count' => 0, 'allocated_boxes' => 0.0];
        }

        $this->pdo->beginTransaction();
        try {
            $select = $this->pdo->prepare(
                "SELECT id, requested_boxes
                 FROM preorder_intents
                 WHERE product_id = ? AND status = 'intent_created'
                 ORDER BY created_at ASC, id ASC"
            );
            $select->execute([$productId]);
            $intents = $select->fetchAll(PDO::FETCH_ASSOC);

            $offeredCount = 0;
            $allocated = 0.0;
            $expiresAt = (new \DateTimeImmutable('now'))->modify('+' . $ttlHours . ' hours')->format('Y-m-d H:i:s');
            $update = $this->pdo->prepare(
                "UPDATE preorder_intents
                 SET status = 'offer_sent',
                     offered_price_per_box = ?,
                     offer_expires_at = ?,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );

            foreach ($intents as $intent) {
                $need = (float)$intent['requested_boxes'];
                if ($need <= 0 || $allocated + $need > $availableBoxes) {
                    continue;
                }

                $update->execute([$pricePerBox, $expiresAt, (int)$intent['id']]);
                $this->logEvent((int)$intent['id'], 'offer_sent', 'intent_created', 'offer_sent', [
                    'offered_price_per_box' => $pricePerBox,
                    'offer_expires_at' => $expiresAt,
                ]);
                $allocated += $need;
                $offeredCount++;

                if ($allocated >= $availableBoxes) {
                    break;
                }
            }

            $this->pdo->commit();
            return ['offered_count' => $offeredCount, 'allocated_boxes' => $allocated];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function expireOffers(): int
    {
        $idsStmt = $this->pdo->query(
            "SELECT id FROM preorder_intents
             WHERE status = 'offer_sent'
               AND offer_expires_at IS NOT NULL
               AND offer_expires_at < CURRENT_TIMESTAMP"
        );
        $ids = $idsStmt ? $idsStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        $stmt = $this->pdo->prepare(
            "UPDATE preorder_intents
             SET status = 'expired', updated_at = CURRENT_TIMESTAMP
             WHERE status = 'offer_sent'
               AND offer_expires_at IS NOT NULL
               AND offer_expires_at < CURRENT_TIMESTAMP"
        );
        $stmt->execute();
        $affected = $stmt->rowCount();
        foreach ($ids as $id) {
            $this->logEvent((int)$id, 'offer_expired', 'offer_sent', 'expired');
        }
        return $affected;
    }

    /** @return array{offered_count:int,allocated_boxes:float} */
    public function reallocateForProduct(int $productId, float $freedBoxes, float $pricePerBox, int $ttlHours = 4): array
    {
        return $this->allocateOfferWave($productId, $freedBoxes, $pricePerBox, $ttlHours);
    }

    private function logEvent(int $intentId, string $eventType, ?string $fromStatus, ?string $toStatus, ?array $meta = null): void
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO preorder_intent_events (preorder_intent_id, event_type, from_status, to_status, meta_json, created_at)
                 VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
            )->execute([
                $intentId,
                $eventType,
                $fromStatus,
                $toStatus,
                $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (Throwable) {
            // non-blocking audit write
        }
    }
}
