# Mobile Apps (Phase F)

Sales-rep mobile apps consume the Laravel Sanctum staff API under `/api`.

## API

| Method | Path | Notes |
|--------|------|-------|
| POST | `/api/login` | `{ email, password, device_name }` → `{ token, user }` |
| GET | `/api/me` | Bearer token |
| GET | `/api/items?search=&new_only=1` | Paginated catalog |
| GET | `/api/customers?search=&assigned_only=1` | Customers (`assigned_only` for reps) |
| GET | `/api/customers/{id}` | Balance / available credit / alerts |
| POST | `/api/sales-orders` | Create New SO with lines |
| GET | `/api/sales-orders/{id}` | Order detail |
| GET | `/api/invoices?customer_id=` | Invoices |

Header: `Authorization: Bearer {token}` and `Accept: application/json`.

## Apps

Scaffold React Native / Expo client lives under [`mobile/`](../mobile/README.md):

- **Sales rep app** (`mobile/rep-app`) — staff login; `assigned_only=1` customers; create SO; show credit / alerts.

Platform target: **iOS + Android**, online-first (no offline queue in v1).

## Remote access

On-prem POS must expose HTTPS via VPN or reverse proxy so apps can reach `/api` from the field (see `docs/ON_PREM_HOSTING.md`).
