#!/usr/bin/env bash
set -euo pipefail

# Client operational code must not fallback from batch price to product price.
files=(
  src/Controllers/ClientController.php
  src/Services/ClientCatalogService.php
)

patterns=(
  'COALESCE\(pb\.[^\)]*,\s*p\.price\)'
  'COALESCE\(pb\.[^\)]*,\s*p\.preorder_price_per_box\)'
  'COALESCE\(pb\.[^\)]*,\s*p\.instant_price_per_box\)'
)

for pattern in "${patterns[@]}"; do
  if rg -n "$pattern" "${files[@]}"; then
    echo "[guard] forbidden client price fallback found (batch->product): $pattern" >&2
    exit 1
  fi
done

echo "[guard] Price fallback guard passed."
