# Laravel 12 Rules for Training Platform Backend

**Training Platform Backend** - Generic, reusable RESTful API for training management platforms

## Core Architecture Principles

### 1. Action-Based Architecture (CQRS-lite)

All business logic lives in `app/Actions/` as single-responsibility invokable classes:

```php
// ✅ CORRECT
class CreateCustomerAction
{
    public function __invoke(array $data): Customer
    {
        return Customer::create($data);
    }
}

// ❌ WRONG - Business logic in controller
class CustomerController extends Controller
{
    public function store(Request $request)
    {
        $customer = Customer::create($request->validated()); // Should use Action
    }
}
```

**Directory Structure:**
```
app/Actions/
├── Customer/       # Customer CRUD operations
├── Formation/      # Formation management
├── Enrollment/     # Customer-Formation enrollment
├── Progress/       # Lesson progress tracking
├── Certificate/    # Certificate generation
├── Payment/        # Payment processing
├── Scheduling/     # Session management
├── Notification/   # Email notifications
└── Content/        # Lesson content management
```

### 2. Strict Types Always Required

**Every .php file MUST start with:**
```php
<?php

declare(strict_types=1);

namespace App\...;
```

**Type hints required for:**
- Method parameters
- Return types
- Property types (promoted properties preferred)

```php
// ✅ CORRECT
public function __invoke(array $data): Customer
{
    return Customer::create($data);
}

// ❌ WRONG - No return type
public function __invoke(array $data)
{
    return Customer::create($data);
}
```

### 3. Use Enums for Constants

All status codes, types, and modes MUST use enums from `app/Enums/`:

```php
// ✅ CORRECT
use App\Enums\HttpStatus;
use App\Enums\SessionStatus;

return response()->json($data, HttpStatus::CREATED->value);
$session->status = SessionStatus::SCHEDULED;

// ❌ WRONG - Magic numbers/strings
return response()->json($data, 201);
$session->status = 'scheduled';
```

### 4. API Response Standardization

**ALWAYS use `ApiResponseBuilder` for API responses:**

```php
use App\Support\Http\ApiResponseBuilder;

// Success responses
ApiResponseBuilder::success($data, 'Resource retrieved');
ApiResponseBuilder::created($data, 'Resource created successfully');
ApiResponseBuilder::accepted($data, 'Request accepted');
ApiResponseBuilder::noContent();

// Error responses
ApiResponseBuilder::error('Something went wrong', 'ERROR_CODE', $errors);
ApiResponseBuilder::unauthorized('Authentication required');
ApiResponseBuilder::forbidden('You do not have permission');
ApiResponseBuilder::notFound('Resource not found');
ApiResponseBuilder::validationFailed($errors);
ApiResponseBuilder::conflict('Resource already exists');
ApiResponseBuilder::tooManyRequests(60); // retry after 60 seconds
ApiResponseBuilder::serverError('Internal server error');
```

### 5. Controllers Must Be Thin

Controllers should only:
- Validate input using FormRequest
- Delegate to Action classes
- Return formatted responses using ApiResponseBuilder

```php
// ✅ CORRECT
class CustomerController extends Controller
{
    public function store(CreateCustomerAction $action, StoreCustomerRequest $request)
    {
        $customer = $action($request->validated());
        return ApiResponseBuilder::created(CustomerResource::make($customer));
    }
}

// ❌ WRONG - Too much logic in controller
class CustomerController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([...]);
        $customer = Customer::create($validated);
        $customer->formations()->attach($request->formations);
        // ... more business logic
        return response()->json($customer, 201);
    }
}
```

### 6. FormRequest for Validation

**All API validation MUST use FormRequest classes:**

```php
// app/Http/Requests/StoreCustomerRequest.php
class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'unique:customers,email'],
            'first_name' => ['required', 'string', 'max:255'],
            // ...
        ];
    }
}

// Usage in controller
public function store(StoreCustomerRequest $request)
{
    $validated = $request->validated(); // Automatically validated
}
```

### 7. JsonResource for Data Transformation

**All API responses MUST use JsonResource:**

```php
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'type' => $this->type,
            'company' => $this->when($this->company, [
                'name' => $this->company?->name,
                'siret' => $this->company?->siret,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

// Usage
return CustomerResource::make($customer);
return CustomerResource::collection($customers);
```

### 8. Custom Exceptions

**All business logic exceptions MUST extend ApiException:**

```php
use App\Support\Exceptions\ApiException;
use App\Enums\HttpStatus;

class NotFoundException extends ApiException
{
    protected string $errorCode = 'RESOURCE_NOT_FOUND';
    protected HttpStatus|int $httpStatus = HttpStatus::NOT_FOUND;

    public static function forResource(string $resource, mixed $identifier = null): self
    {
        return new self("The {$resource} was not found");
    }
}

// Usage
throw NotFoundException::forResource('Customer', 123);
```

### 9. Contextual Logging

**All logging MUST use ContextualLogger with correlation ID:**

```php
use App\Support\Logging\ContextualLogger;

// In controllers/middleware
ContextualLogger::fromRequest($request)->info('Action completed', [
    'customer_id' => $customer->id,
]);

// In Actions
ContextualLogger::action('CreateCustomer')->info('Creating customer', [
    'email' => $data['email'],
]);

// In Jobs
ContextualLogger::job('ProcessPayment')->info('Payment processed');
```

### 10. Testing with Pest

**All tests MUST use Pest syntax:**

```php
// Unit test for Action
test('customer can be created', function () {
    $action = new CreateCustomerAction();
    $customer = $action([
        'email' => 'test@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
    ]);

    expect($customer->email)->toBe('test@example.com');
    expect($customer)->toBeInstanceOf(Customer::class);
});

// Feature test for API endpoint
test('customer can be created via api', function () {
    $data = [
        'email' => 'test@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
    ];

    $response = $this->postJson('/api/v1/customers', $data)
        ->assertStatus(HttpStatus::CREATED->value)
        ->assertJsonPath('data.email', 'test@example.com');
});
```

## Routing Rules

### API Versioning

**All API routes MUST be versioned:**

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('formations', FormationController::class);
    // ...
});
```

### Middleware Stack

**Routes MUST follow middleware order:**
1. `api` - Laravel API routing
2. `throttle:api` - Rate limiting
3. `auth:api` - JWT authentication (for protected routes)
4. `security.headers` - Security headers
5. `csp` - Content Security Policy

### Feature Flags

**Conditional features MUST use feature-flag middleware:**

```php
Route::middleware(['feature-flag:progress'])->group(function () {
    // Progress tracking endpoints
});

Route::middleware(['feature-flag:sessions'])->group(function () {
    // Session management endpoints
});
```

## Model Rules

### Type Casting

**All models MUST use proper casting:**

```php
class Customer extends Model
{
    protected $casts = [
        'email_verified_at' => 'datetime',
        'type' => CustomerType::class,  // Enum casting
        'meta' => 'array',               // JSON casting
    ];
}
```

### Relationships

**Use proper relationship types:**
- `hasMany` - One-to-many
- `belongsTo` - Many-to-one
- `belongsToMany` - Many-to-many
- `hasOne` - One-to-one

### Scopes

**Use scopes for common queries:**

```php
// Global scope
protected static function booted()
{
    static::addGlobalScope('active', fn ($query) => $query->where('active', true));
}

// Local scope
public function scopeActive($query)
{
    return $query->where('active', true);
}

// Usage
Customer::active()->get();
```

## Security Rules

1. **NEVER commit secrets** - Use `.env` files
2. **ALWAYS validate input** - Use FormRequest classes
3. **USE parameterized queries** - Eloquent ORM handles this
4. **SANITIZE user input** - Never trust `request()->all()`
5. **USE HTTPS in production** - Force redirect in production
6. **IMPLEMENT rate limiting** - Already configured per `API_RATE_LIMIT`
7. **USE CSP headers** - Already configured via middleware
8. **VALIDATE file uploads** - Check MIME types, file sizes
9. **USE feature flags** - Conditional feature enabling
10. **LOG security events** - Use ContextualLogger for auth/authorization

## Performance Rules

1. **Eager load relationships** - Use `with()` to avoid N+1 queries
2. **USE pagination** - For list endpoints
3. **CACHE static data** - Use Redis for caching
4. **USE queue workers** - For heavy operations (emails, payments)
5. **OPTIMIZE database queries** - Use indexes, avoid `select(*)`
6. **USE compression** - Enable gzip for API responses
7. **IMPLEMENT caching** - Use Redis for frequently accessed data
8. **USE database transactions** - For multi-step operations

## Code Style Rules

1. **USE PSR-12** - Laravel Pint formatter
2. **USE strict types** - Always `declare(strict_types=1)`
3. **USE short syntax** - `fn()`, `match()`, `?->`, `#[Attribute]`
4. **USE promoted properties** - In constructors
5. **USE readonly properties** - Where appropriate
6. **USE constructor property promotion** - In PHP 8.0+
7. **USE named arguments** - For better code readability
8. **WRITE PHPDoc** - For all public methods
9. **KEEP classes small** - Max 300 lines
10. **KEEP methods short** - Max 20 lines

## Testing Rules

1. **WRITE tests for all Actions** - Unit tests
2. **WRITE tests for all API endpoints** - Feature tests
3. **USE Pest syntax** - `test()` or `it()`
4. **USE factories** - Don't create data manually
5. **MOCK external services** - Stripe, email, etc.
6. **TEST edge cases** - Not just happy path
7. **TEST error handling** - 4xx, 5xx responses
8. **USE data providers** - For multiple test cases
9. **TEST middleware** - Auth, rate limiting, etc.
10. **MAINTAIN test coverage** - Above 80%

## Forbidden Patterns

❌ **NEVER do these things:**

1. Direct `DB::` calls in Controllers/Actions
2. Business logic in Controllers
3. Magic numbers/strings (use Enums)
4. Direct `response()->json()` in Controllers (use ApiResponseBuilder)
5. Missing strict types
6. Missing return types
7. Public properties in Models
8. Raw SQL in queries (use Eloquent)
9. Hardcoded configuration (use config/.env)
10. Silent failures - always throw/log errors

## Quick Reference

### Creating a New Feature

1. **Create Action** - `app/Actions/Domain/CreateXxxAction.php`
2. **Create FormRequest** - `app/Http/Requests/StoreXxxRequest.php`
3. **Create Resource** - `app/Http/Resources/XxxResource.php`
4. **Add Routes** - `routes/api.php`
5. **Create Controller** - `app/Http/Controllers/Api/XxxController.php`
6. **Write Tests** - `tests/Unit/Actions/` and `tests/Feature/Api/`
7. **Add Feature Flag** - If conditional
8. **Update Documentation** - README/API docs

### File Naming

- Actions: `CreateCustomerAction.php`
- Requests: `StoreCustomerRequest.php`, `UpdateCustomerRequest.php`
- Resources: `CustomerResource.php`
- Controllers: `CustomerController.php`
- Tests: `CustomerTest.php`, `CreateCustomerActionTest.php`
- Enums: `CustomerType.php`, `HttpStatus.php`

### Command Reference

```bash
# Development
composer run dev          # Full stack (server + queue + logs)
php artisan serve         # HTTP server
php artisan queue:listen   # Queue worker
php artisan pail          # Log viewer

# Testing
composer test            # Run Pest tests
pest --filter            # Run specific test
pest --watch            # Watch mode
pest --coverage         # Coverage report

# Code Quality
composer analyse        # Larastan (PHPStan)
./vendor/bin/pint        # Laravel Pint formatter

# Cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```
