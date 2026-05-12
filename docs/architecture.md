# Архитектура сервиса интеграции биллинга с Lifestream

## Назначение

Сервис-посредник между двумя системами:

- **Биллинг (ISP)** — источник истины по абонентам, пакетам и паролям. Может быть несколько региональных узлов.
- **Lifestream (IPTV-платформа)** — приёмник, который держится в синхронизированном состоянии с данными биллинга.

---

## Поток данных

```
                       ┌─────────────────────────────────────────┐
                       │           BILLING SERVICE                │
                       │                                          │
┌──────────────┐       │  ┌──────────────┐  ┌─────────────────┐  │      ┌─────────────────┐
│  Биллинг     │       │  │BillingFetcher│  │AccountSyncSvc   │  │      │   Lifestream    │
│  Регион A    │──────▶│  │(пагинация)   │─▶│(upsert аккаунты │  │      │  IPTV-платформа │
└──────────────┘       │  └──────────────┘  │ в локальную БД) │  │      │                 │
                       │                    └────────┬────────┘  │      │                 │
┌──────────────┐       │                             │           │      │                 │
│  Биллинг     │──────▶│  ┌──────────────────────────▼─────────┐ │      │                 │
│  Регион B    │       │  │         Локальная MySQL БД          │ │      │                 │
└──────────────┘       │  │  accounts  devices                  │ │      │                 │
                       │  │  lifestream_offers                  │ │      │                 │
                       │  │  lifestream_subscriptions           │ │      │                 │
                       │  │  lifestream_transactions            │ │      │                 │
                       │  │  lifestream_operation_logs          │ │      │                 │
                       │  └──────────────────────────┬──────────┘ │      │                 │
                       │                             │            │      │                 │
                       │  ┌──────────────────────────▼──────────┐ │      │                 │
                       │  │      LifestreamSyncService           │─│─────▶│  createAccount  │
                       │  │      PasswordSyncService             │─│─────▶│  resetPassword  │
                       │  │      UpsaleService (HTTP)            │◀│─────▶│  upsale ensure/ │
                       │  └─────────────────────────────────────┘ │      │  commit         │
                       │                                           │      └─────────────────┘
                       │  ┌─────────────────────────────────────┐  │
                       │  │         HTTP API (RoadRunner)        │  │
                       │  │  GET  /api/health                    │  │
                       │  │  POST /api/upsale/v3/...             │  │
                       │  └─────────────────────────────────────┘  │
                       └─────────────────────────────────────────┘
```

---

## Потоки синхронизации

### 1. Синхронизация биллинга (`billing:sync {region}`)

**Цель:** забрать абонентов из биллинга и сохранить в локальной БД.

1. `BillingAdapterFactory::make($region)` создаёт `HttpBillingAdapter` для региона из `config/billing.php`.
2. `BillingFetcher::fetchAll()` обходит страницы по флагу `nextPage` / `next_page` пагинации.
3. `AccountSyncService::sync()` делает `updateOrCreate` по паре `(external_id, billing_source)`.
4. Если есть MAC-адрес — upsert в `devices`.
5. Каждый запуск логируется в `lifestream_operation_logs`.

**Маппинг полей биллинга:**

| Поле модели | Источник (приоритет слева) |
|---|---|
| `external_id` | `User.mid` (если > 0), иначе `User.id` |
| `login` | `User.name` |
| `paket` | `Main.paket` → `User.paket` → `User.iptv_packet` |
| `mac` | `User.mac` → `Main.mac` |
| `email` | `User.email` → `Main.email` |

**Идемпотентность:** `updateOrCreate` — повторный запуск не создаёт дублей.

---

### 2. Синхронизация Lifestream (`lifestream:sync {region}`)

**Цель:** привести подписки в Lifestream в соответствие с текущим пакетом каждого абонента.

**Источник истины — поле `paket` из биллинга.**

Для каждого аккаунта:
1. Если нет `lifestream_id` → создать аккаунт через `POST /v2/accounts`, сохранить полученный ID.
2. Определить целевой оффер: `paket` → маппинг в `lifestream_offers` по региону.
3. Получить все активные подписки аккаунта из локальной БД.
4. Подписки, не совпадающие с целевым оффером → отключить в Lifestream (`valid=false`), пометить `inactive` в БД.
5. Целевой оффер → включить (`valid=true`), если ещё не активен.
6. Если `paket` пустой или маппинга нет → отключить все активные подписки.

| Сценарий | Что происходит |
|---|---|
| Пакет есть, маппинг найден | Включить нужный оффер, отключить все остальные активные |
| Пакет изменился | Старый оффер отключается, новый включается |
| Пакет пустой | Все активные подписки отключаются |
| Пакет есть, маппинга нет | Все активные подписки отключаются |

---

### 3. Синхронизация паролей (`billing:sync-passwords {region}`)

**Цель:** обнаружить смены паролей в биллинге и передать в Lifestream.

1. Забрать всех абонентов из биллинга (тот же цикл с пагинацией).
2. Сравнить `sha256(plainPassword)` с хранящимся `password_hash` в локальной БД.
3. Если хэши различаются и есть пароль открытым текстом → вызвать `POST /v2/accounts/{id}/reset-password`.
4. При успехе обновить `password_hash` в БД.

**Безопасность:**
- Пароли в открытом виде **не хранятся** локально — только SHA256-хэш.
- `OperationLogger` автоматически исключает поля с паролями из `operation_data`.

> **Важно:** эндпоинт `reset-password` принимает **JSON body**, несмотря на то что спецификация Lifestream указывает form-urlencoded.

---

### 4. Upsale — двухфазная операция (Lifestream TV → сервис)

Lifestream TV вызывает наши эндпоинты когда абонент инициирует покупку или смену подписки через TV-приложение.

```
Lifestream TV
      │
      │  POST /api/upsale/v3/add-subscription/ensure
      │  ?accountId=...&newOfferId=...&qsTransactionId=...
      │
      ▼
  UpsaleService::ensure()
      │  1. Идемпотентность по qsTransactionId
      │  2. Найти аккаунт по lifestream_id (fallback по login)
      │  3. Если подписка уже активна → no_action_required
      │  4. Создать Transaction (phase=ensure)
      │  Возвращает: { result: "operation_ensured", billingTransactionId: "uuid" }
      │
Lifestream TV
      │  (предоставляет абоненту доступ к контенту)
      │
      │  POST /api/upsale/v3/add-subscription/commit
      │  ?billingTransactionId=...&qsTransactionId=...
      │
      ▼
  UpsaleService::commit()
      │  1. Загрузить транзакцию по UUID (lock for update)
      │  2. Для replace: деактивировать старую подписку в БД
      │  3. Активировать новую подписку в БД
      │  4. Обновить Transaction (phase=committed)
      │  Возвращает: { result: "operation_commited", billingStartTimestamp }
      ▼
Lifestream TV
```

**Коды результата:**

| Код | Когда |
|---|---|
| `operation_ensured` | Транзакция создана, ждёт commit |
| `operation_commited` | Уже подтверждена (идемпотентность) |
| `no_action_required` | Подписка уже активна |
| `no_subscription_rules` | Аккаунт не найден |

---

## Структура БД

Все первичные ключи — тип `uuid`, колонка `uuid`. Внешние ключи — `account_uuid`.

| Таблица | PK | Описание |
|---|---|---|
| `accounts` | `uuid` | Абоненты. Уникальны по `(external_id, billing_source)` |
| `devices` | `uuid` | MAC-адреса устройств. FK: `account_uuid → accounts.uuid` |
| `lifestream_offers` | `uuid` | Маппинг `billing_package_code → lifestream_offer_id` по регионам |
| `lifestream_subscriptions` | `uuid` | Состояние подписок. FK: `account_uuid → accounts.uuid` |
| `lifestream_transactions` | `uuid` | Двухфазные upsale-операции. FK: `account_uuid → accounts.uuid` |
| `lifestream_operation_logs` | `uuid` | Журнал всех операций |

---

## Изоляция регионов

| Сущность | Как изолируется |
|---|---|
| **Аккаунты** | Уникальный ключ `(external_id, billing_source)` |
| **Офферы** | Уникальный ключ `(billing_source, billing_package_code)` |
| **Подписки / транзакции / логи** | Поле `billing_source` |
| **HTTP-адаптеры** | Каждый регион — свой `HttpBillingAdapter` с URL и API-ключом |

---

## Структура компонентов

```
app/
├── Contracts/
│   ├── BillingAdapterInterface      ← getUsers(page, limit), getRegion()
│   └── LifestreamClientInterface    ← createAccount, manageSubscription, resetPassword
├── Adapters/
│   ├── HttpBillingAdapter           ← Guzzle, реализует BillingAdapterInterface
│   └── FakeBillingAdapter           ← In-memory, для тестов
├── Http/Clients/
│   └── HttpLifestreamClient         ← Guzzle + retry + rate limit
├── Services/
│   ├── BillingAdapterFactory        ← Создаёт HttpBillingAdapter по региону
│   ├── BillingFetcher               ← Генератор постраничного обхода
│   ├── AccountSyncService           ← Upsert аккаунтов и устройств
│   ├── LifestreamSyncService        ← Управление подписками в Lifestream
│   ├── PasswordSyncService          ← Обнаружение и передача смен паролей
│   ├── UpsaleService                ← Двухфазный ensure/commit
│   ├── LoginSanitizer               ← Транслитерация кириллицы для логинов
│   └── OperationLogger              ← Запись в lifestream_operation_logs (без секретов)
├── Console/Commands/
│   ├── SyncBillingCommand           ← billing:sync {region}
│   ├── SyncBillingAllCommand        ← billing:sync-all
│   ├── SyncLifestreamCommand        ← lifestream:sync {region}
│   ├── SyncLifestreamAllCommand     ← lifestream:sync-all
│   ├── SyncPasswordsCommand         ← billing:sync-passwords {region}
│   └── SyncPasswordsAllCommand      ← billing:sync-passwords-all
└── Http/Controllers/
    ├── HealthController             ← GET /api/health
    └── UpsaleController             ← POST /api/upsale/v3/...
```

---

## Расписание (RoadRunner cron)

Настраивается в `.rr.yaml`. Запускается автоматически при старте контейнера.

| Команда | Интервал | Что делает |
|---|---|---|
| `billing:sync-all` | каждые 5 мин | Тянет абонентов из всех регионов в локальную БД |
| `lifestream:sync-all` | каждые 10 мин | Синхронизирует подписки с Lifestream |
| `billing:sync-passwords-all` | каждые 15 мин | Обнаруживает смены паролей, обновляет Lifestream |

---

## Добавление нового региона биллинга

1. Добавить в `.env` и `config/billing.php` новый регион.
2. Добавить маппинг офферов в `OfferSeeder.php`.
3. Запустить сидер и первичный синк.

Изменений в коде не требуется — `*-all` команды подхватят новый регион из `config/billing.php` автоматически.
