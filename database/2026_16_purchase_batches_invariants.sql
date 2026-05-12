ALTER TABLE `purchase_batches`
  ADD CONSTRAINT `chk_purchase_batches_non_negative_totals`
    CHECK (`boxes_total` >= 0 AND `boxes_reserved` >= 0 AND `boxes_free` >= 0 AND `boxes_discount` >= 0 AND `boxes_sold` >= 0 AND `boxes_written_off` >= 0 AND `boxes_remaining` >= 0),
  ADD CONSTRAINT `chk_purchase_batches_remaining_formula`
    CHECK (ABS(`boxes_remaining` - (`boxes_total` - `boxes_sold` - `boxes_written_off`)) <= 0.01);
