# DigitalOcean 5-droplet deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce the Docker Compose files, Caddyfile, env template, and walkthrough doc needed to run the Smart Hospital stack (Database-final, central-service, Postgres, MongoDB, Redis) across 5 separate DigitalOcean droplets instead of one host.

**Architecture:** One Docker Compose file per droplet (postgres, mongo, redis run standalone with published ports; central-service runs standalone with a published port; app1 runs database-final + a Caddy reverse proxy sharing a private compose network). Every droplet is configured from one shared `.env.digitalocean` file. Cross-droplet access is controlled by DigitalOcean Cloud Firewalls (documented, not created by these files) keyed on each droplet's public IP, with a fallback note to switch to private VPC IPs if available.

**Tech Stack:** Docker Compose v2, Postgres 18 (alpine), MongoDB 7, Redis 7 (alpine), Caddy 2 (alpine, automatic Let's Encrypt), Laravel (existing Dockerfiles in both repos).

## Global Constraints

- Droplets run Ubuntu 24.04, Docker not preinstalled — the doc must include install steps.
- 5 droplets, fixed public IPs (from spec): app1 `143.198.85.24`, centralservice-api-server `188.166.236.41`, postgres-db `165.22.242.44`, mongodb `159.65.14.101`, redis-db `159.65.9.74`.
- No VPC assumed by default; the doc must open with a check for an existing private network interface and note using private IPs instead of public ones if found.
- TLS via Caddy + automatic Let's Encrypt on app1 only. No Certbot, no Cloudflare Tunnel in this deployment path.
- `Database-final` and `central-service` are two separate git repositories on disk (`/Users/dara/development/Final-CS394/Database-final` and `/Users/dara/development/Final-CS394/central-service`) — files for each go in their own repo and get their own commits.
- One shared `.env.digitalocean` file gets copied identically to all 5 droplets; each droplet's compose file reads only the variables it needs.
- Match the style/conventions of the existing `Database-final/docker-compose.production.yml` and `Database-final/DEPLOYMENT.md` (required env vars use `${VAR:?message}`, defaults use `${VAR:-default}`, restart: unless-stopped, named volumes for data).
- Postgres/Mongo/Redis/central-service must NOT be reachable from the app1/central compose networks by service name anymore (they're on different droplets) — they're reached via `${POSTGRES_HOST}` etc. (an IP), not a Compose service name.

---

## File Structure

```
Database-final/
  deploy/digitalocean/
    .env.digitalocean.example     # Task 1
    docker-compose.postgres.yml   # Task 2
    docker-compose.mongo.yml      # Task 3
    docker-compose.redis.yml      # Task 4
    Caddyfile                     # Task 6
    docker-compose.app.yml        # Task 7
  DEPLOYMENT-DIGITALOCEAN.md      # Task 8

central-service/
  deploy/digitalocean/
    docker-compose.central.yml    # Task 5
```

Task 9 is a cross-file consistency check across everything above — no new files.

---

### Task 1: Shared env template

**Files:**
- Create: `Database-final/deploy/digitalocean/.env.digitalocean.example`

**Interfaces:**
- Produces: the full set of variable names every later compose file reads —
  `POSTGRES_HOST`, `MONGO_HOST`, `REDIS_HOST`, `CENTRAL_SERVICE_HOST`,
  `APP_DOMAIN`, `POSTGRES_USER`, `POSTGRES_PASSWORD`, `MONGO_USER`,
  `MONGO_PASSWORD`, `REDIS_PASSWORD`, `DATABASE_FINAL_APP_KEY`,
  `CENTRAL_SERVICE_APP_KEY`, `CENTRAL_SERVICE_API_KEY`,
  `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT`,
  `R2_URL`. Later tasks must use exactly these names — do not invent new
  ones or rename these.

- [ ] **Step 1: Write the env template**

```
# Copy this file to .env.digitalocean, fill in every value, then copy the
# SAME filled-in file to all 5 droplets (scp). Each droplet's compose file
# only reads the variables it needs and ignores the rest.

# --- Droplet addresses ---
# Use private (10.x) IPs here if the droplets share a VPC — see step 1 of
# DEPLOYMENT-DIGITALOCEAN.md. Otherwise use the public IPs below (default).
POSTGRES_HOST=165.22.242.44
MONGO_HOST=159.65.14.101
REDIS_HOST=159.65.9.74
CENTRAL_SERVICE_HOST=188.166.236.41

# --- Public domain for app1 (143.198.85.24) ---
# Point an A record at 143.198.85.24, then put that hostname here.
APP_DOMAIN=YOUR_DOMAIN

# --- Database credentials ---
# Generate each with: openssl rand -base64 24
POSTGRES_USER=smart_hospital
POSTGRES_PASSWORD=
MONGO_USER=smart_hospital
MONGO_PASSWORD=
REDIS_PASSWORD=

# --- Laravel app keys ---
# Generate each with: echo "base64:$(openssl rand -base64 32)"
DATABASE_FINAL_APP_KEY=
CENTRAL_SERVICE_APP_KEY=

# --- Database-final <-> central-service shared secret ---
# Generate with: openssl rand -hex 32
CENTRAL_SERVICE_API_KEY=

# --- Optional: Cloudflare R2 for lab report PDFs (leave blank for local disk) ---
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=
R2_URL=
```

- [ ] **Step 2: Verify it's plain, parseable env syntax**

No compose file exists yet to validate against (that starts in Task 2), so
check the file's shape directly — every non-comment, non-blank line must
be `KEY=value`:

```bash
cd Database-final/deploy/digitalocean
grep -vE '^\s*(#.*)?$' .env.digitalocean.example | grep -vE '^[A-Z_]+=.*$'
```

Expected: empty output (no line fails the `KEY=value` shape check).

- [ ] **Step 3: Commit**

```bash
cd Database-final
git add deploy/digitalocean/.env.digitalocean.example
git commit -m "Add shared DigitalOcean deployment env template"
```

---

### Task 2: Postgres compose file

**Files:**
- Create: `Database-final/deploy/digitalocean/docker-compose.postgres.yml`

**Interfaces:**
- Consumes: `POSTGRES_USER`, `POSTGRES_PASSWORD` from Task 1's env template.
- Produces: a `postgres` service listening on host port 5432, database name
  fixed at `smart_hospital`.

- [ ] **Step 1: Write the compose file**

```yaml
name: smart-hospital-postgres

services:
  postgres:
    image: postgres:18-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: smart_hospital
      POSTGRES_USER: ${POSTGRES_USER:-smart_hospital}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:?set POSTGRES_PASSWORD in .env.digitalocean}
    ports:
      - "5432:5432"
    volumes:
      - smart_hospital_pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER:-smart_hospital} -d smart_hospital"]
      interval: 5s
      timeout: 5s
      retries: 10

volumes:
  smart_hospital_pgdata:
```

- [ ] **Step 2: Validate the compose file**

Run:
```bash
cd Database-final/deploy/digitalocean
docker compose -f docker-compose.postgres.yml --env-file .env.digitalocean.example config \
  -- 2>&1 | grep -E "POSTGRES_PASSWORD|error" || true
```
Since `.env.digitalocean.example` has an empty `POSTGRES_PASSWORD`, expect
this to FAIL with `POSTGRES_PASSWORD is not set` (proves the required-var
guard works). Then re-run with a dummy value to prove the happy path:
```bash
POSTGRES_PASSWORD=dummy docker compose -f docker-compose.postgres.yml \
  --env-file .env.digitalocean.example config
```
Expected: prints resolved YAML with `POSTGRES_PASSWORD: dummy`, no errors.

- [ ] **Step 3: Commit**

```bash
cd Database-final
git add deploy/digitalocean/docker-compose.postgres.yml
git commit -m "Add DigitalOcean Postgres droplet compose file"
```

---

### Task 3: MongoDB compose file

**Files:**
- Create: `Database-final/deploy/digitalocean/docker-compose.mongo.yml`

**Interfaces:**
- Consumes: `MONGO_USER`, `MONGO_PASSWORD` from Task 1's env template.
- Produces: a `mongo` service listening on host port 27017, database name
  fixed at `smart_hospital_docs`.

- [ ] **Step 1: Write the compose file**

```yaml
name: smart-hospital-mongo

services:
  mongo:
    image: mongo:7
    restart: unless-stopped
    environment:
      MONGO_INITDB_DATABASE: smart_hospital_docs
      MONGO_INITDB_ROOT_USERNAME: ${MONGO_USER:-smart_hospital}
      MONGO_INITDB_ROOT_PASSWORD: ${MONGO_PASSWORD:?set MONGO_PASSWORD in .env.digitalocean}
    ports:
      - "27017:27017"
    volumes:
      - smart_hospital_mongodata:/data/db
    healthcheck:
      test: ["CMD", "mongosh", "--quiet", "--eval", "db.adminCommand('ping')"]
      interval: 5s
      timeout: 5s
      retries: 10

volumes:
  smart_hospital_mongodata:
```

- [ ] **Step 2: Validate the compose file**

Run:
```bash
cd Database-final/deploy/digitalocean
docker compose -f docker-compose.mongo.yml --env-file .env.digitalocean.example config 2>&1 | tail -5
```
Expected: FAILS with `MONGO_PASSWORD is not set` (empty in the example
file). Then:
```bash
MONGO_PASSWORD=dummy docker compose -f docker-compose.mongo.yml \
  --env-file .env.digitalocean.example config
```
Expected: prints resolved YAML with `MONGO_INITDB_ROOT_PASSWORD: dummy`, no
errors.

- [ ] **Step 3: Commit**

```bash
cd Database-final
git add deploy/digitalocean/docker-compose.mongo.yml
git commit -m "Add DigitalOcean MongoDB droplet compose file"
```

---

### Task 4: Redis compose file

**Files:**
- Create: `Database-final/deploy/digitalocean/docker-compose.redis.yml`

**Interfaces:**
- Consumes: `REDIS_PASSWORD` from Task 1's env template.
- Produces: a `redis` service listening on host port 6379, password auth
  required.

- [ ] **Step 1: Write the compose file**

```yaml
name: smart-hospital-redis

services:
  redis:
    image: redis:7-alpine
    restart: unless-stopped
    command: redis-server --requirepass ${REDIS_PASSWORD:?set REDIS_PASSWORD in .env.digitalocean}
    ports:
      - "6379:6379"
    volumes:
      - smart_hospital_redisdata:/data
    healthcheck:
      test: ["CMD", "redis-cli", "-a", "${REDIS_PASSWORD}", "--no-auth-warning", "ping"]
      interval: 5s
      timeout: 5s
      retries: 10

volumes:
  smart_hospital_redisdata:
```

- [ ] **Step 2: Validate the compose file**

Run:
```bash
cd Database-final/deploy/digitalocean
docker compose -f docker-compose.redis.yml --env-file .env.digitalocean.example config 2>&1 | tail -5
```
Expected: FAILS with `REDIS_PASSWORD is not set`. Then:
```bash
REDIS_PASSWORD=dummy docker compose -f docker-compose.redis.yml \
  --env-file .env.digitalocean.example config
```
Expected: prints resolved YAML, `command` includes `--requirepass dummy`.

- [ ] **Step 3: Commit**

```bash
cd Database-final
git add deploy/digitalocean/docker-compose.redis.yml
git commit -m "Add DigitalOcean Redis droplet compose file"
```

---

### Task 5: central-service compose file

**Files:**
- Create: `central-service/deploy/digitalocean/docker-compose.central.yml`

**Interfaces:**
- Consumes: `CENTRAL_SERVICE_APP_KEY`, `CENTRAL_SERVICE_HOST`,
  `POSTGRES_HOST`, `POSTGRES_USER`, `POSTGRES_PASSWORD`, `MONGO_HOST`,
  `MONGO_USER`, `MONGO_PASSWORD`, `REDIS_HOST`, `REDIS_PASSWORD`,
  `CENTRAL_SERVICE_API_KEY`, `R2_*` from Task 1's env template.
- Produces: a `central-service` service listening on host port 8100,
  reachable at `http://<CENTRAL_SERVICE_HOST>:8100` from other droplets.
  Builds from the repo root via `context: ../..` (this file lives two
  directories below the central-service repo root, same relative depth as
  `Database-final/deploy/digitalocean/docker-compose.app.yml`).

- [ ] **Step 1: Confirm the build context assumption**

Run: `ls /Users/dara/development/Final-CS394/central-service/Dockerfile`
Expected: file exists (confirms `context: ../..` from
`central-service/deploy/digitalocean/` will find it).

- [ ] **Step 2: Write the compose file**

```yaml
name: smart-hospital-central

services:
  central-service:
    build:
      context: ../..
    restart: unless-stopped
    ports:
      - "8100:8100"
    environment:
      APP_NAME: central-service
      APP_ENV: production
      APP_DEBUG: "false"
      APP_KEY: ${CENTRAL_SERVICE_APP_KEY:?set CENTRAL_SERVICE_APP_KEY in .env.digitalocean}
      APP_URL: http://${CENTRAL_SERVICE_HOST:?set CENTRAL_SERVICE_HOST in .env.digitalocean}:8100

      DB_CONNECTION: pgsql
      DB_HOST: ${POSTGRES_HOST:?set POSTGRES_HOST in .env.digitalocean}
      DB_PORT: 5432
      DB_DATABASE: smart_hospital
      DB_USERNAME: ${POSTGRES_USER:-smart_hospital}
      DB_PASSWORD: ${POSTGRES_PASSWORD:?set POSTGRES_PASSWORD in .env.digitalocean}

      MONGODB_URI: mongodb://${MONGO_USER:-smart_hospital}:${MONGO_PASSWORD:?set MONGO_PASSWORD in .env.digitalocean}@${MONGO_HOST:?set MONGO_HOST in .env.digitalocean}:27017/?authSource=admin
      MONGODB_DATABASE: smart_hospital_docs

      REDIS_CLIENT: phpredis
      REDIS_HOST: ${REDIS_HOST:?set REDIS_HOST in .env.digitalocean}
      REDIS_PASSWORD: ${REDIS_PASSWORD:?set REDIS_PASSWORD in .env.digitalocean}
      REDIS_PORT: 6379
      REDIS_BUS_DB: 2

      SESSION_DRIVER: array
      CACHE_STORE: array
      QUEUE_CONNECTION: redis
      FILESYSTEM_DISK: local

      CENTRAL_SERVICE_API_KEY: ${CENTRAL_SERVICE_API_KEY:?set CENTRAL_SERVICE_API_KEY in .env.digitalocean}

      R2_ACCESS_KEY_ID: ${R2_ACCESS_KEY_ID:-}
      R2_SECRET_ACCESS_KEY: ${R2_SECRET_ACCESS_KEY:-}
      R2_BUCKET: ${R2_BUCKET:-}
      R2_ENDPOINT: ${R2_ENDPOINT:-}
      R2_URL: ${R2_URL:-}

      MAIL_MAILER: log
```

- [ ] **Step 3: Validate the compose file**

Run (from `central-service/deploy/digitalocean/`, using the example env
file that lives in the other repo, plus dummy values for the required
vars it leaves blank):
```bash
cd central-service/deploy/digitalocean
CENTRAL_SERVICE_APP_KEY=dummy POSTGRES_PASSWORD=dummy MONGO_PASSWORD=dummy \
REDIS_PASSWORD=dummy CENTRAL_SERVICE_API_KEY=dummy \
docker compose -f docker-compose.central.yml \
  --env-file ../../../Database-final/deploy/digitalocean/.env.digitalocean.example \
  config
```
Expected: prints resolved YAML with `APP_URL: http://188.166.236.41:8100`
and all `${...:?...}` placeholders resolved, no errors.

- [ ] **Step 4: Commit**

```bash
cd central-service
git add deploy/digitalocean/docker-compose.central.yml
git commit -m "Add DigitalOcean central-service droplet compose file"
```

---

### Task 6: Caddyfile

**Files:**
- Create: `Database-final/deploy/digitalocean/Caddyfile`

**Interfaces:**
- Consumes: `APP_DOMAIN` environment variable (Caddy's native `{$VAR}`
  substitution, not Docker Compose substitution — this file is mounted
  read-only into the caddy container, so it must use Caddy's own env var
  syntax).
- Produces: reverse proxy from `{$APP_DOMAIN}` (ports 80/443, automatic
  Let's Encrypt) to `database-final:8000` — the Compose service name and
  port that Task 7's `database-final` service must expose.

- [ ] **Step 1: Write the Caddyfile**

```
{$APP_DOMAIN} {
	reverse_proxy database-final:8000
}
```

- [ ] **Step 2: Validate the Caddyfile syntax**

Run:
```bash
cd Database-final/deploy/digitalocean
docker run --rm -v "$(pwd)/Caddyfile:/etc/caddy/Caddyfile:ro" \
  -e APP_DOMAIN=example.com \
  caddy:2-alpine caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
```
Expected: `Valid configuration` (exit code 0). First run pulls the
`caddy:2-alpine` image, which may take a minute.

- [ ] **Step 3: Commit**

```bash
cd Database-final
git add deploy/digitalocean/Caddyfile
git commit -m "Add Caddy reverse proxy config for DigitalOcean app droplet"
```

---

### Task 7: App droplet compose file (database-final + Caddy)

**Files:**
- Create: `Database-final/deploy/digitalocean/docker-compose.app.yml`

**Interfaces:**
- Consumes: `DATABASE_FINAL_APP_KEY`, `APP_DOMAIN`, `POSTGRES_HOST`,
  `POSTGRES_USER`, `POSTGRES_PASSWORD`, `MONGO_HOST`, `MONGO_USER`,
  `MONGO_PASSWORD`, `REDIS_HOST`, `REDIS_PASSWORD`, `CENTRAL_SERVICE_HOST`,
  `CENTRAL_SERVICE_API_KEY`, `R2_*` from Task 1's env template. Reads
  `./Caddyfile` from Task 6 as a bind mount.
- Produces: `database-final` service (internal-only, port 8000, no host
  port published — reached only by the `caddy` service via Compose's
  default network on this same file) and `caddy` service (host ports
  80/443).

- [ ] **Step 1: Write the compose file**

```yaml
name: smart-hospital-app

services:
  database-final:
    build:
      context: ../..
    restart: unless-stopped
    expose:
      - "8000"
    environment:
      APP_NAME: "Smart Hospital Management System"
      APP_ENV: production
      APP_DEBUG: "false"
      APP_KEY: ${DATABASE_FINAL_APP_KEY:?set DATABASE_FINAL_APP_KEY in .env.digitalocean}
      APP_URL: https://${APP_DOMAIN:?set APP_DOMAIN in .env.digitalocean}

      DB_CONNECTION: pgsql
      DB_HOST: ${POSTGRES_HOST:?set POSTGRES_HOST in .env.digitalocean}
      DB_PORT: 5432
      DB_DATABASE: smart_hospital
      DB_USERNAME: ${POSTGRES_USER:-smart_hospital}
      DB_PASSWORD: ${POSTGRES_PASSWORD:?set POSTGRES_PASSWORD in .env.digitalocean}

      MONGODB_URI: mongodb://${MONGO_USER:-smart_hospital}:${MONGO_PASSWORD:?set MONGO_PASSWORD in .env.digitalocean}@${MONGO_HOST:?set MONGO_HOST in .env.digitalocean}:27017/?authSource=admin
      MONGODB_DATABASE: smart_hospital_docs

      REDIS_CLIENT: phpredis
      REDIS_HOST: ${REDIS_HOST:?set REDIS_HOST in .env.digitalocean}
      REDIS_PASSWORD: ${REDIS_PASSWORD:?set REDIS_PASSWORD in .env.digitalocean}
      REDIS_PORT: 6379
      REDIS_BUS_DB: 2

      SESSION_DRIVER: redis
      CACHE_STORE: redis
      QUEUE_CONNECTION: redis
      FILESYSTEM_DISK: local

      CENTRAL_SERVICE_BASE_URL: http://${CENTRAL_SERVICE_HOST:?set CENTRAL_SERVICE_HOST in .env.digitalocean}:8100
      CENTRAL_SERVICE_API_KEY: ${CENTRAL_SERVICE_API_KEY:?set CENTRAL_SERVICE_API_KEY in .env.digitalocean}

      R2_ACCESS_KEY_ID: ${R2_ACCESS_KEY_ID:-}
      R2_SECRET_ACCESS_KEY: ${R2_SECRET_ACCESS_KEY:-}
      R2_BUCKET: ${R2_BUCKET:-}
      R2_ENDPOINT: ${R2_ENDPOINT:-}
      R2_URL: ${R2_URL:-}

      MAIL_MAILER: log

  caddy:
    image: caddy:2-alpine
    restart: unless-stopped
    depends_on:
      - database-final
    ports:
      - "80:80"
      - "443:443"
    environment:
      APP_DOMAIN: ${APP_DOMAIN:?set APP_DOMAIN in .env.digitalocean}
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
      - caddy_config:/config

volumes:
  caddy_data:
  caddy_config:
```

- [ ] **Step 2: Validate the compose file**

Run:
```bash
cd Database-final/deploy/digitalocean
docker compose -f docker-compose.app.yml --env-file .env.digitalocean.example config 2>&1 | tail -5
```
Expected: FAILS on the first unset required var it hits (e.g.
`DATABASE_FINAL_APP_KEY is not set`). Then:
```bash
DATABASE_FINAL_APP_KEY=dummy APP_DOMAIN=example.com POSTGRES_PASSWORD=dummy \
MONGO_PASSWORD=dummy REDIS_PASSWORD=dummy CENTRAL_SERVICE_API_KEY=dummy \
docker compose -f docker-compose.app.yml --env-file .env.digitalocean.example config
```
Expected: prints resolved YAML for both `database-final` and `caddy`
services, `APP_URL: https://example.com`, no errors. Confirm `caddy`'s
`ports` list includes `80:80` and `443:443`, and `database-final` has no
`ports:` key (only `expose: ["8000"]`) — this is the internal-only
guarantee from the design.

- [ ] **Step 3: Commit**

```bash
cd Database-final
git add deploy/digitalocean/docker-compose.app.yml
git commit -m "Add DigitalOcean app droplet compose file (database-final + Caddy)"
```

---

### Task 8: Deployment walkthrough doc

**Files:**
- Create: `Database-final/DEPLOYMENT-DIGITALOCEAN.md`

**Interfaces:**
- Consumes: every file/variable name produced in Tasks 1–7 — must reference
  them exactly (file paths, env var names, service names, ports) or the
  self-review in Task 9 will catch the mismatch.
- Produces: nothing consumed by later tasks — this is the terminal
  human-facing artifact.

- [ ] **Step 1: Write the doc**

```markdown
# Deploying to 5 DigitalOcean droplets

Spreads the Smart Hospital system across 5 separate droplets — one each
for Postgres, MongoDB, Redis, central-service, and the public app
(Database-final + Caddy) — instead of the single-host setup in
`DEPLOYMENT.md`.

| Role | IP | Runs |
|---|---|---|
| app1 | 143.198.85.24 | Caddy (TLS) + Database-final |
| centralservice-api-server | 188.166.236.41 | central-service |
| postgres-db | 165.22.242.44 | Postgres |
| mongodb | 159.65.14.101 | MongoDB |
| redis-db | 159.65.9.74 | Redis |

## Prerequisites

- SSH access to all 5 droplets (root or a sudo user).
- A domain you can point at 143.198.85.24 (an A record).
- `Database-final` cloned on your own machine (for `scp`-ing files) and on
  `app1`. `central-service` cloned on `centralservice-api-server`.

## 1. Check for private networking

On each droplet:

```bash
ip -4 addr show eth1
```

If this shows a `10.x.x.x` address, the droplets share a DigitalOcean VPC
— use those private IPs (not the public ones in the table above) for
`POSTGRES_HOST`, `MONGO_HOST`, `REDIS_HOST`, and `CENTRAL_SERVICE_HOST` in
step 3 below. If the command errors or shows no `eth1`, there's no private
network — stick with the public IPs.

Without a VPC, traffic between droplets crosses the public internet,
restricted by firewall rules (below) but not encrypted in transit. Fine
for this deployment; attach the droplets to a VPC later if that matters
to you.

## 2. Install Docker on all 5 droplets

Run this over SSH on each of the 5 droplets:

```bash
curl -fsSL https://get.docker.com | sh
```

## 3. Create DigitalOcean Cloud Firewalls

In the DigitalOcean dashboard → **Networking → Firewalls → Create Firewall**,
create one firewall per droplet (or reuse a firewall across droplets with
identical rules) and assign it to that droplet:

- **app1**: inbound TCP 80 and 443 from `0.0.0.0/0` (and `::/0`), TCP 22
  from your own IP only.
- **centralservice-api-server**: inbound TCP 8100 from `143.198.85.24/32`
  only, TCP 22 from your own IP only.
- **postgres-db**: inbound TCP 5432 from `143.198.85.24/32` and
  `188.166.236.41/32` only, TCP 22 from your own IP only.
- **mongodb**: inbound TCP 27017 from `143.198.85.24/32` and
  `188.166.236.41/32` only, TCP 22 from your own IP only.
- **redis-db**: inbound TCP 6379 from `143.198.85.24/32` and
  `188.166.236.41/32` only, TCP 22 from your own IP only.

(If you found private IPs in step 1, use those `/32`s instead of the
public ones for the inter-droplet rules — SSH still needs your public IP.)

## 4. Fill in the shared env file

On your own machine:

```bash
cd Database-final
cp deploy/digitalocean/.env.digitalocean.example deploy/digitalocean/.env.digitalocean
```

Edit `deploy/digitalocean/.env.digitalocean`:
- If step 1 found private IPs, replace the 4 `*_HOST` values with them.
- Set `APP_DOMAIN` to the hostname you're pointing at 143.198.85.24.
- Generate and fill in the 5 secrets it asks for, using the `openssl`
  commands in the file's comments.

Copy the same filled-in file to all 5 droplets:

```bash
for ip in 143.198.85.24 188.166.236.41 165.22.242.44 159.65.14.101 159.65.9.74; do
  scp deploy/digitalocean/.env.digitalocean root@$ip:/root/.env.digitalocean
done
```

## 5. Bring up the data tier

These three can be done in any order, in parallel.

**postgres-db** (165.22.242.44):
```bash
scp deploy/digitalocean/docker-compose.postgres.yml root@165.22.242.44:/root/
ssh root@165.22.242.44 \
  'docker compose -f docker-compose.postgres.yml --env-file .env.digitalocean up -d'
```

**mongodb** (159.65.14.101):
```bash
scp deploy/digitalocean/docker-compose.mongo.yml root@159.65.14.101:/root/
ssh root@159.65.14.101 \
  'docker compose -f docker-compose.mongo.yml --env-file .env.digitalocean up -d'
```

**redis-db** (159.65.9.74):
```bash
scp deploy/digitalocean/docker-compose.redis.yml root@159.65.9.74:/root/
ssh root@159.65.9.74 \
  'docker compose -f docker-compose.redis.yml --env-file .env.digitalocean up -d'
```

Check each is healthy: `ssh root@<ip> docker compose ps` should show
`healthy` once the healthcheck passes (a few seconds).

## 6. Bring up central-service

On `centralservice-api-server` (188.166.236.41):

```bash
ssh root@188.166.236.41
git clone https://github.com/sovandara1607/SHMS-Central-service.git central-service
cp /root/.env.digitalocean central-service/deploy/digitalocean/.env.digitalocean
cd central-service
docker compose -f deploy/digitalocean/docker-compose.central.yml \
  --env-file deploy/digitalocean/.env.digitalocean up -d --build
```

First build takes a few minutes. Watch it:
`docker compose -f deploy/digitalocean/docker-compose.central.yml logs -f`

## 7. Bring up app1 (Database-final + Caddy)

On `app1` (143.198.85.24):

```bash
ssh root@143.198.85.24
git clone https://github.com/sovandara1607/SHMS.git Database-final
cp /root/.env.digitalocean Database-final/deploy/digitalocean/.env.digitalocean
cd Database-final
docker compose -f deploy/digitalocean/docker-compose.app.yml \
  --env-file deploy/digitalocean/.env.digitalocean up -d --build
```

## 8. Point DNS

Create an A record for the hostname you put in `APP_DOMAIN` → `143.198.85.24`.
Wait for it to resolve (`dig +short YOUR_DOMAIN`) before continuing — Caddy
requests the Let's Encrypt cert on first HTTPS request and needs DNS to
already point at it.

## 9. Run migrations

From `app1`:

```bash
docker compose -f deploy/digitalocean/docker-compose.app.yml \
  --env-file deploy/digitalocean/.env.digitalocean \
  exec database-final php artisan migrate --force
```

## 10. Verify

```bash
# From app1, the app itself:
curl -sI https://YOUR_DOMAIN/login | head -1

# From app1, reaching central-service over the network:
curl -s http://188.166.236.41:8100/api/health
# (or the private IP, if you're using one)
```

Then open `https://YOUR_DOMAIN` in a browser and confirm the login page
loads with a valid cert (padlock, no warnings), and that you can log in.

## Updating after a code change

Same dependency order as initial bring-up — data tier doesn't need
rebuilding, central-service before app1:

```bash
# On centralservice-api-server:
cd central-service && git pull && docker compose -f deploy/digitalocean/docker-compose.central.yml \
  --env-file deploy/digitalocean/.env.digitalocean up -d --build

# On app1:
cd Database-final && git pull && docker compose -f deploy/digitalocean/docker-compose.app.yml \
  --env-file deploy/digitalocean/.env.digitalocean up -d --build
```

## Notes

- Without a VPC (see step 1), Postgres/Mongo/Redis/central-service traffic
  between droplets is firewalled by source IP but not encrypted — the
  same trust model as opening a database to a fixed list of IPs anywhere
  else. Attach the droplets to a DigitalOcean VPC and switch the `*_HOST`
  values to private IPs if you want this off the public internet entirely.
- `database-final`'s port 8000 is never published to app1's public
  interface — only the `caddy` container on the same droplet can reach it,
  over the Compose-managed network from `docker-compose.app.yml`.
- If lab report PDFs (pharmacist/lab async flows) aren't generating, check
  `docker compose -f deploy/digitalocean/docker-compose.central.yml logs`
  on `centralservice-api-server` — those flows depend on central-service's
  three supervised processes (`serve`, `bus:relay`, `queue:work`) staying
  up.
```

- [ ] **Step 2: Spot-check every IP/port/var name against Tasks 1–7**

Run:
```bash
cd Database-final
grep -oE '1[0-9]{2}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}' DEPLOYMENT-DIGITALOCEAN.md | sort -u
```
Expected: exactly the 5 IPs from the Global Constraints table, no others
introduced. Then:
```bash
grep -c 'docker-compose.postgres.yml\|docker-compose.mongo.yml\|docker-compose.redis.yml\|docker-compose.central.yml\|docker-compose.app.yml' DEPLOYMENT-DIGITALOCEAN.md
```
Expected: a nonzero count for each filename (all 5 are referenced).

- [ ] **Step 3: Commit**

```bash
git add DEPLOYMENT-DIGITALOCEAN.md
git commit -m "Add DigitalOcean 5-droplet deployment walkthrough"
```

---

### Task 9: Cross-file consistency check

**Files:**
- None created — this task only reads files from Tasks 1–8 and fixes any
  mismatch it finds in place.

**Interfaces:**
- Consumes: every file from Tasks 1–8.
- Produces: nothing new; this is the plan's final verification gate.

- [ ] **Step 1: Confirm every env var referenced by a compose file exists in the template**

Run:
```bash
cd Database-final/deploy/digitalocean
comm -23 \
  <(grep -ohE '\$\{[A-Z_]+' docker-compose.*.yml ../../../central-service/deploy/digitalocean/docker-compose.central.yml \
    | sed 's/\${//' | sort -u) \
  <(grep -oE '^[A-Z_]+=' .env.digitalocean.example | sed 's/=//' | sort -u)
```
Expected: empty output (every `${VAR...}` used by any compose file has a
matching line in the shared env template — no typo'd or missing var
names).

- [ ] **Step 2: Confirm the Caddyfile and app compose file agree on the proxied service/port**

Run:
```bash
grep 'reverse_proxy' Caddyfile
grep -A1 'expose:' docker-compose.app.yml
```
Expected: `reverse_proxy database-final:8000` in the Caddyfile, and
`"8000"` under `expose:` in `docker-compose.app.yml` — service name and
port match.

- [ ] **Step 3: Confirm central-service's build context resolves from its actual location**

Run:
```bash
cd /Users/dara/development/Final-CS394/central-service/deploy/digitalocean
ls ../../Dockerfile
```
Expected: file found (proves `context: ../..` in
`docker-compose.central.yml` is correct).

- [ ] **Step 4: Re-run all 5 compose validations together as a final gate**

```bash
cd /Users/dara/development/Final-CS394
POSTGRES_PASSWORD=dummy MONGO_PASSWORD=dummy REDIS_PASSWORD=dummy \
DATABASE_FINAL_APP_KEY=dummy CENTRAL_SERVICE_APP_KEY=dummy \
CENTRAL_SERVICE_API_KEY=dummy APP_DOMAIN=example.com \
  bash -c '
    set -e
    for f in postgres mongo redis app; do
      docker compose -f Database-final/deploy/digitalocean/docker-compose.$f.yml \
        --env-file Database-final/deploy/digitalocean/.env.digitalocean.example \
        config > /dev/null
      echo "$f: OK"
    done
    docker compose -f central-service/deploy/digitalocean/docker-compose.central.yml \
      --env-file Database-final/deploy/digitalocean/.env.digitalocean.example \
      config > /dev/null
    echo "central: OK"
  '
```
Expected: prints `postgres: OK`, `mongo: OK`, `redis: OK`, `app: OK`,
`central: OK` with no errors in between.

- [ ] **Step 5: Fix anything Steps 1–4 turned up, then commit if any files changed**

```bash
cd Database-final && git status --short
cd ../central-service && git status --short
```
If either shows changes (from fixes made in this step), commit them in
their respective repo with a message describing what was fixed. If both
are clean, no commit needed — this task was verification-only.
