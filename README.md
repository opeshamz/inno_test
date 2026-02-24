# Innoscripta — Multi-Country HR Platform

Two Laravel services. Employee data lives in HR Service. The Hub Service watches for changes, rebuilds validation reports, and pushes updates to connected clients over WebSockets.

---

## Section 1: Overview

### What it does

HR admins manage employees across different countries. Each country has different required fields — USA needs SSN and address, Germany needs a tax ID and a goal. When an employee record changes, any open dashboard sees the update immediately without a page refresh.

There are two separate services:

- **HR Service** (port 8001) — handles employee CRUD, publishes an event to RabbitMQ after each write
- **Hub Service** (port 8000) — consumes those events, recomputes checklists, broadcasts to WebSocket clients, and serves read APIs for the frontend

### Technology stack

**Framework — Laravel 10**
Queues, cache, broadcasting, and HTTP client are all built in. No extra packages needed for the core event flow.

**Database — PostgreSQL**
Better JSON handling and stricter types than MySQL. One instance runs both `hr_service` and `hub_service` as separate databases.

**Message broker — RabbitMQ**
Durable queues, dead-letter exchanges, and a management UI at `:15672` for inspecting messages. Using Redis as the queue backend was an option, but RabbitMQ keeps the two services properly decoupled — HR Service just fires an event and doesn't need to know Hub Service exists.

**Cache — Redis**
Rebuilding a checklist report means an HTTP call to HR Service plus running every validator. Redis caches the result and supports key-pattern deletion (`employees:USA:*`), so the worker can wipe stale pages after an event without tracking each key individually.

**WebSockets — Soketi**
Self-hosted Pusher-compatible server — Laravel's broadcasting works with it out of the box. Avoids a hosted Pusher account and its rate limits; runs in Docker with nothing needed beyond `.env` values.

**Queue driver — `vladimir-yuldashev/laravel-queue-rabbitmq`**
No official first-party AMQP driver exists in Laravel; this is the standard maintained option.

### Design decisions

**Server-driven UI**
Steps, schema widgets, and column definitions are stored in the database (`steps`, `step_schemas`, `column_configs` tables), not hardcoded in PHP arrays. The frontend asks the API what to render — it doesn't need to know about country differences.

**Country extensibility**
Adding a new country means:

1. Create a class implementing `CountryValidatorInterface` in `hubService/app/Validators/`
2. Register it in `AppServiceProvider` alongside the existing USA and Germany validators
3. Add validation rules in `CountryRuleProvider::map()` in the HR service
4. Seed rows into `steps`, `step_schemas`, and `column_configs`

No existing controller, service, or model code changes required.

**Masking driven by database column config**
The `column_configs` table has a `masked` boolean per column. `EmployeeController` reads that flag and masks any field marked true. SSN masking for USA is not a hardcoded `if ($country === 'USA')` check — it falls out of the data.

**Cache invalidation strategy**
Cache is invalidated by the hub-worker immediately when an event arrives. The 5-minute TTL is only a fallback for when the worker is down. Keys are scoped by country so a Germany event never touches USA cache entries.

**Trade-offs**

- Pattern-based cache invalidation (`employees:USA:*`) uses Redis SCAN which is O(n). Fine at this scale, but in a large deployment you'd track keys explicitly.
- No authentication on any endpoint. Routes are structured to add `auth:sanctum` middleware in one line if needed — `authorize()` in all FormRequests already returns `true` as a placeholder.
- HR Service and Hub Worker share the same job class name (`App\Jobs\PublishEmployeeEvent`). The HR side only dispatches; the Hub side only handles. The shared FQCN is what makes Laravel's queue driver deserialise the payload correctly on the consumer.

---

## Section 2: Architecture

### System diagram

```
Client
  │
  ├── POST/PATCH/DELETE /api/employees
  │         │
  │         ▼
  │   [ HR Service :8001 ]
  │     EmployeeController
  │     EmployeeService
  │     CountryRuleProvider
  │         │
  │         │ dispatch job
  │         ▼
  │   [ RabbitMQ ]
  │     queue: employee-events
  │         │
  │         │ queue:work
  │         ▼
  │   [ Hub Worker ]
  │     1. invalidate Redis
  │     2. fetch employees ──► HR Service
  │     3. run validators
  │     4. cache checklist ──► Redis
  │     5. broadcast
  │         │
  │         │ WebSocket push
  │         ▼
  │   [ Soketi :6001 ]
  │         │
  │         ▼
  │   Browser (websocket-test.html)
  │
  └── GET /api/checklists
      GET /api/employees
      GET /api/steps
      GET /api/schema/:id
              │
              ▼
        [ Hub Service :8000 ]
          Redis hit  ──► return cached
          Redis miss ──► fetch HR Service
                         cache + return

Shared infrastructure:
  PostgreSQL  — hr_service / hub_service databases
  Redis       — checklist + employee cache (TTL 5 min)
  Soketi      — WebSocket server (Pusher-compatible)
```

### Data flow — employee update end to end

```
1.  PATCH /api/employees/1  →  HR Service
2.  EmployeeService updates the DB row inside a transaction
3.  On success, dispatches PublishEmployeeEvent to RabbitMQ
      payload: { event_type, event_id, timestamp, country, data: { employee_id, changed_fields, employee } }
4.  Hub Worker picks up the message
5.  Worker deletes Redis keys:
      - checklist:USA
      - employees:USA:page:*
6.  Worker GETs /api/employees?country=USA from HR Service (fresh data)
7.  Worker runs UsaCountryValidator against each employee
      checks: ssn present, salary > 0, address non-empty
8.  Worker writes updated checklist report back to Redis (TTL 5 min)
9.  Worker broadcasts EmployeeDataUpdated to three Soketi channels:
      - employees.USA
      - checklists.USA
      - employees.USA.1  (specific employee)
10. Any browser subscribed to those channels receives the event instantly
```

### Cache-aside flow — GET /api/checklists

```
Request → Hub Service
  └── Redis has key "checklist:USA"?
       YES → return cached JSON (< 1ms)
        NO → fetch employees from HR Service
           → run ChecklistService::buildReport()
           → store in Redis with 5 min TTL
           → return response
```

---

## Quick start

```bash
# Start all services
docker compose up -d

# Check everything is up
docker compose ps

# Seed test data (runs automatically on first boot via docker-entrypoint.sh)
# If you need to re-seed manually:
docker compose exec hr-service php artisan db:seed
docker compose exec hub-service php artisan db:seed
```

Services after boot:

| Service         | URL                                    |
| --------------- | -------------------------------------- |
| HR Service API  | http://localhost:8001/api              |
| Hub Service API | http://localhost:8000/api              |
| RabbitMQ UI     | http://localhost:15672 (guest / guest) |
| WebSocket test  | open `websocket-test.html` in browser  |

> **Note:** Code is copied into the image at build time. After editing any PHP file, rebuild before testing:
>
> ```bash
> docker compose build hub-service hub-worker && docker compose up -d hub-service hub-worker
> ```

---

## API reference

### HR Service — port 8001

```
GET    /api/employees?country=USA      paginated employee list
POST   /api/employees                  create employee
GET    /api/employees/{id}             single employee
PATCH  /api/employees/{id}             partial update
DELETE /api/employees/{id}             delete
```

**Create USA employee**

```bash
curl -X POST http://localhost:8001/api/employees \
  -H "Content-Type: application/json" \
  -d '{"name":"John","last_name":"Doe","salary":75000,"country":"USA","ssn":"123-45-6789","address":"123 Main St"}'
```

**Create Germany employee**

```bash
curl -X POST http://localhost:8001/api/employees \
  -H "Content-Type: application/json" \
  -d '{"name":"Hans","last_name":"Mueller","salary":65000,"country":"Germany","goal":"Increase team output","tax_id":"DE123456789"}'
```

### Hub Service — port 8000

```
GET  /api/checklists              all countries
GET  /api/checklists?country=USA  single country
GET  /api/steps?country=USA       navigation steps for the UI
GET  /api/employees?country=USA   employees with column config
GET  /api/schema/{step_id}?country=USA  widget schema for a step
```

---

## Testing

```bash
# HR Service — 13 tests
cd hrService && php artisan test

# Hub Service — 46 tests
cd hubService && php artisan test

# Run a specific suite
cd hubService && php artisan test --testsuite=Integration
```

| Suite       | Count | Covers                                            |
| ----------- | ----- | ------------------------------------------------- |
| Unit        | 16    | Validators, ChecklistService                      |
| Feature     | 26    | All endpoints, validation, caching, SSN masking   |
| Integration | 4     | Full event pipeline: RabbitMQ → cache → WebSocket |

---

## Project structure

```
innoscripta_test/
├── docker-compose.yml
├── websocket-test.html
├── hrService/
│   └── app/
│       ├── Http/Controllers/EmployeeController.php
│       ├── Http/Requests/StoreEmployeeRequest.php
│       ├── Http/Requests/UpdateEmployeeRequest.php
│       ├── Jobs/PublishEmployeeEvent.php
│       ├── Models/Employee.php
│       └── Services/
│           ├── EmployeeService.php
│           └── CountryRuleProvider.php
└── hubService/
    └── app/
        ├── Contracts/CountryValidatorInterface.php
        ├── Validators/UsaCountryValidator.php
        ├── Validators/GermanyCountryValidator.php
        ├── Jobs/PublishEmployeeEvent.php
        ├── Events/EmployeeDataUpdated.php
        ├── Providers/AppServiceProvider.php
        ├── Http/Controllers/
        │   ├── ChecklistController.php
        │   ├── EmployeeController.php
        │   ├── StepsController.php
        │   └── SchemaController.php
        ├── Models/
        │   ├── Step.php
        │   ├── StepSchema.php
        │   └── ColumnConfig.php
        └── Services/
            ├── ChecklistService.php
            ├── CacheService.php
            └── HrApiService.php
```
