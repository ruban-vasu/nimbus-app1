# Nimbus App

Clinic appointment booking system built with Laravel 11, MySQL 8, Redis, Docker, and a minimal React frontend.

## Stack

- Laravel 11
- PHP 8.3 FPM in Docker
- MySQL 8
- Redis 7
- Nginx
- React + TypeScript + Vite

## One-Command Docker Setup

### Prerequisites

- Docker Desktop
- Docker Compose

### Quick Start

The project is set up so the full stack can be started with one command:

```bash
cp .env.docker .env && docker compose up --build -d
```

After the containers are up, run the Laravel bootstrap commands once inside the PHP container:

```bash
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --seed
```

### URLs

- Laravel app via Nginx: http://localhost:8000
- Frontend container: http://localhost:3000
- MySQL host port: 3307
- Redis host port: 6380

### What `docker compose up --build -d` starts

- `php`: Laravel application runtime
- `nginx`: reverse proxy serving Laravel
- `mysql`: primary relational database
- `redis`: cache and locking backend
- `frontend`: built frontend container

### Docker Environment Defaults

These values come from `.env.docker`:

```env
APP_PORT=8000
FRONTEND_PORT=3000
DB_DATABASE=nimbus
DB_USERNAME=nimbus_user
DB_PASSWORD=secret
DB_ROOT_PASSWORD=rootsecret
DB_EXTERNAL_PORT=3307
REDIS_PASSWORD=redissecret
REDIS_EXTERNAL_PORT=6380
```

## API Overview

Base URL:

```text
http://localhost:8000/api
```

All responses are JSON.

## API Documentation

### 1. List Doctors

`GET /api/doctors`

Query params:

- `clinic_id` optional integer
- `specialization` optional string
- `per_page` optional integer, default `15`

Example request:

```http
GET /api/doctors?specialization=Cardiology&per_page=2
```

Example response:

```json
{
	"data": [
		{
			"id": 1,
			"clinic_id": 1,
			"name": "Dr. Asha Menon",
			"specialization": "Cardiology",
			"consultation_fee": "750.00",
			"is_active": true,
			"clinic": {
				"id": 1,
				"name": "Nimbus Central Clinic"
			},
			"created_at": "2026-03-26T08:30:00.000000Z",
			"updated_at": "2026-03-26T08:30:00.000000Z"
		}
	],
	"links": {
		"first": "http://localhost:8000/api/doctors?page=1",
		"last": "http://localhost:8000/api/doctors?page=4",
		"prev": null,
		"next": "http://localhost:8000/api/doctors?page=2"
	},
	"meta": {
		"current_page": 1,
		"from": 1,
		"last_page": 4,
		"path": "http://localhost:8000/api/doctors",
		"per_page": 2,
		"to": 2,
		"total": 8
	}
}
```

### 2. List Available Slots for a Doctor

`GET /api/doctors/{id}/slots`

Query params:

- `start_date` optional `YYYY-MM-DD`
- `end_date` optional `YYYY-MM-DD`
- `per_page` optional integer, default `15`

Example request:

```http
GET /api/doctors/1/slots?start_date=2026-03-27&end_date=2026-03-29
```

Example response:

```json
{
	"data": [
		{
			"id": 12,
			"doctor_id": 1,
			"date": "2026-03-27",
			"start_time": "09:00:00",
			"end_time": "09:30:00",
			"duration": 30,
			"status": "available",
			"doctor": {
				"id": 1,
				"name": "Dr. Asha Menon",
				"specialization": "Cardiology"
			},
			"created_at": "2026-03-26T09:00:00.000000Z",
			"updated_at": "2026-03-26T09:00:00.000000Z"
		}
	],
	"links": {
		"first": "http://localhost:8000/api/doctors/1/slots?page=1",
		"last": "http://localhost:8000/api/doctors/1/slots?page=1",
		"prev": null,
		"next": null
	},
	"meta": {
		"current_page": 1,
		"from": 1,
		"last_page": 1,
		"path": "http://localhost:8000/api/doctors/1/slots",
		"per_page": 15,
		"to": 1,
		"total": 1
	}
}
```

### 3. Generate Slots for a Doctor

`POST /api/doctors/{id}/slots/generate`

Request body:

```json
{
	"start_date": "2026-03-27",
	"end_date": "2026-03-28",
	"slot_duration": 30,
	"morning_start": "08:00",
	"morning_end": "12:00",
	"afternoon_start": "13:00",
	"afternoon_end": "17:00"
}
```

Example response:

```json
{
	"message": "Slots generated successfully.",
	"count": 16,
	"data": [
		{
			"id": 21,
			"doctor_id": 1,
			"date": "2026-03-27",
			"start_time": "08:00:00",
			"end_time": "08:30:00",
			"duration": 30,
			"status": "available",
			"doctor": {
				"id": 1,
				"name": "Dr. Asha Menon",
				"specialization": "Cardiology"
			},
			"created_at": "2026-03-26T10:00:00.000000Z",
			"updated_at": "2026-03-26T10:00:00.000000Z"
		}
	]
}
```

### 4. Book an Appointment

`POST /api/appointments`

Request body:

- `patient_id` required integer
- `slot_id` required integer
- `status` optional enum: `pending`, `confirmed`, `completed`, `cancelled`
- `notes` optional string

Example request:

```json
{
	"patient_id": 5,
	"slot_id": 21,
	"status": "confirmed",
	"notes": "Initial consultation"
}
```

Example success response:

```json
{
	"data": {
		"id": 44,
		"patient_id": 5,
		"slot_id": 21,
		"status": "confirmed",
		"notes": "Initial consultation",
		"patient": {
			"id": 5,
			"name": "Ravi Kumar"
		},
		"slot": {
			"id": 21,
			"doctor_id": 1,
			"date": "2026-03-27",
			"start_time": "08:00:00",
			"end_time": "08:30:00",
			"status": "booked"
		},
		"created_at": "2026-03-26T10:05:00.000000Z",
		"updated_at": "2026-03-26T10:05:00.000000Z"
	}
}
```

Example validation/business-rule error:

```json
{
	"message": "The selected slot is no longer available."
}
```

### 5. Get Appointment Details

`GET /api/appointments/{id}`

Example response:

```json
{
	"data": {
		"id": 44,
		"patient_id": 5,
		"slot_id": 21,
		"status": "confirmed",
		"notes": "Initial consultation",
		"patient": {
			"id": 5,
			"name": "Ravi Kumar"
		},
		"slot": {
			"id": 21,
			"doctor_id": 1,
			"date": "2026-03-27",
			"start_time": "08:00:00",
			"end_time": "08:30:00",
			"status": "booked"
		},
		"created_at": "2026-03-26T10:05:00.000000Z",
		"updated_at": "2026-03-26T10:05:00.000000Z"
	}
}
```

### 6. Cancel an Appointment

`PATCH /api/appointments/{id}/cancel`

Example success response:

```json
{
	"data": {
		"id": 44,
		"patient_id": 5,
		"slot_id": 21,
		"status": "cancelled",
		"notes": "Initial consultation",
		"patient": {
			"id": 5,
			"name": "Ravi Kumar"
		},
		"slot": {
			"id": 21,
			"doctor_id": 1,
			"date": "2026-03-27",
			"start_time": "08:00:00",
			"end_time": "08:30:00",
			"status": "available"
		},
		"created_at": "2026-03-26T10:05:00.000000Z",
		"updated_at": "2026-03-26T10:30:00.000000Z"
	}
}
```

Example rule failure:

```json
{
	"message": "Appointments can only be cancelled more than 4 hours before the scheduled time."
}
```

### 7. List a Patient's Appointment History

`GET /api/patients/{id}/appointments`

Query params:

- `per_page` optional integer, default `15`

Example request:

```http
GET /api/patients/5/appointments?per_page=2
```

Example response:

```json
{
	"data": [
		{
			"id": 44,
			"patient_id": 5,
			"slot_id": 21,
			"status": "confirmed",
			"notes": "Initial consultation",
			"patient": {
				"id": 5,
				"name": "Ravi Kumar"
			},
			"slot": {
				"id": 21,
				"doctor_id": 1,
				"date": "2026-03-27",
				"start_time": "08:00:00",
				"end_time": "08:30:00",
				"status": "booked"
			},
			"created_at": "2026-03-26T10:05:00.000000Z",
			"updated_at": "2026-03-26T10:05:00.000000Z"
		}
	],
	"links": {
		"first": "http://localhost:8000/api/patients/5/appointments?page=1",
		"last": "http://localhost:8000/api/patients/5/appointments?page=2",
		"prev": null,
		"next": "http://localhost:8000/api/patients/5/appointments?page=2"
	},
	"meta": {
		"current_page": 1,
		"from": 1,
		"last_page": 2,
		"path": "http://localhost:8000/api/patients/5/appointments",
		"per_page": 2,
		"to": 2,
		"total": 3
	}
}
```

### 8. Register or Find a Patient

`POST /api/patients/register-or-find`

Request body:

```json
{
	"name": "Ravi Kumar",
	"email": "ravi@example.com",
	"phone": "+91-9876543210",
	"date_of_birth": "1994-06-20",
	"insurance_provider": "Star Health"
}
```

Example response:

```json
{
	"data": {
		"id": 5,
		"name": "Ravi Kumar",
		"email": "ravi@example.com"
	}
}
```

## Booking Rules

The booking flow enforces several business rules:

- A slot must still be `available` at booking time.
- A slot in the past cannot be booked.
- A patient cannot create more than 3 appointments within 24 hours.
- Cancellation is only allowed when the appointment start time is more than 4 hours away.
- Slot generation avoids overlapping slots for the same doctor.

## Redis Locking and Concurrency Handling

### Why Redis was chosen

Redis is used for locking because booking is a write-contention problem, not just a validation problem. When multiple requests try to reserve the same slot at nearly the same time, ordinary application checks are not enough. Redis provides a fast, cross-process locking primitive that works well across multiple PHP workers and containers.

In this project, Redis is already part of the stack for cache/session/queue-style coordination, so it is a practical place to centralize booking locks.

### How concurrency is handled

The booking logic lives in `app/Services/AppointmentService.php`.

The service uses a defensive sequence:

1. Acquire a lock keyed by slot ID, for example `appointments:slot:{slotId}:lock`.
2. Block briefly while waiting for the lock so short bursts of traffic can serialize cleanly.
3. Open a database transaction.
4. Re-read the slot with `lockForUpdate()` so the database row is also protected.
5. Re-check business invariants inside the transaction.
6. Create the appointment.
7. Mark the slot as `booked`.
8. Commit the transaction and release the lock.

This gives two layers of protection:

- Distributed coordination through Redis.
- Row-level consistency through the database transaction.

### Why both Redis and database locks are used

Redis alone would reduce collisions, but it is still good practice to verify state again inside the transaction. Database row locking guarantees the final write path remains correct even if requests arrive very close together or a process resumes after waiting.

That combination prevents common race conditions such as:

- Two users booking the same slot simultaneously.
- A slot being read as available by multiple workers before one write completes.
- A delayed request creating an appointment after the slot state has already changed.

### Failure behavior

If the lock cannot be acquired quickly enough, the API fails safely with:

```json
{
	"message": "Unable to acquire slot lock. Please try again."
}
```

This is preferable to allowing ambiguous double-booking behavior.

## Verification

The appointment feature suite covers booking, cancellation, lock-contention behavior, and the 4-hour cancellation boundary.

Example command:

```bash
php artisan test --filter=AppointmentBookingTest
```
