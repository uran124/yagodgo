# Preorder Roadmap v2 Backlog (Post-release)

Этот файл фиксирует незакрытые пункты этапа 9 как управляемый backlog.

## Epic 1: Персонализация офферов
- [ ] Персональные окна подтверждения по сегментам клиентов
- [ ] Адаптивный TTL по сегменту/категории товара
- Owner: `<name>`
- Target: `<quarter>`

## Epic 2: Сегментация и приоритизация
- [ ] Приоритетные волны для "теплых" клиентов
- [ ] Скоринг вероятности confirm
- Owner: `<name>`
- Target: `<quarter>`

## Epic 3: Рефакторинг домена
- [ ] Выделить preorder в отдельный модуль
- [ ] Разделить controller/service responsibilities
- [ ] Ввести явные idempotency keys для confirm/decline
- Owner: `<name>`
- Target: `<quarter>`

## Epic 4: Performance & retention
- [ ] Регулярный archive sweep по retention-политике
- [ ] Индекс-тюнинг по фактическим explain-планам
- [ ] Дашборд SLO на jobs
- Owner: `<name>`
- Target: `<quarter>`

## Governance
- Review cadence: weekly (операционка), monthly (продукт), quarterly (архитектура)
- Decision log: `docs/runbooks/preorder-go-live-execution-log.md`
