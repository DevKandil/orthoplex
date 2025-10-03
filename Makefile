.PHONY: help up down setup fresh install logs shell test docs

# Default command when just running `make`
help:
	@echo "🏗️  Orthoplex Development Commands"
	@echo ""
	@echo "  make up         - Start all containers"
	@echo "  make down       - Stop all containers"
	@echo "  make setup      - Run application setup"
	@echo "  make install    - Install dependencies"
	@echo "  make logs       - View application logs"
	@echo "  make test       - Run tests"
	@echo "  make docs       - Generate API documentation"
	@echo ""

# Start development environment
up:
	@echo "🚀 Starting Orthoplex development environment..."
	docker-compose up -d
	@echo "✅ Environment started!"
	@echo "📱 Backend: http://orthoplex.test"
	@echo "📬 Mailhog: http://orthoplex.test:8025"

# Stop development environment
down:
	@echo "🛑 Stopping Orthoplex development environment..."
	docker-compose down

# Setup application
setup:
	@echo "🏗️  Setting up Orthoplex application..."
	docker-compose exec backend php artisan app:setup

# Install dependencies
install:
	@echo "📦 Installing dependencies..."
	docker-compose exec backend composer install
	docker-compose exec backend npm install

# View logs
logs:
	@echo "📝 Viewing application logs..."
	docker-compose logs -f backend

# Run tests
test:
	@echo "🧪 Running tests..."
	docker-compose exec backend php artisan test

# Generate API documentation
docs:
	@echo "📚 Generating API documentation..."
	docker-compose exec backend php artisan l5-swagger:generate