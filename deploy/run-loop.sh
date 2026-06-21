#!/usr/bin/env bash
#
# Profit Funnel scheduler loop.
#
# Publishes any due posts every FUNNEL_RUN_INTERVAL seconds (default 300 = 5
# min) and appends output to storage/funnel/run.log — the file the weekly
# monitoring cron reads. Use this as a container CMD (Railway/Fly/Docker) when
# you don't have a real crontab; on a droplet prefer deploy/funnel.crontab.
#
# While FUNNEL_ENABLED is not "true", bin/funnel exits cleanly each tick, so the
# loop is a safe no-op until you flip the flag.
set -euo pipefail

cd "$(dirname "$0")/.."

INTERVAL="${FUNNEL_RUN_INTERVAL:-300}"
LOG="storage/funnel/run.log"
mkdir -p "$(dirname "$LOG")"

echo "[funnel] run-loop started (interval=${INTERVAL}s, FUNNEL_ENABLED=${FUNNEL_ENABLED:-false})" >> "$LOG"

while true; do
    {
        echo "----- $(date -u '+%Y-%m-%dT%H:%M:%SZ') -----"
        php bin/funnel run
    } >> "$LOG" 2>&1 || echo "[funnel] run failed at $(date -u)" >> "$LOG"

    sleep "$INTERVAL"
done
