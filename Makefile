.PHONY: migrate status test supply-smoke supply-digest

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
