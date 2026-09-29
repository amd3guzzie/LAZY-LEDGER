# LazyLedger REST API

Base URL: `/api`. Requests and responses are JSON (except the CSV export).

- **Auth**: PHP session cookie (`lazyledger_sid`), set by `POST /auth/login` or `POST /auth/register`.
- **CSRF**: every `POST`, `PUT`, `DELETE` must send `X-CSRF-Token: <token>`. Pages expose the token in `<meta name="csrf-token">`; login/register also return a fresh `csrf`.
- **Errors**: `{ "error": "message", "fields": { "field": "message" } }` with status `401` (not logged in), `403` (wrong role / bad CSRF), `404`, `405`, `409` (duplicate/conflict), `422` (validation), `429` (too many login attempts), `500`.
- **Pagination** (list endpoints that page): `?page=1&per_page=10` → `{ "data": [...], "meta": { "page", "per_page", "total", "pages" } }`.

## Auth

| Method | Path | Body | Notes |
|---|---|---|---|
| POST | `/auth/register` | `first_name, last_name?, email, password, confirm_password` | Creates a customer and logs in. Password: 8–72 chars, letter + number. |
| POST | `/auth/login` | `email, password` | Returns `user`, `redirect`, `csrf`. |
| POST | `/auth/logout` | — | Destroys the session. |
| GET | `/auth/me` | — | Current user. |

## Profile & notifications (any logged-in role)

| Method | Path | Body |
|---|---|---|
| GET | `/profile` | — |
| PUT | `/profile` | `first_name, last_name, email, budget_alerts?, bill_reminders?` |
| PUT | `/profile/password` | `current_password, new_password, confirm_password` |
| DELETE | `/profile` | `password` (customers only; permanent) |
| GET | `/notifications` | — → latest 30 + `unread` count |
| PUT | `/notifications/{id}/read` | — |
| PUT | `/notifications/read-all` | — |

## Customer

| Method | Path | Body / query |
|---|---|---|
| GET | `/accounts` | → accounts with computed `balance`, plus `net_worth` |
| POST | `/accounts` | `name, type (cash\|bank\|e_wallet\|savings\|credit_card), opening_balance ≥ 0, due_date?` |
| PUT | `/accounts/{id}` | same as POST |
| DELETE | `/accounts/{id}` | deletes the account and its transactions |
| GET | `/categories` | global + own custom categories |
| GET | `/transactions` | `q, type, category_id (id or "none"), account_id, from, to, sort (date\|amount\|description\|category\|account), dir (asc\|desc), page, per_page` |
| GET | `/transactions/{id}` | — |
| POST | `/transactions` | `type (income\|expense), amount > 0, description, transaction_date (YYYY-MM-DD), account_id, category_id?` |
| PUT | `/transactions/{id}` | same as POST |
| DELETE | `/transactions/{id}` | — |
| GET | `/transactions/export` | same filters as list → CSV download |
| GET | `/budgets?month=YYYY-MM` | budgets with `spent`, `pct`, plus summary (`budgeted`, `spent`, `unassigned`) |
| POST | `/budgets` | `category_id (expense), amount_limit > 0, month?` |
| PUT | `/budgets/{id}` | `amount_limit` |
| DELETE | `/budgets/{id}` | — |
| PUT | `/budgets/total` | `monthly_budget` (number or `null`) |
| GET | `/stats/summary?month=YYYY-MM` | left to spend, income/expense (+% vs last month), budgets, alerts, category breakdown, recent transactions, upcoming credit card bills, onboarding counts |
| GET | `/stats/monthly?months=3\|6\|12` | income, expense, net per month |
| GET | `/stats/categories?month=YYYY-MM` | spending per category with share % |
| GET | `/category-requests` | own requests |
| POST | `/category-requests` | `requested_name, requested_type, reason?` |
| GET | `/tickets` | own tickets (with staff replies) |
| POST | `/tickets` | `subject (≥3), message (≥10)` |

## Staff (staff or admin)

| Method | Path | Body / query |
|---|---|---|
| GET | `/staff/overview` | request/ticket counts by status, customer count, recent activity |
| GET | `/staff/category-requests` | `status, q, page` |
| PUT | `/staff/category-requests/{id}` | `status (approved\|rejected)` — approving creates the customer's category; customer is notified; audited |
| GET | `/staff/tickets` | `status (pending\|in_progress\|resolved\|open), q, page` |
| PUT | `/staff/tickets/{id}` | `status, staff_reply?` — customer is notified; audited |
| GET | `/staff/users` | `q, status, page` — customers' status only (no financial data) |

## Admin

| Method | Path | Body / query |
|---|---|---|
| GET | `/admin/overview` | user totals, active this week, signups & transactions per month, role breakdown, top categories, recent audit entries |
| GET | `/admin/users` | `q, role, status, sort (created\|name\|email\|last_login), dir, page` |
| POST | `/admin/users` | `first_name, last_name?, email, role, password` |
| PUT | `/admin/users/{id}` | `role?, status? (active\|suspended)` — not allowed on yourself |
| DELETE | `/admin/users/{id}` | permanent — not allowed on yourself |
| GET | `/admin/categories` | global categories with usage counts |
| POST | `/admin/categories` | `name, type` |
| PUT | `/admin/categories/{id}` | `name, type` (type locked once used) |
| DELETE | `/admin/categories/{id}` | transactions become Uncategorized |
| GET | `/admin/audit-log` | `q, from, to, page` (read-only; no update/delete routes exist) |

## External API consumed

`GET https://api.frankfurter.dev/v1/latest?base=PHP&symbols=USD` — called from the browser when the customer clicks **View in USD** on the Home tab; all amounts on the page are re-rendered using the returned rate.

## Example

```bash
# 1. get a CSRF token + session cookie
curl -c jar.txt http://localhost:8080/ | grep csrf-token
# 2. log in
curl -b jar.txt -c jar.txt -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" -H "X-CSRF-Token: <token>" \
  -d '{"email":"demo@lazyledger.test","password":"<password>"}'
# 3. list this month's expenses, biggest first
curl -b jar.txt "http://localhost:8080/api/transactions?type=expense&from=2026-09-01&sort=amount&dir=desc"
```
