#!/usr/bin/env bash
set -u

interval="${MAILGUN_SYNC_INTERVAL:-${SYNC_INTERVAL_SECONDS:-20}}"
target="${LARAVEL_INGEST_URL:-http://autocare:8000/api/internal/mailgun/messages}"

echo "Mailgun handler started. Syncing every ${interval}s to ${target}"

while true; do
  if /opt/mailgun/sync-messages.sh; then
    echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) sync ok"
  else
    echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) sync failed" >&2
  fi

  sleep "$interval"
done
