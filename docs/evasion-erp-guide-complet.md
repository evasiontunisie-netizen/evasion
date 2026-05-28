# Evasion ERP - Resume fonctionnel et guide API

Version: 1.0  
Stack: PHP 8, MVC, MySQL, PDO, JWT, TailwindCSS, Alpine.js, Chart.js, SweetAlert

## 1. Objectif du projet

Evasion ERP est une application de gestion d'entreprise pour plusieurs showrooms physiques et plusieurs boutiques WooCommerce.

Elle couvre:

- gestion produits;
- stocks multi-emplacements;
- transferts entre magasins;
- caisse POS;
- tickets SAV;
- RH;
- livraisons;
- CRM clients;
- WooCommerce;
- marketing;
- analytics;
- comptabilite;
- notifications;
- API REST securisee.

## 2. Ce qui fonctionne actuellement

### Authentification

- Ecran login visible.
- Creation du premier Super Admin.
- Connexion par email + mot de passe.
- JWT stocke cote navigateur.
- Deconnexion.
- Roles et permissions en base.
- Hash des mots de passe avec `password_hash`.
- Middleware API JWT.

### Interface

- Dashboard moderne responsive.
- Sidebar premium.
- Dark mode.
- Mode apercu UI.
- Interface desktop, tablette et mobile.
- PWA avec service worker.
- Recherche instantanee dans les modules.

### Dashboard et courbes

- KPI chiffre d'affaires.
- Commandes du mois.
- Tickets ouverts.
- Stock faible.
- Employes actifs.
- Livraisons en cours.
- Courbe ventes 30 jours.
- Graphique ventes par canal.
- Graphique produits par categorie.

### Produits

- Creation produit depuis l'interface.
- Liste produits sous forme de cartes.
- Photos produits.
- Prix regulier.
- Prix promo.
- SKU / UGS.
- Barcode.
- Categories.
- Marques.
- Fournisseurs.
- Stock minimum.
- Statut produit.
- Export CSV, XLS, PDF.

### Import CSV WooCommerce

Le format CSV WooCommerce francais est reconnu.

Colonnes supportees:

- `ID`
- `Type`
- `UGS`
- `GTIN, UPC, EAN ou ISBN`
- `Nom`
- `Publie`
- `Description courte`
- `Description`
- `En stock ?`
- `Stock`
- `Montant de stock faible`
- `Tarif promo`
- `Tarif regulier`
- `Categories`
- `Images`
- `Marques`

L'import cree ou met a jour:

- produit;
- categorie;
- marque;
- prix;
- prix promo;
- description;
- image principale et galerie;
- stock web.

### Stock

- Stock principal.
- Showroom 1.
- Showroom 2.
- Depot.
- Stock web.
- Creation stock.
- Quantite.
- Quantite reservee.
- SKU snapshot.

### Transferts magasins

- Creation transfert.
- Reference transfert.
- Stock source.
- Stock destination.
- Statuts:
  - pending;
  - validated;
  - shipped;
  - received;
  - cancelled.
- Validation reception via endpoint dedie.

### POS caisse

- Ecran caisse.
- Produits demo rapides.
- Panier.
- Total.
- Encaissement API.
- Creation commande POS.
- Decrementation stock si produit lie.

### Tickets SAV

- Creation ticket.
- Client.
- Employe assigne.
- Sujet.
- Categorie.
- Priorite.
- Statut.
- Description.

### RH

- Employes.
- Code employe.
- Nom / prenom.
- Email.
- Telephone.
- Poste.
- Salaire base.
- Statut.
- Presences.
- Salaires.
- Conges.
- Documents employe en base.

### Utilisateurs avec photos

- Module `Users`.
- Liste sous forme de cartes.
- Avatar utilisateur.
- Creation utilisateur avec:
  - nom;
  - email;
  - mot de passe;
  - role_id;
  - avatar_path;
  - statut.

### Livraison

- Commande liee.
- Tracking number.
- Zone.
- Frais livraison.
- Livreur.
- Statuts:
  - preparing;
  - shipped;
  - in_delivery;
  - delivered;
  - returned.

### CRM clients

- Nom.
- Email.
- Telephone.
- WhatsApp.
- Ville.
- Points fidelite.
- Notes internes.

### Comptabilite

- Factures.
- Depenses.
- Totaux comptables.
- TVA.
- Benefice simplifie.
- Export.

### Marketing

- Campagnes.
- Canal.
- Budget.
- Revenu.
- Statut.

### Notifications

- Notification in-app.
- Email/SMS/WhatsApp prepares en base.
- Statut notification.

## 3. Ce qui est partiel ou a connecter en production

- Synchronisation WooCommerce automatique en temps reel: structure presente, connecteur a brancher sur les vrais sites.
- Envoi email reel: SMTP/API a configurer.
- SMS reel: fournisseur a configurer.
- WhatsApp API reel: fournisseur Meta/Twilio/360dialog a configurer.
- WebSocket temps reel: publication preparee, serveur socket a deployer.
- PDF facture avance: export PDF simple pour le moment.
- 2FA: verification backend presente, interface de setup du secret a completer.
- Permissions fines par ecran: base et middleware presents, regles a durcir selon organisation.

## 4. Installation locale avec XAMPP

### Copier le projet

Mettre le dossier dans:

```text
C:\xampp\htdocs\evasion-main
```

### Configurer `.env`

Copier:

```powershell
copy .env.example .env
```

Configuration XAMPP classique:

```env
APP_URL=http://localhost:8080
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=evasion_erp
DB_USERNAME=root
DB_PASSWORD=
JWT_SECRET=change-this-with-a-64-char-random-secret
```

### Importer la base

Ouvrir:

```text
http://localhost/phpmyadmin
```

Importer dans l'ordre:

1. `database/schema.sql`
2. `database/seed.sql`

### Lancer le serveur

Dans PowerShell:

```powershell
cd C:\xampp\htdocs\evasion-main
php -S localhost:8080 -t public
```

Ouvrir:

```text
http://localhost:8080
```

## 5. Creation admin

Via l'interface:

1. ouvrir `http://localhost:8080`;
2. cliquer `Premier admin`;
3. saisir:

```text
Nom: Admin
Email: admin@test.com
Mot de passe: AdminSecure123!
```

Via SQL direct:

```sql
DELETE FROM users WHERE email = 'admin@test.com';

INSERT INTO users (
    role_id,
    name,
    email,
    password_hash,
    status
)
SELECT
    id,
    'Admin',
    'admin@test.com',
    '$2y$10$KboHG.9rJAyRGGBWtcoTG.eheghDMXF9j0kpStGdFVgKGiulCkB1y',
    'active'
FROM roles
WHERE slug = 'super-admin';
```

Identifiants:

```text
Email: admin@test.com
Mot de passe: AdminSecure123!
```

## 6. Guide API REST

Base URL locale:

```text
http://localhost:8080/api
```

Header requis pour les routes securisees:

```http
Authorization: Bearer JWT_TOKEN
Accept: application/json
Content-Type: application/json
```

### Health check

```http
GET /api/health
```

Exemple:

```bash
curl http://localhost:8080/api/health
```

### Login

```http
POST /api/auth/login
```

Body:

```json
{
  "email": "admin@test.com",
  "password": "AdminSecure123!"
}
```

Exemple:

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"admin@test.com\",\"password\":\"AdminSecure123!\"}"
```

Reponse:

```json
{
  "success": true,
  "data": {
    "token": "JWT_TOKEN",
    "user": {
      "id": 1,
      "name": "Admin",
      "email": "admin@test.com",
      "role": "super-admin"
    }
  }
}
```

### Profil courant

```http
GET /api/auth/me
```

### Creation premier admin

```http
POST /api/auth/register-admin
```

Body:

```json
{
  "name": "Admin",
  "email": "admin@test.com",
  "password": "AdminSecure123!"
}
```

## 7. Ressources CRUD disponibles

Toutes ces routes supportent:

```http
GET    /api/{resource}
POST   /api/{resource}
GET    /api/{resource}/{id}
PUT    /api/{resource}/{id}
PATCH  /api/{resource}/{id}
DELETE /api/{resource}/{id}
GET    /api/{resource}/export?format=csv
GET    /api/{resource}/export?format=xls
GET    /api/{resource}/export?format=pdf
POST   /api/{resource}/import
```

Ressources:

- `products`
- `product-variants`
- `product-images`
- `categories`
- `brands`
- `suppliers`
- `warehouses`
- `stock`
- `stock-movements`
- `transfers`
- `transfer-items`
- `tickets`
- `ticket-messages`
- `departments`
- `employees`
- `employee-documents`
- `attendance`
- `leaves`
- `salaries`
- `deliveries`
- `orders`
- `order-items`
- `payments`
- `customers`
- `invoices`
- `expenses`
- `notifications`
- `woocommerce-sites`
- `marketing-campaigns`
- `users`

## 8. Exemples API reels

### Creer produit

```bash
curl -X POST http://localhost:8080/api/products \
  -H "Authorization: Bearer JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\":\"TAPIS PRO BT 6100\",
    \"sku\":\"BT-6100\",
    \"sale_price\":15999,
    \"promo_price\":13800,
    \"minimum_stock\":2,
    \"status\":\"active\"
  }"
```

### Importer CSV WooCommerce produits

```bash
curl -X POST http://localhost:8080/api/products/import \
  -H "Authorization: Bearer JWT_TOKEN" \
  -F "file=@wc-product-export.csv"
```

### Creer image produit

```bash
curl -X POST http://localhost:8080/api/product-images \
  -H "Authorization: Bearer JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"product_id\":1,
    \"path\":\"https://fitness-tn.com/wp-content/uploads/image.jpg\",
    \"sort_order\":0
  }"
```

### Creer stock

```bash
curl -X POST http://localhost:8080/api/stock \
  -H "Authorization: Bearer JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"product_id\":1,
    \"warehouse_id\":1,
    \"quantity\":10,
    \"reserved_quantity\":0,
    \"sku_snapshot\":\"BT-6100\"
  }"
```

### Creer transfert

```bash
curl -X POST http://localhost:8080/api/transfers \
  -H "Authorization: Bearer JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"reference\":\"TR-001\",
    \"from_warehouse_id\":1,
    \"to_warehouse_id\":2,
    \"status\":\"pending\",
    \"notes\":\"Transfert depot vers showroom\"
  }"
```

### Reception transfert

```bash
curl -X POST http://localhost:8080/api/transfers/1/receive \
  -H "Authorization: Bearer JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"signature_path\":\"storage/uploads/signatures/signature.png\"}"
```

### Creer ticket SAV

```bash
curl -X POST http://localhost:8080/api/tickets \
  -H "Authorization: Bearer JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"subject\":\"Produit defectueux\",
    \"category\":\"defective_product\",
    \"priority\":\"urgent\",
    \"status\":\"open\",
    \"description\":\"Client signale un probleme moteur.\"
  }"
```

### Creer utilisateur avec photo

```bash
curl -X POST http://localhost:8080/api/users \
  -H "Authorization: Bearer JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"role_id\":10,
    \"name\":\"Employe Test\",
    \"email\":\"employe@test.com\",
    \"password\":\"ChangeMeSecure123!\",
    \"avatar_path\":\"https://example.com/avatar.jpg\",
    \"status\":\"active\"
  }"
```

### Dashboard analytics

```http
GET /api/analytics/dashboard
```

Retourne:

- KPI;
- ventes 30 jours;
- ventes par canal;
- top produits;
- ventes par showroom;
- produits par categorie.

### Comptabilite

```http
GET /api/analytics/accounting
```

Retourne:

- revenus;
- depenses;
- benefice;
- TVA.

### POS checkout

```bash
curl -X POST http://localhost:8080/api/pos/checkout \
  -H "Authorization: Bearer JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"warehouse_id\":1,
    \"items\":[
      {\"product_id\":1,\"sku\":\"BT-6100\",\"name\":\"TAPIS PRO BT 6100\",\"quantity\":1,\"price\":13800}
    ]
  }"
```

### WooCommerce sync

```bash
curl -X POST http://localhost:8080/api/woocommerce-sites/1/sync \
  -H "Authorization: Bearer JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"type\":\"products\"}"
```

### WooCommerce webhook

```http
POST /api/woocommerce/webhook
```

Route publique pour recevoir les webhooks WooCommerce.

## 9. Schema base de donnees

Tables principales:

- `users`
- `roles`
- `permissions`
- `products`
- `product_images`
- `product_variants`
- `categories`
- `brands`
- `suppliers`
- `warehouses`
- `stock`
- `stock_movements`
- `transfers`
- `transfer_items`
- `tickets`
- `ticket_messages`
- `employees`
- `attendance`
- `salaries`
- `deliveries`
- `orders`
- `order_items`
- `customers`
- `invoices`
- `expenses`
- `notifications`
- `logs`
- `woocommerce_sites`
- `marketing_campaigns`

## 10. Recommandations production

- Utiliser HTTPS obligatoire.
- Generer un `JWT_SECRET` fort.
- Mettre `public/` comme document root.
- Desactiver `APP_DEBUG`.
- Configurer sauvegardes MySQL.
- Ajouter SMTP pour emails.
- Ajouter fournisseur SMS/WhatsApp.
- Ajouter worker de sync WooCommerce.
- Ajouter serveur WebSocket.
- Ajouter tests automatises API.
- Proteger les secrets WooCommerce dans un coffre.
