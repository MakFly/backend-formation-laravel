# API Platform Testing Patterns for Laravel

This document outlines the standard patterns for testing API Platform resources in Laravel using Pest PHP.

## Response Format

API Platform with JSON-LD format returns the following structure:

### Single Resource Response
```json
{
    "@context": "/api/v1/contexts/Customer",
    "@id": "/api/v1/customers/1",
    "@type": "Customer",
    "customerType": "individual",
    "firstname": "John",
    "lastname": "Doe",
    "email": "john@example.com",
    "createdAt": "2026-01-03 00:00:00",
    "updatedAt": "2026-01-03 00:00:00"
}
```

### Collection Response
```json
{
    "@context": "/api/v1/contexts/Customer",
    "@id": "/api/v1/customers",
    "@type": "Collection",
    "totalItems": 25,
    "member": [
        {
            "@id": "/api/v1/customers/1",
            "@type": "Customer",
            "customerType": "individual",
            "firstname": "John",
            "lastname": "Doe",
            "email": "john@example.com"
        }
    ]
}
```

**Important**: The response uses:
- `totalItems` (NOT `hydra:totalItems`) because `hydra_prefix` is disabled in config
- `member` (NOT `hydra:member`) for the same reason
- All property names are in **camelCase** (e.g., `customerType`, `zipCode`)

## Provider Pattern

All State Providers must return `ApiPlatform\Laravel\Eloquent\Paginator` for collection operations to ensure proper serialization:

```php
use ApiPlatform\Laravel\Eloquent\Paginator as ApiPlatformPaginator;

// In Provider
$laravelPaginator = $query->paginate($perPage, ['*'], 'page', $page);
return new ApiPlatformPaginator($laravelPaginator);
```

## Test Setup Pattern

```php
<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\deleteJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createModel = fn (array $overrides = []) => Model::factory()->create($overrides);
    $this->createUser = fn (array $overrides = []) => User::factory()->create($overrides);
    $this->actingAsUser = function (User $user) {
        $token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);

        return test()->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/ld+json',
        ]);
    };
});
```

## List Collection Tests

```php
test('returns paginated list of items', function () {
    Model::factory()->count(15)->create();

    $user = ($this->createUser)();

    $response = ($this->actingAsUser)($user)
        ->getJson('/api/v1/models')
        ->assertStatus(200);

    $json = $response->json();

    static::assertJsonContains([
        '@context' => '/api/v1/contexts/Model',
        '@type' => 'Collection',
        'totalItems' => 15,
    ], $json);

    expect($json['member'])->toHaveCount(15);
});

test('filters items by field', function () {
    Model::factory()->create(['field' => 'value1']);
    Model::factory()->create(['field' => 'value2']);
    Model::factory()->create(['field' => 'value3']);

    $user = ($this->createUser)();

    $response = ($this->actingAsUser)($user)
        ->getJson('/api/v1/models?field=value1')
        ->assertStatus(200);

    $json = $response->json();

    expect($json['member'])->toHaveCount(1);
    expect($json['member'][0]['field'])->toBe('value1');
});

test('paginates results with custom page size', function () {
    Model::factory()->count(25)->create();

    $user = ($this->createUser)();

    $response = ($this->actingAsUser)($user)
        ->getJson('/api/v1/models?page=1&itemsPerPage=5')
        ->assertStatus(200);

    $json = $response->json();

    expect($json['member'])->toHaveCount(5);
    expect($json['totalItems'])->toBe(25);
});
```

## Create Resource Tests

```php
test('creates resource with valid data', function () {
    $user = User::factory()->create();

    $data = [
        'field1' => 'value1',
        'field2' => 'value2',
    ];

    $response = ($this->actingAsUser)($user)
        ->postJson('/api/v1/models', $data)
        ->assertStatus(201);

    $json = $response->json();

    static::assertJsonContains([
        '@context' => '/api/v1/contexts/Model',
        '@type' => 'Model',
        'field1' => 'value1',
        'field2' => 'value2',
    ], $json);

    assertDatabaseHas('models', [
        'field1' => 'value1',
        'field2' => 'value2',
    ]);
});

test('returns validation error on missing required fields', function () {
    $user = User::factory()->create();

    ($this->actingAsUser)($user)
        ->postJson('/api/v1/models', [])
        ->assertStatus(422)
        ->assertJsonPath('violations', fn ($violations) => count($violations) >= 1)
        ->assertJsonPath('status', 422);
});

test('requires authentication', function () {
    postJson('/api/v1/models', ['field' => 'value'])
        ->assertStatus(401);
});
```

## Show Single Resource Tests

```php
test('shows resource details', function () {
    $model = Model::factory()->create(['field' => 'value']);

    $user = ($this->createUser)();

    $response = ($this->actingAsUser)($user)
        ->getJson('/api/v1/models/' . $model->id)
        ->assertStatus(200);

    $json = $response->json();

    static::assertJsonContains([
        '@context' => '/api/v1/contexts/Model',
        '@type' => 'Model',
        'field' => 'value',
    ], $json);
});

test('returns 404 for non-existent resource', function () {
    $user = ($this->createUser)();

    ($this->actingAsUser)($user)
        ->getJson('/api/v1/models/999')
        ->assertStatus(404);
});
```

## Update Resource Tests

```php
test('updates resource with valid data', function () {
    $model = Model::factory()->create(['field' => 'old_value']);
    $user = ($this->createUser)();

    $data = ['field' => 'new_value'};

    $response = ($this->actingAsUser)($user)
        ->patchJson('/api/v1/models/' . $model->id, $data)
        ->assertStatus(200);

    $json = $response->json();

    expect($json['field'])->toBe('new_value');
    assertDatabaseHas('models', [
        'id' => $model->id,
        'field' => 'new_value',
    ]);
});

test('partially updates resource', function () {
    $model = Model::factory()->create([
        'field1' => 'value1',
        'field2' => 'value2',
    ]);
    $user = ($this->createUser)();

    ($this->actingAsUser)($user)
        ->patchJson('/api/v1/models/' . $model->id, ['field1' => 'updated'])
        ->assertStatus(200);

    expect($model->fresh()->field1)->toBe('updated');
    expect($model->fresh()->field2)->toBe('value2');
});

test('returns 404 for non-existent resource', function () {
    $user = ($this->createUser)();

    ($this->actingAsUser)($user)
        ->patchJson('/api/v1/models/999', ['field' => 'value'])
        ->assertStatus(404);
});
```

## Delete Resource Tests

```php
test('deletes resource successfully', function () {
    $model = Model::factory()->create();
    $user = ($this->createUser)();

    ($this->actingAsUser)($user)
        ->deleteJson('/api/v1/models/' . $model->id)
        ->assertStatus(204);

    assertDatabaseMissing('models', ['id' => $model->id]);
});

test('returns 404 for non-existent resource', function () {
    $user = ($this->createUser)();

    ($this->actingAsUser)($user)
        ->deleteJson('/api/v1/models/999')
        ->assertStatus(404);
});

test('prevents deletion if resource has dependencies', function () {
    $model = Model::factory()->has(Dependency::factory())->create();
    $user = ($this->createUser)();

    ($this->actingAsUser)($user)
        ->deleteJson('/api/v1/models/' . $model->id)
        ->assertStatus(422); // or appropriate error code
});
```

## Validation Error Tests

```php
test('returns validation error on invalid email', function () {
    $user = User::factory()->create();

    $data = [
        'email' => 'not-an-email',
        // other required fields
    ];

    $response = ($this->actingAsUser)($user)
        ->postJson('/api/v1/models', $data)
        ->assertStatus(422)
        ->assertJsonPath('status', 422);

    $violations = $response->json('violations');
    $hasEmailError = collect($violations)->contains('propertyPath', 'email');
    expect($hasEmailError)->toBeTrue();
});

test('returns validation error on duplicate unique field', function () {
    Model::factory()->create(['email' => 'existing@example.com']);
    $user = User::factory()->create();

    $data = [
        'email' => 'existing@example.com',
        // other required fields
    ];

    $response = ($this->actingAsUser)($user)
        ->postJson('/api/v1/models', $data)
        ->assertStatus(422)
        ->assertJsonPath('status', 422);

    $violations = $response->json('violations');
    $hasEmailError = collect($violations)->contains('propertyPath', 'email');
    expect($hasEmailError)->toBeTrue();
});
```

## Provider Implementation Pattern

All Providers should follow this pattern:

```php
<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Laravel\Eloquent\Paginator as ApiPlatformPaginator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Models\Model;
use Illuminate\Http\Request;

final class ModelProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $method = $operation->getMethod();

        // Collection GET - return paginated results with optional filtering
        if ($method === 'GET' && !isset($uriVariables['id'])) {
            $query = Model::query();

            $perPage = 30;
            $page = 1;

            if (isset($context['request']) && $context['request'] instanceof Request) {
                $request = $context['request'];

                // Apply filters
                foreach (['field1', 'field2', 'field3'] as $field) {
                    if ($request->has($field)) {
                        $query->where($field, 'like', '%' . $request->input($field) . '%');
                    }
                }

                // Handle pagination
                $page = (int) $request->input('page', 1);
                $perPage = (int) $request->input('itemsPerPage', 30);
                $perPage = min($perPage, 100); // Max limit
            }

            $laravelPaginator = $query->paginate($perPage, ['*'], 'page', $page);
            return new ApiPlatformPaginator($laravelPaginator);
        }

        // Single item GET
        if (isset($uriVariables['id'])) {
            return Model::find($uriVariables['id']);
        }

        return null;
    }
}
```

## Key Takeaways

1. **Always wrap Laravel paginator with `ApiPlatformPaginator`** for collections
2. **Use camelCase property names** in tests (e.g., `customerType`, not `customer_type`)
3. **Use `totalItems` and `member`** for collection responses (not `hydra:totalItems`)
4. **Use `static::assertJsonContains()`** for validating JSON-LD structure
5. **Test authentication** for all endpoints
6. **Test validation errors** with proper `violations` array checks
7. **Use `RefreshDatabase` trait** to isolate tests
8. **Use factories** for creating test data
