// ============================================================
// ERP PRO - Customers / CRM Page
// ============================================================
window.load_customers = async function() {
  const app = document.querySelector('[x-data]').__x.$data;
  app.pages.customers = `
  <div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div><h2 class="text-2xl font-bold text-slate-900 dark:text-white">Clients & CRM</h2><p class="text-slate-500 text-sm mt-0.5" id="cust-count"></p></div>
      <button onclick="showAddCustomerModal()" class="btn btn-primary self-start">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
        Nouveau client
      </button>
    </div>
    <div class="card mb-4"><div class="flex gap-3">
      <div class="search-box flex-1 min-w-[200px]"><svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg><input id="cust-search" type="text" placeholder="Nom, téléphone, email..." class="w-full text-sm" oninput="debounceCustSearch()" /></div>
    </div></div>
    <div class="card p-0 overflow-hidden">
      <div class="table-wrapper"><table class="data-table">
        <thead><tr><th>Client</th><th>Téléphone</th><th>Ville</th><th>Commandes</th><th>Total dépensé</th><th>Points fidélité</th><th>Source</th><th class="text-right">Actions</th></tr></thead>
        <tbody id="customers-tbody"><tr><td colspan="8" class="py-12 text-center text-slate-400"><div class="loading-spinner mx-auto mb-2"></div>Chargement...</td></tr></tbody>
      </table></div>
      <div id="customers-pagination" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 dark:border-slate-800"></div>
    </div>
  </div>

  <!-- Add Customer Modal -->
  <div id="add-cust-modal" class="modal-overlay" style="display:none">
    <div class="modal max-w-lg w-full">
      <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
        <h3 class="font-semibold text-slate-900 dark:text-white">Nouveau client</h3>
        <button onclick="document.getElementById('add-cust-modal').style.display='none'" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">✕</button>
      </div>
      <form onsubmit="submitCustomer(event)" class="p-6 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div><label class="form-label">Prénom *</label><input name="first_name" type="text" class="form-input" required /></div>
          <div><label class="form-label">Nom *</label><input name="last_name" type="text" class="form-input" required /></div>
        </div>
        <div><label class="form-label">Téléphone</label><input name="phone" type="tel" class="form-input" placeholder="+212 6XX XXX XXX" /></div>
        <div><label class="form-label">WhatsApp</label><input name="whatsapp" type="tel" class="form-input" placeholder="+212 6XX XXX XXX" /></div>
        <div><label class="form-label">Email</label><input name="email" type="email" class="form-input" /></div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="form-label">Ville</label><input name="city" type="text" class="form-input" /></div>
          <div><label class="form-label">Date naissance</label><input name="birthday" type="date" class="form-input" /></div>
        </div>
        <div><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-input resize-none" placeholder="Notes internes..."></textarea></div>
        <div class="flex justify-end gap-3">
          <button type="button" onclick="document.getElementById('add-cust-modal').style.display='none'" class="btn btn-secondary">Annuler</button>
          <button type="submit" class="btn btn-primary">Créer</button>
        </div>
      </form>
    </div>
  </div>`;
  setTimeout(fetchCustomers, 50);
};

let _custsPage = 1;
window.fetchCustomers = async function() {
  const tbody = document.getElementById('customers-tbody');
  try {
    const res = await ERP.get('customers', { page: _custsPage, per_page: 25, search: document.getElementById('cust-search')?.value||'' });
    const customers = res?.data || [];
    const meta = res?.meta || {};
    const el = document.getElementById('cust-count');
    if (el) el.textContent = `${ERP.number(meta.total||0)} client(s)`;
    const srcLabels = { pos: '🏪 POS', woo: '🛒 WooCommerce', manual: '✍️ Manuel', web: '🌐 Web' };
    tbody.innerHTML = customers.map(c => `
      <tr>
        <td><div class="flex items-center gap-3"><div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-400 to-purple-400 flex items-center justify-center text-white text-xs font-bold">${((c.first_name||'?')[0]+(c.last_name||'?')[0]).toUpperCase()}</div><div><p class="text-sm font-medium text-slate-700 dark:text-slate-200">${c.first_name} ${c.last_name}</p>${c.email?`<p class="text-xs text-slate-400">${c.email}</p>`:''}</div></div></td>
        <td><span class="text-sm">${c.phone||'—'}</span>${c.whatsapp?`<br><span class="text-xs text-emerald-500">📱 WA: ${c.whatsapp}</span>`:''}</td>
        <td><span class="text-sm text-slate-500">${c.city||'—'}</span></td>
        <td><span class="font-medium text-slate-700 dark:text-slate-200">${ERP.number(c.order_count||0)}</span></td>
        <td><span class="font-semibold text-slate-800 dark:text-white">${ERP.currency(c.total_spent)}</span></td>
        <td><span class="badge badge-purple">${ERP.number(c.loyalty_points)} pts</span></td>
        <td><span class="text-xs">${srcLabels[c.source]||c.source}</span></td>
        <td><div class="flex items-center justify-end gap-1">
          <button onclick="viewCustomer(${c.id})" class="btn btn-icon btn-secondary btn-sm" title="Voir"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
          ${c.whatsapp?`<a href="https://wa.me/${c.whatsapp.replace(/\D/g,'')}" target="_blank" class="btn btn-icon btn-sm" style="background:#DCFCE7;color:#15803D" title="WhatsApp"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></a>` : ''}
        </div></td>
      </tr>
    `).join('') || '<tr><td colspan="8" class="py-12 text-center text-slate-400">Aucun client trouvé</td></tr>';
    document.getElementById('customers-pagination').innerHTML = `<span class="text-sm text-slate-500">${meta.from||0}–${meta.to||0} sur ${meta.total||0}</span><div class="flex gap-1"><button class="page-btn" ${_custsPage<=1?'disabled':''} onclick="_custsPage--;fetchCustomers()">‹</button><button class="page-btn" ${_custsPage>=(meta.last_page||1)?'disabled':''} onclick="_custsPage++;fetchCustomers()">›</button></div>`;
  } catch(e) { tbody.innerHTML = `<tr><td colspan="8" class="py-8 text-center text-red-400">Erreur: ${e.message}</td></tr>`; }
};

window.viewCustomer = async function(id) {
  try {
    const c = await ERP.get(`customers/${id}`);
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    const orders = c.orders.slice(0,5).map(o => `<div class="flex justify-between text-sm py-1.5 border-b last:border-0 border-slate-100 dark:border-slate-700"><span class="text-blue-500">${o.order_number}</span>${ERP.statusBadge(o.status,'order')}<span class="font-medium">${ERP.currency(o.total)}</span></div>`).join('');
    modal.innerHTML = `<div class="modal max-w-2xl w-full"><div class="p-6 border-b border-slate-100 dark:border-slate-700 flex justify-between"><div class="flex items-center gap-3"><div class="w-14 h-14 rounded-full bg-gradient-to-br from-blue-400 to-purple-400 flex items-center justify-center text-white text-xl font-bold">${((c.first_name||'?')[0]+(c.last_name||'?')[0]).toUpperCase()}</div><div><h3 class="font-semibold text-slate-900 dark:text-white text-lg">${c.first_name} ${c.last_name}</h3><p class="text-slate-400 text-sm">${c.phone||c.email||''}</p></div></div><button onclick="this.closest('.modal-overlay').remove()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg self-start">✕</button></div><div class="p-6 grid grid-cols-2 gap-4"><div class="col-span-2 grid grid-cols-3 gap-3">${[['Total dépensé',ERP.currency(c.total_spent),'💰'],['Commandes',ERP.number((c.orders||[]).length),'🛍️'],['Points fidélité',ERP.number(c.loyalty_points),'⭐']].map(([label,val,icon])=>`<div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3 text-center"><div class="text-xl mb-1">${icon}</div><div class="font-bold text-slate-800 dark:text-white text-sm">${val}</div><div class="text-xs text-slate-400">${label}</div></div>`).join('')}</div><div class="col-span-2"><h4 class="font-medium text-slate-700 dark:text-slate-200 mb-2 text-sm">Dernières commandes</h4><div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3">${orders||'<p class="text-slate-400 text-sm text-center py-2">Aucune commande</p>'}</div></div><div class="col-span-2"><div class="flex gap-2"><input id="new-note-${id}" type="text" class="form-input flex-1 text-sm" placeholder="Ajouter une note..." /><button onclick="addCustNote(${id})" class="btn btn-secondary btn-sm">Ajouter</button></div></div></div></div>`;
    document.body.appendChild(modal);
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
  } catch(e) { ERP.toast('Erreur', 'error'); }
};

window.addCustNote = async function(id) {
  const inp = document.getElementById(`new-note-${id}`);
  if (!inp?.value.trim()) return;
  try { await ERP.post(`customers/${id}/notes`, { note: inp.value }); inp.value = ''; ERP.toast('Note ajoutée', 'success'); }
  catch(e) { ERP.toast(e.message, 'error'); }
};

window.showAddCustomerModal = function() { document.getElementById('add-cust-modal').style.display = 'flex'; };
window.submitCustomer = async function(e) {
  e.preventDefault();
  try {
    const res = await ERP.post('customers', Object.fromEntries(new FormData(e.target)));
    ERP.toast('Client créé', 'success');
    document.getElementById('add-cust-modal').style.display = 'none';
    e.target.reset(); fetchCustomers();
  } catch(err) { ERP.toast(err.message, 'error'); }
};
window.debounceCustSearch = ERP.debounce(() => { _custsPage = 1; fetchCustomers(); }, 400);
