.PHONY: migrate migrate-no-backup status test lint static-checks supply-smoke supply-digest supply-release-check supply-discount-rollover

migrate:
	php bin/migrate.php up

migrate-no-backup:
	php bin/migrate.php up --skip-backup

status:
	php bin/migrate.php status

test:
	vendor/bin/phpunit

lint:
	find src routes bootstrap config bin tests -name '*.php' -print0 | xargs -0 -n1 php -l

static-checks:
	php bin/static_checks.php

supply-smoke:
	php bin/supply_smoke.php

supply-digest:
	php bin/supply_digest.php --threshold=2


supply-release-check:
	php bin/supply_release_check.php


supply-discount-rollover:
	php bin/supply_discount_rollover.php --dry-run --min-age-days=1
