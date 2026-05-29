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

    public function cancelUnconfirmedByDeadline(int $ttlHours = 48): int
    {
        $ttlHours = max(1, $ttlHours);
        $idsStmt = $this->pdo->prepare(
            "SELECT id
             FROM preorder_intents
             WHERE status = 'intent_created'
               AND created_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ? HOUR)"
        );
        $idsStmt->execute([$ttlHours]);
        $ids = $idsStmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $this->pdo->prepare(
            "UPDATE preorder_intents
             SET status = 'declined', updated_at = CURRENT_TIMESTAMP
             WHERE status = 'intent_created'
               AND created_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ? HOUR)"
        );
        $stmt->execute([$ttlHours]);
        $affected = $stmt->rowCount();
        foreach ($ids as $id) {
            $this->logEvent((int)$id, 'auto_cancel_unconfirmed', 'intent_created', 'declined');
        }
        return $affected;
    }

    /**
     * @return array{ok:bool,from_status:?string,to_status:?string}
     */
    public function decideByManager(int $intentId, string $action): array
    {
        if ($intentId <= 0 || !in_array($action, ['confirm', 'decline'], true)) {
            return ['ok' => false, 'from_status' => null, 'to_status' => null];
        }

        $stmt = $this->pdo->prepare('SELECT id, status FROM preorder_intents WHERE id = ? LIMIT 1');
        $stmt->execute([$intentId]);
        $intent = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$intent) {
            return ['ok' => false, 'from_status' => null, 'to_status' => null];
        }

        $fromStatus = (string)($intent['status'] ?? '');
        $confirmable = ['waiting_batch', 'linked_to_batch', 'awaiting_price_confirmation', 'offer_sent', 'intent_created', 'confirmed'];
        $declinable = ['waiting_batch', 'linked_to_batch', 'awaiting_price_confirmation', 'offer_sent', 'intent_created', 'confirmed'];

        if ($action === 'confirm') {
            if (!in_array($fromStatus, $confirmable, true)) {
                return ['ok' => false, 'from_status' => $fromStatus, 'to_status' => null];
            }

            if ($fromStatus === 'confirmed') {
                return ['ok' => true, 'from_status' => $fromStatus, 'to_status' => 'confirmed'];
            }

            $token = bin2hex(random_bytes(24));
            $update = $this->pdo->prepare(
                "UPDATE preorder_intents
                 SET status = 'confirmed', checkout_token = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND status IN ('waiting_batch','linked_to_batch','awaiting_price_confirmation','offer_sent','intent_created')"
            );
            $update->execute([$token, $intentId]);
            if ($update->rowCount() < 1) {
                return ['ok' => false, 'from_status' => $fromStatus, 'to_status' => null];
            }
            $this->logEvent($intentId, 'manager_confirmed', $fromStatus, 'confirmed');
            return ['ok' => true, 'from_status' => $fromStatus, 'to_status' => 'confirmed'];
        }

        if (!in_array($fromStatus, $declinable, true)) {
            return ['ok' => false, 'from_status' => $fromStatus, 'to_status' => null];
        }

        $update = $this->pdo->prepare(
            "UPDATE preorder_intents
             SET status = 'declined', checkout_token = NULL, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND status IN ('waiting_batch','linked_to_batch','awaiting_price_confirmation','offer_sent','intent_created','confirmed')"
        );
        $update->execute([$intentId]);
        if ($update->rowCount() < 1) {
            return ['ok' => false, 'from_status' => $fromStatus, 'to_status' => null];
        }
        $this->logEvent($intentId, 'manager_declined', $fromStatus, 'declined');
        return ['ok' => true, 'from_status' => $fromStatus, 'to_status' => 'declined'];
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
