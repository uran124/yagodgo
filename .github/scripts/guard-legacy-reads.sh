#!/usr/bin/env bash
set -euo pipefail

# Guard: operational code must not read legacy product snapshot fields.
# Allowed compatibility/projection writers are excluded explicitly.

files=$(rg --files src/Controllers src/Services | rg '\.php$' | rg -v 'src/Services/(StockService|PurchaseBatchService)\.php')

patterns=(
  "products\\.free_stock_boxes"
  "products\\.discount_stock_boxes"
  "products\\.current_purchase_batch_id"
)

failed=0
for p in "${patterns[@]}"; do
  if rg -n "$p" $files; then
    echo "\n[guard] forbidden legacy read pattern found: $p" >&2
    failed=1
  fi
done

if [[ $failed -ne 0 ]]; then
  echo "\n[guard] Legacy read guard failed." >&2
  exit 1
fi

echo "[guard] Legacy read guard passed."
