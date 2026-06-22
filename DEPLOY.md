# 1. Sur ton Mac : commit et push
git add .
git commit -m "feat: <description>"
git push

# 2. Sur le VPS
ssh thibault@TON_IP
cd /srv/redflagbingo
git pull

# 3. Si Dockerfile / composer.json / package.json a changé : rebuild
docker compose --env-file .env.prod.local -f compose.prod.yaml build app

# 4. Si migrations Doctrine
docker compose --env-file .env.prod.local -f compose.prod.yaml run --rm app bin/console doctrine:migrations:migrate --no-interaction

# 5. Recharger les conteneurs
docker compose --env-file .env.prod.local -f compose.prod.yaml up -d

# 6. Vérifier les logs
docker compose --env-file .env.prod.local -f compose.prod.yaml logs app -f --tail=50
