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
- Un nouveau widget = une classe `#[AsTaggedItem('nom')]` implémentant `WidgetInterface` + un template,
  puis `{{ widget('nom') }}` dans `templates/screen/screen.html.twig`.

## Administration

- **Utilisateurs** : uniquement en ligne de commande. `app:user:create <identifiant>` génère et affiche
  un mot de passe ; relancée sur un utilisateur existant, elle en régénère un après confirmation.
- **Lieux et trajets** : dans l'admin. Un lieu sans coordonnées est géocodé à partir de son adresse.
  Le dashboard affiche le premier trajet actif dont la plage horaire contient l'heure courante.
- **Liseuses** : dans l'admin, « Générer une clé » affiche une seule fois la clé à saisir dans la liseuse.
  Seule son empreinte SHA-256 est stockée ; désactiver la liseuse lui retire l'accès.

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
