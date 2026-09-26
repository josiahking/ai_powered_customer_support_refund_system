# AI-Powered Customer Support Refund System

Backend foundation for the WORKNOON full-stack AI integration product challenge, with a Laravel API, Next.js frontend, and PostgreSQL database. Phase 1 adds synthetic customer/order data and a deterministic refund policy; AI integration is not implemented.

## Stack

- Laravel 13 API with PHP 8.3
- Next.js 16 with TypeScript and App Router
- PostgreSQL 17
- Docker Compose

## Structure

- `backend/` Laravel application
- `frontend/` Next.js application
- `docker-compose.yml` local application stack

## Run locally

Prerequisites: Docker Desktop (or Docker Engine) with Compose v2.

Copy `.env.example` to `.env` in the repository root, then start the stack:

```sh
docker compose up --build
```

Open the frontend at [http://localhost:3000](http://localhost:3000). The backend API is at [http://localhost:8000](http://localhost:8000), with a database-aware health check at [http://localhost:8000/api/health](http://localhost:8000/api/health).

Stop the services with `docker compose down`. To also remove the local PostgreSQL data volume, run `docker compose down -v`.

**Status:** Phase 1 — Refund domain and deterministic policy foundation.

## Phase 1 — Refund foundation

Refund decisions are deterministic and independent of AI providers. Final-sale orders and requests outside the 30-day window are denied first; suspicious, conflicting, or requests over $500 are escalated; eligible damaged-item and incorrect-item requests are approved. Other reasons are denied as unsupported.

The refund window and high-value threshold are configurable as `REFUND_WINDOW_DAYS` and `REFUND_HIGH_VALUE_THRESHOLD_CENTS` in the root environment file.

Seed 15 synthetic customer profiles, 30 orders, and seven policy-example requests in the running stack:

```sh
docker compose exec backend php artisan db:seed
```

Submit a request with `POST /api/refund-requests` using `order_id`, `requested_amount`, and a reason (`DAMAGED`, `INCORRECT_ITEM`, or `OTHER`), with optional `customer_message`. Order facts, including final-sale status, amount, and date, are loaded from the database. The response includes `id`, `outcome`, `reason_code`, and `explanation`.