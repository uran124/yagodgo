<?php
declare(strict_types=1);
namespace App\Controllers;

use PDO;

/** Admin-only lifecycle for the inbound Florix24 bearer token. */
final class Florix24TokenAdminController
{
    private const PERMISSIONS = ['customers.read', 'orders.create', 'orders.cancel', 'catalog.read'];
    public function __construct(private PDO $pdo) {}

    public function create(): void
    {
        $token = 'bg_live_' . rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '=');
        $hash = password_hash($token, PASSWORD_DEFAULT); $prefix = substr($token, 0, 16);
        $permissions = json_encode(self::PERMISSIONS, JSON_UNESCAPED_SLASHES);
        $existing = $this->pdo->prepare("SELECT id FROM integration_clients WHERE source='florix24' LIMIT 1"); $existing->execute(); $id = $existing->fetchColumn();
        if ($id) {
            $this->pdo->prepare('UPDATE integration_clients SET name=?,token_hash=?,token_prefix=?,permissions=?,is_active=1,revoked_at=NULL,created_at=CURRENT_TIMESTAMP WHERE id=?')->execute(['Florix24',$hash,$prefix,$permissions,$id]);
        } else {
            $this->pdo->prepare('INSERT INTO integration_clients (name,source,token_hash,token_prefix,permissions,is_active,rate_limit_per_minute,created_at) VALUES (?,\'florix24\',?,?,?,?,60,CURRENT_TIMESTAMP)')->execute(['Florix24',$hash,$prefix,$permissions,1]);
        }
        $_SESSION['florix24_new_token'] = $token;
        header('Location: /admin/settings/integrations?token_created=1'); exit;
    }

    public function revoke(): void
    {
        $this->pdo->prepare("UPDATE integration_clients SET is_active=0,revoked_at=CURRENT_TIMESTAMP WHERE source='florix24'")->execute();
        header('Location: /admin/settings/integrations?token_revoked=1'); exit;
    }
}
