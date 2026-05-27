// ============================================================
// ERP PRO - Deliveries Page
// ============================================================
window.load_deliveries = async function() {
  const app = document.querySelector('[x-data]').__x.$data;
  app.pages.deliveries = `
  <div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div><h2 class="text-2xl font-bold text-slate-900 dark:text-white">Livraisons</h2><p class="text-slate-500 text-sm mt-0.5" id="del-count"></p></div>
    </div>
    <div class="card mb-4"><div class="flex flex-wrap gap-3">
      <select id="filter-del-status" onchange="fetchDeliveries()" class="form-input form-select text-sm w-auto">
        <option value="">Tous statuts</option>
        <option value="preparing">Préparation</option>
        <option value="shipped">Expédiée</option>
        <option value="in_delivery">En livraison</option>
        <option value="delivered">Livrée</option>
        <option value="returned">Retournée</option>
      </select>
      <div class="search-box flex-1"><svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg><input id="del-search" type="text" placeholder="N° livraison, ville, téléphone..." class="w-full text-sm" oninput="debounceDelSearch()" /></div>
    </div></div>
    <div class="card p-0 overflow-hidden">
      <div class="table-wrapper"><table class="data-table">
        <thead><tr><th>N° Livraison</th><th>Commande</th><th>Client</th><th>Adresse</th><th>Livreur</th><th>Zone</th><th>Statut</th><th>Date</th><th class="text-right">Actions</th></tr></thead>
        <tbody id="deliveries-tbody"><tr><td colspan="9" class="py-12 text-center text-slate-400"><div class="loading-spinner mx-auto mb-2"></div>Chargement...</td></tr></tbody>
      </table></div>
      <div id="deliveries-pagination" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 dark:border-slate-800"></div>
    </div>
  </div>`;
  setTimeout(fetchDeliveries, 50);
};

let _delsPage = 1;
window.fetchDeliveries = async function() {
  const tbody = document.getElementById('deliveries-tbody');
  try {
    const res  = await ERP.get('deliveries', { page: _delsPage, per_page: 25, status: document.getElementById('filter-del-status')?.value||'', search: document.getElementById('del-search')?.value||'' });
    const dels = res?.data || [];
    const meta = res?.meta || {};
    const el = document.getElementById('del-count');
    if (el) el.textContent = `${ERP.number(meta.total||0)} livraison(s)`;
    tbody.innerHTML = dels.map(d => `
      <tr>
        <td><span class="text-sm font-medium text-blue-500">${d.delivery_number}</span></td>
        <td><span class="text-sm text-slate-600 dark:text-slate-400">${d.order_number}</span></td>
        <td><span class="text-sm">${d.customer_name||'—'}</span></td>
        <td><span class="text-sm truncate max-w-[150px] block">${d.city||''} ${d.address?.slice(0,30)||'—'}</span></td>
        <td><span class="text-sm">${d.driver_name||'—'}</span></td>
        <td><span class="text-sm">${d.zone_name||'—'}</span></td>
        <td>${ERP.statusBadge(d.status,'delivery')}</td>
        <td><span class="text-xs text-slate-400">${ERP.date(d.created_at,'long')}</span></td>
        <td><div class="flex items-center justify-end gap-1">
          <select onchange="updateDelStatus(${d.id},this.value)" class="form-input form-select text-xs py-1 w-auto">
            <option value="">Changer statut</option>
            <option value="shipped">Expédiée</option>
            <option value="in_delivery">En livraison</option>
            <option value="delivered">Livrée ✓</option>
            <option value="returned">Retournée</option>
          </select>
        </div></td>
      </tr>
    `).join('') || '<tr><td colspan="9" class="py-12 text-center text-slate-400">Aucune livraison</td></tr>';
    document.getElementById('deliveries-pagination').innerHTML = `<span class="text-sm text-slate-500">${meta.from||0}–${meta.to||0} sur ${meta.total||0}</span><div class="flex gap-1"><button class="page-btn" ${_delsPage<=1?'disabled':''} onclick="_delsPage--;fetchDeliveries()">‹</button><button class="page-btn" ${_delsPage>=(meta.last_page||1)?'disabled':''} onclick="_delsPage++;fetchDeliveries()">›</button></div>`;
  } catch(e) { tbody.innerHTML = `<tr><td colspan="9" class="py-8 text-center text-red-400">Erreur: ${e.message}</td></tr>`; }
};

window.updateDelStatus = async function(id, status) {
  if (!status) return;
  try {
    await ERP.patch(`deliveries/${id}/status`, { status });
    ERP.toast('Statut mis à jour', 'success');
    fetchDeliveries();
  } catch(e) { ERP.toast(e.message, 'error'); }
};

window.debounceDelSearch = ERP.debounce(() => { _delsPage = 1; fetchDeliveries(); }, 400);
