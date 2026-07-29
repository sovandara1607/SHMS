# Smart Hospital: DigitalOcean 5-droplet deployment

## Context

The Smart Hospital system (Database-final + central-service, Postgres, MongoDB,
Redis) currently deploys as a single Docker Compose project on one server, with
a Cloudflare Tunnel providing public HTTPS access (see `DEPLOYMENT.md`).

The user has provisioned 5 separate DigitalOcean droplets, one per component,
and wants a deployment that spreads the stack across them instead of running
everything on one box.

## Droplets

| Role | Public IP | Runs |
|---|---|---|
| `app1` | 143.198.85.24 | Caddy (reverse proxy, automatic Let's Encrypt TLS) + Database-final |
| `centralservice-api-server` | 188.166.236.41 | central-service (serve + bus:relay + queue:work under supervisord) |
| `postgres-db` | 165.22.242.44 | Postgres |
| `mongodb` | 159.65.14.101 | MongoDB |
| `redis-db` | 159.65.9.74 | Redis |

OS: Ubuntu 24.04, Docker not yet installed on any of them.

VPC/private networking status is unknown. The doc will open with a check
(`ip -4 addr show eth1` on each droplet) for an existing private interface. If
present, private IPs are used for inter-droplet traffic instead of public
ones. If absent, public IPs are used, locked down by DigitalOcean Cloud
Firewalls.

## Networking model

No shared Docker network exists across droplets (unlike the current
single-host Compose setup), so isolation moves to DigitalOcean Cloud
Firewalls, one per droplet:

- **app1**: inbound 80/443 from `0.0.0.0/0`, 22 from admin IP. This is the
  only droplet reachable from the open internet on app ports.
- **centralservice-api-server**: inbound 8100 from `app1` only, 22 from admin
  IP.
- **postgres-db**: inbound 5432 from `app1` and `centralservice-api-server`
  only, 22 from admin IP.
- **mongodb**: inbound 27017 from `app1` and `centralservice-api-server`
  only, 22 from admin IP.
- **redis-db**: inbound 6379 from `app1` and `centralservice-api-server`
  only, 22 from admin IP.

Caveat (documented, not solved): without a VPC, this traffic crosses the
public internet — firewalled by source IP but not encrypted in transit. Noted
as a follow-up (attach droplets to a VPC) rather than implemented now, to
keep scope matched to what was asked (a working deployment, not a
zero-trust network).

## TLS

Caddy container on `app1`. Caddyfile reverse-proxies `YOUR_DOMAIN` (a
placeholder the user fills in once DNS is set) to the `database-final`
container's port 8000 on the same droplet, and gets a Let's Encrypt cert
automatically on first request once the A record resolves.

## File layout

New files, split so each droplet only needs what it runs:

```
Database-final/
  deploy/digitalocean/
    docker-compose.app.yml       # caddy + database-final (build: context ".")
    Caddyfile                    # reverse_proxy YOUR_DOMAIN -> database-final:8000
    docker-compose.postgres.yml  # official postgres image, no source needed
    docker-compose.mongo.yml     # official mongo image, no source needed
    docker-compose.redis.yml     # official redis image, no source needed
    .env.digitalocean.example    # one shared template; same filled-in file
                                  # copied to all 5 droplets identically
  DEPLOYMENT-DIGITALOCEAN.md     # the walkthrough

central-service/
  deploy/digitalocean/
    docker-compose.central.yml   # central-service (build: context ".")
```

`postgres-db`, `mongodb`, `redis-db` never need a git clone of application
source — their compose files (official images only) are copied via `scp`
from the user's machine. `app1` needs `Database-final` cloned (build
context). `centralservice-api-server` needs `central-service` cloned (build
context).

Env vars that pointed at Docker Compose service names in the original
single-host file (e.g. `DB_HOST: postgres`) become the target droplet's
IP (private if available, else public) in `.env.digitalocean.example`.

## Deployment doc structure (`DEPLOYMENT-DIGITALOCEAN.md`)

1. Droplet table + prerequisites (SSH access, a domain to point at `app1`)
2. Check for private networking (`ip -4 addr show eth1`); note which IP set
   (private/public) to use for the rest of the doc
3. Install Docker Engine + Compose plugin on all 5 droplets (same command
   over SSH on each — `get.docker.com` convenience script)
4. Create the 5 DigitalOcean Cloud Firewalls (dashboard steps) per the rules
   above
5. Fill in `.env.digitalocean` from the example (secrets via `openssl rand`,
   same pattern as the existing `DEPLOYMENT.md`); copy identically to all 5
   droplets
6. Bring up the data tier: `postgres-db`, `mongodb`, `redis-db` (order-
   independent, can be done in parallel)
7. Bring up `centralservice-api-server` (clone central-service, compose up)
8. Bring up `app1` (clone Database-final, compose up — Caddy + app)
9. Point DNS (`YOUR_DOMAIN` A record → 143.198.85.24)
10. Run migrations from `app1`
    (`docker compose exec database-final php artisan migrate --force`)
11. Verify: curl checks per droplet, then browser login check via
    `https://YOUR_DOMAIN`
12. Notes: security caveat (public-IP vs VPC), update workflow (`git pull` +
    rebuild independently per droplet, in the same dependency order as
    initial bring-up)

## Out of scope

- Terraform/doctl provisioning (droplets already exist)
- TLS-in-transit for Postgres/Mongo/Redis protocols
- Load balancing / multi-node redundancy for any single role
- Automated CI/CD for deploys (this is a manual SSH walkthrough, matching
  the existing `DEPLOYMENT.md`'s style)
