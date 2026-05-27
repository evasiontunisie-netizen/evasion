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
      deliveries: 'Livraison',
      'woocommerce-sites': 'Sites WooCommerce',
      customers: 'CRM clients',
      'marketing-campaigns': 'Marketing & analytics',
      invoices: 'Comptabilité',
      notifications: 'Notifications'
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
    },
    openCreate() {
      Swal.fire({
        title: `Créer - ${this.title}`,
        text: 'Les formulaires dynamiques peuvent être branchés sur les endpoints REST générés.',
        icon: 'info',
        confirmButtonColor: '#ff4d19'
      });
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
