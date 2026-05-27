// ============================================================
// ERP PRO - Core JavaScript Library
// ============================================================

const ERP = {
  version: '1.0.0',
  baseUrl: window.location.origin + '/erp/api',

  // ============================================================
  // TOKEN MANAGEMENT
  // ============================================================
  getToken() { return localStorage.getItem('erp_token'); },
  getUser()  { try { return JSON.parse(localStorage.getItem('erp_user') || '{}'); } catch { return {}; } },

  async refreshToken() {
    const refresh = localStorage.getItem('erp_refresh');
    if (!refresh) { this.logout(); return null; }
    try {
      const res  = await fetch(`${this.baseUrl}/auth/refresh`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: refresh })
      });
      const data = await res.json();
      if (!res.ok) throw new Error();
      localStorage.setItem('erp_token',  data.data.access_token);
      localStorage.setItem('erp_refresh',data.data.refresh_token);
      return data.data.access_token;
    } catch {
      this.logout();
      return null;
    }
  },

  logout() {
    localStorage.removeItem('erp_token');
    localStorage.removeItem('erp_refresh');
    localStorage.removeItem('erp_user');
    window.location.href = '/erp/public/login.html';
  },

  // ============================================================
  // HTTP CLIENT
  // ============================================================
  async request(method, endpoint, data = null, retry = true) {
    const token = this.getToken();
    if (!token) { this.logout(); return null; }

    const opts = {
      method,
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type':  'application/json',
        'Accept':        'application/json',
      }
    };
    if (data && method !== 'GET') opts.body = JSON.stringify(data);

    const url = endpoint.startsWith('http') ? endpoint : `${this.baseUrl}/${endpoint}`;
    const res = await fetch(url, opts);

    if (res.status === 401 && retry) {
      const newToken = await this.refreshToken();
      if (newToken) return this.request(method, endpoint, data, false);
      return null;
    }

    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw { status: res.status, message: json.message || 'Erreur', errors: json.errors };
    return json.data ?? json;
  },

  get(endpoint, params = {}) {
    const qs = new URLSearchParams(params).toString();
    return this.request('GET', `${endpoint}${qs ? '?' + qs : ''}`);
  },
  post(endpoint, data)   { return this.request('POST',   endpoint, data); },
  put(endpoint, data)    { return this.request('PUT',    endpoint, data); },
  patch(endpoint, data)  { return this.request('PATCH',  endpoint, data); },
  delete(endpoint)       { return this.request('DELETE', endpoint); },

  // ============================================================
  // FORMATTING
  // ============================================================
  currency(amount, currency = 'MAD') {
    return new Intl.NumberFormat('fr-MA', { style: 'currency', currency }).format(amount || 0);
  },
  number(n, decimals = 0) {
    return new Intl.NumberFormat('fr-MA', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(n || 0);
  },
  date(d, format = 'short') {
    if (!d) return '—';
    return new Intl.DateTimeFormat('fr-FR', format === 'short'
      ? { day: '2-digit', month: '2-digit', year: 'numeric' }
      : { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }
    ).format(new Date(d));
  },
  timeAgo(d) {
    if (!d) return '';
    const diff = (Date.now() - new Date(d).getTime()) / 1000;
    if (diff < 60)     return 'À l\'instant';
    if (diff < 3600)   return `Il y a ${Math.floor(diff/60)} min`;
    if (diff < 86400)  return `Il y a ${Math.floor(diff/3600)}h`;
    if (diff < 604800) return `Il y a ${Math.floor(diff/86400)}j`;
    return this.date(d);
  },

  // ============================================================
  // STATUS HELPERS
  // ============================================================
  statusBadge(status, type = 'order') {
    const maps = {
      order: {
        pending:    { cls: 'badge-yellow', label: 'En attente' },
        processing: { cls: 'badge-blue',   label: 'En cours' },
        completed:  { cls: 'badge-green',  label: 'Terminé' },
        cancelled:  { cls: 'badge-red',    label: 'Annulé' },
        refunded:   { cls: 'badge-purple', label: 'Remboursé' },
        on_hold:    { cls: 'badge-gray',   label: 'En attente' },
      },
      ticket: {
        open:        { cls: 'badge-red',    label: 'Ouvert' },
        in_progress: { cls: 'badge-blue',   label: 'En cours' },
        resolved:    { cls: 'badge-green',  label: 'Résolu' },
        closed:      { cls: 'badge-gray',   label: 'Fermé' },
      },
      priority: {
        low:    { cls: 'badge-gray',   label: 'Basse' },
        medium: { cls: 'badge-yellow', label: 'Moyenne' },
        high:   { cls: 'badge-orange', label: 'Haute' },
        urgent: { cls: 'badge-red',    label: 'Urgente' },
      },
      transfer: {
        pending:   { cls: 'badge-yellow', label: 'En attente' },
        validated: { cls: 'badge-blue',   label: 'Validé' },
        shipped:   { cls: 'badge-purple', label: 'Expédié' },
        received:  { cls: 'badge-green',  label: 'Reçu' },
        cancelled: { cls: 'badge-red',    label: 'Annulé' },
      },
      delivery: {
        preparing:   { cls: 'badge-yellow', label: 'Préparation' },
        shipped:     { cls: 'badge-blue',   label: 'Expédiée' },
        in_delivery: { cls: 'badge-purple', label: 'En livraison' },
        delivered:   { cls: 'badge-green',  label: 'Livrée' },
        returned:    { cls: 'badge-red',    label: 'Retournée' },
      }
    };
    const m = maps[type] || maps.order;
    const s = m[status] || { cls: 'badge-gray', label: status };
    return `<span class="badge ${s.cls}">${s.label}</span>`;
  },

  // ============================================================
  // NOTIFICATIONS (Toast)
  // ============================================================
  toast(message, type = 'success', duration = 3500) {
    const colors = {
      success: 'bg-emerald-500',
      error:   'bg-red-500',
      warning: 'bg-amber-500',
      info:    'bg-blue-500',
    };
    const icons = {
      success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
      error:   '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>',
      warning: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
      info:    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    };

    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'fixed bottom-6 right-6 z-[9999] flex flex-col gap-2 pointer-events-none';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-xl text-white text-sm font-medium shadow-lg ${colors[type]} transform translate-x-full transition-all duration-300`;
    toast.innerHTML = `
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        ${icons[type] || icons.info}
      </svg>
      <span>${message}</span>
      <button onclick="this.parentElement.remove()" class="ml-1 opacity-75 hover:opacity-100">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
      </button>
    `;
    container.appendChild(toast);
    requestAnimationFrame(() => { toast.classList.remove('translate-x-full'); });
    setTimeout(() => {
      toast.classList.add('translate-x-full', 'opacity-0');
      setTimeout(() => toast.remove(), 300);
    }, duration);
  },

  // ============================================================
  // CONFIRM DIALOG
  // ============================================================
  confirm(message, title = 'Confirmation') {
    return new Promise(resolve => {
      const overlay = document.createElement('div');
      overlay.className = 'fixed inset-0 bg-black/60 backdrop-blur-sm z-[9998] flex items-center justify-center p-4';
      overlay.innerHTML = `
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-sm w-full p-6 transform scale-95 transition-transform duration-200">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
            </div>
            <h3 class="font-semibold text-slate-900 dark:text-white">${title}</h3>
          </div>
          <p class="text-slate-600 dark:text-slate-300 text-sm mb-6">${message}</p>
          <div class="flex gap-3 justify-end">
            <button id="confirm-cancel" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Annuler</button>
            <button id="confirm-ok" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-red-500 hover:bg-red-600 transition-colors">Confirmer</button>
          </div>
        </div>
      `;
      document.body.appendChild(overlay);
      requestAnimationFrame(() => overlay.querySelector('.transform').classList.remove('scale-95'));
      overlay.querySelector('#confirm-ok').addEventListener('click', () => { overlay.remove(); resolve(true); });
      overlay.querySelector('#confirm-cancel').addEventListener('click', () => { overlay.remove(); resolve(false); });
      overlay.addEventListener('click', e => { if (e.target === overlay) { overlay.remove(); resolve(false); } });
    });
  },

  // ============================================================
  // DEBOUNCE / THROTTLE
  // ============================================================
  debounce(fn, ms = 300) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), ms); };
  },

  // ============================================================
  // DARK MODE
  // ============================================================
  initTheme() {
    const saved = localStorage.getItem('erp_theme') || 'system';
    const dark   = saved === 'dark' || (saved === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
  },
  toggleTheme() {
    const isDark = document.documentElement.classList.contains('dark');
    const theme  = isDark ? 'light' : 'dark';
    localStorage.setItem('erp_theme', theme);
    document.documentElement.classList.toggle('dark', !isDark);
  },

  // ============================================================
  // PERMISSIONS
  // ============================================================
  can(permission) {
    const perms = JSON.parse(localStorage.getItem('erp_permissions') || '[]');
    const user  = this.getUser();
    if (user.role === 'super_admin') return true;
    return perms.includes(permission);
  },
};

// Init theme on load
ERP.initTheme();

// Auto-refresh token 5min before expiry
setInterval(async () => {
  const token = ERP.getToken();
  if (!token) return;
  try {
    const payload = JSON.parse(atob(token.split('.')[1]));
    if (payload.exp - Date.now()/1000 < 300) await ERP.refreshToken();
  } catch {}
}, 60000);
