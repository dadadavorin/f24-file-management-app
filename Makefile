.PHONY: up down test

up:
	docker compose up -d

down:
	docker compose down

test:
	docker compose exec app php artisan test
