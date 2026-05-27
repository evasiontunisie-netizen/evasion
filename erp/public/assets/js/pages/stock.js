// ============================================================
// ERP PRO - Stock & Transfers Page
// ============================================================
window.load_stock = async function() {
  const app = document.querySelector('[x-data]').__x.$data;
  app.pages.stock = `
  <div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div><h2 class="text-2xl font-bold text-slate-900 dark:text-white">Gestion du Stock</h2></div>
      <div class="flex gap-2">
        <button onclick="showAdjustModal()" class="btn btn-primary">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Ajustement stock
        </button>
        <button onclick="switchStockTab('movements')" class="btn btn-secondary">Mouvements</button>
        <button onclick="switchStockTab('inventory')" class="btn btn-secondary">Inventaire</button>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-5 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl w-fit">
      ${[['stock-tab','Stock'],['movements-tab','Mouvements'],['inventory-tab','Inventaire']].map(([id,label]) => `
        <button id="${id}" onclick="switchStockTab('${id.replace('-tab','')}')" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors ${id==='stock-tab'?'bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow':'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'}">${label}</button>
      `).join('')}
    </div>

    <!-- Stock View -->
    <div id="view-stock">
      <div class="card mb-4"><div class="flex flex-wrap gap-3">
        <select id="stock-warehouse" onchange="fetchStock()" class="form-input form-select text-sm w-auto">
          <option value="">Tous les entrepôts</option>
          <option value="1">Showroom Casa</option>
          <option value="2">Showroom Rabat</option>
          <option value="3">Dépôt</option>
          <option value="4">Stock Web</option>
        </select>
        <div class="search-box flex-1"><svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg><input id="stock-search" type="text" placeholder="Nom, SKU, barcode..." class="w-full text-sm" oninput="debounceStockSearch()" /></div>
      </div></div>
      <div class="card p-0 overflow-hidden">
        <div class="table-wrapper"><table class="data-table">
          <thead><tr><th>Produit</th><th>SKU</th><th>Entrepôt</th><th>Quantité</th><th>Réservé</th><th>Disponible</th><th>Stock min.</th><th>Statut</th></tr></thead>
          <tbody id="stock-tbody"><tr><td colspan="8" class="py-12 text-center text-slate-400"><div class="loading-spinner mx-auto mb-2"></div>Chargement...</td></tr></tbody>
        </table></div>
        <div id="stock-pagination" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 dark:border-slate-800"></div>
      </div>
    </div>

    <!-- Movements View -->
    <div id="view-movements" class="hidden">
      <div class="card p-0 overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex gap-3">
          <select id="mov-type" onchange="fetchMovements()" class="form-input form-select text-sm w-auto">
            <option value="">Tous types</option>
            <option value="in">Entrée</option><option value="out">Sortie</option>
            <option value="sale">Vente</option><option value="transfer_in">Transfert entrant</option>
            <option value="transfer_out">Transfert sortant</option><option value="adjustment">Ajustement</option>
          </select>
          <input type="date" id="mov-from" class="form-input text-sm w-auto" onchange="fetchMovements()" />
          <input type="date" id="mov-to"   class="form-input text-sm w-auto" onchange="fetchMovements()" />
        </div>
        <div class="table-wrapper"><table class="data-table">
          <thead><tr><th>Produit</th><th>Entrepôt</th><th>Type</th><th>Qté avant</th><th>Mouvement</th><th>Qté après</th><th>Par</th><th>Date</th></tr></thead>
          <tbody id="movements-tbody"><tr><td colspan="8" class="py-8 text-center text-slate-400">Sélectionnez une période</td></tr></tbody>
        </table></div>
      </div>
    </div>

    <!-- Inventory View -->
    <div id="view-inventory" class="hidden">
      <div class="card mb-4"><div class="flex gap-3">
        <select id="inv-warehouse" class="form-input form-select text-sm w-auto">
          <option value="1">Showroom Casa</option><option value="2">Showroom Rabat</option><option value="3">Dépôt</option>
        </select>
        <button onclick="fetchInventory()" class="btn btn-primary btn-sm">Charger inventaire</button>
        <button onclick="exportInventory()" class="btn btn-secondary btn-sm">Export Excel</button>
      </div></div>
      <div class="card p-0 overflow-hidden">
        <div class="table-wrapper"><table class="data-table">
          <thead><tr><th>Produit</th><th>SKU</th><th>Catégorie</th><th>Qté système</th><th>Qté réelle</th><th>Écart</th><th>Valeur stock</th></tr></thead>
          <tbody id="inventory-tbody"><tr><td colspan="7" class="py-8 text-center text-slate-400">Sélectionnez un entrepôt et chargez l'inventaire</td></tr></tbody>
        </table></div>
      </div>
    </div>
  </div>

  <!-- Adjust Modal -->
  <div id="adjust-modal" class="modal-overlay" style="display:none">
    <div class="modal max-w-md w-full">
      <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
        <h3 class="font-semibold text-slate-900 dark:text-white">Ajustement de stock</h3>
        <button onclick="document.getElementById('adjust-modal').style.display='none'" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">✕</button>
      </div>
      <form onsubmit="submitAdjust(event)" class="p-6 space-y-4">
        <div><label class="form-label">Produit *</label><input id="adj-product-search" type="text" class="form-input" placeholder="Rechercher un produit..." /><input type="hidden" id="adj-product-id" /></div>
        <div><label class="form-label">Entrepôt *</label><select name="warehouse_id" class="form-input form-select" required><option value="1">Showroom Casa</option><option value="2">Showroom Rabat</option><option value="3">Dépôt</option><option value="4">Stock Web</option></select></div>
        <div><label class="form-label">Type *</label><select name="type" class="form-input form-select" required><option value="in">Entrée (+)</option><option value="out">Sortie (-)</option><option value="adjustment">Réglage absolu</option></select></div>
        <div><label class="form-label">Quantité *</label><input name="quantity" type="number" min="1" class="form-input" placeholder="0" required /></div>
        <div><label class="form-label">Notes</label><input name="notes" type="text" class="form-input" placeholder="Motif..." /></div>
        <div class="flex justify-end gap-3">
          <button type="button" onclick="document.getElementById('adjust-modal').style.display='none'" class="btn btn-secondary">Annuler</button>
          <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
  `;
  setTimeout(fetchStock, 50);
};

window.switchStockTab = function(tab) {
  ['stock','movements','inventory'].forEach(t => {
    document.getElementById(`view-${t}`)?.classList.toggle('hidden', t !== tab);
    const btn = document.getElementById(`${t}-tab`);
    if (btn) btn.className = `px-4 py-2 rounded-lg text-sm font-medium transition-colors ${t === tab ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'}`;
  });
  if (tab === 'movements') fetchMovements();
};

let _stockPage = 1;
window.fetchStock = async function() {
  const tbody = document.getElementById('stock-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="8" class="py-8 text-center text-slate-400"><div class="loading-spinner mx-auto mb-2"></div>Chargement...</td></tr>';
  try {
    const res  = await ERP.get('stock', { page: _stockPage, per_page: 25, warehouse_id: document.getElementById('stock-warehouse')?.value||'', search: document.getElementById('stock-search')?.value||'' });
    const rows = res?.data || [];
    const meta = res?.meta || {};
    tbody.innerHTML = rows.map(r => {
      const available = parseInt(r.available||0);
      const isLow     = available <= parseInt(r.min_stock||5) && available > 0;
      const isOut     = available === 0;
      return `<tr>
        <td><span class="text-sm font-medium text-slate-700 dark:text-slate-200">${r.name}</span></td>
        <td><code class="text-xs bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">${r.sku||'—'}</code></td>
        <td><span class="text-sm">${r.warehouse_name}</span> <span class="text-xs text-slate-400">(${r.warehouse_code})</span></td>
        <td><span class="font-medium ${isOut?'text-red-500':isLow?'text-amber-500':'text-slate-700 dark:text-slate-200'}">${ERP.number(r.quantity)}</span></td>
        <td><span class="text-sm text-slate-500">${ERP.number(r.reserved_qty||0)}</span></td>
        <td><span class="font-bold ${isOut?'text-red-500':isLow?'text-amber-500':'text-emerald-500'}">${ERP.number(available)}</span></td>
        <td><span class="text-sm text-slate-400">${ERP.number(r.min_stock||5)}</span></td>
        <td>${isOut?'<span class="badge badge-red">Rupture</span>':isLow?'<span class="badge badge-yellow">⚠ Faible</span>':'<span class="badge badge-green">OK</span>'}</td>
      </tr>`;
    }).join('') || '<tr><td colspan="8" class="py-12 text-center text-slate-400">Aucun stock trouvé</td></tr>';
    document.getElementById('stock-pagination').innerHTML = `<span class="text-sm text-slate-500">${meta.from||0}–${meta.to||0} sur ${meta.total||0}</span><div class="flex gap-1"><button class="page-btn" ${_stockPage<=1?'disabled':''} onclick="_stockPage--;fetchStock()">‹</button><button class="page-btn" ${_stockPage>=(meta.last_page||1)?'disabled':''} onclick="_stockPage++;fetchStock()">›</button></div>`;
  } catch(e) { tbody.innerHTML = `<tr><td colspan="8" class="py-8 text-center text-red-400">Erreur: ${e.message}</td></tr>`; }
};

window.fetchMovements = async function() {
  const tbody = document.getElementById('movements-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="8" class="py-8 text-center text-slate-400"><div class="loading-spinner mx-auto mb-2"></div>Chargement...</td></tr>';
  const typeLabels = { in: '📥 Entrée', out: '📤 Sortie', sale: '🛍️ Vente', transfer_in: '➡️ Trans. entrant', transfer_out: '⬅️ Trans. sortant', adjustment: '🔧 Ajustement', purchase: '🏭 Achat', return: '↩️ Retour' };
  try {
    const res  = await ERP.get('stock/movements', { per_page: 30, type: document.getElementById('mov-type')?.value||'', date_from: document.getElementById('mov-from')?.value||'', date_to: document.getElementById('mov-to')?.value||'' });
    const rows = res?.data || [];
    tbody.innerHTML = rows.map(m => `
      <tr>
        <td><span class="text-sm font-medium text-slate-700 dark:text-slate-200">${m.product_name}</span><br><code class="text-xs text-slate-400">${m.sku||''}</code></td>
        <td><span class="text-sm">${m.warehouse_name}</span></td>
        <td><span class="text-sm">${typeLabels[m.type]||m.type}</span></td>
        <td><span class="text-sm text-slate-500">${ERP.number(m.qty_before)}</span></td>
        <td><span class="font-medium ${m.type.includes('out')||m.type==='sale'?'text-red-500':'text-emerald-500'}">${m.type.includes('out')||m.type==='sale'?'-':'+'}${ERP.number(m.quantity)}</span></td>
        <td><span class="text-sm font-medium text-slate-700 dark:text-slate-200">${ERP.number(m.qty_after)}</span></td>
        <td><span class="text-sm text-slate-500">${m.first_name||'—'} ${m.last_name||''}</span></td>
        <td><span class="text-xs text-slate-400">${ERP.date(m.created_at,'long')}</span></td>
      </tr>
    `).join('') || '<tr><td colspan="8" class="py-8 text-center text-slate-400">Aucun mouvement</td></tr>';
  } catch(e) { tbody.innerHTML = `<tr><td colspan="8" class="py-8 text-center text-red-400">Erreur</td></tr>`; }
};

window.fetchInventory = async function() {
  const tbody = document.getElementById('inventory-tbody');
  if (!tbody) return;
  const wid = document.getElementById('inv-warehouse')?.value || 1;
  tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-slate-400"><div class="loading-spinner mx-auto mb-2"></div>Chargement...</td></tr>';
  try {
    const rows = await ERP.get('stock/inventory', { warehouse_id: wid });
    tbody.innerHTML = (rows||[]).map(r => `
      <tr>
        <td><span class="text-sm font-medium text-slate-700 dark:text-slate-200">${r.name}</span></td>
        <td><code class="text-xs">${r.sku||'—'}</code></td>
        <td><span class="text-xs text-slate-400">${r.category_name||'—'}</span></td>
        <td><input type="number" value="${r.system_qty}" class="form-input text-sm text-center w-20 py-1" readonly /></td>
        <td><input type="number" id="inv-real-${r.id}" value="${r.system_qty}" class="form-input text-sm text-center w-20 py-1" /></td>
        <td><span id="inv-diff-${r.id}" class="text-sm font-medium text-slate-400">0</span></td>
        <td><span class="text-sm">${ERP.currency(r.system_qty * r.purchase_price)}</span></td>
      </tr>
    `).join('') || '<tr><td colspan="7" class="py-8 text-center text-slate-400">Aucun produit</td></tr>';

    // Calculate diffs
    (rows||[]).forEach(r => {
      const inp  = document.getElementById(`inv-real-${r.id}`);
      const diff = document.getElementById(`inv-diff-${r.id}`);
      if (inp && diff) {
        inp.addEventListener('input', () => {
          const d = parseInt(inp.value) - parseInt(r.system_qty);
          diff.textContent = (d > 0 ? '+' : '') + d;
          diff.className = `text-sm font-medium ${d > 0 ? 'text-emerald-500' : d < 0 ? 'text-red-500' : 'text-slate-400'}`;
        });
      }
    });
  } catch(e) { tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-red-400">Erreur</td></tr>`; }
};

window.showAdjustModal = function() { document.getElementById('adjust-modal').style.display = 'flex'; };
window.submitAdjust = async function(e) {
  e.preventDefault();
  const data = Object.fromEntries(new FormData(e.target));
  const pid  = document.getElementById('adj-product-id')?.value;
  if (!pid) { ERP.toast('Sélectionnez un produit', 'warning'); return; }
  data.product_id = pid;
  try {
    const res = await ERP.post('stock/adjust', data);
    ERP.toast(`Stock mis à jour: ${ERP.number(res.qty_before)} → ${ERP.number(res.qty_after)}`, 'success');
    document.getElementById('adjust-modal').style.display = 'none';
    e.target.reset();
    fetchStock();
  } catch(err) { ERP.toast(err.message, 'error'); }
};

// Product search for adjust modal
document.addEventListener('input', ERP.debounce(async e => {
  if (e.target.id !== 'adj-product-search') return;
  const q = e.target.value.trim();
  if (!q) return;
  try {
    const res  = await ERP.get('products', { search: q, per_page: 10, active: 1 });
    const prods= res?.data || [];
    // Simple dropdown
    let dd = document.getElementById('adj-product-dd');
    if (!dd) {
      dd = document.createElement('div');
      dd.id = 'adj-product-dd';
      dd.className = 'absolute z-50 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg max-h-48 overflow-y-auto';
      e.target.parentElement.style.position = 'relative';
      e.target.parentElement.appendChild(dd);
    }
    dd.innerHTML = prods.map(p => `<div class="px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer text-sm" onclick="document.getElementById('adj-product-search').value='${p.name.replace(/'/g,"&#39;")}';document.getElementById('adj-product-id').value='${p.id}';this.parentElement.remove()">${p.name} <span class='text-xs text-slate-400'>${p.sku||''}</span></div>`).join('');
  } catch {}
}, 300));

window.debounceStockSearch = ERP.debounce(() => { _stockPage = 1; fetchStock(); }, 400);
window.exportInventory = function() { ERP.toast('Export en cours...', 'info'); };
