# Event-Driven Multi-Country HR Platform

A real-time, event-driven backend platform built with Laravel 10, RabbitMQ, Redis,
WebSockets (Soketi), and PostgreSQL. The system consists of two independent microservices
that communicate asynchronously via a message queue, with the HubService acting as the
central orchestration layer.

---

## Table of Contents

1. [Overview](#overview)
2. [Technology Stack](#technology-stack)
3. [Architecture](#architecture)
4. [Data Flow](#data-flow)
5. [Design Decisions & Trade-offs](#design-decisions--trade-offs)
6. [Quick Start](#quick-start)
7. [API Reference](#api-reference)
8. [WebSocket Channels](#websocket-channels)
9. [Testing](#testing)
10. [Environment Variables](#environment-variables)

---

## Overview

The platform solves two key challenges:

1. **Real-time synchronisation** — when employee data changes in the HR Service,
   all connected clients see updated checklists and employee lists immediately via WebSockets.

2. **Country-specific logic** — USA and Germany employees have different required data fields,
   UI columns, and dashboard widgets, all driven by the backend (server-driven UI pattern).

### Services

| Service         | Role                                             | Port |
| --------------- | ------------------------------------------------ | ---- |
| **HR Service**  | Employee CRUD + RabbitMQ publisher               | 8001 |
| **Hub Service** | Event consumer, Checklist, Server-Driven UI APIs | 8000 |
| **Hub Worker**  | RabbitMQ queue worker (same image, worker mode)  | —    |
| **PostgreSQL**  | Relational database (two DBs, one instance)      | 5432 |
| **RabbitMQ**    | Message broker — Management UI on port 15672     | 5672 |
| **Redis**       | Cache layer for HubService                       | 6379 |
| **Soketi**      | Self-hosted Pusher-compatible WebSocket server   | 6001 |

---

## Technology Stack

| Layer          | Choice                                      | Justification                                                              |
| -------------- | ------------------------------------------- | -------------------------------------------------------------------------- |
| Framework      | Laravel 10                                  | Built-in Queue, Events, Cache, Broadcasting abstractions                   |
| Message Broker | RabbitMQ                                    | Reliable delivery, management UI, AMQP protocol                            |
| Cache          | Redis                                       | Sub-millisecond reads, pattern-based key eviction, native in Laravel       |
| WebSockets     | Soketi                                      | Self-hosted, Pusher-compatible, no external dependency or free-tier limits |
| Database       | PostgreSQL                                  | JSONB support if needed later, strong ACID guarantees                      |
| Queue Driver   | `vladimir-yuldashev/laravel-queue-rabbitmq` | Official Laravel-ecosystem RabbitMQ driver                                 |
| Broadcasting   | `pusher/pusher-php-server`                  | Works against Soketi (Pusher-compatible protocol)                          |

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Docker Network                              │
│                                                                     │
│  ┌──────────────┐   REST CRUD    ┌──────────────────────────────┐  │
│  │   Frontend /  │◄──────────────│       HR Service              │  │
│  │   curl/test   │               │  (Laravel 10 — port 8001)    │  │
│  └──────────────┘               │                               │  │
│          │                      │  EmployeeController           │  │
│          │ WebSocket             │  EmployeeSeeder               │  │
│          │ (Soketi:6001)        └──────────┬────────────────────┘  │
│          │                                 │ dispatch(PublishEmployeeEvent)  │
│          │                      ┌──────────▼────────────────────┐  │
│          │                      │         RabbitMQ               │  │
│          │                      │    queue: employee-events      │  │
│          │                      └──────────┬────────────────────┘  │
│          │                                 │ queue:work              │
│          │                      ┌──────────▼────────────────────┐  │
│          │                      │       Hub Worker               │  │
│          │   broadcast()        │  PublishEmployeeEvent::handle  │  │
│          │◄─────────────────────│  CacheService::invalidate      │  │
│          │                      │  ChecklistService::buildReport │  │
│          │                      └──────────────────────────────┘  │
│          │                                                         │
│          │                      ┌──────────────────────────────┐  │
│          └─────── HTTP API ────►│       Hub Service             │  │
│                                 │  (Laravel 10 — port 8000)    │  │
│                                 │                               │  │
│                                 │  /api/checklists              │  │
│                                 │  /api/steps                   │  │
│                                 │  /api/employees               │  │
│                                 │  /api/schema/{step_id}        │  │
│                                 └──────────────────────────────┘  │
│                                                                     │
│  ┌──────────┐   ┌──────────┐   ┌──────────┐                       │
│  │PostgreSQL│   │  Redis   │   │  Soketi  │                       │
│  └──────────┘   └──────────┘   └──────────┘                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Data Flow

### Creating/Updating an Employee → Real-time UI Update

```
1.  Client  ──POST /api/employees──►  HR Service
2.  HR Service saves Employee to PostgreSQL
3.  HR Service dispatches PublishEmployeeEvent to RabbitMQ queue "employee-events"
4.  Hub Worker picks up the job (queue:work)
5.  Hub Worker calls CacheService::invalidateForEmployee(id, country)
     └── deletes "checklist:{country}" and "employees:{country}:*" keys from Redis
6.  Hub Worker calls HrApiService::getEmployeesByCountry(country)
     └── HTTP GET http://hr-service:8001/api/employees?country=...
7.  Hub Worker calls ChecklistService::buildReport(employees)
     └── Runs country-specific validators (USA / Germany)
     └── Calculates per-employee + aggregate completion percentages
8.  Hub Worker writes fresh report to Redis "checklist:{country}" (TTL 5m)
9.  Hub Worker broadcasts EmployeeDataUpdated event via Soketi
     └── Pushes to channels: employees.{COUNTRY}, checklists.{COUNTRY}, employees.{COUNTRY}.{ID}
10. Connected browser clients receive the event instantly
```

### GET /api/checklists (Cache-Aside Pattern)

```
Client ──GET /api/checklists?country=USA──► Hub Service
   └── Cache hit?  YES → return cached JSON (Redis, TTL 5 min)
   └── Cache miss? NO  → fetch from HR Service → run validators → cache → return
```

---

## Design Decisions & Trade-offs

### Cache Key Strategy

```
checklist:{country}                     Aggregate checklist report
employees:{country}:page:{n}:{pp}       Paginated employee list
employee:{id}                           Individual employee snapshot
```

Keys are small, predictable, and scoped by country so a Germany event never
invalidates USA cache entries.

**Trade-off**: Pattern-based invalidation (`employees:{country}:*`) requires a Redis
SCAN, which is O(n) over key count. For the scale of this challenge this is acceptable;
in production you'd track page keys explicitly or use a tag-based cache library.

### WebSocket Channel Design

| Channel                    | Subscribers           |
| -------------------------- | --------------------- |
| `employees.{COUNTRY}`      | Employee list views   |
| `checklists.{COUNTRY}`     | Checklist dashboard   |
| `employees.{COUNTRY}.{ID}` | Employee detail pages |

Public channels are used (no auth). In production, private channels with Sanctum
tokens would be appropriate for sensitive payloads (SSN, salary).

### Event Payload Format (Cross-Service Contract)

Both HR Service and Hub Worker share `App\Jobs\PublishEmployeeEvent` — the same FQCN
ensures Laravel's queue serialiser can hydrate the job on the consumer side.
The payload is a plain PHP array, keeping the contract explicit and version-friendly.

### Country Extensibility

New countries require:

1. A new `CountryValidatorInterface` implementation in `app/Validators/`
2. Registering it in `ChecklistService::$validators`
3. Adding column config in `EmployeeController::$columnConfig`
4. Adding step/schema config in `StepsController` and `SchemaController`

No existing code changes required — open/closed principle applies.

### Caching TTL

Both checklist and employee list caches use **5-minute TTL** as a fallback safety net.
The primary invalidation mechanism is event-driven (immediate on RabbitMQ messages),
so staleness beyond 5 minutes would only occur if the Hub Worker is down.

---

## Quick Start

### Prerequisites

- Docker ≥ 24
- Docker Compose ≥ 2.20
- (Optional) PHP 8.2 + Composer — only needed to run tests locally

### Start Everything

```bash
docker-compose up -d
```

This single command starts all 7 services. First build takes ~3 minutes (downloads
PHP images and installs Composer dependencies). Subsequent starts are fast.

### Check Status

```bash
docker-compose ps
```

### Verify HR Service is running

```bash
curl http://localhost:8001/api/employees
```

### Verify Hub Service is running

```bash
curl http://localhost:8000/api/steps?country=USA
```

### Open RabbitMQ Management UI

[http://localhost:15672](http://localhost:15672) — credentials: `guest / guest`

### Open WebSocket Live Test Page

Open [websocket-test.html](websocket-test.html) directly in your browser. No server needed — it's a standalone HTML file.

---

## API Reference

### HR Service (port 8001)

| Method | Endpoint              | Description                     |
| ------ | --------------------- | ------------------------------- |
| GET    | `/api/employees`      | List employees (`?country=USA`) |
| POST   | `/api/employees`      | Create employee                 |
| GET    | `/api/employees/{id}` | Get single employee             |
| PUT    | `/api/employees/{id}` | Update employee                 |
| DELETE | `/api/employees/{id}` | Delete employee                 |

**Create USA Employee**

```bash
curl -X POST http://localhost:8001/api/employees \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John",
    "last_name": "Doe",
    "salary": 75000,
    "country": "USA",
    "ssn": "123-45-6789",
    "address": "123 Main St, New York, NY"
  }'
```

**Create Germany Employee**

```bash
curl -X POST http://localhost:8001/api/employees \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Hans",
    "last_name": "Mueller",
    "salary": 65000,
    "country": "Germany",
    "goal": "Increase team productivity by 20%",
    "tax_id": "DE123456789"
  }'
```

---

### Hub Service (port 8000)

#### GET /api/checklists?country=USA

Returns aggregated data completeness validation for all employees in a country.

```bash
curl "http://localhost:8000/api/checklists?country=USA"
```

**Response:**

```json
{
  "country": "USA",
  "data": {
    "summary": {
      "total_employees": 3,
      "total_fields": 9,
      "completed_fields": 7,
      "incomplete_fields": 2,
      "overall_completion": 77.78
    },
    "employees": [
      {
        "employee_id": 1,
        "name": "John Doe",
        "country": "USA",
        "completed_fields": 3,
        "total_fields": 3,
        "completion_rate": 100,
        "is_complete": true,
        "fields": {
          "ssn": { "complete": true, "message": "SSN is present." },
          "salary": { "complete": true, "message": "Salary is set." },
          "address": { "complete": true, "message": "Address is present." }
        }
      }
    ]
  }
}
```

#### GET /api/steps?country=USA

```bash
curl "http://localhost:8000/api/steps?country=USA"
# USA:     Dashboard, Employees
# Germany: Dashboard, Employees, Documentation
```

#### GET /api/employees?country=USA&page=1&per_page=15

Returns paginated employees with country-specific columns. SSN is masked for USA.

```bash
curl "http://localhost:8000/api/employees?country=USA"
```

#### GET /api/schema/{step_id}?country=USA

Returns frontend widget configuration for a step.

```bash
curl "http://localhost:8000/api/schema/dashboard?country=Germany"
```

---

## WebSocket Channels

The test page (`websocket-test.html`) demonstrates real-time updates:

1. Open the file in your browser
2. Click **Connect** (default host/port targets Soketi on localhost:6001)
3. Select a country and click **Subscribe**
4. In another terminal, update an employee via the HR Service API
5. The event instantly appears in the test page

**Demo flow:**

```bash
# Watch events in test page, then run:
curl -X PUT http://localhost:8001/api/employees/1 \
  -H "Content-Type: application/json" \
  -d '{"salary": 95000}'
```

---

## Testing

### Run HR Service Tests

```bash
cd hrService
php artisan test
```

**Coverage:** 13 tests — model unit tests, all CRUD endpoints, validation edge cases.

### Run HubService Tests

```bash
cd hubService
php artisan test
```

**Coverage:** 46 tests across three suites:

| Suite       | Tests | What is tested                                                           |
| ----------- | ----- | ------------------------------------------------------------------------ |
| Unit        | 16    | `UsaCountryValidator`, `GermanyCountryValidator`, `ChecklistService`     |
| Feature     | 26    | All 4 API endpoints, validation errors, caching, SSN masking             |
| Integration | 4     | Full event pipeline: RabbitMQ → cache invalidation → WebSocket broadcast |

### Run Specific Suite

```bash
cd hubService && php artisan test --testsuite=Unit
cd hubService && php artisan test --testsuite=Integration
```

---

## Environment Variables

### HR Service

```dotenv
QUEUE_CONNECTION=rabbitmq
DB_CONNECTION=pgsql
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
```

### Hub Service

```dotenv
QUEUE_CONNECTION=rabbitmq
CACHE_DRIVER=redis
BROADCAST_DRIVER=pusher
HR_SERVICE_URL=http://hr-service:8001
PUSHER_HOST=soketi
PUSHER_PORT=6001
PUSHER_APP_KEY=innoscripta-key
PUSHER_APP_SECRET=innoscripta-secret
PUSHER_APP_ID=innoscripta
```

---

## Project Structure

```
innoscripta_test/
├── docker-compose.yml              # One-command startup
├── docker/
│   └── postgres/
│       └── init-multiple-dbs.sh   # Creates hr_service + hub_service DBs
├── websocket-test.html             # Browser WebSocket test page
│
├── hrService/                      # Employee CRUD microservice
│   ├── app/
│   │   ├── Http/Controllers/EmployeeController.php
│   │   ├── Http/Requests/{Store,Update}EmployeeRequest.php
│   │   ├── Http/Resources/EmployeeResource.php
│   │   ├── Jobs/PublishEmployeeEvent.php     ← RabbitMQ publisher
│   │   └── Models/Employee.php
│   ├── database/
│   │   ├── migrations/2024_02_09_..._create_employees_table.php
│   │   └── seeders/EmployeeSeeder.php         ← Demo data
│   └── tests/
│       ├── Feature/EmployeeCrudTest.php
│       └── Unit/EmployeeModelTest.php
│
└── hubService/                     # Main orchestration layer
    ├── app/
    │   ├── Contracts/CountryValidatorInterface.php
    │   ├── Validators/{Usa,Germany}CountryValidator.php
    │   ├── Services/
    │   │   ├── ChecklistService.php     ← Validation engine
    │   │   ├── CacheService.php         ← Redis cache management
    │   │   └── HrApiService.php         ← HTTP client for HR Service
    │   ├── Jobs/PublishEmployeeEvent.php ← RabbitMQ consumer (handle())
    │   ├── Events/EmployeeDataUpdated.php ← WebSocket broadcast event
    │   └── Http/Controllers/
    │       ├── ChecklistController.php
    │       ├── StepsController.php
    │       ├── EmployeeController.php
    │       └── SchemaController.php
    └── tests/
        ├── Unit/{Usa,Germany}CountryValidatorTest.php
        ├── Unit/ChecklistServiceTest.php
        ├── Feature/{Checklist,Steps,Employee,Schema}ControllerTest.php
        └── Integration/EventProcessingTest.php
```
