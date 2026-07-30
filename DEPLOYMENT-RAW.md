# Deploying to 5 DigitalOcean droplets — raw/native (no Docker)

Same 5-droplet layout as `DEPLOYMENT-DIGITALOCEAN.md`, but every service runs
as a native OS process managed by systemd instead of a Docker container.
Read that doc's droplet table first if you haven't already — it's the same
here:

| Role | IP | Runs (native) |
|---|---|---|
| app1 | 143.198.85.24 | Caddy (TLS) + PHP-FPM (Database-final) |
| centralservice-api-server | 188.166.236.41 | Nginx + PHP-FPM (central-service) + 2 systemd units |
| postgres-db | 165.22.242.44 | PostgreSQL 18 |
| mongodb | 159.65.14.101 | MongoDB 7 |
| redis-db | 159.65.9.74 | Redis 7 |

**This assumes Ubuntu 22.04 or 24.04 LTS on all 5 droplets** (`lsb_release -a`
to confirm). Everything below uses `apt`; adjust if that's wrong.

**This is a migration, not a fresh install** — it replaces the currently
running Docker deployment in place, on the same droplets, with no fallback
droplet. The whole point of the step ordering below is: **never remove the
working Docker version of a service until its native replacement is proven
to work with the real data.** Don't skip the verification step between
"install native" and "decommission Docker" on any droplet.

## 0. Before touching anything: back up the data tier

From your own machine, or directly on each data-tier droplet:

**Postgres** (on postgres-db, 165.22.242.44):
```bash
docker exec smart-hospital-postgres-postgres-1 \
  pg_dump -U smart_hospital -d smart_hospital -F c -f /tmp/smart_hospital.dump
docker cp smart-hospital-postgres-postgres-1:/tmp/smart_hospital.dump ./smart_hospital.dump
# copy it somewhere off this droplet — your own machine, a DO Space, etc.
scp root@165.22.242.44:/root/smart_hospital.dump .
```
(Container name may differ — `docker ps` to confirm; Compose typically names
it `<project>-<service>-1`.)

**MongoDB** (on mongodb, 159.65.14.101):
```bash
docker exec smart-hospital-mongo-mongo-1 \
  mongodump --username smart_hospital --password '<MONGO_PASSWORD>' \
  --authenticationDatabase admin --archive=/tmp/mongo.archive
docker cp smart-hospital-mongo-mongo-1:/tmp/mongo.archive ./mongo.archive
scp root@159.65.14.101:/root/mongo.archive .
```

**Redis**: no backup needed. Everything in it (sessions, cache, the bus
queue) is designed to be disposable — see `analysis.md`/the Redis
reliability work in this repo for why. The one thing worth checking before
decommissioning it later is that the bus queue is empty (step 5 below), so
no in-flight audit-log/version-sync job is silently dropped.

Do not proceed past this point until you have both dump files safely off
their source droplets.

## 1. postgres-db (165.22.242.44) — install native PostgreSQL 18

```bash
ssh root@165.22.242.44

apt update
apt install -y curl ca-certificates gnupg
install -d /usr/share/postgresql-common/pgdg
curl -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
  --fail https://www.postgresql.org/media/keys/ACCC4CF8.asc
echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] \
  https://apt.postgresql.org/pub/repos/apt $(. /etc/os-release && echo $VERSION_CODENAME)-pgdg main" \
  > /etc/apt/sources.list.d/pgdg.list
apt update
apt install -y postgresql-18
```

Create the database/user (same credentials as `.env.digitalocean` used —
copy them out of that file before running this):

```bash
sudo -u postgres psql -c "CREATE USER smart_hospital WITH PASSWORD '<POSTGRES_PASSWORD>';"
sudo -u postgres psql -c "CREATE DATABASE smart_hospital OWNER smart_hospital;"
sudo -u postgres psql -d smart_hospital -c "CREATE EXTENSION IF NOT EXISTS pg_trgm;"
```

Restore the dump taken in step 0:

```bash
scp ./smart_hospital.dump root@165.22.242.44:/root/
pg_restore -U smart_hospital -d smart_hospital --no-owner -h 127.0.0.1 /root/smart_hospital.dump
```

Allow remote connections — edit `/etc/postgresql/18/main/postgresql.conf`:
```
listen_addresses = '*'
```
and append to `/etc/postgresql/18/main/pg_hba.conf` (use the private IPs
instead if the droplets share a VPC, same note as in
`DEPLOYMENT-DIGITALOCEAN.md` step 1):
```
host    smart_hospital    smart_hospital    143.198.85.24/32    scram-sha-256
host    smart_hospital    smart_hospital    188.166.236.41/32   scram-sha-256
```
```bash
systemctl restart postgresql
```

**Verify before going further:**
```bash
psql -h 127.0.0.1 -U smart_hospital -d smart_hospital -c "SELECT count(*) FROM patient;"
```
Confirm the count matches what you expect from the live system (or compare
against `SELECT count(*) FROM patient;` run against the *old* Docker
container, before you touch it).

**Only once that's confirmed correct**, decommission Docker on this droplet:
```bash
docker compose -f docker-compose.postgres.yml down
systemctl disable --now docker  # or: apt remove docker-ce docker-ce-cli containerd.io
```

The Cloud Firewall rule for this droplet (from `DEPLOYMENT-DIGITALOCEAN.md`
step 3) doesn't need to change — it already scopes port 5432 to app1 and
centralservice-api-server's IPs.

## 2. mongodb (159.65.14.101) — install native MongoDB 7

```bash
ssh root@159.65.14.101

apt update
apt install -y gnupg curl
curl -fsSL https://pgp.mongodb.com/server-7.0.asc | \
  gpg -o /usr/share/keyrings/mongodb-server-7.0.gpg --dearmor
echo "deb [signed-by=/usr/share/keyrings/mongodb-server-7.0.gpg] \
  http://repo.mongodb.org/apt/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME)/mongodb-org/7.0 multiverse" \
  > /etc/apt/sources.list.d/mongodb-org-7.0.list
apt update
apt install -y mongodb-org
systemctl enable --now mongod
```

Create the admin/app user (mongod starts with no auth enabled yet, so this
works without credentials the first time):
```bash
mongosh --eval '
db.getSiblingDB("admin").createUser({
  user: "smart_hospital",
  pwd: "<MONGO_PASSWORD>",
  roles: [{ role: "readWrite", db: "smart_hospital_docs" }]
})'
```

Restore the dump from step 0:
```bash
scp ./mongo.archive root@159.65.14.101:/root/
mongorestore --username smart_hospital --password '<MONGO_PASSWORD>' \
  --authenticationDatabase admin --archive=/root/mongo.archive
```
(`mongorestore` recreates the indexes defined in
`database/mongodb/collections.js` automatically — no manual re-indexing
needed.)

Enable auth and allow remote connections — edit `/etc/mongod.conf`:
```yaml
net:
  bindIp: 0.0.0.0
security:
  authorization: enabled
```
```bash
systemctl restart mongod
```

**Verify before going further:**
```bash
mongosh --username smart_hospital --password '<MONGO_PASSWORD>' \
  --authenticationDatabase admin \
  --eval 'db.getSiblingDB("smart_hospital_docs").audit_log_documents.countDocuments()'
```

**Only once confirmed**, decommission Docker:
```bash
docker compose -f docker-compose.mongo.yml down
systemctl disable --now docker
```

## 3. redis-db (159.65.9.74) — install native Redis 7

```bash
ssh root@159.65.9.74

apt update
apt install -y redis-server
```

Edit `/etc/redis/redis.conf`:
```
bind 0.0.0.0
requirepass <REDIS_PASSWORD>
protected-mode yes
```
```bash
systemctl restart redis-server
systemctl enable redis-server
```

**Verify:**
```bash
redis-cli -a '<REDIS_PASSWORD>' --no-auth-warning ping
```

**Before decommissioning Docker**, drain-check the bus queue on the *old*
container so nothing in flight gets silently lost:
```bash
docker exec smart-hospital-redis-redis-1 redis-cli -a '<REDIS_PASSWORD>' --no-auth-warning \
  llen central-service:jobs
```
If non-zero, wait a few seconds and check again (central-service's
`bus:relay` should be draining it) before proceeding.

```bash
docker compose -f docker-compose.redis.yml down
systemctl disable --now docker
```

## 4. centralservice-api-server (188.166.236.41) — PHP-FPM + Nginx

```bash
ssh root@188.166.236.41

apt update
apt install -y software-properties-common curl git nginx
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y php8.4-fpm php8.4-cli php8.4-pgsql php8.4-mbstring \
  php8.4-xml php8.4-zip php8.4-intl php8.4-bcmath php8.4-opcache php8.4-curl

# mongodb/redis extensions aren't in the ondrej PPA package set — install
# via PECL, same two extensions the Docker image installs via
# mlocati/php-extension-installer.
apt install -y php-pear php8.4-dev pkg-config libssl-dev
pecl install mongodb redis
echo "extension=mongodb.so" > /etc/php/8.4/mods-available/mongodb.ini
echo "extension=redis.so" > /etc/php/8.4/mods-available/redis.ini
phpenmod mongodb redis

curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
```

Deploy the app:
```bash
git clone https://github.com/sovandara1607/SHMS-Central-service.git /var/www/central-service
cd /var/www/central-service
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp deploy/raw/central-service.env .env
# edit .env: fill in APP_KEY (php artisan key:generate --show), DB/MONGO/
# REDIS hosts + passwords, CENTRAL_SERVICE_API_KEY — same values as the
# Docker deployment's .env.digitalocean used.
php artisan key:generate
chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache && php artisan route:cache
```

PHP-FPM pool (`Database-final/deploy/raw/php-fpm-pool.conf` — this template
lives in the Database-final repo since it's shared by both app droplets;
central-service's droplet has no reason to have that repo checked out, so
`scp` it over from your own machine, where both repos are cloned per the
Prerequisites):
```bash
# from your own machine:
scp Database-final/deploy/raw/php-fpm-pool.conf \
  root@188.166.236.41:/etc/php/8.4/fpm/pool.d/central-service.conf
# back on centralservice-api-server:
sed -i 's/POOL_NAME/central-service/g' /etc/php/8.4/fpm/pool.d/central-service.conf
systemctl restart php8.4-fpm
```

Nginx site (`deploy/raw/nginx-central-service.conf` in this repo):
```bash
cp deploy/raw/nginx-central-service.conf /etc/nginx/sites-available/central-service
ln -sf /etc/nginx/sites-available/central-service /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

The two background processes (`deploy/raw/central-service-bus-relay.service`
and `deploy/raw/central-service-queue-work.service` in this repo — replace
supervisord's two non-serve programs):
```bash
cp deploy/raw/central-service-bus-relay.service /etc/systemd/system/
cp deploy/raw/central-service-queue-work.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now central-service-bus-relay central-service-queue-work
```

**Verify before touching app1:**
```bash
curl -s http://localhost:8100/api/health
systemctl status central-service-bus-relay central-service-queue-work
```
(app1 will briefly be unable to reach central-service during this droplet's
cutover — expected, short, matches the dependency order already used when
*updating* the Docker deployment in `DEPLOYMENT-DIGITALOCEAN.md`.)

**Once confirmed**, decommission Docker:
```bash
docker compose -f deploy/digitalocean/docker-compose.central.yml down
systemctl disable --now docker
```

## 5. app1 (143.198.85.24) — PHP-FPM + Caddy

```bash
ssh root@143.198.85.24

apt update
apt install -y software-properties-common curl git debian-keyring debian-archive-keyring apt-transport-https
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y php8.4-fpm php8.4-cli php8.4-pgsql php8.4-mbstring \
  php8.4-xml php8.4-zip php8.4-intl php8.4-bcmath php8.4-opcache php8.4-curl \
  php-pear php8.4-dev pkg-config libssl-dev
pecl install mongodb redis
echo "extension=mongodb.so" > /etc/php/8.4/mods-available/mongodb.ini
echo "extension=redis.so" > /etc/php/8.4/mods-available/redis.ini
phpenmod mongodb redis

curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# Caddy's official repo
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | \
  gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | \
  tee /etc/apt/sources.list.d/caddy-stable.list
apt update
apt install -y caddy
```

Deploy the app:
```bash
git clone https://github.com/sovandara1607/SHMS.git /var/www/database-final
cd /var/www/database-final
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp deploy/raw/database-final.env .env
# edit .env: APP_KEY, DB/MONGO/REDIS hosts + passwords, CENTRAL_SERVICE_*,
# APP_URL=https://YOUR_DOMAIN — same values the Docker deployment used.
php artisan key:generate
php artisan storage:link --force
chown -R www-data:www-data storage bootstrap/cache
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

PHP-FPM pool (same template, `POOL_NAME` → `database-final`):
```bash
cp deploy/raw/php-fpm-pool.conf /etc/php/8.4/fpm/pool.d/database-final.conf
sed -i 's/POOL_NAME/database-final/g' /etc/php/8.4/fpm/pool.d/database-final.conf
systemctl restart php8.4-fpm
```

Caddy (`deploy/raw/Caddyfile` in this repo):
```bash
cp deploy/raw/Caddyfile /etc/caddy/Caddyfile
# systemctl edit creates a drop-in override — the reliable, package-
# version-independent way to inject an env var into caddy.service,
# rather than assuming a specific EnvironmentFile path the packaged unit
# may or may not read by default.
systemctl edit caddy
```
In the editor that opens, add:
```ini
[Service]
Environment="APP_DOMAIN=YOUR_DOMAIN"
```
```bash
systemctl restart caddy
```

**Verify locally on the droplet first:**
```bash
curl -sI http://localhost/login  # Caddy will redirect to https itself
```

**Then from outside:**
```bash
curl -sI https://YOUR_DOMAIN/login | head -1
```
Open `https://YOUR_DOMAIN` in a real browser, confirm the cert is valid (DNS
didn't change — same IP as before — so this should be fast, not a real
outage), and log in.

**Once fully confirmed working**, decommission Docker:
```bash
docker compose -f deploy/digitalocean/docker-compose.app.yml down
systemctl disable --now docker
```

## 6. Final cleanup

Only after all 5 droplets are verified running natively:
```bash
# on each droplet, if you want Docker fully removed rather than just disabled:
apt remove -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

## Updating after a code change

```bash
# centralservice-api-server:
cd /var/www/central-service && git pull && composer install --no-dev --optimize-autoloader \
  && npm ci && npm run build && php artisan config:cache && php artisan route:cache \
  && systemctl restart php8.4-fpm central-service-bus-relay central-service-queue-work

# app1:
cd /var/www/database-final && git pull && composer install --no-dev --optimize-autoloader \
  && npm ci && npm run build && php artisan migrate --force \
  && php artisan config:cache && php artisan route:cache && php artisan view:cache \
  && systemctl restart php8.4-fpm
```

## Rollback

Because Docker isn't removed until each droplet's native replacement is
independently verified (steps 1–5 above), the practical rollback window is
per-droplet and closes the moment you run that droplet's `docker compose
down` — before that point, rolling back is just `systemctl stop <the new
native service>` and leaving the still-running Docker container as-is. This
is the whole reason the plan verifies before decommissioning rather than
migrating and decommissioning in the same step.
