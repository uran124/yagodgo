#!/usr/bin/env bash
set -euo pipefail

if [[ -z "${DB_HOST:-}" || -z "${DB_PORT:-}" || -z "${DB_DATABASE:-}" || -z "${DB_USERNAME:-}" || -z "${DB_PASSWORD:-}" ]]; then
  echo "Missing DB_* environment variables." >&2
  exit 1
fi

MYSQL_CMD=(mysql --protocol=tcp -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}")

"${MYSQL_CMD[@]}" < .github/ci/test_schema.sql

for file in $(find database -maxdepth 1 -name '*.sql' | sort); do
  echo "Applying migration: ${file}"
  "${MYSQL_CMD[@]}" < "${file}"
done
