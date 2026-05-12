# Сервис интеграции биллинга с Lifestream

Сервис-посредник между биллингом интернет-оператора и ТВ-платформой Lifestream. Хранит согласованное состояние абонентов и подписок в MySQL, синхронизирует данные в обе стороны.

**Стек:** PHP 8.2 + Laravel 10, RoadRunner 2025, MySQL 8, Redis 7, Docker.

---

## Что делает

```
Биллинг (REST API)
      │
      │  billing:sync (каждые 5 мин)
      ▼
   MySQL ──── lifestream:sync (каждые 10 мин) ────► Lifestream TV API
      │                                                      │
      │  billing:sync-passwords (каждые 15 мин)             │
      └──────────────────────────────────────────────────────┘
                               ▲
                  upsale ensure/commit (входящие запросы от Lifestream)
```

- **`billing:sync`** — забирает абонентов из биллинга постранично, upsert в `accounts` + `devices`
- **`lifestream:sync`** — создаёт аккаунты в Lifestream, управляет подписками по маппингу офферов
- **`billing:sync-passwords`** — находит изменения паролей по SHA256-хэшу, отправляет в Lifestream
- **Upsale API** — принимает входящие запросы от Lifestream при подключении/смене подписки (двухфазный протокол ensure → commit)

Расписание — через cron-плагин RoadRunner (`.rr.yaml`), не системный cron.

---

## Быстрый старт

```bash
cp .env.example .env
# Заполнить: LIFESTREAM_URL, BILLING_REGION_A_URL, BILLING_REGION_A_KEY

docker compose up -d --build
```

При первом старте контейнер автоматически запускает миграции.

**Проверка:**
```bash
curl http://localhost:8080/api/health
# {"status":"ok","timestamp":"...","checks":{"database":"ok"}}
```

**Swagger UI:** `http://localhost:8080/api/documentation`

---

## Переменные окружения

| Переменная | Описание |
|---|---|
| `APP_KEY` | Генерируется автоматически при первом старте |
| `APP_ENV` | `local` / `production` |
| `DB_HOST` | Хост MySQL (в Docker: `mysql_new`) |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Параметры MySQL |
| `REDIS_HOST` | Хост Redis (в Docker: `redis`) |
| `LIFESTREAM_URL` | URL API Lifestream — выдаёт менеджер по интеграции |
| `LIFESTREAM_RATE_LIMIT` | Максимум запросов/сек к Lifestream (по умолчанию `10`) |
| `LIFESTREAM_TIMEOUT` | Таймаут запросов в секундах (по умолчанию `30`) |
| `LIFESTREAM_RETRIES` | Повторных попыток при ошибке (по умолчанию `3`) |
| `BILLING_PAGE_LIMIT` | Размер страницы при опросе биллинга (по умолчанию `1000`) |
| `BILLING_REGION_A_URL` | URL API биллинга региона A |
| `BILLING_REGION_A_KEY` | API-ключ биллинга региона A |

> **Аутентификация к Lifestream** — по IP-адресу (whitelist). API-ключ не нужен.
> Сообщите менеджеру Lifestream внешний IP сервера — без этого запросы вернут 403.

---

## Команды

```bash
# Синхронизация абонентов из биллинга
docker compose exec app php artisan billing:sync region_a
docker compose exec app php artisan billing:sync-all

# Создание аккаунтов и управление подписками в Lifestream
docker compose exec app php artisan lifestream:sync region_a
docker compose exec app php artisan lifestream:sync-all

# Синхронизация паролей
docker compose exec app php artisan billing:sync-passwords region_a
docker compose exec app php artisan billing:sync-passwords-all

# Заполнить маппинг офферов
docker compose exec app php artisan db:seed --class=OfferSeeder

# Сбросить БД и пересоздать
docker compose exec app php artisan migrate:fresh --force
```

Все синк-команды **идемпотентны** — повторный запуск не создаёт дублей.

---

## Структура БД

Все первичные ключи — UUID (тип `uuid`, колонка `uuid`). Внешние ключи — `account_uuid`.

| Таблица | Что хранит |
|---|---|
| `accounts` | Абоненты. Уникальны по `(external_id, billing_source)` |
| `devices` | MAC-адреса устройств, привязанные к аккаунту |
| `lifestream_offers` | Маппинг кода пакета биллинга → offer_id Lifestream, по регионам |
| `lifestream_subscriptions` | Состояние подписок абонентов |
| `lifestream_transactions` | Двухфазные upsale-операции (ensure → commit) |
| `lifestream_operation_logs` | Журнал всех операций для аудита и расследования |

---

## API

Swagger UI: `http://localhost:8080/api/documentation`

| Метод | Путь | Описание |
|---|---|---|
| GET | `/api/health` | Проверка состояния сервиса |
| POST | `/api/upsale/v3/add-subscription/ensure` | Проверка возможности подключения подписки |
| POST | `/api/upsale/v3/add-subscription/commit` | Подтверждение подключения |
| POST | `/api/upsale/v3/replace-subscription/ensure` | Проверка возможности смены подписки |
| POST | `/api/upsale/v3/replace-subscription/commit` | Подтверждение смены |

Параметры передаются в **query string**. Тело запроса (JSON) — опциональный контекст для логирования.

---

## Тесты

```bash
docker compose run --no-deps --entrypoint sh app -c "composer dump-autoload -q && php vendor/bin/phpunit --testdox"
```

52 теста, 122 утверждения.

---

## Добавление нового биллинг-региона

**1. `.env`:**
```dotenv
BILLING_REGION_B_URL=http://billing-node-b.example.com/api
BILLING_REGION_B_KEY=secret-key-b
```

**2. `config/billing.php`:**
```php
'region_b' => [
    'base_url' => env('BILLING_REGION_B_URL', ''),
    'api_key'  => env('BILLING_REGION_B_KEY', ''),
    'timeout'  => 30,
],
```

**3. `database/seeders/OfferSeeder.php`** — добавить маппинг пакетов:
```php
['billing_source' => 'region_b', 'billing_package_code' => '10', 'lifestream_offer_id' => '6010', 'name' => 'Базовый', 'is_active' => true],
```

```bash
docker compose exec app php artisan db:seed --class=OfferSeeder
docker compose exec app php artisan billing:sync region_b
docker compose exec app php artisan lifestream:sync region_b
```

Изменений в коде не требуется — `billing:sync-all` и `lifestream:sync-all` подхватят новый регион автоматически.

---

## Kubernetes

Манифесты в [`k8s/`](./k8s/): `deployment.yaml`, `service.yaml`, `secret.yaml`, `configmap.yaml`, `cronjob.yaml`.

Миграции при деплое — init-контейнер в `deployment.yaml`:
```bash
php artisan migrate --force
```

---

## Типовые инциденты

**Lifestream возвращает 403** — IP сервера не в белом списке Lifestream. Сообщите менеджеру по интеграции текущий внешний IP контейнера.

**`billing:sync` падает с ошибкой соединения** — проверить `BILLING_REGION_A_URL` и доступность биллинга:
```bash
docker compose exec app curl $BILLING_REGION_A_URL/users -H "X-API-Key: $BILLING_REGION_A_KEY"
```

**Расследование инцидентов** — таблица `lifestream_operation_logs`: все операции с типом, результатом и текстом ошибки.
```bash
docker exec lifestream-bridge-mysql-new mysql -ubilling -psecret billing_service \
  -e "SELECT operation_type, result, error_message, created_at FROM lifestream_operation_logs ORDER BY created_at DESC LIMIT 20;"
```
