# Déploiement Railway Hobby avec SQLite

Cette configuration utilise une instance Laravel unique, une base SQLite sur un volume Railway persistant et un stockage privé sur ce même volume. Le fichier `database/database.sqlite` du dépôt sert uniquement à la démonstration locale : la production utilise exclusivement `/data/madina-import.sqlite`.

## 1. Créer le service et le volume

1. Créer un projet Railway et connecter le dépôt GitHub `rivoharyv4-creator/madina-import`.
2. Créer un seul service depuis ce dépôt.
3. Ajouter manuellement un volume au service.
4. Configurer son point de montage exactement sur `/data`.
5. Conserver une seule replica, une seule région et aucun déploiement multi-région.
6. Ne jamais monter le volume sur `/app` ni sur tout le dossier de l’application.
7. Ne jamais supprimer le volume lors d’un redéploiement.

Railway monte les volumes uniquement au démarrage, pas pendant le build ni pendant une commande de pre-deploy. C’est pourquoi les migrations et la préparation du volume sont exécutées par `scripts/railway-start.sh`.

## 2. Variables d’environnement

Configurer dans Railway :

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
APP_URL=https://votre-domaine.up.railway.app

DB_CONNECTION=sqlite
DB_DATABASE=/data/madina-import.sqlite
DB_FOREIGN_KEYS=true
DB_BUSY_TIMEOUT=5000
DB_SYNCHRONOUS=FULL

FILESYSTEM_DISK=persistent
PERSISTENT_STORAGE_PATH=/data/files
BACKUP_PATH=/data/backups
BACKUP_RETENTION_DAYS=14

ADMIN_EMAIL=manager@madina-import.mg
ADMIN_PASSWORD=un-secret-fort-uniquement-pour-l-initialisation

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync

```

Railway fournit automatiquement `PORT`, `RAILWAY_VOLUME_NAME` et `RAILWAY_VOLUME_MOUNT_PATH`. Le script refuse de démarrer si le volume déclaré n’est pas monté sur `/data` ou n’est pas inscriptible.

### Générer `APP_KEY`

Exécuter localement `php artisan key:generate --show`, puis copier la valeur complète `base64:...` dans la variable Railway `APP_KEY`. Ne jamais committer cette clé.

## 3. Build et démarrage

Railway détecte automatiquement le `Dockerfile` du dépôt. Dans **Service > Settings**, sélectionner le builder **Dockerfile** et laisser **Build Command vide**. Le Dockerfile utilise PHP 8.3, active GD et PDO SQLite, installe les dépendances puis compile Vite.

Après le build Vite, `node_modules` est supprimé directement. Le Dockerfile n’exécute pas `npm prune`, car cette commande peut dépasser la mémoire du builder Hobby et terminer avec le code 137.

La commande de démarrage est déjà définie par le `CMD` du Dockerfile :

```bash
sh scripts/railway-start.sh
```

Configurer aussi `/up` comme chemin de health check, un délai de 120 secondes, le redémarrage sur échec et désactiver le chevauchement des déploiements. Pour les nouveaux services Railway, ces réglages sont saisis dans le dashboard : l’ancien format `railway.json` est déprécié et ne doit pas être considéré comme la source de vérité.

Au démarrage, le script :

1. vérifie le montage et l’écriture sur `/data` ;
2. crée les dossiers `files/products`, `payments`, `expenses`, `invoices`, `quotes`, `logistics`, `exports` et `backups` ;
3. crée le fichier SQLite seulement s’il n’existe pas ;
4. active WAL une seule fois si nécessaire et vérifie les PRAGMA ;
5. exécute uniquement `php artisan migrate --force` ;
6. met en cache configuration, routes et vues ;
7. lance le scheduler puis le serveur Laravel.

Le script n’utilise jamais `migrate:fresh`, ne supprime jamais la base et ne remplace jamais un fichier existant.

### Initialisation facultative des données de test

Une nouvelle base de production est volontairement vide après les migrations. Pour un environnement de démonstration destiné au client, exécuter **une seule fois** dans le shell Railway :

```bash
php artisan db:seed --force
```

Les identifiants proviennent de `ADMIN_EMAIL` et `ADMIN_PASSWORD`. Définir un mot de passe fort dans Railway avant cette commande, puis retirer `ADMIN_PASSWORD` des variables une fois l’accès vérifié. Ne pas ajouter `db:seed` au script de démarrage : un redéploiement ne doit jamais réinjecter ou modifier automatiquement les données métier.

## 4. SQLite et concurrence

Réglages appliqués :

```sql
PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;
PRAGMA synchronous = FULL;
PRAGMA busy_timeout = 5000;
```

Le mode WAL est persistant. `madina:sqlite-configure` le modifie seulement lorsque la base n’est pas déjà en WAL. Les autres réglages sont réappliqués à chaque connexion, car ils sont propres à la connexion SQLite. Les écritures métier utilisent des transactions courtes.

SQLite impose ici une seule instance et une seule replica. Ne pas activer le multi-région ou l’autoscaling horizontal.

## 5. Fichiers persistants et privés

Le disque Laravel `persistent` pointe sur `/data/files`. Les photos, justificatifs, documents et exports sont stockés hors de SQLite. Les fichiers privés sont servis par des routes Laravel authentifiées avec validation du nom, type MIME contrôlé et protection contre les traversées de chemin.

Il n’existe volontairement aucune route publique pour les photos produit. Une future route de catalogue public devra vérifier explicitement qu’un produit est publié avant d’envoyer son image.

## 6. Sauvegardes Laravel

Créer une sauvegarde cohérente manuellement :

```bash
php artisan madina:backup
```

La commande utilise `VACUUM INTO`, vérifie `PRAGMA integrity_check`, journalise le résultat et supprime les sauvegardes plus anciennes que `BACKUP_RETENTION_DAYS`. Le scheduler l’exécute chaque jour à 02:00. Les fichiers sont créés dans `/data/backups` sous la forme `madina-import-AAAA-MM-JJ_HH-MM-SS-microsecondes.sqlite`.

Ces sauvegardes partagent le même volume que la base. Elles protègent contre une erreur applicative, mais pas contre la perte du volume.

## 7. Sauvegardes du volume Railway

Dans le dashboard Railway, ouvrir le volume attaché, accéder à **Backups**, activer les sauvegardes automatiques disponibles sur le plan Hobby et déclencher une première sauvegarde manuelle après validation du déploiement.

Conserver aussi périodiquement une copie hors Railway avec la CLI :

```bash
railway volume files download /backups/madina-import-AAAA-MM-JJ_HH-MM-SS-microsecondes.sqlite ./madina-import-backup.sqlite
```

## 8. Restaurer SQLite

1. Mettre le service hors ligne afin qu’aucun processus n’écrive dans SQLite.
2. Télécharger une copie de sécurité de `/data/madina-import.sqlite`.
3. Vérifier la sauvegarde : `sqlite3 backup.sqlite "PRAGMA integrity_check;"` doit retourner `ok`.
4. Renommer la base active, sans la supprimer immédiatement.
5. Téléverser la sauvegarde validée sous `/data/madina-import.sqlite`.
6. Redémarrer le service et vérifier `/up`, la connexion et les totaux du dashboard.
7. Supprimer l’ancienne copie uniquement après validation complète.

Ne jamais restaurer pendant que l’application ou le scheduler utilise la base.

## 9. Vérifications après déploiement

- `/up` répond avec un statut HTTP 200 sans donnée sensible ;
- la page de connexion s’affiche via HTTPS ;
- `php artisan migrate:status` indique toutes les migrations comme exécutées ;
- `php artisan madina:sqlite-configure` confirme WAL, clés étrangères, FULL et 5000 ms ;
- une photo et un justificatif restent accessibles après un redéploiement ;
- `php artisan madina:backup` crée une sauvegarde intègre ;
- les sessions persistent et le cache fonctionne ;
- le service possède exactement une replica.

## 10. Redéploiement et retour arrière

Un redéploiement réexécute les migrations non encore appliquées sans recréer la base et sans toucher aux fichiers existants. Le volume reste attaché au service.

Pour un retour arrière applicatif :

1. créer une sauvegarde Laravel et une sauvegarde Railway du volume ;
2. utiliser **Rollback** sur le déploiement Railway précédent ;
3. ne jamais exécuter automatiquement le `down()` des migrations ;
4. si une migration récente est incompatible, restaurer ensemble le code et une sauvegarde SQLite antérieure pendant un arrêt complet ;
5. vérifier `/up`, l’authentification, les fichiers et le dashboard.

Le rollback du code ne restaure pas automatiquement le contenu du volume.
