# Expense Approval API

A plain-PHP API where managers approve their employees' expenses. Built with
PHP 8.5, MariaDB 11.4, PDO, and PHPUnit.

## Request to follow

The main example is **`POST /expenses/{id}/approve`**, covering authentication,
input validation, permissions, database writes, and a JSON response.

1. **Entry:** `public/index.php` calls `Request::capture()` in `src/Http/Request.php`
   and dispatches the route registered in `routes.php`.
2. **Handler:** `ExpenseController::approve()` in `src/Expense/ExpenseController.php`
   authenticates the caller and validates the comment.
3. **Database operation:** `ApproveExpense::execute()` in `src/Expense/ApproveExpense.php`
   checks permissions and saves the approval and history in one transaction.
4. **Response:** `JsonResponse::send()` in `src/Http/JsonResponse.php` sends the HTTP
   response, called from `public/index.php`, which also catches errors and logs them.

## Setup

Requires Docker and Docker Compose. No local PHP or database installation needed.

```sh
docker compose up --build -d --wait
```

API: **http://127.0.0.1:8080**. The database is created and seeded automatically on
first startup. Optional settings are in `.env.example`; defaults work without `.env`.
Rerun the command after changing code to rebuild the app.

```sh
docker compose logs app   # View logs
docker compose down       # Stop and keep database data
```

Database client connection: `127.0.0.1:3307`, database `expense_approval`,
user `expense_app`, password `local-password` (unless overridden).

## Demo data

All accounts use **`demo-password`**.

| Manager | Employee | Pending expense |
|---|---|---|
| `manager.a@app.test` | `employee.a@app.test` | 42 |
| `manager.b@app.test` | `employee.b@app.test` | 43 |

Expense 44 belongs to employee A and is already approved.

## Try the API

Login:

```sh
curl -s http://127.0.0.1:8080/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"manager.a@app.test","password":"demo-password"}'
```

Copy `data.access_token` from the response, then approve an expense:

```sh
TOKEN='paste-token-here'

curl -i http://127.0.0.1:8080/expenses/42/approve \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"comment":"Approved"}'
```

Returns **200** on success, **409** if already approved, or **403** if the caller
is not the assigned manager. Send `{}` to omit the comment.

Logout revokes the current token and returns **204**:

```sh
curl -i -X POST http://127.0.0.1:8080/logout \
  -H "Authorization: Bearer $TOKEN"
```

Tokens expire after one hour. Missing, expired, or revoked tokens return **401**.
`GET /` returns a simple API message.

## Tests

```sh
docker compose --profile test run --build --rm tests
docker compose --profile test stop test-db
```

Tests use an isolated database. Latest run: **33 tests, 309 assertions passed**.
Coverage includes authentication, permissions, approval, duplicates, and rollback.

## Structure and notes

- `public/index.php`: HTTP entry point; `routes.php`: route registration.
- `bootstrap.php`: autoloading and database connection; `src/`: application classes.
- `database/`: schema and seed data. Schema edits do not migrate existing databases.
- Approval and history are saved in one transaction, with row locking to prevent duplicate approvals.
- Local demo only: no production server, TLS, or rate limiting. Extra fields are ignored and expense IDs are cast to integers.

gpt-6-astra assistance was used for writing infrastructure code, tests, code review and documentation.
