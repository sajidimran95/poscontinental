# Sales Rep Mobile API (Section 11.9)

**Version:** 1.1.1  
**Last updated:** 2026-08-04  

Related files:

- [sales-rep-examples.json](./sales-rep-examples.json) — sample bodies  
- [sales-rep-openapi.json](./sales-rep-openapi.json) — OpenAPI 3  

---

## 0. Base URL (use this first)

Laravel API prefix is always `/api`. Sales Rep mobile routes live under **`/api/rep`**.

### Base URL by environment

| Environment | Site / app origin (`APP_URL`) | **API base for Flutter / Postman** |
|-------------|-------------------------------|-------------------------------------|
| Local Laragon (HTTPS) | `https://www.poscontinentalwholesale.test` | **`https://www.poscontinentalwholesale.test/api/rep`** |
| Local Laragon (HTTP alternate) | `http://poscontinentalwholesale.test` | **`http://poscontinentalwholesale.test/api/rep`** |
| Local `php artisan serve` | `http://127.0.0.1:8000` | **`http://127.0.0.1:8000/api/rep`** |
| Staging | `https://staging.yourdomain.com` | **`https://staging.yourdomain.com/api/rep`** |
| Production | `https://poscontinentalwholesale.com` | **`https://poscontinentalwholesale.com/api/rep`** |

**Rule:**  
`REP_API_BASE = {APP_URL}/api/rep`  
(no trailing slash)

Examples:

```
APP_URL      = https://www.poscontinentalwholesale.test
API_PREFIX   = /api
REP_PREFIX   = /api/rep
REP_API_BASE = https://www.poscontinentalwholesale.test/api/rep
```

### Full URL construction

```
{REP_API_BASE}/{endpoint}
```

| Action | Method | Full URL (local Laragon) |
|--------|--------|---------------------------|
| Login | POST | `https://www.poscontinentalwholesale.test/api/rep/login` |
| Profile | GET | `https://www.poscontinentalwholesale.test/api/rep/me` |
| Logout | POST | `https://www.poscontinentalwholesale.test/api/rep/logout` |
| Customers | GET | `https://www.poscontinentalwholesale.test/api/rep/customers` |
| Customer detail | GET | `https://www.poscontinentalwholesale.test/api/rep/customers/{id}` |
| Catalog items | GET | `https://www.poscontinentalwholesale.test/api/rep/catalog/items` |
| Item detail | GET | `https://www.poscontinentalwholesale.test/api/rep/catalog/items/{id}` |
| Departments | GET | `https://www.poscontinentalwholesale.test/api/rep/catalog/departments` |
| Categories | GET | `https://www.poscontinentalwholesale.test/api/rep/catalog/categories` |
| Subcategories | GET | `https://www.poscontinentalwholesale.test/api/rep/catalog/subcategories` |
| Brands | GET | `https://www.poscontinentalwholesale.test/api/rep/catalog/brands` |
| Order list | GET | `https://www.poscontinentalwholesale.test/api/rep/sales-orders` |
| Create order | POST | `https://www.poscontinentalwholesale.test/api/rep/sales-orders` |
| Order detail | GET | `https://www.poscontinentalwholesale.test/api/rep/sales-orders/{id}` |

Endpoint paths below are **relative to `REP_API_BASE`** (e.g. `/login` means `REP_API_BASE + /login`).

### Flutter example (baseUrl)

```dart
// Change only this when switching environment
const String repApiBase = String.fromEnvironment(
  'REP_API_BASE',
  defaultValue: 'https://www.poscontinentalwholesale.test/api/rep',
);

final dio = Dio(BaseOptions(
  baseUrl: repApiBase, // no trailing slash
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
));

// Then call relative paths only:
// dio.post('/login', data: {...});
// dio.get('/customers');
// dio.get('/catalog/items', queryParameters: {'customer_id': 10});
// dio.post('/sales-orders', data: {...});
```

Build flavors:

```bash
# local
flutter run --dart-define=REP_API_BASE=https://www.poscontinentalwholesale.test/api/rep

# production
flutter run --dart-define=REP_API_BASE=https://poscontinentalwholesale.com/api/rep
```

### Postman

1. Create environment variable `REP_API_BASE` = `https://www.poscontinentalwholesale.test/api/rep`  
2. Request URL = `{{REP_API_BASE}}/login`  
3. After login, set `TOKEN` from response and header `Authorization: Bearer {{TOKEN}}`

### Notes

- Use the **same host** users open in the browser for the POS web app.
- HTTPS local Laragon may need trust / `HttpClient` bad certificate only on debug.
- Media/images on items use site origin: `{APP_URL}/media/...` (not under `/api/rep`).

---

## 1. POS setup (admin, not the app)

| Step | Where in POS web | What |
|------|------------------|------|
| 1 | **File → Users & Roles** | Create one or more users with role **Sales Rep**, set **Active**, email + password |
| 2 | **Sales → Customers** list | Use **Sales Rep** column dropdown to assign each customer to that user (or edit customer form) |
| 3 | Flutter app | Login with that email/password → only assigned customers appear |

Rules:

- **Only role `sales_rep` (label: Sales Rep)** can use this API.
- Admin / Buyer / Warehouse → login **403**.
- Inactive user → **403**.
- Customer assignment is **done by admin in POS**, not by the mobile app.
- Mobile user never sees the full customer list — only `customers.sales_rep_id = logged-in user id`.

---

## 2. Authentication

**Type:** Laravel Sanctum personal access token (Bearer).

### Headers (all protected routes)

```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

### Response envelope

**Success**

```json
{
  "success": true,
  "message": "optional string or null",
  "data": {}
}
```

**Paginated list** — same + `meta` + `links`

```json
{
  "success": true,
  "message": null,
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 10,
    "last_page": 1,
    "from": 1,
    "to": 10
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": null
  }
}
```

**API error (login/middleware)**

```json
{
  "success": false,
  "message": "Human readable error",
  "errors": null,
  "data": null
}
```

**Validation (HTTP 422)**

```json
{
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

### Demo users (if seeded)

| Email | Password | Can use app? |
|-------|----------|--------------|
| `sales@continental.local` | `password` | Yes (sales_rep) |
| `admin@gmail.com` | `password` | No (admin → 403 on this API) |

---

## 3. Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| `POST` | `/login` | No | Sales Rep login → token |
| `GET` | `/me` | Yes | Current user profile |
| `POST` | `/logout` | Yes | Revoke current token |
| `GET` | `/customers` | Yes | Assigned customers only |
| `GET` | `/customers/{id}` | Yes | Balance, credit, messages |
| `GET` | `/catalog/items` | Yes | Search / browse catalog |
| `GET` | `/catalog/items/{id}` | Yes | Item detail |
| `GET` | `/catalog/departments` | Yes | Filter helpers |
| `GET` | `/catalog/categories` | Yes | Optional `department_id` |
| `GET` | `/catalog/subcategories` | Yes | Optional `category_id` |
| `GET` | `/catalog/brands` | Yes | Manufacturer list |
| `GET` | `/sales-orders` | Yes | Order history |
| `POST` | `/sales-orders` | Yes | Create SO status **New** |
| `GET` | `/sales-orders/{id}` | Yes | Order detail + lines |

---

## 4. Endpoint details

### 4.1 `POST /login`

**Body**

| Field | Type | Required |
|-------|------|----------|
| `email` | string (email) | yes |
| `password` | string | yes |
| `device_name` | string | no (token label) |

**200** — token + user (`is_sales_rep: true`)  
**403** — inactive, not company, or not Sales Rep role  
**422** — bad credentials / validation  

---

### 4.2 `GET /me`

**200** — `{ "user": { id, name, email, company_id, role, site, is_sales_rep, ... } }`

---

### 4.3 `POST /logout`

Revokes current Sanctum token.

---

### 4.4 `GET /customers`

Assigned customers only.

| Query | Type | Default | Notes |
|-------|------|---------|-------|
| `search` | string | — | code, name, contact, phone, email |
| `include_inactive` | bool | `false` | |
| `page` | int | 1 | |
| `per_page` | int | 25 | max 100 |

**Customer object (main fields)**

| Field | Notes |
|-------|--------|
| `id` | Internal PK (use this in order create) |
| `customer_id` | Display code e.g. C1005 |
| `company_name`, `contact`, phones, email, address | |
| `balance`, `credit_limit`, `available_credit` | money |
| `messages_alerts` | free text from POS |
| `price_level_id` / `price_level` | for catalog pricing |

---

### 4.5 `GET /customers/{id}`

**403** if customer not assigned to this rep.

**200**

```json
{ "success": true, "data": { "customer": { ... } } }
```

---

### 4.6 `GET /catalog/items`

Same inventory as POS Items (active + can_sell).

| Query | Type | Notes |
|-------|------|-------|
| `search` | string | code, description, UPC, brand |
| `department_id` | int | |
| `category_id` | int | |
| `subcategory_id` | int | |
| `brand` | string | matches `manufacturer` |
| `new_only` | bool | items created last 30 days → `is_new` |
| `customer_id` | int | **recommended** — resolves `unit_price` via customer price level; must be assigned |
| `page` | int | |
| `per_page` | int | default 50, max 100 |

**Item fields (selected)**

| Field | Notes |
|-------|--------|
| `item_code` | use when creating order lines |
| `unit_price` | sell price (with or without customer level) |
| `list_price` | base list |
| `available_qty` | stock − allocated |
| `is_new` | new item flag (11.1) |
| `brand` | manufacturer |
| `thumbnail_url` | may be null |

---

### 4.7 Catalog helpers

- `GET /catalog/departments`
- `GET /catalog/categories?department_id=`
- `GET /catalog/subcategories?category_id=`
- `GET /catalog/brands`

---

### 4.8 `GET /sales-orders`

History for this rep’s customers / orders.

| Query | Notes |
|-------|--------|
| `customer_id` | filter one assigned customer |
| `status` | e.g. `New`, `Invoiced` |
| `search` | order #, PO, reference, customer |
| `page`, `per_page` | default 25 |

---

### 4.9 `POST /sales-orders`

Creates a **Sales Order** (same as desk Section 4.1) with status **`New`** for warehouse/staff review.

**Body**

| Field | Required | Notes |
|-------|----------|-------|
| `customer_id` | yes | assigned customer **id** (PK) |
| `lines` | yes | min 1 |
| `lines[].item_code` | yes | |
| `lines[].qty_ordered` | yes | > 0 |
| `lines[].price` | no | if omitted → price level / list |
| `lines[].uom` | no | |
| `lines[].line_message` | no | |
| `required_date` | no | date |
| `customer_po_no` | no | |
| `reference_no` | no | |
| `comments` | no | |

**201** — `{ "order": { order_number, status: "New", lines, totals, ... } }`  
**403** — customer not yours  
**422** — stock (if no backorder), missing item, validation  

---

### 4.10 `GET /sales-orders/{id}`

Order + lines. **403** if not your customer/order.

---

## 5. Suggested Flutter app screens

```
Login
  → Home / Customers list (GET /customers)
      → Customer detail (balance, credit, messages)
          → Catalog (GET /catalog/items?customer_id=)
              → Cart
                  → Checkout POST /sales-orders
      → Order history (GET /sales-orders?customer_id=)
```

### Flutter coding notes

1. Save token in `flutter_secure_storage`.
2. Dio/http interceptor: `Authorization: Bearer $token`.
3. On **401** or login **403** with wrong role → logout + message.
4. Always pass `customer_id` to catalog after customer is selected.
5. Connectivity: app needs reachability to POS server (on-prem / VPN / tunnel — Section 11.10).

### Example Dio base

```dart
// Prefer dart-define — see section 0 Base URL
const repApiBase = String.fromEnvironment(
  'REP_API_BASE',
  defaultValue: 'https://www.poscontinentalwholesale.test/api/rep',
);

final dio = Dio(BaseOptions(
  baseUrl: repApiBase,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
));
// after login:
dio.options.headers['Authorization'] = 'Bearer $token';
// paths: dio.get('/customers'); dio.post('/login', data: ...);
```

---

## 6. HTTP status summary

| Code | Meaning |
|------|---------|
| 200 | OK |
| 201 | Order created |
| 401 | Missing/invalid token |
| 403 | Inactive, not Sales Rep, or not assigned to resource |
| 404 | Not found |
| 422 | Validation / stock |

---

## 7. Source code (backend)

| Piece | Path |
|-------|------|
| Routes | `routes/api.php` → prefix `rep` |
| Auth | `app/Http/Controllers/Api/Rep/AuthController.php` |
| Customers | `app/Http/Controllers/Api/Rep/CustomerController.php` |
| Catalog | `app/Http/Controllers/Api/Rep/CatalogController.php` |
| Orders | `app/Http/Controllers/Api/Rep/SalesOrderController.php` |
| Middleware | `app/Http/Middleware/EnsureSalesRepApi.php` |
| Scope | `app/Services/Rep/SalesRepScope.php` |
| Create SO | `app/Services/Rep/CreateSalesOrderFromRep.php` |

Quick route check:

```bash
php artisan route:list --path=api/rep
```
