.PHONY: up down migrate test sync-region logs shell fresh seed

up:
	docker compose up -d --build

down:
	docker compose down

migrate:
	docker compose exec app php artisan migrate --force

migrate-fresh:
	docker compose exec app php artisan migrate:fresh --force

seed:
	docker compose exec app php artisan db:seed

test:
	docker compose exec app php artisan test

test-unit:
	docker compose exec app php artisan test --testsuite=Unit

test-feature:
	docker compose exec app php artisan test --testsuite=Feature

sync-region:
	docker compose exec app php artisan billing:sync $(REGION)

sync-lifestream:
	docker compose exec app php artisan lifestream:sync $(REGION)

sync-passwords:
	docker compose exec app php artisan billing:sync-passwords $(REGION)

sync-all:
	docker compose exec app php artisan billing:sync-all
	docker compose exec app php artisan lifestream:sync-all

logs:
	docker compose logs -f app

shell:
	docker compose exec app sh

tinker:
	docker compose exec app php artisan tinker

cache-clear:
	docker compose exec app php artisan cache:clear
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan route:clear

cache-warm:
	docker compose exec app php artisan config:cache
	docker compose exec app php artisan route:cache
	docker compose exec app php artisan view:cache

key-generate:
	docker compose exec app php artisan key:generate

fresh: down
	docker compose up -d --build
	sleep 10
	make migrate
