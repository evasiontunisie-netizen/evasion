# ERP Pro — Système de Gestion d'Entreprise

> Plateforme ERP complète et moderne pour la gestion de showrooms, boutiques eCommerce WooCommerce, stocks, SAV, RH, comptabilité et plus encore.

---

## 🚀 Fonctionnalités

| Module | Description |
|--------|-------------|
| 🔐 **Authentification** | JWT, 2FA, rôles & permissions, rate limiting |
| 📦 **Produits** | CRUD, variantes, barcode, QR code, galerie |
| 🏪 **Stock** | Multi-entrepôts, mouvements, alertes, inventaire |
| 🔄 **Transferts** | Entre showrooms, validation, bon PDF |
| 🧾 **Caisse POS** | Vente rapide, scan, mixte paiement, ticket |
| 📋 **Commandes** | POS + WooCommerce, statuts, suivi |
| 🎫 **Tickets SAV** | Support client, priorités, chat interne |
| 👥 **RH** | Employés, présence, salaires, congés |
| 🚚 **Livraison** | Livreurs, suivi GPS, signature |
| 👤 **CRM Clients** | Historique, fidélité, WhatsApp, notes |
| 🛒 **WooCommerce** | Multi-sites, sync produits/commandes/stock |
| 📊 **Analytics** | Dashboard, KPIs, graphiques avancés |
| 💰 **Comptabilité** | Revenus, dépenses, TVA, factures |
| 🔔 **Notifications** | Temps réel, email, SMS, WhatsApp |

---

## 🛠️ Stack Technique

**Backend:**
- PHP 8+ (MVC propre)
- MySQL 8 (PDO sécurisé)
- JWT Authentication
- API REST complète
- Rate limiting & sécurité

**Frontend:**
- TailwindCSS (responsive)
- Alpine.js (interactivité)
- Chart.js (graphiques)
- PWA (offline support)
- Dark Mode

---

## 📥 Installation

### 1. Cloner et configurer

```bash
git clone <repo>
cd erp
cp .env.example .env
# Modifier .env avec vos paramètres
```

### 2. Base de données

```sql
mysql -u root -p < database/schema.sql
mysql -u root -p erp_pro < database/seed_admin.sql
```

### 3. Configuration serveur (Apache)

```apache
<VirtualHost *:80>
    ServerName erp.local
    DocumentRoot /var/www/erp
    
    <Directory /var/www/erp>
        AllowOverride All
        Require all granted
    </Directory>
    
    Alias /erp/api /var/www/erp/api
    
    <Directory /var/www/erp/api>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 4. Permissions

```bash
chmod -R 755 storage/
chmod -R 777 storage/uploads storage/logs storage/cache
```

---

## 🔑 Accès par défaut

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Super Admin | admin@erppro.ma | password |
| Admin | admin2@erppro.ma | password |
| Caissier | caissier@erppro.ma | password |
| Support | support@erppro.ma | password |

> ⚠️ **Changez ces mots de passe immédiatement en production !**

---

## 📡 API REST

Base URL: `https://votre-domaine.com/erp/api/`

### Authentification

```http
POST /auth/login
Content-Type: application/json

{
  "email": "admin@erppro.ma",
  "password": "password"
}
```

### Endpoints principaux

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/auth/login` | Connexion |
| GET | `/auth/me` | Profil utilisateur |
| GET | `/products` | Liste produits |
| POST | `/products` | Créer produit |
| GET | `/orders` | Liste commandes |
| POST | `/orders` | Créer commande POS |
| GET | `/stock` | Stock multi-entrepôts |
| POST | `/stock/adjust` | Ajuster stock |
| GET | `/tickets` | Liste tickets SAV |
| GET | `/analytics/dashboard` | Dashboard data |

---

## 🔒 Sécurité

- ✅ Anti SQL Injection (PDO prepared statements)
- ✅ Anti XSS (htmlspecialchars)
- ✅ CSRF Protection
- ✅ Rate Limiting
- ✅ JWT avec refresh tokens
- ✅ Bcrypt password hashing
- ✅ Validation backend + frontend
- ✅ CORS configuré
- ✅ Headers sécurité HTTP

---

## 📱 PWA

L'application est installable comme une app native sur:
- 📱 iOS (Safari → "Ajouter à l'écran d'accueil")
- 🤖 Android (Chrome → "Installer")
- 💻 Desktop (Chrome/Edge → icône d'installation)

---

## 🌍 Multi-langue

Langues supportées: **Français** | **English** | **العربية**

---

## 📄 License

Propriétaire — Tous droits réservés © 2024
