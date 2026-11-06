#!/usr/bin/env bash
set -euo pipefail

ENVIRONMENT="${1:-}"
COMPOSE_FILE="${2:-docker-compose.yml}"
IMAGE_TAG="${IMAGE_TAG:-$(git rev-parse --short HEAD)}"

if [[ -z "$ENVIRONMENT" ]]; then
  echo "Usage: $0 <staging|production> [compose-file]" >&2
  exit 1
fi

if [[ "$ENVIRONMENT" == staging ]]; then
  COMPOSE_ARGS=(-f docker-compose.yml -f docker-compose.staging.yml)
elif [[ "$ENVIRONMENT" == production ]]; then
  COMPOSE_ARGS=(-f docker-compose.yml)
else
  echo "Unsupported environment: $ENVIRONMENT" >&2
  exit 1
fi

export APP_IMAGE_TAG="$IMAGE_TAG"
docker compose "${COMPOSE_ARGS[@]}" build --pull app worker scheduler
docker compose "${COMPOSE_ARGS[@]}" up -d postgres redis minio
docker compose "${COMPOSE_ARGS[@]}" run --rm app php artisan migrate --force --no-interaction
docker compose "${COMPOSE_ARGS[@]}" up -d app worker scheduler nginx
docker compose "${COMPOSE_ARGS[@]}" exec -T app php artisan optimize
docker compose "${COMPOSE_ARGS[@]}" exec -T app php artisan queue:restart
