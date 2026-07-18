# Cafeteria Access Control System

A secure RESTful API built with Laravel for managing cafeteria entries through two physical turnstiles using single-use QR codes.

## Features

- Mobile user authentication via Laravel Sanctum (token-based).
- Unique, single-use QR code generation per turnstile with automatic expiry.
- Entry scanning with strict business rule enforcement:
  - **Global limit:** max 100 total entries per day across all users/turnstiles.
  - **Per-user limit:** max 2 entries per day per user.
  - QR codes are invalidated immediately after use and refreshed automatically.
- Race-condition-safe validation using database transactions and row locking.
- Dummy `OpenTurnstileJob` simulating a hardware trigger (dispatched as a queued job).

## Requirements

- PHP ^8.2
- Composer
- MySQL (e.g. via DBngin) or another Laravel-supported database

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure your database connection in `.env` (see `.env.example` for the expected variables), then run:

```bash
php artisan migrate
```

Serve the app locally (via Laravel Herd, `php artisan serve`, or your preferred method).

## API Endpoints

All endpoints are prefixed with `/api`.

### `POST /api/register`

Registers a new user and returns a Sanctum token.

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

### `POST /api/login`

Authenticates a user and returns a Sanctum token.

```json
{
  "email": "jane@example.com",
  "password": "password123"
}
```

### `GET /api/turnstiles/{id}/qr`

Returns the turnstile's current valid QR code, generating a new one if none exists or the previous one expired.

```json
{
  "turnstile_id": 1,
  "qr_code": "abc123...",
  "expires_at": "2026-07-18T12:46:43.000000Z"
}
```

### `POST /api/entry/scan`

**Requires authentication** (`Authorization: Bearer <token>`).

```json
{
  "turnstile_id": 1,
  "qr_code": "abc123..."
}
```

Validates, in order:
1. QR code matches the turnstile and hasn't expired.
2. Global daily limit (100) hasn't been exceeded.
3. Per-user daily limit (2) hasn't been exceeded.

On success: logs the entry, dispatches `OpenTurnstileJob`, invalidates the scanned QR code, and generates a fresh one for the next user.

## Architecture Notes

- Business logic lives in `App\Services\AccessControlService`, keeping controllers thin.
- `App\Exceptions\EntryValidationException` carries the appropriate HTTP status code for each business rule violation.
- Entry scans lock all turnstile rows (`lockForUpdate`) inside a `DB::transaction` to serialize concurrent scans and keep the global daily counter race-free.
