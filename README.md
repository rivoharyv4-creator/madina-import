# Madina Import

Monolithe de gestion interne construit avec Laravel 13, Inertia.js, React 19, TypeScript, Tailwind CSS et MySQL.

## Installation

1. Copier `.env.example` vers `.env` et renseigner les accès MySQL.
2. Créer la base `madina_import` en `utf8mb4`.
3. Exécuter `composer install`, puis `npm install`.
4. Exécuter `php artisan key:generate` puis `php artisan migrate --seed`.
5. Compiler avec `npm run build` ou démarrer avec `composer dev`.
6. Se connecter avec `ADMIN_EMAIL` et `ADMIN_PASSWORD`, puis changer immédiatement le mot de passe depuis le profil.

Le logo officiel doit être copié sans modification dans `public/brand/madina-import-logo.png`. Le fichier source mentionné dans le brief n’était pas disponible dans les pièces jointes lors de l’installation.

## Production

Configurer HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, un serveur de files d’attente, le stockage privé ou objet pour les pièces jointes, puis lancer `php artisan optimize`, `php artisan migrate --force` et `npm run build`. Le dossier `public` est le seul document root exposé.

## Architecture de données

La migration métier couvre les séquences annuelles atomiques, clients, fournisseurs et catalogue, devis et lignes, commandes et lignes, factures, paiements clients, paiements fournisseurs multi-commandes, expéditions, stock et mouvements, inventaires mensuels, ventes locales, dépenses, employés, salaires, fiscalité, documents privés et journal d’audit. Tous les montants utilisent `DECIMAL`.

## Tests

`php artisan test`. En environnement Windows local, activer `pdo_sqlite` pour la suite en mémoire, ou utiliser une base MySQL de test dédiée.
