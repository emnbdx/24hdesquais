# 24h des Quais de Bordeaux

Site de l’édition 2027. Départ le samedi 1er mai à 18h, arrivée le dimanche 2 mai à 18h, briefing à 17h30. Les heures sont en Europe/Paris.

Une personne s’inscrit une seule fois : nom, prénom, pseudo, email, et les créneaux qu’elle choisit. Il n’y a pas de mot de passe. Un code à 6 chiffres est envoyé par email (valable 10 minutes). Elle peut se reconnecter avec le même procédé pour modifier ses infos et ses créneaux. Le site affiche les pseudos, créneau par créneau.

Les inscriptions se ferment le 30 avril 2027 à 23h59 (`REGISTRATION_END_DATE`).

## Prérequis

- PHP 8.2 ou plus, avec `pdo_mysql`, `intl` et `mbstring`
- Composer
- MySQL ou MariaDB
- Un compte [Postal](https://docs.postalserver.io/) pour l’envoi des emails (`POSTAL_API_URL`, `POSTAL_API_KEY`)

## Lancer en local

```bash
composer install
cp .env.example .env
```

Renseigner `.env` : accès MySQL (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`), Postal, et la date de fin d’inscription. En local, `DB_HOST=127.0.0.1` et `DB_PORT=3306`.

Créer les tables :

```bash
php bin/migrate.php
```

Le script crée la base si le compte MySQL en a le droit, puis applique `db/migrations/*.sql`. Une migration déjà passée n’est pas rejouée.

Démarrer le site :

```bash
php -S 127.0.0.1:8080
```

Ouvrir http://127.0.0.1:8080.

## Déployer sur OVH

L’hébergement est un mutualisé Apache. La racine web du domaine doit être le dossier du projet (`index.php` à la racine). `.htaccess` bloque l’accès à `.env`, `vendor/`, `src/`, `db/` et `bin/`.

1. Créer une base MySQL dans le manager OVH. Noter l’hôte (souvent `xxxx.mysql.db`), le nom de la base, l’utilisateur et le mot de passe. Le port est `3306`. Il n’y a pas de socket.
2. Envoyer le code sur l’hébergement (git ou FTP), sans le `.env` local et sans `vendor/`.
3. En SSH, à la racine du site :

```bash
composer install --no-dev
cp .env.example .env
```

4. Éditer le `.env` du serveur :

```dotenv
POSTAL_API_URL=https://exemple.postal
POSTAL_API_KEY=
REGISTRATION_END_DATE="2027-04-30 23:59:59"

DB_HOST=xxxx.mysql.db
DB_PORT=3306
DB_NAME=nomdelabase
DB_USER=utilisateur
DB_PASSWORD=
```

5. Appliquer le schéma. Sur OVH le compte ne peut en général pas créer la base : elle existe déjà, le script l’indique et continue avec les migrations.

```bash
php bin/migrate.php
```

6. Dans le manager OVH, choisir PHP 8.2 ou plus pour ce multisite, avec le dossier racine pointé sur le projet.

Après un changement de schéma, renvoyer le code puis relancer `php bin/migrate.php`.
