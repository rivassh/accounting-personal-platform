# Persian RTL Personal Finance App

Production-ready Docker infrastructure for a multi-currency personal finance application with full RTL (Right-to-Left) Persian support.

## Tech Stack

| Layer | Technology | Purpose |
|-------|-----------|---------|
| Backend | Laravel 11 | API, Business Logic |
| Frontend | Nuxt 3 | SSR + SPA |
| Database | PostgreSQL 15 | Primary data store |
| Cache | Redis 7 | Sessions, Queue, Cache |
| Auth | Laravel Sanctum | SPA + API tokens |
| Proxy | Nginx 1.25 | Reverse proxy + SSL |

## Quick Start

```bash
cp .env.example .env
make install
```

Or step-by-step:

```bash
make build     # Build Docker images
make up        # Start all services
make key       # Generate APP_KEY
make migrate   # Run database migrations
make seed      # Seed initial data
```

## Services

| Service | URL | Description |
|---------|-----|-------------|
| Frontend | http://localhost:3000 | Nuxt 3 dev server |
| API | http://localhost:8000 | Laravel API |
| API Docs | http://localhost:8000/api | Route list |

## Available Make Commands

```bash
make up          # Start all services
make down        # Stop all services
make build       # Build images
make rebuild     # Rebuild without cache
make restart     # Restart all services

make logs        # Tail all logs
make logs-app    # Tail PHP-FPM logs
make logs-web    # Tail Nginx logs
make logs-db     # Tail PostgreSQL logs
make logs-nuxt   # Tail Nuxt logs

make shell       # Open shell in app container
make shell-nuxt  # Open shell in Nuxt container
make psql        # Open psql console
make redis-cli   # Open Redis CLI

make migrate     # Run migrations
make seed        # Seed database
make fresh       # Fresh migrations + seed
make artisan     # Run artisan command
make queue       # Start queue worker
make test        # Run tests

make health      # Check service health
make ps          # List running services
```

## Project Structure

```
.
├── backend/               # Laravel 11 API
│   ├── app/
│   │   ├── Http/         # Controllers, Middleware, Requests
│   │   ├── Models/       # Eloquent models
│   │   ├── Services/     # Business logic services
│   │   └── Providers/    # Service providers
│   ├── config/           # Laravel config files
│   ├── database/          # Migrations, Seeders, Factories
│   ├── routes/            # Route definitions
│   ├── tests/             # Unit & Feature tests
│   ├── storage/           # Logs, cache, sessions
│   └── bootstrap/         # App bootstrap
│
├── frontend/              # Nuxt 3 SPA
│   ├── pages/             # Route pages
│   ├── components/        # Vue components
│   ├── composables/       # Vue composables
│   ├── stores/            # Pinia stores
│   ├── layouts/           # Page layouts
│   ├── middleware/        # Route middleware
│   ├── i18n/              # Translations (Persian RTL)
│   └── types/             # TypeScript types
│
├── nginx/
│   └── default.conf       # Nginx configuration
│
├── docker-compose.yml     # Service orchestration
├── Makefile              # Operational commands
└── .env                  # Environment variables
```

## Money Handling

All monetary values are stored as **integers (minor units)** to avoid floating-point errors.

| Currency | Minor Unit | Example |
|----------|-----------|---------|
| IRR (Toman) | Rial (1/10) | 150,000 Toman = 1,500,000 Rial |
| USD | Cent | $50.00 = 5000 cents |

## RTL Support

- Full Right-to-Left layout using TailwindCSS `dir="rtl"`
- Jalali (Persian) calendar for dates
- Persian numerals (۰۱۲۳۴۵۶۷۸۹) display
- Persian translations for all UI text

## Environment Variables

```bash
APP_NAME="Persian Finance"
APP_ENV=local
APP_KEY=                 # Generate with: make key
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=finance
DB_USERNAME=finance
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PORT=6379

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

SANCTUM_STATEFUL_DOMAINS=localhost:3000

WEB_PORT=8000
NUXT_PORT=3000
NUXT_PUBLIC_API_BASE=http://localhost:8000/api
```

## Production Deployment

For production with SSL, update `.env`:

```bash
APP_ENV=production
APP_URL=https://your-domain.com
SANCTUM_STATEFUL_DOMAINS=your-domain.com

WEB_PORT=443
# Mount SSL certificates in docker-compose.yml
# volumes:
#   - ./nginx/certs:/etc/nginx/certs:ro
```

## Development

### Adding a new domain (multi-tenant)

1. Create `data/nginx/sub.<domain>.conf`
2. Add static files to `websites/<domain>/`
3. Run `make init DOMAINS="domain.com"`

### Running tests

```bash
make test-backend     # PHP unit tests
make test             # Alias for test-backend
```

## License

MIT
