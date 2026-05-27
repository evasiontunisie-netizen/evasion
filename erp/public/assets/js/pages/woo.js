// ============================================================
// ERP PRO - WooCommerce Integration Page
// ============================================================
window.load_woo = async function() {
  const app = document.querySelector('[x-data]').__x.$data;
  app.pages.woo = `
  <div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div><h2 class="text-2xl font-bold text-slate-900 dark:text-white">Intégration WooCommerce</h2><p class="text-slate-500 text-sm mt-0.5">Synchronisation multi-boutiques</p></div>
      <button onclick="showAddSiteModal()" class="btn btn-primary self-start">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Ajouter un site
      </button>
    </div>
    <div id="woo-sites-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      ${Array(3).fill(0).map(()=>'<div class="skeleton h-48 rounded-2xl"></div>').join('')}
    </div>
  </div>

  <!-- Add Site Modal -->
  <div id="add-site-modal" class="modal-overlay" style="display:none">
    <div class="modal max-w-lg w-full">
      <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
        <h3 class="font-semibold text-slate-900 dark:text-white">Ajouter un site WooCommerce</h3>
        <button onclick="document.getElementById('add-site-modal').style.display='none'" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">✕</button>
      </div>
      <form onsubmit="submitWooSite(event)" class="p-6 space-y-4">
        <div><label class="form-label">Nom du site *</label><input name="name" type="text" class="form-input" placeholder="Ex: Boutique Nike Casa" required /></div>
        <div><label class="form-label">URL du site *</label><input name="url" type="url" class="form-input" placeholder="https://monsite.ma" required /></div>
        <div><label class="form-label">Consumer Key *</label><input name="consumer_key" type="text" class="form-input" placeholder="ck_..." required /></div>
        <div><label class="form-label">Consumer Secret *</label><input name="consumer_secret" type="password" class="form-input" placeholder="cs_..." required /></div>
        <div><label class="form-label">Entrepôt associé</label><select name="warehouse_id" class="form-input form-select"><option value="">— Sélectionner —</option><option value="1">Showroom Casa</option><option value="2">Showroom Rabat</option><option value="4">Stock Web</option></select></div>
        <div class="flex justify-end gap-3">
          <button type="button" onclick="document.getElementById('add-site-modal').style.display='none'" class="btn btn-secondary">Annuler</button>
          <button type="submit" class="btn btn-primary">Connecter</button>
        </div>
      </form>
    </div>
  </div>`;
  setTimeout(fetchWooSites, 50);
};

window.fetchWooSites = async function() {
  const grid = document.getElementById('woo-sites-grid');
  if (!grid) return;
  try {
    const sites = await ERP.get('woo/sites');
    if (!sites?.length) {
      grid.innerHTML = `<div class="col-span-3"><div class="card flex flex-col items-center justify-center py-16 text-center"><div class="text-5xl mb-4">🛒</div><h3 class="font-semibold text-slate-700 dark:text-slate-200 mb-2">Aucun site connecté</h3><p class="text-slate-400 text-sm mb-6">Ajoutez vos boutiques WooCommerce pour commencer la synchronisation</p><button onclick="showAddSiteModal()" class="btn btn-primary">Ajouter un site</button></div></div>`;
      return;
    }
    grid.innerHTML = sites.map(s => `
      <div class="card">
        <div class="flex items-start justify-between mb-4">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-purple-100 dark:bg-purple-900/20 flex items-center justify-center">
              <svg class="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 24 24"><path d="M7.12 0C3.77 0 0 3.77 0 7.12v9.76C0 20.23 3.77 24 7.12 24h9.76C20.23 24 24 20.23 24 16.88V7.12C24 3.77 20.23 0 16.88 0zM9.12 17.52l-2.88-9.36h1.68l1.8 6.84 1.44-4.68H9.6l.48-1.44h2.64l.48-1.44H10.2l.48-1.44h5.04l-3.12 10.56z"/></svg>
            </div>
            <div>
              <h3 class="font-semibold text-slate-800 dark:text-white">${s.name}</h3>
              <p class="text-xs text-slate-400 truncate max-w-[160px]">${s.url}</p>
            </div>
          </div>
          <span class="badge ${s.is_active ? 'badge-green' : 'badge-gray'}">${s.is_active ? 'Actif' : 'Inactif'}</span>
        </div>
        <div class="space-y-2 text-sm mb-4">
          <div class="flex justify-between text-slate-500 dark:text-slate-400"><span>Dernière sync</span><span class="font-medium">${s.last_sync ? ERP.date(s.last_sync,'long') : 'Jamais'}</span></div>
          <div class="flex justify-between text-slate-500 dark:text-slate-400"><span>Statut sync</span><span class="badge ${s.sync_status === 'success' ? 'badge-green' : 'badge-gray'}">${s.sync_status || '—'}</span></div>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <button onclick="syncOrders(${s.id})" class="btn btn-secondary btn-sm text-xs w-full justify-center">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Sync commandes
          </button>
          <button onclick="syncStock(${s.id})" class="btn btn-secondary btn-sm text-xs w-full justify-center">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
            Sync stock
          </button>
        </div>
      </div>
    `).join('');
  } catch(e) { grid.innerHTML = `<div class="col-span-3 text-center text-red-400 py-8">Erreur: ${e.message}</div>`; }
};

window.syncOrders = async function(siteId) {
  ERP.toast('Synchronisation commandes en cours...', 'info');
  try {
    const res = await ERP.post(`woo/sites/${siteId}/sync-orders`);
    ERP.toast(`${res.imported} commandes importées`, 'success');
    fetchWooSites();
  } catch(e) { ERP.toast(e.message, 'error'); }
};

window.syncStock = async function(siteId) {
  ERP.toast('Synchronisation stock en cours...', 'info');
  try {
    const res = await ERP.post(`woo/sites/${siteId}/sync-stock`);
    ERP.toast(`${res.updated} produits mis à jour`, 'success');
    fetchWooSites();
  } catch(e) { ERP.toast(e.message, 'error'); }
};

window.showAddSiteModal = function() { document.getElementById('add-site-modal').style.display = 'flex'; };
window.submitWooSite = async function(e) {
  e.preventDefault();
  const btn = e.target.querySelector('[type=submit]');
  btn.disabled = true; btn.textContent = 'Connexion...';
  try {
    await ERP.post('woo/sites', Object.fromEntries(new FormData(e.target)));
    ERP.toast('Site WooCommerce connecté', 'success');
    document.getElementById('add-site-modal').style.display = 'none';
    e.target.reset(); fetchWooSites();
  } catch(err) { ERP.toast(err.message, 'error'); }
  finally { btn.disabled = false; btn.textContent = 'Connecter'; }
};
