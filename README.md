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

## Architecture de données

La migration métier couvre les séquences annuelles atomiques, clients, fournisseurs et catalogue, devis et lignes, commandes et lignes, factures, paiements clients, paiements fournisseurs multi-commandes, expéditions, stock et mouvements, inventaires mensuels, ventes locales, dépenses, employés, salaires, fiscalité, documents privés et journal d’audit. Tous les montants utilisent `DECIMAL`.

## Tests

`php artisan test`. Sous Windows, si `pdo_sqlite` n’est pas activé globalement : `php -d extension=pdo_sqlite vendor/bin/phpunit`.
