# Customer Mobile App API

Brief **§11.7** — Customer-Facing Mobile App (catalog, ordering, history).  
Use this document to connect your **Flutter** customer app to the JAPS POS Laravel backend.

**Status:** Active (implemented)

---

## Base URL

| Environment | Example |
|-------------|---------|
| Local (Laragon) | `http://poscontinentalwholesale.test/api` |
| Or PHP artisan serve | `http://127.0.0.1:8000/api` |

All customer endpoints are under:

```
{BASE}/customer/...
```

Example: `POST http://poscontinentalwholesale.test/api/customer/login`

---

## Headers

| Header | Value |
|--------|--------|
| `Accept` | `application/json` |
| `Content-Type` | `application/json` (POST/PUT bodies) |
| `Authorization` | `Bearer {token}` (all routes except login) |

---

## Auth flow (Flutter)

1. Staff turns on **File → Customer App API** (company-wide Active).
2. Staff sets the customer’s **App Email / Password** on Customer → Account tab.
3. App calls `POST /api/customer/login` with email + password.
4. Store `token` securely (e.g. `flutter_secure_storage`).
5. Send `Authorization: Bearer {token}` on every later request.
6. On logout call `POST /api/customer/logout` and delete the local token.
7. If API returns `401` / `403` → send user to login / show “API deactivated” or inactive account.

---

## Brief §11.7 → Endpoints

| Brief feature | Endpoint |
|---------------|----------|
| Customer login | `POST /customer/login` |
| Own pricing / balance / account | `GET /customer/me` |
| Catalog search & browse | `GET /customer/items` |
| Place order (Sales Order **New**) | `POST /customer/orders` |
| Order history | `GET /customer/orders` |
| Order detail | `GET /customer/orders/{id}` |
| Invoices / balances (extra) | `GET /customer/invoices` |

---

## Endpoints

### 1. Login — `POST /api/customer/login`

**Auth:** none  
**Status:** Active

**Body**

```json
{
  "email": "store@example.com",
  "password": "secret123",
  "device_name": "pixel-8"
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `email` | yes | Portal email (`portal_email`) |
| `password` | yes | Portal password |
| `device_name` | no | Label for Sanctum token |

**Success `200`**

```json
{
  "token": "1|xxxxxxxx",
  "token_type": "Bearer",
  "customer": {
    "id": 12,
    "customer_id": "C1001",
    "company_name": "Corner Store LLC",
    "contact": "Jane Doe",
    "email": "jane@example.com",
    "portal_email": "store@example.com",
    "telephone": "555-0100",
    "address": "123 Main",
    "city": "Detroit",
    "state": "MI",
    "zip_code": "48201",
    "balance": 450.00,
    "credit_limit": 5000.00,
    "available_credit": 4550.00,
    "messages_alerts": null,
    "price_level": { "id": 1, "code": "A", "name": "Price A" },
    "portal_active": true
  }
}
```

**Errors**

| Code | When |
|------|------|
| `422` | Validation / invalid credentials |
| `403` | Customer inactive, or company **Customer App API** is deactivated |

---

### 2. Logout — `POST /api/customer/logout`

**Auth:** Bearer  
**Status:** Active

**Success `200`**

```json
{ "message": "Logged out." }
```

---

### 3. Me (profile) — `GET /api/customer/me`

**Auth:** Bearer  
**Status:** Active

Returns the same `customer` object shape as login (balance, credit, price level).

---

### 4. Catalog — `GET /api/customer/items`

**Auth:** Bearer  
**Status:** Active

**Query params**

| Param | Type | Notes |
|-------|------|--------|
| `search` | string | Item code, description, brand, UPC |
| `brand` | string | Exact manufacturer / brand |
| `department_id` | int | Filter |
| `category_id` | int | Filter |
| `subcategory_id` | int | Filter |
| `new_only` | bool | `1` = created in last 30 days (§11.1) |
| `per_page` | int | Default 50, max 100 |
| `page` | int | Pagination |

**Success `200`** — Laravel paginator

```json
{
  "data": [
    {
      "id": 55,
      "item_code": "1229W",
      "description": "Sample Item",
      "unit_of_measure": "EA",
      "brand": "Acme",
      "list_price": 12.50,
      "price": 12.50,
      "price_level_id": 1,
      "is_new": true,
      "department": { "id": 1, "code": "TOB", "name": "Tobacco" },
      "category": null,
      "subcategory": null
    }
  ],
  "current_page": 1,
  "last_page": 3,
  "per_page": 50,
  "total": 120
}
```

> **Price note:** `price` currently uses item **List Price**. Customer `price_level` is returned on `/me` for display; per-level matrix pricing can be layered later without changing the Flutter field name `price`.

---

### 5. Item detail — `GET /api/customer/items/{id}`

**Auth:** Bearer  
**Status:** Active

Same fields as list, plus `extended_description`, `primary_upc`, and `prices[]` (per-UOM rows).

---

### 6. Place order — `POST /api/customer/orders`

**Auth:** Bearer  
**Status:** Active  

Creates a POS **Sales Order** with status **`New`** for the **logged-in customer only** (customer_id cannot be spoofed).

**Body**

```json
{
  "customer_po_no": "PO-9988",
  "reference_no": null,
  "required_date": "2026-07-30",
  "comments": "Deliver to back door",
  "lines": [
    { "item_code": "1229W", "qty_ordered": 10 },
    { "item_code": "AB-100", "qty_ordered": 2, "price": 9.99 }
  ]
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `lines` | yes | Min 1 line |
| `lines.*.item_code` | yes | Must exist, sellable, active |
| `lines.*.qty_ordered` | yes | > 0 |
| `lines.*.price` | no | Defaults to item list price |
| `customer_po_no` | no | |
| `required_date` | no | Defaults today |
| `comments` | no | |

**Success `201`** — order with `lines`  
**Errors:** `422` validation / unknown item code

---

### 7. Order history — `GET /api/customer/orders`

**Auth:** Bearer  
**Status:** Active

| Param | Notes |
|-------|--------|
| `status` | Optional filter e.g. `New` |
| `per_page` / `page` | Pagination |

Own orders only.

---

### 8. Order detail — `GET /api/customer/orders/{id}`

**Auth:** Bearer  
**Status:** Active  

Includes `lines`. `404` if not this customer’s order.

---

### 9. Invoices — `GET /api/customer/invoices`

**Auth:** Bearer  
**Status:** Active

| Param | Notes |
|-------|--------|
| `unpaid_only` | `1` = exclude Paid |
| `per_page` / `page` | Pagination |

```json
{
  "data": [
    {
      "id": 9,
      "invoice_number": "INV-1001",
      "invoice_date": "2026-07-01",
      "status": "Open",
      "invoice_total": 250.00,
      "balance": 100.00
    }
  ]
}
```

---

## Error shape

Validation (`422`):

```json
{
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Unauthorized (`401`):

```json
{ "message": "Unauthorized. Customer portal token required." }
```

Inactive (`403`):

```json
{ "message": "Customer app access is inactive." }
```

---

## POS admin

### Turn Customer App API on/off
1. Log into POS.
2. **File → Customer App API**.
3. Click **Activate API** or **Deactivate API** for your company.

### Set a customer’s login (email / password)
1. POS → **Sales → Customers** → edit customer → **Account Summary** tab.
2. Set **App Email** + **App Password** → Save.

That customer can then login to the Flutter app with those credentials (while the company API is Active).

---

## Flutter quick tips

```dart
// After login
headers: {
  'Accept': 'application/json',
  'Authorization': 'Bearer $token',
}

// Catalog
GET /api/customer/items?search=cig&new_only=1&page=1

// Checkout
POST /api/customer/orders
body: { "lines": [ { "item_code": "1229W", "qty_ordered": 5 } ] }
```

Use `http` or `dio`. Prefer HTTPS in production (on-prem with reverse proxy / VPN — brief §11.10).

---

## Related APIs (not for customer Flutter app)

| API | Path | Status | Notes |
|-----|------|--------|--------|
| Staff / Sales Rep login | `POST /api/login` | Active (staff tokens) | Brief **§11.9** Sales Rep app — use later |
| Staff items/customers/orders | `/api/items`, `/api/customers`, … | Active | Requires **User** Sanctum token |

Do **not** mix staff tokens with `/api/customer/*` routes.

---

## Changelog

| Date | Change |
|------|--------|
| 2026-07-22 | Initial Customer App API (§11.7) + File → Customer App Access + this doc |
