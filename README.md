# Red Flag Bingo

## Architecture prod

En production, redflagbingo tourne **derrière un reverse proxy Caddy global externe**
qui mutualise les ports 80/443 sur le VPS et termine TLS pour plusieurs applications.

Conséquences pour ce projet :

- FrankenPHP n'écoute qu'en **HTTP plain sur le port 80 du réseau Docker interne**.
  Il ne gère plus TLS, ne demande plus de certificat Let's Encrypt, et **n'expose
  aucun port sur l'hôte** (la section `ports:` du service `app` a été retirée).
- Les headers de sécurité (`Strict-Transport-Security`, `X-Content-Type-Options`,
  `X-Frame-Options`, `Referrer-Policy`, etc.) sont ajoutés par le **Caddy global**,
  pas par FrankenPHP — pour éviter les doublons.
- Le service `app` est branché sur deux réseaux Docker :
  - `web` (externe, partagé avec le Caddy global) — c'est par là que le proxy joint l'app.
  - `internal` (interne au projet) — pour parler à `database` et `scheduler`.
- `database` et `scheduler` ne sont que sur `internal`, donc invisibles du Caddy global.

### Prérequis sur le VPS

Le réseau Docker `web` doit exister **avant** de démarrer la stack :

```bash
docker network create web
```

Le Caddy global doit lui aussi être attaché à ce réseau `web`, et sa config
doit faire un `reverse_proxy` vers `rfb_app:80` (le nom du conteneur sur le
réseau Docker).

### Variables d'environnement

`LETSENCRYPT_EMAIL` est conservée dans `.env.prod.local.dist` pour compatibilité
descendante mais n'est plus consommée par la stack — la gestion des certificats
est déléguée au Caddy global.
