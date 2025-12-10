# Wallet Service API

A backend-only Wallet Service built with Laravel 11, enabling users to:

- Deposit money using Paystack
- Manage wallet balances
- View transaction history
- Transfer funds between wallets
- Access the API using JWT (Google sign-in) or API keys

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Environment Variables](#environment-variables)
- [Authentication](#authentication)
- [API Endpoints](#api-endpoints)
- [Security Considerations](#security-considerations)
- [Error Handling & Idempotency](#error-handling--idempotency)
- [License](#license)

## Features

- Google OAuth2 JWT authentication
- Service-to-service API key access with permissions and expiry
- Wallet deposit via Paystack (with mandatory webhook handling)
- Wallet-to-wallet transfers with atomic DB transactions
- Wallet balance retrieval and transaction history
- Maximum 5 active API keys per user
- Permission-based API key access

## Requirements

- PHP 8.2+
- Laravel 11
- MySQL / MariaDB
- Composer
- Paystack account

## Installation

Clone the repository:

```bash
git clone <your-repo-url>
cd hng-wallet-service
```

Install dependencies:

```bash
composer install
```

Copy `.env.example` and configure:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

Generate JWT secret:

```bash
php artisan jwt:secret
```

Run migrations:

```bash
php artisan migrate
```

Serve the application locally:

```bash
php artisan serve
```

## Environment Variables

```env
APP_NAME=WalletService
APP_URL=http://localhost:8000

PAYSTACK_SECRET_KEY=sk_test_xxxxxxxxxxxxxxxxx
PAYSTACK_PUBLIC_KEY=pk_test_xxxxxxxxxxxxxxxxx

GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

## Authentication

### Google Sign-In (JWT)

**Routes:**
- `GET /api/auth/google/redirect`
- `GET /api/auth/google/callback`

Returns a JWT token for authenticated users.

### API Keys (Service-to-Service)

**Routes:**
- `POST /api/keys/create`
- `POST /api/keys/rollover`

API keys have permissions (read, deposit, transfer) and expiry. Maximum 5 active keys per user.

## API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/wallet/balance` | GET | JWT / API Key (read) | Retrieve wallet balance |
| `/wallet/deposit` | POST | JWT / API Key (deposit) | Initialize Paystack deposit |
| `/wallet/paystack/webhook` | POST | None | Paystack webhook to confirm deposit |
| `/wallet/deposit/{reference}/status` | GET | JWT / API Key (read) | Check deposit status (no wallet credit) |
| `/wallet/transfer` | POST | JWT / API Key (transfer) | Transfer funds to another wallet |
| `/wallet/transactions` | GET | JWT / API Key (read) | Get transaction history |

## Security Considerations

- Never expose secret keys
- Webhooks are validated before crediting wallets
- Transfers are rejected for insufficient balance
- API keys without permissions are rejected
- Maximum 5 active API keys per user; expired keys are rejected automatically

## Error Handling & Idempotency

- Unique Paystack reference required per deposit
- Webhooks are idempotent to prevent double-credit
- Transfers are atomic (no partial deduction)
- Clear error responses for:
  - Insufficient balance
  - Invalid API key
  - Expired API key
  - Missing permissions

## Notes

- Frontend/UI is out of scope; API-only
- Manual bank transfers are not supported
- Paystack is the only payment provider integrated

