CALL yg_add_column_if_missing('product_types', '`in_sitemap` tinyint(1) NOT NULL DEFAULT 1');
CALL yg_add_column_if_missing('content_categories', '`in_sitemap` tinyint(1) NOT NULL DEFAULT 1');
