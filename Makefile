.PHONY: migrate status test supply-smoke supply-digest supply-release-check supply-discount-rollover

migrate:
	php bin/migrate.php up

status:
	php bin/migrate.php status

test:
	vendor/bin/phpunit

supply-smoke:
	php bin/supply_smoke.php

supply-digest:
	php bin/supply_digest.php --threshold=2


supply-release-check:
	php bin/supply_release_check.php


supply-discount-rollover:
	php bin/supply_discount_rollover.php --dry-run --min-age-days=1
