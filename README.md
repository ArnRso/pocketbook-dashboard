# PocketBook Dashboard

Transforme une liseuse PocketBook (InkPad Color, écran E Ink 1404×1872) en panneau d'information :
météo du jour, pluie, temps de trajet en voiture avec le trafic Waze. Le pilotage de la domotique
via l'écran tactile viendra dans un second temps.

```
┌──────────────────────┐   GET /api/v1/screen (PNG)   ┌────────────────────────────────┐
│  Liseuse PocketBook  │ ◄─────────────────────────── │  Serveur Symfony (FrankenPHP)  │
│  app « généraliste » │   Authorization: Bearer …    │  widgets → HTML → Gotenberg    │
│  URL serveur + clé   │                              │  → PNG 16 niveaux de gris      │
└──────────────────────┘                              └────────────────────────────────┘
```

La liseuse ne fait qu'afficher une image : toute la logique est côté serveur. L'en-tête
`X-Refresh-Interval` de la réponse lui indique quand revenir chercher l'écran suivant.

## Organisation

| Dossier | Contenu |
|---|---|
| `server/` | Application Symfony 8.1 : widgets, API des liseuses, admin EasyAdmin |
| `device/` | Application de la liseuse (C, SDK PocketBook / InkView) |
| `.github/workflows/` | CI/CD : qualité et tests, image Docker, scan Trivy |

## Développement

Tout tourne dans Docker (Imagick et libcurl-impersonate sont nécessaires) :

```bash
cd server
docker compose up -d --build
docker compose exec app bin/console doctrine:migrations:migrate -n
docker compose exec app bin/console doctrine:fixtures:load -n
docker compose exec app bin/console app:user:create <identifiant>
```

L'admin est sur http://localhost:8080. Les données de démonstration contiennent une liseuse dont la clé
est `App\DataFixtures\AppFixtures::DEVICE_TOKEN`.

### Avant chaque push

```bash
docker compose exec app composer qa
```

C'est exactement ce que lance la CI : lint du conteneur et du YAML, PHP-CS-Fixer, PHPStan (niveau max,
règles strictes), lint Twig et PHPUnit (tests en ordre aléatoire, échec sur toute dépréciation ou test
risqué). `composer fix` corrige automatiquement le style.

### Conventions

- Contrôleurs et commandes très fins : toute la logique est dans des services.
- Pas de tableaux bruts : les réponses des API sont désérialisées dans des DTO par le Serializer.
- Les services lèvent des exceptions ; le rendu des widgets attrape celles qui sont affichables
  (`DisplayableException`) pour qu'une panne de Waze n'empêche pas d'afficher la météo.
- Un nouveau type de tuile = une sous-classe de `Tile` (ses réglages) déclarée dans `Tile::TYPES`, un
  widget `#[AsTaggedItem(XxxTile::WIDGET)]` implémentant `WidgetInterface`, et son template. Les tailles
  du template s'expriment en proportion de la tuile (`cqw`, `cqh`) pour s'adapter à toutes les tailles.

## Application de la liseuse

Une application C minimale (14 Ko) : elle se connecte au Wi-Fi, télécharge l'écran avec sa clé, l'affiche
en plein écran et revient le chercher après le délai annoncé par le serveur. Sur batterie, le Wi-Fi est
coupé entre deux écrans.

| Action | Effet |
|---|---|
| Appui court, touches page précédente/suivante | Rafraîchir maintenant |
| Appui long, touche Menu | Menu : rafraîchir, adresse du serveur, clé d'accès, quitter |
| Touche Retour | Quitter |

La configuration est dans `system/config/pocketbook-dashboard.cfg` sur la liseuse (modifiable par USB ou
depuis le menu de l'application) :

```
server_url = https://pocketbook.home-arnrso.com
token = pbd_…
```

En cas de problème, le journal est dans `system/pocketbook-dashboard.log`.

### Mise à jour automatique

Une fois par jour, après un rafraîchissement réussi, l'application compare sa version à celle de la release
`device-latest` (`version.txt` : commit et SHA-256). Si elle diffère, elle télécharge le nouveau binaire à côté
du sien, vérifie son empreinte et que c'est bien un exécutable ARM, le substitue puis redémarre dessus. La
version installée et une vérification immédiate sont accessibles depuis le menu. Les versions compilées en
local (`dev`) ne se mettent jamais à jour d'elles-mêmes.

### Compilation

La chaîne de compilation est une image Docker contenant le
[SDK officiel](https://github.com/pocketbook/SDK_6.3.0) (branche 6.5, plateforme B288) :

```bash
cd device
make docker-qa     # clang-format, cppcheck, tests natifs (sanitizers), compilation croisée
make install       # copie dist/pocketbook-dashboard.app sur la liseuse branchée en USB
```

La CI exécute le même `make qa` et publie l'application de `main`, zippée, dans la release
[device-latest](https://github.com/ArnRso/pocketbook-dashboard/releases/tag/device-latest).

## Administration

- **Dashboards** : un dashboard est une grille de 12 × 16 cases où se placent des tuiles (date et heure,
  météo actuelle, pluie, prévisions heure par heure, trajet). « Éditer la grille » permet de les ajouter,
  de les glisser et de les redimensionner à la souris, avec l'aperçu réel à côté. Chaque tuile a ses
  horaires d'affichage : deux tuiles peuvent occuper la même place si leurs horaires ne se recoupent pas
  (le trajet aller le matin, le retour le soir). L'éditeur montre les tuiles visibles au jour et à l'heure
  choisis.
- **Utilisateurs** : uniquement en ligne de commande. `app:user:create <identifiant>` génère et affiche
  un mot de passe ; relancée sur un utilisateur existant, elle en régénère un après confirmation.
- **Lieux et trajets** : dans l'admin. Un lieu sans coordonnées est géocodé à partir de son adresse.
- **Liseuses** : dans l'admin, chaque liseuse affiche le dashboard qui lui est assigné. « Générer une
  clé » affiche une seule fois la clé à saisir dans la liseuse ; seule son empreinte SHA-256 est stockée,
  et désactiver la liseuse lui retire l'accès.

## Déploiement

La CI publie `arnrso/pocketbook-dashboard:latest` sur Docker Hub à chaque push sur `main`. La stack de
production (application + Gotenberg) est décrite dans le dépôt Ansible, rôle `docker-ct`, et Watchtower
la met à jour dans les 5 minutes qui suivent la publication d'une image. Les migrations sont jouées au
démarrage du conteneur ; la base SQLite vit dans `/opt/stacks/pocketbook_dashboard/data`.

Pangolin publie l'application sans SSO : la liseuse s'authentifie avec sa clé et l'admin avec la
connexion de l'application.

## Waze

Waze n'a pas d'API publique : le client utilise celle de sa carte en ligne, qui refuse toute requête
dont l'empreinte TLS n'est pas celle d'un Chrome récent. L'image Docker charge donc
[libcurl-impersonate](https://github.com/lexiforest/curl-impersonate) à la place de libcurl.
Si le widget trajet affiche « Waze a refusé la requête (HTTP 403) », mettre à jour
`CURL_IMPERSONATE_VERSION` et `CURL_IMPERSONATE_PROFILE` dans `server/Dockerfile`.
