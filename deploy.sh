#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

# Contrat de déploiement attendu par le webhook push-to-deploy : le dispatcher
# fait `git reset --hard origin/main` puis lance CE ./deploy.sh à la racine du
# repo. Il DOIT donc vivre ici (pas sous scripts/ ni documenté dans DEPLOY.md)
# et se localiser lui-même via `cd "$(dirname "$0")"`.

COMPOSE_FILE="compose.prod.yaml"
ENV_FILE=".env.prod.local"
HEALTH_RETRIES=20

dc()  { docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" "$@"; }
log() { echo "[$(date '+%H:%M:%S')] [rfb] $*"; }

[ -f "$ENV_FILE" ] || { log "ERREUR : $ENV_FILE introuvable"; exit 1; }

log "Build des images…"
dc build --pull

log "Migrations Doctrine…"
dc run --rm app bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

log "(Re)démarrage des conteneurs…"
dc up -d --remove-orphans

# Le volume persistant app_var monte /app/var par-dessus le cache compilé par le
# Dockerfile (cache:warmup). En prod Twig ne revérifie pas la source
# (auto_reload off) → sans ce clear, le cache obsolète du volume masque toute
# modif de template/config. On vide + réchauffe donc le cache dans le conteneur
# en cours d'exécution, après le mount du volume.
log "Vidage du cache prod (volume app_var masque le warmup du build)…"
dc exec -T app bin/console cache:clear --env=prod --no-debug
dc exec -T app bin/console cache:warmup --env=prod --no-debug

# Pas de route /health dédiée : on vérifie que la home répond 200 (l'image
# runtime embarque curl, cf HEALTHCHECK du Dockerfile.prod).
log "Healthcheck → http://localhost/"
for i in $(seq 1 "$HEALTH_RETRIES"); do
  if dc exec -T app curl -fsS -o /dev/null http://localhost/; then
    log "Healthy ✓"
    docker image prune -f >/dev/null 2>&1 || true
    log "Déploiement terminé ✓"
    exit 0
  fi
  sleep 3
done

log "ÉCHEC : l'app ne répond pas après $((HEALTH_RETRIES * 3))s ✗"
dc ps
exit 1
