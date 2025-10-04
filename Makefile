.PHONY: help up down setup fresh install logs shell test docs

# Default command when just running `make`
help:
	@echo "🏗️  Orthoplex Development Commands"
	@echo ""
	@echo "  make setup      - Run application setup"
	@echo "  make up         - Start all containers"
	@echo "  make down       - Stop all containers"
	@echo "  make migrate    - Run database migrations"
	@echo "  make logs       - View application logs"
	@echo "  make test       - Run tests"
	@echo "  make docs       - Generate API documentation"
	@echo ""

# Start development environment
up:
	@echo "🚀 Starting Orthoplex development environment..."
	docker compose up -d
	@echo "⏳ Waiting for services to be fully up..."
	sleep 3
	@echo "Environment started ✅"
	@echo "Backend: http://orthoplex.test"
	@echo "Mailhog: http://orthoplex.test:8025"
	@echo "Telescope: http://orthoplex.test:8080"
	@echo "Swagger UI: http://orthoplex.test/api/documentation"
	@echo "Horizon: http://orthoplex.test/horizon"

# Stop development environment
down:
	@echo "🛑 Stopping Orthoplex development environment..."
	docker compose down

# Setup application
setup:
	@echo "🏗️  Setting up Orthoplex application..."
	cp .env.example .env
	cd backend && cp .env.example .env
	cd backend && composer install
	cd backend && npm install
	cd backend && php artisan key:generate
	cd backend && php artisan l5-swagger:generate
	cd backend && php artisan storage:link
	cd backend && php artisan jwt:secret

	@echo "📝 Updating /etc/hosts with required domains..."
	@if ! grep -q "orthoplex.test" /etc/hosts; then \
		echo "127.0.0.1 orthoplex.test" | sudo tee -a /etc/hosts > /dev/null; \
		echo "➕ Added orthoplex.test"; \
	else \
		echo "✔ orthoplex.test already exists"; \
	fi
	@if ! grep -q "app.orthoplex.test" /etc/hosts; then \
		echo "127.0.0.1 app.orthoplex.test" | sudo tee -a /etc/hosts > /dev/null; \
		echo "➕ Added app.orthoplex.test"; \
	else \
		echo "✔ app.orthoplex.test already exists"; \
	fi

	@echo "✅ Setup complete! You can now access the application at http://orthoplex.test"

# Run database migrations
migrate:
	@echo "🛠️  Running database migrations..."
	docker compose exec backend php artisan migrate --force --seed

# View logs
logs:
	@echo "📝 Viewing application logs..."
	docker compose logs -f backend

# Run tests
test:
	@echo "🧪 Running tests..."
	docker compose exec backend php artisan test

# Generate API documentation
docs:
	@echo "📚 Generating API documentation..."
	docker compose exec backend php artisan l5-swagger:generate