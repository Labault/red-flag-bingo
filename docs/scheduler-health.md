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

## Production runtime compatibility

Both production Dockerfile stages pin FrankenPHP 1.12.7 / PHP 8.4 by the
multiarchitecture manifest digest from the previous successful deployment.
The moving `1-php8.4` tag selected FrankenPHP 1.13 / Mercure 1.0 on 14 October
2026. That hub rejected the existing top-level JWT directives and prevented
startup. Returning to the verified base preserves the current Mercure client
and token contract; a Mercure 1.0 migration needs a separate coordinated change.

After building, `deploy.sh` validates the actual image's Caddy configuration
in a disposable container before migrations or web replacement. It uses
synthetic keys, no network and no production volumes. An incompatible hub
configuration must fail at this gate while the existing web keeps running.
The probe is a configuration check, not a complete application readiness test.

For a future base update, resolve and review its digest, rerun this startup
check and verify Mercure client compatibility before deployment. The
[official Mercure upgrade guide](https://mercure.rocks/docs/UPGRADE) explains
the coordinated token and subscription changes required by version 1.0.
