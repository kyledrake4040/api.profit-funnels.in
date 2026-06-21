# Deploying the Profit Funnel engine

The engine ships **dormant** on `main` behind `FUNNEL_ENABLED`. Nothing posts,
nothing is charged, and `storage/funnel/run.log` stays empty until you set
`FUNNEL_ENABLED=true`. This directory has everything needed to run it.

> The artifacts here run **only** the funnel engine (`bin/funnel`) on a
> schedule — they do not stand up the legacy Laravel web app. The engine is
> self-contained PHP 8.2 and writes its queue/attribution to `storage/funnel/`.

## What runs

Laravel's scheduler (`routes/console.php`) runs **`php artisan funnel:run`** every
5 minutes — it publishes any **due** posts and appends output to
`storage/funnel/run.log` — plus a weekly `funnel:report`. You drive the scheduler
one of two ways below. (`bin/funnel` remains for manual, framework-free use.)

## Configuration

Copy `.env.funnel.example` values into the environment (see that file for the
full list). At minimum to go live:

| Variable | Purpose |
| --- | --- |
| `FUNNEL_ENABLED` | **Must be `true`** to activate. Default `false`. |
| `FUNNEL_*_ENDPOINT` / `FUNNEL_*_TOKEN` | GoHighLevel posting creds per platform (tiktok/instagram/youtube/gbp). Without them the engine dry-runs. |
| `FUNNEL_POSTS_PER_DAY` | Social cadence/day (default `2`, hard-capped at 5). |

## Option A — $5 droplet (cron)

```bash
git clone <repo> /var/www/api.profit-funnels.in
cd /var/www/api.profit-funnels.in
composer install --no-dev

# edit APP_DIR at the top of deploy/funnel.crontab if your path differs
crontab deploy/funnel.crontab   # one line: * * * * * php artisan schedule:run

# queue a few days of content once
FUNNEL_ENABLED=true php bin/funnel schedule 3
FUNNEL_ENABLED=true php bin/funnel build
```

The single `schedule:run` cron drives Laravel's scheduler, which drains the
queue into `storage/funnel/run.log` every 5 minutes.

## Option B — Railway / Fly / any Docker host

`deploy/Dockerfile` runs `php artisan schedule:work` in the foreground (the
scheduler fires `funnel:run` every 5 minutes); `deploy/railway.json` points
Railway at it.

```bash
docker build -f deploy/Dockerfile -t funnel .
docker run -e FUNNEL_ENABLED=true \
           -e FUNNEL_TIKTOK_ENDPOINT=... -e FUNNEL_TIKTOK_TOKEN=... \
           -v "$PWD/storage/funnel:/app/storage/funnel" funnel
```

On Railway: set the same variables in the service, deploy from `deploy/railway.json`.

## Webhooks (attribution)

The GoHighLevel + QuickBooks webhook controllers live in the Laravel app
(`POST /api/funnel/webhooks/gohighlevel` and `/quickbooks`). Point those
providers at your deployed API host so leads and revenue are recorded, then:

```bash
php bin/funnel report --days=7
```

shows leads & revenue split by `utm_source=funnel` vs. other.

## Activation checklist

1. Set `FUNNEL_ENABLED=true` and the GHL endpoint/token vars.
2. `php bin/funnel schedule 3 && php bin/funnel build` to seed the queue.
3. Start the cron (Option A) or container (Option B).
4. Confirm `storage/funnel/run.log` is filling up.
5. Wire the GHL/QuickBooks webhooks; verify with `bin/funnel report --days=7`.
