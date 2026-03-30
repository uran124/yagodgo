.PHONY: migrate status test

migrate:
	php bin/migrate.php up

status:
	php bin/migrate.php status

test:
	vendor/bin/phpunit
