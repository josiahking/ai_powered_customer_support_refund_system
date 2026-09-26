# AI-Powered Customer Support Refund System

Foundation for the WORKNOON full-stack AI integration product challenge. This phase provides a Laravel API, Next.js frontend, and PostgreSQL database; refund and AI features are not implemented yet.

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

**Status:** Phase 0 — Foundation.