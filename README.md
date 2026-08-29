# Madina Import

Application de gestion interne construite avec Laravel 13, Inertia.js, React 19, TypeScript, Tailwind CSS et SQLite. Une base de démonstration réelle et préremplie est fournie dans `database/database.sqlite`.

## Installation

1. Vérifier que PHP 8.3+ possède l’extension `pdo_sqlite`.
2. Exécuter `composer install`, puis `npm install`.
3. Copier `.env.example` vers `.env`.
4. Exécuter `php artisan key:generate` puis `php artisan migrate`.
5. Compiler avec `npm run build` et démarrer avec `php artisan serve`.
6. Ouvrir `http://127.0.0.1:8000`.

Compte de démonstration :

- E-mail : `manager@madina-import.mg`
- Mot de passe : `ChangeMe!2026`

La base SQLite incluse contient des exemples de clients, fournisseurs, produits, commandes, paiements, factures, dépenses, salaires et données logistiques. Pour réinitialiser toutes les données de test : `php artisan migrate:fresh --seed`.

## Production

La base incluse est destinée aux tests client. Avant une mise en production, repartir d’une base vide, remplacer le mot de passe de démonstration, configurer HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, les sauvegardes SQLite et le stockage privé des pièces jointes.

### Laravel Cloud et conservation des données

SQLite doit rester réservé au développement local : le système de fichiers des instances Laravel Cloud est éphémère. En production Cloud, attacher une base Laravel MySQL ou Serverless Postgres à l’environnement avant le premier déploiement. Laravel Cloud injectera alors les variables `DB_*` nécessaires.

La commande de déploiement doit utiliser uniquement `php artisan migrate --force`. Ne jamais exécuter `migrate:fresh`, `db:wipe` ou `db:seed` automatiquement en production. Les migrations normales conservent les enregistrements existants. Les fichiers téléversés doivent être placés dans Laravel Object Storage plutôt que sur le disque local de l’instance.

## Architecture de données

La migration métier couvre les séquences annuelles atomiques, clients, fournisseurs et catalogue, devis et lignes, commandes et lignes, factures, paiements clients, paiements fournisseurs multi-commandes, expéditions, stock et mouvements, inventaires mensuels, ventes locales, dépenses, employés, salaires, fiscalité, documents privés et journal d’audit. Tous les montants utilisent `DECIMAL`.

## Partie publique

La page d’accueil, le catalogue publié depuis le stock, le suivi sécurisé et le formulaire de contact sont servis par le même monolithe Laravel/Inertia. Avant la mise en production, confirmer les numéros publics et renseigner `PUBLIC_FACEBOOK_URL` si nécessaire.

L’accès à la connexion de gestion n’est pas affiché sur le site public. Définir une adresse difficile à deviner avec `ADMIN_LOGIN_PATH` dans `.env` (sans slash initial), puis conserver cette adresse en privé. Les pages de gestion consultées sans authentification répondent 404 afin de ne pas révéler cette adresse par redirection. Après une modification en production, reconstruire le cache avec `php artisan config:cache`.

La production doit utiliser HTTPS avec `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true` et `SESSION_SAME_SITE=lax`.

## Tests

`php artisan test`. Sous Windows, si `pdo_sqlite` n’est pas activé globalement : `php -d extension=pdo_sqlite vendor/bin/phpunit`.
