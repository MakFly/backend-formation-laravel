# API Platform & JSON:API Rules for Training Platform

**Critical**: API Platform is Symfony-specific. For Laravel, we implement JSON:API standards using Laravel-native solutions and compatible packages.

## Stack Decision

### ✅ Our Chosen Stack
- **Laravel 12** API Resources (native)
- **JSON:API 1.0** specification compliance
- **OpenAPI 3.1** documentation
- **Pest PHP** for testing

### ❌ Not Recommended
- **API Platform** - Symfony-only, no Laravel integration
- **Hydra** - Overkill, not well-supported in Laravel

---

## JSON:API Response Format

All API endpoints MUST follow JSON:API 1.0 specification:

### Success Response Structure

```json
{
  "jsonapi": {
    "version": "1.0"
  },
  "data": {
    "type": "customers",
    "id": "123",
    "attributes": {
      "email": "john@example.com",
      "first_name": "John",
      "last_name": "Doe"
    },
    "relationships": {
      "formations": {
        "links": {
          "self": "/api/v1/customers/123/relationships/formations",
          "related": "/api/v1/customers/123/formations"
        },
        "data": [
          { "type": "formations", "id": "456" }
        ]
      }
    },
    "links": {
      "self": "/api/v1/customers/123"
    }
  },
  "included": [
    {
      "type": "formations",
      "id": "456",
      "attributes": {
        "title": "Laravel Basics"
      }
    }
  ],
  "meta": {
    "total": 100,
    "page": 1
  },
  "links": {
    "self": "/api/v1/customers",
    "first": "/api/v1/customers?page[page]=1",
    "last": "/api/v1/customers?page[page]=7",
    "prev": null,
    "next": "/api/v1/customers?page[page]=2"
  }
}
```

### Error Response Structure

```json
{
  "jsonapi": {
    "version": "1.0"
  },
  "errors": [
    {
      "id": "validation_error",
      "status": "422",
      "code": "VALIDATION_ERROR",
      "title": "Unprocessable Entity",
      "detail": "The email field is required.",
      "source": {
        "pointer": "/data/attributes/email"
      },
      "meta": {
        "field": "email",
        "rule": "required"
      }
    }
  ],
  "meta": {
    "correlation_id": "uuid-here"
  }
}
```

---

## Resource Type Naming

**Rules:**
1. Use **plural** kebab-case for resource types
2. Match route names to resource types
3. Use consistent naming across the API

```php
// ✅ CORRECT
"type": "customers"
"type": "formations"
"type": "training-sessions"
"type": "lesson-contents"

// ❌ WRONG
"type": "customer"
"type": "Customers"
"type": "Customer"
```

---

## Pagination Standards

### Query Parameters

Use JSON:API page parameters:

```
GET /api/v1/customers?page[number]=2&page[size]=30
```

### Response Format

```json
{
  "data": [...],
  "meta": {
    "page": {
      "total": 300,
      "count": 30,
      "per_page": 30,
      "current_page": 2,
      "total_pages": 10,
      "links": {
        "self": "http://api.example.com/api/v1/customers?page[number]=2",
        "first": "http://api.example.com/api/v1/customers?page[number]=1",
        "last": "http://api.example.com/api/v1/customers?page[number]=10",
        "prev": "http://api.example.com/api/v1/customers?page[number]=1",
        "next": "http://api.example.com/api/v1/customers?page[number]=3"
      }
    }
  }
}
```

### Implementation Pattern

```php
// In Controller
public function index(Request $request)
{
    $perPage = (int) $request->input('page.size', 30);
    $pageNumber = (int) $request->input('page.number', 1);

    $paginator = Customer::query()
        ->paginate($perPage, ['*'], 'page', $pageNumber);

    return CustomerResource::collection($paginator);
}

// In Resource
class CustomerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'type' => 'customers',
            'id' => (string) $this->id,
            'attributes' => [
                'email' => $this->email,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
            ],
            'relationships' => [
                'formations' => [
                    'links' => [
                        'self' => route('api.v1.customers.relationships.formations', $this->id),
                        'related' => route('api.v1.customers.formations', $this->id),
                    ],
                ],
            ],
            'links' => [
                'self' => route('api.v1.customers.show', $this->id),
            ],
        ];
    }
}
```

---

## Filtering Standards

### Query Parameters

Use JSON:API filter parameter:

```
GET /api/v1/formations?filter[tags]=laravel&filter[mode]=online&filter[featured]=true
```

### Implementation Pattern

```php
// In Controller
public function index(Request $request)
{
    $query = Formation::query();

    // Apply filters
    if ($request->has('filter.tags')) {
        $tags = explode(',', $request->input('filter.tags'));
        $query->whereJsonContains('tags', $tags);
    }

    if ($request->has('filter.mode')) {
        $query->where('mode', $request->input('filter.mode'));
    }

    if ($request->has('filter.featured')) {
        $query->where('featured', $request->boolean('filter.featured'));
    }

    return FormationResource::collection($query->paginate());
}
```

### Supported Filter Operators

| Operator | Syntax | Example |
|----------|--------|---------|
| Equals | `filter[field]=value` | `?filter[mode]=online` |
| Contains | `filter[field][contains]=value` | `?filter[name][contains]=Laravel` |
| Greater Than | `filter[field][gt]=value` | `?filter[price][gt]=100` |
| Less Than | `filter[field][lt]=value` | `?filter[price][lt]=500` |
| In Array | `filter[field]=a,b,c` | `?filter[tags]=laravel,php,web` |

---

## Sorting Standards

### Query Parameters

```
GET /api/v1/customers?sort=last_name,created_at
GET /api/v1/formations?sort=-title
```

### Rules
1. **Asc sort**: `sort=field`
2. **Desc sort**: `sort=-field`
3. **Multiple fields**: `sort=field1,-field2`

### Implementation Pattern

```php
// In Controller
public function index(Request $request)
{
    $query = Formation::query();

    if ($request->has('sort')) {
        $sortFields = explode(',', $request->input('sort'));

        foreach ($sortFields as $field) {
            $direction = Str::startsWith($field, '-') ? 'desc' : 'asc';
            $fieldName = ltrim($field, '-');

            $query->orderBy($fieldName, $direction);
        }
    } else {
        $query->orderBy('created_at', 'desc'); // Default sort
    }

    return FormationResource::collection($query->paginate());
}
```

---

## Sparse Fieldsets

Allow clients to request specific fields:

```
GET /api/v1/customers?fields[customers]=id,email,last_name
```

### Implementation

```php
// In Resource
class CustomerResource extends JsonResource
{
    public function toArray($request)
    {
        $fields = $request->input('fields.customers');

        $data = [
            'type' => 'customers',
            'id' => (string) $this->id,
        ];

        // Only return requested fields
        if (!$fields || in_array('email', explode(',', $fields))) {
            $data['attributes']['email'] = $this->email;
        }

        if (!$fields || in_array('last_name', explode(',', $fields))) {
            $data['attributes']['last_name'] = $this->last_name;
        }

        return $data;
    }
}
```

---

## Included Resources

Use the `include` parameter to request related resources:

```
GET /api/v1/customers/123?include=formations.lessons
```

### Implementation

```php
// In Controller
public function show($id, Request $request)
{
    $includes = explode(',', $request->input('include', ''));

    $customer = Customer::with($includes)->findOrFail($id);

    return CustomerResource::make($customer);
}

// In Resource
class CustomerResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'type' => 'customers',
            'id' => (string) $this->id,
            'attributes' => [...],
        ];

        // Include related resources if loaded
        if ($this->relationLoaded('formations')) {
            $data['relationships']['formations'] = [
                'data' => FormationResource::collection($this->formations),
            ];
        }

        return $data;
    }
}
```

---

## OpenAPI/Swagger Documentation

### Required Annotations

All controllers MUST have OpenAPI annotations:

```php
/**
 * @OA\Info(
 *   title="Training Platform API",
 *   version="1.0.0",
 *   description="Training Platform REST API",
 *   @OA\Contact(
 *     email="api@example.com",
 *     name="API Support"
 *   )
 * )
 *
 * @OA\Server(
 *   url=L5_APP_URL,
 *   description="API Server",
 *   @OA\Variable(
 *     name=L5_APP_URL,
 *     default="http://localhost:8000"
 *   )
 * )
 */
class CustomerController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/v1/customers",
     *   summary="List customers",
     *   description="Returns paginated list of customers with filtering and sorting",
     *   tags={"Customers"},
     *   @OA\Parameter(
     *     name="page[number]",
     *     in="query",
     *     description="Page number",
     *     required=false,
     *     @OA\Schema(type="integer", default=1)
     *   ),
     *   @OA\Parameter(
     *     name="page[size]",
     *     in="query",
     *     description="Page size",
     *     required=false,
     *     @OA\Schema(type="integer", default=30, maximum=100)
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Successful response",
     *     @OA\MediaType(
     *       mediaType="application/vnd.api+json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(
     *           property="data",
     *           type="array",
     *           @OA\Items(ref="#/components/schemas/Customer")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\MediaType(mediaType="application/vnd.api+json")
     *   )
     * )
     */
    public function index(Request $request)
    {
        // ...
    }

    /**
     * @OA\Post(
     *   path="/api/v1/customers",
     *   summary="Create customer",
     *   description="Creates a new customer",
     *   tags={"Customers"},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="application/vnd.api+json",
     *       @OA\Schema(
     *         type="object",
     *         required={"data"},
     *         @OA\Property(
     *           property="data",
     *           type="object",
     *           required={"type", "attributes"},
     *           @OA\Property(property="type", type="string", example="customers"),
     *           @OA\Property(
     *             property="attributes",
     *             type="object",
     *             required={"email", "first_name", "last_name"},
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="first_name", type="string"),
     *             @OA\Property(property="last_name", type="string")
     *           )
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="201",
     *     description="Customer created successfully"
     *   ),
     *   @OA\Response(
     *     response="422",
     *     description="Validation error"
     *   )
     * )
     */
    public function store(StoreCustomerRequest $request)
    {
        // ...
    }
}
```

### Schema Definitions

```php
/**
 * @OA\Schema(
 *   schema="Customer",
 *   type="object",
 *   title="Customer",
 *   description="Customer resource",
 *   required={"id", "type", "attributes"},
 *   @OA\Property(
 *     property="type",
 *     type="string",
 *     example="customers"
 *   ),
 *   @OA\Property(
 *     property="id",
 *     type="string",
 *     format="uuid"
 *   ),
 *   @OA\Property(
 *     property="attributes",
 *     type="object",
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="first_name", type="string"),
 *     @OA\Property(property="last_name", type="string")
 *   )
 * )
 */
```

---

## HTTP Status Codes

Use HttpStatus enum for all status codes:

| Status | HttpStatus Enum | Usage |
|--------|-----------------|-------|
| 200 | `HttpStatus::OK` | Successful GET, PATCH |
| 201 | `HttpStatus::CREATED` | Successful POST |
| 204 | `HttpStatus::NO_CONTENT` | Successful DELETE |
| 400 | `HttpStatus::BAD_REQUEST` | Invalid request |
| 401 | `HttpStatus::UNAUTHORIZED` | Not authenticated |
| 403 | `HttpStatus::FORBIDDEN` | Authenticated but not authorized |
| 404 | `HttpStatus::NOT_FOUND` | Resource not found |
| 409 | `HttpStatus::CONFLICT` | Resource conflict |
| 422 | `HttpStatus::UNPROCESSABLE_ENTITY` | Validation error |
| 429 | `HttpStatus::TOO_MANY_REQUESTS` | Rate limit exceeded |
| 500 | `HttpStatus::INTERNAL_SERVER_ERROR` | Server error |

---

## Content Negotiation

### Supported Content Types

**Request Headers:**
```
Content-Type: application/vnd.api+json
Accept: application/vnd.api+json
```

**Response Headers:**
```
Content-Type: application/vnd.api+json; charset=utf-8
```

### Implementation

```php
// bootstrap/app.php
$middleware->append(function (Request $request, callable $next) {
    // Enforce JSON:API content type for API requests
    if ($request->is('api/*')) {
        if ($request->isMethod('POST') &&
            !$request->hasHeader('Content-Type', 'application/vnd.api+json')) {
            return ApiResponseBuilder::error(
                'Content-Type must be application/vnd.api+json',
                'INVALID_CONTENT_TYPE'
            )->withStatusCode(HttpStatus::UNSUPPORTED_MEDIA_TYPE);
        }
    }

    return $next($request);
});
```

---

## Rate Limiting

### Headers

Include rate limit info in response headers:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1609459200
```

### Implementation

```php
// In middleware
if ($response->headers->get('X-RateLimit-Remaining') === 0) {
    $response->headers->set('Retry-After', 60);
}
```

---

## Security Headers

### Required Headers

All API responses MUST include:

```php
// Already implemented via SecurityHeaders middleware
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=()
Content-Security-Policy: default-src 'none'
```

---

## Testing with Pest

### JSON:API Compliance Tests

```php
use App\Models\Customer;

test('api returns json:api compliant response', function () {
    Customer::factory()->create([
        'email' => 'test@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
    ]);

    $response = $this->getJson('/api/v1/customers')
        ->assertStatus(HttpStatus::OK->value)
        ->assertHeader('Content-Type', 'application/vnd.api+json; charset=utf-8')
        ->assertJsonStructure([
            'jsonapi' => ['version' => '1.0'],
            'data' => [
                '*' => [
                    'type',
                    'id',
                    'attributes',
                    'links' => ['self']
                ]
            ],
            'links' => ['self', 'first', 'last', 'prev', 'next'],
            'meta' => ['page']
        ]);
});

test('can filter customers by email', function () {
    Customer::factory()->create(['email' => 'john@example.com']);
    Customer::factory()->create(['email' => 'jane@example.com']);

    $response = $this->getJson('/api/v1/customers?filter[email][contains]=john')
        ->assertStatus(HttpStatus::OK->value)
        ->assertJsonCount(1, 'data');
});

test('can sort customers by name', function () {
    Customer::factory()->create(['last_name' => 'Zoe']);
    Customer::factory()->create(['last_name' => 'Adam']);

    $response = $this->getJson('/api/v1/customers?sort=last_name')
        ->assertStatus(HttpStatus::OK->value);

    $data = $response->json('data');
    expect($data[0]['attributes']['last_name'])->toBe('Adam');
    expect($data[1]['attributes']['last_name'])->toBe('Zoe');
});
```

---

## Implementation Checklist

When creating new API endpoints:

- [ ] Follow JSON:API 1.0 spec for response format
- [ ] Include `jsonapi.version` in all responses
- [ ] Include `links.self` for each resource
- [ ] Use `data` wrapper for single resources
- [ ] Use `errors` array for error responses
- [ ] Include `meta.correlation_id` for tracing
- [ ] Add OpenAPI annotations (`@OA\*`)
- [ ] Use HttpStatus enum for status codes
- [ ] Include pagination metadata
- [ ] Support filtering, sorting, sparse fieldsets
- [ ] Write Pest tests for all endpoints
- [ ] Test error responses
- [ ] Test authentication/authorization
