ALTER TABLE `preorder_intents`
  MODIFY COLUMN `status` enum('intent_created','offer_sent','confirmed','declined','expired','completed')
  NOT NULL DEFAULT 'intent_created';
