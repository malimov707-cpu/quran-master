#!/usr/bin/env bash
set -euo pipefail

ENVIRONMENT="${1:-}"
IMAGE_TAG="${2:-}"

if [[ -z "$ENVIRONMENT" || -z "$IMAGE_TAG" ]]; then
  echo "Usage: $0 <staging|production> <image-tag>" >&2
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
docker compose "${COMPOSE_ARGS[@]}" up -d --force-recreate app worker scheduler nginx
docker compose "${COMPOSE_ARGS[@]}" exec -T app php artisan optimize
