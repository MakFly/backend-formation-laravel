# Training Platform Backend API

> A generic, reusable RESTful API for training/formation management platforms built with Laravel 12.

## Overview

This is a backend API for managing training platforms, supporting:
- Course/Formation management
- Student enrollment and progress tracking
- Payment processing via Stripe
- Certificate generation
- Admin dashboard with analytics
- Content management (modules, lessons, resources)

## Tech Stack

- **PHP**: 8.4+
- **Framework**: Laravel 12
- **Database**: SQLite (dev) / PostgreSQL (prod)
- **Testing**: PHPUnit 11
- **Authentication**: JWT (tymon/jwt-auth)
- **Payments**: Stripe API
- **API Response**: JSON:API 1.0 compliant

## Requirements

- PHP 8.4 or higher
- Composer
- SQLite or PostgreSQL
- Stripe account (for payments)

## Installation

```bash
# Clone the repository
git clone <repository-url>
cd backend-training-platform

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Generate JWT secret
php artisan jwt:secret

# Run migrations
php artisan migrate

# Link storage for file uploads
php artisan storage:link

# Start development server
php artisan serve
```

## Environment Configuration

Update your `.env` file with:

```env
# Database
DB_CONNECTION=sqlite
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=training_platform
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# Stripe
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_SUCCESS_URL=http://localhost:8000/api/v1/payments/success
STRIPE_CANCEL_URL=http://localhost:8000/api/v1/payments/cancel

# JWT
JWT_SECRET=<generate with: php artisan jwt:secret>
JWT_ALGORITHM=HS256
JWT_TTL=1440
JWT_REFRESH_TTL=20160

# Storage
FILESYSTEM_DISK=public

# API Rate Limiting
API_RATE_LIMIT=60
```

## API Versioning

The API uses URL-based versioning. All endpoints are prefixed with `/api/v1/`.

### Version Information

Get current API version information:

```bash
GET /api/version
```

Response:
```json
{
  "api": "Training Platform API",
  "version": "1.0.0",
  "latest_version": "1.0.0",
  "min_supported_version": "1.0.0",
  "endpoints": {
    "current": "http://localhost:8000/api/v1",
    "documentation": "http://localhost:8000/api/documentation"
  }
}
```

### Health Check

Monitor API and service health:

```bash
GET /api/health
```

Response:
```json
{
  "status": "ok",
  "timestamp": "2026-01-04T12:00:00Z",
  "services": {
    "database": "up",
    "cache": "up"
  }
}
```

## API Endpoints

### Base URL
```
http://localhost:8000/api/v1
```

### Authentication
All protected endpoints require a JWT token in the Authorization header:
```
Authorization: Bearer <token>
```

### Modules

#### Formations
```
GET    /api/v1/formations              - List formations (public)
POST   /api/v1/formations              - Create formation (admin)
GET    /api/v1/formations/{id}         - Get formation details
PATCH  /api/v1/formations/{id}         - Update formation (admin)
DELETE /api/v1/formations/{id}         - Delete formation (admin)
```

#### Modules (nested under formations)
```
GET    /api/v1/formations/{formationId}/modules        - List modules
POST   /api/v1/formations/{formationId}/modules        - Create module
GET    /api/v1/formations/{formationId}/modules/{id}   - Get module details
PATCH  /api/v1/formations/{formationId}/modules/{id}   - Update module
DELETE /api/v1/formations/{formationId}/modules/{id}   - Delete module
POST   /api/v1/formations/{formationId}/modules/reorder - Reorder modules
POST   /api/v1/formations/{formationId}/modules/{id}/publish - Publish module
POST   /api/v1/formations/{formationId}/modules/{id}/unpublish - Unpublish module
GET    /api/v1/formations/{formationId}/modules/{id}/lessons - Get module lessons
```

#### Lessons
```
GET    /api/v1/lessons                    - List lessons (by module or formation)
POST   /api/v1/lessons                    - Create lesson
GET    /api/v1/lessons/{id}               - Get lesson details
PATCH  /api/v1/lessons/{id}               - Update lesson
DELETE /api/v1/lessons/{id}               - Delete lesson
POST   /api/v1/lessons/reorder            - Reorder lessons
POST   /api/v1/lessons/{id}/publish       - Publish lesson
POST   /api/v1/lessons/{id}/unpublish     - Unpublish lesson
POST   /api/v1/lessons/{id}/content       - Upload lesson content
POST   /api/v1/lessons/{id}/thumbnail     - Upload lesson thumbnail
GET    /api/v1/lessons/{id}/resources     - Get lesson resources
```

#### Lesson Resources (attachments)
```
GET    /api/v1/lessons/{lessonId}/resources   - List resources
POST   /api/v1/lessons/{lessonId}/resources   - Upload resource
GET    /api/v1/lessons/{lessonId}/resources/{id} - Get resource
PATCH  /api/v1/lessons/{lessonId}/resources/{id} - Update resource
DELETE /api/v1/lessons/{lessonId}/resources/{id} - Delete resource
POST   /api/v1/lessons/{lessonId}/resources/reorder - Reorder resources
```

### Enrollment & Progress

```
GET    /api/v1/enrollments                - List enrollments
POST   /api/v1/enrollments                - Create enrollment
GET    /api/v1/enrollments/{id}           - Get enrollment details
POST   /api/v1/enrollments/{id}/validate  - Validate enrollment
POST   /api/v1/enrollments/{id}/cancel    - Cancel enrollment

GET    /api/v1/enrollments/{enrollmentId}/lessons/{lessonId}/access - Check lesson access

GET    /api/v1/progress                   - List progress
GET    /api/v1/progress/{id}              - Get progress details
GET    /api/v1/progress/enrollments/{enrollmentId} - Get enrollment progress
POST   /api/v1/progress/enrollments/{enrollmentId}/lessons/{lessonId}/start - Start lesson
PATCH  /api/v1/progress/enrollments/{enrollmentId}/lessons/{lessonId} - Update progress
POST   /api/v1/progress/enrollments/{enrollmentId}/lessons/{lessonId}/complete - Complete lesson
```

### Certificates

```
GET    /api/v1/certificates               - List certificates
GET    /api/v1/certificates/{id}          - Get certificate details
POST   /api/v1/certificates/enrollments/{enrollmentId}/generate - Generate certificate
POST   /api/v1/certificates/{id}/revoke   - Revoke certificate
POST   /api/v1/certificates/{id}/regenerate - Regenerate certificate
GET    /api/v1/certificates/{id}/download - Download certificate PDF

# Public verification (no auth required)
GET    /api/v1/certificates/verify/{code} - Verify certificate by code
GET    /api/v1/certificates/verify/number/{number} - Verify by number
```

### Payments

```
GET    /api/v1/payments                   - List payments
POST   /api/v1/payments                   - Create payment (Stripe checkout)
GET    /api/v1/payments/{id}              - Get payment details
POST   /api/v1/payments/{id}/refund       - Refund payment

# Stripe redirect handlers (no auth required)
GET    /api/v1/payments/success           - Successful payment redirect
GET    /api/v1/payments/cancel            - Cancelled payment redirect

# Webhooks (no auth required, signature verified)
POST   /api/v1/webhooks/stripe            - Stripe webhook handler
```

### Admin Dashboard

```
GET    /api/v1/admin/dashboard                - Dashboard statistics
GET    /api/v1/admin/dashboard/revenue        - Revenue analytics
GET    /api/v1/admin/dashboard/popular-formations - Popular formations

GET    /api/v1/admin/customers                - List customers
POST   /api/v1/admin/customers                - Create customer
GET    /api/v1/admin/customers/{id}           - Get customer details
PATCH  /api/v1/admin/customers/{id}           - Update customer
DELETE /api/v1/admin/customers/{id}           - Delete customer
GET    /api/v1/admin/customers/{id}/enrollments - Customer enrollments
GET    /api/v1/admin/customers/{id}/payments  - Customer payments
GET    /api/v1/admin/customers/{id}/stats     - Customer statistics

GET    /api/v1/admin/formations               - List formations (admin)
POST   /api/v1/admin/formations               - Create formation
GET    /api/v1/admin/formations/{id}          - Get formation details (admin)
PATCH  /api/v1/admin/formations/{id}          - Update formation
DELETE /api/v1/admin/formations/{id}          - Delete formation
POST   /api/v1/admin/formations/{id}/duplicate - Duplicate formation
POST   /api/v1/admin/formations/{id}/publish  - Publish formation
POST   /api/v1/admin/formations/{id}/unpublish - Unpublish formation
GET    /api/v1/admin/formations/{id}/stats    - Formation statistics

GET    /api/v1/admin/orders                   - List orders
GET    /api/v1/admin/orders/stats             - Order statistics
GET    /api/v1/admin/orders/{id}              - Get order details
POST   /api/v1/admin/orders/{id}/refund       - Refund order
```

## Response Format

All API responses follow a standard JSON:API compatible format.

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {
    "id": "uuid",
    "attribute": "value"
  }
}
```

### Created Response (201)
```json
{
  "success": true,
  "message": "Resource created successfully",
  "data": {
    "id": "uuid",
    "attribute": "value"
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

### Paginated Response
```json
{
  "success": true,
  "data": {
    "data": [...],
    "meta": {
      "current_page": 1,
      "per_page": 30,
      "total": 100,
      "last_page": 4
    }
  }
}
```

## HTTP Status Codes

| Code | Status | Usage |
|------|--------|-------|
| 200 | OK | Successful GET, PATCH |
| 201 | Created | Successful POST |
| 204 | No Content | Successful DELETE |
| 400 | Bad Request | Invalid request |
| 401 | Unauthorized | Not authenticated |
| 403 | Forbidden | Authenticated but not authorized |
| 404 | Not Found | Resource not found |
| 422 | Unprocessable Entity | Validation error |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error |

## Rate Limiting

The API implements rate limiting. Default limits are shown in response headers:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1609459200
```

Configure rate limiting in `.env`:
```env
API_RATE_LIMIT=60  # requests per minute
```

## Testing

```bash
# Run all tests
php artisan test

# Run unit tests only
php artisan test --testsuite=Unit

# Run specific test
php artisan test --filter test_name

# Run with coverage
php artisan test --coverage

# Run in watch mode
pest --watch
```

### Test Structure

```
tests/
├── Unit/
│   ├── Actions/         # Action class tests
│   ├── Controllers/     # Controller tests
│   └── Support/         # Support class tests
└── Feature/             # Feature tests
```

## Code Quality

```bash
# Format code with Laravel Pint
./vendor/bin/pint

# Run static analysis with Larastan
./vendor/bin/phpstan analyse

# Run both
composer run check
```

### Code Quality Tools

- **Laravel Pint** - Code formatting (PSR-12)
- **Larastan (PHPStan)** - Static analysis
- **Pest PHP** - Testing framework
- **PHP 8.4+** - Strict types enabled

## CI/CD

The project includes GitHub Actions workflows for:

### 1. Quality & Security (`.github/workflows/quality.yml`)

Runs on every pull request:
- Code formatting check (Pint)
- Static analysis (PHPStan)
- Security audit (composer audit)
- Full test suite

### 2. Build (`.github/workflows/build.yml`)

Runs on every push to main:
- Dependency installation
- Database migrations
- All tests execution
- Artifacts generation

### 3. Deploy (`.github/workflows/deploy.yml`)

**Disabled by default** - Manual trigger required:
- Stage deployment (staging)
- Production deployment (production)

To enable deployment, uncomment the workflow trigger or use manual dispatch from GitHub Actions UI.

## Project Structure

```
app/
├── Actions/           # Business logic (CQRS-lite)
│   ├── Certificate/
│   ├── Content/
│   ├── Enrollment/
│   ├── Formation/
│   ├── LessonProgress/
│   ├── LessonResource/
│   ├── Module/
│   ├── Payment/
│   └── Progress/
├── Enums/             # PHP 8.1 enums for constants
│   ├── CertificateStatus.php
│   ├── EnrollmentStatus.php
│   ├── HttpStatus.php
│   ├── LessonStatus.php
│   ├── PaymentStatus.php
│   ├── PaymentType.php
│   └── PricingTier.php
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── Admin/          # Admin controllers
│   │       ├── ModuleController.php
│   │       ├── LessonController.php
│   │       └── ...
│   ├── Resources/      # API resources
│   ├── Requests/       # Form request validation
│   └── Middleware/
├── Models/            # Eloquent models with UUIDs
├── Support/           # Custom support classes
│   ├── Http/
│   │   └── ApiResponseBuilder.php
│   ├── Stripe/
│   │   └── StripePaymentService.php
│   └── Certificate/
│       └── CertificatePdfService.php
└── Traits/
    ├── HasUuids.php
    └── ...

routes/
├── api.php           # API versioning wrapper
└── api/
    └── v1.php        # v1 endpoints

tests/
├── Unit/
│   ├── Actions/
│   ├── Controllers/
│   └── Support/
└── Feature/
```

## Architecture Principles

1. **Action-Based Architecture**: Business logic in `app/Actions/`
2. **Strict Types**: All files use `declare(strict_types=1)`
3. **Enums for Constants**: Status codes, types, modes
4. **UUID Primary Keys**: All models use UUIDs
5. **FormRequest Validation**: Input validation via FormRequest classes
6. **JsonResource**: Data transformation via Resource classes
7. **ApiResponseBuilder**: Standardized API responses

## Stripe Webhooks

The API handles the following Stripe webhook events:

- `payment_intent.succeeded` - Payment completed
- `payment_intent.payment_failed` - Payment failed
- `charge.refunded` - Payment refunded
- `checkout.session.completed` - Checkout session completed

Configure your Stripe webhook endpoint:
```
https://your-domain.com/api/v1/webhooks/stripe
```

## Deployment

### Environment Variables for Production

See `.env.production.example` for the complete production configuration template.

Key production settings:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database (PostgreSQL recommended)
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_PORT=5432
DB_DATABASE=production_db
DB_USERNAME=production_user
DB_PASSWORD=secure_password

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Stripe (Production keys)
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

# JWT
JWT_ALGORITHM=HS256
JWT_SECRET=<generate strong secret>
```

### Deployment Steps

Use the automated deployment script:
```bash
chmod +x deploy.sh
./deploy.sh
```

Or manually:
```bash
# Install dependencies
composer install --optimize-autoloader --no-dev --no-interaction

# Run migrations
php artisan migrate --force --no-interaction

# Clear and cache configs
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Link storage
php artisan storage:link

# Optimize application
php artisan optimize

# Set permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Post-Deployment Checklist

- [ ] Verify API health: `curl https://your-domain.com/api/health`
- [ ] Check API version: `curl https://your-domain.com/api/version`
- [ ] Test authentication endpoint
- [ ] Verify Stripe webhook connectivity
- [ ] Check logs: `tail -f storage/logs/laravel.log`
- [ ] Monitor queue workers (if applicable)

## OpenAPI Documentation

OpenAPI 3.1 specification is available in `swagger.yaml`. You can:

- View it directly in any OpenAPI/Swagger UI
- Generate client SDKs using `swagger-codegen`
- Import into tools like Postman or Insomnia

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Support

For issues, questions, or contributions, please open an issue on the repository.
