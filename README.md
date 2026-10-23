# Expernet – refonte du site

Site d'Expernet, organisme de formation à La Réunion et à Mayotte : formation continue et alternance (Expernet Campus) réunies dans une seule application Symfony.

- **Partie publique** : catalogue avec filtres, fiches formation, programme en PDF, formulaires de contact, de devis et de candidature, pages légales.
- **Espace formation (ENT)** : connexion avec double authentification obligatoire, tableaux de bord étudiant, formateur et administrateur.

Projet réalisé dans le cadre du titre CDA (Groupe 6). Le site tourne en **mode démonstration** : bandeau visible, pages non indexées par les moteurs de recherche, comptes et sessions fictifs.

## Technologies

Symfony 7.4, PHP 8.4, Doctrine ORM 3, MySQL 8.4, Twig, Bootstrap 5.3 (AssetMapper, sans Node.js), Stimulus, API Platform, FrankenPHP, Docker.

## Démarrage avec Docker

Prérequis : Docker avec Docker Compose. Rien d'autre n'est nécessaire, ni PHP ni Composer sur la machine. Avec l'ancienne version autonome de Compose, remplacer `docker compose` par `docker-compose` dans les commandes.

```bash
git clone <adresse-du-depot>
cd RefonteSolutionExpernetGroupe6
docker compose up -d
```

Le premier lancement prend une à deux minutes. L'application :

1. installe les dépendances Composer et les librairies front (Bootstrap…) ;
2. attend que MySQL soit prêt, puis applique les migrations ;
3. charge les données de démonstration si la base est vide ;
4. démarre le worker qui envoie les e-mails en arrière-plan.

Pour suivre le démarrage : `docker compose logs -f app`.

| Service | Adresse |
|---|---|
| Site | http://localhost:8000 |
| E-mails envoyés par le site (Mailpit) | http://localhost:8025 |
| MySQL (depuis la machine) | `127.0.0.1:3307` |

### Ports déjà utilisés

Les ports se changent par des variables d'environnement :

```bash
APP_PORT=8080 DATABASE_PORT=3308 MAILPIT_PORT=8026 MAILPIT_SMTP_PORT=1026 docker compose up -d
```

Sous Windows (PowerShell) :

```powershell
$env:APP_PORT=8080; $env:DATABASE_PORT=3308; docker compose up -d
```

### Fedora et Linux avec SELinux

La configuration commune ne fixe pas d'UID/GID et fonctionne aussi avec Docker Desktop sous Windows et macOS. Sur Fedora, ajoutez le fichier dédié pour adapter l'utilisateur du conteneur et son étiquetage SELinux :

```bash
HOST_UID="$(id -u)" HOST_GID="$(id -g)" \
docker compose -f compose.yaml -f compose.override.yaml -f compose.fedora.yaml up -d
```

Sous Windows et macOS, utilisez simplement `docker compose up -d`.

Les dépendances Composer de développement sont conservées dans le volume Docker `vendor_data`, partagé par `app` et `worker`, et non dans le dossier `vendor/` de l'hôte.

### Windows

- Installer [Docker Desktop](https://www.docker.com/products/docker-desktop/) avec le moteur WSL 2.
- **Recommandé** : cloner le projet dans WSL 2 (par exemple `~/projets` dans Ubuntu) plutôt que sur `C:\`. Le site fonctionne dans les deux cas, mais il est nettement plus rapide depuis WSL 2, car Docker accède lentement aux fichiers du disque Windows.
- Les fins de ligne sont imposées par `.gitattributes` : les scripts du dossier `docker/` restent au format Unix, même avec `core.autocrlf=true`.

### Dépannage : l'application reste sur « Attente de la base de données »

Toutes les 20 secondes, les journaux (`docker compose logs -f app`) affichent la raison de l'attente. Pour l'obtenir tout de suite :

```bash
docker compose ps                                              # la base doit être « healthy »
docker compose exec app php bin/console dbal:run-sql "SELECT 1"
```

| Message | Cause | Solution |
|---|---|---|
| `Access denied for user 'usr_expernet'` | Le volume MySQL a été créé auparavant avec d'autres identifiants : MySQL ne les relit qu'à la création du volume. | Supprimer le volume, ce qui efface la base (les données de démonstration sont rechargées au lancement suivant) : `docker compose down -v`, puis `docker compose up -d` |
| `Unknown database 'db_refonte_expernet'` | Même cause. | Même solution. |
| `getaddrinfo` ou `Connection refused` | La base n'est pas démarrée ou a planté. | `docker compose logs database` |
| `Unable to write in the cache directory` | Dossier `var/` créé par un autre utilisateur. | Supprimer le dossier `var/` du projet, puis `docker compose restart app` |

Si un autre projet utilise déjà un conteneur MySQL sur le port 3307, démarrer avec `DATABASE_PORT=3308` (voir plus haut).

## Comptes de démonstration

Mot de passe commun : `Expernet-Demo-2026`. Pour en choisir un autre, définir `FIXTURES_MOT_DE_PASSE` dans `.env.local` puis recharger les données.

| Rôle | Identifiant |
|---|---|
| Administrateur | `admin@demo.expernet.test` |
| Formateur | `formateur@demo.expernet.test`, `formateur2@demo.expernet.test` |
| Jury | `jury@demo.expernet.test` |
| Étudiant | `etudiant@demo.expernet.test` à `etudiant6@demo.expernet.test` |

Connexion : http://localhost:8000/login. À la première connexion, l'espace formation demande d'activer la double authentification avec une application comme FreeOTP ou Google Authenticator.

Pour réinitialiser la double authentification d'un compte (téléphone perdu) :

```bash
docker compose exec app php bin/console app:double-authentification:reinitialiser admin@demo.expernet.test
```

## Base de données

| Paramètre | Valeur de développement |
|---|---|
| Base | `db_refonte_expernet` |
| Utilisateur | `usr_expernet` |
| Mot de passe | `app_dev_password` |
| Mot de passe root | `root_dev_password` |

Ces valeurs ne servent qu'en local. Elles se modifient avec les variables `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD` et `MYSQL_ROOT_PASSWORD`.

```bash
# Console MySQL
docker compose exec database mysql -u usr_expernet -papp_dev_password db_refonte_expernet

# Remettre les données de démonstration à zéro
docker compose exec app php bin/console doctrine:fixtures:load
```

Les données sont conservées dans le volume Docker `database_data`. `docker compose down -v` les supprime.

## Tests

La base de test `db_refonte_expernet_test` est créée au premier lancement de MySQL. Avant la première exécution des tests, on y applique les migrations et on y charge les données :

```bash
docker compose exec app php bin/console doctrine:migrations:migrate -n --env=test
docker compose exec app php bin/console doctrine:fixtures:load -n --env=test
docker compose exec app php bin/phpunit
```

Chaque test est annulé à la fin (DAMA DoctrineTestBundle) : les données ne changent pas d'un test à l'autre.

Vérifications du code :

```bash
docker compose exec app php bin/console lint:twig templates
docker compose exec app php bin/console lint:yaml config
docker compose exec app php bin/console lint:container
```

## Sans Docker

Avec PHP 8.4 ou 8.5 (extensions `intl`, `pdo_mysql`, `gd`, `zip`, `mbstring`), Composer et la [CLI Symfony](https://symfony.com/download). Sous Windows, ces extensions sont fournies avec PHP mais doivent être activées dans `php.ini` (lignes `extension=…`).

```bash
composer install
docker compose up -d database mailer   # ou un MySQL 8.4 local, à déclarer dans .env.local
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n
symfony serve -d
php bin/console messenger:consume async -vv   # envoi des e-mails
```

Les réglages propres à la machine (adresse de la base, `MAILER_DSN`…) vont dans un fichier `.env.local`, jamais versionné.

## Production

Un fichier Compose dédié publie le site sur le port 8000 (modifiable avec `APP_PORT`) et exige des secrets fournis par l'environnement de déploiement. Définissez au minimum `APP_SECRET`, `DATABASE_URL`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD` et `MYSQL_ROOT_PASSWORD`, puis lancez :

```bash
docker compose -f compose.yaml -f compose.prod.yaml up -d --build
```

Le site sera disponible sur `http://localhost:8000`. Encodez les caractères spéciaux des identifiants dans `DATABASE_URL` selon le format d'URL Doctrine. Le fichier `.env` fourni contient des valeurs de développement ; ne les réutilisez pas en production.

L'image contient les dépendances et les assets compilés, mais aucun secret. `compose.prod.yaml` exige les paramètres suivants :

- `APP_SECRET` : chaîne aléatoire longue. Elle sert aussi à sceller les émargements, il ne faut donc plus la changer une fois le site en service ;
- `DATABASE_URL` et les variables `MYSQL_*` correspondant à la base du service ;
- `MAILER_DSN` si l'envoi d'e-mails est configuré (`null://null` par défaut) ;
- `APP_DEMO=0` quand le site présente des données réelles (valeur par défaut en production).

## Organisation du code

| Dossier | Contenu |
|---|---|
| `src/Controller` | Pages publiques ; `Ent/` pour l'espace formation |
| `src/Entity`, `src/Repository` | Modèle de données (Doctrine) |
| `src/Form` | Formulaires et filtres du catalogue |
| `src/Security`, `src/EventSubscriber` | Connexion, double authentification, limites d'essais |
| `src/Service` | PDF, e-mails, calcul d'assiduité |
| `templates/` | Vues Twig |
| `assets/styles` | Charte graphique (surcouche de Bootstrap) |
| `config/packages/expernet.yaml` | Informations de l'organisme (coordonnées, mentions légales, durées de conservation) |
| `docker/` | Point d'entrée du conteneur, initialisation de MySQL |
| `tests/` | Tests unitaires et fonctionnels |

Les commits suivent [Conventional Commits](https://www.conventionalcommits.org/fr/), en français.
