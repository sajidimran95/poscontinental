# Mobile Apps (Phase F)

Continental Wholesale customer and sales-rep mobile apps consume the Laravel Sanctum API under `/api`.

## API

| Method | Path | Notes |
|--------|------|-------|
| POST | `/api/login` | `{ email, password, device_name }` → `{ token, user }` |
| GET | `/api/me` | Bearer token |
| GET | `/api/items?search=&new_only=1` | Paginated catalog |
| GET | `/api/customers?search=&assigned_only=1` | Customers (`assigned_only` for reps) |
| GET | `/api/customers/{id}` | Balance / available credit / alerts |
| POST | `/api/sales-orders` | Create New SO with lines |
| GET | `/api/sales-orders?customer_id=` | Order history |

Header: `Authorization: Bearer {token}` and `Accept: application/json`.

## Apps

Scaffold React Native / Expo clients live under [`mobile/`](../mobile/README.md):

1. **Customer app** (`mobile/customer-app`) — catalog search; cart → `POST /api/sales-orders`; order history.
2. **Sales rep app** (`mobile/rep-app`) — staff login; `assigned_only=1` customers; create SO; show credit / alerts.

Both platforms (iOS + Android), online-first (no offline queue in v1). Expand scaffolds with full UI when branding assets are available.

## Remote access

On-prem POS must expose HTTPS via VPN or reverse proxy so apps can reach `/api` from the field (see `docs/ON_PREM_HOSTING.md`).
