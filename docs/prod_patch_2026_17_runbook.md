# PROD patch 2026_17 (split into 3 parts)

1. `database/2026_17_prod_patch_part1_schema.sql` — только DDL (таблицы/колонки/индексы).
2. `database/2026_17_prod_patch_part2_backfill.sql` — backfill и нормализация данных.
3. `database/2026_17_prod_patch_part3_constraints_compat.sql` — FK/checks/совместимость/контрольные выборки.

Рекомендуемый порядок:

```sql
SOURCE database/2026_17_prod_patch_part1_schema.sql;
SOURCE database/2026_17_prod_patch_part2_backfill.sql;
SOURCE database/2026_17_prod_patch_part3_constraints_compat.sql;
```

Перед запуском:

- Сделать backup.
- Прогнать на staging-копии прода.
- Зафиксировать окно работ.

После запуска:

- Проверить контрольные SELECT из part3.
- Запустить `php bin/migrate.php status`.
- Запустить `php bin/migrate.php up --dry-run`.
