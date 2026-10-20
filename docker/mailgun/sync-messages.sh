#!/usr/bin/env bash
set -euo pipefail

require() {
  local name="$1"
  if [[ -z "${!name:-}" ]]; then
    echo "Missing required environment variable: ${name}" >&2
    exit 1
  fi
}

require MAILGUN_SECRET
require MAILGUN_DOMAIN
require MAIL_HANDLER_TOKEN

endpoint="${MAILGUN_ENDPOINT:-api.mailgun.net}"
endpoint="${endpoint#https://}"
endpoint="${endpoint#http://}"
endpoint="${endpoint%/}"

INGEST_URL="${LARAVEL_INGEST_URL:-http://autocare:8000/api/internal/mailgun/messages}"

workdir="$(mktemp -d)"
trap 'rm -rf "$workdir"' EXIT

events_file="${workdir}/events.json"
curl --proto '=https' --max-redirs 2 -fsS -u "api:${MAILGUN_SECRET}" \
  "https://${endpoint}/v3/${MAILGUN_DOMAIN}/events?limit=100" \
  -o "$events_file"

items_file="${workdir}/items.json"
printf '[]\n' > "$items_file"

while IFS= read -r row; do
  mailgun_id="$(jq -r '.message.headers["message-id"] // .id // empty' <<<"$row" | tr -d '<>')"
  if [[ -z "$mailgun_id" || "$mailgun_id" == "null" ]]; then
    continue
  fi

  body_file="${workdir}/body.json"
  printf '{}\n' > "$body_file"
  storage_url="$(jq -r '.storage.url // empty' <<<"$row")"
  if [[ -n "$storage_url" && "$storage_url" == https://* ]]; then
    curl --proto '=https' --max-redirs 2 -fsS -u "api:${MAILGUN_SECRET}" \
      "$storage_url" -o "$body_file" || printf '{}\n' > "$body_file"
  fi

  sent_at=""
  timestamp="$(jq -r '.timestamp // empty' <<<"$row")"
  if [[ -n "$timestamp" && "$timestamp" != "null" ]]; then
    sent_at="$(jq -nr --argjson ts "$timestamp" '($ts | floor | todate)')"
  fi

  item="$(jq -n \
    --argjson event "$row" \
    --slurpfile stored "$body_file" \
    --arg mailgun_id "$mailgun_id" \
    --arg sent_at "$sent_at" \
    '
    def split_addr($raw):
      if $raw == null or $raw == "" then {name: null, email: null}
      else
        ($raw | split(",")[0] | gsub("^\\s+|\\s+$"; "")) as $first
        | if ($first | test("<[^>]+>")) then
            {
              name: ($first | sub("\\s*<[^>]+>\\s*$"; "") | gsub("^\"|\"$"; "") | if . == "" then null else . end),
              email: ($first | capture("<(?<e>[^>]+)>").e)
            }
          else {name: null, email: $first}
          end
      end;
    ($stored[0] // {}) as $body
    | ($event.message.headers.from // $body.From // "") as $from_raw
    | ($event.message.headers.to // $body.To // "") as $to_raw
    | split_addr($from_raw) as $from
    | split_addr($to_raw) as $to
    | ($body["body-plain"] // $body["stripped-text"] // null) as $text
    | ($body["body-html"] // $body["stripped-html"] // null) as $html
    | {
        mailgun_id: $mailgun_id,
        subject: ($event.message.headers.subject // $body.Subject // null),
        from_email: $from.email,
        from_name: $from.name,
        to_email: $to.email,
        to_name: $to.name,
        text_body: (if ($text // "") == "" then null else $text[0:100000] end),
        html_body: (if ($html // "") == "" then null else $html[0:100000] end),
        sent_at: (if $sent_at == "" then null else $sent_at end),
        is_read: false,
        delivery_note: (if (($event.reject.reason // "") | length) > 0 then $event.reject.reason else null end)
      }
    ')"

  jq --argjson item "$item" '. + [$item]' "$items_file" > "${items_file}.next"
  mv "${items_file}.next" "$items_file"
done < <(jq -c '.items // [] | sort_by(.timestamp // 0) | .[]' "$events_file")

if [[ "$(jq 'length' "$items_file")" -eq 0 ]]; then
  echo "sync ok (0 messages)"
  exit 0
fi

jq '{messages: .}' "$items_file" > "${workdir}/payload.json"

curl --proto '=http,https' --max-redirs 2 -fsS \
  -X POST "$INGEST_URL" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "X-Mail-Handler-Token: ${MAIL_HANDLER_TOKEN}" \
  --data-binary @"${workdir}/payload.json"
echo
