# Deploying to your own server (Docker Compose)

Runs the whole system — Postgres, MongoDB, Redis, Database-final (the
Application Server), and central-service — as one Docker Compose project.
central-service stays internal-only; only Database-final is reachable from
outside the server.

## 1. Prerequisites

- Docker + Docker Compose v2 installed on the server (`docker compose version`).
- Both repos cloned **side by side** on the server:
  ```
  smart-hospital/
    Database-final/     <- this repo; run all commands from here
    central-service/    <- https://github.com/sovandara1607/SHMS-Central-service
  ```

```bash
mkdir smart-hospital && cd smart-hospital
git clone https://github.com/sovandara1607/SHMS.git Database-final
git clone https://github.com/sovandara1607/SHMS-Central-service.git central-service
cd Database-final
```

## 2. Configure environment

```bash
cp .env.production.example .env.production
```

Generate the required secrets:

```bash
# Postgres / MongoDB / Redis passwords — run three times, paste each into .env.production
openssl rand -base64 24

# Shared secret for Database-final <-> central-service REST calls
openssl rand -hex 32
```

Fill in `.env.production`:
- `POSTGRES_PASSWORD`, `MONGO_PASSWORD`, `REDIS_PASSWORD` — the generated passwords above.
- `CENTRAL_SERVICE_API_KEY` — the hex secret above (used by both apps — same value).
- `DATABASE_FINAL_APP_URL` — how you'll actually reach it, e.g. `http://192.168.0.100:8000` on your LAN, or a real domain if you're reverse-proxying/exposing it further.
- `R2_*` — only if you want lab report PDFs on Cloudflare R2; leave blank to store them on local disk instead.

## 3. Generate the two Laravel APP_KEYs

Each app needs its own. These are just `base64:` + 32 random bytes — no need to run the containers first:

```bash
echo "DATABASE_FINAL_APP_KEY=base64:$(openssl rand -base64 32)" >> .env.production
echo "CENTRAL_SERVICE_APP_KEY=base64:$(openssl rand -base64 32)" >> .env.production
```

(Then move those two lines up near the top of the file if you want it tidy — order doesn't matter functionally.)

## 4. Build and start

```bash
docker compose -f docker-compose.production.yml --env-file .env.production up -d --build
```

First build takes a few minutes (composer install, npm build, PHP extensions). Watch it come up:

```bash
docker compose -f docker-compose.production.yml logs -f
```

## 5. Run migrations

Database-final owns the Postgres schema — central-service never migrates (see its README).

```bash
docker compose -f docker-compose.production.yml exec database-final php artisan migrate --force
```

## 6. Verify

```bash
# Database-final should return your login page
curl -sI http://localhost:8000/login | head -1

# central-service health check, from inside the network (it has no host port published)
docker compose -f docker-compose.production.yml exec database-final \
  curl -s http://central-service:8100/api/health
```

Then open `DATABASE_FINAL_APP_URL` in a browser (e.g. `http://192.168.0.100:8000`) and log in.

## Updating after a code change

```bash
git pull
docker compose -f docker-compose.production.yml --env-file .env.production up -d --build
```

(Pull the latest `central-service` too if it changed, from its own directory.)

## Notes

- Postgres/MongoDB/Redis have no host ports published by default — only reachable by the app containers on the compose network, not from outside the server. Uncomment a `ports:` block in `docker-compose.production.yml` if you need direct access for debugging.
- `central-service` also has no host port published — Database-final reaches it at `http://central-service:8100` over the compose network. This means the pharmacist/lab async flows (PDF generation, audit log, medical record versioning) only work while `central-service`'s three processes (`serve`, `bus:relay`, `queue:work`, all managed by `supervisord` inside its container) are running — check `docker compose -f docker-compose.production.yml logs central-service` if lab reports aren't generating.
- If you want this reachable from outside your home network, put a reverse proxy (Caddy/nginx/Traefik) with a real TLS cert in front of `database-final`'s published port rather than exposing port 8000 directly — this compose file doesn't set that up for you.
