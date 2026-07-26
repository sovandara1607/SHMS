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
- `DATABASE_FINAL_APP_URL` — the public hostname from step 7, e.g. `https://smarthospital.sovandara.lol`. LAN access via `http://192.168.0.100:8000` keeps working either way.
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

Then open `http://192.168.0.100:8000` in a browser on your LAN and log in — confirm it works before moving on to step 7.

## 7. Public HTTPS access via Cloudflare Tunnel

Gives you `https://smarthospital.sovandara.lol` reachable from anywhere, with no router port-forwarding and no separate TLS cert to manage — Cloudflare terminates HTTPS at their edge and tunnels the connection back to `database-final:8000` over the Docker network.

**One-time setup, in the Cloudflare dashboard** (not on the server):

1. Go to [one.dash.cloudflare.com](https://one.dash.cloudflare.com) → **Networks → Tunnels → Create a tunnel**.
2. Choose **Cloudflared**, name it `smart-hospital`, click **Save tunnel**.
3. On the "Install and run a connector" step, pick **Docker** — it shows a command containing a long `--token eyJ...` value. Copy just that token string.
4. Click **Next**, then under **Public Hostname**:
   - Subdomain: `smarthospital`
   - Domain: `sovandara.lol`
   - Service Type: `HTTP`
   - URL: `database-final:8000`
   - Save the hostname.
5. Back on the server, add the token to `.env.production`:
   ```bash
   echo "CLOUDFLARE_TUNNEL_TOKEN=<paste the token here>" >> .env.production
   ```
6. Start the tunnel (it's already in `docker-compose.production.yml`):
   ```bash
   docker compose -f docker-compose.production.yml --env-file .env.production up -d cloudflared
   ```
7. In the dashboard, the tunnel should flip to **Healthy** within a few seconds. Visit `https://smarthospital.sovandara.lol` from anywhere — no VPN, no port forwarding.

If you haven't already, also set `DATABASE_FINAL_APP_URL=https://smarthospital.sovandara.lol` in `.env.production` and re-run the `up -d --build` command from step 4 so it picks up the change.

## Updating after a code change

```bash
git pull
docker compose -f docker-compose.production.yml --env-file .env.production up -d --build
```

(Pull the latest `central-service` too if it changed, from its own directory.)

## Notes

- Postgres/MongoDB/Redis have no host ports published by default — only reachable by the app containers on the compose network, not from outside the server. Uncomment a `ports:` block in `docker-compose.production.yml` if you need direct access for debugging.
- `central-service` also has no host port published — Database-final reaches it at `http://central-service:8100` over the compose network. This means the pharmacist/lab async flows (PDF generation, audit log, medical record versioning) only work while `central-service`'s three processes (`serve`, `bus:relay`, `queue:work`, all managed by `supervisord` inside its container) are running — check `docker compose -f docker-compose.production.yml logs central-service` if lab reports aren't generating.
- The `cloudflared` service (step 7) is the intended way to expose this outside your home network — it doesn't need port 8000 opened on your router at all, since it dials *out* to Cloudflare rather than accepting inbound connections directly. Skip it if LAN-only access is all you need; just delete/comment out the `cloudflared` service and don't set `CLOUDFLARE_TUNNEL_TOKEN`.
