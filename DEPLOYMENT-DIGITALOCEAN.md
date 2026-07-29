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
