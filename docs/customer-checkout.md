# Livraison du parcours client Madina Import

Branche : `feature/guest-cart-manual-checkout`. Aucun push, fusion ou déploiement effectué. Les modifications locales présentes avant cette intervention ont été conservées. Aucun `migrate:fresh` exécuté ; la base applicative existante n’a pas été migrée pendant cette intervention. Les tests utilisent SQLite en mémoire.

## Parcours livré

Le catalogue reste public. Les produits tarifés et non marqués en rupture peuvent être ajoutés au panier avec la clé `madina-import:cart:v1`. Les produits sans prix affiché conservent le parcours de demande de devis/WhatsApp. Le panier permet de modifier les quantités, supprimer une ligne et confirmer sa suppression complète. Son montant est explicitement indicatif.

Le checkout redirige les invités vers `/connexion`, puis les comptes non vérifiés vers `/verify-email`. L’inscription crée un compte `customer`, actif, sans permission de gestion. Le téléphone est demandé à l’inscription et préremplit le checkout. L’e-mail est normalisé et le mot de passe utilise le cast Laravel `hashed` et les règles existantes. L’envoi du code est synchrone. Un échec d’envoi laisse le compte connecté pour permettre un renvoi ; une nouvelle inscription ne crée pas un second compte.

Le code de six chiffres est généré avec `random_int`, conserve les zéros initiaux et est uniquement enregistré sous forme de hash Laravel. Il expire après dix minutes, est consommé une seule fois, bloque après cinq erreurs et ne peut être renvoyé avant soixante secondes. Un renvoi remplace le code précédent. Les compteurs d’erreurs sont validés en transaction avant de retourner l’erreur de validation. La vérification déclenche l’événement Laravel `Verified`. Les anciens liens de vérification ne sont plus disponibles. Une modification de l’adresse e-mail dans le profil invalide son ancien code.

Laravel recharge les produits publiés et non supprimés, refuse les quantités invalides, les ruptures et les prix nécessitant un devis, vérifie les stocks disponibles, puis recalcule les prix et totaux en unités monétaires mineures entières. Le catalogue ne possède pas de variantes commerciales ; le snapshot d’options est donc vide. La devise est `MGA`, affichée en ariary (`Ar`).

Les commandes réutilisent `orders` et `order_items`. Les snapshots conservent noms, SKU, quantités, prix unitaires, sous-totaux et livraison. Un même utilisateur réutilise sa fiche client. La référence de commande est aléatoire. Les clés UUID du checkout et des preuves empêchent les doublons pour une même soumission. Le panier est vidé uniquement lorsque Laravel retourne la confirmation de création de la commande.

Les comptes de paiement actifs et non supprimés sont visibles exclusivement sur une commande appartenant au client authentifié et vérifié. Aucun compte actif entraîne un refus du checkout avant la création de la commande. Les méthodes sont des libellés libres, sans API de paiement. Chaque compte peut demander une preuve obligatoire. Les preuves acceptées sont JPEG, PNG et WebP, avec vérification MIME et une limite de 5 Mo ; leur nom est généré par Laravel. Elles utilisent le disque privé `persistent` et sont téléchargées uniquement après contrôle du propriétaire ou de l’administrateur.

Les paiements passent de `awaiting_submission` à `under_review`, puis `confirmed` ou `rejected`. Une preuve reçue ne confirme jamais la commande. Un refus nécessite un motif destiné au client, conserve l’historique et autorise une nouvelle soumission. Les snapshots des destinations de paiement restent inchangés après modification ou suppression du compte.

La confirmation administrative verrouille la commande et la soumission, vérifie à nouveau le stock, confirme le paiement et la commande, renseigne l’auteur/date, déduit le stock avec un mouvement d’audit et crée le paiement dans `client_payments` en une seule transaction. Une seconde confirmation n’a aucun effet comptable et ne renvoie pas d’e-mail. Les produits « sur commande » ne déduisent pas un stock local indisponible. Les commandes web utilisent leur interface administrative dédiée pour protéger les snapshots et éviter une seconde déduction par l’ancien formulaire métier.

Après paiement confirmé, l’administrateur peut passer la commande à `processing`, puis `completed`. Une commande sans paiement en cours de vérification ou confirmé peut être annulée. Les transitions invalides sont refusées côté serveur. Le client dispose de la liste de ses commandes, de leur détail, du statut public et de la chronologie des tentatives. Les coûts fournisseurs, marges, notes internes et chemins privés ne sont pas transmis aux pages client.

## Administration et autorisations

La page `/admin/paiements-manuels` figure dans le menu administratif pour les utilisateurs autorisés. Elle présente les commandes web, comptes, auteurs des modifications, preuves et historique des soumissions. Elle permet l’ajout, la modification, l’ordre d’affichage, l’activation/désactivation et la suppression logique des comptes. Les actions de suppression, désactivation et confirmation requièrent une confirmation dans l’interface. La revue utilise un dialogue HTML natif avec gestion du focus et fermeture par Échap.

Les mécanismes existants sont conservés : middleware `auth`, `verified`, `module.access`, méthodes d’autorisation sur `User` et contrôles du propriétaire dans les requêtes. `User::canManageManualPayments()` autorise le `super_admin` actif ou un rôle `admin` actif avec permission `paiements`. Le gestionnaire de rôles existant conserve ses rôles actuels ; aucun compte `admin` supplémentaire n’est provisionné automatiquement. Les clients sont explicitement exclus de tous les modules et des anciennes routes de documents internes. Aucun nouveau fichier de policy n’est nécessaire avec cette architecture.

Les limites Laravel d’authentification et de code sont cumulées par IP, hash d’adresse e-mail et compte. Les routes de commande et paiement appliquent également une limitation à 60 requêtes/minute. Les formulaires réutilisent la protection CSRF Laravel/Inertia. Un compte désactivé est bloqué même avec une session déjà ouverte.

## Migration et relations

Nouvelle migration : `database/migrations/2026_09_16_000200_add_customer_checkout.php`.

Elle crée `email_verification_codes`, `payment_accounts` avec suppression logique et `payment_submissions`, puis ajoute :

- `users.phone`, nullable pour les comptes existants ;
- `clients.user_id`, nullable et unique, vers `users` ;
- `orders.user_id`, `checkout_key` unique, `payment_status`, `currency`, `delivery_snapshot`, `payment_confirmed_at` ;
- `order_items.sku`, `options`, `unit_price`, `stock_managed`.

Relations par clés étrangères : utilisateur → code ; utilisateur → fiche client ; utilisateur → commandes ; commande → lignes et soumissions ; soumission → compte et administrateur de revue ; compte → auteurs de création/modification. Les anciennes commandes conservent `user_id` et `payment_status` nuls et restent dans leur parcours existant.

Le projet utilise le Query Builder pour ses entités métier. Cette convention est conservée : aucun modèle Eloquent métier parallèle n’a été ajouté. Le modèle `User` existant est adapté pour `MustVerifyEmail`, l’envoi personnalisé et les permissions client/paiement.

La migration d’affichage de disponibilité `2026_09_16_000100_add_public_availability_status_to_inventory_products.php` était déjà présente dans les modifications locales ; elle est réutilisée sans modification.

## Routes ajoutées ou adaptées

| Méthode | URL | Fonction |
| --- | --- | --- |
| GET | `/panier` | Panier invité |
| GET / POST | `/connexion` | Connexion client |
| GET / POST | `/inscription` | Inscription client |
| GET / POST | `/verify-email` | Notice et validation du code |
| POST | `/email/verification-notification` | Renvoi du code |
| GET / POST | `/commande` | Checkout et création |
| POST | `/commande/apercu` | Prix et disponibilités validés |
| GET | `/mes-commandes` | Commandes du client |
| GET | `/mes-commandes/{number}` | Détail appartenant au client |
| POST | `/mes-commandes/{number}/paiement` | Soumission de paiement |
| GET | `/paiement-preuves/{id}` | Téléchargement autorisé |
| GET | `/admin/paiements-manuels` | Administration |
| POST | `/admin/comptes-paiement` | Ajout de compte |
| PUT / DELETE | `/admin/comptes-paiement/{id}` | Modification / suppression logique |
| POST | `/admin/paiements-manuels/{id}` | Confirmation ou refus |
| PATCH | `/admin/commandes-web/{id}` | Préparation, achèvement, annulation |

`verification.notice`, `verification.verify` et `verification.send` restent les noms de routes de vérification ; `verification.verify` utilise désormais POST et un code. La connexion privée de gestion reste inchangée.

## Resend et Laravel Cloud

Laravel 13 possède déjà le transport Resend dans `config/mail.php`. Le SDK `resend/resend-php` a été ajouté avec Composer ; `composer.lock` verrouille sa version 1.15.0. L’envoi est isolé dans `CustomerMailService` et le mailable `CustomerMessage`, avec une version HTML et une version texte de marque. Aucune clé réelle n’est utilisée dans les tests.

Configurer les variables de l’environnement Cloud :

```dotenv
MAIL_MAILER=resend
RESEND_API_KEY=<clé configurée dans les secrets Cloud>
MAIL_FROM_ADDRESS=no-reply@madina-import.com
MAIL_FROM_NAME="Madina Import"
APP_URL=https://<domaine-public-réel>
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
```

`.env.example` contient une clé Resend vide et utilise `MAIL_MAILER=array` pour éviter l’enregistrement des codes dans les logs en développement. Pour réceptionner les codes localement, configurer un SMTP de test avec `MAIL_MAILER=smtp`. Ne pas utiliser `log` ou un transport de secours susceptible de journaliser un e-mail client : le service les refuse. Les erreurs d’envoi ne journalisent que le type technique de l’exception, jamais son message, le destinataire ou le code.

Dans Resend, ajouter un domaine détenu par Madina Import. Copier exactement les enregistrements DNS DKIM et SPF/MX fournis dans le dashboard vers le fournisseur DNS du domaine, puis lancer la vérification et attendre le statut vérifié. Ajouter DMARC selon la politique du domaine. Le domaine de `MAIL_FROM_ADDRESS` doit correspondre au domaine vérifié. Créer une clé autorisée à envoyer des e-mails et la conserver uniquement dans les secrets de Cloud. Procédure officielle : [gestion des domaines Resend](https://resend.com/docs/dashboard/domains/introduction), [diagnostic de vérification DNS](https://resend.com/docs/knowledge-base/what-if-my-domain-is-not-verifying).

Le logo utilise le chemin existant `/brand-logo-transparent`, construit depuis `APP_URL` et affiché uniquement pour une URL HTTPS sans hôte localhost. Le nom Madina Import reste présent en texte lorsque les images sont bloquées.

Après autorisation de mise en production, les commandes de construction/déploiement adaptées sont :

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan view:cache
```

Configurer une base persistante attachée à Cloud. Le disque `persistent` actuel utilise le système de fichiers local : conserver le montage persistant déjà prévu par le projet via `PERSISTENT_STORAGE_PATH`, ou adapter ce disque au stockage objet privé de l’environnement Cloud avant production. Ne jamais publier `customer-payment-proofs`. Aucun nouveau worker n’est requis, car les e-mails sont synchrones. Après migration et configuration, ajouter au moins un compte actif depuis l’administration, puis vérifier l’envoi avec une boîte de test autorisée.

## Fichiers créés

- `app/Http/Controllers/CustomerAuthController.php`
- `app/Http/Controllers/CustomerOrderController.php`
- `app/Http/Controllers/ManualPaymentAdminController.php`
- `app/Mail/CustomerMessage.php`
- `app/Services/CustomerMailService.php`
- `app/Services/EmailCodeService.php`
- `database/migrations/2026_09_16_000200_add_customer_checkout.php`
- `resources/js/lib/cart.ts`
- `resources/js/Components/AddToCart.tsx`
- `resources/js/Components/CustomerShell.tsx`
- `resources/js/Pages/Customer/Auth.tsx`
- `resources/js/Pages/Customer/Cart.tsx`
- `resources/js/Pages/Customer/Checkout.tsx`
- `resources/js/Pages/Customer/Order.tsx`
- `resources/js/Pages/Customer/Orders.tsx`
- `resources/js/Pages/Customer/Verify.tsx`
- `resources/js/Pages/Admin/ManualPayments.tsx`
- `resources/views/emails/customer.blade.php`
- `resources/views/emails/customer-text.blade.php`
- `routes/customer.php`
- `tests/Feature/CustomerCheckoutTest.php`
- `docs/customer-checkout.md`

## Fichiers modifiés par cette intervention

`.env.example`, `composer.json`, `composer.lock`, `config/services.php`, `config/madina.php`, `README.md`, `routes/auth.php`, `routes/web.php`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/Authenticate.php`, `app/Http/Controllers/ProfileController.php`, `app/Http/Controllers/PublicSiteController.php`, `app/Http/Controllers/ModuleController.php`, `app/Http/Controllers/SecureFileController.php`, `resources/css/app.css`, `resources/js/Components/PublicProductCard.tsx`, `resources/js/Pages/Public/Product.tsx`, `resources/js/Layouts/PublicLayout.tsx`, `resources/js/Layouts/AuthenticatedLayout.tsx`, `tests/Feature/Auth/EmailVerificationTest.php`, `tests/Feature/ModuleCreationTest.php`, `tests/Feature/PdfDocumentTest.php`.

Les deux dernières suites existantes ont uniquement eu leur assertion d’adresse actualisée pour utiliser la configuration déjà présente du projet. Les tests de vérification par lien ont été remplacés par les tests de vérification par code. Les autres modifications préexistantes ne font pas partie de cette livraison.

## Validation et limites

### Profit des commandes web

Dans ce calcul, le **prix d’achat complet (coût de revient)** signifie : **prix fournisseur + fret + emballage + livraison en Chine + autres dépenses professionnelles attribuées à la commande**. Le prix fournisseur seul est une composante de ce total. Le **profit net de la commande = montant de vente − prix d’achat complet**. Un frais déjà compris dans le prix saisi ne doit pas être enregistré une seconde fois comme dépense supplémentaire.

Le profit d’une commande web confirmée est le montant de vente moins le prix d’achat figé à la commande, la livraison en Chine et l’emballage unitaires multipliés par la quantité, le fret total et les dépenses professionnelles liées à cette commande. Le fret global de la commande est prioritaire sur la somme du fret des lignes pour éviter une double déduction. Les coûts sont répartis au prorata des factures lorsqu’il y en a plusieurs. Les dépenses personnelles sont exclues ; les charges professionnelles sans commande restent déduites au niveau du profit global.

Pour enregistrer l’emballage, le fret ou d’autres frais supplémentaires d’une commande web, utiliser **Dépenses**, choisir **Professionnelle** et sélectionner la **Commande liée**. Ne pas ressaisir un coût déjà inclus dans le prix d’achat ou le fret de la commande. Le fret alloué à une fiche de stock n’est pas automatiquement réparti sur les ventes web : enregistrer la part réellement attribuée à la commande dans ses dépenses. Validation : 28 tests ciblant checkout, dashboard et fiscalité réussissent.

Toutes les routes du back-office exigent aussi `backoffice.account`, y compris le profil interne, les documents et les paiements manuels. Les visiteurs sont redirigés vers leur connexion publique `/connexion`, jamais vers la connexion administrative ; les comptes `customer` et les rôles inconnus reçoivent 403. Les permissions de module restent obligatoires pour les comptes de gestion. Les tests parcourent automatiquement les routes protégées et vérifient chaque méthode HTTP pour un visiteur et un client, même avec des permissions attribuées par erreur. Validation ciblée : **39 tests, 561 assertions**.

### Mise à jour locale après livraison

La séparation des comptes est renforcée : la connexion de gestion accepte uniquement les rôles d’équipe, la connexion publique uniquement `customer`, et les routes de checkout/commandes exigent le middleware `customer.account`. Les clients sont exclus de la liste de l’équipe et ne peuvent pas être promus depuis ce formulaire, ni recevoir une configuration Google Authenticator administrative. Le middleware d’authentification bloque leur accès aux pages de gestion, même si des permissions leur étaient attribuées par erreur. Les clients déjà connectés sont redirigés vers leur espace client depuis les pages de connexion. La base `users` existante est conservée avec un rôle distinct ; aucun compte public ni compte administrateur n’est converti automatiquement. Les justificatifs restent accessibles au propriétaire client et aux administrateurs autorisés. Tests ajoutés : `tests/Feature/AccountSeparationTest.php`.

L’erreur « no such table: payment_accounts » sur `/admin/paiements-manuels` provenait des migrations non appliquées à `database/database.local.sqlite`. Les deux migrations en attente (disponibilité publique et checkout client) ont désormais été appliquées avec `php artisan migrate --force`, après sauvegarde cohérente dans `storage/app/backups/before-customer-checkout-2026-09-16_11-34-06.sqlite`. Les nombres d’utilisateurs, clients, commandes, lignes et produits sont restés identiques. Le rendu serveur de la page renvoie 200, le contrôle d’intégrité SQLite réussit et les 26 tests ciblés réussissent. Aucun changement n’a été appliqué à Laravel Cloud.

Résultat final : `php artisan test --process-isolation` réussit avec **132 tests et 1 373 assertions**. Les 26 tests du nouveau parcours et de vérification e-mail réussissent également sans isolation. Le build frontend, Laravel Pint et `git diff --check` réussissent.

- `npm.cmd run build` : TypeScript et compilation Vite réussis. Sous PowerShell, `npm.cmd` contourne la politique locale bloquant `npm.ps1` sans la modifier.
- Laravel Pint appliqué sur les fichiers PHP concernés, puis contrôle `--test`.
- Les nouveaux tests couvrent inscription/hash/envoi avec `Mail::fake`, panne d’envoi sans doublon, zéros initiaux, espaces, expiration, consommation, cinq erreurs, délai de renvoi, invalidation de l’ancien code, protection du checkout, prix falsifiés, snapshots, stocks, quantités, absence de compte actif, idempotence, types de fichiers, preuve obligatoire, propriétaire, confirmation/refus, resoumission, comptabilité, stock, suppression logique et transitions.
- `php artisan test` a été lancé, mais le processus PHP local s’interrompt dans un ancien test de reçu PDF. Les six tests PDF passent lorsqu’ils sont exécutés seuls. La suite complète passe avec `php artisan test --process-isolation` ; le résultat final est indiqué dans la réponse de livraison.
- Aucun script `lint` ou dispositif de tests frontend n’est configuré dans `package.json`. Le build comprend déjà le contrôle TypeScript. Aucun outil de lint artificiel n’a été installé.
- La connexion au navigateur intégré n’est pas disponible dans cette session. Une tentative avec Edge sans interface a également échoué lors du démarrage du processus GPU dans l’environnement restreint. Aucun résultat visuel n’est donc revendiqué. Les parcours aux largeurs 375, 768, 1280 et 1536 px, en clair/sombre, ainsi que le clavier, le collage du code, le copier-numéro et les échanges réels avec une boîte de test restent à vérifier dans un navigateur fonctionnel. Le fichier temporaire de vérification a été retiré et le serveur de test arrêté.
- Les tests exécutent les contrôles et transitions serveur ; les courses réelles entre deux connexions SQL, les erreurs réseau d’interface et la réception effective via Resend ne sont pas simulées de bout en bout. Aucun test avec une vraie clé Resend.
- Le stock est vérifié au checkout puis à la confirmation administrative ; aucune réservation temporaire automatique n’est créée. Un stock devenu insuffisant bloque atomiquement la confirmation et exige une intervention humaine avant de traiter le paiement.
- Les notifications de commande, preuve reçue, confirmation et refus sont envoyées après la transaction et une seule fois par transition. Un échec de notification ne défait pas une commande ou un paiement confirmé ; aucun système automatique de relance des e-mails n’est ajouté sans worker existant.
- La récupération par phrase secrète existante reste réservée aux gestionnaires. Aucun parcours autonome de récupération de mot de passe client n’a été ajouté à cette version.
# Bénéfice des commandes web

Pour une commande web dont le paiement est confirmé, le bénéfice des produits est la somme de `(prix de vente enregistré − prix d’achat enregistré) × quantité`. Le prix d’achat du stock est mémorisé à la création de la commande ; il n’est pas communiqué au client. Les commandes en attente, refusées ou annulées ne contribuent pas à ce bénéfice. Une facture ultérieure ne compte pas la vente une seconde fois. Le tableau de bord déduit ensuite les charges générales professionnelles pour afficher le bénéfice net.

La migration des anciennes commandes web mémorise le prix d’achat actuel du produit lié, ou le coût fournisseur de la ligne si le produit n’existe plus. Le coût historique n’ayant pas été enregistré auparavant, leur bénéfice peut nécessiter une correction comptable.
