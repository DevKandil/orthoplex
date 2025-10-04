# Orthoplex - Laravel 12 Multi-Tenant SaaS Platform

A comprehensive Laravel 12 multi-tenant SaaS platform showcasing enterprise-grade authentication, RBAC, user lifecycle management, and production-ready architecture.

![Laravel](https://img.shields.io/badge/Laravel-12.0-red.svg)
![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange.svg)
![Redis](https://img.shields.io/badge/Redis-Alpine-red.svg)
![Docker](https://img.shields.io/badge/Docker-Ready-blue.svg)

## 🚀 Features

### Core Authentication & Security
- **JWT Authentication** with refresh tokens
- **Email Verification** mandatory before first login
- **2FA (TOTP)** with backup codes
- **Magic Link** passwordless authentication
- **Rate Limiting** with brute-force protection
- **Optimistic Locking** for concurrency control
- **Audit Logging** for admin actions

### Multi-Tenancy & RBAC
- **Database-per-tenant** architecture using `stancl/tenancy`
- **Role-Based Access Control** (Owner, Admin, Member, Auditor)
- **Permission System** with fine-grained controls
- **Organization Invitations** with email tokens
- **Cross-tenant isolation** and security

### User Management & Compliance
- **Full CRUD operations** with soft deletes
- **GDPR Compliance** (export/delete requests)
- **User Analytics** with login tracking
- **Profile Management** with timezones and localization
- **Batch Operations** with queue processing

### API & Integration
- **RESTful API** with OpenAPI/Swagger documentation
- **Cursor-based Pagination** for performance
- **RSQL Filtering** with server-side validation
- **Sparse Fieldsets** and includes
- **Webhook System** with HMAC signing and retries
- **API Keys** with scoped permissions

### Developer Experience
- **Docker Development Environment** with single-command setup
- **Comprehensive Test Suite** (Unit, Feature, Integration)
- **Code Quality Tools** (Laravel Pint, PHPStan)
- **Queue Management** with Laravel Horizon
- **Development Tools** (Telescope, Mailhog)

## 📋 Requirements

- Docker & Docker Compose
- Make (optional, for convenience commands)

## 🏗️ Quick Start

### 1. Clone Repository

```bash
git clone <repository-url> orthoplex
cd orthoplex
```

### 2. Setup Application

```bash
make setup
```

This will:
- Copy environment files
- Install Composer and NPM dependencies
- Generate application key and JWT secret
- Configure /etc/hosts entries

### 3. Start Development Environment

```bash
make up
```

### 4. Run Database Migrations

```bash
make migrate
```

### 5. Access the Application

- **Backend**: http://orthoplex.test
- **API Documentation**: http://orthoplex.test/api/documentation
- **Mailhog**: http://orthoplex.test:8025
- **Telescope**: http://orthoplex.test:8080
- **Horizon**: http://orthoplex.test/horizon
- **Example Tenant**: http://app.orthoplex.test

## 🛠️ Development Commands

```bash
# Start environment
make up

# Stop environment
make down

# Setup application
make setup

# Run database migrations
make migrate

# View logs
make logs

# Run tests
make test

# Generate API docs
make docs
```

## 🏗️ Architecture Overview

### Multi-Tenancy Strategy

Orthoplex uses a **database-per-tenant** approach:

- **Central Database**: Stores tenants, domains, and global configuration
- **Tenant Databases**: Each tenant gets isolated database with full schema
- **Domain Routing**: Automatic tenant resolution via subdomain/domain
- **Data Isolation**: Complete separation between tenant data

### Authentication Flow

1. **Registration**: User creates account with email verification
2. **Login**: JWT token issued with tenant context
3. **2FA Setup**: Optional TOTP with backup codes
4. **Magic Links**: Passwordless authentication option
5. **Rate Limiting**: Protection against brute force attacks

### Permission System

```
Roles:
├── owner (full access)
├── admin (user management, analytics)
├── member (basic access)
└── auditor (read-only access)

Permissions:
├── users.read
├── users.update
├── users.delete
├── users.invite
└── analytics.read
```

## 🔧 Configuration

### Environment Variables

Key configuration options in `.env`:

```env
# Application
APP_URL=http://orthoplex.test
APP_DOMAIN=orthoplex.test

# JWT
JWT_TTL=60
JWT_REFRESH_TTL=20160

# Rate Limiting
RATE_LIMIT_LOGIN_ATTEMPTS=5
RATE_LIMIT_LOGIN_DECAY_MINUTES=15

# 2FA
GOOGLE_2FA_ENCRYPT=true

# Webhooks
WEBHOOK_SIGNING_SECRET=your-secret-key
WEBHOOK_MAX_RETRIES=3

# GDPR
GDPR_EXPORT_TTL_HOURS=24
```

### Tenant Configuration

Tenants are automatically configured with:
- Isolated database with full schema migration
- Role and permission seeding
- Default admin user setup
- Webhook endpoint configuration

## 📚 API Documentation

### Authentication Endpoints

```http
POST /api/auth/register
POST /api/auth/login
POST /api/auth/logout
POST /api/auth/refresh
GET  /api/auth/me
```

### User Management

```http
GET    /api/users                 # List users (paginated)
POST   /api/users                 # Create user
GET    /api/users/{id}            # Get user
PUT    /api/users/{id}            # Update user
DELETE /api/users/{id}            # Soft delete user
POST   /api/users/{id}/restore    # Restore user
```

### Analytics

```http
GET /api/users/top-logins?window=7d
GET /api/users/inactive?window=week
GET /api/analytics/login-stats
```

### Full API documentation available at `/api/documentation` when running.

## 🧪 Testing

### Running Tests

```bash
# All tests
make test

# Specific test suite
docker-compose exec backend php artisan test --testsuite=Feature

# With coverage
docker-compose exec backend php artisan test --coverage
```

### Test Structure

- **Unit Tests**: Model logic, services, utilities
- **Feature Tests**: API endpoints, authentication flows
- **Integration Tests**: Multi-tenant scenarios, webhooks

## 📦 Package Choices & Rationale

### Core Dependencies

- **`tymon/jwt-auth`**: Mature JWT implementation with Laravel integration
- **`stancl/tenancy`**: Most comprehensive multi-tenancy package for Laravel
- **`spatie/laravel-permission`**: Industry standard for RBAC implementation
- **`pragmarx/google2fa-laravel`**: Reliable 2FA with good documentation
- **`laravel/horizon`**: Official Redis queue monitoring and management
- **`spatie/laravel-query-builder`**: Powerful API filtering and sorting
- **`darkaonline/l5-swagger`**: OpenAPI documentation generation

### Architecture Decisions

1. **Database-per-tenant**: Ensures complete data isolation and scalability
2. **JWT over Sessions**: Better for API-first, multi-domain architecture
3. **Queue-based Processing**: Handles heavy operations (exports, webhooks) asynchronously
4. **Event-driven Webhooks**: Ensures reliable delivery with retry mechanisms
5. **Cursor Pagination**: Better performance for large datasets

## 🔒 Security Features

- **Input Validation**: All requests validated with Form Request classes
- **Rate Limiting**: Multiple layers (IP, user, endpoint)
- **CORS Configuration**: Properly configured for multi-domain setup
- **SQL Injection Protection**: Eloquent ORM with parameterized queries
- **XSS Prevention**: JSON-only API responses
- **CSRF Protection**: Built-in Laravel middleware
- **Audit Logging**: Track all sensitive operations
- **Encrypted Secrets**: 2FA secrets and recovery codes encrypted at rest

## 📈 Performance Considerations

- **Database Indexing**: Strategic indexes on frequently queried columns
- **Redis Caching**: Session storage and rate limiting
- **Queue Processing**: Async handling of heavy operations
- **Optimistic Locking**: Prevents race conditions in updates
- **Cursor Pagination**: Efficient pagination for large datasets
- **Eager Loading**: Prevent N+1 queries with proper relationships

## 🚀 Production Deployment

### Environment Setup

1. Configure production environment variables
2. Set up SSL certificates for multi-domain setup
3. Configure Redis for caching and queues
4. Set up queue workers with Supervisor
5. Configure log rotation and monitoring

### Scaling Considerations

- **Database Sharding**: Can distribute tenants across multiple DB servers
- **Cache Strategy**: Redis clustering for high availability
- **Queue Workers**: Scale horizontally based on load
- **CDN Integration**: For static assets and file uploads
- **Load Balancing**: Multiple application servers behind load balancer

## 📝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Run tests (`make test`)
4. Format code (`make lint`)
5. Commit changes (`git commit -m 'Add amazing feature'`)
6. Push to branch (`git push origin feature/amazing-feature`)
7. Open Pull Request

## 🤝 Support

- **Documentation**: Full API docs at `/api/documentation`
- **Issues**: Use GitHub Issues for bug reports
- **Discussions**: Use GitHub Discussions for questions

---

**Built with ❤️ using Laravel 12, showcasing modern PHP development practices and enterprise-grade architecture.**