.PHONY: help up down build restart logs logs-app logs-web logs-db shell shell-app shell-nuxt shell-db psql redis-cli artisan composer pnpm test test-backend test-frontend key fresh migrate seed seed-demo queue horizon tinker routes install init cert renew backup restore status health

include .env
export

COMPOSE = docker compose
APP    = $(COMPOSE) exec -T app
WEB    = $(COMPOSE) exec -T web
NUXT   = $(COMPOSE) exec -T nuxt
DB     = $(COMPOSE) exec -T db

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

up: ## Start all services in detached mode
	$(COMPOSE) up -d
	@echo "Waiting for services to be healthy..."
	@sleep 5
	@$(MAKE) health

down: ## Stop all services
	$(COMPOSE) down

build: ## Build all images
	$(COMPOSE) build

rebuild: ## Rebuild without cache
	$(COMPOSE) build --no-cache

restart: ## Restart all services
	$(COMPOSE) restart

ps: ## List running services
	$(COMPOSE) ps

logs: ## Tail logs from all services
	$(COMPOSE) logs -f --tail=100

logs-app: ## Tail app (php-fpm) logs
	$(COMPOSE) logs -f --tail=100 app

logs-web: ## Tail nginx logs
	$(COMPOSE) logs -f --tail=100 web

logs-db: ## Tail postgres logs
	$(COMPOSE) logs -f --tail=100 db

logs-nuxt: ## Tail nuxt logs
	$(COMPOSE) logs -f --tail=100 nuxt

shell: shell-app ## alias for shell-app

shell-app: ## Open shell in app container
	$(COMPOSE) exec app sh

shell-nuxt: ## Open shell in nuxt container
	$(COMPOSE) exec nuxt sh

shell-db: ## Open psql in db container
	$(COMPOSE) exec db psql -U $(DB_USERNAME) -d $(DB_DATABASE)

psql: ## psql into database (usage: make psql QUERY="SELECT 1")
	@if [ -z "$(QUERY)" ]; then \
		$(COMPOSE) exec -T db psql -U $(DB_USERNAME) -d $(DB_DATABASE); \
	else \
		$(COMPOSE) exec -T db psql -U $(DB_USERNAME) -d $(DB_DATABASE) -c "$(QUERY)"; \
	fi

redis-cli: ## Open redis-cli
	$(COMPOSE) exec redis redis-cli

artisan: ## Run artisan (usage: make artisan CMD="migrate")
	@if [ -z "$(CMD)" ]; then \
		$(COMPOSE) exec app php artisan; \
	else \
		$(COMPOSE) exec app php artisan $(CMD); \
	fi

composer: ## Run composer (usage: make composer CMD="require package")
	@if [ -z "$(CMD)" ]; then \
		$(COMPOSE) exec app composer; \
	else \
		$(COMPOSE) exec app composer $(CMD); \
	fi

pnpm: ## Run pnpm in nuxt (usage: make pnpm CMD="add axios")
	@if [ -z "$(CMD)" ]; then \
		$(COMPOSE) exec nuxt pnpm; \
	else \
		$(COMPOSE) exec nuxt pnpm $(CMD); \
	fi

test: test-backend ## Run backend tests

test-backend: ## Run phpunit
	$(COMPOSE) exec app php artisan test

test-unit: ## Run unit tests only
	$(COMPOSE) exec app php artisan test --testsuite=Unit

test-feature: ## Run feature tests only
	$(COMPOSE) exec app php artisan test --testsuite=Feature

key: ## Generate APP_KEY
	$(COMPOSE) exec app php artisan key:generate

fresh: ## Drop all tables and re-run migrations
	$(COMPOSE) exec app php artisan migrate:fresh

migrate: ## Run migrations
	$(COMPOSE) exec app php artisan migrate

seed: ## Seed database
	$(COMPOSE) exec app php artisan db:seed

seed-demo: ## Seed demo data
	$(COMPOSE) exec app php artisan db:seed --class=DemoSeeder

queue: ## Start queue worker
	$(COMPOSE) exec app php artisan queue:work --tries=3

horizon: ## Start horizon (if installed)
	$(COMPOSE) exec app php artisan horizon

tinker: ## Open tinker
	$(COMPOSE) exec app php artisan tinker

routes: ## List API routes
	$(COMPOSE) exec app php artisan route:list --path=api

install: ## Full install: build + up + composer install + key + migrate
	$(COMPOSE) build
	$(COMPOSE) up -d
	@sleep 8
	$(MAKE) composer-install
	$(MAKE) key
	$(MAKE) migrate
	@echo "✓ Install complete. API: http://localhost:$${WEB_PORT:-8000}"

composer-install:
	$(COMPOSE) exec app composer install --optimize-autoloader

init: ## Initialize SSL (placeholder for Phase 1)
	@echo "SSL initialization will be added in a later step"

ssl-init: ## Request SSL certificate via certbot (usage: make ssl-init DOMAINS="example.com" EMAIL="admin@example.com")
	@if [ -z "$(DOMAINS)" ]; then \
		echo "Error: DOMAINS is required. Usage: make ssl-init DOMAINS=\"example.com api.example.com\" EMAIL=\"admin@example.com\""; \
		exit 1; \
	fi
	@if [ -z "$(EMAIL)" ]; then \
		echo "Error: EMAIL is required. Usage: make ssl-init DOMAINS=\"example.com\" EMAIL=\"admin@example.com\""; \
		exit 1; \
	fi
	docker run --rm \
		-v $(PWD)/data/certbot/conf:/etc/letsencrypt \
		-v $(PWD)/data/certbot/www:/var/www/certbot \
		certbot/certbot certonly --webroot \
		--webroot-path=/var/www/certbot \
		--email $(EMAIL) \
		--agree-tos --no-eff-email \
		-d $(DOMAINS)
	cp $(PWD)/data/certbot/conf/live/$$(echo $(DOMAINS) | cut -d' ' -f1)/fullchain.pem $(PWD)/nginx/ssl/
	cp $(PWD)/data/certbot/conf/live/$$(echo $(DOMAINS) | cut -d' ' -f1)/privkey.pem $(PWD)/nginx/ssl/
	cp $(PWD)/data/certbot/conf/live/$$(echo $(DOMAINS) | cut -d' ' -f1)/chain.pem $(PWD)/nginx/ssl/
	@echo "✓ SSL certificates installed. Run: make up-prod"

ssl-renew: ## Renew SSL certificates
	docker run --rm \
		-v $(PWD)/data/certbot/conf:/etc/letsencrypt \
		-v $(PWD)/data/certbot/www:/var/www/certbot \
		certbot/certbot renew

ssl-test: ## Test SSL renewal (dry run)
	docker run --rm \
		-v $(PWD)/data/certbot/conf:/etc/letsencrypt \
		-v $(PWD)/data/certbot/www:/var/www/certbot \
		certbot/certbot renew --dry-run

up-prod: ## Start production stack with SSL
	$(COMPOSE) -f docker-compose.yml -f docker-compose.prod.yml --profile dev up -d
	@echo "Production stack running. HTTPS: https://$${WEB_PORT:-443}"

down-prod: ## Stop production stack
	$(COMPOSE) -f docker-compose.yml -f docker-compose.prod.yml down

health: ## Check health of all services
	@echo "Service status:"
	@$(COMPOSE) ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"
	@echo ""
	@echo "Database:"
	@$(COMPOSE) exec -T db pg_isready -U $(DB_USERNAME) 2>&1 || echo "DB not ready"
	@echo "Redis:"
	@$(COMPOSE) exec -T redis redis-cli ping 2>&1 || echo "Redis not ready"
	@echo "App:"
	@curl -sf -o /dev/null -w "HTTP %{http_code}\n" http://localhost:$${WEB_PORT:-8000}/up 2>&1 || echo "App not ready"
