#!/usr/bin/env bash
set -euo pipefail

# Client operational code must not fallback from batch price to product price.
files=(
  src/Controllers/ClientController.php
  src/Services/ClientCatalogService.php
)

pattern='COALESCE\(pb\.[^\)]*,\s*p\.price\)'

if rg -n "$pattern" "${files[@]}"; then
  echo "[guard] forbidden client price fallback found (batch->product)." >&2
  exit 1
fi

echo "[guard] Price fallback guard passed."
