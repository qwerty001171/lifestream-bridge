# Сервис интеграции биллинга с Lifestream

Сервис-посредник между биллингом интернет-оператора и ТВ-платформой Lifestream. Хранит состояние абонентов и подписок в MySQL, синхронизирует данные.

**Стек:** PHP 8.4 + Laravel 12, RoadRunner 2025, MySQL 8, Redis 7, Docker.

---

## Что делает

- **`billing:sync`** — забирает абонентов из биллинга постранично, upsert в `accounts` + `devices`
- **`lifestream:sync`** — создаёт аккаунты в Lifestream, управляет подписками по маппингу офферов
- **`billing:sync-passwords`** — находит изменения паролей по SHA256-хэшу, отправляет в Lifestream
- **Upsale API** — принимает запросы от Lifestream при подключении/смене подписки (ensure → commit)

Расписание через cron-плагин RoadRunner (`.rr.yaml`).

---

## Быстрый старт

```bash
cp .env.example .env
# Заполнить: APP_KEY, LIFESTREAM_URL, BILLING_SOURCE_A_URL, BILLING_SOURCE_A_KEY

docker compose up -d --build
curl http://localhost:8080/api/health
```

Swagger UI: `http://localhost:8080/api/documentation`

> **APP_KEY обязателен** — контейнер не стартует без него. Сгенерировать: `php artisan key:generate --show`

---

## Переменные окружения

| Переменная | Описание |
|---|---|
| `APP_KEY` | Ключ шифрования — обязателен |
| `APP_ENV` | `local` / `production` |
| `MIGRATE_ON_STARTUP` | `true` — Docker Compose; `false` — K8s (миграции в initContainer) |
| `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | MySQL |
| `REDIS_HOST` | Redis |
| `LIFESTREAM_URL` | URL API Lifestream |
| `LIFESTREAM_TIMEOUT` / `LIFESTREAM_RETRIES` / `LIFESTREAM_RATE_LIMIT` | Таймаут, ретраи, лимит запросов/сек |
| `BILLING_SOURCE_A_URL` / `BILLING_SOURCE_A_KEY` | Биллинг источник A |
| `BILLING_PAGE_LIMIT` | Размер страницы при опросе биллинга (по умолчанию `1000`) |

> Аутентификация к Lifestream — по IP (whitelist). Сообщите менеджеру Lifestream внешний IP сервера.

---

## Команды

```bash
docker compose exec app php artisan billing:sync source_a
docker compose exec app php artisan billing:sync-all

docker compose exec app php artisan lifestream:sync source_a
docker compose exec app php artisan lifestream:sync-all

docker compose exec app php artisan billing:sync-passwords source_a
docker compose exec app php artisan billing:sync-passwords-all
```

Все команды идемпотентны.

---

## Структура БД

| Таблица | Что хранит |
|---|---|
| `accounts` | Абоненты. Уникальны по `(external_id, billing_source)` |
| `devices` | MAC-адреса устройств |
| `lifestream_offers` | Маппинг пакетов биллинга → offer_id Lifestream |
| `lifestream_subscriptions` | Состояние подписок |
| `lifestream_transactions` | Двухфазные upsale-операции (ensure → commit) |
| `lifestream_operation_logs` | Журнал операций |

---

## Добавление нового источника

**1. `.env`:**
```dotenv
BILLING_SOURCE_B_URL=http://billing-b.example.com/api
BILLING_SOURCE_B_KEY=secret-key-b
```

**2. `config/billing.php`** — добавить секцию `source_b` по аналогии с `source_a`.

**3.** Добавить маппинг офферов в `database/seeders/OfferSeeder.php`, затем:
```bash
docker compose exec app php artisan db:seed --class=OfferSeeder
```

`billing:sync-all` и `lifestream:sync-all` подхватят новый источник автоматически.

---

## Тесты

```bash
docker compose exec app ./vendor/bin/phpunit
```

---

## Kubernetes

Манифесты в [`k8s/`](./k8s/). Миграции запускаются в initContainer (`deployment.yaml`), в основном контейнере `MIGRATE_ON_STARTUP=false`.

---

## Типовые инциденты

**Lifestream 403** — IP не в белом списке. Сообщите менеджеру текущий внешний IP контейнера.

**`billing:sync` не соединяется** — проверить доступность биллинга:
```bash
docker compose exec app curl $BILLING_SOURCE_A_URL/users -H "X-API-Key: $BILLING_SOURCE_A_KEY"
```

**Расследование** — таблица `lifestream_operation_logs`:
```bash
docker exec lifestream-bridge-mysql-new mysql -ubilling -psecret billing_service \
  -e "SELECT operation_type, result, error_message, created_at FROM lifestream_operation_logs ORDER BY created_at DESC LIMIT 20;"
```
