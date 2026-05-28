# Evasion ERP

ERP PHP 8 moderne pour gérer plusieurs showrooms physiques et plusieurs sites eCommerce WooCommerce. Le projet fournit une architecture MVC légère, une API REST sécurisée par JWT, une interface responsive premium et un schéma MySQL complet.

## Modules inclus

- Authentification: login, premier register admin, récupération mot de passe, JWT, 2FA TOTP, rôles et permissions.
- Produits: CRUD, catégories, marques, fournisseurs, variantes, images, QR/barcode, prix, alertes stock.
- Stock avancé: multi-entrepôts, showrooms, dépôt, stock web, mouvements et réservations.
- Transferts magasins: statuts, validation réception, signature, bon PDF via export.
- POS: panier rapide, scan barcode/QR côté UI, encaissement API, décrément stock.
- Tickets SAV: priorités, statuts, catégories, assignation et messages.
- RH: employés, départements, présence, congés, salaires, documents.
- Livraison: livreurs, tracking, zones, frais, signature client.
- WooCommerce: multi-sites, connecteur de sync et endpoint webhook.
- CRM: clients, historique commandes, fidélité, WhatsApp direct via données client.
- Marketing & analytics: KPI, top produits, ventes par canal/showroom, campagnes.
- Comptabilité: factures, dépenses, TVA, bénéfice, exports.
- Notifications: in-app, email, SMS, WhatsApp-ready, publication temps réel.
- IA pro: assistant d'analyse ventes/stocks/SAV/comptabilité avec recommandations opérationnelles.
- Factures PDF complètes: endpoint dédié `/api/invoices/{id}/pdf`.
- WebSocket réel: serveur PHP léger `bin/websocket-server.php` diffusant les événements ERP.
- 2FA complet: setup QR, confirmation OTP et désactivation via API/UI.
- PWA, dark mode, responsive desktop/tablette/mobile, FR/AR/EN prêt à étendre.

## Stack

- Backend: PHP 8, MVC, PDO, MySQL, JWT, rate limiting, logs, cache fichier.
- Frontend: HTML5, TailwindCSS, Alpine.js, Chart.js, DataTables, SweetAlert.
- Exports: CSV, XLS compatible Excel, PDF léger.
- API docs: `docs/openapi.yaml`.

## Installation locale

```bash
cp .env.example .env
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql
php -S 0.0.0.0:8080 -t public
```

Ouvrir ensuite `http://localhost:8080`.

Pour les notifications temps réel WebSocket:

```bash
php bin/websocket-server.php
```

Le serveur écoute par défaut sur `0.0.0.0:8090`.

Production Linux:

```bash
sudo bash scripts/install-websocket-systemd.sh /var/www/evasion
```

XAMPP Windows:

```text
scripts/start-websocket-xampp.bat
```

## Premier administrateur

Après avoir importé le schéma et les rôles:

```bash
curl -X POST http://localhost:8080/api/auth/register-admin \
  -H "Content-Type: application/json" \
  -d '{"name":"Super Admin","email":"admin@example.com","password":"ChangeMeSecure123!"}'
```

Puis:

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"ChangeMeSecure123!"}'
```

Copier le `token` dans `localStorage.token` pour utiliser l'interface avec les endpoints sécurisés.

## Architecture

```text
app/
  Controllers/        Contrôleurs HTTP et modules métier
  Core/               Routeur, DB, sécurité, JWT, cache, exports
  Support/            Registre des modules ERP
database/             Schéma SQL et données de démonstration
docs/                 OpenAPI
public/               Front controller, PWA, assets UI
routes/               Déclaration des routes REST
storage/              Cache, logs et uploads
```

## API REST

Toutes les routes métier sont préfixées par `/api` et utilisent un bearer token JWT.

Documentation complète:

- Résumé fonctionnel + guide API: [`docs/evasion-erp-guide-complet.md`](docs/evasion-erp-guide-complet.md)
- PDF téléchargeable: [`docs/evasion-erp-guide-complet.pdf`](docs/evasion-erp-guide-complet.pdf)
- Spécification OpenAPI: [`docs/openapi.yaml`](docs/openapi.yaml)

Ressources CRUD génériques:

- `/api/products`
- `/api/orders`
- `/api/tickets`
- `/api/employees`
- `/api/stock`
- `/api/transfers`
- `/api/customers`
- `/api/deliveries`
- `/api/woocommerce-sites`
- `/api/invoices`
- `/api/expenses`
- `/api/notifications`

Chaque ressource supporte:

- `GET /api/{resource}?q=&page=&per_page=`
- `POST /api/{resource}`
- `GET /api/{resource}/{id}`
- `PUT/PATCH /api/{resource}/{id}`
- `DELETE /api/{resource}/{id}`
- `GET /api/{resource}/export?format=csv|xls|pdf`
- `POST /api/{resource}/import`

Endpoints spécialisés:

- `POST /api/pos/checkout`
- `POST /api/transfers/{id}/receive`
- `GET /api/invoices/{id}/pdf`
- `GET /api/analytics/dashboard`
- `GET /api/analytics/accounting`
- `GET /api/ai/insights`
- `POST /api/ai/ask`
- `POST /api/auth/2fa/setup`
- `POST /api/auth/2fa/confirm`
- `POST /api/auth/2fa/disable`
- `POST /api/woocommerce-sites/{id}/sync`
- `POST /api/woocommerce/webhook`

## Sécurité

- Requêtes SQL préparées via PDO.
- Hash des mots de passe avec `password_hash`.
- JWT HMAC SHA-256.
- Protection XSS via échappement HTML et nettoyage des champs string.
- CSRF disponible pour vues serveur.
- Rate limiting par IP et route.
- Permissions par rôle.
- Logs applicatifs et activités.

Avant production, générer un `JWT_SECRET` long et aléatoire, forcer HTTPS, configurer une passerelle email/SMS/WhatsApp, déplacer les secrets WooCommerce vers un coffre sécurisé et placer `public/` comme document root unique.

## Permissions fines

Les écrans et endpoints sont protégés par permissions:

- `products.manage`
- `stock.manage`
- `transfers.manage`
- `pos.use`
- `tickets.manage`
- `hr.manage`
- `deliveries.manage`
- `woocommerce.manage`
- `customers.manage`
- `marketing.manage`
- `accounting.manage`
- `notifications.manage`
- `users.manage`
- `analytics.view`

Le menu masque automatiquement les modules non autorisés et l'API retourne `403` si la permission manque.
