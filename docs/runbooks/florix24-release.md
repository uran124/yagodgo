# Florix24 — production handoff and release checklist

## Before deployment

- [ ] Review and merge the Florix24 migration, lifecycle, UI, journal and acceptance PRs in order.
- [ ] Confirm `mysqldump` is installed and writable `MIGRATION_BACKUP_DIR` is configured, or document the externally verified backup procedure.
- [ ] Confirm the release host serves `<web-root>/feeds/catalog.yml` as a static file and does not execute PHP in `feeds/`.
- [ ] Add cron: `*/10 * * * * cd /path/to/yagodgo && /usr/bin/php bin/generate_catalog_feed.php >> log/catalog_feed.log 2>&1`.
- [ ] Confirm an administrator will receive the one-time token over a secure channel only.

## Deployment

```bash
make migrate
php bin/generate_catalog_feed.php --force
php bin/florix24_release_check.php
```

- [ ] Open **Settings → Integrations → Incoming Florix24 API**.
- [ ] Create the token, copy it once, and transfer it to Florix24 securely.
- [ ] Verify permissions: `customers.read`, `orders.create`, `orders.cancel`, `catalog.read`.
- [ ] Keep IP restriction disabled until Florix24 provides stable outbound IP/CIDR and the proxy topology is confirmed.
- [ ] Enable only explicitly approved users as integration partners.

## Production smoke test

Replace placeholders; never paste a live token into a ticket, shell history, or shared log.

```bash
curl -i -H "Authorization: Bearer $FLORIX24_TOKEN" \
  'https://berrygo.ru/api/v1/integrations/florix/customers/by-phone?phone=79000000000'

curl -i -H "Authorization: Bearer $FLORIX24_TOKEN" \
  -H 'Content-Type: application/json' \
  -d @florix-order.json \
  https://berrygo.ru/api/v1/integrations/florix/orders

# Repeat the exact request: the response must contain idempotent_replay=true.
curl -I https://berrygo.ru/feeds/catalog.yml
```

- [ ] Verify the inbound journal records correlation ID, status, external order and points, without token data.
- [ ] Verify a test cancellation returns points only once and creates a partner reversal only when an award exists.

## Monitoring and rollback

- Monitor HTTP `401`, `403`, `422`, `429`, and `500`, plus `catalog_feed_state.last_error` and stale `is_dirty=1` state.
- Rotate/revoke the token immediately on exposure, then issue a replacement in the admin UI.
- If a release must be rolled back, disable the integration token first, preserve the request journal, restore the pre-migration backup, and reconcile any Florix24 orders created after the backup before re-enabling traffic.
