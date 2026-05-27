// ============================================================
// ERP PRO - Orders Page
// ============================================================
window.load_orders = async function() {
  const app = document.querySelector('[x-data]').__x.$data;
  app.pages.orders = `
  <div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div><h2 class="text-2xl font-bold text-slate-900 dark:text-white">Commandes</h2><p class="text-slate-500 text-sm mt-0.5" id="orders-count"></p></div>
      <div class="flex gap-2">
        <select id="filter-order-status" onchange="fetchOrders()" class="form-input form-select text-sm w-auto">
          <option value="">Tous statuts</option>
          <option value="pending">En attente</option>
          <option value="processing">En cours</option>
          <option value="completed">Terminé</option>
          <option value="cancelled">Annulé</option>
        </select>
        <select id="filter-order-source" onchange="fetchOrders()" class="form-input form-select text-sm w-auto">
          <option value="">Toutes sources</option>
          <option value="pos">POS</option>
          <option value="woo">WooCommerce</option>
          <option value="phone">Téléphone</option>
        </select>
        <div class="search-box">
          <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input id="order-search" type="text" placeholder="N° commande, client..." class="w-48 text-sm" oninput="debounceOrderSearch()" />
        </div>
      </div>
    </div>
    <div class="card p-0 overflow-hidden">
      <div class="table-wrapper">
        <table class="data-table">
          <thead><tr>
            <th>N° Commande</th><th>Client</th><th>Source</th><th>Articles</th><th>Total</th><th>Paiement</th><th>Statut</th><th>Date</th><th class="text-right">Actions</th>
          </tr></thead>
          <tbody id="orders-tbody"><tr><td colspan="9" class="py-12 text-center text-slate-400"><div class="loading-spinner mx-auto mb-2"></div>Chargement...</td></tr></tbody>
        </table>
      </div>
      <div id="orders-pagination" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 dark:border-slate-800"></div>
    </div>
  </div>`;
  setTimeout(fetchOrders, 50);
};

let _ordersPage = 1;
window.fetchOrders = async function() {
  const tbody = document.getElementById('orders-tbody');
  try {
    const res = await ERP.get('orders', {
      page: _ordersPage, per_page: 25,
      search: document.getElementById('order-search')?.value || '',
      status: document.getElementById('filter-order-status')?.value || '',
      source: document.getElementById('filter-order-source')?.value || '',
    });
    const orders = res?.data || [];
    const meta   = res?.meta || {};
    const el = document.getElementById('orders-count');
    if (el) el.textContent = `${ERP.number(meta.total || 0)} commande${meta.total > 1 ? 's' : ''}`;

    const srcLabels = { pos: '🏪 POS', woo: '🛒 WooCommerce', web: '🌐 Web', phone: '📞 Téléphone', manual: '✍️ Manuel' };
    const payLabels = { cash: '💵 Espèces', card: '💳 Carte', transfer: '🏦 Virement', online: '🌐 En ligne', mixed: '🔀 Mixte' };

    tbody.innerHTML = orders.map(o => `
      <tr>
        <td><span class="font-medium text-blue-500 cursor-pointer hover:underline text-sm" onclick="viewOrder(${o.id})">${o.order_number}</span></td>
        <td><p class="text-sm font-medium text-slate-700 dark:text-slate-200">${o.customer_name || 'Anonyme'}</p><p class="text-xs text-slate-400">${o.customer_phone || ''}</p></td>
        <td><span class="text-xs">${srcLabels[o.source] || o.source}</span></td>
        <td><span class="text-sm text-slate-600 dark:text-slate-400">—</span></td>
        <td><span class="font-semibold text-slate-800 dark:text-white text-sm">${ERP.currency(o.total)}</span></td>
        <td><span class="text-xs">${payLabels[o.payment_method] || o.payment_method}</span></td>
        <td>${ERP.statusBadge(o.status, 'order')}</td>
        <td><span class="text-xs text-slate-400">${ERP.date(o.created_at, 'long')}</span></td>
        <td>
          <div class="flex items-center justify-end gap-1">
            <button onclick="viewOrder(${o.id})" class="btn btn-icon btn-secondary btn-sm" title="Voir">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
            ${o.status !== 'cancelled' ? `<button onclick="cancelOrder(${o.id})" class="btn btn-icon btn-danger btn-sm" title="Annuler">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>` : ''}
          </div>
        </td>
      </tr>
    `).join('') || '<tr><td colspan="9" class="py-12 text-center text-slate-400">Aucune commande</td></tr>';

    document.getElementById('orders-pagination').innerHTML = `
      <span class="text-sm text-slate-500">${meta.from || 0}–${meta.to || 0} sur ${meta.total || 0}</span>
      <div class="flex gap-1">
        <button class="page-btn" ${_ordersPage <= 1 ? 'disabled' : ''} onclick="_ordersPage--;fetchOrders()">‹</button>
        <button class="page-btn" ${_ordersPage >= meta.last_page ? 'disabled' : ''} onclick="_ordersPage++;fetchOrders()">›</button>
      </div>`;
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="9" class="py-8 text-center text-red-400">Erreur: ${e.message}</td></tr>`;
  }
};

window.viewOrder = async function(id) {
  try {
    const o = await ERP.get(`orders/${id}`);
    const items = o.items.map(i => `<div class="flex justify-between text-sm py-1 border-b border-slate-100 dark:border-slate-700 last:border-0"><span>${i.product_name} x${i.quantity}</span><span class="font-medium">${ERP.currency(i.total)}</span></div>`).join('');
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `<div class="modal max-w-lg w-full"><div class="p-6 border-b border-slate-100 dark:border-slate-700 flex justify-between"><div><h3 class="font-semibold text-slate-900 dark:text-white">${o.order_number}</h3><p class="text-xs text-slate-400 mt-0.5">${ERP.date(o.created_at,'long')}</p></div><button onclick="this.closest('.modal-overlay').remove()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">✕</button></div><div class="p-6 space-y-4"><div class="grid grid-cols-2 gap-3 text-sm"><div><p class="text-xs text-slate-400">Client</p><p class="font-medium text-slate-700 dark:text-slate-200">${o.customer_name || 'Anonyme'}</p></div><div><p class="text-xs text-slate-400">Statut</p>${ERP.statusBadge(o.status,'order')}</div><div><p class="text-xs text-slate-400">Magasin</p><p class="font-medium text-slate-700 dark:text-slate-200">${o.warehouse_name || '—'}</p></div><div><p class="text-xs text-slate-400">Paiement</p><p class="font-medium">${o.payment_method}</p></div></div><div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3">${items}</div><div class="flex justify-between font-bold text-lg text-slate-900 dark:text-white"><span>Total</span><span>${ERP.currency(o.total)}</span></div></div></div>`;
    document.body.appendChild(modal);
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
  } catch(e) { ERP.toast('Erreur', 'error'); }
};

window.cancelOrder = async function(id) {
  const ok = await ERP.confirm('Annuler cette commande ?');
  if (!ok) return;
  try { await ERP.patch(`orders/${id}/status`, { status: 'cancelled' }); ERP.toast('Commande annulée', 'success'); fetchOrders(); }
  catch(e) { ERP.toast(e.message, 'error'); }
};

window.debounceOrderSearch = ERP.debounce(() => { _ordersPage = 1; fetchOrders(); }, 400);
