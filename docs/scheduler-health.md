# Scheduler health

The production scheduler reuses the web image but consumes `scheduler_default`
instead of serving HTTP. Its Compose healthcheck overrides the image's HTTP
probe and checks PID 1's command line for that specific Messenger consumer.
Another PHP command or consumer does not satisfy the check.

The probe runs every 30 seconds, with a three-second timeout, three retries and
a ten-second startup grace period. It checks process liveness, not completion
of every scheduled message or database availability. The web and database keep
their own probes.

For a healthcheck-only correction on the VPS, validate the production Compose
file, then recreate only the scheduler using the existing image:

```sh
docker compose --env-file .env.prod.local -f compose.prod.yaml config --quiet
docker compose --env-file .env.prod.local -f compose.prod.yaml up -d \
    --no-deps --no-build --pull never --force-recreate --wait \
    --wait-timeout 90 scheduler
docker inspect --format '{{.State.Health.Status}}' rfb_scheduler
```

Expected result: `healthy`, with PID 1 consuming `scheduler_default`. No web,
database or shared-proxy replacement is needed. The hourly worker restart
from `--time-limit=3600` remains normal behaviour. A healthcheck failure signals
an alert; Docker does not automatically restart a running unhealthy container.
