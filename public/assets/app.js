function erpApp() {
  return {
    module: 'dashboard',
    query: '',
    rows: [],
    columns: ['id', 'name', 'status', 'created_at'],
    darkMode: localStorage.getItem('theme') === 'dark',
    locale: localStorage.getItem('locale') || 'fr',
    token: localStorage.getItem('token') || '',
    previewMode: localStorage.getItem('previewMode') === 'true',
    authMode: 'login',
    authLoading: false,
    authForm: {
      name: '',
      email: '',
      password: '',
      otp: ''
    },
    analytics: null,
    salesChart: null,
    channelChart: null,
    productChart: null,
    posSearch: '',
    cart: [],
    demoProducts: [
      { product_id: 1, sku: 'EV-SHOE-001', name: 'Sneakers Performance', price: 249 },
      { product_id: 2, sku: 'EV-BAG-002', name: 'Sac Sport Premium', price: 189 },
      { product_id: 3, sku: 'EV-CAP-003', name: 'Casquette Signature', price: 59 },
      { product_id: 4, sku: 'EV-TEE-004', name: 'T-shirt Training', price: 79 }
    ],
    titles: {
      dashboard: 'Dashboard principal',
      products: 'Gestion produits',
      stock: 'Stock avancé',
      transfers: 'Transferts magasins',
      pos: 'Caisse POS',
      tickets: 'Tickets & SAV',
      employees: 'Ressources humaines',
      users: 'Utilisateurs',
      deliveries: 'Livraison',
      'woocommerce-sites': 'Sites WooCommerce',
      customers: 'CRM clients',
      'marketing-campaigns': 'Marketing & analytics',
      invoices: 'Comptabilité',
      notifications: 'Notifications'
    },
    formSchemas: {
      products: [
        { name: 'name', label: 'Nom produit', required: true },
        { name: 'sku', label: 'SKU', required: true },
        { name: 'barcode', label: 'Barcode' },
        { name: 'category_id', label: 'ID catégorie', type: 'number' },
        { name: 'brand_id', label: 'ID marque', type: 'number' },
        { name: 'supplier_id', label: 'ID fournisseur', type: 'number' },
        { name: 'purchase_price', label: 'Prix achat', type: 'number', step: '0.001', value: 0 },
        { name: 'sale_price', label: 'Prix vente', type: 'number', step: '0.001', value: 0 },
        { name: 'minimum_stock', label: 'Stock minimum', type: 'number', value: 0 },
        { name: 'status', label: 'Statut', type: 'select', options: [['active', 'Actif'], ['draft', 'Brouillon'], ['archived', 'Archivé']] }
      ],
      stock: [
        { name: 'product_id', label: 'ID produit', type: 'number', required: true },
        { name: 'warehouse_id', label: 'ID stock/showroom', type: 'number', required: true },
        { name: 'quantity', label: 'Quantité', type: 'number', value: 0 },
        { name: 'reserved_quantity', label: 'Réservé', type: 'number', value: 0 },
        { name: 'sku_snapshot', label: 'SKU' }
      ],
      transfers: [
        { name: 'reference', label: 'Référence', required: true, value: `TR-${Date.now()}` },
        { name: 'from_warehouse_id', label: 'Depuis stock ID', type: 'number', required: true, value: 1 },
        { name: 'to_warehouse_id', label: 'Vers stock ID', type: 'number', required: true, value: 2 },
        { name: 'status', label: 'Statut', type: 'select', options: [['pending', 'En attente'], ['validated', 'Validé'], ['shipped', 'Expédié'], ['received', 'Reçu'], ['cancelled', 'Annulé']] },
        { name: 'notes', label: 'Commentaires', type: 'textarea' }
      ],
      tickets: [
        { name: 'subject', label: 'Sujet', required: true },
        { name: 'customer_id', label: 'ID client', type: 'number' },
        { name: 'assigned_to', label: 'ID employé assigné', type: 'number' },
        { name: 'category', label: 'Catégorie', type: 'select', options: [['sav', 'SAV'], ['delivery', 'Livraison'], ['defective_product', 'Produit défectueux'], ['refund', 'Remboursement'], ['complaint', 'Réclamation'], ['technical_support', 'Assistance technique']] },
        { name: 'priority', label: 'Priorité', type: 'select', options: [['low', 'Basse'], ['medium', 'Moyenne'], ['high', 'Haute'], ['urgent', 'Urgente']] },
        { name: 'status', label: 'Statut', type: 'select', options: [['open', 'Ouvert'], ['in_progress', 'En cours'], ['resolved', 'Résolu'], ['closed', 'Fermé']] },
        { name: 'description', label: 'Description', type: 'textarea' }
      ],
      employees: [
        { name: 'employee_code', label: 'Code employé', required: true, value: `EMP-${Date.now()}` },
        { name: 'first_name', label: 'Prénom', required: true },
        { name: 'last_name', label: 'Nom', required: true },
        { name: 'email', label: 'Email', type: 'email' },
        { name: 'phone', label: 'Téléphone' },
        { name: 'position', label: 'Poste' },
        { name: 'salary_base', label: 'Salaire base', type: 'number', step: '0.001', value: 0 },
        { name: 'status', label: 'Statut', type: 'select', options: [['active', 'Actif'], ['on_leave', 'Congé'], ['inactive', 'Inactif']] }
      ],
      deliveries: [
        { name: 'order_id', label: 'ID commande', type: 'number', required: true },
        { name: 'tracking_number', label: 'Tracking', required: true, value: `DL-${Date.now()}` },
        { name: 'driver_id', label: 'ID livreur', type: 'number' },
        { name: 'zone', label: 'Zone' },
        { name: 'delivery_fee', label: 'Frais livraison', type: 'number', step: '0.001', value: 0 },
        { name: 'status', label: 'Statut', type: 'select', options: [['preparing', 'Préparation'], ['shipped', 'Expédiée'], ['in_delivery', 'En livraison'], ['delivered', 'Livrée'], ['returned', 'Retournée']] }
      ],
      customers: [
        { name: 'name', label: 'Nom client', required: true },
        { name: 'email', label: 'Email', type: 'email' },
        { name: 'phone', label: 'Téléphone' },
        { name: 'whatsapp', label: 'WhatsApp' },
        { name: 'city', label: 'Ville' },
        { name: 'loyalty_points', label: 'Points fidélité', type: 'number', value: 0 },
        { name: 'internal_notes', label: 'Notes internes', type: 'textarea' }
      ],
      invoices: [
        { name: 'invoice_number', label: 'Numéro facture', required: true, value: `INV-${Date.now()}` },
        { name: 'customer_id', label: 'ID client', type: 'number' },
        { name: 'order_id', label: 'ID commande', type: 'number' },
        { name: 'issue_date', label: 'Date émission', type: 'date', value: new Date().toISOString().slice(0, 10) },
        { name: 'subtotal', label: 'Sous-total', type: 'number', step: '0.001', value: 0 },
        { name: 'tax_total', label: 'TVA', type: 'number', step: '0.001', value: 0 },
        { name: 'grand_total', label: 'Total', type: 'number', step: '0.001', value: 0 },
        { name: 'status', label: 'Statut', type: 'select', options: [['draft', 'Brouillon'], ['sent', 'Envoyée'], ['paid', 'Payée'], ['cancelled', 'Annulée']] }
      ],
      expenses: [
        { name: 'label', label: 'Libellé', required: true },
        { name: 'category', label: 'Catégorie', required: true },
        { name: 'amount', label: 'Montant', type: 'number', step: '0.001', required: true },
        { name: 'tax_amount', label: 'TVA', type: 'number', step: '0.001', value: 0 },
        { name: 'expense_date', label: 'Date', type: 'date', value: new Date().toISOString().slice(0, 10) },
        { name: 'payment_method', label: 'Paiement' },
        { name: 'notes', label: 'Notes', type: 'textarea' }
      ],
      'woocommerce-sites': [
        { name: 'name', label: 'Nom boutique', required: true },
        { name: 'url', label: 'URL WordPress', required: true },
        { name: 'consumer_key', label: 'Consumer key', required: true },
        { name: 'consumer_secret', label: 'Consumer secret', required: true },
        { name: 'status', label: 'Statut', type: 'select', options: [['active', 'Actif'], ['paused', 'Pause'], ['error', 'Erreur']] }
      ],
      'marketing-campaigns': [
        { name: 'name', label: 'Campagne', required: true },
        { name: 'channel', label: 'Canal', required: true },
        { name: 'budget', label: 'Budget', type: 'number', step: '0.001', value: 0 },
        { name: 'revenue', label: 'Revenu', type: 'number', step: '0.001', value: 0 },
        { name: 'starts_at', label: 'Début', type: 'date' },
        { name: 'status', label: 'Statut', type: 'select', options: [['draft', 'Brouillon'], ['active', 'Active'], ['paused', 'Pause'], ['completed', 'Terminée']] }
      ],
      notifications: [
        { name: 'title', label: 'Titre', required: true },
        { name: 'body', label: 'Message', type: 'textarea' },
        { name: 'channel', label: 'Canal', type: 'select', options: [['in_app', 'In app'], ['email', 'Email'], ['sms', 'SMS'], ['whatsapp', 'WhatsApp']] },
        { name: 'status', label: 'Statut', type: 'select', options: [['queued', 'En attente'], ['sent', 'Envoyée'], ['read', 'Lue'], ['failed', 'Échec']] }
      ],
      users: [
        { name: 'name', label: 'Nom', required: true },
        { name: 'email', label: 'Email', type: 'email', required: true },
        { name: 'password', label: 'Mot de passe', type: 'password', required: true, value: 'ChangeMeSecure123!' },
        { name: 'role_id', label: 'ID rôle', type: 'number', value: 10 },
        { name: 'avatar_path', label: 'URL photo/avatar' },
        { name: 'status', label: 'Statut', type: 'select', options: [['active', 'Actif'], ['inactive', 'Inactif'], ['suspended', 'Suspendu']] }
      ]
    },
    get title() {
      return this.titles[this.module] || this.module;
    },
    get isAuthenticated() {
      return Boolean(this.token || this.previewMode);
    },
    get kpiCards() {
      const k = this.analytics?.kpis || {};
      return [
        { label: 'CA aujourd hui', value: this.money(k.revenue_today || 0) },
        { label: 'Commandes mois', value: k.orders_month || 0 },
        { label: 'Tickets ouverts', value: k.open_tickets || 0 },
        { label: 'Stock faible', value: k.low_stock || 0 },
        { label: 'Employés actifs', value: k.active_employees || 0 },
        { label: 'Livraisons', value: k.pending_deliveries || 0 }
      ];
    },
    get cartTotal() {
      return this.cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    },
    init() {
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
      }
      if (this.isAuthenticated) {
        this.load();
      }
      this.ensureTokenNotice();
    },
    persistTheme() {
      localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
    },
    setModule(key) {
      this.module = key;
      this.query = '';
      this.load();
    },
    headers() {
      return {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...(this.token ? { 'Authorization': `Bearer ${this.token}` } : {})
      };
    },
    async api(path, options = {}) {
      const response = await fetch(path, { ...options, headers: { ...this.headers(), ...(options.headers || {}) } });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(payload.error || 'Erreur API');
      return payload.data || payload;
    },
    async login() {
      this.authLoading = true;
      try {
        const data = await this.api('/api/auth/login', {
          method: 'POST',
          body: JSON.stringify({
            email: this.authForm.email,
            password: this.authForm.password,
            otp: this.authForm.otp
          })
        });
        if (data.requires_2fa) {
          Swal.fire('Code 2FA requis', 'Saisis ton code OTP puis reconnecte-toi.', 'info');
          return;
        }
        this.token = data.token;
        this.previewMode = false;
        localStorage.setItem('token', data.token);
        localStorage.removeItem('previewMode');
        await this.load();
        Swal.fire('Connecté', 'Bienvenue dans Evasion ERP.', 'success');
      } catch (error) {
        Swal.fire('Connexion impossible', error.message || 'Vérifie la base MySQL et tes identifiants.', 'error');
      } finally {
        this.authLoading = false;
      }
    },
    async registerAdmin() {
      this.authLoading = true;
      try {
        await this.api('/api/auth/register-admin', {
          method: 'POST',
          body: JSON.stringify({
            name: this.authForm.name,
            email: this.authForm.email,
            password: this.authForm.password
          })
        });
        this.authMode = 'login';
        Swal.fire('Admin créé', 'Tu peux maintenant te connecter avec ce compte.', 'success');
      } catch (error) {
        Swal.fire('Création impossible', error.message || 'Importe le schéma SQL ou vérifie qu’aucun admin n’existe déjà.', 'error');
      } finally {
        this.authLoading = false;
      }
    },
    enterPreview() {
      this.previewMode = true;
      localStorage.setItem('previewMode', 'true');
      this.load();
    },
    logout() {
      this.token = '';
      this.previewMode = false;
      localStorage.removeItem('token');
      localStorage.removeItem('previewMode');
      this.rows = [];
      this.analytics = null;
      this.module = 'dashboard';
    },
    async load() {
      if (!this.isAuthenticated) return;
      if (this.module === 'dashboard') {
        await this.loadAnalytics();
        return;
      }
      if (this.module === 'pos') return;
      try {
        const data = await this.api(`/api/${this.module}?q=${encodeURIComponent(this.query)}`);
        this.rows = data.items || [];
        this.columns = this.rows[0] ? Object.keys(this.rows[0]).slice(0, 6) : ['id', 'name', 'status', 'created_at'];
      } catch (error) {
        this.rows = [];
      }
    },
    async loadAnalytics() {
      try {
        this.analytics = await this.api('/api/analytics/dashboard');
      } catch (error) {
        this.analytics = { kpis: {}, sales_series: [], sales_by_channel: [] };
      }
      this.$nextTick(() => this.renderCharts());
    },
    renderCharts() {
      const sales = this.analytics?.sales_series || [];
      const channels = this.analytics?.sales_by_channel || [];
      const salesCanvas = document.getElementById('salesChart');
      const channelCanvas = document.getElementById('channelChart');
      if (salesCanvas) {
        this.salesChart?.destroy();
        this.salesChart = new Chart(salesCanvas, {
          type: 'line',
          data: { labels: sales.map(row => row.day), datasets: [{ label: 'CA', data: sales.map(row => row.revenue), borderColor: '#ff4d19', backgroundColor: 'rgba(255,77,25,.12)', fill: true, tension: .35 }] },
          options: { plugins: { legend: { display: false } }, responsive: true }
        });
      }
      if (channelCanvas) {
        this.channelChart?.destroy();
        this.channelChart = new Chart(channelCanvas, {
          type: 'doughnut',
          data: { labels: channels.map(row => row.channel), datasets: [{ data: channels.map(row => row.revenue), backgroundColor: ['#111111', '#ff4d19', '#f59e0b', '#737373'] }] },
          options: { plugins: { legend: { position: 'bottom' } } }
        });
      }
      const productCanvas = document.getElementById('productChart');
      const categories = this.analytics?.product_categories || [];
      if (productCanvas) {
        this.productChart?.destroy();
        this.productChart = new Chart(productCanvas, {
          type: 'bar',
          data: {
            labels: categories.map(row => row.name),
            datasets: [{ label: 'Produits', data: categories.map(row => row.products), backgroundColor: '#ff4d19', borderRadius: 12 }]
          },
          options: { plugins: { legend: { display: false } }, responsive: true, scales: { y: { beginAtZero: true } } }
        });
      }
    },
    async importCsv(event) {
      const file = event.target.files?.[0];
      event.target.value = '';
      if (!file) return;
      if (!this.token) {
        Swal.fire('Connexion requise', 'Connecte-toi en admin pour importer le CSV WooCommerce.', 'info');
        return;
      }

      const form = new FormData();
      form.append('file', file);
      try {
        const response = await fetch('/api/products/import', {
          method: 'POST',
          headers: { 'Authorization': `Bearer ${this.token}`, 'Accept': 'application/json' },
          body: form
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.error || 'Import impossible');
        await this.load();
        Swal.fire('Import terminé', `${payload.data.imported} produits et ${payload.data.images} images importés depuis le CSV WooCommerce.`, 'success');
      } catch (error) {
        Swal.fire('Erreur import', error.message || 'Vérifie le fichier CSV et la base de données.', 'error');
      }
    },
    async openCreate() {
      if (this.previewMode && !this.token) {
        Swal.fire('Mode aperçu', 'Connecte-toi avec un compte admin pour créer des données réelles.', 'info');
        return;
      }

      const schema = this.formSchemas[this.module];
      if (!schema) {
        Swal.fire('Module non disponible', 'Ce module n’a pas encore de formulaire rapide.', 'info');
        return;
      }

      const result = await Swal.fire({
        title: `Créer - ${this.title}`,
        html: `<div class="swal-form-grid">${schema.map(field => this.formFieldHtml(field)).join('')}</div>`,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Enregistrer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#ff4d19',
        preConfirm: () => this.collectCreatePayload(schema)
      });

      if (!result.isConfirmed) return;

      try {
        await this.api(`/api/${this.module}`, { method: 'POST', body: JSON.stringify(result.value) });
        await this.load();
        Swal.fire('Créé', `${this.title} enregistré avec succès.`, 'success');
      } catch (error) {
        Swal.fire('Erreur création', error.message || 'Vérifie les champs obligatoires et la base de données.', 'error');
      }
    },
    formFieldHtml(field) {
      const id = `swal-field-${field.name.replace(/[^a-z0-9_-]/gi, '-')}`;
      const required = field.required ? 'required' : '';
      const value = this.escapeHtml(field.value ?? '');
      const label = `${this.escapeHtml(field.label)}${field.required ? ' *' : ''}`;

      if (field.type === 'textarea') {
        return `<label class="swal-form-label" for="${id}">${label}<textarea id="${id}" data-name="${field.name}" data-type="textarea" class="swal2-textarea" ${required}>${value}</textarea></label>`;
      }

      if (field.type === 'select') {
        const options = (field.options || []).map(([optionValue, optionLabel]) => {
          const selected = optionValue === (field.value ?? (field.options[0] ? field.options[0][0] : '')) ? 'selected' : '';
          return `<option value="${this.escapeHtml(optionValue)}" ${selected}>${this.escapeHtml(optionLabel)}</option>`;
        }).join('');
        return `<label class="swal-form-label" for="${id}">${label}<select id="${id}" data-name="${field.name}" data-type="select" class="swal2-input" ${required}>${options}</select></label>`;
      }

      return `<label class="swal-form-label" for="${id}">${label}<input id="${id}" data-name="${field.name}" data-type="${field.type || 'text'}" class="swal2-input" type="${field.type || 'text'}" step="${field.step || '1'}" value="${value}" ${required}></label>`;
    },
    collectCreatePayload(schema) {
      const payload = {};
      for (const field of schema) {
        const id = `swal-field-${field.name.replace(/[^a-z0-9_-]/gi, '-')}`;
        const element = document.getElementById(id);
        if (!element) continue;

        const rawValue = element.value.trim();
        if (field.required && rawValue === '') {
          Swal.showValidationMessage(`${field.label} est obligatoire`);
          return false;
        }
        if (rawValue === '') continue;

        payload[field.name] = field.type === 'number' ? Number(rawValue) : rawValue;
      }

      return payload;
    },
    escapeHtml(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    },
    exportData(format) {
      if (['dashboard', 'pos'].includes(this.module)) return;
      window.open(`/api/${this.module}/export?format=${format}&q=${encodeURIComponent(this.query)}`, '_blank');
    },
    addToCart(product) {
      const existing = this.cart.find(item => item.sku === product.sku);
      if (existing) existing.quantity += 1;
      else this.cart.push({ ...product, quantity: 1 });
    },
    async checkout() {
      if (!this.cart.length) return;
      try {
        await this.api('/api/pos/checkout', { method: 'POST', body: JSON.stringify({ warehouse_id: 1, items: this.cart }) });
        this.cart = [];
        Swal.fire('Vente enregistrée', 'Ticket et stock mis à jour.', 'success');
      } catch (error) {
        Swal.fire('Mode démonstration', 'Connectez vous avec un JWT pour encaisser via API.', 'info');
      }
    },
    money(value) {
      return new Intl.NumberFormat(this.locale === 'en' ? 'en-US' : 'fr-FR', { style: 'currency', currency: 'TND' }).format(value);
    },
    ensureTokenNotice() {
      if (!this.token) {
        console.info('Set localStorage.token with a JWT from /api/auth/login to use secured APIs.');
      }
    }
  };
}
